<?php
/**
 * cron/minutes/5/self_monitor_admin_errors.php
 *
 * Standalone self-monitor: detects new admin error patterns in this host's
 * own admin error_log and notifies the app's admin users. Runs every 5 min.
 *
 * Why this exists: each deployed admin has its own error_log. When nokemo.com
 * aggregates errors across apps it picks these up via its monitoring cron and
 * creates tasks for the builder — but if nokemo is offline or unreachable,
 * errors would pile up with nothing watching. This cron is the standalone
 * fallback: each app's admin surfaces its own errors to its own admin users
 * via the existing notification/email system. No dependency on nokemo, no
 * new schema, no new tables.
 *
 * Design:
 *   1. Read unresolved admin error_log rows via $db (READ ONLY from child).
 *   2. Group by normalized message pattern (same regex used by nokemo's
 *      monitoring path so fingerprints match).
 *   3. State file ($files_location/self_monitor/admin_errors_state.json): per pattern
 *      {ts: last alert, seen: last_seen_at it covered}, plus _digest_at.
 *   4. ERROR/FATAL patterns that are new or have RECURRED since their last alert go
 *      into ONE digest notification (category 'admin_error_alert') to every admin,
 *      at most once an hour. Info/warnings never push (owner 2026-09-29: the old
 *      per-pattern, every-24h-forever policy was a steady stream of pushes).
 *   5. Update the state file.
 *
 * Rules followed:
 *   - cron/cron.php already bootstrapped common.php — do not re-require.
 *   - $db is READ ONLY from child context. We only SELECT from error_log.
 *   - Idempotent: running twice in a row is a no-op (state file blocks it).
 *   - Fail-soft: any exception is logged to error_log() and the cron exits
 *     cleanly. This cron must never crash the overall cron.php run.
 */

global $db, $files_location;

if (!$db) {
    error_log('self_monitor_admin_errors: $db not available, skipping.');
    return;
}
if (!function_exists('send_notification')) {
    error_log('self_monitor_admin_errors: send_notification() not available, skipping.');
    return;
}

// ─── State file ─────────────────────────────────────────────────────────────
// Lives above webroot alongside uploads (never in rsync deploy scope).
$state_dir  = isset($files_location) && $files_location
    ? rtrim($files_location, '/') . '/self_monitor'
    : __DIR__ . '/../../../../files/self_monitor';
$state_file = $state_dir . '/admin_errors_state.json';

if (!is_dir($state_dir)) {
    @mkdir($state_dir, 0755, true);
}

$state = [];
if (is_file($state_file)) {
    $raw = @file_get_contents($state_file);
    $decoded = $raw ? json_decode($raw, true) : [];
    if (is_array($decoded)) $state = $decoded;
}
// Structure: { pattern_fingerprint: last_notified_unix_ts }

$now_ts = time();

// ─── Pull open admin errors ─────────────────────────────────────────────────
try {
    $stmt = $db->prepare(
        "SELECT error_id, level, message, file, line, source_app, occurrence_count, last_seen_at, created
         FROM error_log
         WHERE resolved_at IS NULL
         ORDER BY last_seen_at DESC
         LIMIT 200"
    );
    $stmt->execute();
    $errors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('self_monitor_admin_errors: error_log query failed: ' . $e->getMessage());
    return;
}

if (empty($errors)) {
    echo "    self-monitor: no open admin errors.\n";
    return;
}

// ─── Group by normalized pattern (same logic as nokemo monitor) ─────────────
$groups = [];
foreach ($errors as $err) {
    $msg = $err['message'] ?? '';
    $key = preg_replace('/\d+/', '#', $msg);
    $key = preg_replace('/\/[^\s]+\.(mp3|wav|mp4|jpg|png|gif|svg|css|js|php|json)/', '/...file', $key);
    $key = mb_substr(trim($key), 0, 120);
    if ($key === '') $key = '__unknown__';
    $groups[$key][] = $err;
}

// ─── Find admin users to notify ─────────────────────────────────────────────
// Queried once up front so we don't repeat the same user lookup per group.
$admin_users = [];
try {
    $ustmt = $db->query("SELECT user_id, shard_id FROM user WHERE is_admin = 1");
    $admin_users = $ustmt ? $ustmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Exception $e) {
    error_log('self_monitor_admin_errors: user query failed: ' . $e->getMessage());
    return;
}

if (empty($admin_users)) {
    echo "    self-monitor: no admin users to notify.\n";
    return;
}

// ─── Notify: errors only, only when they happen again, one digest, ≤1/hour ─────
// Owner 2026-09-29: "I'm getting pretty regular admin warnings through push
// notifications". The old loop pushed EVERY unresolved pattern (info and warnings
// included — 404s, week-old needs-build warnings, a Sep 17 billing test) once per
// pattern and again every 24h forever. Now:
//   - ERROR / FATAL only (info + warnings stay in the error log and nokemo's monitor);
//   - a pattern alerts when first seen, then only if it RECURRED since its last alert
//     (last_seen_at moved), never just because nobody resolved it;
//   - one digest notification per run, and at most one per hour (_digest_at).
$min_digest_gap = 3600;
// Which app this is, for the push title (the file is shared by every child app).
$app_slug = basename(dirname(__DIR__, 3));
$app_name = '';
if (function_exists('get_branding')) {
    $b = get_branding();
    $app_name = trim((string) ($b['site_name'] ?? ''));
    if ($app_name === 'Admin') $app_name = '';
}
if ($app_name === '') $app_name = $app_slug !== '' ? ucfirst($app_slug) : 'App';
$due = [];
foreach ($groups as $pattern => $group_errors) {
    $sample = $group_errors[0];
    $level  = strtoupper($sample['level'] ?? 'ERROR');
    if (!in_array($level, ['ERROR', 'FATAL', 'CRITICAL'], true)) continue;
    $fingerprint = hash('sha256', $pattern . '|' . $level);
    $seen = strtotime((string) ($sample['last_seen_at'] ?? $sample['created'] ?? '')) ?: 0;
    $prev = $state[$fingerprint] ?? null;
    // Old state format is the bare notify time: it counts as "seen up to then".
    $prev_seen = is_array($prev) ? (int) ($prev['seen'] ?? 0) : (int) $prev;
    if ($prev !== null && $seen <= $prev_seen) continue;   // nothing new since the last alert
    $due[$fingerprint] = ['sample' => $sample, 'level' => $level, 'count' => array_sum(array_map(function ($e) { return (int) ($e['occurrence_count'] ?? 1); }, $group_errors)), 'seen' => $seen];
}
$last_digest = (int) ($state['_digest_at'] ?? 0);
if ($due && ($now_ts - $last_digest) >= $min_digest_gap) {
    $n = count($due);
    $title = $app_name . ': ' . $n . ' new error' . ($n === 1 ? '' : 's');
    $lines = [];
    foreach (array_slice($due, 0, 5) as $d) {
        $lines[] = '[' . $d['level'] . '] ' . mb_substr(trim((string) ($d['sample']['message'] ?? '')), 0, 110) . ' (×' . $d['count'] . ')';
    }
    if ($n > 5) $lines[] = '…and ' . ($n - 5) . ' more.';
    $body = implode("\n", $lines) . "\n\nOpen the admin Error Log for details.";
    foreach ($admin_users as $u) {
        try {
            send_notification((int) $u['user_id'], (string) $u['shard_id'], 'admin_error_alert', $title, $body,
                ['source_app' => $app_slug, 'error_id' => (int) (reset($due)['sample']['error_id'] ?? 0)]);
        } catch (Exception $e) {
            error_log('self_monitor_admin_errors: send_notification failed for user ' . $u['user_id'] . ': ' . $e->getMessage());
        }
    }
    foreach ($due as $fp => $d) $state[$fp] = ['ts' => $now_ts, 'seen' => $d['seen']];
    $state['_digest_at'] = $now_ts;
    $notified = $n; $skipped = 0;
} else {
    $notified = 0; $skipped = count($due);
}

// ─── Persist state ──────────────────────────────────────────────────────────
// Prune stale fingerprints so the file doesn't grow unbounded — anything
// older than 30 days is dropped.
$cutoff = $now_ts - (30 * 86400);
foreach ($state as $fp => $v) {
    if ($fp === '_digest_at') continue;
    $ts = is_array($v) ? (int) ($v['ts'] ?? 0) : (int) $v;
    if ($ts < $cutoff) unset($state[$fp]);
}

@file_put_contents($state_file, json_encode($state, JSON_PRETTY_PRINT), LOCK_EX);

echo "    self-monitor: {$notified} new error pattern(s) in one digest, {$skipped} waiting for the hourly gap.\n";

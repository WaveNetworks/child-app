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
 *   3. Dedup against a JSON state file at $files_location/self_monitor_state.json
 *      — track last-notified timestamp per pattern fingerprint. Only re-notify
 *      if 24h has elapsed since the last notification for that fingerprint.
 *   4. For each new or re-notify-due pattern: send_notification() to every
 *      admin-role user, category 'admin_error_alert'. The notification body
 *      summarizes the error level, occurrence count, sample message, and the
 *      most recent error_id so a human can jump straight to the admin error
 *      log view.
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

$renotify_after_seconds = 86400; // 24h — don't spam the same pattern
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

// ─── Notify new/re-due patterns ─────────────────────────────────────────────
$notified = 0;
$skipped  = 0;

foreach ($groups as $pattern => $group_errors) {
    $count  = count($group_errors);
    $sample = $group_errors[0];
    $level  = strtoupper($sample['level'] ?? 'ERROR');
    $msg    = trim($sample['message'] ?? 'Unknown error');
    $src    = $sample['source_app'] ?? 'admin';

    $fingerprint = hash('sha256', $pattern . '|' . $level);

    // Dedup: skip if notified within the re-notify window
    if (isset($state[$fingerprint]) && ($now_ts - (int)$state[$fingerprint]) < $renotify_after_seconds) {
        $skipped++;
        continue;
    }

    $short_msg = mb_substr($msg, 0, 120);
    $title = "Admin error: [{$level}] {$short_msg}";
    $title = mb_substr($title, 0, 140);

    $body  = "A new admin error pattern has been detected on this host.\n\n";
    $body .= "Level: {$level}\n";
    $body .= "Source: {$src}\n";
    $body .= "Occurrences in this group: {$count}\n";
    if (!empty($sample['file'])) {
        $body .= "File: {$sample['file']}" . (!empty($sample['line']) ? ":{$sample['line']}" : '') . "\n";
    }
    $body .= "Most recent error_id: " . (int)($sample['error_id'] ?? 0) . "\n";
    $body .= "Last seen: " . ($sample['last_seen_at'] ?? 'unknown') . "\n\n";
    $body .= "Review in the admin Error Log view. This alert was sent by the app's standalone self-monitor cron — no dependency on external monitoring.";

    foreach ($admin_users as $u) {
        try {
            send_notification(
                (int)$u['user_id'],
                (string)$u['shard_id'],
                'admin_error_alert',
                $title,
                $body,
                [
                    'source_app' => $src,
                    'error_id'   => (int)($sample['error_id'] ?? 0),
                ]
            );
        } catch (Exception $e) {
            error_log('self_monitor_admin_errors: send_notification failed for user '
                . $u['user_id'] . ': ' . $e->getMessage());
        }
    }

    $state[$fingerprint] = $now_ts;
    $notified++;
}

// ─── Persist state ──────────────────────────────────────────────────────────
// Prune stale fingerprints so the file doesn't grow unbounded — anything
// older than 30 days is dropped.
$cutoff = $now_ts - (30 * 86400);
foreach ($state as $fp => $ts) {
    if ((int)$ts < $cutoff) unset($state[$fp]);
}

@file_put_contents($state_file, json_encode($state, JSON_PRETTY_PRINT), LOCK_EX);

echo "    self-monitor: {$notified} pattern(s) notified, {$skipped} skipped (within re-notify window).\n";

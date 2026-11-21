<?php
/**
 * cron/cron.php — Child app cron entry point
 * Add to crontab (runs every minute):
 *   * * * * * /usr/bin/php /path/to/app/cron/cron.php >> /tmp/app_cron.log 2>&1
 *
 * Schedule structure:
 *   cron/minutes/{N}/*.php  — run every N minutes
 *   cron/days/*.php         — run ONCE per day (first run after 00:00 UTC)
 *   cron/weeks/*.php        — run ONCE per ISO week (first run on Monday)
 *   cron/months/*.php       — run ONCE per month (first run on the 1st)
 *
 * Until 2026-11-21 days/ ran on EVERY minute's invocation (~1440x a day) and weeks/ and
 * months/ ran every minute of their day (task #2818; core fixed the same bug 2026-10-20).
 * child_cron_once() now records each period under $files_location/cron_state/, so each
 * runs once. A job that really needs to run often belongs in minutes/{N}/.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo 'CLI only.';
    exit;
}

// Bootstrap: loads DB connections, config, helpers.
// Each job file also does require_once so this is safe (no double-load).
require_once __DIR__ . '/../include/common.php';

$minute  = (int)date('i');
$today   = (int)date('j');
$dow     = (int)date('N'); // 1=Monday … 7=Sunday
$cronDir = __DIR__;

echo "Cron started: " . date('Y-m-d H:i:s') . "\n";

/**
 * True the first time it is asked for $key in $period (e.g. 'days', '2026-11-21'), false
 * after. Same rule as core's cron_once(): a lock while deciding so two overlapping minute
 * runs never both start a job, and no writable state dir = skip, never a run every minute.
 * Keyed by app folder so sibling child apps included by core's cron never share a stamp.
 */
if (!function_exists('child_cron_once')) {
    function child_cron_once(string $app, string $key, string $period): bool {
        global $files_location;
        $dir = rtrim((string) ($files_location ?: sys_get_temp_dir()), '/') . '/cron_state/';
        if (!is_dir($dir)) { @mkdir($dir, 0700, true); }
        $fh = @fopen($dir . preg_replace('/[^A-Za-z0-9_.-]/', '_', 'child-' . $app . '-' . $key), 'c+');
        if (!$fh) { return false; }
        if (!flock($fh, LOCK_EX | LOCK_NB)) { fclose($fh); return false; }
        $last = trim((string) stream_get_contents($fh));
        $due = $last !== $period;
        if ($due) { ftruncate($fh, 0); rewind($fh); fwrite($fh, $period); fflush($fh); }
        flock($fh, LOCK_UN); fclose($fh);
        return $due;
    }
}
$cronApp = basename(dirname(__DIR__));

if (!function_exists('run_jobs')) {
function run_jobs(string $dir, string $label): void {
    if (!is_dir($dir)) return;
    foreach (glob($dir . '*.php') as $job) {
        echo "  Running " . $label . '/' . basename($job) . "\n";
        try {
            include($job);
        } catch (Throwable $e) {
            echo "    ERROR in " . basename($job) . ': ' . $e->getMessage() . "\n";
        }
    }
}
}

// Minute jobs — cron/minutes/{N}/*.php — runs every N minutes
$minuteDirs = glob($cronDir . '/minutes/*', GLOB_ONLYDIR) ?: [];
foreach ($minuteDirs as $mDir) {
    $interval = (int)basename($mDir);
    if ($interval > 0 && $minute % $interval === 0) {
        run_jobs($mDir . '/', 'minutes/' . $interval);
    }
}

// Daily jobs — cron/days/*.php — once a day, first run after 00:00 UTC
if (glob($cronDir . '/days/*.php') && child_cron_once($cronApp, 'days', gmdate('Y-m-d'))) {
    run_jobs($cronDir . '/days/', 'days');
}

// Weekly jobs — cron/weeks/*.php — once a week, first run on Monday (UTC)
if ((int) gmdate('N') === 1 && glob($cronDir . '/weeks/*.php') && child_cron_once($cronApp, 'weeks', gmdate('o-\\WW'))) {
    run_jobs($cronDir . '/weeks/', 'weeks');
}

// Monthly jobs — cron/months/*.php — once a month, first run on the 1st (UTC)
if ((int) gmdate('j') === 1 && glob($cronDir . '/months/*.php') && child_cron_once($cronApp, 'months', gmdate('Y-m'))) {
    run_jobs($cronDir . '/months/', 'months');
}

echo "Cron finished: " . date('Y-m-d H:i:s') . "\n";

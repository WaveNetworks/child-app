<?php
/**
 * cron/cron.php — Child app cron entry point
 * Add to crontab (runs every minute):
 *   * * * * * /usr/bin/php /path/to/app/cron/cron.php >> /tmp/app_cron.log 2>&1
 *
 * Schedule structure:
 *   cron/minutes/{N}/*.php  — run every N minutes
 *   cron/days/*.php         — run once per day
 *   cron/weeks/*.php        — run once per week (Monday)
 *   cron/months/*.php       — run once per month (1st of month)
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

// Minute jobs — cron/minutes/{N}/*.php — runs every N minutes
$minuteDirs = glob($cronDir . '/minutes/*', GLOB_ONLYDIR) ?: [];
foreach ($minuteDirs as $mDir) {
    $interval = (int)basename($mDir);
    if ($interval > 0 && $minute % $interval === 0) {
        run_jobs($mDir . '/', 'minutes/' . $interval);
    }
}

// Daily jobs — cron/days/*.php — runs every day
run_jobs($cronDir . '/days/', 'days');

// Weekly jobs — cron/weeks/*.php — runs every Monday
if ($dow === 1) {
    run_jobs($cronDir . '/weeks/', 'weeks');
}

// Monthly jobs — cron/months/*.php — runs on the 1st of each month
if ($today === 1) {
    run_jobs($cronDir . '/months/', 'months');
}

echo "Cron finished: " . date('Y-m-d H:i:s') . "\n";

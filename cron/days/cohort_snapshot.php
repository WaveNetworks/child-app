<?php
/**
 * cron/days/cohort_snapshot.php
 * Daily cohort progress snapshot for timeline charts.
 * Run via: admin/cron/cron.php (daily schedule)
 */
require_once __DIR__ . '/../../include/common.php';

if (!function_exists('compute_cohort_summary')) {
    // Load the teamApiActions which defines this function
    $api_actions_path = __DIR__ . '/../../include/actions/apiActions/teamApiActions.php';
    if (file_exists($api_actions_path)) {
        include_once($api_actions_path);
    }
}

global $app_config;
if (empty($app_config['snapshot_enabled'])) {
    exit;
}

$summary = function_exists('compute_cohort_summary') ? compute_cohort_summary() : [];
if (empty($summary)) {
    exit;
}

$snapshot = [
    'date' => date('Y-m-d'),
    'total_sellers' => $summary['total_sellers'] ?? 0,
    'avg_stage' => $summary['avg_stage'] ?? 0,
    'avg_mindset' => $summary['avg_mindset'] ?? 0,
    'avg_toolset' => $summary['avg_toolset'] ?? 0,
    'avg_skillset' => $summary['avg_skillset'] ?? 0,
    'at_wall_count' => $summary['at_wall_count'] ?? 0,
    'stage_distribution' => $summary['stage_distribution'] ?? [],
];

child_db_query_prepared(
    "INSERT INTO cohort_snapshots (snapshot_date, data_json) VALUES (?, ?)
     ON DUPLICATE KEY UPDATE data_json = VALUES(data_json)",
    [date('Y-m-d'), json_encode($snapshot)]
);

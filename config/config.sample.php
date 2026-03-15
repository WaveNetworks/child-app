<?php
/**
 * config/config.sample.php
 * Child app configuration template.
 *
 * Copy to config.php and fill in your child app's database credentials.
 * Admin core credentials are NOT needed here — admin's bootstrap loads its own config.
 */

// ─── Child App Main Database ────────────────────────────────────────────────
$child_db_host = 'your_child_main_db_host';
$child_db_name = 'childapp_main';
$child_db_user = 'your_child_main_db_user';
$child_db_pass = 'your_child_main_db_password';

// ─── Child App Shard Databases ──────────────────────────────────────────────
// Child app has its own shards, separate from admin's.
// Users are assigned to shards based on their shard_id (set during registration by admin).
// The child app maps the same shard_id to its own shard databases.

$childShardConfigs = [
    'shard1' => [
        'host' => 'your_child_shard1_db_host',
        'name' => 'childapp_shard_1',
        'user' => 'your_child_shard1_db_user',
        'pass' => 'your_child_shard1_db_password',
    ],
    'shard2' => [
        'host' => 'your_child_shard2_db_host',
        'name' => 'childapp_shard_2',
        'user' => 'your_child_shard2_db_user',
        'pass' => 'your_child_shard2_db_password',
    ],
];

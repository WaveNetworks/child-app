<?php
/**
 * common_api.php — Child app JSON API bootstrap.
 * Sets JSON header, includes admin's API bootstrap, then opens child DBs.
 */
header('Content-Type: application/json');

// 1. Include admin's API bootstrap — provides: autoload, admin config, $db,
//    helpers, admin migrations, session, definition, all admin action files.
include(__DIR__ . '/../../admin/include/common_api.php');

// 2. Load child app's own config (only child DB credentials)
$childConfigFile = __DIR__ . '/../config/config.php';
if (file_exists($childConfigFile)) {
    include($childConfigFile);
} else {
    $child_db_host = getenv('CHILD_DB_HOST') ?: 'db_child';
    $child_db_name = getenv('CHILD_DB_NAME') ?: 'childapp_main';
    $child_db_user = getenv('CHILD_DB_USER') ?: getenv('DB_USER') ?: 'root';
    $child_db_pass = getenv('CHILD_DB_PASS') ?: getenv('DB_PASSWORD') ?: '';

    $childShardConfigs = [];
    if (getenv('CHILD_DB_HOST_SHARD')) {
        $childShardConfigs['shard1'] = [
            'host' => getenv('CHILD_DB_HOST_SHARD'),
            'name' => getenv('CHILD_DB_NAME_SHARD') ?: 'childapp_shard_1',
            'user' => getenv('CHILD_DB_USER') ?: getenv('DB_USER') ?: 'root',
            'pass' => getenv('CHILD_DB_PASS') ?: getenv('DB_PASSWORD') ?: '',
        ];
    }
    if (getenv('CHILD_DB_HOST_SHARD2')) {
        $childShardConfigs['shard2'] = [
            'host' => getenv('CHILD_DB_HOST_SHARD2'),
            'name' => getenv('CHILD_DB_NAME_SHARD2') ?: 'childapp_shard_2',
            'user' => getenv('CHILD_DB_USER') ?: getenv('DB_USER') ?: 'root',
            'pass' => getenv('CHILD_DB_PASS') ?: getenv('DB_PASSWORD') ?: '',
        ];
    }
}

// 3. Open child app's own main database
$child_db = new PDO(
    "mysql:host=$child_db_host;dbname=$child_db_name;charset=utf8mb4",
    $child_db_user, $child_db_pass
);
$child_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 4. Include child DB helpers and migration functions
include_once(__DIR__ . '/common/childDBFunctions.php');
include_once(__DIR__ . '/common/appMigrationFunctions.php');

// 5. Run child app migrations
// MUST stay in sync with include/common.php. These had drifted to 1.0/1.0 while
// common.php was on 1.1/1.2, so API requests silently skipped migrations that
// web requests applied — and every app scaffolded from this template inherited
// the drift. Versions are single-decimal floats: (float)"1.10" == 1.1 and would
// collide with 1.1.sql, so after 1.9 comes 2.0.
$child_db_version    = 1.1;
$child_shard_version = 1.2;
child_check_and_migrate($child_db, 'main', $child_db_version, __DIR__ . '/../db_migrations/');
child_check_and_migrate_shards($child_shard_version, __DIR__ . '/../db_migrations/');

// 6. Glob-include child app helpers and ALL actions
foreach (glob(__DIR__ . '/common/*.php') as $f) { include_once($f); }
foreach (glob(__DIR__ . '/actions/memberActions/*.php') as $f) { include_once($f); }
foreach (glob(__DIR__ . '/actions/apiActions/*.php') as $f) { include_once($f); }

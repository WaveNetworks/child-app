<?php
/**
 * common.php — Child app authenticated bootstrap.
 * Includes admin core → loads child config → opens child DBs → runs child migrations → globs helpers + actions.
 */

// 1. Include admin core — provides: autoload, admin config, $db, helpers,
//    admin migrations, session guard, definition.php, admin action files.
//    Admin loads its own config/config.php (or env vars). We don't touch it.
include(__DIR__ . '/../../admin/include/common.php');

// 2. Load child app's own config (only child DB credentials)
$childConfigFile = __DIR__ . '/../config/config.php';
if (file_exists($childConfigFile)) {
    include($childConfigFile);
} else {
    // Docker / CI: read child app DB from environment
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

// 4. Include child DB helpers and migration functions (before glob, so they're available)
include_once(__DIR__ . '/common/childDBFunctions.php');
include_once(__DIR__ . '/common/appMigrationFunctions.php');

// 5. Run child app migrations
$child_db_version    = 1.0;
$child_shard_version = 1.0;
child_check_and_migrate($child_db, 'main', $child_db_version, __DIR__ . '/../db_migrations/');
child_check_and_migrate_shards($child_shard_version, __DIR__ . '/../db_migrations/');

// 6. Child app definition.php
include(__DIR__ . '/definition.php');

// 7. Glob-include child app helpers and actions
foreach (glob(__DIR__ . '/common/*.php') as $f) { include_once($f); }
foreach (glob(__DIR__ . '/actions/memberActions/*.php') as $f) { include_once($f); }
foreach (glob(__DIR__ . '/actions/apiActions/*.php') as $f) { include_once($f); }

<?php
/**
 * common.php — child app authenticated bootstrap.
 * Includes admin core → loads child config → opens child DBs → runs child migrations → globs helpers + actions.
 */

// 1. Include admin core
include(__DIR__ . '/../../admin/include/common.php');

// 1b. Load .env from project root (local dev; Docker sets env vars directly)
$_envFile = __DIR__ . '/../../../.env';
if (file_exists($_envFile)) {
    foreach (file($_envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $_line) {
        $_line = trim($_line);
        if ($_line === '' || $_line[0] === '#') continue;
        if (strpos($_line, '=') === false) continue;
        list($_k, $_v) = explode('=', $_line, 2);
        $_k = trim($_k);
        $_v = trim($_v);
        if (!getenv($_k)) putenv("$_k=$_v");
    }
}
unset($_envFile, $_line, $_k, $_v);

// 2. Load child app's own config
$childConfigFile = __DIR__ . '/../config/config.php';
if (file_exists($childConfigFile)) {
    include($childConfigFile);
} else {
    // Docker / CI: read child app DB from environment
    $child_db_host = getenv('CHILD_DB_HOST') ?: 'db_child';
    $child_db_name = getenv('CHILD_DB_NAME') ?: ((getenv('CHILD_APP_SLUG') ?: 'child_app') . '_main');
    $child_db_user = getenv('CHILD_DB_USER') ?: getenv('DB_USER') ?: 'root';
    $child_db_pass = getenv('CHILD_DB_PASS') ?: getenv('DB_PASSWORD') ?: '';

    $childShardConfigs = [];
    if (getenv('CHILD_DB_HOST_SHARD')) {
        $childShardConfigs['shard1'] = [
            'host' => getenv('CHILD_DB_HOST_SHARD'),
            'name' => getenv('CHILD_DB_NAME_SHARD') ?: ((getenv('CHILD_APP_SLUG') ?: 'child_app') . '_shard_1'),
            'user' => getenv('CHILD_DB_USER') ?: getenv('DB_USER') ?: 'root',
            'pass' => getenv('CHILD_DB_PASS') ?: getenv('DB_PASSWORD') ?: '',
        ];
    }
    if (getenv('CHILD_DB_HOST_SHARD2')) {
        $childShardConfigs['shard2'] = [
            'host' => getenv('CHILD_DB_HOST_SHARD2'),
            'name' => getenv('CHILD_DB_NAME_SHARD2') ?: ((getenv('CHILD_APP_SLUG') ?: 'child_app') . '_shard_2'),
            'user' => getenv('CHILD_DB_USER') ?: getenv('DB_USER') ?: 'root',
            'pass' => getenv('CHILD_DB_PASS') ?: getenv('DB_PASSWORD') ?: '',
        ];
    }

    // AI config from env (multi-vendor with fallback)
    $ai_config = [
        'default_vendor' => 'anthropic',
        'max_tokens'     => 2048,
        'temperature'    => 0.7,
        'vendors' => [
            'anthropic' => [
                'api_key' => getenv('ANTHROPIC_API_KEY') ?: '',
                'model'   => getenv('CHILD_APP_AI_MODEL') ?: 'claude-sonnet-4-6',
            ],
            'openai' => [
                'api_key' => getenv('OPEN_AI_API_KEY') ?: '',
                'model'   => 'gpt-4o',
            ],
            'deepseek' => [
                'api_key' => getenv('DEEPSEEK_API_KEY') ?: '',
                'model'   => 'deepseek-chat',
            ],
        ],
    ];

    // Framework config from env
    $framework_config = [
        'source'     => getenv('CHILD_APP_FRAMEWORK_SOURCE') ?: 'local',
        'local_path' => getenv('CHILD_APP_FRAMEWORK_PATH') ?: __DIR__ . '/../../dswa-framework/framework',
        'remote_url' => getenv('CHILD_APP_FRAMEWORK_URL') ?: '',
        'auto_sync'  => false,
    ];

    // App config
    $app_config = [
        'app_url'                => getenv('CHILD_APP_URL') ?: '',
        'invite_max_per_coach'   => 20,
        'session_timeout_hours'  => 4,
        'cohort_cache_hours'     => 1,
        'snapshot_enabled'       => true,
    ];

    $qr_config = [
        'scale'     => 10,
        'logo_path' => null,
        'fg_color'  => [46, 117, 182],
        'bg_color'  => [255, 255, 255],
    ];
}

// 2b. Normalize $ai_config to multi-vendor format
// Docker entrypoint may generate old single-vendor format. Upgrade it here.
if (isset($ai_config) && !isset($ai_config['vendors'])) {
    $legacy_key   = $ai_config['api_key'] ?? '';
    $legacy_model = $ai_config['model'] ?? 'claude-sonnet-4-6';
    $ai_config = [
        'default_vendor' => 'anthropic',
        'max_tokens'     => $ai_config['max_tokens'] ?? 2048,
        'temperature'    => $ai_config['temperature'] ?? 0.7,
        'vendors' => [
            'anthropic' => [
                'api_key' => $legacy_key ?: (getenv('ANTHROPIC_API_KEY') ?: ''),
                'model'   => $legacy_model,
            ],
            'openai' => [
                'api_key' => getenv('OPEN_AI_API_KEY') ?: '',
                'model'   => 'gpt-4o',
            ],
            'deepseek' => [
                'api_key' => getenv('DEEPSEEK_API_KEY') ?: '',
                'model'   => 'deepseek-chat',
            ],
        ],
    ];
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
$child_db_version    = 1.2;
$child_shard_version = 1.8;
child_check_and_migrate($child_db, 'main', $child_db_version, __DIR__ . '/../db_migrations/');
child_check_and_migrate_shards($child_shard_version, __DIR__ . '/../db_migrations/');

// 6. Child app definition.php
include(__DIR__ . '/definition.php');

// 7. Glob-include child app helpers and actions
foreach (glob(__DIR__ . '/common/*.php') as $f) { include_once($f); }
foreach (glob(__DIR__ . '/actions/memberActions/*.php') as $f) { include_once($f); }
foreach (glob(__DIR__ . '/actions/apiActions/*.php') as $f) { include_once($f); }

// 8. Auto-sync framework on first request per session (cheap checksum check)
if (empty($_SESSION['_fw_sync_checked'])) {
    $_SESSION['_fw_sync_checked'] = true;
    if (function_exists('sync_framework_data')) {
        try {
            $sync_result = sync_framework_data();
            if (!empty($sync_result['changed'])) {
                error_log("Framework auto-synced to " . ($sync_result['version'] ?? 'unknown'));
            }
        } catch (Exception $e) {
            error_log("Framework auto-sync error: " . $e->getMessage());
        }
    }
}

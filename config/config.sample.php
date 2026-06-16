<?php
/**
 * config/config.sample.php
 * Child app — configuration template.
 *
 * Copy to config.php and fill in credentials.
 * Admin core credentials are NOT needed here — admin's bootstrap loads its own config.
 * Replace the example DB names below (`{slug}_main`, `{slug}_shard_*`) with the
 * actual DB names you create for your child app.
 */

// ─── Child App Main Database ────────────────────────────────────────────────
$child_db_host = 'your_child_main_db_host';
$child_db_name = '{slug}_main';          // e.g. 'elevateher_main', 'hireasupport_main'
$child_db_user = 'your_child_main_db_user';
$child_db_pass = 'your_child_main_db_password';

// ─── Child App Shard Databases ──────────────────────────────────────────────
$childShardConfigs = [
    'shard1' => [
        'host' => 'your_child_shard1_db_host',
        'name' => '{slug}_shard_1',       // e.g. 'elevateher_shard_1'
        'user' => 'your_child_shard1_db_user',
        'pass' => 'your_child_shard1_db_password',
    ],
    'shard2' => [
        'host' => 'your_child_shard2_db_host',
        'name' => '{slug}_shard_2',       // e.g. 'elevateher_shard_2'
        'user' => 'your_child_shard2_db_user',
        'pass' => 'your_child_shard2_db_password',
    ],
];

// ─── AI Configuration (multi-vendor with fallback) ──────────────────────────
// Fallback order: user's saved vendor → anthropic → openai → deepseek
// Keys can be set here or via environment variables / .env file in project root.
$ai_config = [
    'default_vendor' => 'anthropic',
    'max_tokens'     => 2048,
    'temperature'    => 0.7,
    'vendors' => [
        'anthropic' => [
            'api_key' => getenv('ANTHROPIC_API_KEY') ?: '',
            'model'   => 'claude-sonnet-4-6',
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

// ─── Framework Data Source ──────────────────────────────────────────────────
$framework_config = [
    'source'     => 'local',
    'local_path' => __DIR__ . '/../../dswa-framework/framework',
    'remote_url' => '',
    'auto_sync'  => false,
];

// ─── App Settings ───────────────────────────────────────────────────────────
$app_config = [
    'app_url'                => '',
    'invite_max_per_coach'   => 20,
    'session_timeout_hours'  => 4,
    'cohort_cache_hours'     => 1,
    'snapshot_enabled'       => true,
];

// ─── QR Code Settings ───────────────────────────────────────────────────────
$qr_config = [
    'scale'     => 10,
    'logo_path' => null,
    'fg_color'  => [46, 117, 182],
    'bg_color'  => [255, 255, 255],
];

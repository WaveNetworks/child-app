<?php
/**
 * cron/days/framework_sync.php
 * Auto-sync framework data if enabled in config.
 */
require_once __DIR__ . '/../../include/common.php';

global $framework_config;

if (empty($framework_config['auto_sync'])) {
    exit;
}

$result = sync_framework_data();
if ($result['changed']) {
    // Notify admin via email if configured
    if (function_exists('queue_email')) {
        $admin_email = get_branding()['admin_email'] ?? '';
        if ($admin_email) {
            queue_email($admin_email, 'Admin', CHILD_APP_NAME . ' Framework Updated',
                "Framework data updated to version {$result['version']}. {$result['files_loaded']} files loaded.");
        }
    }
}

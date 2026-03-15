<?php
/**
 * themeRegistration.php
 * Auto-registers this child app's custom theme with admin core.
 * Runs on every request (idempotent via ON DUPLICATE KEY UPDATE).
 */
if (function_exists('register_theme') && has_custom_theme()) {
    register_theme('glassmorphism', 'Glassmorphism', 'child-app/assets/css/custom.css', [
        'sidebar_mode'   => 'glass',
        'created_by_app' => 'child-app',
    ]);
}

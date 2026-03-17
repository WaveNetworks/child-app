<?php
/**
 * themeRegistration.php
 * Auto-registers custom themes from assets/themes/ with admin core.
 * Runs on every request (idempotent via ON DUPLICATE KEY UPDATE).
 *
 * Each theme folder must contain a compiled custom.css.
 * The folder name becomes the theme slug.
 */
if (function_exists('register_theme')) {
    $themes_dir = __DIR__ . '/../../assets/themes';
    if (is_dir($themes_dir)) {
        foreach (glob($themes_dir . '/*/custom.css') as $css_file) {
            $slug = basename(dirname($css_file));
            $name = ucfirst(str_replace(['-', '_'], ' ', $slug));
            $rel_path = 'child-app/assets/themes/' . $slug . '/custom.css';
            register_theme($slug, $name, $rel_path, [
                'sidebar_mode'   => 'glass',
                'created_by_app' => 'child-app',
            ]);
        }
    }
}

<?php
/**
 * appFunctions.php
 * Child app helper functions.
 */

/**
 * Get the CSS URL for the child app's active theme.
 * Checks assets/themes/{slug}/custom.css first, then falls back to
 * admin's Bootswatch theme system.
 *
 * @param string $admin_prefix  Path prefix to admin's assets (e.g. '../../admin/')
 * @return string
 */
function get_app_theme_css_url($admin_prefix = '../../admin/', $webroot_prefix = '../../') {
    $theme = $_COOKIE['wn_theme'] ?? '';
    $themes_dir = __DIR__ . '/../../assets/themes';

    // Check if the active theme has a local compiled CSS
    if ($theme !== '') {
        $css_path = $themes_dir . '/' . $theme . '/custom.css';
        if (file_exists($css_path)) {
            $v = filemtime($css_path);
            return '../assets/themes/' . $theme . '/custom.css?v=' . $v;
        }
    }

    // No cookie — use first available custom theme as default
    if ($theme === '' && is_dir($themes_dir)) {
        foreach (glob($themes_dir . '/*/custom.css') as $css_file) {
            $slug = basename(dirname($css_file));
            $v = filemtime($css_file);
            return '../assets/themes/' . $slug . '/custom.css?v=' . $v;
        }
    }

    return get_theme_css_url($admin_prefix, $webroot_prefix);
}

/**
 * Check whether the child app has any compiled custom themes available.
 *
 * @return bool
 */
function has_custom_theme() {
    $themes_dir = __DIR__ . '/../../assets/themes';
    return is_dir($themes_dir) && count(glob($themes_dir . '/*/custom.css')) > 0;
}

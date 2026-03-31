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
    // Always use DSWA theme — no theme switching in this app
    $css_path = __DIR__ . '/../../assets/themes/dswa/custom.css';
    if (file_exists($css_path)) {
        return '../assets/themes/dswa/custom.css?v=' . filemtime($css_path);
    }
    // Fallback to glassmorphism
    $css_path = __DIR__ . '/../../assets/themes/glassmorphism/custom.css';
    if (file_exists($css_path)) {
        return '../assets/themes/glassmorphism/custom.css?v=' . filemtime($css_path);
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

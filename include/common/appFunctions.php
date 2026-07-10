<?php
/**
 * appFunctions.php
 * Child app helper functions.
 */

/**
 * Get the CSS URL for the child app's active theme.
 * If the user has selected "custom" (or no theme cookie is set and custom.css exists),
 * returns the locally compiled custom.css. Otherwise falls back to admin's
 * Bootswatch theme system.
 *
 * @param string $admin_prefix  Path prefix to admin's assets (e.g. '../../admin/')
 * @return string
 */
function get_app_theme_css_url($admin_prefix = '../../admin/', $webroot_prefix = '../../') {
    $custom_exists = file_exists(__DIR__ . '/../../assets/css/custom.css');
    $theme = $_COOKIE['wn_theme'] ?? '';

    // "custom" explicitly selected, or no theme cookie and custom.css exists (default)
    if ($custom_exists && ($theme === 'custom' || $theme === '')) {
        // Cache-bust on the compiled file's mtime so a rebuilt theme is picked up
        // immediately and a stale CDN/origin 404 (cached before the file existed)
        // is never served — the bare URL is a different cache key.
        $v = @filemtime(__DIR__ . '/../../assets/css/custom.css');
        return '../assets/css/custom.css' . ($v ? '?v=' . $v : '');
    }

    return get_theme_css_url($admin_prefix, $webroot_prefix);
}

/**
 * Check whether the child app has a compiled custom.css available.
 * Used by the theme switcher JS to know whether to show the "Custom" option.
 *
 * @return bool
 */
function has_custom_theme() {
    return file_exists(__DIR__ . '/../../assets/css/custom.css');
}

<?php
/**
 * auth/template.php
 * Branded HTML shell for child-app auth pages (centered card layout).
 * Uses admin's branding by default. Override by editing this file.
 * $page_title and $page_content_html must be set before including this.
 */

// Core asset URLs versioned by mtime so a host CDN can't keep serving a stale core
// style.css/JS after a core deploy. core_asset_url() lives in core; the fallback
// covers a core that predates it.
$core_asset = function ($rel) {
    return function_exists('core_asset_url')
        ? core_asset_url($rel, '../../admin/')
        : '../../admin/' . $rel . '?v=' . (int) @filemtime(__DIR__ . '/../../admin/' . $rel);
};
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>
    (function(){var s=localStorage.getItem('wn_color_mode');
    var m=s||(window.matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light');
    document.documentElement.setAttribute('data-bs-theme',m);})();
    </script>
    <?php $b = get_branding(); ?>
    <title><?= h($page_title ?? $b['site_name']) ?></title>
    <meta name="theme-color" content="<?= h($b['theme_color']) ?>">
    <?php if (!empty($b['favicon_path'])) { ?>
    <link rel="icon" href="../../admin/branding/<?= h($b['favicon_path']) ?>">
    <link rel="apple-touch-icon" href="../../admin/branding/<?= h($b['favicon_path']) ?>">
    <?php } ?>
    <link rel="stylesheet" href="<?= h(get_app_theme_css_url()) ?>" id="themeStylesheet">
    <link rel="stylesheet" href="<?= h($core_asset('assets/css/style.css')) ?>">
    <link rel="stylesheet" href="<?= h($core_asset('assets/css/bs-theme-overrides.css')) ?>">
    <style>
        body { background-color: var(--bs-tertiary-bg); }
        .auth-wrapper { min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .auth-card { width: 100%; max-width: 420px; }
    </style>
</head>
<body>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="text-center mb-4">
            <?php if (!empty($b['logo_path'])) { ?>
            <img src="../../admin/branding/<?= h($b['logo_path']) ?>" alt="<?= h($b['site_name']) ?>" style="max-height: 64px;" class="mb-2"<?php if (!empty($b['logo_dark_path'])) { ?> data-logo-dark="../../admin/branding/<?= h($b['logo_dark_path']) ?>" data-logo-light="../../admin/branding/<?= h($b['logo_path']) ?>"<?php } ?>>
            <?php } ?>
            <h2><?= h($b['site_name']) ?></h2>
        </div>

        <div class="card shadow-sm">
            <div class="card-body p-4">
                <?php include(__DIR__ . '/../../admin/snippets/feedback.php'); ?>
                <?php if (isset($page_content_html)) { echo $page_content_html; }
                elseif (isset($page_content) && file_exists($page_content)) { include($page_content); } ?>
            </div>
        </div>

        <p class="text-center text-muted mt-3 small">
            &copy; <?= date('Y') ?> <?= h($b['site_name']) ?>
        </p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
/**
 * auth/template.php
 * Branded HTML shell for child-app auth pages (centered card layout).
 * Glassmorphism theme with animated canvas background.
 * $page_title and $page_content_html must be set before including this.
 */
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
    <!-- CSS: admin base → overrides → glassmorphism theme (correct load order) -->
    <link rel="stylesheet" href="../../admin/assets/css/style.css">
    <link rel="stylesheet" href="../../admin/assets/css/bs-theme-overrides.css">
    <link rel="stylesheet" href="<?= h(get_app_theme_css_url()) ?>" id="themeStylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: transparent; }
        .auth-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            z-index: 1;
            padding: 24px;
        }
        .auth-card { width: 100%; max-width: 420px; }
        .auth-card .card {
            background: rgba(255, 255, 255, 0.04);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.2);
        }
        .auth-card h2 {
            font-weight: 700;
            letter-spacing: -0.01em;
        }
        [data-bs-theme="light"] .auth-card .card {
            background: rgba(255, 255, 255, 0.7);
            border-color: rgba(0, 0, 0, 0.08);
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
        }
        .auth-color-toggle {
            position: fixed;
            top: 16px;
            right: 16px;
            z-index: 10;
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.2s, border-color 0.2s;
        }
        .auth-color-toggle:hover {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.25);
        }
        [data-bs-theme="light"] .auth-color-toggle {
            background: rgba(0, 0, 0, 0.05);
            border-color: rgba(0, 0, 0, 0.1);
        }
        [data-bs-theme="light"] .auth-color-toggle:hover {
            background: rgba(0, 0, 0, 0.1);
            border-color: rgba(0, 0, 0, 0.2);
        }
    </style>
</head>
<body>

<!-- Dark/light mode toggle -->
<button class="auth-color-toggle" id="colorModeToggle" type="button" title="Toggle dark/light mode">
    <i class="bi bi-moon-fill"></i>
</button>

<!-- Canvas background — animated nebula -->
<canvas id="bgCanvas" style="position:fixed;top:0;left:0;width:100%;height:100%;z-index:0;pointer-events:none;"></canvas>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="text-center mb-4">
            <?php if (!empty($b['logo_path'])) { ?>
            <img src="../../admin/branding/<?= h($b['logo_path']) ?>" alt="<?= h($b['site_name']) ?>" style="max-height: 64px;" class="mb-2"<?php if (!empty($b['logo_dark_path'])) { ?> data-logo-dark="../../admin/branding/<?= h($b['logo_dark_path']) ?>" data-logo-light="../../admin/branding/<?= h($b['logo_path']) ?>"<?php } ?>>
            <?php } ?>
            <h2><?= h($b['site_name']) ?></h2>
        </div>

        <div class="card">
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
<script src="../assets/js/bg-canvas.js"></script>
<script>
// Auth page color mode toggle + logo swap
(function(){
    var STORAGE_KEY = 'wn_color_mode';
    var btn = document.getElementById('colorModeToggle');
    var iconEl = btn ? btn.querySelector('i') : null;

    function getMode() {
        return document.documentElement.getAttribute('data-bs-theme') || 'light';
    }

    function syncIcon(mode) {
        if (!iconEl) return;
        iconEl.className = mode === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
        btn.setAttribute('title', mode === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
    }

    function updateLogos(mode) {
        document.querySelectorAll('img[data-logo-dark]').forEach(function(img){
            img.src = (mode === 'dark') ? img.dataset.logoDark : img.dataset.logoLight;
        });
    }

    // Initial sync
    syncIcon(getMode());
    updateLogos(getMode());

    // Toggle on click
    if (btn) {
        btn.addEventListener('click', function(){
            var current = getMode();
            var next = current === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-bs-theme', next);
            localStorage.setItem(STORAGE_KEY, next);
            syncIcon(next);
            updateLogos(next);
        });
    }
})();
</script>
</body>
</html>

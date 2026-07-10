<?php
/**
 * error.php — Standalone error page for 403, 404, 500 responses.
 * Used as Apache ErrorDocument target. Also logs HTTP errors to the
 * admin error_log DB table for security monitoring (scan detection).
 *
 * Works without authentication — uses common_auth.php for branding only.
 * Styled with glassmorphism card + canvas background to match auth pages.
 */

// Determine HTTP status code
$status = http_response_code();
if (!in_array($status, [403, 404, 500])) {
    $status = 404; // fallback
}

// Error metadata
$errors = [
    403 => ['title' => 'Forbidden',        'icon' => 'bi-shield-lock',          'message' => 'You don\'t have permission to access this resource.'],
    404 => ['title' => 'Not Found',         'icon' => 'bi-exclamation-triangle', 'message' => 'The page you\'re looking for doesn\'t exist or has been moved.'],
    500 => ['title' => 'Server Error',      'icon' => 'bi-bug',                 'message' => 'Something went wrong on our end. Please try again later.'],
];
$err = $errors[$status];

// Try to bootstrap branding (graceful fallback if DB is down)
$branding = null;
$has_theme = false;
try {
    include_once(__DIR__ . '/../admin/include/common_auth.php');
    $branding = get_branding();

    // Log to error_log DB table for security monitoring
    if (function_exists('log_error_to_db')) {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        $msg = "HTTP $status: $method $uri";
        log_error_to_db('WARNING', $msg, __FILE__, 0, null);
    }

    // Check for custom theme
    if (function_exists('get_app_theme_css_url')) {
        $theme_url = get_app_theme_css_url();
        $has_theme = true;
    }
} catch (Exception $e) {
    // Branding unavailable — use fallback
}

// Fallback branding
if (!$branding) {
    $branding = [
        'site_name'   => 'App',
        'theme_color' => '#212529',
        'favicon_path' => null,
        'logo_path'    => null,
        'logo_dark_path' => null,
    ];
    if (!function_exists('h')) {
        function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
    }
}
if (!$has_theme) {
    $theme_url = 'https://cdn.jsdelivr.net/npm/bootswatch@5/dist/sandstone/bootstrap.min.css';
}

$page_title = $status . ' — ' . $err['title'];
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
    <title><?= h($page_title) ?></title>
    <meta name="theme-color" content="<?= h($branding['theme_color']) ?>">
    <?php if (!empty($branding['favicon_path'])) { ?>
    <link rel="icon" href="/admin/branding/<?= h($branding['favicon_path']) ?>">
    <?php } ?>
    <link rel="stylesheet" href="/admin/assets/css/style.css">
    <link rel="stylesheet" href="/admin/assets/css/bs-theme-overrides.css">
    <link rel="stylesheet" href="<?= h($theme_url) ?>" id="themeStylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: transparent; }
        .error-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            z-index: 1;
            padding: 24px;
        }
        .error-card { width: 100%; max-width: 480px; text-align: center; }
        .error-card .card {
            background: rgba(255, 255, 255, 0.04);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.2);
        }
        [data-bs-theme="light"] .error-card .card {
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

<button class="auth-color-toggle" id="colorModeToggle" type="button" title="Toggle dark/light mode">
    <i class="bi bi-moon-fill"></i>
</button>

<canvas id="bgCanvas" style="position:fixed;top:0;left:0;width:100%;height:100%;z-index:0;pointer-events:none;"></canvas>

<div class="error-wrapper">
    <div class="error-card">
        <?php if (!empty($branding['logo_path'])) { ?>
        <div class="mb-3">
            <img src="/admin/branding/<?= h($branding['logo_path']) ?>" alt="<?= h($branding['site_name']) ?>" style="max-height: 48px;"<?php if (!empty($branding['logo_dark_path'])) { ?> data-logo-dark="/admin/branding/<?= h($branding['logo_dark_path']) ?>" data-logo-light="/admin/branding/<?= h($branding['logo_path']) ?>"<?php } ?>>
        </div>
        <?php } ?>
        <div class="card">
            <div class="card-body p-5">
                <div class="mb-3">
                    <i class="bi <?= $err['icon'] ?> text-warning" style="font-size: 3rem;"></i>
                </div>
                <h1 class="display-4 fw-bold text-body-emphasis mb-2"><?= $status ?></h1>
                <h4 class="text-body-secondary mb-3"><?= h($err['title']) ?></h4>
                <p class="text-body-tertiary mb-4"><?= h($err['message']) ?></p>
                <div class="d-flex justify-content-center gap-2">
                    <a href="/child-app/auth/login.php" class="btn btn-primary">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
                    </a>
                    <a href="javascript:history.back()" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Go Back
                    </a>
                </div>
            </div>
        </div>
        <p class="text-center text-body-tertiary mt-3 small">
            &copy; <?= date('Y') ?> <?= h($branding['site_name']) ?>
        </p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="/child-app/assets/js/bg-canvas.js"></script>
<script>
(function(){
    var STORAGE_KEY = 'wn_color_mode';
    var btn = document.getElementById('colorModeToggle');
    var iconEl = btn ? btn.querySelector('i') : null;
    function getMode() { return document.documentElement.getAttribute('data-bs-theme') || 'light'; }
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
    syncIcon(getMode());
    updateLogos(getMode());
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

<?php
try {
    include(__DIR__ . '/../include/common_auth.php');
    $b = get_branding();
} catch (Throwable $e) {
    $b = ['site_name' => defined('CHILD_APP_NAME') ? CHILD_APP_NAME : 'this app', 'logo_path' => '', 'logo_dark_path' => '', 'favicon_path' => '', 'theme_color' => '#0f172a'];
    function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
}
$logo = !empty($b['logo_dark_path']) ? $b['logo_dark_path'] : ($b['logo_path'] ?? '');
$app_name = defined('CHILD_APP_NAME') ? CHILD_APP_NAME : ($b['site_name'] ?? 'this app');
$ob_key = (defined('CHILD_APP_SLUG') ? CHILD_APP_SLUG : 'app') . '_onboarded';
$theme_color = !empty($b['theme_color']) ? $b['theme_color'] : '#0f172a';
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Welcome to <?= h($b['site_name']) ?></title>
    <meta name="theme-color" content="<?= h($theme_color) ?>">
    <?php if (!empty($b['favicon_path'])) { ?>
    <link rel="icon" href="../../admin/branding/<?= h($b['favicon_path']) ?>">
    <?php } ?>
    <link rel="stylesheet" href="../assets/css/onboarding.css?v=20260710a">
    <script>
    window.WN_ONBOARD_KEY = <?= json_encode($ob_key) ?>;
    if (localStorage.getItem(window.WN_ONBOARD_KEY)) {
        window.location.replace('login.php');
    }
    </script>
</head>
<body>

<div class="onboarding-container">
    <div class="onboarding-bg"></div>

    <button class="onboarding-skip" id="obSkip" type="button">Skip</button>

    <div class="onboarding-track" id="onboardingTrack">

        <!-- Panel 1: Welcome -->
        <div class="onboarding-panel active">
            <div class="panel-content">
                <?php if ($logo) { ?>
                <img src="../../admin/branding/<?= h($logo) ?>" alt="<?= h($b['site_name']) ?>" class="panel-brand-logo">
                <?php } ?>
                <div class="panel-icon">
                    <div class="hub-icon">
                        <div class="hub-core"></div>
                        <div class="hub-node"></div>
                        <div class="hub-node"></div>
                        <div class="hub-node"></div>
                        <div class="hub-node"></div>
                        <div class="hub-node"></div>
                        <div class="hub-node"></div>
                    </div>
                </div>
                <h1 class="panel-title">Welcome to <?= h($app_name) ?></h1>
                <p class="panel-subtitle">
                    Everything your team needs, in one place. Here's a quick look
                    before you sign in.
                </p>
            </div>
        </div>

        <!-- Panel 2: Built around your work -->
        <div class="onboarding-panel">
            <div class="panel-content">
                <div class="panel-icon">
                    <div class="chat-bubbles-icon">
                        <div class="bubble bubble-left">Welcome aboard</div>
                        <div class="bubble bubble-right">Let's get set up</div>
                        <div class="bubble bubble-left">Ready when you are</div>
                    </div>
                </div>
                <h1 class="panel-title">Built Around Your Work</h1>
                <p class="panel-subtitle">
                    A fast, focused workspace that adapts to how you and your team
                    actually work &mdash; on desktop and mobile.
                </p>
            </div>
        </div>

        <!-- Panel 3: What you get -->
        <div class="onboarding-panel">
            <div class="panel-content">
                <div class="panel-icon">
                    <div class="dimension-bars-icon">
                        <div class="dim-bar"></div>
                        <div class="dim-bar"></div>
                        <div class="dim-bar"></div>
                        <div class="dim-bar"></div>
                        <div class="dim-bar"></div>
                        <div class="dim-bar"></div>
                    </div>
                </div>
                <h1 class="panel-title">What You Get</h1>
                <p class="panel-subtitle">
                    A secure account and a workspace that keeps your data yours.
                </p>
                <div class="panel-features">
                    <div class="panel-feature">
                        <span class="panel-feature-icon">&#128274;</span>
                        <span>Secure sign-in with 2FA and SSO support</span>
                    </div>
                    <div class="panel-feature">
                        <span class="panel-feature-icon">&#128241;</span>
                        <span>Works on desktop and mobile</span>
                    </div>
                    <div class="panel-feature">
                        <span class="panel-feature-icon">&#9878;</span>
                        <span>Your data stays private and portable</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel 4: Ready to Start -->
        <div class="onboarding-panel panel-ready">
            <div class="ready-petals-bg">
                <div class="falling-petal"></div>
                <div class="falling-petal"></div>
                <div class="falling-petal"></div>
                <div class="falling-petal"></div>
                <div class="falling-petal"></div>
                <div class="falling-petal"></div>
                <div class="falling-petal"></div>
                <div class="falling-petal"></div>
            </div>
            <div class="panel-content">
                <h1 class="panel-title">Ready to Start?</h1>
                <p class="panel-subtitle">
                    Create your account and jump in &mdash; it only takes a minute.
                </p>
                <div class="ready-bloom">
                    <div class="hub-core"></div>
                    <div class="hub-node"></div>
                    <div class="hub-node"></div>
                    <div class="hub-node"></div>
                    <div class="hub-node"></div>
                    <div class="hub-node"></div>
                    <div class="hub-node"></div>
                </div>
            </div>
        </div>

    </div>

    <div class="onboarding-footer">
        <div class="onboarding-dots">
            <button class="onboarding-dot active" type="button" aria-label="Panel 1"></button>
            <button class="onboarding-dot" type="button" aria-label="Panel 2"></button>
            <button class="onboarding-dot" type="button" aria-label="Panel 3"></button>
            <button class="onboarding-dot" type="button" aria-label="Panel 4"></button>
        </div>
        <div class="onboarding-nav">
            <button class="onboarding-btn onboarding-btn-secondary" id="obPrev" type="button" style="display:none">Back</button>
            <button class="onboarding-btn onboarding-btn-primary" id="obNext" type="button">Next</button>
        </div>
    </div>
</div>

<script src="../assets/js/onboarding.js?v=20260710a"></script>
</body>
</html>

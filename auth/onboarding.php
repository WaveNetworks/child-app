<?php
try {
    include(__DIR__ . '/../include/common_auth.php');
    $b = get_branding();
} catch (Throwable $e) {
    $b = ['site_name' => defined('CHILD_APP_NAME') ? CHILD_APP_NAME : 'this app', 'logo_path' => '', 'logo_dark_path' => '', 'favicon_path' => ''];
    function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
}
$logo = !empty($b['logo_dark_path']) ? $b['logo_dark_path'] : ($b['logo_path'] ?? '');
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Welcome to <?= h($b['site_name']) ?></title>
    <meta name="theme-color" content="#1a0533">
    <?php if (!empty($b['favicon_path'])) { ?>
    <link rel="icon" href="../../admin/branding/<?= h($b['favicon_path']) ?>">
    <?php } ?>
    <link rel="stylesheet" href="../assets/css/onboarding.css?v=20260331h">
    <script>
    if (localStorage.getItem('elevatehr_onboarded')) {
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
                    <div class="lotus-icon">
                        <div class="lotus-petal"></div>
                        <div class="lotus-petal"></div>
                        <div class="lotus-petal"></div>
                        <div class="lotus-petal"></div>
                        <div class="lotus-petal"></div>
                        <div class="lotus-center"></div>
                    </div>
                </div>
                <h1 class="panel-title">Welcome to <?= h(defined("CHILD_APP_NAME") ? CHILD_APP_NAME : "this app") ?></h1>
                <p class="panel-subtitle">
                    A free AI-powered assessment from the Direct Selling Women's Alliance.
                    Discover exactly where you stand in your direct sales journey &mdash;
                    always 100% free for you and your team.
                </p>
            </div>
        </div>

        <!-- Panel 2: A Real Assessment -->
        <div class="onboarding-panel">
            <div class="panel-content">
                <div class="panel-icon">
                    <div class="chat-bubbles-icon">
                        <div class="bubble bubble-left">How am I doing?</div>
                        <div class="bubble bubble-right">Let's find out...</div>
                        <div class="bubble bubble-left">Show me my path</div>
                    </div>
                </div>
                <h1 class="panel-title">A Real AI Assessment</h1>
                <p class="panel-subtitle">
                    Our AI coach quickly evaluates your business across 7 dimensions
                    and grows with you over time &mdash; a lifelong learning tool
                    that tracks your progress from first steps to Elite Legacy.
                </p>
            </div>
        </div>

        <!-- Panel 3: What You Get -->
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
                        <div class="dim-bar"></div>
                    </div>
                </div>
                <h1 class="panel-title">What You'll Get</h1>
                <p class="panel-subtitle">
                    Your assessment maps strengths and growth areas across the DSWA Training Continuum.
                    Coaches at the Pro tier can assess unlimited team members for free and
                    track everyone's progress in real time.
                </p>
                <div class="panel-features">
                    <div class="panel-feature">
                        <span class="panel-feature-icon">&#9670;</span>
                        <span>100% free assessment &mdash; no credit card</span>
                    </div>
                    <div class="panel-feature">
                        <span class="panel-feature-icon">&#9733;</span>
                        <span>Coaches see their team's scores &amp; progress</span>
                    </div>
                    <div class="panel-feature">
                        <span class="panel-feature-icon">&#9829;</span>
                        <span>Access to a 30 day free trial in the DSWA Community</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel 4: Ready to Start -->
        <div class="onboarding-panel panel-ready">
            <!-- Falling petals background -->
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
                    Create a free account and take your first assessment.
                    Share your results with your coach or keep them private &mdash; it's up to you.
                </p>
                <!-- Lotus at bottom of content -->
                <div class="ready-lotus">
                    <div class="lotus-petal"></div>
                    <div class="lotus-petal"></div>
                    <div class="lotus-petal"></div>
                    <div class="lotus-petal"></div>
                    <div class="lotus-petal"></div>
                    <div class="lotus-center"></div>
                </div>
            </div>
        </div>

    </div>

    <!-- Dots + Nav: outside the track so they stay visible on all panels -->
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

<script src="../assets/js/onboarding.js?v=20260331h"></script>
</body>
</html>

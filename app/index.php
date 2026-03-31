<?php
/**
 * app/index.php
 * ElevateHER Learning Suite — Main app controller.
 * Session-guarded by common.php. Routes to view files based on ?page= parameter.
 */
include(__DIR__ . '/../include/common.php');

$page = $_GET['page'] ?? $_POST['page'] ?? 'assessment';

// Redirect legacy privacy page into profile tabs
if ($page === 'privacy') {
    $tab = $_GET['tab'] ?? 'consent';
    header('Location: index.php?page=profile&tab=' . urlencode($tab));
    exit;
}

// Store pending invite token in session
if (!empty($_GET['token']) && $page === 'invite-landing') {
    $_SESSION['pending_invite'] = $_GET['token'];
}

// Get user roles and tier for sidebar visibility
$user_roles = function_exists('get_user_role') ? get_user_role($_SESSION['user_id'] ?? 0) : ['seller'];
$is_coach_user = in_array('coach', $user_roles) || in_array('org_lead', $user_roles);
$is_org_lead_user = in_array('org_lead', $user_roles);
$user_tier = function_exists('get_user_tier') ? get_user_tier($_SESSION['user_id'] ?? 0, $_SESSION['shard_id'] ?? '') : null;
$is_pro_or_above = $user_tier && function_exists('is_tier_at_least') && is_tier_at_least($user_tier, 'pro');
$is_elite = $user_tier && function_exists('is_tier_at_least') && is_tier_at_least($user_tier, 'elite');

// ─── Pre-template redirects (must happen before any output) ─────────────
$uid = $_SESSION['user_id'] ?? 0;
$sid = $_SESSION['shard_id'] ?? '';
$_redirect_to = null;

if ($page === 'assessment') {
    $inst = function_exists('get_user_instance') ? get_user_instance($uid, $sid, 'ai-assessment') : null;
    if ($inst && ($inst['status'] ?? '') === 'completed') {
        $_redirect_to = 'index.php?page=results';
    }
}

if ($page === 'results') {
    $inst = function_exists('get_user_instance') ? get_user_instance($uid, $sid, 'ai-assessment') : null;
    if (!$inst || ($inst['status'] ?? '') !== 'completed') {
        $_redirect_to = 'index.php?page=assessment';
    }
}

if ($page === 'onboarding') {
    $existing = function_exists('get_onboarding_profile') ? get_onboarding_profile($uid, $sid) : null;
    if ($existing && !isset($_GET['edit'])) {
        $_redirect_to = 'index.php?page=dashboard';
    }
}

if ($page === 'team' && !($is_pro_or_above ?? false)) {
    $_SESSION['error'] = 'Pro tier or above required to access team features.';
    $_redirect_to = 'index.php?page=dashboard';
}

if ($page === 'invite' && !($is_pro_or_above ?? false)) {
    $_SESSION['error'] = 'Pro tier or above required to invite sellers.';
    $_redirect_to = 'index.php?page=dashboard';
}

if ($page === 'cohort' && !$is_org_lead_user) {
    $_SESSION['error'] = 'Organization lead access required.';
    $_redirect_to = 'index.php?page=dashboard';
}

if (in_array($page, ['admin-framework', 'admin-stages', 'admin-content', 'admin-proposals']) && !(function_exists('has_role') && has_role('admin'))) {
    $_SESSION['error'] = 'Admin access required.';
    $_redirect_to = 'index.php?page=dashboard';
}

if ($_redirect_to) {
    $reloadCheck = $_GET['reloadView'] ?? $_POST['reloadView'] ?? null;
    if ($reloadCheck === 'true') {
        // SPA navigation: return JSON redirect so spa-nav.js can handle it
        header('Content-Type: application/json');
        echo json_encode(['redirect' => $_redirect_to]);
        exit;
    }
    header('Location: ' . $_redirect_to);
    exit;
}

// Map page names to view files
$views = [
    'dashboard'       => __DIR__ . '/../views/dashboard.php',
    'assessment'      => __DIR__ . '/../views/assessment.php',
    'results'         => __DIR__ . '/../views/results.php',
    'content'         => __DIR__ . '/../views/content.php',
    'content-detail'  => __DIR__ . '/../views/content_detail.php',
    'coaches'         => __DIR__ . '/../views/coaches.php',
    'team'            => __DIR__ . '/../views/team.php',
    'invite'          => __DIR__ . '/../views/invite.php',
    'invite-landing'  => __DIR__ . '/../views/invite_landing.php',
    'cohort'          => __DIR__ . '/../views/cohort.php',
    'admin-framework' => __DIR__ . '/../views/admin_framework.php',
    'admin-stages'    => __DIR__ . '/../views/admin_stages.php',
    'admin-content'   => __DIR__ . '/../views/admin_content.php',
    'admin-proposals' => __DIR__ . '/../views/admin_proposals.php',
    'onboarding'      => __DIR__ . '/../views/onboarding.php',
    'profile'         => __DIR__ . '/../views/profile.php',
    'feedback'        => __DIR__ . '/../views/feedback.php',
];

if (isset($views[$page]) && file_exists($views[$page])) {
    $current_page_file = $views[$page];
} else {
    $current_page_file = __DIR__ . '/../views/404.php';
}

// SPA navigation
$reloadView = $_GET['reloadView'] ?? $_POST['reloadView'] ?? null;

if ($reloadView === 'true') {
    header('Content-Type: application/json');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    ob_start();
    $toast_success = $_SESSION['success'] ?? '';
    $toast_error   = $_SESSION['error']   ?? '';
    $toast_warning = $_SESSION['warning'] ?? '';
    $toast_info    = $_SESSION['info']    ?? '';
    $_SESSION['success'] = null;
    $_SESSION['error']   = null;
    $_SESSION['warning'] = null;
    $_SESSION['info']    = null;

    if (isset($current_page_file) && file_exists($current_page_file)) {
        include($current_page_file);
    }
    $html = ob_get_clean();

    echo json_encode([
        'html'  => $html,
        'page'  => $page,
        'title' => ($page_title ?? ucfirst($page)) . ' — ' . h(get_branding()['site_name']),
        'toast' => [
            'success' => $toast_success,
            'error'   => $toast_error,
            'warning' => $toast_warning,
            'info'    => $toast_info,
        ],
    ]);
    exit;
}

// Full page load with template
include(__DIR__ . '/../views/template.php');

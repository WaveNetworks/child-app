<?php
/**
 * app/index.php
 * Main app controller. Session-guarded by common.php.
 * Routes to view files based on ?page= parameter.
 *
 * Supports SPA-style navigation:
 *   ?reloadView=true  — returns just the view content (no template shell)
 *                        so the canvas background stays alive during navigation.
 *   (no reloadView)   — returns full page with template wrapper (initial load).
 */
include(__DIR__ . '/../include/common.php');

$page = $_GET['page'] ?? $_POST['page'] ?? 'dashboard';

// Map page names to view files
$views = [
    'dashboard' => __DIR__ . '/../views/dashboard.php',
    'items'     => __DIR__ . '/../views/items.php',
    'profile'   => __DIR__ . '/../views/profile.php',
    'examples'  => __DIR__ . '/../views/examples.php',
    'ui-guide'  => __DIR__ . '/../views/ui-guide.php',
    'privacy'   => __DIR__ . '/../views/privacy.php',
];

if (isset($views[$page]) && file_exists($views[$page])) {
    $current_page_file = $views[$page];
} else {
    $current_page_file = __DIR__ . '/../views/404.php';
}

// Sidebar badge data
$item_count = 0;
$stmt = child_db_query_prepared("SELECT COUNT(*) FROM items WHERE user_id = ?", [$_SESSION['user_id']]);
if ($stmt) { $item_count = (int) $stmt->fetchColumn(); }

// SPA navigation: return just the view content without template shell
$reloadView = $_GET['reloadView'] ?? $_POST['reloadView'] ?? null;

if ($reloadView === 'true') {
    // Return JSON with view HTML + metadata for SPA navigation
    header('Content-Type: application/json');

    // Capture view HTML
    ob_start();
    // Toast data (session flash messages)
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
        'html'       => $html,
        'page'       => $page,
        'title'      => ($page_title ?? ucfirst($page)) . ' — ' . h(get_branding()['site_name']),
        'item_count' => $item_count,
        'toast'      => [
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

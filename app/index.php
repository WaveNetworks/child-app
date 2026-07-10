<?php
/**
 * app/index.php
 * Main app controller. Session-guarded by common.php.
 * Routes to view files based on ?page= parameter.
 */
include(__DIR__ . '/../include/common.php');

$page = $_GET['page'] ?? 'dashboard';

// Map page names to view files
$views = [
    'dashboard' => __DIR__ . '/../views/dashboard.php',
    'items'     => __DIR__ . '/../views/items.php',
    'profile'   => __DIR__ . '/../views/profile.php',
    'feedback'  => __DIR__ . '/../views/feedback.php',
    'privacy'   => __DIR__ . '/../views/privacy.php',
    'examples'  => __DIR__ . '/../views/examples.php',
    'ui-guide'  => __DIR__ . '/../views/ui-guide.php',
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

// Template wraps the view
include(__DIR__ . '/../views/template.php');

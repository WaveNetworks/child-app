<?php
/**
 * apiActions/exampleApiActions.php
 * Example AJAX action: paginated item list for inline loading.
 * Called via POST to api/index.php with action=getItems.
 */

if (($_POST['action'] ?? '') == 'getItems') {
    $uid      = $_SESSION['user_id'];
    $page_num = max(1, (int) ($_POST['page'] ?? 1));
    $per_page = min(50, max(1, (int) ($_POST['per_page'] ?? 15)));
    $offset   = ($page_num - 1) * $per_page;
    $filter   = $_POST['filter'] ?? '';

    if (!$uid) {
        $_SESSION['error'] = 'Login required.';
    } else {
        $where  = "WHERE user_id = ?";
        $params = [$uid];

        if (in_array($filter, ['active', 'draft', 'archived'])) {
            $where .= " AND status = ?";
            $params[] = $filter;
        }

        // Total count
        $count_r = child_db_query_prepared("SELECT COUNT(*) as cnt FROM items $where", $params);
        $total   = (int) db_fetch($count_r)['cnt'];

        // Fetch page
        $per_page = (int) $per_page;
        $offset   = (int) $offset;
        $rows = child_db_query_prepared(
            "SELECT item_id, title, description, status, created, updated FROM items $where ORDER BY updated DESC LIMIT $per_page OFFSET $offset",
            $params
        );

        $items = [];
        if ($rows) {
            while ($row = db_fetch($rows)) {
                $items[] = $row;
            }
        }

        $data['items']    = $items;
        $data['total']    = $total;
        $data['page']     = $page_num;
        $data['per_page'] = $per_page;
        $data['pages']    = max(1, ceil($total / $per_page));
    }
}

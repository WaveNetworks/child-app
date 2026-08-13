<?php
// SCAFFOLD:DEMO-START
// Live search over the example `items` table. A real app should replace this
// with search over its own content; until then the strip removes it so the
// topnav search does not silently return nothing.
/**
 * apiActions/searchActions.php
 * Live search: returns matching items for the nav search bar.
 * Called via POST to api/index.php with action=liveSearch.
 */

if (($_POST['action'] ?? '') == 'liveSearch') {
    $uid = $_SESSION['user_id'];
    $q   = trim($_POST['q'] ?? '');

    if (!$uid) {
        $_SESSION['error'] = 'Login required.';
    } elseif (strlen($q) < 2) {
        $data['results'] = [];
    } else {
        $like = '%' . $q . '%';
        $rows = child_db_query_prepared(
            "SELECT item_id, title, status, updated
             FROM items
             WHERE user_id = ? AND (title LIKE ? OR description LIKE ?)
             ORDER BY updated DESC
             LIMIT 8",
            [$uid, $like, $like]
        );

        $results = [];
        if ($rows) {
            while ($row = db_fetch($rows)) {
                $results[] = $row;
            }
        }

        $data['results'] = $results;
        $data['query']   = $q;
    }
}
// SCAFFOLD:DEMO-END

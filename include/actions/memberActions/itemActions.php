<?php
/**
 * memberActions/itemActions.php
 * CRUD actions for items table in $child_db.
 * Logs activity to user_history on child shard.
 */

// ─── Save Item (create or update) ───────────────────────────────────────────

if (($_POST['action'] ?? '') == 'saveItem') {
    $errs = [];
    $uid  = $_SESSION['user_id'];
    $sid  = $_SESSION['shard_id'];
    $id   = (int) ($_POST['item_id'] ?? 0);

    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status      = $_POST['status'] ?? 'draft';

    if (!$uid) { $errs['auth'] = 'Login required.'; }
    if ($title === '') { $errs['title'] = 'Title is required.'; }
    if (mb_strlen($title) > 255) { $errs['title'] = 'Title must be 255 characters or fewer.'; }
    if (!in_array($status, ['draft', 'active', 'archived'])) { $errs['status'] = 'Invalid status.'; }

    if (count($errs) <= 0) {
        $safe_title = sanitize($title, SQL);
        $safe_desc  = sanitize($description, SQL);
        $safe_status = sanitize($status, SQL);

        if ($id > 0) {
            // Update — verify ownership
            $check = child_db_query_prepared(
                "SELECT item_id, title FROM items WHERE item_id = ? AND user_id = ?",
                [$id, $uid]
            );
            $existing = db_fetch($check);

            if (!$existing) {
                $errs['item'] = 'Item not found.';
            } else {
                child_db_query_prepared(
                    "UPDATE items SET title = ?, description = ?, status = ?, updated = NOW() WHERE item_id = ? AND user_id = ?",
                    [$title, $description, $status, $id, $uid]
                );
                log_user_history($uid, $sid, 'item_updated', ['item_id' => $id, 'title' => $title]);
                $_SESSION['success'] = 'Item updated.';
                header('Location: index.php?page=items');
                exit;
            }
        } else {
            // Create
            child_db_query_prepared(
                "INSERT INTO items (user_id, title, description, status, created, updated) VALUES (?, ?, ?, ?, NOW(), NOW())",
                [$uid, $title, $description, $status]
            );
            $new_id = child_db_insert_id();
            log_user_history($uid, $sid, 'item_created', ['item_id' => $new_id, 'title' => $title]);
            $_SESSION['success'] = 'Item created.';
            header('Location: index.php?page=items');
            exit;
        }
    }

    if (count($errs) > 0) {
        $_SESSION['error'] = implode('<br>', $errs);
    }
}

// ─── Delete Item ────────────────────────────────────────────────────────────

if (($_POST['action'] ?? '') == 'deleteItem') {
    $errs = [];
    $uid  = $_SESSION['user_id'];
    $sid  = $_SESSION['shard_id'];
    $id   = (int) ($_POST['item_id'] ?? 0);

    if (!$uid) { $errs['auth'] = 'Login required.'; }
    if ($id <= 0) { $errs['item'] = 'Invalid item.'; }

    if (count($errs) <= 0) {
        $check = child_db_query_prepared(
            "SELECT item_id, title FROM items WHERE item_id = ? AND user_id = ?",
            [$id, $uid]
        );
        $existing = db_fetch($check);

        if (!$existing) {
            $errs['item'] = 'Item not found.';
        } else {
            child_db_query_prepared("DELETE FROM items WHERE item_id = ? AND user_id = ?", [$id, $uid]);
            log_user_history($uid, $sid, 'item_deleted', ['item_id' => $id, 'title' => $existing['title']]);
            $_SESSION['success'] = 'Item deleted.';
        }
    }

    if (count($errs) > 0) {
        $_SESSION['error'] = implode('<br>', $errs);
    }
}

// ─── Update Item Status (inline) ────────────────────────────────────────────

if (($_POST['action'] ?? '') == 'updateItemStatus') {
    $errs = [];
    $uid    = $_SESSION['user_id'];
    $sid    = $_SESSION['shard_id'];
    $id     = (int) ($_POST['item_id'] ?? 0);
    $status = $_POST['status'] ?? '';

    if (!$uid) { $errs['auth'] = 'Login required.'; }
    if ($id <= 0) { $errs['item'] = 'Invalid item.'; }
    if (!in_array($status, ['draft', 'active', 'archived'])) { $errs['status'] = 'Invalid status.'; }

    if (count($errs) <= 0) {
        $check = child_db_query_prepared(
            "SELECT item_id, title FROM items WHERE item_id = ? AND user_id = ?",
            [$id, $uid]
        );
        $existing = db_fetch($check);

        if (!$existing) {
            $errs['item'] = 'Item not found.';
        } else {
            child_db_query_prepared(
                "UPDATE items SET status = ?, updated = NOW() WHERE item_id = ? AND user_id = ?",
                [$status, $id, $uid]
            );
            log_user_history($uid, $sid, 'item_updated', [
                'item_id' => $id,
                'title'   => $existing['title'],
                'status'  => $status,
            ]);
            $_SESSION['success'] = 'Status updated.';
            $data['item_id'] = $id;
            $data['status']  = $status;
        }
    }

    if (count($errs) > 0) {
        $_SESSION['error'] = implode('<br>', $errs);
    }
}

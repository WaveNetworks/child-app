<?php
/**
 * contentActions.php
 * Content progress tracking actions.
 */

if (($_POST['action'] ?? '') === 'content_start') {
    $errs = [];
    $uid = $_SESSION['user_id'];
    $sid = $_SESSION['shard_id'];
    $catalog_item_id = trim($_POST['catalog_item_id'] ?? '');

    if (!$uid) { $errs['auth'] = 'Login required.'; }
    if (!$catalog_item_id) { $errs['item'] = 'Content item ID required.'; }

    if (count($errs) <= 0) {
        update_content_progress($uid, $sid, $catalog_item_id, 'started');
        log_user_history($uid, $sid, 'content_started', ['catalog_item_id' => $catalog_item_id]);
        $_SESSION['success'] = 'Content marked as started.';
    } else {
        $_SESSION['error'] = implode('<br>', $errs);
    }
}

if (($_POST['action'] ?? '') === 'content_unstart') {
    $errs = [];
    $uid = $_SESSION['user_id'];
    $sid = $_SESSION['shard_id'];
    $catalog_item_id = trim($_POST['catalog_item_id'] ?? '');

    if (!$uid) { $errs['auth'] = 'Login required.'; }
    if (!$catalog_item_id) { $errs['item'] = 'Content item ID required.'; }

    if (count($errs) <= 0) {
        update_content_progress($uid, $sid, $catalog_item_id, 'recommended');
        log_user_history($uid, $sid, 'content_unstarted', ['catalog_item_id' => $catalog_item_id]);
        $_SESSION['success'] = 'Content removed from in-progress.';
    } else {
        $_SESSION['error'] = implode('<br>', $errs);
    }
}

if (($_POST['action'] ?? '') === 'content_complete') {
    $errs = [];
    $uid = $_SESSION['user_id'];
    $sid = $_SESSION['shard_id'];
    $catalog_item_id = trim($_POST['catalog_item_id'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if (!$uid) { $errs['auth'] = 'Login required.'; }
    if (!$catalog_item_id) { $errs['item'] = 'Content item ID required.'; }

    if (count($errs) <= 0) {
        update_content_progress($uid, $sid, $catalog_item_id, 'completed', $notes ?: null);
        log_user_history($uid, $sid, 'content_completed', ['catalog_item_id' => $catalog_item_id]);
        $_SESSION['success'] = 'Content marked as completed!';
    } else {
        $_SESSION['error'] = implode('<br>', $errs);
    }
}

if (($_POST['action'] ?? '') === 'content_uncomplete') {
    $errs = [];
    $uid = $_SESSION['user_id'];
    $sid = $_SESSION['shard_id'];
    $catalog_item_id = trim($_POST['catalog_item_id'] ?? '');

    if (!$uid) { $errs['auth'] = 'Login required.'; }
    if (!$catalog_item_id) { $errs['item'] = 'Content item ID required.'; }

    if (count($errs) <= 0) {
        update_content_progress($uid, $sid, $catalog_item_id, 'started');
        log_user_history($uid, $sid, 'content_uncompleted', ['catalog_item_id' => $catalog_item_id]);
        $_SESSION['success'] = 'Content completion removed.';
    } else {
        $_SESSION['error'] = implode('<br>', $errs);
    }
}

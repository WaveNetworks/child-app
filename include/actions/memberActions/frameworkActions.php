<?php
/**
 * frameworkActions.php
 * Admin framework sync actions.
 */

if (($_POST['action'] ?? '') === 'sync_framework') {
    $errs = [];
    $uid = $_SESSION['user_id'];

    if (!$uid) { $errs['auth'] = 'Login required.'; }
    if (!has_role('admin')) { $errs['role'] = 'Admin access required.'; }

    if (count($errs) <= 0) {
        $result = sync_framework_data();
        if (!empty($result['errors'])) {
            $_SESSION['error'] = implode('<br>', $result['errors']);
        } elseif ($result['changed']) {
            $_SESSION['success'] = "Framework synced! Version: {$result['version']}. {$result['files_loaded']} files loaded.";
        } else {
            $_SESSION['info'] = "Framework is already up to date (v{$result['version']}).";
        }
        $data['result'] = $result;
    } else {
        $_SESSION['error'] = implode('<br>', $errs);
    }
}

if (($_POST['action'] ?? '') === 'rollback_framework') {
    $errs = [];
    $uid = $_SESSION['user_id'];
    $version_id = (int)($_POST['version_id'] ?? 0);

    if (!$uid) { $errs['auth'] = 'Login required.'; }
    if (!has_role('admin')) { $errs['role'] = 'Admin access required.'; }
    if (!$version_id) { $errs['version'] = 'Version ID required.'; }

    if (count($errs) <= 0) {
        rollback_framework($version_id);
        $_SESSION['success'] = 'Framework rolled back successfully.';
    } else {
        $_SESSION['error'] = implode('<br>', $errs);
    }
}

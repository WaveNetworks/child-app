<?php
/**
 * memberActions/profileActions.php
 * Writes user preferences to childapp_user_prefs on the child shard.
 */

if (($_POST['action'] ?? '') == 'updateAppPreferences') {
    $errs = [];
    $uid  = $_SESSION['user_id'];
    $sid  = $_SESSION['shard_id'];

    if (!$uid) { $errs['auth'] = 'Login required.'; }

    // Collect preferences
    $prefs_to_save = [
        'items_per_page'      => $_POST['items_per_page'] ?? '15',
        'default_status'      => $_POST['default_status'] ?? 'draft',
        'email_on_item_change'=> !empty($_POST['email_on_item_change']) ? '1' : '0',
        'bio'                 => trim($_POST['bio'] ?? ''),
    ];

    // Validate
    if (!in_array($prefs_to_save['items_per_page'], ['10', '15', '25', '50'])) {
        $errs['items_per_page'] = 'Invalid items per page value.';
    }
    if (!in_array($prefs_to_save['default_status'], ['draft', 'active'])) {
        $errs['default_status'] = 'Invalid default status.';
    }
    if (mb_strlen($prefs_to_save['bio']) > 1000) {
        $errs['bio'] = 'Bio must be 1000 characters or fewer.';
    }

    if (count($errs) <= 0) {
        child_prime_shard($sid);

        foreach ($prefs_to_save as $key => $value) {
            // Upsert each preference
            child_db_query_shard_prepared($sid,
                "INSERT INTO childapp_user_prefs (user_id, pref_key, pref_value, updated)
                 VALUES (?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE pref_value = VALUES(pref_value), updated = NOW()",
                [$uid, $key, $value]
            );
        }

        $_SESSION['success'] = 'Preferences saved.';
    } else {
        $_SESSION['error'] = implode('<br>', $errs);
    }
}

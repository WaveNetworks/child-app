<?php
/**
 * memberActions/profileActions.php
 * Profile, password, and app preferences actions.
 *
 * Uses both global session flash (for toasts) and form-specific keys (for inline alerts):
 *   $_SESSION['success'] / $_SESSION['error']           → toast notification
 *   $_SESSION['profile_success'] / $_SESSION['profile_error']   → inline in profile card
 *   $_SESSION['password_success'] / $_SESSION['password_error'] → inline in password card
 *   $_SESSION['prefs_success'] / $_SESSION['prefs_error']       → inline in prefs card
 */

// ── Update personal info (name + email) ──────────────────────────────────────

if (($_POST['action'] ?? '') == 'updateProfile') {
    $errs = [];
    $uid  = (int) $_SESSION['user_id'];
    $sid  = $_SESSION['shard_id'];

    if (!$uid) { $errs['auth'] = 'Login required.'; }

    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name']  ?? '');
    $email      = trim($_POST['email']      ?? '');

    // Validate
    if (!$first_name) { $errs['first_name'] = 'First name is required.'; }
    if (mb_strlen($first_name) > 100) { $errs['first_name'] = 'First name must be 100 characters or fewer.'; }
    if (!$last_name)  { $errs['last_name'] = 'Last name is required.'; }
    if (mb_strlen($last_name) > 100) { $errs['last_name'] = 'Last name must be 100 characters or fewer.'; }
    if (!$email)      { $errs['email'] = 'Email is required.'; }
    if ($email && !valid_email($email)) { $errs['email'] = 'Please enter a valid email address.'; }

    // Check email uniqueness (skip if unchanged)
    if ($email && count($errs) <= 0) {
        $current_user = get_user($uid);
        if ($current_user && strtolower($email) !== strtolower($current_user['email'])) {
            $existing = get_user_by_email($email);
            if ($existing) {
                $errs['email'] = 'This email address is already in use.';
            }
        }
    }

    if (count($errs) <= 0) {
        // Update name on admin shard
        $s_first = sanitize($first_name, SQL);
        $s_last  = sanitize($last_name, SQL);
        prime_shard($sid);
        db_query_shard($sid, "UPDATE user_profile SET
            first_name = '$s_first',
            last_name  = '$s_last'
            WHERE user_id = '$uid'");

        // Update email on admin main DB
        $s_email = sanitize($email, SQL);
        db_query("UPDATE user SET email = '$s_email' WHERE user_id = '$uid'");

        // Update session so navbar reflects new email immediately
        $_SESSION['email'] = $email;

        $_SESSION['success']         = 'Profile updated successfully.';
        $_SESSION['profile_success'] = 'Profile updated successfully.';
    } else {
        $msg = implode('<br>', $errs);
        $_SESSION['error']         = $msg;
        $_SESSION['profile_error'] = $msg;
    }
}

// ── Change password ──────────────────────────────────────────────────────────

if (($_POST['action'] ?? '') == 'changePassword') {
    $errs = [];
    $uid  = (int) $_SESSION['user_id'];

    if (!$uid) { $errs['auth'] = 'Login required.'; }

    $current_password = $_POST['current_password'] ?? '';
    $new_password     = $_POST['new_password']     ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!$current_password) { $errs['current_password'] = 'Current password is required.'; }
    if (!$new_password)     { $errs['new_password'] = 'New password is required.'; }
    if (strlen($new_password) < 8) { $errs['new_password'] = 'New password must be at least 8 characters.'; }
    if ($new_password !== $confirm_password) { $errs['confirm_password'] = 'Passwords do not match.'; }

    // Verify current password
    if (count($errs) <= 0) {
        $user = get_user($uid);
        if (!$user || !verify_password($current_password, $user['password'])) {
            $errs['current_password'] = 'Current password is incorrect.';
        }
        // Block OAuth-only accounts (no password set)
        if ($user && !empty($user['oauth_provider']) && empty($user['password'])) {
            $errs['auth'] = 'Password changes are not available for OAuth accounts.';
        }
    }

    if (count($errs) <= 0) {
        $hashed   = hash_password($new_password);
        $s_hashed = sanitize($hashed, SQL);
        db_query("UPDATE user SET password = '$s_hashed' WHERE user_id = '$uid'");

        $_SESSION['success']          = 'Password updated successfully.';
        $_SESSION['password_success'] = 'Password updated successfully.';
    } else {
        $msg = implode('<br>', $errs);
        $_SESSION['error']          = $msg;
        $_SESSION['password_error'] = $msg;
    }
}

// ── Update app preferences (child shard) ─────────────────────────────────────

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

        $_SESSION['success']       = 'Preferences saved.';
        $_SESSION['prefs_success'] = 'Preferences saved.';
    } else {
        $msg = implode('<br>', $errs);
        $_SESSION['error']       = $msg;
        $_SESSION['prefs_error'] = $msg;
    }
}

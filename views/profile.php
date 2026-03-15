<?php
/**
 * views/profile.php
 * User profile page.
 * Sections: Personal Info (editable), Change Password, App Preferences.
 * Uses admin shard (user_profile), admin main (user), child shard (prefs).
 *
 * Inline feedback: each form card reads its own session keys (e.g. profile_success)
 * and clears them after display. Toast still fires from global $_SESSION['success'].
 */
$page_title = 'Profile';

$uid = $_SESSION['user_id'];
$sid = $_SESSION['shard_id'];

// Admin main: user record (for email, OAuth status)
$user = get_user($uid);

// Admin shard: user profile (name, member since)
$profile = get_user_profile($uid, $sid) ?: [];

// Child shard: user preferences
child_prime_shard($sid);
$prefs_r = child_db_query_shard_prepared($sid,
    "SELECT pref_key, pref_value FROM childapp_user_prefs WHERE user_id = ?",
    [$uid]
);
$prefs = [];
if ($prefs_r) {
    while ($row = db_fetch($prefs_r)) {
        $prefs[$row['pref_key']] = $row['pref_value'];
    }
}

// Is this an OAuth-only account? (no password set)
$is_oauth = !empty($user['oauth_provider']) && empty($user['password']);

// Read and clear inline feedback from session
$profile_success  = $_SESSION['profile_success']  ?? ''; unset($_SESSION['profile_success']);
$profile_error    = $_SESSION['profile_error']    ?? ''; unset($_SESSION['profile_error']);
$password_success = $_SESSION['password_success'] ?? ''; unset($_SESSION['password_success']);
$password_error   = $_SESSION['password_error']   ?? ''; unset($_SESSION['password_error']);
$prefs_success    = $_SESSION['prefs_success']    ?? ''; unset($_SESSION['prefs_success']);
$prefs_error      = $_SESSION['prefs_error']      ?? ''; unset($_SESSION['prefs_error']);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">Profile</h4>
    <a href="index.php?page=privacy" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-shield-check me-1"></i>Privacy & Data
    </a>
</div>

<div class="row g-4">
    <!-- Personal Information (editable) -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-person me-1"></i> Personal Information
            </div>
            <div class="card-body">
                <?php if ($profile_success) { ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-1"></i> <?= $profile_success ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php } ?>
                <?php if ($profile_error) { ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-1"></i> <?= $profile_error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php } ?>

                <div class="text-center mb-4">
                    <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center" style="width:80px;height:80px">
                        <span class="fs-2 text-primary fw-bold">
                            <?= h(strtoupper(substr($profile['first_name'] ?? '?', 0, 1))) ?><?= h(strtoupper(substr($profile['last_name'] ?? '', 0, 1))) ?>
                        </span>
                    </div>
                    <div class="text-muted small mt-2">
                        Member since <?= h(date('M j, Y', strtotime($profile['created'] ?? 'now'))) ?>
                    </div>
                </div>

                <form method="post">
                    <input type="hidden" name="action" value="updateProfile">

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label for="first_name" class="form-label">First name</label>
                            <input type="text" class="form-control" id="first_name" name="first_name"
                                   value="<?= h($profile['first_name'] ?? '') ?>" required>
                        </div>
                        <div class="col-sm-6">
                            <label for="last_name" class="form-label">Last name</label>
                            <input type="text" class="form-control" id="last_name" name="last_name"
                                   value="<?= h($profile['last_name'] ?? '') ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email address</label>
                        <input type="email" class="form-control" id="email" name="email"
                               value="<?= h($user['email'] ?? '') ?>" required>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i> Save Changes
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Change Password -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-shield-lock me-1"></i> Change Password
            </div>
            <div class="card-body">
                <?php if ($password_success) { ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-1"></i> <?= $password_success ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php } ?>
                <?php if ($password_error) { ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-1"></i> <?= $password_error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php } ?>

                <?php if ($is_oauth) { ?>
                <div class="text-center py-4">
                    <i class="bi bi-cloud-check fs-1 text-muted"></i>
                    <p class="text-muted mt-2 mb-0">
                        Your account uses <strong><?= h(ucfirst($user['oauth_provider'])) ?></strong> sign-in.<br>
                        Password management is handled by your identity provider.
                    </p>
                </div>
                <?php } else { ?>
                <form method="post">
                    <input type="hidden" name="action" value="changePassword">

                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current password</label>
                        <input type="password" class="form-control" id="current_password"
                               name="current_password" required autocomplete="current-password">
                    </div>

                    <div class="mb-3">
                        <label for="new_password" class="form-label">New password</label>
                        <input type="password" class="form-control" id="new_password"
                               name="new_password" required minlength="8" autocomplete="new-password">
                        <div class="form-text">Minimum 8 characters.</div>
                    </div>

                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm new password</label>
                        <input type="password" class="form-control" id="confirm_password"
                               name="confirm_password" required autocomplete="new-password">
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-key me-1"></i> Update Password
                    </button>
                </form>
                <?php } ?>
            </div>
        </div>
    </div>

    <!-- App Preferences (stored in child shard) -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-gear me-1"></i> App Preferences
            </div>
            <div class="card-body">
                <?php if ($prefs_success) { ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-1"></i> <?= $prefs_success ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php } ?>
                <?php if ($prefs_error) { ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-1"></i> <?= $prefs_error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php } ?>

                <form method="post">
                    <input type="hidden" name="action" value="updateAppPreferences">

                    <div class="mb-3">
                        <label for="pref_items_per_page" class="form-label">Items per page</label>
                        <select class="form-select" id="pref_items_per_page" name="items_per_page">
                            <?php foreach ([10, 15, 25, 50] as $opt) { ?>
                            <option value="<?= $opt ?>" <?= ($prefs['items_per_page'] ?? '15') == $opt ? 'selected' : '' ?>><?= $opt ?></option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="pref_default_status" class="form-label">Default item status</label>
                        <select class="form-select" id="pref_default_status" name="default_status">
                            <?php foreach (['draft' => 'Draft', 'active' => 'Active'] as $val => $label) { ?>
                            <option value="<?= $val ?>" <?= ($prefs['default_status'] ?? 'draft') === $val ? 'selected' : '' ?>><?= $label ?></option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="pref_email_on_item"
                               name="email_on_item_change" value="1"
                               <?= ($prefs['email_on_item_change'] ?? '0') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="pref_email_on_item">
                            Email me when an item status changes
                        </label>
                    </div>

                    <div class="mb-3">
                        <label for="pref_bio" class="form-label">Bio</label>
                        <textarea class="form-control" id="pref_bio" name="bio" rows="3"
                                  placeholder="Tell us about yourself..."
                        ><?= h($prefs['bio'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i> Save Preferences
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
/**
 * views/profile.php
 * User profile page.
 * Reads from admin's shard (user_profile) and child's shard (childapp_user_prefs).
 * Demonstrates using both shard systems side by side.
 */
$page_title = 'Profile';

$uid = $_SESSION['user_id'];
$sid = $_SESSION['shard_id'];

// Admin shard: user profile
prime_shard($sid);
$profile_r = db_query_shard($sid, "SELECT * FROM user_profile WHERE user_id = '$uid'");
$profile   = db_fetch($profile_r) ?: [];

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
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">Profile</h4>
</div>

<div class="row g-4">
    <!-- Profile info (read-only — managed by admin) -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-person me-1"></i> Your Information
            </div>
            <div class="card-body">
                <div class="text-center mb-4">
                    <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center" style="width:80px;height:80px">
                        <span class="fs-2 text-primary fw-bold">
                            <?= h(strtoupper(substr($profile['first_name'] ?? '?', 0, 1))) ?><?= h(strtoupper(substr($profile['last_name'] ?? '', 0, 1))) ?>
                        </span>
                    </div>
                </div>

                <table class="table table-borderless mb-0">
                    <tr>
                        <th class="text-muted" style="width:120px">Name</th>
                        <td><?= h(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')) ?></td>
                    </tr>
                    <tr>
                        <th class="text-muted">Email</th>
                        <td><?= h($_SESSION['email'] ?? '') ?></td>
                    </tr>
                    <tr>
                        <th class="text-muted">Member since</th>
                        <td><?= h($profile['created'] ?? 'N/A') ?></td>
                    </tr>
                </table>

                <div class="mt-3">
                    <small class="text-muted">
                        <i class="bi bi-info-circle me-1"></i>
                        Name and email are managed through your account settings.
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- App preferences (stored in child shard) -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-gear me-1"></i> App Preferences
            </div>
            <div class="card-body">
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

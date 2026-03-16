<?php
/**
 * views/profile.php
 * Combined Profile & Privacy page with responsive tabbed navigation.
 * Tabs: Profile, Password, Preferences, Consent, Login History, Sessions,
 *       Data Export, Danger Zone.
 *
 * Uses tabdrop.js for responsive overflow — extra tabs collapse into a
 * "More" dropdown when the card is too narrow.
 */
$page_title = 'Profile';

$uid = $_SESSION['user_id'];
$sid = $_SESSION['shard_id'];

// ── Data: Profile ──────────────────────────────────────────────────
$user    = get_user($uid);
$profile = get_user_profile($uid, $sid) ?: [];

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

$is_oauth = !empty($user['oauth_provider']) && empty($user['password']);

// ── Data: Privacy ──────────────────────────────────────────────────
$consent_statuses = get_all_consent_statuses($uid);
$consent_versions = get_all_consent_versions();
$pending_deletion = get_pending_deletion($uid);
$latest_export    = get_latest_export($uid);
$login_history    = function_exists('get_login_history') ? get_login_history($uid, 10) : [];
$active_devices   = function_exists('get_user_devices') ? get_user_devices($uid) : [];

// ── Inline feedback ────────────────────────────────────────────────
$profile_success  = $_SESSION['profile_success']  ?? ''; unset($_SESSION['profile_success']);
$profile_error    = $_SESSION['profile_error']    ?? ''; unset($_SESSION['profile_error']);
$password_success = $_SESSION['password_success'] ?? ''; unset($_SESSION['password_success']);
$password_error   = $_SESSION['password_error']   ?? ''; unset($_SESSION['password_error']);
$prefs_success    = $_SESSION['prefs_success']    ?? ''; unset($_SESSION['prefs_success']);
$prefs_error      = $_SESSION['prefs_error']      ?? ''; unset($_SESSION['prefs_error']);
$privacy_success  = $_SESSION['privacy_success']  ?? ''; unset($_SESSION['privacy_success']);
$privacy_error    = $_SESSION['privacy_error']    ?? ''; unset($_SESSION['privacy_error']);

// Which tab to show? (supports deep-linking via ?tab=)
$tab = $_GET['tab'] ?? 'profile';
$validTabs = ['profile','password','preferences','consent','history','sessions','export','danger'];
if (!in_array($tab, $validTabs)) { $tab = 'profile'; }
?>

<style>
/* Tabdrop: hide overflowed tabs, style the "More" dropdown */
[data-tabdrop] { flex-wrap: nowrap !important; overflow: hidden; }
[data-tabdrop] .nav-link { white-space: nowrap; }
.tabdrop-hidden { display: none !important; }
.tabdrop-more .dropdown-menu { min-width: 12rem; }
.tabdrop-more .dropdown-item.active { font-weight: 600; }
.tabdrop-more > .nav-link.active { border-color: var(--bs-nav-tabs-link-active-border-color, #dee2e6); border-bottom-color: transparent; }
</style>

<h4 class="mb-4">Profile & Settings</h4>

<div class="card">
    <!-- Tab navigation with tabdrop -->
    <div class="card-header p-0">
        <ul class="nav nav-tabs card-header-tabs px-2 pt-2" data-tabdrop role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link <?= $tab === 'profile' ? 'active' : '' ?>" id="tab-profile" data-bs-toggle="tab"
                   href="#pane-profile" role="tab"><i class="bi bi-person me-1"></i>Profile</a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?= $tab === 'password' ? 'active' : '' ?>" id="tab-password" data-bs-toggle="tab"
                   href="#pane-password" role="tab"><i class="bi bi-key me-1"></i>Password</a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?= $tab === 'preferences' ? 'active' : '' ?>" id="tab-preferences" data-bs-toggle="tab"
                   href="#pane-preferences" role="tab"><i class="bi bi-gear me-1"></i>Preferences</a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?= $tab === 'consent' ? 'active' : '' ?>" id="tab-consent" data-bs-toggle="tab"
                   href="#pane-consent" role="tab"><i class="bi bi-clipboard-check me-1"></i>Consent</a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?= $tab === 'history' ? 'active' : '' ?>" id="tab-history" data-bs-toggle="tab"
                   href="#pane-history" role="tab"><i class="bi bi-clock-history me-1"></i>Login History</a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?= $tab === 'sessions' ? 'active' : '' ?>" id="tab-sessions" data-bs-toggle="tab"
                   href="#pane-sessions" role="tab"><i class="bi bi-laptop me-1"></i>Sessions</a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?= $tab === 'export' ? 'active' : '' ?>" id="tab-export" data-bs-toggle="tab"
                   href="#pane-export" role="tab"><i class="bi bi-download me-1"></i>Data Export</a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?= $tab === 'danger' ? 'active text-danger' : 'text-danger' ?>" id="tab-danger" data-bs-toggle="tab"
                   href="#pane-danger" role="tab"><i class="bi bi-exclamation-triangle me-1"></i>Danger Zone</a>
            </li>
        </ul>
    </div>

    <!-- Tab panes -->
    <div class="tab-content">

        <!-- ═══════════════════════════════════════════════════════════
             TAB: Profile
             ═══════════════════════════════════════════════════════════ -->
        <div class="tab-pane fade <?= $tab === 'profile' ? 'show active' : '' ?>" id="pane-profile" role="tabpanel">
            <form method="post">
                <input type="hidden" name="action" value="updateProfile">
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

                    <div class="mb-0">
                        <label for="email" class="form-label">Email address</label>
                        <input type="email" class="form-control" id="email" name="email"
                               value="<?= h($user['email'] ?? '') ?>" required>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>

        <!-- ═══════════════════════════════════════════════════════════
             TAB: Password
             ═══════════════════════════════════════════════════════════ -->
        <div class="tab-pane fade <?= $tab === 'password' ? 'show active' : '' ?>" id="pane-password" role="tabpanel">
            <?php if ($is_oauth) { ?>
            <div class="card-body text-center py-4">
                <i class="bi bi-cloud-check fs-1 text-muted"></i>
                <p class="text-muted mt-2 mb-0">
                    Your account uses <strong><?= h(ucfirst($user['oauth_provider'])) ?></strong> sign-in.<br>
                    Password management is handled by your identity provider.
                </p>
            </div>
            <?php } else { ?>
            <form method="post">
                <input type="hidden" name="action" value="changePassword">
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

                    <div class="mb-0">
                        <label for="confirm_password" class="form-label">Confirm new password</label>
                        <input type="password" class="form-control" id="confirm_password"
                               name="confirm_password" required autocomplete="new-password">
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-key me-1"></i> Update Password
                    </button>
                </div>
            </form>
            <?php } ?>
        </div>

        <!-- ═══════════════════════════════════════════════════════════
             TAB: Preferences
             ═══════════════════════════════════════════════════════════ -->
        <div class="tab-pane fade <?= $tab === 'preferences' ? 'show active' : '' ?>" id="pane-preferences" role="tabpanel">
            <form method="post">
                <input type="hidden" name="action" value="updateAppPreferences">
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

                    <div class="mb-0">
                        <label for="pref_bio" class="form-label">Bio</label>
                        <textarea class="form-control" id="pref_bio" name="bio" rows="3"
                                  placeholder="Tell us about yourself..."
                        ><?= h($prefs['bio'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i> Save Preferences
                    </button>
                </div>
            </form>
        </div>

        <!-- ═══════════════════════════════════════════════════════════
             TAB: Consent
             ═══════════════════════════════════════════════════════════ -->
        <div class="tab-pane fade <?= $tab === 'consent' ? 'show active' : '' ?>" id="pane-consent" role="tabpanel">
            <form method="post">
                <input type="hidden" name="action" value="updateConsent">
                <div class="card-body">
                    <?php if ($privacy_success) { ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-1"></i> <?= $privacy_success ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php } ?>
                    <?php if ($privacy_error) { ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle me-1"></i> <?= $privacy_error ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php } ?>

                    <p class="text-muted small mb-3">
                        Manage what you consent to. Required consents (Terms of Service, Privacy Policy) cannot be withdrawn while your account is active.
                    </p>

                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <div>
                            <strong>Terms of Service</strong>
                            <div class="text-muted small">Required to use the service</div>
                        </div>
                        <span class="badge bg-success"><i class="bi bi-check-lg me-1"></i>Accepted</span>
                    </div>

                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <div>
                            <strong>Privacy Policy</strong>
                            <div class="text-muted small">Required to use the service</div>
                        </div>
                        <span class="badge bg-success"><i class="bi bi-check-lg me-1"></i>Accepted</span>
                    </div>

                    <div class="form-check form-switch py-2 border-bottom">
                        <input class="form-check-input" type="checkbox" id="consent_marketing_email"
                               name="consent_marketing_email" value="1"
                               <?= ($consent_statuses['marketing_email'] ?? '') === 'granted' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="consent_marketing_email">
                            <strong>Marketing Emails</strong>
                            <div class="text-muted small">Receive product updates, tips, and promotional offers</div>
                        </label>
                    </div>

                    <div class="form-check form-switch py-2 border-bottom">
                        <input class="form-check-input" type="checkbox" id="consent_cookie_analytics"
                               name="consent_cookie_analytics" value="1"
                               <?= ($consent_statuses['cookie_analytics'] ?? '') === 'granted' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="consent_cookie_analytics">
                            <strong>Analytics Cookies</strong>
                            <div class="text-muted small">Help us understand how you use the site to improve it</div>
                        </label>
                    </div>

                    <div class="form-check form-switch py-2">
                        <input class="form-check-input" type="checkbox" id="consent_cookie_marketing"
                               name="consent_cookie_marketing" value="1"
                               <?= ($consent_statuses['cookie_marketing'] ?? '') === 'granted' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="consent_cookie_marketing">
                            <strong>Marketing Cookies</strong>
                            <div class="text-muted small">Allow personalized advertising based on your browsing</div>
                        </label>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i> Save Preferences
                    </button>
                </div>
            </form>
        </div>

        <!-- ═══════════════════════════════════════════════════════════
             TAB: Login History
             ═══════════════════════════════════════════════════════════ -->
        <div class="tab-pane fade <?= $tab === 'history' ? 'show active' : '' ?>" id="pane-history" role="tabpanel">
            <?php if (empty($login_history)) { ?>
            <div class="card-body text-center py-4 text-muted">
                <i class="bi bi-clock fs-1"></i>
                <p class="mt-2 mb-0">No login history recorded yet.</p>
            </div>
            <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Browser</th>
                            <th>IP Address</th>
                            <th>Method</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($login_history as $entry) { ?>
                        <tr>
                            <td class="small"><?= h(date('M j, g:i A', strtotime($entry['created']))) ?></td>
                            <td class="small"><?= h($entry['browser'] ?? 'Unknown') ?></td>
                            <td class="small"><code><?= h($entry['ip_address'] ?? '') ?></code></td>
                            <td class="small">
                                <?php
                                $method_icons = ['password' => 'bi-key', 'oauth' => 'bi-cloud', 'remember_me' => 'bi-cookie', 'saml' => 'bi-building', '2fa' => 'bi-shield-lock'];
                                $icon = $method_icons[$entry['login_method']] ?? 'bi-box-arrow-in-right';
                                ?>
                                <i class="bi <?= $icon ?>" title="<?= h($entry['login_method']) ?>"></i>
                                <?= h($entry['login_method']) ?>
                            </td>
                            <td>
                                <?php if ($entry['status'] === 'success') { ?>
                                <span class="badge bg-success">Success</span>
                                <?php } else { ?>
                                <span class="badge bg-danger">Failed</span>
                                <?php } ?>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <?php } ?>
        </div>

        <!-- ═══════════════════════════════════════════════════════════
             TAB: Sessions
             ═══════════════════════════════════════════════════════════ -->
        <div class="tab-pane fade <?= $tab === 'sessions' ? 'show active' : '' ?>" id="pane-sessions" role="tabpanel">
            <?php if (empty($active_devices)) { ?>
            <div class="card-body text-center py-4 text-muted">
                <i class="bi bi-laptop fs-1"></i>
                <p class="mt-2 mb-0">No active sessions found.</p>
            </div>
            <?php } else { ?>
            <div class="list-group list-group-flush">
                <?php foreach ($active_devices as $device) { ?>
                <div class="list-group-item d-flex justify-content-between align-items-start">
                    <div>
                        <div class="fw-bold small">
                            <i class="bi bi-<?= ($device['browser'] ?? '') === 'Chrome' ? 'browser-chrome' : (($device['browser'] ?? '') === 'Safari' ? 'browser-safari' : (($device['browser'] ?? '') === 'Firefox' ? 'browser-firefox' : (($device['browser'] ?? '') === 'Edge' ? 'browser-edge' : 'globe'))) ?> me-1"></i>
                            <?= h($device['browser'] ?? 'Unknown Browser') ?>
                        </div>
                        <div class="small text-muted">
                            <code><?= h($device['ip_address'] ?? '') ?></code>
                            <?php if ($device['last_used']) { ?>
                            — Last active <?= h(date('M j, g:i A', strtotime($device['last_used']))) ?>
                            <?php } ?>
                        </div>
                    </div>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="action" value="revokeSession">
                        <input type="hidden" name="device_id" value="<?= (int)$device['device_id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-secondary" title="Sign out this device" onclick="return confirm('Sign out this device?')">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </form>
                </div>
                <?php } ?>
            </div>
            <?php if (count($active_devices) > 1) { ?>
            <div class="card-footer">
                <form method="post" class="d-inline">
                    <input type="hidden" name="action" value="revokeAllSessions">
                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Sign out of all other devices?')">
                        <i class="bi bi-box-arrow-right me-1"></i>Sign out all others
                    </button>
                </form>
            </div>
            <?php } ?>
            <?php } ?>
        </div>

        <!-- ═══════════════════════════════════════════════════════════
             TAB: Data Export
             ═══════════════════════════════════════════════════════════ -->
        <div class="tab-pane fade <?= $tab === 'export' ? 'show active' : '' ?>" id="pane-export" role="tabpanel">
            <form method="post">
                <input type="hidden" name="action" value="requestDataExport">
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        Download a copy of all data we hold about you. This includes your profile, items, preferences, activity history, and consent records.
                    </p>

                    <?php if ($latest_export && $latest_export['status'] === 'ready' && strtotime($latest_export['expires_at']) > time()) { ?>
                    <div class="alert alert-info">
                        <i class="bi bi-file-earmark-check me-1"></i>
                        Your data export is ready.
                        <strong><?= h(strtoupper($latest_export['format'])) ?></strong> format,
                        <?= h(number_format($latest_export['file_size'] / 1024, 1)) ?> KB.
                        Expires <?= h(date('M j, Y', strtotime($latest_export['expires_at']))) ?>.
                        <div class="mt-2">
                            <a href="index.php?action=downloadExport" class="btn btn-sm btn-info" data-no-spa>
                                <i class="bi bi-download me-1"></i> Download Export
                            </a>
                        </div>
                    </div>
                    <?php } elseif ($latest_export && in_array($latest_export['status'], ['pending', 'processing'])) { ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-hourglass-split me-1"></i>
                        Your data export is being prepared. Please check back shortly.
                    </div>
                    <?php } ?>

                    <div class="mb-3">
                        <label for="export_format" class="form-label">Export format</label>
                        <select class="form-select" id="export_format" name="export_format">
                            <option value="json">JSON (machine-readable)</option>
                            <option value="csv">CSV (spreadsheet)</option>
                        </select>
                        <div class="form-text">JSON is recommended for data portability.</div>
                    </div>

                    <h6 class="text-muted"><i class="bi bi-info-circle me-1"></i> What's included</h6>
                    <ul class="small text-muted mb-0">
                        <li>Account information (name, email, registration date)</li>
                        <li>Profile data</li>
                        <li>Items you've created</li>
                        <li>App preferences</li>
                        <li>Activity history</li>
                        <li>Consent records</li>
                        <li>Device/session information</li>
                    </ul>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-file-earmark-arrow-down me-1"></i> Request Data Export
                    </button>
                </div>
            </form>
        </div>

        <!-- ═══════════════════════════════════════════════════════════
             TAB: Danger Zone
             ═══════════════════════════════════════════════════════════ -->
        <div class="tab-pane fade <?= $tab === 'danger' ? 'show active' : '' ?>" id="pane-danger" role="tabpanel">
            <?php if ($pending_deletion) { ?>
            <div class="card-body">
                <div class="alert alert-danger bg-danger bg-opacity-10 border-danger mb-3">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    <strong>Account Deletion</strong> — this action is irreversible after the cooling-off period.
                </div>
                <div class="alert alert-warning mb-0">
                    <h6 class="alert-heading"><i class="bi bi-clock-history me-1"></i> Deletion Scheduled</h6>
                    <p class="mb-1">
                        Your account is scheduled for permanent deletion on
                        <strong><?= h(date('F j, Y', strtotime($pending_deletion['cancel_before']))) ?></strong>.
                    </p>
                    <p class="mb-0 small">
                        Requested on <?= h(date('M j, Y \a\t g:i A', strtotime($pending_deletion['requested_at']))) ?>.
                        <?php if ($pending_deletion['reason']) { ?>
                        <br>Reason: <?= h($pending_deletion['reason']) ?>
                        <?php } ?>
                    </p>
                </div>
            </div>
            <div class="card-footer">
                <form method="post" class="d-inline">
                    <input type="hidden" name="action" value="cancelAccountDeletion">
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-x-circle me-1"></i> Cancel Deletion Request
                    </button>
                </form>
            </div>

            <?php } else { ?>
            <form method="post" id="deletionForm">
                <input type="hidden" name="action" value="requestAccountDeletion">
                <div class="card-body">
                    <div class="alert alert-danger bg-danger bg-opacity-10 border-danger mb-3">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        <strong>Account Deletion</strong> — this action is irreversible after the cooling-off period.
                    </div>

                    <p>
                        Once your account is deleted, all of your data will be permanently removed.
                        This includes your profile, items, preferences, and all associated data.
                    </p>
                    <p class="text-muted small">
                        <i class="bi bi-shield-check me-1"></i>
                        For your protection, there is a <strong>30-day cooling-off period</strong>.
                        You can cancel the deletion at any time during this period.
                        We recommend downloading your data before requesting deletion.
                    </p>

                    <div class="mb-3">
                        <label for="deletion_reason" class="form-label">Reason for leaving (optional)</label>
                        <textarea class="form-control" id="deletion_reason" name="deletion_reason"
                                  rows="2" placeholder="Help us improve — why are you deleting your account?"></textarea>
                    </div>

                    <div class="mb-0">
                        <label for="confirm_delete" class="form-label">
                            Type <strong class="text-danger">DELETE</strong> to confirm
                        </label>
                        <input type="text" class="form-control" id="confirm_delete" name="confirm_delete"
                               placeholder="Type DELETE here" autocomplete="off" required>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-danger" id="btnDeleteAccount" disabled>
                        <i class="bi bi-trash me-1"></i> Request Account Deletion
                    </button>
                </div>
            </form>
            <?php } ?>
        </div>

    </div><!-- /.tab-content -->
</div><!-- /.card -->

<script src="../assets/js/tabdrop.js"></script>
<script>
// Enable delete button only when user types DELETE
(function(){
    var input = document.getElementById('confirm_delete');
    var btn = document.getElementById('btnDeleteAccount');
    if (input && btn) {
        input.addEventListener('input', function(){
            btn.disabled = (this.value.trim() !== 'DELETE');
        });
    }
})();
</script>

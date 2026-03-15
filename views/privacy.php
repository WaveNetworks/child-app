<?php
/**
 * views/privacy.php
 * GDPR Privacy & Data management page.
 * Sections: Consent Preferences, Data Export, Account Deletion.
 */
$page_title = 'Privacy & Data';

$uid = $_SESSION['user_id'];
$sid = $_SESSION['shard_id'];

// Current consent statuses
$consent_statuses = get_all_consent_statuses($uid);
$consent_versions = get_all_consent_versions();

// Pending deletion request
$pending_deletion = get_pending_deletion($uid);

// Latest data export
$latest_export = get_latest_export($uid);

// Login history (recent 20)
$login_history = function_exists('get_login_history') ? get_login_history($uid, 10) : [];

// Active sessions/devices
$active_devices = function_exists('get_user_devices') ? get_user_devices($uid) : [];

// Inline feedback
$privacy_success = $_SESSION['privacy_success'] ?? ''; unset($_SESSION['privacy_success']);
$privacy_error   = $_SESSION['privacy_error']   ?? ''; unset($_SESSION['privacy_error']);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">Privacy & Data</h4>
</div>

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

<div class="row g-4">

    <!-- Consent Preferences -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-clipboard-check me-1"></i> Consent Preferences
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Manage what you consent to. Required consents (Terms of Service, Privacy Policy) cannot be withdrawn while your account is active.
                </p>

                <form method="post">
                    <input type="hidden" name="action" value="updateConsent">

                    <!-- Terms of Service (required — display only) -->
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <div>
                            <strong>Terms of Service</strong>
                            <div class="text-muted small">Required to use the service</div>
                        </div>
                        <span class="badge bg-success"><i class="bi bi-check-lg me-1"></i>Accepted</span>
                    </div>

                    <!-- Privacy Policy (required — display only) -->
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <div>
                            <strong>Privacy Policy</strong>
                            <div class="text-muted small">Required to use the service</div>
                        </div>
                        <span class="badge bg-success"><i class="bi bi-check-lg me-1"></i>Accepted</span>
                    </div>

                    <!-- Marketing emails (optional) -->
                    <div class="form-check form-switch py-2 border-bottom">
                        <input class="form-check-input" type="checkbox" id="consent_marketing_email"
                               name="consent_marketing_email" value="1"
                               <?= ($consent_statuses['marketing_email'] ?? '') === 'granted' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="consent_marketing_email">
                            <strong>Marketing Emails</strong>
                            <div class="text-muted small">Receive product updates, tips, and promotional offers</div>
                        </label>
                    </div>

                    <!-- Analytics cookies (optional) -->
                    <div class="form-check form-switch py-2 border-bottom">
                        <input class="form-check-input" type="checkbox" id="consent_cookie_analytics"
                               name="consent_cookie_analytics" value="1"
                               <?= ($consent_statuses['cookie_analytics'] ?? '') === 'granted' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="consent_cookie_analytics">
                            <strong>Analytics Cookies</strong>
                            <div class="text-muted small">Help us understand how you use the site to improve it</div>
                        </label>
                    </div>

                    <!-- Marketing cookies (optional) -->
                    <div class="form-check form-switch py-2 mb-3">
                        <input class="form-check-input" type="checkbox" id="consent_cookie_marketing"
                               name="consent_cookie_marketing" value="1"
                               <?= ($consent_statuses['cookie_marketing'] ?? '') === 'granted' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="consent_cookie_marketing">
                            <strong>Marketing Cookies</strong>
                            <div class="text-muted small">Allow personalized advertising based on your browsing</div>
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i> Save Preferences
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Data Export -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-download me-1"></i> Export Your Data
            </div>
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

                <form method="post">
                    <input type="hidden" name="action" value="requestDataExport">

                    <div class="mb-3">
                        <label for="export_format" class="form-label">Export format</label>
                        <select class="form-select" id="export_format" name="export_format">
                            <option value="json">JSON (machine-readable)</option>
                            <option value="csv">CSV (spreadsheet)</option>
                        </select>
                        <div class="form-text">JSON is recommended for data portability.</div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-file-earmark-arrow-down me-1"></i> Request Data Export
                    </button>
                </form>

                <hr>
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
        </div>
    </div>

    <!-- Login History -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-clock-history me-1"></i> Login History</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($login_history)) { ?>
                <div class="text-center py-4 text-muted">
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
        </div>
    </div>

    <!-- Active Sessions -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-laptop me-1"></i> Active Sessions</span>
                <?php if (count($active_devices) > 1) { ?>
                <form method="post" class="d-inline">
                    <input type="hidden" name="action" value="revokeAllSessions">
                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Sign out of all other devices?')">
                        <i class="bi bi-box-arrow-right me-1"></i>Sign out all others
                    </button>
                </form>
                <?php } ?>
            </div>
            <div class="card-body p-0">
                <?php if (empty($active_devices)) { ?>
                <div class="text-center py-4 text-muted">
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
                <?php } ?>
            </div>
        </div>
    </div>

    <!-- Account Deletion -->
    <div class="col-12">
        <div class="card border-danger">
            <div class="card-header bg-danger bg-opacity-10 text-danger">
                <i class="bi bi-exclamation-triangle me-1"></i> Danger Zone — Account Deletion
            </div>
            <div class="card-body">
                <?php if ($pending_deletion) { ?>
                <!-- Active deletion request -->
                <div class="alert alert-warning">
                    <h6 class="alert-heading"><i class="bi bi-clock-history me-1"></i> Deletion Scheduled</h6>
                    <p class="mb-1">
                        Your account is scheduled for permanent deletion on
                        <strong><?= h(date('F j, Y', strtotime($pending_deletion['cancel_before']))) ?></strong>.
                    </p>
                    <p class="mb-2 small">
                        Requested on <?= h(date('M j, Y \a\t g:i A', strtotime($pending_deletion['requested_at']))) ?>.
                        <?php if ($pending_deletion['reason']) { ?>
                        <br>Reason: <?= h($pending_deletion['reason']) ?>
                        <?php } ?>
                    </p>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="action" value="cancelAccountDeletion">
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-x-circle me-1"></i> Cancel Deletion Request
                        </button>
                    </form>
                </div>

                <?php } else { ?>
                <!-- No pending request — show deletion form -->
                <div class="row">
                    <div class="col-lg-8">
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

                        <form method="post" id="deletionForm">
                            <input type="hidden" name="action" value="requestAccountDeletion">

                            <div class="mb-3">
                                <label for="deletion_reason" class="form-label">Reason for leaving (optional)</label>
                                <textarea class="form-control" id="deletion_reason" name="deletion_reason"
                                          rows="2" placeholder="Help us improve — why are you deleting your account?"></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="confirm_delete" class="form-label">
                                    Type <strong class="text-danger">DELETE</strong> to confirm
                                </label>
                                <input type="text" class="form-control" id="confirm_delete" name="confirm_delete"
                                       placeholder="Type DELETE here" autocomplete="off" required>
                            </div>

                            <button type="submit" class="btn btn-danger" id="btnDeleteAccount" disabled>
                                <i class="bi bi-trash me-1"></i> Request Account Deletion
                            </button>
                        </form>
                    </div>
                </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

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

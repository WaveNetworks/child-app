<?php
/**
 * memberActions/privacyActions.php
 * GDPR privacy actions: consent management, data export, account deletion.
 */

// ── Update consent preferences ───────────────────────────────────────────────

if (($_POST['action'] ?? '') == 'updateConsent') {
    $errs = [];
    $uid  = (int) $_SESSION['user_id'];

    if (!$uid) { $errs['auth'] = 'Login required.'; }

    $consent_types = ['marketing_email', 'cookie_analytics', 'cookie_marketing'];

    if (count($errs) <= 0) {
        $current = get_all_consent_statuses($uid);
        $versions = get_all_consent_versions();

        foreach ($consent_types as $type) {
            $submitted = !empty($_POST['consent_' . $type]) ? 'granted' : 'withdrawn';
            $current_status = $current[$type] ?? null;

            // Only record if status actually changed
            if ($submitted !== $current_status) {
                $vid = isset($versions[$type]) ? (int)$versions[$type]['version_id'] : null;
                record_consent($uid, $type, $submitted, $vid);
            }
        }

        $_SESSION['success']         = 'Consent preferences updated.';
        $_SESSION['privacy_success'] = 'Consent preferences updated.';
    } else {
        $msg = implode('<br>', $errs);
        $_SESSION['error']         = $msg;
        $_SESSION['privacy_error'] = $msg;
    }
}

// ── Request data export ──────────────────────────────────────────────────────

if (($_POST['action'] ?? '') == 'requestDataExport') {
    $errs = [];
    $uid  = (int) $_SESSION['user_id'];
    $sid  = $_SESSION['shard_id'];

    if (!$uid) { $errs['auth'] = 'Login required.'; }

    $format = in_array($_POST['export_format'] ?? '', ['json', 'csv']) ? $_POST['export_format'] : 'json';

    if (count($errs) <= 0) {
        $export_id = request_data_export($uid, $format);

        if ($export_id === false) {
            $_SESSION['warning']        = 'You already have a pending data export request.';
            $_SESSION['privacy_error']  = 'You already have a pending data export request. Please wait for it to complete.';
        } else {
            // For now, build and complete the export immediately (synchronous)
            // In production this could be a background job for large datasets
            $data = build_export_data($uid, $sid);

            // Add child app data
            child_prime_shard($sid);

            // Child items
            $r = child_db_query_prepared("SELECT item_id, title, description, status, created, updated FROM items WHERE user_id = ?", [$uid]);
            $items = [];
            while ($row = db_fetch($r)) { $items[] = $row; }
            $data['child_app_items'] = $items;

            // Child preferences
            $r = child_db_query_shard_prepared($sid,
                "SELECT pref_key, pref_value, updated FROM childapp_user_prefs WHERE user_id = ?", [$uid]);
            $child_prefs = [];
            while ($row = db_fetch($r)) { $child_prefs[] = $row; }
            $data['child_app_preferences'] = $child_prefs;

            // Child history
            $r = child_db_query_shard_prepared($sid,
                "SELECT event_type, event_data, ip_address, created FROM user_history WHERE user_id = ? ORDER BY created DESC", [$uid]);
            $history = [];
            while ($row = db_fetch($r)) { $history[] = $row; }
            $data['child_app_history'] = $history;

            // Write to temp file
            global $files_location;
            $export_dir = $files_location . 'exports/';
            if (!is_dir($export_dir)) { mkdir($export_dir, 0755, true); }

            $filename = 'data_export_' . $uid . '_' . date('Ymd_His');
            if ($format === 'json') {
                $filepath = $export_dir . $filename . '.json';
                file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            } else {
                // CSV: flatten the main fields
                $filepath = $export_dir . $filename . '.csv';
                $fp = fopen($filepath, 'w');
                fputcsv($fp, ['Section', 'Key', 'Value']);
                // User data
                if (isset($data['user'])) {
                    foreach ($data['user'] as $k => $v) {
                        fputcsv($fp, ['User', $k, is_array($v) ? json_encode($v) : $v]);
                    }
                }
                // Profile
                if (isset($data['profile'])) {
                    foreach ($data['profile'] as $k => $v) {
                        fputcsv($fp, ['Profile', $k, is_array($v) ? json_encode($v) : $v]);
                    }
                }
                // Items
                foreach ($items as $i => $item) {
                    foreach ($item as $k => $v) {
                        fputcsv($fp, ['Item_' . ($i+1), $k, $v]);
                    }
                }
                // Consent
                foreach ($data['consent_history'] as $i => $c) {
                    fputcsv($fp, ['Consent_' . ($i+1), $c['consent_type'], $c['action'] . ' on ' . $c['created']]);
                }
                fclose($fp);
            }

            $file_size = filesize($filepath);
            complete_data_export($export_id, $filepath, $file_size);

            $_SESSION['success']         = 'Your data export is ready for download.';
            $_SESSION['privacy_success'] = 'Your data export is ready for download.';
        }
    } else {
        $msg = implode('<br>', $errs);
        $_SESSION['error']         = $msg;
        $_SESSION['privacy_error'] = $msg;
    }
}

// ── Download data export ─────────────────────────────────────────────────────

if (($_GET['action'] ?? '') == 'downloadExport') {
    $uid = (int) $_SESSION['user_id'];
    if ($uid) {
        $export = get_latest_export($uid);
        if ($export && $export['status'] === 'ready' && file_exists($export['file_path'])) {
            // Check expiry
            if (strtotime($export['expires_at']) > time()) {
                $ext = pathinfo($export['file_path'], PATHINFO_EXTENSION);
                $mime = $ext === 'json' ? 'application/json' : 'text/csv';
                header('Content-Type: ' . $mime);
                header('Content-Disposition: attachment; filename="my_data_export.' . $ext . '"');
                header('Content-Length: ' . $export['file_size']);
                readfile($export['file_path']);
                exit;
            }
        }
    }
    $_SESSION['error'] = 'Export not found or has expired.';
}

// ── Request account deletion ─────────────────────────────────────────────────

if (($_POST['action'] ?? '') == 'requestAccountDeletion') {
    $errs = [];
    $uid  = (int) $_SESSION['user_id'];

    if (!$uid) { $errs['auth'] = 'Login required.'; }

    $reason      = trim($_POST['deletion_reason'] ?? '');
    $confirm_text = trim($_POST['confirm_delete'] ?? '');

    if ($confirm_text !== 'DELETE') {
        $errs['confirm'] = 'Please type DELETE to confirm.';
    }

    if (count($errs) <= 0) {
        $request_id = request_account_deletion($uid, $reason);

        if ($request_id === false) {
            $_SESSION['warning']       = 'You already have a pending deletion request.';
            $_SESSION['privacy_error'] = 'You already have a pending deletion request. You can cancel it below.';
        } else {
            $_SESSION['success']         = 'Account deletion requested. You have 30 days to cancel.';
            $_SESSION['privacy_success'] = 'Account deletion requested. Your account will be permanently deleted after 30 days. You can cancel this request at any time before then.';
        }
    } else {
        $msg = implode('<br>', $errs);
        $_SESSION['error']         = $msg;
        $_SESSION['privacy_error'] = $msg;
    }
}

// ── Cancel account deletion ──────────────────────────────────────────────────

if (($_POST['action'] ?? '') == 'cancelAccountDeletion') {
    $uid = (int) $_SESSION['user_id'];

    if ($uid) {
        cancel_account_deletion($uid);
        $_SESSION['success']         = 'Account deletion request cancelled.';
        $_SESSION['privacy_success'] = 'Your account deletion request has been cancelled. Your account is safe.';
    }
}

// ── Revoke a single device session ───────────────────────────────────────────

if (($_POST['action'] ?? '') == 'revokeSession') {
    $uid       = (int) $_SESSION['user_id'];
    $device_id = (int) ($_POST['device_id'] ?? 0);

    if ($uid && $device_id && function_exists('revoke_device')) {
        revoke_device($device_id, $uid);
        $_SESSION['success']         = 'Device session revoked.';
        $_SESSION['privacy_success'] = 'The device has been signed out.';
    }
}

// ── Revoke all other sessions ────────────────────────────────────────────────

if (($_POST['action'] ?? '') == 'revokeAllSessions') {
    $uid = (int) $_SESSION['user_id'];

    if ($uid && function_exists('revoke_all_other_devices')) {
        $current_cookie = $_COOKIE['wn_auto_login'] ?? null;
        revoke_all_other_devices($uid, $current_cookie);
        $_SESSION['success']         = 'All other sessions have been signed out.';
        $_SESSION['privacy_success'] = 'All other devices have been signed out.';
    }
}

<?php
/**
 * inviteActions.php
 * Coach invite link management.
 */

if (($_POST['action'] ?? '') === 'generate_invite') {
    $errs = [];
    $uid = $_SESSION['user_id'];
    $sid = $_SESSION['shard_id'];

    if (!$uid) { $errs['auth'] = 'Login required.'; }
    if (!can_be_coach($uid, $sid)) { $errs['tier'] = 'You must be assessed at Pro level or above to generate invites.'; }

    $max_uses = ($_POST['max_uses'] ?? '') !== '' ? (int)$_POST['max_uses'] : null;
    $expires_hours = ($_POST['expires_hours'] ?? '') !== '' ? (int)$_POST['expires_hours'] : null;
    $type = $_POST['invite_type'] ?? 'coach_to_seller';

    if (count($errs) <= 0) {
        // Ensure coach role on first invite generation
        ensure_coach_role($uid, $sid);

        $result = generate_invite_token($uid, $sid, $type, $max_uses, $expires_hours);
        if (isset($result['error'])) {
            $_SESSION['error'] = $result['error'];
        } else {
            $data['token'] = $result['token'];
            $data['invite_url'] = get_invite_link_url($result['token']);
            $_SESSION['success'] = 'Invite link created!';
        }
    } else {
        $_SESSION['error'] = implode('<br>', $errs);
    }
}

if (($_POST['action'] ?? '') === 'disable_invite') {
    $errs = [];
    $uid = $_SESSION['user_id'];
    $invite_id = (int)($_POST['invite_id'] ?? 0);

    if (!$uid) { $errs['auth'] = 'Login required.'; }
    if (!$invite_id) { $errs['id'] = 'Invite ID required.'; }

    if (count($errs) <= 0) {
        child_db_query_prepared(
            "UPDATE invite_links SET is_active = 0 WHERE id = ? AND creator_user_id = ?",
            [$invite_id, $uid]
        );
        $_SESSION['success'] = 'Invite link disabled.';
    } else {
        $_SESSION['error'] = implode('<br>', $errs);
    }
}

if (($_POST['action'] ?? '') === 'accept_invite') {
    $errs = [];
    $uid = $_SESSION['user_id'];
    $sid = $_SESSION['shard_id'];
    $token = trim($_POST['token'] ?? '');

    if (!$uid) { $errs['auth'] = 'Login required.'; }
    if (!$token) { $errs['token'] = 'Token required.'; }

    if (count($errs) <= 0) {
        $result = redeem_invite_token($token, $uid, $sid);
        if ($result['valid']) {
            $_SESSION['success'] = 'You are now connected with coach ' . ($result['coach_name'] ?? 'your coach') . '!';

            // Send notifications
            if (function_exists('send_notification')) {
                $seller_name = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
                $validation = validate_invite_token($token);
                if ($validation['valid']) {
                    $link = $validation['link'];
                    send_notification($link['creator_user_id'], $link['creator_shard_id'], 'seller_linked',
                        'New Team Member', "$seller_name has accepted your invite.");
                    send_notification($uid, $sid, 'coach_linked',
                        'New Coach Linked', ($result['coach_name'] ?? 'A coach') . ' is now your coach on ' . CHILD_APP_NAME . '.');
                }
            }
        } else {
            $_SESSION['error'] = $result['reason'] ?? 'Invalid invite.';
        }
    } else {
        $_SESSION['error'] = implode('<br>', $errs);
    }
}

if (($_POST['action'] ?? '') === 'decline_invite') {
    $_SESSION['info'] = 'Invite declined.';
}

// Dev/testing: manually set user tier
if (($_POST['action'] ?? '') === 'setUserTier') {
    $uid = $_SESSION['user_id'];
    $sid = $_SESSION['shard_id'];
    $tier = $_POST['tier'] ?? '';
    $valid_tiers = ['starter', 'builder', 'pro', 'elite'];
    if (in_array($tier, $valid_tiers)) {
        set_user_tier($uid, $sid, $tier);
        // If Pro+, also ensure coach role
        if (is_tier_at_least($tier, 'pro')) {
            ensure_coach_role($uid, $sid);
        }
        $_SESSION['success'] = 'Tier updated to ' . ucfirst($tier) . '.';
    } else {
        $_SESSION['error'] = 'Invalid tier.';
    }
    header('Location: index.php?page=dashboard');
    exit;
}

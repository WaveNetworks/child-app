<?php
/**
 * coachHelpers.php
 * Helper functions for coach relationships and invite management.
 */

function generate_invite_token($coach_user_id, $coach_shard_id, $type = 'coach_to_seller', $max_uses = null, $expires_hours = null) {
    global $app_config;

    // Check rate limit
    $stmt = child_db_query_prepared(
        "SELECT COUNT(*) FROM invite_links WHERE creator_user_id = ? AND is_active = 1",
        [$coach_user_id]
    );
    $active_count = $stmt ? (int)$stmt->fetchColumn() : 0;
    $max_per_coach = $app_config['invite_max_per_coach'] ?? 20;

    if ($active_count >= $max_per_coach) {
        return ['error' => "Maximum $max_per_coach active invites reached."];
    }

    $token = bin2hex(random_bytes(32)); // 64-char hex, 256 bits
    $expires_at = $expires_hours ? date('Y-m-d H:i:s', strtotime("+{$expires_hours} hours")) : null;

    child_db_query_prepared(
        "INSERT INTO invite_links (token, creator_user_id, creator_shard_id, type, max_uses, expires_at)
         VALUES (?, ?, ?, ?, ?, ?)",
        [$token, $coach_user_id, $coach_shard_id, $type, $max_uses, $expires_at]
    );

    return ['token' => $token, 'id' => child_db_insert_id()];
}

function validate_invite_token($token) {
    $stmt = child_db_query_prepared(
        "SELECT * FROM invite_links WHERE token = ? LIMIT 1",
        [$token]
    );
    if (!$stmt) return ['valid' => false, 'reason' => 'Database error'];
    $link = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$link) return ['valid' => false, 'reason' => 'Token not found'];
    if (!$link['is_active']) return ['valid' => false, 'reason' => 'Invite disabled'];
    if ($link['expires_at'] && strtotime($link['expires_at']) < time()) {
        return ['valid' => false, 'reason' => 'Invite expired'];
    }
    if ($link['max_uses'] && $link['use_count'] >= $link['max_uses']) {
        return ['valid' => false, 'reason' => 'Invite usage limit reached'];
    }

    return ['valid' => true, 'link' => $link];
}

function redeem_invite_token($token, $seller_user_id, $seller_shard_id) {
    $validation = validate_invite_token($token);
    if (!$validation['valid']) return $validation;

    $link = $validation['link'];
    $coach_user_id = $link['creator_user_id'];
    $coach_shard_id = $link['creator_shard_id'];

    // Check if relationship already exists
    $stmt = child_db_query_shard_prepared($seller_shard_id,
        "SELECT id FROM coach_relationships WHERE coach_user_id = ? AND seller_user_id = ? AND status = 'active'",
        [$coach_user_id, $seller_user_id]
    );
    if ($stmt && $stmt->fetch()) {
        return ['valid' => false, 'reason' => 'Already linked to this coach'];
    }

    // Create relationship on seller's shard
    child_db_query_shard_prepared($seller_shard_id,
        "INSERT INTO coach_relationships (coach_user_id, coach_shard_id, seller_user_id, seller_shard_id, invite_token, status)
         VALUES (?, ?, ?, ?, ?, 'active')
         ON DUPLICATE KEY UPDATE status = 'active', linked_at = NOW(), ended_at = NULL",
        [$coach_user_id, $coach_shard_id, $seller_user_id, $seller_shard_id, $token]
    );

    // Create relationship on coach's shard too
    child_db_query_shard_prepared($coach_shard_id,
        "INSERT INTO coach_relationships (coach_user_id, coach_shard_id, seller_user_id, seller_shard_id, invite_token, status)
         VALUES (?, ?, ?, ?, ?, 'active')
         ON DUPLICATE KEY UPDATE status = 'active', linked_at = NOW(), ended_at = NULL",
        [$coach_user_id, $coach_shard_id, $seller_user_id, $seller_shard_id, $token]
    );

    // Increment use count
    child_db_query_prepared(
        "UPDATE invite_links SET use_count = use_count + 1 WHERE token = ?",
        [$token]
    );

    // Ensure coach role
    ensure_coach_role($coach_user_id, $coach_shard_id);

    // Get coach name for display
    $coach_profile = get_user_profile($coach_user_id, $coach_shard_id);
    $coach_name = trim(($coach_profile['first_name'] ?? '') . ' ' . ($coach_profile['last_name'] ?? ''));

    return ['valid' => true, 'coach_name' => $coach_name];
}

function get_coach_invites($coach_user_id) {
    $stmt = child_db_query_prepared(
        "SELECT * FROM invite_links WHERE creator_user_id = ? ORDER BY created_at DESC",
        [$coach_user_id]
    );
    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

function get_user_coaches($user_id, $shard_id) {
    $stmt = child_db_query_shard_prepared($shard_id,
        "SELECT * FROM coach_relationships WHERE seller_user_id = ? AND status = 'active' ORDER BY linked_at DESC",
        [$user_id]
    );
    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

function get_coach_sellers($coach_user_id, $shard_id) {
    $stmt = child_db_query_shard_prepared($shard_id,
        "SELECT * FROM coach_relationships WHERE coach_user_id = ? AND status = 'active' ORDER BY linked_at DESC",
        [$coach_user_id]
    );
    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

function get_invite_link_url($token) {
    global $app_config;
    $base = rtrim($app_config['app_url'] ?? '', '/');
    if (!$base) $base = '';
    return $base . '/index.php?page=invite-landing&token=' . urlencode($token);
}

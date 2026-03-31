<?php
/**
 * assessmentHelpers.php
 * Helper functions for assessment instances and user placement.
 * Instance data lives on the filesystem (see instanceFileHelpers.php).
 * This file provides convenience wrappers and session management.
 */

// ─── Instance Access (delegates to instanceFileHelpers) ─────────────────────

function get_user_instance($user_id, $shard_id, $instance_type = 'ai-assessment') {
    // Try filesystem first
    $data = load_instance_file($user_id, $shard_id, $instance_type);
    if ($data) {
        return ['instance_data' => $data, 'instance_type' => $instance_type];
    }

    // Fallback to DB for backward compat
    $stmt = child_db_query_shard_prepared($shard_id,
        "SELECT * FROM assessment_instances WHERE user_id = ? AND instance_type = ? LIMIT 1",
        [$user_id, $instance_type]
    );
    if (!$stmt) return null;
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    $row['instance_data'] = json_decode($row['instance_json'], true);
    return $row;
}

function create_instance_shell($user_id, $shard_id, $subject_profile = [], $instance_type = 'ai-assessment') {
    $instance = create_instance_v23($user_id, $shard_id, $instance_type);

    // Apply any provided profile data
    if (!empty($subject_profile)) {
        foreach ($subject_profile as $key => $value) {
            set_profile_field($instance['subjectProfile'], $key, $value);
        }
    }

    // Save to filesystem + DB
    save_instance_file($user_id, $shard_id, $instance, $instance_type);

    return $instance;
}

function update_instance_json($user_id, $shard_id, $instance_type, $instance_data, $status = null) {
    // Save to filesystem + DB
    return save_instance_file($user_id, $shard_id, $instance_data, $instance_type);
}

// ─── Convenience Wrappers ───────────────────────────────────────────────────

function get_user_placement($user_id, $shard_id) {
    $instance = load_instance_file($user_id, $shard_id, 'ai-assessment');
    if (!$instance) return null;
    return $instance['placement'] ?? null;
}

function get_user_scores($user_id, $shard_id) {
    $instance = load_instance_file($user_id, $shard_id, 'ai-assessment');
    if (!$instance) return null;
    return $instance['scores'] ?? null;
}

function get_onboarding_profile($user_id, $shard_id) {
    $instance = load_instance_file($user_id, $shard_id, 'ai-assessment');
    if (!$instance) return null;
    return $instance['subjectProfile'] ?? null;
}

// ─── Roles ──────────────────────────────────────────────────────────────────

function get_user_role($user_id) {
    $stmt = child_db_query_prepared(
        "SELECT role FROM app_roles WHERE user_id = ? ORDER BY FIELD(role, 'org_lead', 'coach', 'seller')",
        [$user_id]
    );
    if (!$stmt) return ['seller'];
    $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
    return $roles ?: ['seller'];
}

function is_coach($user_id) {
    $roles = get_user_role($user_id);
    return in_array('coach', $roles) || in_array('org_lead', $roles);
}

function is_org_lead($user_id) {
    $roles = get_user_role($user_id);
    return in_array('org_lead', $roles);
}

function ensure_seller_role($user_id, $shard_id) {
    child_db_query_prepared(
        "INSERT IGNORE INTO app_roles (user_id, shard_id, role, granted_by) VALUES (?, ?, 'seller', ?)",
        [$user_id, $shard_id, $user_id]
    );
}

function ensure_coach_role($user_id, $shard_id) {
    child_db_query_prepared(
        "INSERT IGNORE INTO app_roles (user_id, shard_id, role, granted_by) VALUES (?, ?, 'coach', ?)",
        [$user_id, $shard_id, $user_id]
    );
}

// ─── Tier Helpers ───────────────────────────────────────────────────────────

/**
 * Map a Training Continuum stage to a tier label.
 * Stages 00-03 = starter, 04-06 = builder, 07-08 = pro, 09-10 = elite.
 */
function get_tier_for_stage($stage) {
    $s = (int)$stage;
    if ($s >= 9)  return 'elite';
    if ($s >= 7)  return 'pro';
    if ($s >= 4)  return 'builder';
    return 'starter';
}

/**
 * Get the user's assessed tier from their placement data or prefs.
 * Returns 'starter', 'builder', 'pro', 'elite', or null if not assessed.
 */
function get_user_tier($user_id, $shard_id) {
    // First try placement from instance file
    $placement = get_user_placement($user_id, $shard_id);
    if ($placement && !empty($placement['current_stage'])) {
        return get_tier_for_stage($placement['current_stage']);
    }
    // Fallback to manual pref (for dev/testing or manual override)
    $stmt = child_db_query_shard_prepared($shard_id,
        "SELECT pref_value FROM childapp_user_prefs WHERE user_id = ? AND pref_key = 'assessed_tier'",
        [$user_id]
    );
    if ($stmt) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) return $row['pref_value'];
    }
    return null;
}

/**
 * Set user tier manually (dev/testing or admin override).
 */
function set_user_tier($user_id, $shard_id, $tier) {
    child_db_query_shard_prepared($shard_id,
        "INSERT INTO childapp_user_prefs (user_id, pref_key, pref_value, updated)
         VALUES (?, 'assessed_tier', ?, NOW())
         ON DUPLICATE KEY UPDATE pref_value = VALUES(pref_value), updated = NOW()",
        [$user_id, $tier]
    );
}

/**
 * Check if a tier meets a minimum level.
 */
function is_tier_at_least($user_tier, $minimum) {
    $order = ['starter' => 0, 'builder' => 1, 'pro' => 2, 'elite' => 3];
    return ($order[$user_tier] ?? -1) >= ($order[$minimum] ?? 99);
}

/**
 * Check if user qualifies to be a coach (Pro or above).
 */
function can_be_coach($user_id, $shard_id) {
    $tier = get_user_tier($user_id, $shard_id);
    return $tier && is_tier_at_least($tier, 'pro');
}

/**
 * Get tier display info (label, Bootstrap color, icon).
 */
function get_tier_display($tier) {
    $tiers = [
        'starter' => ['label' => 'Starter', 'color' => 'secondary', 'icon' => 'bi-seedling'],
        'builder' => ['label' => 'Builder', 'color' => 'info',      'icon' => 'bi-bricks'],
        'pro'     => ['label' => 'Pro',     'color' => 'warning',   'icon' => 'bi-star-fill'],
        'elite'   => ['label' => 'Elite',   'color' => 'danger',    'icon' => 'bi-trophy-fill'],
    ];
    return $tiers[$tier] ?? $tiers['starter'];
}

// ─── Session Management (stays in DB — sessions are transient) ──────────────

function start_assessment_session($user_id, $shard_id, $instance_id, $initial_phase = 'profile') {
    $session_id = 'sess-' . bin2hex(random_bytes(8));
    child_db_query_shard_prepared($shard_id,
        "INSERT INTO assessment_sessions (session_id, user_id, instance_id, phase) VALUES (?, ?, ?, ?)",
        [$session_id, $user_id, $instance_id, $initial_phase]
    );
    return $session_id;
}

function get_active_session($user_id, $shard_id) {
    $stmt = child_db_query_shard_prepared($shard_id,
        "SELECT * FROM assessment_sessions WHERE user_id = ? AND completed_at IS NULL ORDER BY started_at DESC LIMIT 1",
        [$user_id]
    );
    return $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
}

function get_session_by_id($session_id, $shard_id) {
    $stmt = child_db_query_shard_prepared($shard_id,
        "SELECT * FROM assessment_sessions WHERE session_id = ? LIMIT 1",
        [$session_id]
    );
    return $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
}

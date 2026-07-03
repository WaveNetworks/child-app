<?php
/**
 * historyFunctions.php
 * User event logging on the child app's shards.
 * Demonstrates writing per-user data to shards — the core use case for sharding.
 */

/**
 * Log a user activity event to the child shard's user_event_log table.
 *
 * @param int    $user_id    The user ID
 * @param string $shard_id   The child shard to write to (from session or lookup)
 * @param string $event_type e.g. 'login', 'item_created', 'page_view'
 * @param array  $event_data Optional key-value data stored as JSON
 */
function log_user_event($user_id, $shard_id, $event_type, $event_data = []) {
    $json = !empty($event_data) ? json_encode($event_data) : '{}';
    $ip   = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua   = $_SERVER['HTTP_USER_AGENT'] ?? '';

    child_db_query_shard_prepared($shard_id,
        "INSERT INTO user_event_log (user_id, event_type, event_data, ip_address, user_agent, created)
         VALUES (?, ?, ?, ?, ?, NOW())",
        [$user_id, $event_type, $json, $ip, $ua]
    );
}

/**
 * Get recent user event entries from the child shard.
 *
 * @param int    $user_id
 * @param string $shard_id
 * @param int    $limit
 * @return array
 */
function get_user_events($user_id, $shard_id, $limit = 20) {
    $limit = (int) $limit;
    $stmt = child_db_query_shard_prepared($shard_id,
        "SELECT * FROM user_event_log WHERE user_id = ? ORDER BY created DESC LIMIT $limit",
        [$user_id]
    );
    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

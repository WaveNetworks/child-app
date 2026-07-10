<?php
/**
 * cacheHelpers.php
 * Simple key-value cache backed by the app_cache table.
 */

function cache_get($cache_key) {
    $stmt = child_db_query_prepared(
        "SELECT data_json FROM app_cache WHERE cache_key = ? AND expires_at > NOW() LIMIT 1",
        [$cache_key]
    );
    if (!$stmt) return null;
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? json_decode($row['data_json'], true) : null;
}

function cache_set($cache_key, $data, $ttl_seconds = 3600) {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    $expires = date('Y-m-d H:i:s', time() + $ttl_seconds);
    child_db_query_prepared(
        "INSERT INTO app_cache (cache_key, data_json, expires_at) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE data_json = VALUES(data_json), expires_at = VALUES(expires_at), created_at = NOW()",
        [$cache_key, $json, $expires]
    );
}

function cache_delete($cache_key) {
    child_db_query_prepared("DELETE FROM app_cache WHERE cache_key = ?", [$cache_key]);
}

function cache_cleanup() {
    child_db_query_prepared("DELETE FROM app_cache WHERE expires_at < NOW()", []);
}

<?php
/**
 * childDBFunctions.php
 * PDO wrapper functions for the child app's own databases.
 * Mirrors admin's mysqlFunctions.php and shardDBFunctions.php patterns.
 */

// Global array to hold open child shard PDO connections
$child_shard_connections = [];

// ─── CHILD MAIN DB ──────────────────────────────────────────────────────────

/**
 * Execute a query on the child app's main database.
 *
 * @param string $sql
 * @return PDOStatement|false
 */
function child_db_query($sql) {
    global $child_db;
    try {
        return $child_db->query($sql);
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Database error: ' . $e->getMessage();
        return false;
    }
}

/**
 * Execute a prepared statement on the child app's main database.
 *
 * @param string $sql
 * @param array  $params
 * @return PDOStatement|false
 */
function child_db_query_prepared($sql, $params = []) {
    global $child_db;
    try {
        $stmt = $child_db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Database error: ' . $e->getMessage();
        return false;
    }
}

/**
 * Get the last insert ID from the child app's main database.
 *
 * @return string
 */
function child_db_insert_id() {
    global $child_db;
    return $child_db->lastInsertId();
}

// ─── CHILD SHARD DB ─────────────────────────────────────────────────────────

/**
 * Open (or reuse) a PDO connection to a child app shard.
 *
 * @param string $shard_id  e.g. 'shard1', 'shard2'
 * @return PDO|false
 */
function child_prime_shard($shard_id) {
    global $childShardConfigs, $child_shard_connections;

    if (isset($child_shard_connections[$shard_id])) {
        return $child_shard_connections[$shard_id];
    }

    if (!isset($childShardConfigs[$shard_id])) {
        error_log("child_prime_shard: unknown shard '$shard_id'");
        return false;
    }

    $cfg = $childShardConfigs[$shard_id];
    try {
        $conn = new PDO(
            "mysql:host={$cfg['host']};dbname={$cfg['name']};charset=utf8mb4",
            $cfg['user'],
            $cfg['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $child_shard_connections[$shard_id] = $conn;
        return $conn;
    } catch (PDOException $e) {
        error_log("child_prime_shard: connection failed for '$shard_id': " . $e->getMessage());
        return false;
    }
}

/**
 * Execute a query on a child app shard.
 *
 * @param string $shard_id
 * @param string $sql
 * @return PDOStatement|false
 */
function child_db_query_shard($shard_id, $sql) {
    global $child_shard_connections;

    if (!isset($child_shard_connections[$shard_id])) {
        child_prime_shard($shard_id);
    }

    if (!isset($child_shard_connections[$shard_id])) {
        $_SESSION['error'] = "Child shard '$shard_id' is not available.";
        return false;
    }

    try {
        return $child_shard_connections[$shard_id]->query($sql);
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Child shard query error: ' . $e->getMessage();
        return false;
    }
}

/**
 * Execute a prepared statement on a child app shard.
 *
 * @param string $shard_id
 * @param string $sql
 * @param array  $params
 * @return PDOStatement|false
 */
function child_db_query_shard_prepared($shard_id, $sql, $params = []) {
    global $child_shard_connections;

    if (!isset($child_shard_connections[$shard_id])) {
        child_prime_shard($shard_id);
    }

    if (!isset($child_shard_connections[$shard_id])) {
        $_SESSION['error'] = "Child shard '$shard_id' is not available.";
        return false;
    }

    try {
        $stmt = $child_shard_connections[$shard_id]->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Child shard query error: ' . $e->getMessage();
        return false;
    }
}

/**
 * Get the last insert ID from a child shard connection.
 *
 * @param string $shard_id
 * @return string
 */
function child_shard_insert_id($shard_id) {
    global $child_shard_connections;
    if (!isset($child_shard_connections[$shard_id])) return '0';
    return $child_shard_connections[$shard_id]->lastInsertId();
}

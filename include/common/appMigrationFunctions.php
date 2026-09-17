<?php
/**
 * appMigrationFunctions.php
 * Migration runner for the child app's own databases.
 * Reuses admin's get_available_migrations(), run_migration(), get_current_db_version().
 */

/**
 * Check and run pending migrations on a child app database.
 *
 * @param PDO    $conn            The database connection to migrate
 * @param string $db_type         'main' or 'shard'
 * @param float  $target_version  The version to migrate to (e.g. 1.0)
 * @param string $base_dir        Path to child app's db_migrations/ directory
 */
function child_check_and_migrate($conn, $db_type, $target_version, $base_dir) {
    // Core's one loop (migrationFunctions.php): ordered, STOPS at the first failed
    // file, records it for apiSchemaAudit, flag-gated. The code below is only the
    // fallback for a core older than that API, and it stops on failure too.
    if (function_exists('wn_child_migrate')) {
        wn_child_migrate($conn, $db_type, $target_version, $base_dir);
        return;
    }

    $current = get_current_db_version($conn);
    if ($current >= $target_version) return;

    $available = get_available_migrations($db_type, $base_dir);
    foreach ($available as $ver) {
        if ($ver <= $current) continue;
        if ($ver > $target_version) break;

        $file = rtrim($base_dir, '/') . '/' . $db_type . '/' . number_format($ver, 1, '.', '') . '.sql';
        if (!file_exists($file)) continue;

        if (!run_migration($conn, $file, "child-$db_type", $ver)) break;  // never skip past a failure
    }
}

/**
 * Check and run pending migrations on all child app shard databases.
 *
 * @param float  $target_version  The shard version to migrate to
 * @param string $base_dir        Path to child app's db_migrations/ directory
 */
function child_check_and_migrate_shards($target_version, $base_dir, $budget = null) {
    global $childShardConfigs;

    if (function_exists('wn_child_migrate_shards')) {
        // Per-shard flags + connection budget; each shard stops at its first failed file.
        wn_child_migrate_shards($childShardConfigs ?? [], 'child_prime_shard', $target_version, $base_dir, $budget);
        return;
    }

    if (empty($childShardConfigs)) return;

    $available = get_available_migrations('shard', $base_dir);
    if (empty($available)) return;

    foreach ($childShardConfigs as $shard_id => $cfg) {
        $conn = child_prime_shard($shard_id);
        if (!$conn) continue;

        $current = get_current_db_version($conn);
        if ($current >= $target_version) continue;

        foreach ($available as $ver) {
            if ($ver <= $current) continue;
            if ($ver > $target_version) break;

            $file = rtrim($base_dir, '/') . '/shard/' . number_format($ver, 1, '.', '') . '.sql';
            if (!file_exists($file)) continue;

            if (!run_migration($conn, $file, "child-shard/$shard_id", $ver)) break;  // never skip past a failure
        }
    }
}

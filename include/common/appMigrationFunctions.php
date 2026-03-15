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
    $current = get_current_db_version($conn);
    if ($current >= $target_version) return;

    $available = get_available_migrations($db_type, $base_dir);
    foreach ($available as $ver) {
        if ($ver <= $current) continue;
        if ($ver > $target_version) break;

        $file = rtrim($base_dir, '/') . '/' . $db_type . '/' . number_format($ver, 1, '.', '') . '.sql';
        if (!file_exists($file)) continue;

        run_migration($conn, $file, "child-$db_type", $ver);
    }
}

/**
 * Check and run pending migrations on all child app shard databases.
 *
 * @param float  $target_version  The shard version to migrate to
 * @param string $base_dir        Path to child app's db_migrations/ directory
 */
function child_check_and_migrate_shards($target_version, $base_dir) {
    global $childShardConfigs;

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

            run_migration($conn, $file, "child-shard/$shard_id", $ver);
        }
    }
}

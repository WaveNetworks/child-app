<?php
/**
 * migration_versions.php — the ONE declaration of this app's migration targets.
 *
 * Every bootstrap (common.php, common_api.php, common_auth.php) includes this file, and core's
 * apiSchemaAudit reads it. They used to be re-declared per bootstrap and drifted
 * (API or auth requests migrated to a different version than web requests).
 *
 * Adding db_migrations/main/X.Y.sql  -> set $child_db_version    = X.Y;
 * Adding db_migrations/shard/X.Y.sql -> set $child_shard_version = X.Y;
 * Single-decimal floats: (float)"1.10" == 1.1 would collide with 1.1.sql, so after 1.9 comes 2.0.
 * The loop (core wn_child_migrate) stops at the first failed file; see appMigrationFunctions.php.
 */
$child_db_version    = 1.1;
$child_shard_version = 1.2;

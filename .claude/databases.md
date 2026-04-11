# Databases and Migrations

## Three database layers
1. **Admin main DB** (`$db`) — READ ONLY from child
2. **Child main DB** (`$child_db`) — `child_db_query()`, `child_db_query_prepared()`
3. **Child shard DBs** — `child_prime_shard()`, `child_db_query_shard()`, `child_db_query_shard_prepared()`

Use `db_query_shard()` ONLY for reading admin shard data (user_profile).

## Migration rules
Dir: `db_migrations/main/` and `db_migrations/shard/` — decimal versioning (1.0, 1.1, 2.0).

EVERY migration requires BOTH steps:
1. Create the SQL file in `db_migrations/main/` or `db_migrations/shard/`
2. Update `$child_db_version` or `$child_shard_version` in `include/common.php`

-- Create all 6 namespaced databases for the child app on a single MySQL server.
-- Names use the default CHILD_APP_SLUG (`child_app`); if you set a custom slug
-- in docker-compose, rename these to match `<slug>_*`.

-- Admin core databases
CREATE DATABASE IF NOT EXISTS `child_app_admin_main`;
CREATE DATABASE IF NOT EXISTS `child_app_admin_shard_1`;
CREATE DATABASE IF NOT EXISTS `child_app_admin_shard_2`;

-- Child app databases
CREATE DATABASE IF NOT EXISTS `child_app_main`;
CREATE DATABASE IF NOT EXISTS `child_app_shard_1`;
CREATE DATABASE IF NOT EXISTS `child_app_shard_2`;

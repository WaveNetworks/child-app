-- Create all 6 namespaced databases on a single MySQL server.
-- MULTI-APP: Replace 'child_app' with your app name (e.g. payments).

-- Admin core databases
CREATE DATABASE IF NOT EXISTS `child_app_admin_main`;
CREATE DATABASE IF NOT EXISTS `child_app_admin_shard_1`;
CREATE DATABASE IF NOT EXISTS `child_app_admin_shard_2`;

-- Child app databases
CREATE DATABASE IF NOT EXISTS `child_app_main`;
CREATE DATABASE IF NOT EXISTS `child_app_shard_1`;
CREATE DATABASE IF NOT EXISTS `child_app_shard_2`;

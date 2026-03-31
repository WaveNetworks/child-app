-- Create all 6 namespaced databases for ElevateHER on a single MySQL server.

-- Admin core databases
CREATE DATABASE IF NOT EXISTS `elevateher_admin_main`;
CREATE DATABASE IF NOT EXISTS `elevateher_admin_shard_1`;
CREATE DATABASE IF NOT EXISTS `elevateher_admin_shard_2`;

-- ElevateHER child app databases
CREATE DATABASE IF NOT EXISTS `elevateher_main`;
CREATE DATABASE IF NOT EXISTS `elevateher_shard_1`;
CREATE DATABASE IF NOT EXISTS `elevateher_shard_2`;

-- Migration 1.0 for Child App Shard Database
-- Initial schema: db_version tracking, user_history, childapp_user_prefs
-- ⚠️ REMINDER: Update child-app/include/common.php $child_shard_version = 1.0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Migration version tracking (matches admin's schema: version_id int PK)
CREATE TABLE IF NOT EXISTS `db_version` (
    `version_id` INT NOT NULL AUTO_INCREMENT,
    `version` DECIMAL(10,1) NOT NULL DEFAULT 0.0,
    PRIMARY KEY (`version_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `db_version` (`version_id`, `version`) VALUES (1, 1.0)
ON DUPLICATE KEY UPDATE `version` = 1.0;

-- Per-user activity/event log
-- This is the primary example of WHY sharding exists: user history grows fast
-- and is per-user, so spreading it across shards keeps each DB manageable.
CREATE TABLE IF NOT EXISTS `user_history` (
    `history_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `event_type` VARCHAR(50) NOT NULL,
    `event_data` JSON NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` VARCHAR(512) NULL,
    `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`history_id`),
    INDEX `idx_user_created` (`user_id`, `created`),
    INDEX `idx_event_type` (`event_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-user preferences for child app (key-value pairs)
CREATE TABLE IF NOT EXISTS `childapp_user_prefs` (
    `user_id` INT UNSIGNED NOT NULL,
    `pref_key` VARCHAR(100) NOT NULL,
    `pref_value` TEXT NULL,
    `updated` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`, `pref_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;

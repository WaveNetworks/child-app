-- Migration 1.0 for Child App Main Database
-- Initial schema: db_version tracking, app_settings, items table
-- ⚠️ REMINDER: Update child-app/include/common.php $child_db_version = 1.0;
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

-- App-wide key-value settings
CREATE TABLE IF NOT EXISTS `app_settings` (
    `setting_key` VARCHAR(100) NOT NULL,
    `setting_value` TEXT NULL,
    `updated` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Items table (example feature — user-owned resources)
CREATE TABLE IF NOT EXISTS `items` (
    `item_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `status` ENUM('draft','active','archived') NOT NULL DEFAULT 'draft',
    `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`item_id`),
    INDEX `idx_user_status` (`user_id`, `status`),
    INDEX `idx_updated` (`updated`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;

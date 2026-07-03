-- Child app — Main DB v1.0
-- Foundation tables: invite links, app roles
-- (Example scaffold tables — replace with your app's own.)
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE TABLE IF NOT EXISTS `db_version` (
    `version_id` INT NOT NULL AUTO_INCREMENT,
    `version` DECIMAL(10,1) NOT NULL DEFAULT 0.0,
    PRIMARY KEY (`version_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `db_version` (`version`) VALUES (1.0);

CREATE TABLE IF NOT EXISTS `app_settings` (
    `setting_key` VARCHAR(100) NOT NULL,
    `setting_value` TEXT NULL,
    `updated` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Invite links
CREATE TABLE IF NOT EXISTS `invite_links` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `token` VARCHAR(64) NOT NULL,
    `creator_user_id` INT UNSIGNED NOT NULL,
    `creator_shard_id` VARCHAR(20) NOT NULL,
    `type` ENUM('coach_to_seller','org_to_coach') NOT NULL DEFAULT 'coach_to_seller',
    `max_uses` INT UNSIGNED NULL,
    `use_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `expires_at` DATETIME NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_token` (`token`),
    INDEX `idx_creator` (`creator_user_id`),
    INDEX `idx_active_expires` (`is_active`, `expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- App role assignments
CREATE TABLE IF NOT EXISTS `app_roles` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `shard_id` VARCHAR(20) NOT NULL,
    `role` ENUM('seller','coach','org_lead') NOT NULL DEFAULT 'seller',
    `granted_by` INT UNSIGNED NULL,
    `granted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_role` (`user_id`, `role`),
    INDEX `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

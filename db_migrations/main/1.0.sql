-- Child app — Main DB v1.0
-- Foundation tables: framework data, invite links, app roles
-- (Inherited from the ElevateHER assessment engine.)
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

-- Framework version tracking
CREATE TABLE IF NOT EXISTS `framework_versions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `version_label` VARCHAR(50) NOT NULL,
    `schema_version` VARCHAR(20) NOT NULL DEFAULT '5.0',
    `source` ENUM('local','remote') NOT NULL DEFAULT 'local',
    `checksum` VARCHAR(64) NOT NULL,
    `file_checksums` JSON NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `loaded_by` INT UNSIGNED NULL,
    `loaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `notes` TEXT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_active` (`is_active`),
    INDEX `idx_version` (`version_label`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Framework data storage (chunked JSON)
CREATE TABLE IF NOT EXISTS `framework_data` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `version_id` INT UNSIGNED NOT NULL,
    `file_type` VARCHAR(50) NOT NULL,
    `file_key` VARCHAR(100) NOT NULL,
    `data_json` LONGTEXT NOT NULL,
    `checksum` VARCHAR(64) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_version_type` (`version_id`, `file_type`),
    INDEX `idx_version_key` (`version_id`, `file_key`),
    CONSTRAINT `fk_fd_version` FOREIGN KEY (`version_id`) REFERENCES `framework_versions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Coach invite links
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

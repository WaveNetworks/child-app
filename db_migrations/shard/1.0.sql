-- Child app — Shard DB v1.0
-- Per-user tables: user history and per-user scaffold data
-- (Example scaffold tables — replace with your app's own.)
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE TABLE IF NOT EXISTS `db_version` (
    `version_id` INT NOT NULL AUTO_INCREMENT,
    `version` DECIMAL(10,1) NOT NULL DEFAULT 0.0,
    PRIMARY KEY (`version_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `db_version` (`version`) VALUES (1.0);

-- User activity history
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Assessment instances
CREATE TABLE IF NOT EXISTS `assessment_instances` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `instance_type` ENUM('ai-assessment','coach-assessment','self-assessment') NOT NULL DEFAULT 'ai-assessment',
    `framework_version_id` INT UNSIGNED NULL,
    `instance_json` LONGTEXT NOT NULL,
    `status` ENUM('not_started','in_progress','completed') NOT NULL DEFAULT 'not_started',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_type` (`user_id`, `instance_type`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Assessment sessions (audit trail)
CREATE TABLE IF NOT EXISTS `assessment_sessions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id` VARCHAR(30) NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `instance_id` INT UNSIGNED NOT NULL,
    `phase` ENUM('profile','floor','ceiling','zone','score') NOT NULL DEFAULT 'profile',
    `conversation_log` LONGTEXT NULL,
    `started_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `completed_at` DATETIME NULL,
    `completed_through` VARCHAR(10) NULL,
    `hard_wall_hit` VARCHAR(100) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_session` (`session_id`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_instance` (`instance_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Coach-seller relationships
CREATE TABLE IF NOT EXISTS `coach_relationships` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `coach_user_id` INT UNSIGNED NOT NULL,
    `coach_shard_id` VARCHAR(20) NOT NULL,
    `seller_user_id` INT UNSIGNED NOT NULL,
    `seller_shard_id` VARCHAR(20) NOT NULL,
    `invite_token` VARCHAR(64) NULL,
    `status` ENUM('active','paused','ended') NOT NULL DEFAULT 'active',
    `linked_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `ended_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_coach_seller` (`coach_user_id`, `seller_user_id`),
    INDEX `idx_coach` (`coach_user_id`, `status`),
    INDEX `idx_seller` (`seller_user_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Content progress tracking
CREATE TABLE IF NOT EXISTS `content_progress` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `catalog_item_id` VARCHAR(100) NOT NULL,
    `status` ENUM('recommended','started','completed','skipped') NOT NULL DEFAULT 'recommended',
    `recommended_by` ENUM('assessment','coach','self') NOT NULL DEFAULT 'assessment',
    `recommended_at` DATETIME NULL,
    `started_at` DATETIME NULL,
    `completed_at` DATETIME NULL,
    `notes` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_item` (`user_id`, `catalog_item_id`),
    INDEX `idx_user_status` (`user_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- AI-generated session notes
CREATE TABLE IF NOT EXISTS `session_notes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `session_id` VARCHAR(30) NOT NULL,
    `stage` VARCHAR(10) NULL,
    `dimension` VARCHAR(20) NULL,
    `observation` TEXT NOT NULL,
    `focus` VARCHAR(100) NULL,
    `canvas_label` VARCHAR(50) NULL,
    `surface_in_canvas` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_session` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

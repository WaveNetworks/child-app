-- Child app — Shard DB v1.1
-- Longitudinal progress tracking
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE TABLE IF NOT EXISTS `progress_snapshots` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `snapshot_type` ENUM('assessment','milestone','periodic') NOT NULL,
    `stage` VARCHAR(5) NOT NULL,
    `overall_score` DECIMAL(5,4) NULL,
    `mindset_score` DECIMAL(5,4) NULL,
    `toolset_score` DECIMAL(5,4) NULL,
    `skillset_score` DECIMAL(5,4) NULL,
    `items_resolved` INT UNSIGNED NOT NULL DEFAULT 0,
    `items_total` INT UNSIGNED NOT NULL DEFAULT 0,
    `hard_wall_id` VARCHAR(50) NULL,
    `content_started` INT UNSIGNED NOT NULL DEFAULT 0,
    `content_completed` INT UNSIGNED NOT NULL DEFAULT 0,
    `session_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `snapshot_data` JSON NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_user_time` (`user_id`, `created_at`),
    INDEX `idx_user_type` (`user_id`, `snapshot_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

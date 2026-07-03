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

-- ElevateHER Learning Suite — Main DB v1.1
-- Cache and cohort snapshot tables

CREATE TABLE IF NOT EXISTS `app_cache` (
    `cache_key` VARCHAR(100) NOT NULL,
    `data_json` LONGTEXT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME NOT NULL,
    PRIMARY KEY (`cache_key`),
    INDEX `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cohort_snapshots` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `snapshot_date` DATE NOT NULL,
    `data_json` JSON NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_date` (`snapshot_date`),
    INDEX `idx_date` (`snapshot_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

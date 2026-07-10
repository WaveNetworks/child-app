-- Child app — Main DB v1.1
-- Cache table

CREATE TABLE IF NOT EXISTS `app_cache` (
    `cache_key` VARCHAR(100) NOT NULL,
    `data_json` LONGTEXT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME NOT NULL,
    PRIMARY KEY (`cache_key`),
    INDEX `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

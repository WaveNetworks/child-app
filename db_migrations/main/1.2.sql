-- ElevateHER Learning Suite — Main DB v1.2
-- Framework proposals and feedback system
-- ⚠️ REMINDER: Update elevateher/include/common.php $child_db_version = 1.2;

CREATE TABLE IF NOT EXISTS `framework_proposals` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `target_type` ENUM('stage','item','kpi','content','wall','general') NOT NULL,
    `target_id` VARCHAR(100) NOT NULL DEFAULT '',
    `change_type` ENUM('comment','edit','add','remove','reorder') NOT NULL DEFAULT 'comment',
    `field_path` VARCHAR(200) NULL,
    `old_value` TEXT NULL,
    `new_value` TEXT NULL,
    `rationale` TEXT NOT NULL,
    `status` ENUM('draft','submitted','approved','applied','rejected') NOT NULL DEFAULT 'submitted',
    `reviewed_by` INT UNSIGNED NULL,
    `reviewed_at` DATETIME NULL,
    `applied_version_id` INT UNSIGNED NULL,
    `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_target` (`target_type`, `target_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

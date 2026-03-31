-- Content guide response cache
-- Stores AI responses for common first-chat choices so they can be reused
-- across users without redundant AI calls. Keyed by content_id + choice_key + content_version.
CREATE TABLE IF NOT EXISTS `content_guide_cache` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `content_id` VARCHAR(100) NOT NULL,
  `choice_key` VARCHAR(80) NOT NULL,
  `content_version` INT UNSIGNED NOT NULL DEFAULT 1,
  `response` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_content_choice_version` (`content_id`, `choice_key`, `content_version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

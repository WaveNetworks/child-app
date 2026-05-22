-- Migration 1.7 for Shard Database
-- Add kpi_update and recompute to progress_snapshots snapshot_type ENUM
-- ⚠️ REMINDER: Update include/common.php $child_shard_version = 1.7;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

ALTER TABLE `progress_snapshots`
    MODIFY COLUMN `snapshot_type` ENUM('assessment','milestone','periodic','kpi_update','recompute') NOT NULL;

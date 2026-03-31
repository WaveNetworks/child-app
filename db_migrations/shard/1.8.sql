-- Add belief_update to progress_snapshots snapshot_type ENUM
-- Needed for inline belief self-assessment from dashboard

ALTER TABLE `progress_snapshots`
    MODIFY COLUMN `snapshot_type` ENUM('assessment','milestone','periodic','kpi_update','recompute','belief_update') NOT NULL;

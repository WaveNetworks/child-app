-- Widen canvas_label to accommodate AI-generated labels
ALTER TABLE `session_notes` MODIFY `canvas_label` VARCHAR(255) NULL;

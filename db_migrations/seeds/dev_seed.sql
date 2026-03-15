-- Dev seed data for child app
-- Run against childapp_main after migrations
-- Assumes user_id 1 exists in admin's main DB

-- Sample items for user 1
INSERT INTO `items` (`user_id`, `title`, `description`, `status`, `created`, `updated`) VALUES
(1, 'Getting Started Guide', 'A walkthrough of the child-app features and architecture.', 'active', NOW(), NOW()),
(1, 'API Integration Notes', 'Documentation for connecting to external services.', 'active', NOW(), NOW()),
(1, 'Design Mockups', 'Initial wireframes and UI concepts for the dashboard.', 'draft', NOW(), NOW()),
(1, 'Q1 Planning Doc', 'Quarterly planning document with milestones.', 'archived', NOW(), NOW()),
(1, 'Bug Tracker Setup', 'Configuration for issue tracking integration.', 'draft', NOW(), NOW());

-- Sample app settings
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES
('app_name', 'Child App'),
('items_require_approval', '0'),
('max_items_per_user', '100')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

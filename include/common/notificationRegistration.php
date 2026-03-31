<?php
/**
 * notificationRegistration.php
 * Register ElevateHER-specific notification categories with Wave Networks.
 * Runs on every request (idempotent via ON DUPLICATE KEY UPDATE).
 */
if (function_exists('register_notification_category')) {
    register_notification_category('coach_linked', 'Coach Linked',
        'Notifications when a new coach is linked to your account.');
    register_notification_category('coach_note', 'Coach Note',
        'Notifications when a coach adds a shared note for you.');
    register_notification_category('seller_linked', 'Seller Linked',
        'Notifications when a new seller accepts your invite.');
    register_notification_category('seller_inactive', 'Inactive Seller',
        'Notifications when a team member has been inactive for 14+ days.');
    register_notification_category('assessment_complete', 'Assessment Complete',
        'Notifications when a team member completes their assessment.');
    register_notification_category('framework_updated', 'Framework Updated',
        'Notifications when the training framework data is updated.');
}

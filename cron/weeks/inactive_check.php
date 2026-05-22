<?php
/**
 * cron/weeks/inactive_check.php
 * Identify sellers inactive for > 14 days and notify their coaches.
 */
require_once __DIR__ . '/../../include/common.php';

global $childShardConfigs;

foreach ($childShardConfigs as $shard_id => $cfg) {
    // Find inactive sellers (no assessment update in 14+ days)
    $stmt = child_db_query_shard_prepared($shard_id,
        "SELECT ai.user_id, ai.updated_at FROM assessment_instances ai
         WHERE ai.instance_type = 'ai-assessment'
         AND ai.status = 'in_progress'
         AND ai.updated_at < DATE_SUB(NOW(), INTERVAL 14 DAY)", []
    );
    if (!$stmt) continue;

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $seller_id = $row['user_id'];

        // Find their coaches
        $coaches = child_db_query_shard_prepared($shard_id,
            "SELECT coach_user_id, coach_shard_id FROM coach_relationships
             WHERE seller_user_id = ? AND status = 'active'",
            [$seller_id]
        );
        if (!$coaches) continue;

        $seller_profile = get_user_profile($seller_id, $shard_id);
        $seller_name = trim(($seller_profile['first_name'] ?? '') . ' ' . ($seller_profile['last_name'] ?? ''));

        while ($coach = $coaches->fetch(PDO::FETCH_ASSOC)) {
            if (function_exists('send_notification')) {
                send_notification(
                    $coach['coach_user_id'],
                    $coach['coach_shard_id'],
                    'seller_inactive',
                    'Inactive Team Member',
                    "$seller_name hasn't been active on " . CHILD_APP_NAME . " for over 2 weeks."
                );
            }
        }
    }
}

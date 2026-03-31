<?php
/**
 * relationshipActions.php
 * Coach-seller relationship management.
 */

if (($_POST['action'] ?? '') === 'end_relationship') {
    $errs = [];
    $uid = $_SESSION['user_id'];
    $sid = $_SESSION['shard_id'];
    $relationship_coach_id = (int)($_POST['coach_user_id'] ?? 0);
    $relationship_coach_shard = trim($_POST['coach_shard_id'] ?? '');

    if (!$uid) { $errs['auth'] = 'Login required.'; }
    if (!$relationship_coach_id) { $errs['coach'] = 'Coach ID required.'; }

    if (count($errs) <= 0) {
        // End on seller's shard
        child_db_query_shard_prepared($sid,
            "UPDATE coach_relationships SET status = 'ended', ended_at = NOW() WHERE seller_user_id = ? AND coach_user_id = ? AND status = 'active'",
            [$uid, $relationship_coach_id]
        );

        // End on coach's shard
        if ($relationship_coach_shard) {
            child_db_query_shard_prepared($relationship_coach_shard,
                "UPDATE coach_relationships SET status = 'ended', ended_at = NOW() WHERE seller_user_id = ? AND coach_user_id = ? AND status = 'active'",
                [$uid, $relationship_coach_id]
            );
        }

        $_SESSION['success'] = 'Coaching relationship ended.';
        log_user_history($uid, $sid, 'relationship_ended', ['coach_user_id' => $relationship_coach_id]);
    } else {
        $_SESSION['error'] = implode('<br>', $errs);
    }
}

<?php
/**
 * coachNoteActions.php
 * Coach note CRUD actions.
 */

if (($_POST['action'] ?? '') === 'add_coach_note') {
    $errs = [];
    $uid = $_SESSION['user_id'];
    $sid = $_SESSION['shard_id'];
    $seller_user_id = (int)($_POST['seller_user_id'] ?? 0);
    $seller_shard_id = trim($_POST['seller_shard_id'] ?? '');
    $note_text = trim($_POST['note_text'] ?? '');
    $stage = trim($_POST['stage'] ?? '');
    $dimension = trim($_POST['dimension'] ?? '');
    $visibility = trim($_POST['visibility'] ?? 'shared');

    if (!$uid) { $errs['auth'] = 'Login required.'; }
    if (!$seller_user_id) { $errs['seller'] = 'Seller required.'; }
    if (!$note_text) { $errs['note'] = 'Note text required.'; }

    if (count($errs) <= 0) {
        // Verify active relationship
        $stmt = child_db_query_shard_prepared($sid,
            "SELECT id FROM coach_relationships WHERE coach_user_id = ? AND seller_user_id = ? AND status = 'active'",
            [$uid, $seller_user_id]
        );
        if (!$stmt || !$stmt->fetch()) {
            $_SESSION['error'] = 'No active coaching relationship with this seller.';
        } else {
            $instance = get_user_instance($seller_user_id, $seller_shard_id, 'ai-assessment');
            if ($instance && $instance['instance_data']) {
                $inst_data = $instance['instance_data'];
                $profile = get_user_profile($uid, $sid);
                $coach_name = trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? ''));

                $note = [
                    'noteId' => 'cn-' . bin2hex(random_bytes(4)),
                    'coachId' => 'user-' . $uid,
                    'coachName' => $coach_name,
                    'date' => date('c'),
                    'stage' => $stage,
                    'dimension' => $dimension,
                    'note' => $note_text,
                    'visibility' => $visibility,
                ];

                $inst_data['coachNotes'][] = $note;
                update_instance_json($seller_user_id, $seller_shard_id, 'ai-assessment', $inst_data);

                $_SESSION['success'] = 'Coach note added.';

                // Notify seller if shared
                if ($visibility === 'shared' && function_exists('send_notification')) {
                    send_notification($seller_user_id, $seller_shard_id, 'coach_note',
                        'New Coach Note', "$coach_name added a coaching note.");
                }
            } else {
                $_SESSION['error'] = 'Seller has no assessment instance.';
            }
        }
    } else {
        $_SESSION['error'] = implode('<br>', $errs);
    }
}

if (($_POST['action'] ?? '') === 'delete_coach_note') {
    $errs = [];
    $uid = $_SESSION['user_id'];
    $note_id = trim($_POST['note_id'] ?? '');
    $seller_user_id = (int)($_POST['seller_user_id'] ?? 0);
    $seller_shard_id = trim($_POST['seller_shard_id'] ?? '');

    if (!$uid) { $errs['auth'] = 'Login required.'; }
    if (!$note_id) { $errs['note'] = 'Note ID required.'; }

    if (count($errs) <= 0) {
        $instance = get_user_instance($seller_user_id, $seller_shard_id, 'ai-assessment');
        if ($instance && $instance['instance_data']) {
            $inst_data = $instance['instance_data'];
            $inst_data['coachNotes'] = array_values(array_filter(
                $inst_data['coachNotes'] ?? [],
                fn($n) => $n['noteId'] !== $note_id || $n['coachId'] !== 'user-' . $uid
            ));
            update_instance_json($seller_user_id, $seller_shard_id, 'ai-assessment', $inst_data);
            $_SESSION['success'] = 'Note deleted.';
        }
    } else {
        $_SESSION['error'] = implode('<br>', $errs);
    }
}

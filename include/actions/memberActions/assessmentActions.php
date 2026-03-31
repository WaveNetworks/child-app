<?php
/**
 * assessmentActions.php
 * Actions for starting, resuming, and completing assessments.
 * Updated to work with filesystem-based instance files (v2.3).
 */

// Start a new AI assessment
if (($_POST['action'] ?? '') === 'assessment_start') {
    $errs = [];
    $uid = $_SESSION['user_id'] ?? 0;
    $sid = $_SESSION['shard_id'] ?? '';

    if (!$uid) { $errs['auth'] = 'Login required.'; }

    if (count($errs) <= 0) {
        // Ensure instance file exists
        $inst_data = load_instance_file($uid, $sid, 'ai-assessment');
        if (!$inst_data) {
            $inst_data = create_instance_v23($uid, $sid, 'ai-assessment');
            save_instance_file($uid, $sid, $inst_data, 'ai-assessment');
        }

        // Start or resume session
        $active_session = get_active_session($uid, $sid);
        if (!$active_session) {
            // Get instance DB row ID for session FK
            $stmt = child_db_query_shard_prepared($sid,
                "SELECT id FROM assessment_instances WHERE user_id = ? AND instance_type = 'ai-assessment' LIMIT 1",
                [$uid]
            );
            $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
            $instance_id = $row ? $row['id'] : 0;

            $session_id = start_assessment_session($uid, $sid, $instance_id);
        } else {
            $session_id = $active_session['session_id'];
        }

        $data['session_id'] = $session_id;
        $data['phase'] = $active_session ? $active_session['phase'] : 'profile';
        $data['resuming'] = !!$active_session;
        $_SESSION['success'] = 'Assessment session started.';
    } else {
        $_SESSION['error'] = implode('<br>', $errs);
    }
}

// Complete assessment session
if (($_POST['action'] ?? '') === 'assessment_complete') {
    $errs = [];
    $uid = $_SESSION['user_id'] ?? 0;
    $sid = $_SESSION['shard_id'] ?? '';
    $session_id = $_POST['session_id'] ?? '';

    if (!$uid) { $errs['auth'] = 'Login required.'; }
    if (!$session_id) { $errs['session'] = 'Session ID required.'; }

    if (count($errs) <= 0) {
        $session = get_session_by_id($session_id, $sid);
        if ($session && $session['user_id'] == $uid) {
            child_db_query_shard_prepared($sid,
                "UPDATE assessment_sessions SET completed_at = NOW(), phase = 'score' WHERE session_id = ?",
                [$session_id]
            );

            // Run final scoring with new engine
            $inst_data = load_instance_file($uid, $sid, 'ai-assessment');
            if ($inst_data) {
                $_meta = (array)($inst_data['responseMeta'] ?? []);
                apply_inference_pass($inst_data['responses'], $_meta);
                $inst_data['responseMeta'] = $_meta;
                $inst_data['scores'] = compute_all_stage_scores($inst_data['responses']);
                $inst_data['placement'] = compute_placement($inst_data);
                $inst_data['hardWallStatus'] = compute_hard_wall_status($inst_data['kpiResponses']);
                save_instance_file($uid, $sid, $inst_data, 'ai-assessment');
                compute_user_state($uid, $sid);
            }

            $_SESSION['success'] = 'Assessment completed!';
            $data['redirect'] = 'index.php?page=results';
        } else {
            $_SESSION['error'] = 'Session not found.';
        }
    } else {
        $_SESSION['error'] = implode('<br>', $errs);
    }
}

// Reset assessment — clear all data and start fresh
if (($_POST['action'] ?? '') === 'assessment_reset') {
    $errs = [];
    $uid = $_SESSION['user_id'] ?? 0;
    $sid = $_SESSION['shard_id'] ?? '';

    if (!$uid) { $errs['auth'] = 'Login required.'; }

    if (count($errs) <= 0) {
        // 1. Delete assessment sessions from DB
        child_db_query_shard_prepared($sid,
            "DELETE s FROM assessment_sessions s
             INNER JOIN assessment_instances i ON s.instance_id = i.id
             WHERE i.user_id = ? AND i.instance_type = 'ai-assessment'",
            [$uid]
        );

        // 2. Delete assessment instance from DB
        child_db_query_shard_prepared($sid,
            "DELETE FROM assessment_instances WHERE user_id = ? AND instance_type = 'ai-assessment'",
            [$uid]
        );

        // 3. Delete instance file from filesystem
        $instance_file = find_instance_file($uid, 'ai-assessment');
        if ($instance_file && file_exists($instance_file)) {
            unlink($instance_file);
        }

        // 4. Delete userState.json and recompute (will be empty now)
        $dir = get_elevateher_dir($uid);
        if ($dir && file_exists($dir . 'userState.json')) {
            unlink($dir . 'userState.json');
        }

        $_SESSION['success'] = 'Assessment data cleared. You can start a fresh assessment.';
        $data['redirect'] = 'index.php?page=dashboard';
    } else {
        $_SESSION['error'] = implode('<br>', $errs);
    }
}

// Get assessment state (safe for GET)
if (($_GET['action'] ?? $_POST['action'] ?? '') === 'assessment_state') {
    $uid = $_SESSION['user_id'] ?? 0;
    $sid = $_SESSION['shard_id'] ?? '';

    if ($uid) {
        $inst_data = load_instance_file($uid, $sid, 'ai-assessment');
        $active_session = get_active_session($uid, $sid);

        $data['has_instance'] = !!$inst_data;
        $data['status'] = $inst_data ? 'in_progress' : 'not_started';
        $data['has_active_session'] = !!$active_session;
        $data['session_id'] = $active_session ? $active_session['session_id'] : null;
        $data['phase'] = $active_session ? $active_session['phase'] : null;
        $data['placement'] = $inst_data ? ($inst_data['placement'] ?? null) : null;
        $data['scores'] = $inst_data ? ($inst_data['scores'] ?? null) : null;
    }
}

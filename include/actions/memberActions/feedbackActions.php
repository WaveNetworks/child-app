<?php
/**
 * Feedback Actions (child-app, authenticated users)
 * Actions: submitUserFeedback, getUserFeedback, upvoteUserFeedback, getRoadmapData
 */

// ── Submit feedback (any logged-in user) ────────────────────
if (($action ?? null) == 'submitUserFeedback') {
    $errs = array();

    if (!$_SESSION['user_id']) { $errs['auth'] = 'Login required.'; }

    $message = trim($_POST['message'] ?? '');
    $type    = trim($_POST['feedback_type'] ?? 'general');

    if ($message === '') { $errs['message'] = 'Please enter your feedback.'; }

    if (count($errs) <= 0) {
        $opts = [
            'source_app'  => 'child-app',
            'page_url'    => $_POST['page_url'] ?? ($_SERVER['HTTP_REFERER'] ?? ''),
            'user_id'     => $_SESSION['user_id'],
            'user_role'   => get_user_role_label(),
        ];

        $fid = submit_feedback($message, $type, $opts);
        if ($fid !== false) {
            $data['feedback_id'] = $fid;
            $_SESSION['success'] = 'Thank you for your feedback!';
        } else {
            $_SESSION['error'] = 'Failed to submit feedback. Please try again.';
        }
    } else {
        $_SESSION['error'] = implode('<br>', $errs);
    }
}

// ── Get user's own feedback (AJAX) ──────────────────────────
if (($action ?? null) == 'getUserFeedback') {
    $errs = array();

    if (!$_SESSION['user_id']) { $errs['auth'] = 'Login required.'; }

    if (count($errs) <= 0) {
        $filters = [
            'user_id'  => $_SESSION['user_id'],
            'page'     => $_POST['page'] ?? 1,
            'per_page' => $_POST['per_page'] ?? 20,
        ];
        if (!empty($_POST['feedback_type'])) $filters['feedback_type'] = $_POST['feedback_type'];
        if (!empty($_POST['status']))        $filters['status']        = $_POST['status'];

        $result = get_feedback_entries($filters);
        $data['items']       = $result['items'];
        $data['total']       = $result['total'];
        $data['page']        = $result['page'];
        $data['per_page']    = $result['per_page'];
        $data['user_upvotes'] = get_user_upvotes($_SESSION['user_id']);
    } else {
        $_SESSION['error'] = implode('<br>', $errs);
    }
}

// ── Upvote feedback (any logged-in user) ────────────────────
if (($action ?? null) == 'upvoteUserFeedback') {
    $errs = array();

    if (!$_SESSION['user_id']) { $errs['auth'] = 'Login required.'; }

    $fid = intval($_POST['feedback_id'] ?? 0);
    if ($fid <= 0) { $errs['id'] = 'Feedback ID required.'; }

    if (count($errs) <= 0) {
        $result = upvote_feedback($fid, $_SESSION['user_id']);
        $data['upvoted'] = $result['upvoted'];
        $data['upvotes'] = $result['upvotes'];
        $_SESSION['success'] = $result['upvoted'] ? 'Upvoted!' : 'Upvote removed.';
    } else {
        $_SESSION['error'] = implode('<br>', $errs);
    }
}

// ── Get roadmap data (any logged-in user) ───────────────────
if (($action ?? null) == 'getRoadmapData') {
    $errs = array();

    if (!$_SESSION['user_id']) { $errs['auth'] = 'Login required.'; }

    if (count($errs) <= 0) {
        // Get all non-rejected change requests
        $all = get_change_requests(['per_page' => 100]);
        $items = $all['items'] ?? [];

        // Group by status
        $grouped = [
            'in_progress' => [],
            'approved'    => [],
            'proposed'    => [],
            'completed'   => [],
            'paused'      => [],
            'rejected'    => [],
        ];
        foreach ($items as $cr) {
            $status = $cr['status'] ?? 'proposed';
            if (isset($grouped[$status])) {
                $grouped[$status][] = $cr;
            }
        }

        // Check if user has feedback linked to any change request
        $userFeedback = get_feedback_entries([
            'user_id'  => $_SESSION['user_id'],
            'status'   => 'grouped',
            'per_page' => 100,
        ]);
        $userCRIds = [];
        foreach (($userFeedback['items'] ?? []) as $fb) {
            if ($fb['change_request_id']) {
                $userCRIds[] = (int) $fb['change_request_id'];
            }
        }

        $data['roadmap']     = $grouped;
        $data['user_cr_ids'] = array_unique($userCRIds);
        $data['total']       = $all['total'];
    } else {
        $_SESSION['error'] = implode('<br>', $errs);
    }
}

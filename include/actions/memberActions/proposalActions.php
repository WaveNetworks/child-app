<?php
/**
 * proposalActions.php — Framework proposal/feedback actions
 */

// Submit a new proposal or feedback comment
if (($_POST['action'] ?? '') === 'submit_proposal') {
    $errs = [];
    $uid = $_SESSION['user_id'] ?? 0;

    if (!$uid) { $errs['auth'] = 'Login required.'; }
    if (!has_role('admin')) { $errs['role'] = 'Admin access required.'; }
    if (empty($_POST['target_type'])) { $errs['target_type'] = 'Target type is required.'; }
    if (empty($_POST['rationale'])) { $errs['rationale'] = 'Feedback text is required.'; }

    if (count($errs) <= 0) {
        create_proposal(
            $uid,
            $_POST['target_type'],
            $_POST['target_id'] ?? '',
            $_POST['change_type'] ?? 'comment',
            $_POST['rationale'],
            [
                'field_path' => $_POST['field_path'] ?? null,
                'old_value'  => $_POST['old_value'] ?? null,
                'new_value'  => $_POST['new_value'] ?? null,
            ]
        );
        $_SESSION['success'] = 'Feedback submitted.';
    } else {
        $_SESSION['error'] = implode('<br>', $errs);
    }
}

// Update proposal status (approve/reject)
if (($_POST['action'] ?? '') === 'review_proposal') {
    $errs = [];
    $uid = $_SESSION['user_id'] ?? 0;

    if (!$uid) { $errs['auth'] = 'Login required.'; }
    if (!has_role('admin')) { $errs['role'] = 'Admin access required.'; }
    if (empty($_POST['proposal_id'])) { $errs['id'] = 'Proposal ID required.'; }
    if (empty($_POST['new_status'])) { $errs['status'] = 'Status required.'; }

    $allowed = ['approved', 'rejected', 'submitted'];
    if (!in_array($_POST['new_status'] ?? '', $allowed)) { $errs['status'] = 'Invalid status.'; }

    if (count($errs) <= 0) {
        update_proposal_status((int)$_POST['proposal_id'], $_POST['new_status'], $uid);
        $_SESSION['success'] = 'Proposal ' . $_POST['new_status'] . '.';
    } else {
        $_SESSION['error'] = implode('<br>', $errs);
    }
}

// Bulk-approve all submitted proposals (optionally filtered by target type)
if (($_POST['action'] ?? '') === 'approve_all_proposals') {
    $errs = [];
    $uid = $_SESSION['user_id'] ?? 0;

    if (!$uid) { $errs['auth'] = 'Login required.'; }
    if (!has_role('admin')) { $errs['role'] = 'Admin access required.'; }

    if (count($errs) <= 0) {
        $filters = ['status' => 'submitted'];
        if (!empty($_POST['target_type'])) {
            $filters['target_type'] = $_POST['target_type'];
        }
        $pending = get_proposals($filters) ?: [];
        $count = 0;
        foreach ($pending as $p) {
            update_proposal_status((int)$p['id'], 'approved', $uid);
            $count++;
        }
        $_SESSION['success'] = $count === 1
            ? '1 proposal approved.'
            : $count . ' proposals approved.';
    } else {
        $_SESSION['error'] = implode('<br>', $errs);
    }
}

<?php
/**
 * proposalHelpers.php
 * CRUD helpers for framework proposals/feedback.
 */

function create_proposal($user_id, $target_type, $target_id, $change_type, $rationale, $opts = []) {
    return child_db_query_prepared(
        "INSERT INTO framework_proposals (user_id, target_type, target_id, change_type, field_path, old_value, new_value, rationale, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $user_id,
            $target_type,
            $target_id,
            $change_type,
            $opts['field_path'] ?? null,
            $opts['old_value'] ?? null,
            $opts['new_value'] ?? null,
            $rationale,
            $opts['status'] ?? 'submitted',
        ]
    );
}

function get_proposals($filters = []) {
    $where = [];
    $params = [];

    if (!empty($filters['target_type'])) {
        $where[] = 'target_type = ?';
        $params[] = $filters['target_type'];
    }
    if (!empty($filters['target_id'])) {
        $where[] = 'target_id = ?';
        $params[] = $filters['target_id'];
    }
    if (!empty($filters['status'])) {
        $where[] = 'status = ?';
        $params[] = $filters['status'];
    }
    if (!empty($filters['user_id'])) {
        $where[] = 'user_id = ?';
        $params[] = $filters['user_id'];
    }

    $sql = "SELECT p.*, u.email as user_email
            FROM framework_proposals p
            LEFT JOIN " . ($GLOBALS['dbInstance'] ?? 'wncore_main') . ".user u ON u.user_id = p.user_id";

    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY p.created DESC';

    if (!empty($filters['limit'])) {
        $sql .= ' LIMIT ' . (int)$filters['limit'];
    }

    return child_db_query_prepared($sql, $params);
}

function get_proposal($id) {
    $rows = child_db_query_prepared(
        "SELECT p.*, u.email as user_email
         FROM framework_proposals p
         LEFT JOIN " . ($GLOBALS['dbInstance'] ?? 'wncore_main') . ".user u ON u.user_id = p.user_id
         WHERE p.id = ?",
        [$id]
    );
    return $rows[0] ?? null;
}

function get_proposals_for_target($target_type, $target_id) {
    return get_proposals(['target_type' => $target_type, 'target_id' => $target_id]);
}

function get_proposal_counts_by_target($target_type = null) {
    $sql = "SELECT target_type, target_id, COUNT(*) as total,
                   SUM(status = 'submitted') as pending,
                   SUM(status = 'approved') as approved
            FROM framework_proposals
            WHERE status IN ('submitted','approved')";
    $params = [];
    if ($target_type) {
        $sql .= ' AND target_type = ?';
        $params[] = $target_type;
    }
    $sql .= ' GROUP BY target_type, target_id';

    $rows = child_db_query_prepared($sql, $params);
    $counts = [];
    foreach ($rows as $r) {
        $key = $r['target_type'] . ':' . $r['target_id'];
        $counts[$key] = $r;
    }
    return $counts;
}

function update_proposal_status($id, $status, $reviewed_by = null) {
    $sql = "UPDATE framework_proposals SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?";
    return child_db_query_prepared($sql, [$status, $reviewed_by, $id]);
}

function mark_proposals_applied($ids, $version_id) {
    if (empty($ids)) return;
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $params = array_merge([$version_id], $ids);
    return child_db_query_prepared(
        "UPDATE framework_proposals SET status = 'applied', applied_version_id = ? WHERE id IN ($placeholders)",
        $params
    );
}

<?php
/**
 * dashboardApiActions.php
 * API actions for dashboard data (progress timeline, growth rate).
 */

// ── Progress timeline for longitudinal chart ──
if (($_GET['action'] ?? '') === 'my_progress_timeline') {
    $uid = $_SESSION['user_id'] ?? 0;
    $sid = $_SESSION['shard_id'] ?? '';

    if (!$uid) {
        echo json_encode(['error' => 'Login required.']);
        exit;
    }

    $from = $_GET['from'] ?? null;
    $to   = $_GET['to']   ?? null;

    // Get snapshot timeline
    $snapshots = get_user_progress_timeline($uid, $sid, $from, $to);

    // Format for chart consumption
    $timeline = [];
    foreach ($snapshots as $snap) {
        $timeline[] = [
            'date'      => $snap['created_at'],
            'type'      => $snap['snapshot_type'] ?? 'assessment',
            'stage'     => $snap['stage'] ?? '00',
            'overall'   => round((float)($snap['overall_score'] ?? 0), 4),
            'mindset'   => round((float)($snap['mindset_score'] ?? 0), 4),
            'toolset'   => round((float)($snap['toolset_score'] ?? 0), 4),
            'skillset'  => round((float)($snap['skillset_score'] ?? 0), 4),
            'items_resolved'     => (int)($snap['items_resolved'] ?? 0),
            'items_total'        => (int)($snap['items_total'] ?? 0),
            'content_started'    => (int)($snap['content_started'] ?? 0),
            'content_completed'  => (int)($snap['content_completed'] ?? 0),
            'session_count'      => (int)($snap['session_count'] ?? 0),
        ];
    }

    // Also get growth rate for summary stats
    $growth = compute_growth_rate($uid, $sid, 30);

    echo json_encode([
        'results' => [
            'timeline' => $timeline,
            'growth'   => $growth,
        ]
    ]);
    exit;
}

// ── Save KPI response from dashboard inline form ──
if (($_POST['action'] ?? '') === 'save_kpi_response') {
    $uid = $_SESSION['user_id'] ?? 0;
    $sid = $_SESSION['shard_id'] ?? '';

    if (!$uid) {
        echo json_encode(['error' => 'Login required.']);
        exit;
    }

    $kpi_id = sanitize($_POST['kpi_id'] ?? '', SQL);
    $level  = sanitize($_POST['level'] ?? '', SQL);

    $errs = [];
    if (!$kpi_id) $errs[] = 'Missing KPI ID.';
    if (!in_array($level, ['beginner', 'developing', 'mastery'])) $errs[] = 'Invalid level.';

    if (count($errs) > 0) {
        echo json_encode(['error' => implode(' ', $errs)]);
        exit;
    }

    // Load current instance
    $instance = get_user_instance($uid, $sid, 'ai-assessment');
    if (!$instance) {
        echo json_encode(['error' => 'No assessment found.']);
        exit;
    }

    $inst_data = $instance['instance_data'];
    if (!isset($inst_data['kpiResponses'])) {
        $inst_data['kpiResponses'] = [];
    }

    // Save KPI response
    $inst_data['kpiResponses'][$kpi_id] = [
        'value'      => $level,
        'answeredAt' => date('c'),
    ];

    // Run inference pass then recompute scores/placement after KPI change
    $resp = (array)($inst_data['responses'] ?? []);
    $meta = (array)($inst_data['responseMeta'] ?? []);
    apply_inference_pass($resp, $meta);
    $inst_data['responses'] = $resp;
    $inst_data['responseMeta'] = $meta;
    $inst_data['scores'] = compute_all_stage_scores($inst_data['responses']);
    $inst_data['placement'] = compute_placement($inst_data);
    $inst_data['hardWallStatus'] = compute_hard_wall_status($inst_data['kpiResponses']);

    // Save updated instance
    update_instance_json($uid, $sid, 'ai-assessment', $inst_data);

    // Capture a snapshot
    if (function_exists('capture_progress_snapshot')) {
        capture_progress_snapshot($uid, $sid, 'kpi_update');
    }

    echo json_encode([
        'success' => true,
        'placement' => $inst_data['placement'],
        'message' => 'KPI updated. Placement recalculated.',
    ]);
    exit;
}

// ── Save belief/diagnostic response from dashboard inline form ──
if (($_POST['action'] ?? '') === 'save_belief_response') {
    $uid = $_SESSION['user_id'] ?? 0;
    $sid = $_SESSION['shard_id'] ?? '';

    if (!$uid) {
        echo json_encode(['error' => 'Login required.']);
        exit;
    }

    $item_id = sanitize($_POST['item_id'] ?? '', SQL);
    $value   = $_POST['value'] ?? '';

    $errs = [];
    if (!$item_id) $errs[] = 'Missing item ID.';
    if (!in_array($value, ['true', 'false'], true)) $errs[] = 'Invalid value.';

    if (count($errs) > 0) {
        echo json_encode(['error' => implode(' ', $errs)]);
        exit;
    }

    $bool_value = ($value === 'true');

    // Load current instance
    $instance = get_user_instance($uid, $sid, 'ai-assessment');
    if (!$instance) {
        echo json_encode(['error' => 'No assessment found.']);
        exit;
    }

    $inst_data = $instance['instance_data'];
    if (!isset($inst_data['responses'])) {
        $inst_data['responses'] = [];
    }
    if (!isset($inst_data['responseMeta'])) {
        $inst_data['responseMeta'] = [];
    }

    // Save the response
    $inst_data['responses'][$item_id] = make_response($bool_value);
    $inst_data['responseMeta'][$item_id] = make_response_meta('asked', 1.0, 'Self-assessed from dashboard');

    // Run inference pass then recompute scores/placement
    $resp = (array)($inst_data['responses'] ?? []);
    $meta = (array)($inst_data['responseMeta'] ?? []);
    apply_inference_pass($resp, $meta);
    $inst_data['responses'] = $resp;
    $inst_data['responseMeta'] = $meta;
    $inst_data['scores'] = compute_all_stage_scores($inst_data['responses']);
    $inst_data['placement'] = compute_placement($inst_data);
    $inst_data['hardWallStatus'] = compute_hard_wall_status($inst_data['kpiResponses'] ?? []);

    // Save updated instance
    update_instance_json($uid, $sid, 'ai-assessment', $inst_data);

    // Capture a snapshot
    if (function_exists('capture_progress_snapshot')) {
        capture_progress_snapshot($uid, $sid, 'belief_update');
    }

    echo json_encode([
        'success' => true,
        'item_id' => $item_id,
        'value' => $bool_value,
        'placement' => $inst_data['placement'],
        'message' => $bool_value ? 'Marked as true for you!' : 'Marked as not yet true.',
    ]);
    exit;
}

// ── Recompute scores/placement without changing responses ──
if (($_POST['action'] ?? '') === 'recompute_scores') {
    $uid = $_SESSION['user_id'] ?? 0;
    $sid = $_SESSION['shard_id'] ?? '';

    if (!$uid) {
        echo json_encode(['error' => 'Login required.']);
        exit;
    }

    $instance = get_user_instance($uid, $sid, 'ai-assessment');
    if (!$instance) {
        echo json_encode(['error' => 'No assessment found.']);
        exit;
    }

    $inst_data = $instance['instance_data'];
    $old_placement = $inst_data['placement']['placedStage'] ?? '??';

    // Run inference on existing responses (picks up new inferredBy from updated framework)
    $resp = (array)($inst_data['responses'] ?? []);
    $meta = (array)($inst_data['responseMeta'] ?? []);
    $inf = apply_inference_pass($resp, $meta);
    $inst_data['responses'] = $inf['responses'];
    $inst_data['responseMeta'] = $inf['responseMeta'];

    // Recompute everything
    $inst_data['scores'] = compute_all_stage_scores($inst_data['responses']);
    $inst_data['placement'] = compute_placement($inst_data);
    $inst_data['hardWallStatus'] = compute_hard_wall_status($inst_data['kpiResponses'] ?? []);

    // Stamp framework version so auto-recompute doesn't re-trigger
    $header = function_exists('get_framework_header') ? get_framework_header() : null;
    if ($header) {
        $inst_data['_lastFrameworkVersion'] = ($header['frameworkVersion'] ?? '') . '_' . ($header['dateUpdated'] ?? '');
    }

    // Save
    update_instance_json($uid, $sid, 'ai-assessment', $inst_data);

    // Snapshot the new state
    if (function_exists('capture_progress_snapshot')) {
        capture_progress_snapshot($uid, $sid, 'recompute');
    }

    $new_placement = $inst_data['placement']['placedStage'] ?? '??';
    $msg = ($inf['inferred_count'] ?? 0) > 0
        ? "Recomputed. Inferred {$inf['inferred_count']} items from higher stages."
        : "Recomputed. No new inferences.";
    if ($new_placement !== $old_placement) {
        $msg .= " Stage changed: $old_placement → $new_placement!";
    }

    echo json_encode([
        'success' => true,
        'placement' => $inst_data['placement'],
        'inferred_count' => $inf['inferred_count'] ?? 0,
        'old_stage' => $old_placement,
        'new_stage' => $new_placement,
        'message' => $msg,
    ]);
    exit;
}

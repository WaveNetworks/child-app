<?php
/**
 * progressHelpers.php
 * Longitudinal progress snapshot capture and timeline queries.
 */

function capture_progress_snapshot($user_id, $shard_id, $type = 'assessment') {
    $instance = get_user_instance($user_id, $shard_id, 'ai-assessment');
    if (!$instance || !$instance['instance_data']) return false;

    $d = $instance['instance_data'];
    $placement = $d['placement'] ?? [];
    $scores = $d['scores'] ?? [];
    $responses = (array)($d['responses'] ?? []);
    $meta = (array)($d['responseMeta'] ?? []);

    // Count resolved items
    $items_resolved = 0;
    $items_total = 0;
    for ($i = 0; $i <= 10; $i++) {
        $stage = get_stage($i);
        if (!$stage) continue;
        foreach (get_stage_diagnostic_items($stage) as $item) {
            if ($item['deprecated'] ?? false) continue;
            $items_total++;
            $id = $item['id'] ?? '';
            if (isset($responses[$id]) && ($meta[$id]['confidence'] ?? 0) >= 0.60) {
                $items_resolved++;
            }
        }
    }

    // Count content engagement
    $started = child_db_query_shard_prepared($shard_id,
        "SELECT COUNT(*) FROM content_progress WHERE user_id = ? AND status IN ('started','completed')", [$user_id]
    );
    $completed = child_db_query_shard_prepared($shard_id,
        "SELECT COUNT(*) FROM content_progress WHERE user_id = ? AND status = 'completed'", [$user_id]
    );
    $sessions = child_db_query_shard_prepared($shard_id,
        "SELECT COUNT(*) FROM assessment_sessions WHERE user_id = ?", [$user_id]
    );

    // Build detailed snapshot data
    $per_stage = [];
    for ($i = 0; $i <= 10; $i++) {
        $stage = get_stage($i);
        if (!$stage) continue;
        $key = sprintf('%02d', $i);
        $items = get_stage_diagnostic_items($stage);
        $threshold = (float)($stage['advanceThreshold'] ?? 0.80);
        $per_stage[$key] = [
            'mindset' => compute_dimension_score($responses, $items, 'mindset'),
            'toolset' => compute_dimension_score($responses, $items, 'toolset'),
            'skillset' => compute_dimension_score($responses, $items, 'skillset'),
            'complete' => check_stage_complete($responses, $items, $threshold),
        ];
    }

    $kpi_levels = (array)($d['kpiResponses'] ?? []);
    $walls_crossed = [];
    $walls_blocking = [];
    $hard_walls = get_hard_walls();
    foreach ($hard_walls as $wall) {
        $wall_id = $wall['id'] ?? $wall['label'] ?? '';
        if (check_hard_wall($wall, $kpi_levels)) {
            $walls_crossed[] = $wall_id;
        } else {
            $walls_blocking[] = $wall_id;
        }
    }

    $snapshot_data = json_encode([
        'perStage' => $per_stage,
        'kpiLevels' => $kpi_levels,
        'wallsCrossed' => $walls_crossed,
        'wallsBlocking' => $walls_blocking,
        'contentEngaged' => [],
    ]);

    // Aggregate dimension scores across all stages (max per dimension, matching dashboard logic)
    $agg_mindset = 0; $agg_toolset = 0; $agg_skillset = 0;
    if (is_array($scores)) {
        foreach ($scores as $stage_key => $stage_scores) {
            if (!is_array($stage_scores)) continue;
            $agg_mindset = max($agg_mindset, (float)($stage_scores['mindset'] ?? 0));
            $agg_toolset = max($agg_toolset, (float)($stage_scores['toolset'] ?? 0));
            $agg_skillset = max($agg_skillset, (float)($stage_scores['skillset'] ?? 0));
        }
    }
    $agg_overall = ($agg_mindset + $agg_toolset + $agg_skillset) / 3;

    child_db_query_shard_prepared($shard_id,
        "INSERT INTO progress_snapshots
         (user_id, snapshot_type, stage, overall_score, mindset_score, toolset_score, skillset_score,
          items_resolved, items_total, hard_wall_id, content_started, content_completed, session_count, snapshot_data)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $user_id, $type,
            $placement['currentStage'] ?? '00',
            $agg_overall,
            $agg_mindset,
            $agg_toolset,
            $agg_skillset,
            $items_resolved, $items_total,
            !empty($placement['hardWallBlocking']) ? ($placement['hardWallBlocking']['label'] ?? null) : null,
            $started ? (int)$started->fetchColumn() : 0,
            $completed ? (int)$completed->fetchColumn() : 0,
            $sessions ? (int)$sessions->fetchColumn() : 0,
            $snapshot_data,
        ]
    );

    return true;
}

function get_user_progress_timeline($user_id, $shard_id, $from_date = null, $to_date = null) {
    $sql = "SELECT * FROM progress_snapshots WHERE user_id = ?";
    $params = [$user_id];
    if ($from_date) { $sql .= " AND created_at >= ?"; $params[] = $from_date; }
    if ($to_date) { $sql .= " AND created_at <= ?"; $params[] = $to_date; }
    $sql .= " ORDER BY created_at ASC";

    $stmt = child_db_query_shard_prepared($shard_id, $sql, $params);
    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

function get_user_milestones($user_id, $shard_id) {
    $stmt = child_db_query_shard_prepared($shard_id,
        "SELECT * FROM progress_snapshots WHERE user_id = ? AND snapshot_type = 'milestone' ORDER BY created_at ASC",
        [$user_id]
    );
    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

function compute_growth_rate($user_id, $shard_id, $days = 30) {
    $from = date('Y-m-d H:i:s', strtotime("-{$days} days"));
    $oldest = child_db_query_shard_prepared($shard_id,
        "SELECT * FROM progress_snapshots WHERE user_id = ? AND created_at >= ? ORDER BY created_at ASC LIMIT 1",
        [$user_id, $from]
    );
    $newest = child_db_query_shard_prepared($shard_id,
        "SELECT * FROM progress_snapshots WHERE user_id = ? ORDER BY created_at DESC LIMIT 1",
        [$user_id]
    );

    $old = $oldest ? $oldest->fetch(PDO::FETCH_ASSOC) : null;
    $new = $newest ? $newest->fetch(PDO::FETCH_ASSOC) : null;

    if (!$old || !$new) return ['stage_delta' => 0, 'score_delta' => 0];

    return [
        'stage_delta' => (int)$new['stage'] - (int)$old['stage'],
        'score_delta' => round(($new['overall_score'] ?? 0) - ($old['overall_score'] ?? 0), 4),
        'mindset_delta' => round(($new['mindset_score'] ?? 0) - ($old['mindset_score'] ?? 0), 4),
        'toolset_delta' => round(($new['toolset_score'] ?? 0) - ($old['toolset_score'] ?? 0), 4),
        'skillset_delta' => round(($new['skillset_score'] ?? 0) - ($old['skillset_score'] ?? 0), 4),
        'items_delta' => ($new['items_resolved'] ?? 0) - ($old['items_resolved'] ?? 0),
        'from_date' => $old['created_at'],
        'to_date' => $new['created_at'],
    ];
}

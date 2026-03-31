<?php
/**
 * cohortProgressHelpers.php
 * Cohort-level progress aggregation, content impact analysis, wall crossing rates.
 */

function get_cohort_progress_timeline($from_date = null, $to_date = null) {
    global $childShardConfigs;
    $timeline = [];

    foreach ($childShardConfigs as $shard_id => $cfg) {
        $sql = "SELECT DATE(created_at) as snap_date,
                       AVG(overall_score) as avg_score,
                       AVG(CAST(stage AS UNSIGNED)) as avg_stage,
                       AVG(mindset_score) as avg_mindset,
                       AVG(toolset_score) as avg_toolset,
                       AVG(skillset_score) as avg_skillset,
                       COUNT(DISTINCT user_id) as user_count
                FROM progress_snapshots WHERE 1=1";
        $params = [];
        if ($from_date) { $sql .= " AND created_at >= ?"; $params[] = $from_date; }
        if ($to_date) { $sql .= " AND created_at <= ?"; $params[] = $to_date; }
        $sql .= " GROUP BY DATE(created_at) ORDER BY snap_date ASC";

        $stmt = child_db_query_shard_prepared($shard_id, $sql, $params);
        if (!$stmt) continue;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $date = $row['snap_date'];
            if (!isset($timeline[$date])) {
                $timeline[$date] = [
                    'date' => $date, 'total_score' => 0, 'total_stage' => 0,
                    'total_mindset' => 0, 'total_toolset' => 0, 'total_skillset' => 0,
                    'total_users' => 0,
                ];
            }
            $n = (int)$row['user_count'];
            $timeline[$date]['total_score'] += (float)$row['avg_score'] * $n;
            $timeline[$date]['total_stage'] += (float)$row['avg_stage'] * $n;
            $timeline[$date]['total_mindset'] += (float)$row['avg_mindset'] * $n;
            $timeline[$date]['total_toolset'] += (float)$row['avg_toolset'] * $n;
            $timeline[$date]['total_skillset'] += (float)$row['avg_skillset'] * $n;
            $timeline[$date]['total_users'] += $n;
        }
    }

    $result = [];
    foreach ($timeline as $t) {
        $n = $t['total_users'] ?: 1;
        $result[] = [
            'date' => $t['date'],
            'avg_score' => round($t['total_score'] / $n, 4),
            'avg_stage' => round($t['total_stage'] / $n, 1),
            'avg_mindset' => round($t['total_mindset'] / $n, 4),
            'avg_toolset' => round($t['total_toolset'] / $n, 4),
            'avg_skillset' => round($t['total_skillset'] / $n, 4),
            'user_count' => $t['total_users'],
        ];
    }
    return $result;
}

function get_coach_team_progress($coach_user_id, $coach_shard_id) {
    $sellers = get_coach_sellers($coach_user_id, $coach_shard_id);
    $team_progress = [];

    foreach ($sellers as $rel) {
        $seller_id = $rel['seller_user_id'];
        $seller_shard = $rel['seller_shard_id'];
        $profile = get_user_profile($seller_id, $seller_shard);
        $growth = compute_growth_rate($seller_id, $seller_shard, 30);
        $milestones = get_user_milestones($seller_id, $seller_shard);

        $team_progress[] = [
            'user_id' => $seller_id,
            'name' => trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')),
            'growth_30d' => $growth,
            'milestone_count' => count($milestones),
            'last_milestone' => !empty($milestones) ? end($milestones) : null,
        ];
    }

    return $team_progress;
}

function compute_content_impact($catalog_item_id) {
    global $childShardConfigs;
    $engaged_scores = [];
    $not_engaged_scores = [];

    foreach ($childShardConfigs as $shard_id => $cfg) {
        // Users who completed this content
        $engaged = child_db_query_shard_prepared($shard_id,
            "SELECT cp.user_id FROM content_progress cp
             WHERE cp.catalog_item_id = ? AND cp.status = 'completed'",
            [$catalog_item_id]
        );
        $engaged_ids = $engaged ? $engaged->fetchAll(PDO::FETCH_COLUMN) : [];

        // Get latest scores for all users
        $all = child_db_query_shard_prepared($shard_id,
            "SELECT user_id, overall_score FROM progress_snapshots
             WHERE (user_id, created_at) IN (
                 SELECT user_id, MAX(created_at) FROM progress_snapshots GROUP BY user_id
             )", []
        );
        if (!$all) continue;
        while ($row = $all->fetch(PDO::FETCH_ASSOC)) {
            if (in_array($row['user_id'], $engaged_ids)) {
                $engaged_scores[] = (float)$row['overall_score'];
            } else {
                $not_engaged_scores[] = (float)$row['overall_score'];
            }
        }
    }

    $avg_engaged = !empty($engaged_scores) ? array_sum($engaged_scores) / count($engaged_scores) : 0;
    $avg_not = !empty($not_engaged_scores) ? array_sum($not_engaged_scores) / count($not_engaged_scores) : 0;

    return [
        'catalog_item_id' => $catalog_item_id,
        'engaged_count' => count($engaged_scores),
        'not_engaged_count' => count($not_engaged_scores),
        'avg_score_engaged' => round($avg_engaged, 4),
        'avg_score_not_engaged' => round($avg_not, 4),
        'impact_delta' => round($avg_engaged - $avg_not, 4),
    ];
}

function get_wall_crossing_rates() {
    global $childShardConfigs;
    $hard_walls = get_hard_walls();
    $rates = [];

    foreach ($hard_walls as $wall) {
        $wall_id = $wall['id'] ?? $wall['label'] ?? '';
        $rates[$wall_id] = ['label' => $wall['label'] ?? $wall_id, 'total' => 0, 'crossed' => 0, 'avg_days' => 0];
    }

    foreach ($childShardConfigs as $shard_id => $cfg) {
        $stmt = child_db_query_shard_prepared($shard_id,
            "SELECT snapshot_data FROM progress_snapshots WHERE snapshot_type = 'milestone'", []
        );
        if (!$stmt) continue;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $data = json_decode($row['snapshot_data'] ?? '{}', true);
            $crossed = $data['wallsCrossed'] ?? [];
            foreach ($rates as $wid => &$r) {
                if (in_array($wid, $crossed)) $r['crossed']++;
            }
        }

        // Total users who have assessment instances
        $total = child_db_query_shard_prepared($shard_id,
            "SELECT COUNT(DISTINCT user_id) FROM assessment_instances WHERE instance_type = 'ai-assessment'", []
        );
        $count = $total ? (int)$total->fetchColumn() : 0;
        foreach ($rates as &$r) { $r['total'] += $count; }
    }

    foreach ($rates as &$r) {
        $r['crossing_rate'] = $r['total'] > 0 ? round($r['crossed'] / $r['total'] * 100, 1) : 0;
    }

    return array_values($rates);
}

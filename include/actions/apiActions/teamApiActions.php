<?php
/**
 * teamApiActions.php
 * API actions for coach team and org lead cohort dashboards.
 */

// Coach's team list
if (($_GET['action'] ?? $_POST['action'] ?? '') === 'team_list') {
    $uid = $_SESSION['user_id'] ?? 0;
    $sid = $_SESSION['shard_id'] ?? '';

    if (!$uid || !is_coach($uid)) {
        $_SESSION['error'] = 'Coach access required.';
    } else {
        $relationships = get_coach_sellers($uid, $sid);
        $team = [];

        foreach ($relationships as $rel) {
            $seller_id = $rel['seller_user_id'];
            $seller_shard = $rel['seller_shard_id'];
            $user = get_user($seller_id);
            $profile = get_user_profile($seller_id, $seller_shard);
            $instance = get_user_instance($seller_id, $seller_shard, 'ai-assessment');

            $member = [
                'user_id' => $seller_id,
                'shard_id' => $seller_shard,
                'name' => trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')),
                'email' => $user['email'] ?? '',
                'linked_at' => $rel['linked_at'],
                'status' => $instance ? $instance['status'] : 'not_started',
                'placement' => null,
                'scores' => null,
                'hard_wall' => null,
                'last_active' => $instance ? $instance['updated_at'] : null,
            ];

            if ($instance && $instance['instance_data']) {
                $d = $instance['instance_data'];
                $member['placement'] = $d['placement'] ?? null;
                $member['scores'] = $d['scores'] ?? null;
                $member['hard_wall'] = ($d['placement']['hardWallBlocking'] ?? null) ? $d['placement']['hardWallBlocking']['label'] ?? 'Yes' : null;
            }

            $team[] = $member;
        }

        $data['team'] = $team;
    }
}

// Detailed seller view for coaches
if (($_GET['action'] ?? $_POST['action'] ?? '') === 'team_member_detail') {
    $uid = $_SESSION['user_id'] ?? 0;
    $sid = $_SESSION['shard_id'] ?? '';
    $seller_id = (int)($_GET['seller_id'] ?? $_POST['seller_id'] ?? 0);

    if (!$uid || !is_coach($uid)) {
        $_SESSION['error'] = 'Coach access required.';
    } elseif (!$seller_id) {
        $_SESSION['error'] = 'Seller ID required.';
    } else {
        // Verify active relationship
        $stmt = child_db_query_shard_prepared($sid,
            "SELECT * FROM coach_relationships WHERE coach_user_id = ? AND seller_user_id = ? AND status = 'active'",
            [$uid, $seller_id]
        );
        $rel = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;

        if (!$rel) {
            $_SESSION['error'] = 'No active relationship with this seller.';
        } else {
            $seller_shard = $rel['seller_shard_id'];
            $user = get_user($seller_id);
            $profile = get_user_profile($seller_id, $seller_shard);
            $instance = get_user_instance($seller_id, $seller_shard, 'ai-assessment');

            $data['seller'] = [
                'user_id' => $seller_id,
                'name' => trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')),
                'email' => $user['email'] ?? '',
                'linked_at' => $rel['linked_at'],
                'instance' => $instance ? $instance['instance_data'] : null,
                'status' => $instance ? $instance['status'] : 'not_started',
                'content_progress' => get_content_progress_list($seller_id, $seller_shard),
            ];
        }
    }
}

// Cohort summary (org lead)
if (($_GET['action'] ?? $_POST['action'] ?? '') === 'cohort_summary') {
    $uid = $_SESSION['user_id'] ?? 0;
    if (!$uid || !is_org_lead($uid)) {
        $_SESSION['error'] = 'Organization lead access required.';
    } else {
        global $app_config;
        $cache_key = 'cohort_summary';
        $cached = cache_get($cache_key);
        if ($cached) {
            $data = array_merge($data ?? [], $cached);
        } else {
            $summary = compute_cohort_summary();
            $ttl = ($app_config['cohort_cache_hours'] ?? 1) * 3600;
            cache_set($cache_key, $summary, $ttl);
            $data = array_merge($data ?? [], $summary);
        }
    }
}

// Per-coach stats for DataTable
if (($_GET['action'] ?? $_POST['action'] ?? '') === 'cohort_by_coach') {
    $uid = $_SESSION['user_id'] ?? 0;
    if (!$uid || !is_org_lead($uid)) {
        $_SESSION['error'] = 'Organization lead access required.';
    } else {
        $cache_key = 'cohort_by_coach';
        $cached = cache_get($cache_key);
        if ($cached) {
            $data['results'] = $cached;
        } else {
            $results = compute_cohort_by_coach();
            cache_set($cache_key, $results, 3600);
            $data['results'] = $results;
        }
    }
}

// Timeline snapshots
if (($_GET['action'] ?? $_POST['action'] ?? '') === 'cohort_timeline') {
    $uid = $_SESSION['user_id'] ?? 0;
    if (!$uid || (!is_org_lead($uid) && !is_coach($uid))) {
        $_SESSION['error'] = 'Access denied.';
    } else {
        $stmt = child_db_query_prepared(
            "SELECT snapshot_date, data_json FROM cohort_snapshots ORDER BY snapshot_date ASC LIMIT 365", []
        );
        $data['snapshots'] = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }
}

// Wall distribution
if (($_GET['action'] ?? $_POST['action'] ?? '') === 'wall_distribution') {
    $uid = $_SESSION['user_id'] ?? 0;
    if (!$uid || (!is_org_lead($uid) && !is_coach($uid))) {
        $_SESSION['error'] = 'Access denied.';
    } else {
        $data['walls'] = compute_wall_distribution();
    }
}

// ─── Helper functions for cohort computation ─────────────────────────────

function compute_cohort_summary() {
    global $childShardConfigs;
    $total = 0;
    $active_7d = 0;
    $at_wall = 0;
    $stage_sum = 0;
    $m_sum = $t_sum = $s_sum = 0;
    $content_engaged = 0;
    $stage_dist = ['0-2' => 0, '3-5' => 0, '6-8' => 0, '9-10' => 0];

    foreach ($childShardConfigs as $shard_id => $cfg) {
        $stmt = child_db_query_shard_prepared($shard_id,
            "SELECT instance_json, status, updated_at FROM assessment_instances WHERE instance_type = 'ai-assessment'", []
        );
        if (!$stmt) continue;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $total++;
            $d = json_decode($row['instance_json'], true);
            if (!$d) continue;

            $stage = (int)($d['placement']['currentStage'] ?? 0);
            $stage_sum += $stage;

            if ($stage <= 2) $stage_dist['0-2']++;
            elseif ($stage <= 5) $stage_dist['3-5']++;
            elseif ($stage <= 8) $stage_dist['6-8']++;
            else $stage_dist['9-10']++;

            $m_sum += (float)($d['scores']['mindset_score'] ?? 0);
            $t_sum += (float)($d['scores']['toolset_score'] ?? 0);
            $s_sum += (float)($d['scores']['skillset_score'] ?? 0);

            if (!empty($d['placement']['hardWallBlocking'])) $at_wall++;
            if ($row['updated_at'] && strtotime($row['updated_at']) > strtotime('-7 days')) $active_7d++;
        }

        // Count content engagement
        $cstmt = child_db_query_shard_prepared($shard_id,
            "SELECT COUNT(DISTINCT user_id) FROM content_progress WHERE status IN ('started','completed')", []
        );
        if ($cstmt) $content_engaged += (int)$cstmt->fetchColumn();
    }

    return [
        'total_sellers' => $total,
        'active_7d' => $active_7d,
        'avg_stage' => $total > 0 ? round($stage_sum / $total, 1) : 0,
        'at_wall_count' => $at_wall,
        'content_engagement_rate' => $total > 0 ? round($content_engaged / $total * 100) : 0,
        'avg_mindset' => $total > 0 ? round($m_sum / $total, 2) : 0,
        'avg_toolset' => $total > 0 ? round($t_sum / $total, 2) : 0,
        'avg_skillset' => $total > 0 ? round($s_sum / $total, 2) : 0,
        'stage_distribution' => $stage_dist,
    ];
}

function compute_cohort_by_coach() {
    global $childShardConfigs;
    $coaches = [];

    foreach ($childShardConfigs as $shard_id => $cfg) {
        $stmt = child_db_query_shard_prepared($shard_id,
            "SELECT DISTINCT coach_user_id, coach_shard_id FROM coach_relationships WHERE status = 'active'", []
        );
        if (!$stmt) continue;
        while ($rel = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cid = $rel['coach_user_id'];
            if (!isset($coaches[$cid])) {
                $profile = get_user_profile($cid, $rel['coach_shard_id']);
                $coaches[$cid] = [
                    'coach_id' => $cid,
                    'coach_name' => trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')),
                    'team_size' => 0, 'stage_sum' => 0, 'score_sum' => 0, 'at_wall' => 0, 'active_7d' => 0,
                ];
            }

            // Get sellers for this coach on this shard
            $sellers = child_db_query_shard_prepared($shard_id,
                "SELECT seller_user_id, seller_shard_id FROM coach_relationships WHERE coach_user_id = ? AND status = 'active'",
                [$cid]
            );
            if (!$sellers) continue;
            while ($s = $sellers->fetch(PDO::FETCH_ASSOC)) {
                $inst = get_user_instance($s['seller_user_id'], $s['seller_shard_id'], 'ai-assessment');
                if (!$inst) continue;
                $d = $inst['instance_data'];
                $coaches[$cid]['team_size']++;
                $coaches[$cid]['stage_sum'] += (int)($d['placement']['currentStage'] ?? 0);
                $coaches[$cid]['score_sum'] += (float)($d['scores']['overall_score'] ?? 0);
                if (!empty($d['placement']['hardWallBlocking'])) $coaches[$cid]['at_wall']++;
                if ($inst['updated_at'] && strtotime($inst['updated_at']) > strtotime('-7 days')) $coaches[$cid]['active_7d']++;
            }
        }
    }

    $results = [];
    foreach ($coaches as $c) {
        $results[] = [
            'coach_name' => $c['coach_name'],
            'team_size' => $c['team_size'],
            'avg_stage' => $c['team_size'] > 0 ? round($c['stage_sum'] / $c['team_size'], 1) : 0,
            'avg_score' => $c['team_size'] > 0 ? round($c['score_sum'] / $c['team_size'], 2) : 0,
            'at_wall' => $c['at_wall'],
            'active_7d' => $c['active_7d'],
        ];
    }
    return $results;
}

function compute_wall_distribution() {
    global $childShardConfigs;
    $walls = [];

    foreach ($childShardConfigs as $shard_id => $cfg) {
        $stmt = child_db_query_shard_prepared($shard_id,
            "SELECT instance_json FROM assessment_instances WHERE instance_type = 'ai-assessment'", []
        );
        if (!$stmt) continue;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $d = json_decode($row['instance_json'], true);
            if (!$d) continue;
            $wall = $d['placement']['hardWallBlocking'] ?? null;
            if ($wall) {
                $label = $wall['label'] ?? 'Unknown Wall';
                $walls[$label] = ($walls[$label] ?? 0) + 1;
            }
        }
    }

    return $walls;
}

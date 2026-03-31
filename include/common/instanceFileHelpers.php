<?php
/**
 * instanceFileHelpers.php
 * Filesystem-based instance file I/O for ElevateHER.
 * Reads/writes spec-compliant v2.3 instance JSON files in the user's homedir.
 * UserState is computed from all instance files.
 *
 * Directory: {user_profile.homedir}/elevateher/
 * Files:
 *   instance_ai_{YYYY-MM-DD}.json
 *   instance_self_{YYYY-MM-DD}.json
 *   instance_coach_{coach-id}_{YYYY-MM-DD}.json
 *   userState.json
 */

// ─── Homedir / Directory ────────────────────────────────────────────────────

/**
 * Get the ElevateHER directory for a user. Creates it if missing.
 * Uses admin's existing hashed homedir system.
 */
function get_elevateher_dir($user_id = null) {
    $homedir = $_SESSION['homedir'] ?? '';

    // Fallback: create homedir if missing
    if (!$homedir || !is_dir($homedir)) {
        $uid = $user_id ?: ($_SESSION['user_id'] ?? 0);
        if (!$uid) return false;
        $homedir = create_home_dir_id($uid);
        if ($homedir) {
            $_SESSION['homedir'] = $homedir;
        }
    }
    if (!$homedir) return false;

    $dir = $homedir . 'elevateher/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

// ─── Instance File Naming ───────────────────────────────────────────────────

/**
 * Build the filename for an instance file.
 * @param string $instance_type  'ai-assessment', 'self-assessment', 'coach-assessment'
 * @param string $date           ISO date (YYYY-MM-DD)
 * @param string|null $coach_id  Coach user ID (for coach assessments)
 */
function build_instance_filename($instance_type, $date = null, $coach_id = null) {
    $date = $date ?: date('Y-m-d');
    switch ($instance_type) {
        case 'ai-assessment':
            return "instance_ai_{$date}.json";
        case 'self-assessment':
            return "instance_self_{$date}.json";
        case 'coach-assessment':
            $coach_id = $coach_id ?: 'unknown';
            return "instance_coach_{$coach_id}_{$date}.json";
        default:
            return "instance_{$instance_type}_{$date}.json";
    }
}

// ─── Instance Shell (Schema 2.3) ────────────────────────────────────────────

/**
 * Create a new instance file conforming to framework spec schema 2.3.
 * @return array The instance data
 */
function create_instance_v23($user_id, $shard_id, $instance_type = 'ai-assessment', $coach_id = null) {
    global $ai_config;

    $user = get_user($user_id);
    $profile = get_user_profile($user_id, $shard_id);
    $version = get_active_framework_version();
    $model = $ai_config['model'] ?? 'claude-sonnet-4-20250514';

    // Assessor block per spec
    $assessor = [];
    switch ($instance_type) {
        case 'ai-assessment':
            $assessor = [
                'type' => 'ai',
                'id' => 'elevateher-ai-v1',
                'name' => 'ElevateHER AI Coach',
                'model' => $model,
                'modelVersion' => date('Y-m'),
            ];
            break;
        case 'self-assessment':
            $assessor = [
                'type' => 'self',
                'id' => 'user-' . $user_id,
                'name' => trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')),
            ];
            break;
        case 'coach-assessment':
            $coach_profile = $coach_id ? get_user_profile($coach_id, $shard_id) : null;
            $assessor = [
                'type' => 'coach',
                'id' => 'coach-' . ($coach_id ?: 'unknown'),
                'name' => $coach_profile ? trim($coach_profile['first_name'] . ' ' . $coach_profile['last_name']) : 'Coach',
                'role' => 'Executive Partner',
            ];
            break;
    }

    $instance = [
        'instanceSchema' => [
            'schemaVersion' => '2.3',
            'frameworkVersion' => '5.0',
            'frameworkId' => 'dswa-continuum',
            'instanceType' => $instance_type,
        ],
        'subject' => [
            'id' => 'user-' . $user_id,
            'name' => trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')),
            'email' => $user['email'] ?? '',
            'company' => '',
            'joinDate' => date('c'),
        ],
        'subjectProfile' => [
            'who' => [],
            'what' => [],
            'why' => [],
        ],
        'assessor' => $assessor,
        'sessions' => [],
        'responses' => (object)[],
        'responseMeta' => (object)[],
        'kpiResponses' => (object)[],
        'hardWallStatus' => (object)[],
        'scores' => (object)[],
        'placement' => [
            'placedStage' => null,
            'placedDate' => null,
            'assessedThrough' => null,
            'confidence' => null,
        ],
        'coachNotes' => [],
        'recommendations' => [
            'primaryStage' => null,
            'hardWallAlert' => null,
            'contentSuggestions' => [],
            'fieldWisdomSuggestions' => [],
            'generatedAt' => null,
        ],
        'contentGaps' => [],
        'sessionNotes' => [],
        'legacyResponses' => (object)[],
        'migrationLog' => [],
    ];

    return $instance;
}

// ─── File Read/Write ────────────────────────────────────────────────────────

/**
 * Write an instance file to the user's ElevateHER directory.
 * Also updates the DB row as an index/cache.
 */
function save_instance_file($user_id, $shard_id, $instance_data, $instance_type = 'ai-assessment', $coach_id = null) {
    $dir = get_elevateher_dir($user_id);
    if (!$dir) return false;

    // Determine filename — use existing file if we have one, otherwise create new
    $existing = find_instance_file($user_id, $instance_type, $coach_id);
    if ($existing) {
        $filepath = $existing;
    } else {
        $filename = build_instance_filename($instance_type, date('Y-m-d'), $coach_id);
        $filepath = $dir . $filename;
    }

    $json = json_encode($instance_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $result = file_put_contents($filepath, $json, LOCK_EX);

    // Also update the DB index
    $version = get_active_framework_version();
    $status = 'in_progress';
    if (!empty($instance_data['placement']['placedStage'])) {
        $status = 'completed';
    }
    child_db_query_shard_prepared($shard_id,
        "INSERT INTO assessment_instances (user_id, instance_type, framework_version_id, instance_json, status)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE instance_json = VALUES(instance_json), status = VALUES(status), updated_at = NOW()",
        [$user_id, $instance_type, $version ? $version['id'] : null, $json, $status]
    );

    return $result !== false;
}

/**
 * Find the most recent instance file of a given type for the current user.
 * @return string|null Full file path, or null if not found
 */
function find_instance_file($user_id = null, $instance_type = 'ai-assessment', $coach_id = null) {
    $dir = get_elevateher_dir($user_id);
    if (!$dir) return null;

    switch ($instance_type) {
        case 'ai-assessment':
            $pattern = $dir . 'instance_ai_*.json';
            break;
        case 'self-assessment':
            $pattern = $dir . 'instance_self_*.json';
            break;
        case 'coach-assessment':
            if ($coach_id) {
                $pattern = $dir . "instance_coach_{$coach_id}_*.json";
            } else {
                $pattern = $dir . 'instance_coach_*.json';
            }
            break;
        default:
            $pattern = $dir . "instance_{$instance_type}_*.json";
    }

    $files = glob($pattern);
    if (empty($files)) return null;

    // Return most recent (sorted by filename = sorted by date)
    sort($files);
    return end($files);
}

/**
 * Load an instance file from disk. Falls back to DB if file missing.
 * @return array|null Instance data array
 */
function load_instance_file($user_id, $shard_id, $instance_type = 'ai-assessment', $coach_id = null) {
    $filepath = find_instance_file($user_id, $instance_type, $coach_id);

    if ($filepath && file_exists($filepath)) {
        $json = file_get_contents($filepath);
        $data = json_decode($json, true);
        if ($data) return $data;
    }

    // Fallback: try DB
    $stmt = child_db_query_shard_prepared($shard_id,
        "SELECT instance_json FROM assessment_instances WHERE user_id = ? AND instance_type = ? LIMIT 1",
        [$user_id, $instance_type]
    );
    if ($stmt) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && $row['instance_json']) {
            return json_decode($row['instance_json'], true);
        }
    }

    return null;
}

/**
 * List all instance files for a user (all types).
 * @return array Array of ['type' => ..., 'path' => ..., 'data' => ...]
 */
function list_instance_files($user_id = null) {
    $dir = get_elevateher_dir($user_id);
    if (!$dir) return [];

    $files = glob($dir . 'instance_*.json');
    $result = [];

    foreach ($files as $filepath) {
        $filename = basename($filepath);
        $type = 'unknown';
        if (preg_match('/^instance_ai_/', $filename)) $type = 'ai-assessment';
        elseif (preg_match('/^instance_self_/', $filename)) $type = 'self-assessment';
        elseif (preg_match('/^instance_coach_/', $filename)) $type = 'coach-assessment';

        $result[] = [
            'type' => $type,
            'path' => $filepath,
            'filename' => $filename,
        ];
    }

    return $result;
}

// ─── UserState Computation ──────────────────────────────────────────────────

/**
 * Compute the userState from all instance files for a user.
 * Follows v5.0_userStateSchema.json spec.
 * Merges responses across all instances — highest confidence wins.
 */
function compute_user_state($user_id, $shard_id) {
    $instances = list_instance_files($user_id);
    $all_data = [];

    foreach ($instances as $inst) {
        $json = file_get_contents($inst['path']);
        $data = json_decode($json, true);
        if ($data) {
            $all_data[] = ['type' => $inst['type'], 'data' => $data];
        }
    }

    if (empty($all_data)) return null;

    $user = get_user($user_id);
    $profile = get_user_profile($user_id, $shard_id);

    // Build itemProficiency from all instances
    // Map boolean responses + confidence → none/beginner/developing/mastery
    $item_proficiency = [];
    $kpi_scores = [];
    $content_log = [];
    $assessment_history = [];
    $merged_responses = [];
    $merged_meta = [];

    foreach ($all_data as $inst) {
        $d = $inst['data'];
        $responses = (array)($d['responses'] ?? []);
        $meta = (array)($d['responseMeta'] ?? []);
        $kpis = (array)($d['kpiResponses'] ?? []);

        // Merge responses — highest confidence wins
        foreach ($responses as $item_id => $resp) {
            $value = is_array($resp) ? ($resp['value'] ?? $resp) : $resp;
            $conf = $meta[$item_id]['confidence'] ?? ($meta[$item_id]['confidence'] ?? 0.5);
            $existing_conf = $merged_meta[$item_id]['confidence'] ?? 0;

            if ($conf > $existing_conf) {
                $merged_responses[$item_id] = $value;
                $merged_meta[$item_id] = $meta[$item_id] ?? ['confidence' => $conf];
            }
        }

        // Merge KPIs — latest assessment wins
        foreach ($kpis as $kpi_id => $kpi_data) {
            $level = is_array($kpi_data) ? ($kpi_data['value'] ?? $kpi_data['level'] ?? 'beginner') : $kpi_data;
            $kpi_scores[$kpi_id] = [
                'level' => $level,
                'lastAssessed' => is_array($kpi_data) ? ($kpi_data['answeredAt'] ?? null) : null,
                'windowStart' => null,
            ];
        }

        // Collect sessions for assessment history
        foreach ($d['sessions'] ?? [] as $sess) {
            $assessment_history[] = [
                'timestamp' => $sess['date'] ?? null,
                'assessedStage' => $sess['completedThrough'] ?? null,
                'method' => $inst['type'] === 'ai-assessment' ? 'ai-conversation'
                    : ($inst['type'] === 'self-assessment' ? 'self-report' : 'upline-assessment'),
                'confidence' => 0.8,
                'itemsResolved' => [],
            ];
        }
    }

    // Convert merged responses to itemProficiency levels
    // Get all items from all stages
    for ($i = 0; $i <= 10; $i++) {
        $stage = get_stage($i);
        if (!$stage) continue;

        foreach (['mindset', 'toolset', 'skillset'] as $dim) {
            $items = $stage[$dim] ?? [];
            foreach ($items as $item) {
                $id = $item['id'] ?? '';
                if (!$id) continue;

                if (!isset($merged_responses[$id])) {
                    $item_proficiency[$id] = 'none';
                } else {
                    $value = $merged_responses[$id];
                    $conf = $merged_meta[$id]['confidence'] ?? 0.5;

                    if ($value === false || $value === 0) {
                        $item_proficiency[$id] = 'beginner';
                    } elseif ($conf >= 0.85) {
                        $item_proficiency[$id] = 'mastery';
                    } elseif ($conf >= 0.60) {
                        $item_proficiency[$id] = 'developing';
                    } else {
                        $item_proficiency[$id] = 'beginner';
                    }
                }
            }
        }
    }

    // Fill in all KPIs not yet assessed
    for ($i = 0; $i <= 10; $i++) {
        $stage = get_stage($i);
        if (!$stage) continue;
        foreach ($stage['kpis'] ?? [] as $kpi) {
            $id = $kpi['id'] ?? '';
            if ($id && !isset($kpi_scores[$id])) {
                $kpi_scores[$id] = ['level' => 'beginner', 'lastAssessed' => null, 'windowStart' => null];
            }
        }
    }

    // Compute placement from merged data
    $current_stage = '00';
    for ($i = 0; $i <= 10; $i++) {
        $stage_key = sprintf('%02d', $i);
        $stage = get_stage($i);
        if (!$stage) continue;

        // Check if stage is complete based on itemProficiency
        $total = 0;
        $passed = 0;
        foreach (['mindset', 'toolset', 'skillset'] as $dim) {
            foreach ($stage[$dim] ?? [] as $item) {
                if ($item['deprecated'] ?? false) continue;
                $total++;
                $level = $item_proficiency[$item['id']] ?? 'none';
                if (in_array($level, ['developing', 'mastery'])) {
                    $passed++;
                }
            }
        }

        $threshold = (float)($stage['advanceThreshold'] ?? 0.80);
        if ($total > 0 && ($passed / $total) >= $threshold) {
            $current_stage = sprintf('%02d', min($i + 1, 10));
        } else {
            $current_stage = $stage_key;
            break;
        }
    }

    // Determine tier
    $tier_map = ['00' => 'pre-spark'];
    foreach (['01','02','03','04'] as $s) $tier_map[$s] = 'rising-star';
    foreach (['05','06','07'] as $s) $tier_map[$s] = 'pro';
    foreach (['08','09','10'] as $s) $tier_map[$s] = 'elite';

    // Compute wall status
    $hard_walls = get_hard_walls();
    $wall_status = [];
    foreach ($hard_walls as $wall) {
        $wall_id = $wall['id'] ?? '';
        $gating_stages = $wall['gatingKpiStages'] ?? [];
        $all_pass = true;
        foreach ($gating_stages as $gs) {
            $stage = get_stage((int)$gs);
            if (!$stage) continue;
            foreach ($stage['kpis'] ?? [] as $kpi) {
                $level = $kpi_scores[$kpi['id']]['level'] ?? 'beginner';
                if (!meets_level_v23($level, 'developing')) {
                    $all_pass = false;
                    break 2;
                }
            }
        }
        $wall_status[$wall_id] = $all_pass ? 'cleared' : 'locked';
    }

    // Compute dimensionStrength per stage
    $dim_strength = [];
    for ($i = 0; $i <= 10; $i++) {
        $stage = get_stage($i);
        if (!$stage) continue;
        $key = sprintf('%02d', $i);
        $has_data = false;

        $strengths = [];
        foreach (['mindset', 'toolset', 'skillset'] as $dim) {
            $total = 0;
            $score = 0;
            foreach ($stage[$dim] ?? [] as $item) {
                if ($item['deprecated'] ?? false) continue;
                $total++;
                $level = $item_proficiency[$item['id']] ?? 'none';
                $score += ['none' => 0, 'beginner' => 0.33, 'developing' => 0.66, 'mastery' => 1.0][$level] ?? 0;
                if ($level !== 'none') $has_data = true;
            }
            $strengths[$dim] = $total > 0 ? round($score / $total, 2) : 0;
        }

        if ($has_data) {
            $dim_strength[$key] = $strengths;
        }
    }

    // Load content log from content_progress table
    $stmt = child_db_query_shard_prepared($shard_id,
        "SELECT catalog_item_id, status, COALESCE(completed_at, started_at, recommended_at) as timestamp, recommended_by
         FROM content_progress WHERE user_id = ? ORDER BY COALESCE(completed_at, started_at, recommended_at)",
        [$user_id]
    );
    if ($stmt) {
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $content_log[] = [
                'catalogId' => $row['catalog_item_id'],
                'status' => $row['status'],
                'timestamp' => $row['timestamp'],
                'source' => $row['recommended_by'] ?? 'recommendation',
            ];
        }
    }

    $user_state = [
        '$schema' => 'v5.0_userStateSchema.json',
        'userId' => 'user-' . $user_id,
        'displayName' => trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')),
        'currentStage' => $current_stage,
        'currentTier' => $tier_map[$current_stage] ?? 'pre-spark',
        'itemProficiency' => $item_proficiency,
        'kpiScores' => $kpi_scores,
        'wallStatus' => $wall_status,
        'contentLog' => $content_log,
        'assessmentHistory' => $assessment_history,
        'dimensionStrength' => $dim_strength,
        'createdAt' => date('c'),
        'lastActive' => date('c'),
    ];

    // Save to disk
    $dir = get_elevateher_dir($user_id);
    if ($dir) {
        file_put_contents($dir . 'userState.json',
            json_encode($user_state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    return $user_state;
}

/**
 * Load the cached userState from disk. Recompute if missing.
 */
function load_user_state($user_id, $shard_id) {
    $dir = get_elevateher_dir($user_id);
    if ($dir && file_exists($dir . 'userState.json')) {
        $data = json_decode(file_get_contents($dir . 'userState.json'), true);
        if ($data) return $data;
    }
    return compute_user_state($user_id, $shard_id);
}

// ─── Response Helpers (Schema 2.3 Format) ───────────────────────────────────

/**
 * Create a spec-compliant response object.
 */
function make_response($value, $answered_at = null) {
    return [
        'value' => (bool)$value,
        'answeredAt' => $answered_at ?: date('c'),
    ];
}

/**
 * Create a spec-compliant responseMeta object.
 */
function make_response_meta($source, $confidence, $reasoning = null) {
    return [
        'source' => $source,  // 'asked' or 'inferred'
        'confidence' => (float)$confidence,
        'reasoning' => $reasoning,
    ];
}

/**
 * Create a spec-compliant kpiResponse object.
 */
function make_kpi_response($level, $answered_at = null) {
    return [
        'value' => $level,  // 'beginner', 'developing', 'mastery'
        'answeredAt' => $answered_at ?: date('c'),
    ];
}

/**
 * Read the boolean value from a response (handles both old bare-bool and new object format).
 */
function read_response_value($response) {
    if (is_array($response)) {
        return $response['value'] ?? false;
    }
    return (bool)$response;
}

/**
 * Level comparison per spec: beginner < developing < mastery
 */
function meets_level_v23($current, $target) {
    $levels = ['beginner' => 1, 'developing' => 2, 'mastery' => 3];
    return ($levels[$current] ?? 0) >= ($levels[$target] ?? 0);
}

// ─── Profile Helpers (Structured who/what/why) ──────────────────────────────

/**
 * Set a profile field using dot-notation path (e.g., 'who.lifeStage', 'what.teamSize').
 */
function set_profile_field(&$subject_profile, $field, $value) {
    $parts = explode('.', $field);
    if (count($parts) === 2 && in_array($parts[0], ['who', 'what', 'why'])) {
        if (!is_array($subject_profile[$parts[0]] ?? null)) {
            $subject_profile[$parts[0]] = [];
        }
        $subject_profile[$parts[0]][$parts[1]] = $value;
    } else {
        // Flat field — map to the right sub-object
        $field_map = [
            'lifeStage' => 'who', 'ageRange' => 'who', 'backgroundIndustry' => 'who',
            'industry' => 'who', 'householdRole' => 'who',
            'businessTenure' => 'what', 'priorDSExperience' => 'what', 'priorDsExperience' => 'what',
            'teamSize' => 'what', 'engagementLevel' => 'what', 'productRelationship' => 'what',
            'primaryMotivation' => 'why', 'secondaryMotivations' => 'why',
            'drivingCircumstance' => 'why', 'tags' => 'why',
        ];
        $section = $field_map[$field] ?? null;
        if ($section) {
            if (!is_array($subject_profile[$section] ?? null)) {
                $subject_profile[$section] = [];
            }
            $subject_profile[$section][$field] = $value;
        }
    }
}

<?php
/**
 * contentHelpers.php
 * Content state machine implementing v5.0_contentRules.json.
 * Resolves every catalog item to one of 7 states for a given user.
 * Uses itemRefs for recommendation scoring, not just stage/dimension matching.
 *
 * States (resolution order):
 *   locked → in-progress → mastered → proficient → completed → recommended → available
 */

// ─── Content State Resolution ───────────────────────────────────────────────

/**
 * Resolve the state of a single content catalog item for a user.
 * Implements the evaluationPseudocode from v5.0_contentRules.json exactly.
 *
 * @param array $content   Catalog item from v5.0_contentCatalog.json
 * @param array $user_state  UserState from userState.json
 * @return string  One of: locked, in-progress, mastered, proficient, completed, recommended, available
 */
function resolve_content_state($content, $user_state) {
    $content_type = $content['type'] ?? '';
    $stages = (array)($content['stages'] ?? []);
    $item_refs = (array)($content['itemRefs'] ?? []);
    $content_id = $content['id'] ?? '';

    $user_stage = $user_state['currentStage'] ?? '00';
    $wall_status = (array)($user_state['wallStatus'] ?? []);
    $item_proficiency = (array)($user_state['itemProficiency'] ?? []);
    $content_log = (array)($user_state['contentLog'] ?? []);

    // 1. VISIBILITY CHECK — recurring events always visible within stage range
    if ($content_type !== 'recurring-event') {
        $accessible = false;
        foreach ($stages as $s) {
            if (is_stage_accessible($s, $user_stage, $wall_status)) {
                $accessible = true;
                break;
            }
        }
        if (!$accessible) return 'locked';
    }

    // 2. IN-PROGRESS CHECK
    $log_entry = find_content_log_entry($content_log, $content_id);
    if ($log_entry && $log_entry['status'] === 'started') return 'in-progress';

    // 3. PROFICIENCY CHECK (only for content with itemRefs)
    if (!empty($item_refs)) {
        $levels = array_map(function($ref) use ($item_proficiency) {
            return $item_proficiency[$ref] ?? 'none';
        }, $item_refs);

        $all_mastery = true;
        $all_developing_plus = true;
        foreach ($levels as $level) {
            if ($level !== 'mastery') $all_mastery = false;
            if (!in_array($level, ['developing', 'mastery'])) $all_developing_plus = false;
        }

        if ($all_mastery) return 'mastered';
        if ($all_developing_plus) return 'proficient';
    }

    // 4. COMPLETION CHECK
    if ($log_entry && $log_entry['status'] === 'completed') return 'completed';

    // 5. RECOMMENDATION SCORE
    if (!empty($item_refs)) {
        $need_score = 0;
        foreach ($item_refs as $ref) {
            $level = $item_proficiency[$ref] ?? 'none';
            if ($level === 'none') $need_score += 2;
            elseif ($level === 'beginner') $need_score += 1;
        }
        if ($need_score > 0) return 'recommended';
    }

    // 6. DEFAULT
    return 'available';
}

/**
 * Check if a stage is accessible to a user (within ±1 range and not wall-gated).
 */
function is_stage_accessible($stage_str, $user_stage, $wall_status) {
    $stage_int = (int)$stage_str;
    $user_int = (int)$user_stage;

    // Stage 00 is always accessible
    if ($stage_str === '00') return true;

    // Check range: ±1
    if (abs($stage_int - $user_int) > 1) return false;

    // Check walls gating this stage
    $walls = get_hard_walls();
    foreach ($walls as $wall) {
        $wall_to = (int)($wall['to'] ?? 0);
        if ($stage_int >= $wall_to) {
            $wall_id = $wall['id'] ?? '';
            if (($wall_status[$wall_id] ?? 'locked') !== 'cleared') {
                return false;
            }
        }
    }

    return true;
}

/**
 * Find a content log entry for a catalog item.
 */
function find_content_log_entry($content_log, $catalog_id) {
    // Return the most recent entry
    $found = null;
    foreach ($content_log as $entry) {
        if (($entry['catalogId'] ?? '') === $catalog_id) {
            $found = $entry;
        }
    }
    return $found;
}

// ─── Recommendation Engine ──────────────────────────────────────────────────

/**
 * Get scored content recommendations for a user.
 * Implements rec-item-need, rec-dimension-balance, and rec-sequence-position rules.
 *
 * @return array Sorted array of { item, state, score, reason }
 */
function get_content_recommendations($user_id, $shard_id) {
    $user_state = load_user_state($user_id, $shard_id);
    if (!$user_state) return [];

    $catalog = get_content_catalog();
    if (empty($catalog)) return [];
    $items = isset($catalog['contentCatalog']) ? $catalog['contentCatalog'] : (isset($catalog['items']) ? $catalog['items'] : $catalog);
    if (!is_array($items)) return [];

    $sequence = get_content_sequence();
    $current_stage = $user_state['currentStage'] ?? '00';
    $dim_strength = $user_state['dimensionStrength'] ?? [];
    $item_proficiency = $user_state['itemProficiency'] ?? [];

    // Find weakest dimension at current stage for dimension balancing
    $current_dim = $dim_strength[$current_stage] ?? [];
    $dim_balance_boost = null;
    if (!empty($current_dim)) {
        $strongest = max($current_dim);
        $weakest_dim = null;
        $weakest_val = 1.0;
        foreach ($current_dim as $dim => $val) {
            if ($val < $weakest_val) {
                $weakest_val = $val;
                $weakest_dim = $dim;
            }
        }
        if ($weakest_dim && ($strongest - $weakest_val) >= 0.3) {
            $dim_balance_boost = $weakest_dim;
        }
    }

    // Get sequence position for current stage
    $stage_key = 's' . $current_stage;
    $stage_sequence = $sequence['stages'][$stage_key]['recommended_order'] ?? [];

    $recommendations = [];

    foreach ($items as $item) {
        $state = resolve_content_state($item, $user_state);

        // Only recommend items in 'recommended' state
        if ($state !== 'recommended') continue;

        $item_refs = (array)($item['itemRefs'] ?? []);
        $dimensions = array_map('strtolower', (array)($item['dimensions'] ?? []));

        // Score: count of unmastered itemRefs (none × 2, beginner × 1)
        $score = 0;
        foreach ($item_refs as $ref) {
            $level = $item_proficiency[$ref] ?? 'none';
            if ($level === 'none') $score += 2;
            elseif ($level === 'beginner') $score += 1;
        }

        // Dimension balance boost (1.5×)
        if ($dim_balance_boost && in_array($dim_balance_boost, $dimensions)) {
            $score *= 1.5;
        }

        // Sequence position boost
        $seq_pos = array_search($item['id'], $stage_sequence);
        if ($seq_pos !== false && count($stage_sequence) > 0) {
            $seq_boost = (count($stage_sequence) - $seq_pos) / count($stage_sequence);
            $score += $seq_boost;
        }

        $reason = '';
        if ($dim_balance_boost && in_array($dim_balance_boost, $dimensions)) {
            $reason = 'Strengthens your ' . ucfirst($dim_balance_boost);
        } elseif ($score > 3) {
            $reason = 'Addresses key gaps in your current stage';
        } else {
            $reason = 'Recommended for your journey';
        }

        $recommendations[] = [
            'item' => $item,
            'state' => $state,
            'score' => $score,
            'reason' => $reason,
        ];
    }

    // Sort by score descending
    usort($recommendations, fn($a, $b) => $b['score'] <=> $a['score']);

    return $recommendations;
}

/**
 * Resolve all catalog items to their states for the content browse view.
 * Returns array of { item, state } for every catalog item.
 */
function resolve_all_content_states($user_id, $shard_id) {
    $user_state = load_user_state($user_id, $shard_id);
    if (!$user_state) return [];

    $catalog = get_content_catalog();
    if (empty($catalog)) return [];
    $items = isset($catalog['contentCatalog']) ? $catalog['contentCatalog'] : (isset($catalog['items']) ? $catalog['items'] : $catalog);

    $results = [];
    foreach ($items as $item) {
        $results[] = [
            'item' => $item,
            'state' => resolve_content_state($item, $user_state),
        ];
    }

    return $results;
}

/**
 * Get content recommendations specifically for items that were just assessed.
 * Finds catalog items whose itemRefs overlap with the resolved item IDs.
 *
 * @param array $resolved_item_ids  Diagnostic item IDs to match against
 * @param array $user_state         UserState object
 * @param string|null $assessed_stage  Override currentStage for visibility (use the stage being assessed)
 */
function get_content_for_resolved_items($resolved_item_ids, $user_state, $assessed_stage = null) {
    if (empty($resolved_item_ids)) return [];

    // Use the assessed stage for visibility so content for the stage being
    // discussed is reachable, not just the user's placed stage.
    // Also bypass wall checks — during active assessment, walls shouldn't
    // prevent content delivery for stages we're actively evaluating.
    if ($assessed_stage !== null) {
        $user_state['currentStage'] = $assessed_stage;
        // Mark all walls as cleared for content visibility during assessment
        $walls = get_hard_walls();
        foreach ($walls as $wall) {
            $user_state['wallStatus'][$wall['id']] = 'cleared';
        }
    }

    $catalog = get_content_catalog();
    if (empty($catalog)) { error_log("[content] catalog empty"); return []; }
    $items = isset($catalog['contentCatalog']) ? $catalog['contentCatalog'] : (isset($catalog['items']) ? $catalog['items'] : $catalog);

    error_log("[content] searching " . count($items) . " catalog items for " . count($resolved_item_ids) . " resolved IDs (assessed_stage=" . ($assessed_stage ?? 'null') . ", currentStage=" . ($user_state['currentStage'] ?? 'null') . ")");

    $matches = [];
    $debug_rejected = [];
    foreach ($items as $item) {
        $item_refs = (array)($item['itemRefs'] ?? []);
        $overlap = array_intersect($item_refs, $resolved_item_ids);
        if (!empty($overlap)) {
            $state = resolve_content_state($item, $user_state);
            if (in_array($state, ['recommended', 'available'])) {
                $matches[] = [
                    'item' => $item,
                    'state' => $state,
                    'overlap' => count($overlap),
                ];
            } else {
                $debug_rejected[] = ($item['id'] ?? '?') . ':' . $state;
            }
        }
    }

    if (!empty($debug_rejected)) {
        error_log("[content] " . count($debug_rejected) . " items had overlap but rejected — " . implode(', ', array_slice($debug_rejected, 0, 10)));
    }
    error_log("[content] returning " . count($matches) . " matches");

    // Sort by overlap count (most relevant first)
    usort($matches, fn($a, $b) => $b['overlap'] <=> $a['overlap']);

    return $matches;
}

// ─── Content Thumbnail Helper ───────────────────────────────────────────────

/**
 * Get the thumbnail URL for a content catalog item.
 * Looks in the framework content directory structure.
 */
function get_content_thumbnail_url($catalog_item) {
    $id = $catalog_item['id'] ?? '';
    $stages = (array)($catalog_item['stages'] ?? []);
    $dimensions = array_map('strtolower', (array)($catalog_item['dimensions'] ?? []));
    $title = $catalog_item['title'] ?? '';

    if (empty($stages) || empty($dimensions)) return null;

    $stage_num = $stages[0];
    $dim = $dimensions[0];

    // Build stage folder name
    $stage_names = [
        '00' => 'stage_00_the_seedbed', '01' => 'stage_01_the_spark',
        '02' => 'stage_02_the_activator', '03' => 'stage_03_the_producer',
        '04' => 'stage_04_the_connector', '05' => 'stage_05_the_sponsor',
        '06' => 'stage_06_the_team_builder', '07' => 'stage_07_the_culture_architect',
        '08' => 'stage_08_the_leader_developer', '09' => 'stage_09_the_visionary',
        '10' => 'stage_10_the_architect',
    ];
    $stage_folder = $stage_names[$stage_num] ?? null;
    if (!$stage_folder) return null;

    // Check mockup_thumbnails directory
    $base_path = realpath(__DIR__ . '/../../../dswa-framework/framework/content/_mockup_pages/mockup_thumbnails');
    if (!$base_path) return null;

    $thumb_dir = $base_path . '/' . $stage_folder . '/' . $dim;
    if (!is_dir($thumb_dir)) return null;

    // Look for *_mockup.png files in the directory
    $files = glob($thumb_dir . '/*_mockup.png');
    if (!empty($files)) {
        // Try to match by title
        $title_slug = str_replace([' ', '—', '–', ':', ',', "'", '"'], ['_', '', '', '', '', '', ''], $title);
        foreach ($files as $f) {
            $fname = basename($f);
            if (stripos($fname, substr($title_slug, 0, 15)) !== false) {
                return '/elevateher/content/_mockup_pages/mockup_thumbnails/' . $stage_folder . '/' . $dim . '/' . rawurlencode($fname);
            }
        }
        // Fallback: return the first one
        return '/elevateher/content/_mockup_pages/mockup_thumbnails/' . $stage_folder . '/' . $dim . '/' . rawurlencode(basename($files[0]));
    }

    return null;
}

// ─── Content Markdown Loader ─────────────────────────────────────────────────

/**
 * Load the mirrored Markdown file for a content catalog item.
 * Returns the full text content or null if no MD file exists.
 *
 * Uses the same stage_names mapping as get_content_thumbnail_url()
 * and searches the stage/dimension folder for a matching .md file by title slug.
 */
function get_content_markdown($catalog_item) {
    $stages = (array)($catalog_item['stages'] ?? []);
    $dimensions = array_map('strtolower', (array)($catalog_item['dimensions'] ?? []));
    $title = $catalog_item['title'] ?? '';

    if (empty($stages) || empty($dimensions) || !$title) return null;

    $stage_num = $stages[0];
    $dim = $dimensions[0];

    $stage_names = [
        '00' => 'stage_00_the_seedbed', '01' => 'stage_01_the_spark',
        '02' => 'stage_02_the_activator', '03' => 'stage_03_the_producer',
        '04' => 'stage_04_the_connector', '05' => 'stage_05_the_sponsor',
        '06' => 'stage_06_the_team_builder', '07' => 'stage_07_the_culture_architect',
        '08' => 'stage_08_the_leader_developer', '09' => 'stage_09_the_visionary',
        '10' => 'stage_10_the_architect',
    ];
    $stage_folder = $stage_names[$stage_num] ?? null;
    if (!$stage_folder) return null;

    $base_path = realpath(__DIR__ . '/../../../dswa-framework/framework/content');
    if (!$base_path) return null;

    $content_dir = $base_path . '/' . $stage_folder . '/' . $dim;
    if (!is_dir($content_dir)) return null;

    $md_files = glob($content_dir . '/*.md');
    if (empty($md_files)) return null;

    // Match by title slug — same approach as thumbnail matching
    $title_slug = str_replace([' ', '—', '–', ':', ',', "'", '"'], ['_', '', '', '', '', '', ''], $title);
    foreach ($md_files as $f) {
        $fname = basename($f, '.md');
        if (stripos($fname, substr($title_slug, 0, 15)) !== false) {
            $text = file_get_contents($f);
            return $text !== false ? $text : null;
        }
    }

    // Fallback: try matching catalog ID in filename (stages 7+ use cat-sXX-... filenames)
    $cat_id = $catalog_item['id'] ?? '';
    if ($cat_id) {
        foreach ($md_files as $f) {
            $fname = basename($f, '.md');
            if (stripos($fname, $cat_id) !== false) {
                $text = file_get_contents($f);
                return $text !== false ? $text : null;
            }
        }
    }

    return null;
}

// ─── Content Progress Tracking ──────────────────────────────────────────────

function get_user_completed_items($user_id, $shard_id) {
    $stmt = child_db_query_shard_prepared($shard_id,
        "SELECT catalog_item_id FROM content_progress WHERE user_id = ? AND status IN ('completed', 'skipped')",
        [$user_id]
    );
    return $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
}

function get_content_progress_list($user_id, $shard_id, $status = null) {
    $sql = "SELECT * FROM content_progress WHERE user_id = ?";
    $params = [$user_id];
    if ($status) {
        $sql .= " AND status = ?";
        $params[] = $status;
    }
    $sql .= " ORDER BY COALESCE(completed_at, started_at, recommended_at) DESC";
    $stmt = child_db_query_shard_prepared($shard_id, $sql, $params);
    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

function update_content_progress($user_id, $shard_id, $catalog_item_id, $new_status, $notes = null) {
    $now = date('Y-m-d H:i:s');
    $sql_parts = ["status = ?"];
    $params = [$new_status];

    if ($new_status === 'started') {
        $sql_parts[] = "started_at = ?";
        $params[] = $now;
    } elseif ($new_status === 'completed') {
        $sql_parts[] = "completed_at = ?";
        $params[] = $now;
    }
    if ($notes !== null) {
        $sql_parts[] = "notes = ?";
        $params[] = $notes;
    }

    $set_clause = implode(', ', $sql_parts);
    $params[] = $user_id;
    $params[] = $catalog_item_id;

    $stmt = child_db_query_shard_prepared($shard_id,
        "UPDATE content_progress SET $set_clause WHERE user_id = ? AND catalog_item_id = ?",
        $params
    );

    if ($stmt && $stmt->rowCount() === 0) {
        child_db_query_shard_prepared($shard_id,
            "INSERT INTO content_progress (user_id, catalog_item_id, status, recommended_by, recommended_at, started_at, completed_at, notes)
             VALUES (?, ?, ?, 'assessment', ?, ?, ?, ?)",
            [
                $user_id, $catalog_item_id, $new_status, $now,
                $new_status === 'started' ? $now : null,
                $new_status === 'completed' ? $now : null,
                $notes
            ]
        );
    }

    // Recompute userState after content progress change
    compute_user_state($user_id, $shard_id);
}

// ─── Content Sequence / Rules Loaders ───────────────────────────────────────

function get_content_sequence() {
    static $sequence = null;
    if ($sequence !== null) return $sequence;

    // Try framework file first
    $path = realpath(__DIR__ . '/../../../dswa-framework/framework/v5.0_contentSequence.json');
    if ($path && file_exists($path)) {
        $sequence = json_decode(file_get_contents($path), true) ?: [];
        return $sequence;
    }

    // Fallback to DB
    $raw = get_framework_data('content_sequence', 'content_sequence');
    $sequence = $raw ?: [];
    return $sequence;
}

function get_content_rules() {
    static $rules = null;
    if ($rules !== null) return $rules;

    $path = realpath(__DIR__ . '/../../../dswa-framework/framework/v5.0_contentRules.json');
    if ($path && file_exists($path)) {
        $rules = json_decode(file_get_contents($path), true) ?: [];
        return $rules;
    }

    $raw = get_framework_data('content_rules', 'content_rules');
    $rules = $raw ?: [];
    return $rules;
}

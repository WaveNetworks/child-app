<?php
/**
 * scoringEngine.php
 * Scoring engine (inherited from ElevateHER assessment framework).
 * Reads spec v2.3 format: responses as {value, answeredAt}, levels as beginner/developing/mastery.
 * Computes per-stage scores, placement, and hard wall checks.
 */

/**
 * Compute dimension score for a stage.
 * Handles both old bare-boolean and new {value, answeredAt} response formats.
 */
function compute_dimension_score($responses, $stage_items, $dimension) {
    $total_weight = 0;
    $achieved_weight = 0;

    foreach ($stage_items as $item) {
        if (($item['deprecated'] ?? false) === true) continue;
        if (($item['dimension'] ?? '') !== $dimension) continue;

        $weight = (float)($item['weight'] ?? 1.0);
        $total_weight += $weight;

        $item_id = $item['id'] ?? '';
        if (isset($responses[$item_id])) {
            $value = read_response_value($responses[$item_id]);
            if ($value === true) {
                $achieved_weight += $weight;
            }
        }
    }

    return $total_weight > 0 ? round($achieved_weight / $total_weight, 4) : 0;
}

/**
 * Compute overall score for a stage.
 */
function compute_overall_score($responses, $stage_items) {
    $total_weight = 0;
    $achieved_weight = 0;

    foreach ($stage_items as $item) {
        if (($item['deprecated'] ?? false) === true) continue;

        $weight = (float)($item['weight'] ?? 1.0);
        $total_weight += $weight;

        $item_id = $item['id'] ?? '';
        if (isset($responses[$item_id])) {
            $value = read_response_value($responses[$item_id]);
            if ($value === true) {
                $achieved_weight += $weight;
            }
        }
    }

    return $total_weight > 0 ? round($achieved_weight / $total_weight, 4) : 0;
}

/**
 * Check if a stage is complete (overall >= threshold, no nonNeg items false).
 */
function check_stage_complete($responses, $stage_items, $advance_threshold = 0.80) {
    $overall = compute_overall_score($responses, $stage_items);
    if ($overall < $advance_threshold) return false;

    foreach ($stage_items as $item) {
        if (($item['deprecated'] ?? false) === true) continue;
        if (($item['nonNeg'] ?? false) !== true) continue;

        $item_id = $item['id'] ?? '';
        if (isset($responses[$item_id])) {
            $value = read_response_value($responses[$item_id]);
            if ($value === false) return false;
        }
    }

    return true;
}

/**
 * Inference pass: retroactively resolve earlier-stage items based on later-stage answers.
 * Scans all items with `inferredBy` arrays. If a referenced later-stage item has been
 * resolved true (with sufficient confidence), the earlier item is inferred as true.
 * Direct answers always take precedence — inference only fills unresolved items.
 * Single-pass, no transitive chaining.
 *
 * @param array $responses     Current responses (modified in place)
 * @param array $responseMeta  Current response metadata (modified in place)
 * @return array ['responses' => [...], 'responseMeta' => [...], 'inferred_count' => int]
 */
function apply_inference_pass(&$responses, &$responseMeta) {
    $inferred_count = 0;

    for ($i = 0; $i <= 10; $i++) {
        $stage_data = get_stage($i);
        if (!$stage_data) continue;

        foreach (['mindset', 'toolset', 'skillset'] as $dim) {
            foreach ($stage_data[$dim] ?? [] as $item) {
                if (($item['deprecated'] ?? false) === true) continue;
                if (empty($item['inferredBy'])) continue;

                $item_id = $item['id'] ?? '';
                if (!$item_id) continue;

                // Direct answer exists — skip (direct always wins)
                if (isset($responses[$item_id])) {
                    $existing_value = read_response_value($responses[$item_id]);
                    // Only skip if it's a direct answer (not a previous inference)
                    $existing_source = $responseMeta[$item_id]['source'] ?? 'asked';
                    if ($existing_source === 'asked') continue;
                }

                // Find the best inference from all inferredBy entries
                $best_confidence = 0;
                $best_source_id = null;

                foreach ($item['inferredBy'] as $inf) {
                    $source_id = $inf['itemId'] ?? '';
                    $inf_confidence = (float)($inf['confidence'] ?? 0.7);

                    if (!$source_id || !isset($responses[$source_id])) continue;

                    $source_value = read_response_value($responses[$source_id]);
                    if ($source_value !== true) continue;

                    // Get the source item's own confidence
                    $source_conf = (float)($responseMeta[$source_id]['confidence'] ?? 1.0);
                    if ($source_conf < 0.60) continue; // Source not confident enough

                    // Effective confidence = inference multiplier × source confidence
                    $effective = $inf_confidence * $source_conf;

                    if ($effective > $best_confidence) {
                        $best_confidence = $effective;
                        $best_source_id = $source_id;
                    }
                }

                // Apply inference if we found a valid source
                if ($best_source_id && $best_confidence >= 0.60) {
                    $responses[$item_id] = [
                        'value' => true,
                        'answeredAt' => date('c'),
                    ];
                    $responseMeta[$item_id] = [
                        'source' => 'inferred',
                        'confidence' => round($best_confidence, 4),
                        'reasoning' => "Inferred from $best_source_id (confidence: " . round($best_confidence * 100) . "%)",
                        'inferredFrom' => $best_source_id,
                    ];
                    $inferred_count++;
                }
            }
        }
    }

    return [
        'responses' => $responses,
        'responseMeta' => $responseMeta,
        'inferred_count' => $inferred_count,
    ];
}

/**
 * Compute per-stage scores object matching the spec:
 * { "00": { overall, mindset, toolset, skillset, complete }, "01": {...}, ... }
 */
function compute_all_stage_scores($responses) {
    $scores = [];

    for ($i = 0; $i <= 10; $i++) {
        $stage_key = sprintf('%02d', $i);
        $stage_data = get_stage($i);
        if (!$stage_data) continue;

        $all_items = get_stage_diagnostic_items($stage_data);
        $threshold = (float)($stage_data['advanceThreshold'] ?? 0.80);

        $mindset = compute_dimension_score($responses, $all_items, 'mindset');
        $toolset = compute_dimension_score($responses, $all_items, 'toolset');
        $skillset = compute_dimension_score($responses, $all_items, 'skillset');
        $overall = compute_overall_score($responses, $all_items);
        $complete = check_stage_complete($responses, $all_items, $threshold);

        // Only include stages with any data
        $has_data = false;
        foreach ($all_items as $item) {
            if (isset($responses[$item['id'] ?? ''])) { $has_data = true; break; }
        }

        if ($has_data || $i === 0) {
            $scores[$stage_key] = [
                'overall' => $overall,
                'mindset' => $mindset,
                'toolset' => $toolset,
                'skillset' => $skillset,
                'complete' => $complete,
            ];
        }
    }

    return $scores;
}

/**
 * Get all diagnostic items from a stage as a flat array with dimension added.
 */
function get_stage_diagnostic_items($stage_data) {
    $items = [];
    foreach (['mindset', 'toolset', 'skillset'] as $dim) {
        foreach ($stage_data[$dim] ?? [] as $item) {
            $item['dimension'] = $dim;
            $items[] = $item;
        }
    }
    return $items;
}

/**
 * Check a hard wall — are all gating KPIs at developing or above?
 * Uses spec v2.3 levels: beginner < developing < mastery.
 */
function check_hard_wall($wall, $kpi_responses) {
    $gating_stages = $wall['gatingKpiStages'] ?? [];

    foreach ($gating_stages as $gs) {
        $stage = get_stage((int)$gs);
        if (!$stage) continue;

        foreach ($stage['kpis'] ?? [] as $kpi) {
            $kpi_id = $kpi['id'] ?? '';
            $current = $kpi_responses[$kpi_id] ?? null;
            if (!$current) return false;

            // Handle both old {level} and new {value, answeredAt} formats
            $current_level = is_array($current) ? ($current['value'] ?? $current['level'] ?? 'beginner') : $current;
            if (!meets_level_v23($current_level, 'developing')) {
                return false;
            }
        }
    }

    return true;
}

/**
 * Compute placement from instance data. Returns spec-format placement.
 * { placedStage, placedDate, assessedThrough, confidence }
 */
function compute_placement($instance_data) {
    $responses = (array)($instance_data['responses'] ?? []);
    $responseMeta = (array)($instance_data['responseMeta'] ?? []);
    $kpi_responses = (array)($instance_data['kpiResponses'] ?? []);
    $hard_walls = get_hard_walls();

    // Run inference pass before scoring — retroactively resolve earlier-stage items
    apply_inference_pass($responses, $responseMeta);

    $placed_stage = '00';
    $assessed_through = '00';
    $confidence = 'partial';

    for ($i = 0; $i <= 10; $i++) {
        $stage_key = sprintf('%02d', $i);
        $stage_data = get_stage($i);
        if (!$stage_data) continue;

        $all_items = get_stage_diagnostic_items($stage_data);

        // Track furthest stage with any responses
        $has_responses = false;
        foreach ($all_items as $item) {
            if (isset($responses[$item['id'] ?? ''])) { $has_responses = true; break; }
        }
        if ($has_responses) {
            $assessed_through = $stage_key;
        }

        // Check hard walls blocking advancement past this stage
        $wall_blocks = false;
        foreach ($hard_walls as $wall) {
            if (($wall['from'] ?? '') === $stage_key) {
                if (!check_hard_wall($wall, $kpi_responses)) {
                    $wall_blocks = true;
                    break;
                }
            }
        }

        $threshold = (float)($stage_data['advanceThreshold'] ?? 0.80);
        $complete = check_stage_complete($responses, $all_items, $threshold);

        if (!$complete || $wall_blocks) {
            $placed_stage = $stage_key;
            break;
        }

        // Stage complete and no wall — advance
        if ($i === 10) {
            $placed_stage = '10';
            $confidence = 'full';
        }
    }

    // Determine confidence
    $meta = (array)($instance_data['responseMeta'] ?? []);
    $low_conf_count = 0;
    $total_meta = 0;
    foreach ($meta as $m) {
        $total_meta++;
        if (($m['confidence'] ?? 1.0) < 0.75) $low_conf_count++;
    }
    if ($total_meta > 0 && $low_conf_count === 0) {
        $confidence = 'full';
    }

    return [
        'placedStage' => $placed_stage,
        'placedDate' => date('c'),
        'assessedThrough' => $assessed_through,
        'confidence' => $confidence,
    ];
}

/**
 * Compute hard wall status for all walls. Returns spec format.
 * { "wall-00-01": { detectedAt, currentlyBlocking, blockingKpis, crossedAt, verificationMethod } }
 */
function compute_hard_wall_status($kpi_responses) {
    $hard_walls = get_hard_walls();
    $status = [];

    foreach ($hard_walls as $wall) {
        $wall_id = $wall['id'] ?? '';
        $crossed = check_hard_wall($wall, $kpi_responses);
        $blocking_kpis = [];

        if (!$crossed) {
            $gating_stages = $wall['gatingKpiStages'] ?? [];
            foreach ($gating_stages as $gs) {
                $stage = get_stage((int)$gs);
                if (!$stage) continue;
                foreach ($stage['kpis'] ?? [] as $kpi) {
                    $kpi_id = $kpi['id'] ?? '';
                    $current = $kpi_responses[$kpi_id] ?? null;
                    $level = is_array($current) ? ($current['value'] ?? $current['level'] ?? 'beginner') : ($current ?: 'beginner');
                    if (!meets_level_v23($level, 'developing')) {
                        $blocking_kpis[] = $kpi_id;
                    }
                }
            }
        }

        $status[$wall_id] = [
            'detectedAt' => date('c'),
            'currentlyBlocking' => !$crossed,
            'blockingKpis' => $blocking_kpis,
            'crossedAt' => $crossed ? date('c') : null,
            'verificationMethod' => 'conversational',
        ];
    }

    return $status;
}

<?php
/**
 * questionMatrixHelpers.php
 * Question matrix selection, filtering, deterministic MC scoring, and tracking.
 * The question matrix DRIVES the assessment. AI is the backstop.
 */

function get_question_matrix_data() {
    static $matrix = null;
    if ($matrix !== null) return $matrix;
    $raw = get_framework_data('question_matrix', 'question_matrix');
    $matrix = $raw['questionMatrix'] ?? $raw ?? [];
    return $matrix;
}

/**
 * Load the profile question matrix.
 * Returns array of profile questions with pre-built MC choices.
 */
function get_profile_matrix_data() {
    static $matrix = null;
    if ($matrix !== null) return $matrix;
    $raw = get_framework_data('profile_matrix', 'profile_matrix');
    $matrix = $raw['profileMatrix'] ?? $raw ?? [];
    return $matrix;
}

/**
 * Pick the next profile question based on which fields are already collected.
 *
 * @param array $instance_data  The assessment instance data
 * @return array|null  Next profile question or null if all collected
 */
function pick_next_profile_question($instance_data) {
    $profile = $instance_data['subjectProfile'] ?? [];
    $who = $profile['who'] ?? [];
    $what = $profile['what'] ?? [];
    $why = $profile['why'] ?? [];
    $asked = $instance_data['askedQuestions'] ?? [];

    // Map profile fields to their sections for checking if already collected
    $collected = [];
    $field_sections = [
        'primaryMotivation' => $why,
        'lifeStage' => $who,
        'businessTenure' => $what,
        'engagementLevel' => $what,
        'teamSize' => $what,
    ];
    foreach ($field_sections as $field => $section) {
        if (!empty($section[$field])) $collected[] = $field;
    }

    $matrix = get_profile_matrix_data();
    if (empty($matrix)) return null;

    // Sort by priority
    usort($matrix, function($a, $b) {
        return ($a['priority'] ?? 99) - ($b['priority'] ?? 99);
    });

    // Find the first question whose field hasn't been collected and hasn't been asked
    foreach ($matrix as $q) {
        $field = $q['field'] ?? '';
        if (in_array($field, $collected)) continue;
        if (in_array($q['id'], $asked)) continue;
        // Add tier for compatibility with existing MC flow
        $q['tier'] = 'profile';
        $q['answerFormat'] = $q['answerFormat'] ?? 'multiple-choice';
        return $q;
    }

    return null;
}

/**
 * Resolve a profile MC choice deterministically.
 * Parses "field:value" format from the choice value and returns profile_hints.
 *
 * @param array  $question  The profile question
 * @param string $message   The user's message (choice value or label)
 * @return array  ['resolved' => bool, 'profile_hints' => [...], 'choice' => array|null]
 */
function resolve_profile_choice($question, $message) {
    $result = ['resolved' => false, 'profile_hints' => [], 'choice' => null];

    $choices = $question['choices'] ?? [];
    foreach ($choices as $c) {
        $val = $c['value'] ?? '';
        $label = $c['label'] ?? '';

        // Match by value or label
        if ($message === $val || $message === $label) {
            if ($val === 'free-form') {
                // User wants to type — not resolved, AI will handle
                return $result;
            }

            // Parse "field:value" format
            if (strpos($val, ':') !== false) {
                list($field, $field_value) = explode(':', $val, 2);
                $result['resolved'] = true;
                $result['profile_hints'] = [$field => $field_value];
                $result['choice'] = $c;
            }
            break;
        }
    }

    return $result;
}

function get_question_by_id($question_id) {
    $matrix = get_question_matrix_data();
    foreach ($matrix as $q) {
        if (($q['id'] ?? '') === $question_id) return $q;
    }
    return null;
}

function get_questions_for_phase($phase, $stage_band = null) {
    $matrix = get_question_matrix_data();
    if (empty($matrix)) return [];

    $tier_map = [
        'profile' => 'floor',
        'floor'   => 'floor',
        'ceiling' => 'ceiling',
        'zone'    => 'zone',
        'growth'  => 'growth',
    ];
    $tier = $tier_map[$phase] ?? $phase;

    $filtered = [];
    foreach ($matrix as $q) {
        if (($q['tier'] ?? '') !== $tier) continue;

        if ($tier === 'zone' && $stage_band !== null) {
            $target = $q['targetStages'] ?? [];
            if (!empty($target) && empty(array_intersect($target, (array)$stage_band))) continue;
        }

        // Growth tier: filter by stage if stage_band provided
        if ($tier === 'growth' && $stage_band !== null) {
            $q_stage = $q['stage'] ?? '';
            if ($q_stage && !in_array($q_stage, (array)$stage_band)) continue;
        }

        $filtered[] = $q;
    }

    usort($filtered, fn($a, $b) => ($a['priority'] ?? 99) - ($b['priority'] ?? 99));
    return $filtered;
}

function get_unresolved_items($instance_data) {
    $responses = (array)($instance_data['responses'] ?? []);
    $meta = (array)($instance_data['responseMeta'] ?? []);

    $all_items = [];
    for ($i = 0; $i <= 10; $i++) {
        $stage = get_stage($i);
        if (!$stage) continue;
        foreach (['mindset', 'toolset', 'skillset'] as $dim) {
            foreach ($stage[$dim] ?? [] as $item) {
                $id = $item['id'] ?? '';
                if (!$id) continue;
                $confidence = $meta[$id]['confidence'] ?? 0;
                if (!isset($responses[$id]) || $confidence < 0.60) {
                    $all_items[] = $id;
                }
            }
        }
    }
    return $all_items;
}

function pick_next_question($phase, $instance_data, $asked_questions = [], $suggested_stage = null) {
    $stage_band = null;
    if ($phase === 'zone') {
        $placement = $instance_data['placement'] ?? [];
        $current = $placement['placedStage'] ?? $placement['currentStage'] ?? '00';

        // If the AI suggested a different stage, use that as the center of the band
        // This is how the AI's assessment guides the server to the right area
        if ($suggested_stage !== null) {
            $ci = (int)$suggested_stage;
            error_log("ElevateHER: Using AI suggested_stage $suggested_stage to guide zone question selection (placed: $current)");
        } else {
            $ci = (int)$current;
        }

        $stage_band = [];
        for ($s = max(0, $ci - 1); $s <= min(10, $ci + 1); $s++) {
            $stage_band[] = sprintf('%02d', $s);
        }
    } elseif ($phase === 'floor' && $suggested_stage !== null) {
        // During floor phase, if AI suggests a high stage, we still use floor-tier questions
        // but the suggested_stage is logged for later use when we enter zone
        error_log("ElevateHER: AI suggested_stage $suggested_stage during floor phase — will influence zone entry");
    } elseif ($phase === 'ceiling' && $suggested_stage !== null) {
        error_log("ElevateHER: AI suggested_stage $suggested_stage during ceiling phase — narrowing bracket");
    }

    $questions = get_questions_for_phase($phase, $stage_band);
    $unresolved = get_unresolved_items($instance_data);

    $available = array_filter($questions, fn($q) => !in_array($q['id'] ?? '', $asked_questions));
    if (empty($available)) return null;

    $scored = [];
    foreach ($available as $q) {
        $coverage = 0;
        foreach ($q['resolvableItems'] ?? [] as $ri) {
            if (in_array($ri['itemId'] ?? '', $unresolved)) {
                $coverage++;
            }
        }
        foreach ($q['resolvableKpis'] ?? [] as $rk) {
            $kpi_id = $rk['kpiId'] ?? '';
            $kpi_responses = (array)($instance_data['kpiResponses'] ?? []);
            if (!isset($kpi_responses[$kpi_id])) $coverage++;
        }

        // Boost questions that target the suggested stage
        $stage_bonus = 0;
        if ($suggested_stage !== null) {
            $target_stages = $q['targetStages'] ?? [];
            if (in_array($suggested_stage, $target_stages)) {
                $stage_bonus = 3; // Significant boost for stage-relevant questions
            }
        }

        $scored[] = ['question' => $q, 'coverage' => $coverage + $stage_bonus, 'priority' => $q['priority'] ?? 99];
    }

    usort($scored, function($a, $b) {
        if ($b['coverage'] !== $a['coverage']) return $b['coverage'] - $a['coverage'];
        return $a['priority'] - $b['priority'];
    });

    return $scored[0]['question'] ?? null;
}

// ─── Deterministic MC Scoring ───────────────────────────────────────────────

/**
 * Score a multiple-choice answer deterministically from the framework data.
 * No AI needed — the signalHint + trueSignal/falseSignal tell us everything.
 *
 * @param array $question     Question object from the matrix
 * @param string $choice_id   The choice ID the user selected (e.g., 'a', 'b', 'c')
 * @return array {
 *   'resolved_items' => [ { itemId, value (bool), confidence } ],
 *   'resolved_kpis'  => [ { kpiId, level } ],
 *   'choice'         => the matched choice object,
 * }
 */
function resolve_mc_choice($question, $choice_id) {
    $choices = $question['choices'] ?? [];
    $resolvable_items = $question['resolvableItems'] ?? [];
    $resolvable_kpis = $question['resolvableKpis'] ?? [];

    // Find the selected choice
    $selected = null;
    foreach ($choices as $c) {
        if (($c['id'] ?? '') === $choice_id) {
            $selected = $c;
            break;
        }
    }

    // Also match by label text (user might have sent the label)
    if (!$selected) {
        foreach ($choices as $c) {
            if (strcasecmp($c['label'] ?? '', $choice_id) === 0) {
                $selected = $c;
                break;
            }
        }
    }

    if (!$selected) {
        return ['resolved_items' => [], 'resolved_kpis' => [], 'choice' => null];
    }

    $signal_hint = strtolower($selected['signalHint'] ?? '');

    // Determine signal direction from signalHint
    $is_positive = determine_signal_direction($signal_hint);

    // Resolve diagnostic items
    $resolved_items = [];
    foreach ($resolvable_items as $ri) {
        $item_id = $ri['itemId'] ?? '';
        if (!$item_id) continue;

        // The signalHint tells us the direction.
        // trueSignal/falseSignal on the item are rubrics for what true/false look like.
        // A positive signal means the user demonstrated the trueSignal behavior.
        $resolved_items[] = [
            'itemId' => $item_id,
            'value' => $is_positive,
            'confidence' => 1.0,  // MC choice = definitive
        ];
    }

    // Resolve KPIs
    $resolved_kpis = [];
    foreach ($resolvable_kpis as $rk) {
        $kpi_id = $rk['kpiId'] ?? '';
        if (!$kpi_id) continue;

        // Map signal direction to KPI level
        $level = determine_kpi_level($signal_hint, $rk);
        $resolved_kpis[] = [
            'kpiId' => $kpi_id,
            'level' => $level,
        ];
    }

    return [
        'resolved_items' => $resolved_items,
        'resolved_kpis' => $resolved_kpis,
        'choice' => $selected,
    ];
}

/**
 * Determine if a signalHint indicates a positive (true) or negative (false) direction.
 */
function determine_signal_direction($signal_hint) {
    $hint = strtolower($signal_hint);

    // Negative indicators
    $negative = ['false', 'low', 'negative', 'no ', 'never', 'rarely', 'avoids',
        'hasn\'t', 'not yet', 'beginner', 'none', 'struggle', 'fear',
        'uncomfortable', 'resist', 'avoid', 'weak', 'absent', 'lacks', 'can\'t',
        'doesn\'t', 'unable', 'minimal', 'limited'];

    foreach ($negative as $neg) {
        if (strpos($hint, $neg) !== false) return false;
    }

    // Positive indicators
    $positive = ['true', 'high', 'positive', 'yes', 'always', 'regularly', 'confident',
        'strong', 'mastery', 'developing', 'consistent', 'comfortable', 'capable',
        'demonstrates', 'has ', 'does', 'can ', 'practices', 'frequently'];

    foreach ($positive as $pos) {
        if (strpos($hint, $pos) !== false) return true;
    }

    // Default: treat as positive (user chose to answer)
    return true;
}

/**
 * Map a signalHint to a KPI level using the KPI's signal descriptions.
 */
function determine_kpi_level($signal_hint, $kpi_entry) {
    $hint = strtolower($signal_hint);
    $mastery_signal = strtolower($kpi_entry['masterySignal'] ?? '');
    $developing_signal = strtolower($kpi_entry['developingSignal'] ?? '');
    $beginner_signal = strtolower($kpi_entry['beginnerSignal'] ?? '');

    // Check for keywords that suggest mastery
    $mastery_words = ['mastery', 'expert', 'advanced', 'consistently', 'always',
        'strong', 'high', 'excellent', '3+', '5+', '7+', 'regularly'];
    foreach ($mastery_words as $w) {
        if (strpos($hint, $w) !== false) return 'mastery';
    }

    // Check for keywords that suggest beginner
    $beginner_words = ['beginner', 'none', 'never', 'zero', '0', 'hasn\'t',
        'avoids', 'no ', 'not yet', 'rarely', 'low'];
    foreach ($beginner_words as $w) {
        if (strpos($hint, $w) !== false) return 'beginner';
    }

    // Default to developing
    return 'developing';
}

// ─── Question Context for AI ────────────────────────────────────────────────

/**
 * Build the structured question context to send to AI.
 * Sends the actual question object data, not prose.
 */
function build_question_context($question) {
    if (!$question) return '';

    $qid = $question['id'] ?? '';
    $format = $question['answerFormat'] ?? 'free-form';

    $ctx = "═══ YOU MUST PRESENT THIS QUESTION ═══\n";
    $ctx .= "Question ID: $qid\n";
    $ctx .= "Question: " . ($question['prompt'] ?? '') . "\n";
    $ctx .= "Format: $format\n\n";

    if (($question['tier'] ?? '') === 'growth') {
        $ctx .= "TYPE: GROWTH (content routing). Present the choices below. System routes to content.\n";
        $ctx .= "Set question_id to \"$qid\" in your response.\n";
        $ctx .= "COPY the choices EXACTLY from below into your response choices array.\n\n";
    } elseif (!empty($question['choices'])) {
        $ctx .= "TYPE: MULTIPLE-CHOICE. System scores the selection deterministically.\n";
        $ctx .= "Set question_id to \"$qid\" in your response.\n";
        $ctx .= "COPY the choices EXACTLY from below into your response choices array.\n\n";
    } else {
        $ctx .= "TYPE: FREE-FORM. Score the user's answer against the resolvable items below.\n";
        $ctx .= "Set question_id to \"$qid\" in your response.\n";
        $ctx .= "IMPORTANT: After the user answers, the SERVER picks the next question. You just score this answer.\n";
        $ctx .= "Set suggested_stage to your best estimate of where this person is based on their answer.\n\n";

        // Extract item IDs for the prompt
        $items = $question['resolvableItems'] ?? [];
        if (!empty($items)) {
            $ctx .= "RESOLVABLE ITEMS — Score these from the user's answer using inferred_responses:\n";
            foreach ($items as $ri) {
                $ctx .= "  - " . ($ri['itemId'] ?? '') . ": true=\"" . ($ri['trueSignal'] ?? '') . "\" / false=\"" . ($ri['falseSignal'] ?? '') . "\"\n";
            }
            $ctx .= "\n";
        }

        $kpis = $question['resolvableKpis'] ?? [];
        if (!empty($kpis)) {
            $ctx .= "RESOLVABLE KPIs — Set levels in kpi_signals:\n";
            foreach ($kpis as $kpi) {
                $ctx .= "  - " . ($kpi['kpiId'] ?? '') . ": beginner=\"" . ($kpi['beginnerSignal'] ?? '') . "\" / mastery=\"" . ($kpi['masterySignal'] ?? '') . "\"\n";
            }
            $ctx .= "\n";
        }

        // Include branching rules if this question has them
        $branching = $question['branchingRules'] ?? [];
        if (!empty($branching)) {
            $ctx .= "BRANCHING RULES — Factor these into your suggested_stage:\n";
            foreach ($branching as $rule) {
                $ctx .= "  - IF: " . ($rule['condition'] ?? '') . " → " . ($rule['action'] ?? '') . " (" . ($rule['description'] ?? '') . ")\n";
            }
            $ctx .= "\n";
        }

        $ctx .= "GENERATE 3-4 choices that map to these items:\n";
        $ctx .= "  Choice 1: Strong match to trueSignals → resolves items to {value:true, confidence:0.85}\n";
        $ctx .= "  Choice 2: Partial/developing → resolves items to {value:true, confidence:0.6}\n";
        $ctx .= "  Choice 3: Matches falseSignals → resolves items to {value:false, confidence:0.8}\n";
        $ctx .= "  Choice 4: {label:\"I'd like to share more details\", value:\"free-form\", resolves:{}}\n\n";
    }

    // Include full question JSON for reference
    $ctx .= "FULL QUESTION DATA:\n";
    $ctx .= json_encode($question, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

    return $ctx;
}

function get_asked_questions_from_instance($instance_data) {
    return $instance_data['askedQuestions'] ?? [];
}

function check_assessment_completion($instance_data, $phase) {
    $responses = (array)($instance_data['responses'] ?? []);
    $meta = (array)($instance_data['responseMeta'] ?? []);
    $placement = $instance_data['placement'] ?? [];
    $current_stage = $placement['placedStage'] ?? $placement['currentStage'] ?? '00';

    $stage_data = get_stage((int)$current_stage);
    if (!$stage_data) return false;

    $all_items = get_stage_diagnostic_items($stage_data);
    $total = 0;
    $resolved = 0;

    foreach ($all_items as $item) {
        if ($item['deprecated'] ?? false) continue;
        $total++;
        $id = $item['id'] ?? '';
        if (isset($responses[$id]) && ($meta[$id]['confidence'] ?? 0) >= 0.60) {
            $resolved++;
        }
    }

    if ($phase === 'zone' && $total > 0 && ($resolved / $total) >= 0.80) {
        return true;
    }

    return false;
}

// ─── Growth Tier Support (Schema 2.4) ────────────────────────────────────────

/**
 * Check if a growth question should be activated.
 * Conditions per spec §12.5.9:
 *   1. A hard wall is currently blocking advancement
 *   2. All assessment-tier questions (floor/ceiling/zone) are exhausted for the wall's stage
 *   3. No prior growth response exists for this wall in this session
 *   4. User has consented to continue (implicit — they're still in the assessment)
 *
 * @param array $instance_data  Current instance data
 * @param array $asked_questions  Questions already asked
 * @return array|null  ['wall' => wall object, 'stage' => blocking stage] or null
 */
function check_growth_activation($instance_data, $asked_questions) {
    $hard_wall_status = $instance_data['hardWallStatus'] ?? [];
    $kpi_responses = (array)($instance_data['kpiResponses'] ?? []);
    $placement = $instance_data['placement'] ?? [];
    $current_stage = $placement['placedStage'] ?? $placement['currentStage'] ?? '00';

    // Find a blocking wall at the current stage
    $hard_walls = get_hard_walls();
    $blocking_wall = null;
    foreach ($hard_walls as $wall) {
        $wall_id = $wall['id'] ?? '';
        $from_stage = $wall['from'] ?? '';

        // Wall must block FROM the current placed stage
        if ($from_stage !== $current_stage) continue;

        // Check if wall is currently blocking
        $ws = $hard_wall_status[$wall_id] ?? [];
        if (!empty($ws['currentlyBlocking'])) {
            $blocking_wall = $wall;
            break;
        }

        // Also check via compute if status not yet set
        if (!check_hard_wall($wall, $kpi_responses)) {
            $blocking_wall = $wall;
            break;
        }
    }

    if (!$blocking_wall) return null;

    // Check that assessment questions (floor/ceiling/zone) are exhausted for this stage
    $stage_band = [$current_stage];
    foreach (['floor', 'ceiling', 'zone'] as $assess_phase) {
        $phase_questions = get_questions_for_phase($assess_phase, $stage_band);
        foreach ($phase_questions as $q) {
            if (!in_array($q['id'] ?? '', $asked_questions)) {
                // Still have assessment questions available — don't activate growth yet
                return null;
            }
        }
    }

    // Check that growth questions haven't already been fully asked for this stage
    $growth_questions = get_questions_for_phase('growth', [$current_stage]);
    $has_unasked_growth = false;
    foreach ($growth_questions as $q) {
        if (!in_array($q['id'] ?? '', $asked_questions)) {
            $has_unasked_growth = true;
            break;
        }
    }

    if (!$has_unasked_growth) return null;

    return [
        'wall' => $blocking_wall,
        'stage' => $current_stage,
    ];
}

/**
 * Pick the next growth question for the given stage.
 *
 * @param string $stage  Stage key (e.g., '00', '03')
 * @param array $asked_questions  Already asked question IDs
 * @return array|null  Question object or null
 */
function pick_growth_question($stage, $asked_questions = []) {
    $questions = get_questions_for_phase('growth', [$stage]);
    foreach ($questions as $q) {
        if (!in_array($q['id'] ?? '', $asked_questions)) {
            return $q;
        }
    }
    return null;
}

/**
 * Resolve a growth-tier multiple-choice answer.
 * Growth choices use a different schema than assessment choices:
 *   - Growth: {label, value, dimension, contentIds}
 *   - Assessment: {id, label, signalHint, followUp}
 *
 * Growth questions do NOT resolve diagnostic items or KPIs — they route to content.
 *
 * @param array $question   Growth question from the matrix
 * @param string $choice_input  User's choice (value string or label text)
 * @return array {
 *   'choice'      => matched choice object (or null),
 *   'dimension'   => selected dimension (mindset/toolset/skillset/all),
 *   'content_ids' => array of content catalog IDs to surface,
 *   'stage'       => the growth question's stage,
 * }
 */
function resolve_growth_choice($question, $choice_input) {
    $choices = $question['choices'] ?? [];
    $stage = $question['stage'] ?? '';

    // Find the selected choice — match by value first, then by label
    $selected = null;
    foreach ($choices as $c) {
        if (($c['value'] ?? '') === $choice_input) {
            $selected = $c;
            break;
        }
    }
    if (!$selected) {
        foreach ($choices as $c) {
            if (strcasecmp($c['label'] ?? '', $choice_input) === 0) {
                $selected = $c;
                break;
            }
        }
    }

    if (!$selected) {
        return ['choice' => null, 'dimension' => null, 'content_ids' => [], 'stage' => $stage];
    }

    return [
        'choice' => $selected,
        'dimension' => $selected['dimension'] ?? null,
        'content_ids' => $selected['contentIds'] ?? [],
        'stage' => $stage,
    ];
}

/**
 * Resolve content catalog items from an array of content IDs.
 * Returns enriched catalog items ready for the frontend.
 *
 * @param array $content_ids  Array of catalog item IDs
 * @return array  Array of catalog item objects with thumbnails
 */
function get_content_by_ids($content_ids) {
    if (empty($content_ids)) return [];

    $catalog = get_content_catalog();
    $items = isset($catalog['contentCatalog']) ? $catalog['contentCatalog']
        : (isset($catalog['items']) ? $catalog['items'] : ($catalog ?: []));

    $results = [];
    foreach ($items as $item) {
        $id = $item['id'] ?? '';
        if (in_array($id, $content_ids)) {
            $item['thumbnail'] = get_content_thumbnail_url($item);
            $item['reason'] = 'Recommended for your growth path';
            $results[] = $item;
        }
    }

    return $results;
}

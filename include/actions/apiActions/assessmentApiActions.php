<?php
/**
 * assessmentApiActions.php
 * API actions for assessment chat and state retrieval.
 * Question matrix drives the UX. AI is the backstop.
 * MC choices scored deterministically. Instance files follow spec v2.4.
 * Growth-tier questions route to content (schema 2.4).
 */

// ─── AI Assessment Chat ─────────────────────────────────────────────────────

if (($_POST['action'] ?? '') === 'assessment_chat') {
    $errs = [];
    $uid = $_SESSION['user_id'] ?? 0;
    $sid = $_SESSION['shard_id'] ?? '';
    $session_id = trim($_POST['session_id'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (!$uid) { $errs['auth'] = 'Login required.'; }
    if (!$session_id) { $errs['session'] = 'Session ID required.'; }
    if (!$message) { $errs['message'] = 'Message required.'; }

    if (count($errs) <= 0) {
        global $ai_config;

        $session = get_session_by_id($session_id, $sid);
        if (!$session || $session['user_id'] != $uid) {
            $_SESSION['error'] = 'Session not found.';
        } else {
            // Load instance from filesystem (falls back to DB)
            $inst_data = load_instance_file($uid, $sid, 'ai-assessment');
            if (!$inst_data) {
                $inst_data = create_instance_v23($uid, $sid, 'ai-assessment');
            }

            $conv_log = $session['conversation_log'] ? json_decode($session['conversation_log'], true) : [];
            $display_label = trim($_POST['display_label'] ?? '');
            // Only add visible user messages (not internal commands)
            if (!in_array($message, ['[start]', '[resume]', '[explorer-reflections]']) && strpos($message, '[explorer-reflections]') !== 0) {
                $conv_log[] = ['role' => 'user', 'content' => $message, 'display' => $display_label ?: $message, 'ts' => date('c')];
            } else {
                $conv_log[] = ['role' => 'user', 'content' => $message];
            }

            // ── Step 1: Deterministic MC Scoring ──
            // If the user clicked a MC choice, score it from the framework BEFORE calling AI.
            $inferred_items = [];
            $mc_resolved = false;
            $profile_resolved = false;
            $profile_question_text = null;
            $growth_resolved = false;
            $growth_content_ids = [];
            $growth_dimension = null;
            $ai_suggested_stage = null;
            $asked = get_asked_questions_from_instance($inst_data);

            // Profile phase: use profile matrix (no AI needed for MC choices)
            if ($session['phase'] === 'profile') {
                $cur_q = pick_next_profile_question($inst_data);
            } else {
                $cur_q = pick_next_question($session['phase'], $inst_data, $asked);
            }

            // In growth phase, pick the growth question instead
            if ($session['phase'] === 'growth' && !$cur_q) {
                $placement = $inst_data['placement'] ?? [];
                $growth_stage = $placement['placedStage'] ?? $placement['currentStage'] ?? '00';
                $cur_q = pick_growth_question($growth_stage, $asked);
            }

            // ── Step 1a: Profile MC resolution (completely deterministic, zero AI) ──
            if ($cur_q && ($cur_q['tier'] ?? '') === 'profile' && !in_array($message, ['[start]', '[resume]'])) {
                $profile_result = resolve_profile_choice($cur_q, $message);
                if ($profile_result['resolved']) {
                    $profile_resolved = true;
                    $mc_resolved = true;

                    // Apply profile hints
                    foreach ($profile_result['profile_hints'] as $key => $value) {
                        set_profile_field($inst_data['subjectProfile'], $key, $value);
                    }

                    // Mark question as asked
                    if (!isset($inst_data['askedQuestions'])) $inst_data['askedQuestions'] = [];
                    if (!in_array($cur_q['id'], $inst_data['askedQuestions'])) {
                        $inst_data['askedQuestions'][] = $cur_q['id'];
                    }
                }
            }

            if ($cur_q && !empty($cur_q['choices'])) {
                $format = $cur_q['answerFormat'] ?? 'free-form';
                $is_growth = ($cur_q['tier'] ?? '') === 'growth';

                if ($is_growth) {
                    // ── Growth MC: content routing, not diagnostic scoring ──
                    $growth_result = resolve_growth_choice($cur_q, $message);

                    if ($growth_result['choice']) {
                        $growth_resolved = true;
                        $mc_resolved = true;
                        $growth_content_ids = $growth_result['content_ids'];
                        $growth_dimension = $growth_result['dimension'];

                        // Record the growth response in instance data
                        if (!isset($inst_data['growthResponses'])) $inst_data['growthResponses'] = [];
                        $inst_data['growthResponses'][] = [
                            'questionId' => $cur_q['id'],
                            'stage' => $growth_result['stage'],
                            'choiceValue' => $growth_result['choice']['value'] ?? '',
                            'dimension' => $growth_dimension,
                            'contentIds' => $growth_content_ids,
                            'answeredAt' => date('c'),
                        ];

                        // Track asked question
                        if (!isset($inst_data['askedQuestions'])) $inst_data['askedQuestions'] = [];
                        if (!in_array($cur_q['id'], $inst_data['askedQuestions'])) {
                            $inst_data['askedQuestions'][] = $cur_q['id'];
                        }

                        error_log("ElevateHER: Growth choice resolved — Q: {$cur_q['id']}, dimension: $growth_dimension, content: " . implode(', ', $growth_content_ids));
                    }
                } elseif (in_array($format, ['multiple-choice', 'hybrid'])) {
                    // ── Assessment MC: diagnostic scoring ──
                    $mc_result = resolve_mc_choice($cur_q, $message);

                    if ($mc_result['choice']) {
                        $mc_resolved = true;
                        $now = date('c');

                        // Record resolved diagnostic items
                        foreach ($mc_result['resolved_items'] as $ri) {
                            $item_id = $ri['itemId'];
                            $inst_data['responses'][$item_id] = make_response($ri['value'], $now);
                            $inst_data['responseMeta'][$item_id] = make_response_meta(
                                'asked', $ri['confidence'],
                                'MC choice: ' . ($mc_result['choice']['label'] ?? $message)
                            );

                            // Collect for petal animation
                            $item_meta = find_item_stage_dimension($item_id);
                            if ($item_meta) {
                                $inferred_items[] = [
                                    'id' => $item_id,
                                    'stage' => $item_meta['stage'],
                                    'dimension' => $item_meta['dimension'],
                                    'progress' => $ri['confidence'],
                                ];
                            }
                        }

                        // Record resolved KPIs
                        foreach ($mc_result['resolved_kpis'] as $rk) {
                            $inst_data['kpiResponses'][$rk['kpiId']] = make_kpi_response($rk['level'], $now);
                        }

                        // Track asked question
                        if (!isset($inst_data['askedQuestions'])) $inst_data['askedQuestions'] = [];
                        if (!in_array($cur_q['id'], $inst_data['askedQuestions'])) {
                            $inst_data['askedQuestions'][] = $cur_q['id'];
                        }
                    }
                }
            }

            // ── Step 1b: AI-generated choice resolution ──
            // If the user clicked an AI-generated choice that has a 'resolves' map,
            // apply those resolutions deterministically (same as MC scoring).
            if (!$mc_resolved && !empty($conv_log)) {
                // Find the last bot message with choices
                $last_bot_choices = null;
                for ($ci = count($conv_log) - 1; $ci >= 0; $ci--) {
                    if (($conv_log[$ci]['role'] ?? '') === 'assistant' && !empty($conv_log[$ci]['choices'])) {
                        $last_bot_choices = $conv_log[$ci]['choices'];
                        break;
                    }
                }
                if ($last_bot_choices) {
                    foreach ($last_bot_choices as $ch) {
                        $ch_val = $ch['value'] ?? '';
                        $ch_label = $ch['label'] ?? '';
                        // Match by value or label
                        if ($ch_val && ($message === $ch_val || $message === $ch_label) && !empty($ch['resolves']) && $ch_val !== 'free-form') {
                            $now = date('c');
                            foreach ($ch['resolves'] as $item_id => $resolution) {
                                $res_value = (bool)($resolution['value'] ?? false);
                                $res_conf = (float)($resolution['confidence'] ?? 0.6);
                                $inst_data['responses'][$item_id] = make_response($res_value, $now);
                                $inst_data['responseMeta'][$item_id] = make_response_meta(
                                    'asked', $res_conf,
                                    'AI choice: ' . $ch_label
                                );

                                $item_meta = find_item_stage_dimension($item_id);
                                if ($item_meta) {
                                    $inferred_items[] = [
                                        'id' => $item_id,
                                        'stage' => $item_meta['stage'],
                                        'dimension' => $item_meta['dimension'],
                                        'progress' => $res_conf,
                                    ];
                                }
                            }
                            $mc_resolved = true;

                            // Mark the question as asked
                            if ($cur_q) {
                                if (!isset($inst_data['askedQuestions'])) $inst_data['askedQuestions'] = [];
                                if (!in_array($cur_q['id'], $inst_data['askedQuestions'])) {
                                    $inst_data['askedQuestions'][] = $cur_q['id'];
                                }
                            }
                            break;
                        }
                    }
                }
            }

            // ── Step 2: Determine next question and decide if AI is needed ──
            // Recompute scores/placement after any MC scoring so next question pick is accurate
            if ($mc_resolved) {
                $_meta = (array)($inst_data['responseMeta'] ?? []);
                $_inf = apply_inference_pass($inst_data['responses'], $_meta);
                $inst_data['responses'] = $_inf['responses'];
                $inst_data['responseMeta'] = $_inf['responseMeta'];
                $inst_data['scores'] = compute_all_stage_scores($inst_data['responses']);
                $inst_data['placement'] = compute_placement($inst_data);
                $inst_data['hardWallStatus'] = compute_hard_wall_status($inst_data['kpiResponses']);
            }

            $next_asked = get_asked_questions_from_instance($inst_data);
            $cur_phase = $session['phase'];

            // Check phase advancement
            $is_complete = check_assessment_completion($inst_data, $cur_phase);
            $new_phase_check = $cur_phase;
            if ($cur_phase === 'profile') {
                $who = $inst_data['subjectProfile']['who'] ?? [];
                $what = $inst_data['subjectProfile']['what'] ?? [];
                $why = $inst_data['subjectProfile']['why'] ?? [];
                $profile_fields = 0;
                foreach (['businessTenure' => $what, 'engagementLevel' => $what, 'primaryMotivation' => $why, 'lifeStage' => $who, 'teamSize' => $what] as $f => $s) {
                    if (!empty($s[$f])) $profile_fields++;
                }
                if ($profile_fields >= 3) $new_phase_check = 'floor';
            } elseif ($cur_phase !== 'score' && $cur_phase !== 'growth') {
                // Check if current phase questions are exhausted → advance
                $phase_q = pick_next_question($cur_phase, $inst_data, $next_asked);
                if (!$phase_q && !$is_complete) {
                    $phase_order = ['profile', 'floor', 'ceiling', 'zone'];
                    $idx = array_search($cur_phase, $phase_order);
                    if ($idx !== false && $idx < count($phase_order) - 1) {
                        $new_phase_check = $phase_order[$idx + 1];
                    }
                }
            }

            // Pick the next question in the (possibly new) phase
            if ($new_phase_check === 'growth') {
                $growth_stage = $inst_data['placement']['placedStage'] ?? $inst_data['placement']['currentStage'] ?? '00';
                $next_q = pick_growth_question($growth_stage, $next_asked);
            } elseif ($new_phase_check === 'profile') {
                $next_q = pick_next_profile_question($inst_data);
            } else {
                $next_q = pick_next_question($new_phase_check, $inst_data, $next_asked);
            }

            // If next question has choices (MC/hybrid/growth/profile), serve directly — NO AI call needed
            $skip_ai = false;
            $ai_response = null;
            $system_prompt = null;
            $raw_ai_content = null;
            if ($next_q && !empty($next_q['choices'])) {
                $nq_format = $next_q['answerFormat'] ?? 'free-form';
                $nq_tier = $next_q['tier'] ?? '';
                if ($nq_tier === 'growth' || $nq_tier === 'profile' || in_array($nq_format, ['multiple-choice', 'hybrid'])) {
                    $skip_ai = true;
                    // Build direct response — no AI needed
                    $is_growth_q = ($nq_tier === 'growth');
                    $is_profile_q = ($nq_tier === 'profile');
                    $direct_choices = array_map(function($c) use ($is_growth_q, $is_profile_q) {
                        return [
                            'label' => $c['label'] ?? '',
                            'value' => ($is_growth_q || $is_profile_q) ? ($c['value'] ?? $c['label'] ?? '') : ($c['id'] ?? $c['label'] ?? ''),
                        ];
                    }, $next_q['choices']);

                    // Build display message: warm acknowledgment + next question prompt
                    $prompt_text = $next_q['prompt'] ?? 'What do you think?';
                    if ($is_profile_q && $message === '[start]') {
                        // First message greeting — zero AI cost
                        $subject_name = ($inst_data['subject']['name'] ?? '') ?: 'there';
                        $prompt_text = "Hi $subject_name! I'm so excited to connect with you today. I'm here to help you discover exactly where you are in your business journey and create a personalized roadmap for your next steps.<br><br>Before we dive into the assessment, I'd love to get to know you a little better.";
                        // Question goes in a separate bubble
                        $profile_question_text = $next_q['prompt'] ?? '';
                    } elseif ($profile_resolved) {
                        // Canned warm responses for profile MC — zero AI cost
                        $warm_acks = [
                            "That's great to know!",
                            "Thanks for sharing that!",
                            "Love it — that helps me understand you better!",
                            "Wonderful, I appreciate you telling me!",
                            "Got it — that's really helpful!",
                        ];
                        $prompt_text = $warm_acks[array_rand($warm_acks)];
                        // Question goes in a separate bubble
                        $profile_question_text = $next_q['prompt'] ?? '';
                    }
                    $parsed = [
                        'display_message' => $prompt_text,
                        'inferred_responses' => [],
                        'kpi_signals' => [],
                        'profile_hints' => [],
                        'session_notes' => [],
                        'phase_signal' => 'continue',
                        'choices' => $direct_choices,
                        'question_id' => $next_q['id'],
                    ];
                }
            }

            // ── Skip AI for MC-resolved transitions: serve next question or completion directly ──
            if (!$skip_ai && $mc_resolved) {
                $skip_ai = true;
                $warm_acks = [
                    "That's so valuable — having clear goals keeps you focused on what really matters!",
                    "That tells me a lot about where you are — let's keep exploring!",
                    "Great insight — that really helps me understand your journey!",
                    "I appreciate you sharing that — it paints a clearer picture!",
                    "That's really helpful context — let's dig a little deeper!",
                ];

                if ($next_q) {
                    // If next question has its own choices (MC/profile/growth), use them directly
                    $nq_direct_choices = null;
                    if (!empty($next_q['choices'])) {
                        $nq_tier = $next_q['tier'] ?? '';
                        $is_nq_growth = ($nq_tier === 'growth');
                        $is_nq_profile = ($nq_tier === 'profile');
                        $nq_direct_choices = array_map(function($c) use ($is_nq_growth, $is_nq_profile) {
                            return [
                                'label' => $c['label'] ?? '',
                                'value' => ($is_nq_growth || $is_nq_profile) ? ($c['value'] ?? $c['label'] ?? '') : ($c['id'] ?? $c['label'] ?? ''),
                            ];
                        }, $next_q['choices']);
                    }

                    // Next question exists — serve it directly
                    $profile_question_text = $next_q['prompt'] ?? '';
                    $parsed = [
                        'display_message' => $warm_acks[array_rand($warm_acks)],
                        'inferred_responses' => [],
                        'kpi_signals' => [],
                        'profile_hints' => [],
                        'session_notes' => [],
                        'phase_signal' => 'continue',
                        'choices' => $nq_direct_choices,
                        'question_id' => $next_q['id'],
                        'question_text' => $next_q['prompt'] ?? null,
                    ];
                } elseif ($is_complete || $new_phase_check === 'score') {
                    // No more questions and assessment is complete — finish without AI
                    $subject_name = ($inst_data['subject']['name'] ?? '') ?: 'there';
                    $parsed = [
                        'display_message' => "Wonderful work, $subject_name! I now have a clear picture of where you are in your journey. Let me put together your personalized results!",
                        'inferred_responses' => [],
                        'kpi_signals' => [],
                        'profile_hints' => [],
                        'session_notes' => [],
                        'phase_signal' => 'session_complete',
                        'choices' => null,
                        'question_id' => null,
                    ];
                } else {
                    // Phase exhausted but not complete — advance phase without AI
                    $parsed = [
                        'display_message' => $warm_acks[array_rand($warm_acks)],
                        'inferred_responses' => [],
                        'kpi_signals' => [],
                        'profile_hints' => [],
                        'session_notes' => [],
                        'phase_signal' => 'advance_phase',
                        'choices' => null,
                        'question_id' => null,
                    ];
                }
            }

            // Only call AI when needed: free-form questions, profile phase, or no matrix question
            if (!$skip_ai) {
                $system_prompt = build_assessment_system_prompt($inst_data, $session);
                $tool_schema = get_assessment_response_schema();

                // Trim conversation log to last 10 exchanges to reduce input tokens
                $trimmed_log = $conv_log;
                if (count($trimmed_log) > 20) {
                    $trimmed_log = array_slice($conv_log, -20);
                }

                $ai_response = call_ai_api($system_prompt, $trimmed_log, $ai_config, $tool_schema);

                $parsed = null;
                if ($ai_response && isset($ai_response['content'])) {
                    $parsed = parse_ai_response($ai_response['content'], $ai_response['structured'] ?? null);
                    $raw_ai_content = $ai_response['content'];
                }

                // If AI failed, provide a fallback
                if (!$parsed || empty($parsed['display_message'])) {
                    if ($mc_resolved || $next_q) {
                        $parsed = [
                            'display_message' => $next_q ? ($next_q['prompt'] ?? "Let's continue...") : "Got it! Let me think about what to explore next...",
                            'inferred_responses' => [],
                            'kpi_signals' => [],
                            'profile_hints' => [],
                            'session_notes' => [],
                            'phase_signal' => 'continue',
                            'choices' => null,
                            'question_id' => $next_q['id'] ?? ($cur_q['id'] ?? null),
                        ];
                    } else {
                        $_SESSION['error'] = 'AI service unavailable. Please try again.';
                        save_instance_file($uid, $sid, $inst_data, 'ai-assessment');
                        goto end_chat;
                    }
                }
            }

            // ── Step 2b: Server-side scaffold choices for free-form questions ──
            // When the response has no choices AND the question is free-form with
            // resolvableItems, generate contextual scaffold choices server-side.
            // This fixes: resume/reload missing MC, AI non-compliance, MC→free-form transitions.
            // Only scaffold when a question is actually being presented to the user.
            $has_question = !empty($parsed['question_text']) || !empty($profile_question_text);
            if ($parsed && empty($parsed['choices']) && $has_question) {
                $scaffold_q = $next_q; // the question being presented
                if ($scaffold_q && ($scaffold_q['answerFormat'] ?? '') === 'free-form' && !empty($scaffold_q['resolvableItems'])) {
                    $ri_items = $scaffold_q['resolvableItems'];

                    // Build resolves maps for each strength level
                    $resolves_strong_true = [];
                    $resolves_partial_true = [];
                    $resolves_partial_false = [];
                    $resolves_strong_false = [];
                    foreach ($ri_items as $ri) {
                        $iid = $ri['itemId'] ?? '';
                        if (!$iid) continue;
                        $resolves_strong_true[$iid]  = ['value' => true,  'confidence' => 0.85];
                        $resolves_partial_true[$iid]  = ['value' => true,  'confidence' => 0.6];
                        $resolves_partial_false[$iid] = ['value' => false, 'confidence' => 0.6];
                        $resolves_strong_false[$iid]  = ['value' => false, 'confidence' => 0.85];
                    }

                    // Build contextual labels from the first resolvable item's signals
                    $first_ri = $ri_items[0] ?? [];
                    $true_sig = $first_ri['trueSignal'] ?? '';
                    $false_sig = $first_ri['falseSignal'] ?? '';

                    // Extract a short contextual phrase from the signals for better labels
                    $strong_label = 'I have a solid routine and structure in place';
                    $partial_label = 'I\'m building habits but not fully consistent yet';
                    $weak_label = 'I\'m still figuring out my approach';
                    $struggle_label = 'I haven\'t really started yet';

                    // Use signal text to generate more relevant labels when available
                    if ($true_sig && $false_sig) {
                        // Truncate signals to ~60 chars for readable button labels
                        $strong_label = mb_strlen($true_sig) > 70
                            ? mb_substr($true_sig, 0, 67) . '...'
                            : $true_sig;
                        $struggle_label = mb_strlen($false_sig) > 70
                            ? mb_substr($false_sig, 0, 67) . '...'
                            : $false_sig;
                    }

                    $parsed['choices'] = [
                        ['label' => $strong_label,                                   'value' => 'strong-true',   'resolves' => $resolves_strong_true],
                        ['label' => $partial_label,                                  'value' => 'partial-true',  'resolves' => $resolves_partial_true],
                        ['label' => $weak_label,                                     'value' => 'partial-false', 'resolves' => $resolves_partial_false],
                        ['label' => $struggle_label,                                 'value' => 'strong-false',  'resolves' => $resolves_strong_false],
                        ['label' => 'I\'d like to share more details',               'value' => 'free-form',     'resolves' => []],
                    ];
                    error_log("ElevateHER: Server-side scaffold choices generated for free-form question {$scaffold_q['id']} (AI didn't provide choices)");
                }
            }

            // ── Step 3: Process AI inferences (free-form only) ──
            // Only apply AI inferences for items NOT already resolved by MC
            if (!$mc_resolved && !empty($parsed['inferred_responses'])) {
                $now = date('c');
                foreach ($parsed['inferred_responses'] as $item_id => $value) {
                    $existing_conf = $inst_data['responseMeta'][$item_id]['confidence'] ?? 0;
                    $new_conf = $value['confidence'] ?? 0.7;
                    if ($new_conf > $existing_conf) {
                        $inst_data['responses'][$item_id] = make_response($value['value'], $now);
                        $inst_data['responseMeta'][$item_id] = make_response_meta(
                            'inferred', $new_conf,
                            $value['reasoning'] ?? null
                        );

                        $item_meta = find_item_stage_dimension($item_id);
                        if ($item_meta) {
                            $inferred_items[] = [
                                'id' => $item_id,
                                'stage' => $item_meta['stage'],
                                'dimension' => $item_meta['dimension'],
                                'progress' => $new_conf,
                            ];
                        }
                    }
                }
            }

            // ── Step 4: Process profile hints (structured who/what/why) ──
            if (!empty($parsed['profile_hints'])) {
                foreach ($parsed['profile_hints'] as $key => $value) {
                    set_profile_field($inst_data['subjectProfile'], $key, $value);
                }
            }

            // ── Step 5: Process KPI signals from AI (free-form only) ──
            if (!$mc_resolved && !empty($parsed['kpi_signals'])) {
                $now = date('c');
                foreach ($parsed['kpi_signals'] as $kpi_id => $kpi_data) {
                    $level = $kpi_data['level'] ?? 'developing';
                    $inst_data['kpiResponses'][$kpi_id] = make_kpi_response($level, $now);
                }
            }

            // ── Step 6: Process session notes ──
            if (!empty($parsed['session_notes'])) {
                foreach ($parsed['session_notes'] as $note) {
                    $inst_data['sessionNotes'][] = [
                        'noteId' => 'sn-' . $session_id . '-' . count($inst_data['sessionNotes']),
                        'sessionId' => $session_id,
                        'sessionDate' => date('c'),
                        'assessmentType' => 'ai',
                        'stageRef' => $note['stage'] ?? null,
                        'dimension' => $note['dimension'] ?? null,
                        'relatedItems' => [],
                        'relatedKpis' => [],
                        'observation' => $note['observation'] ?? '',
                        'focus' => $note['focus'] ?? null,
                        'surfaceInCanvas' => $note['surfaceInCanvas'] ?? false,
                        'canvasLabel' => $note['canvasLabel'] ?? null,
                    ];

                    // Also save to DB for querying
                    child_db_query_shard_prepared($sid,
                        "INSERT INTO session_notes (user_id, session_id, stage, dimension, observation, focus, canvas_label, surface_in_canvas)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                        [
                            $uid, $session_id,
                            $note['stage'] ?? null,
                            $note['dimension'] ?? null,
                            $note['observation'] ?? '',
                            $note['focus'] ?? null,
                            $note['canvasLabel'] ? mb_substr($note['canvasLabel'], 0, 255) : null,
                            (int)($note['surfaceInCanvas'] ?? 0),
                        ]
                    );
                }
            }

            // ── Step 7: Track asked question ──
            // Mark the current question as asked — both from AI's question_id AND the
            // server-side cur_q. This ensures free-form questions get marked as asked
            // immediately after the AI scores the answer, so the server always advances.
            if (!isset($inst_data['askedQuestions'])) $inst_data['askedQuestions'] = [];

            // Mark from AI response
            if (!empty($parsed['question_id'])) {
                if (!in_array($parsed['question_id'], $inst_data['askedQuestions'])) {
                    $inst_data['askedQuestions'][] = $parsed['question_id'];
                }
            }

            // Also mark the server-side current question (catches cases where AI
            // doesn't report question_id, e.g., free-form answers that went off-topic)
            if ($cur_q && !in_array($cur_q['id'], $inst_data['askedQuestions'])) {
                $inst_data['askedQuestions'][] = $cur_q['id'];
                error_log("ElevateHER: Marked cur_q {$cur_q['id']} as asked (server-side enforcement)");
            }

            // ── Step 7b: Process suggested_stage from AI ──
            $ai_suggested_stage = $parsed['suggested_stage'] ?? null;
            if ($ai_suggested_stage !== null) {
                // Validate it's a valid stage string
                $ai_suggested_stage = sprintf('%02d', min(10, max(0, (int)$ai_suggested_stage)));

                // Store in placement for use by pick_next_question
                if (!isset($inst_data['placement'])) $inst_data['placement'] = [];
                $inst_data['placement']['aiSuggestedStage'] = $ai_suggested_stage;
                error_log("ElevateHER: AI suggested_stage = $ai_suggested_stage (current placed: " . ($inst_data['placement']['placedStage'] ?? 'none') . ")");
            }

            // ── Step 8: Handle phase advancement ──
            $new_phase = $session['phase'];
            if (($parsed['phase_signal'] ?? '') === 'advance_phase') {
                $phase_order = ['profile' => 'floor', 'floor' => 'ceiling', 'ceiling' => 'zone', 'zone' => 'score', 'growth' => 'score'];
                $new_phase = $phase_order[$session['phase']] ?? $session['phase'];
                if ($session['phase'] === 'profile') {
                    $inst_data['subjectProfile']['who']['completedAt'] = date('c');
                }
            } elseif (($parsed['phase_signal'] ?? '') === 'session_complete') {
                $new_phase = 'score';
            }

            // Growth phase auto-completion: after a growth choice is resolved, transition to score
            if ($session['phase'] === 'growth' && $growth_resolved) {
                $new_phase = 'score';
                error_log("ElevateHER: Growth choice resolved — transitioning from growth to score");
            }

            // Server-side enforcement: if no more questions remain for this phase, advance regardless of AI signal
            if ($new_phase === $session['phase']) {
                $enforce_asked = get_asked_questions_from_instance($inst_data);
                $enforce_next = pick_next_question($session['phase'], $inst_data, $enforce_asked, $ai_suggested_stage ?? null);

                // For growth phase, also check growth questions specifically
                if ($session['phase'] === 'growth' && $enforce_next === null) {
                    $growth_stage = $inst_data['placement']['placedStage'] ?? $inst_data['placement']['currentStage'] ?? '00';
                    $enforce_next = pick_growth_question($growth_stage, $enforce_asked);
                }

                if ($enforce_next === null) {
                    if (in_array($session['phase'], ['floor', 'ceiling'])) {
                        $phase_order = ['floor' => 'ceiling', 'ceiling' => 'zone'];
                        $new_phase = $phase_order[$session['phase']] ?? $session['phase'];
                        error_log("ElevateHER: Server-side phase enforcement — advancing from {$session['phase']} to $new_phase (no questions remain)");
                    } elseif ($session['phase'] === 'zone') {
                        // Before going to score, check if we should transition to growth
                        $growth_check = check_growth_activation($inst_data, $enforce_asked);
                        if ($growth_check) {
                            $new_phase = 'growth';
                            error_log("ElevateHER: Server-side phase enforcement — advancing from zone to growth (wall blocking at stage {$growth_check['stage']}, assessment questions exhausted)");
                        } else {
                            $new_phase = 'score';
                            error_log("ElevateHER: Server-side phase enforcement — advancing from zone to score (no questions remain)");
                        }
                    } elseif ($session['phase'] === 'growth') {
                        $new_phase = 'score';
                        error_log("ElevateHER: Server-side phase enforcement — advancing from growth to score (no growth questions remain)");
                    } elseif ($session['phase'] === 'profile') {
                        $profile_check = $inst_data['subjectProfile'] ?? [];
                        $who_check = $profile_check['who'] ?? [];
                        $what_check = $profile_check['what'] ?? [];
                        $why_check = $profile_check['why'] ?? [];
                        $field_count = 0;
                        foreach (['businessTenure' => $what_check, 'engagementLevel' => $what_check, 'primaryMotivation' => $why_check, 'lifeStage' => $who_check, 'teamSize' => $what_check] as $f => $s) {
                            if (!empty($s[$f])) $field_count++;
                        }
                        if ($field_count >= 3) {
                            $new_phase = 'floor';
                            $inst_data['subjectProfile']['who']['completedAt'] = date('c');
                            error_log("ElevateHER: Server-side phase enforcement — advancing from profile to floor ($field_count profile fields collected)");
                        }
                    }
                }
            }

            // ── Step 8b: Log question gap suggestions ──
            // When no matrix question was available, log which items were uncovered
            // so framework authors can build new questions for them later.
            if ($cur_q === null && !in_array($session['phase'], ['profile', 'score'])) {
                $gap_stage_num = (int)($inst_data['placement']['placedStage'] ?? $inst_data['placement']['currentStage'] ?? '00');
                $gap_stage_data = get_stage($gap_stage_num);
                if ($gap_stage_data) {
                    $gap_responses = (array)($inst_data['responses'] ?? []);
                    $gap_meta = (array)($inst_data['responseMeta'] ?? []);
                    $gap_items = [];
                    foreach (['mindset', 'toolset', 'skillset'] as $dim) {
                        foreach ($gap_stage_data[$dim] ?? [] as $item) {
                            $gid = $item['id'] ?? '';
                            if (!$gid) continue;
                            if (!isset($gap_responses[$gid]) || ($gap_meta[$gid]['confidence'] ?? 0) < 0.60) {
                                $gap_items[] = ['itemId' => $gid, 'dimension' => $dim, 'label' => $item['label'] ?? $gid];
                            }
                        }
                    }
                    if (!empty($gap_items)) {
                        if (!isset($inst_data['questionSuggestions'])) $inst_data['questionSuggestions'] = [];
                        $inst_data['questionSuggestions'][] = [
                            'suggestedAt' => date('c'),
                            'sessionId' => $session_id,
                            'phase' => $session['phase'],
                            'stage' => sprintf('%02d', $gap_stage_num),
                            'unresolvedItems' => $gap_items,
                            'note' => 'No matrix questions available for phase "' . $session['phase'] . '" to cover these stage ' . sprintf('%02d', $gap_stage_num) . ' items.',
                        ];
                        error_log("ElevateHER: Question gap detected — stage " . sprintf('%02d', $gap_stage_num) . ", phase {$session['phase']}, " . count($gap_items) . " unresolved items with no matrix questions.");
                    }
                }
            }

            // ── Step 9: Inference pass + recompute scores and placement ──
            $meta = (array)($inst_data['responseMeta'] ?? []);
            $inf_result = apply_inference_pass($inst_data['responses'], $meta);
            $inst_data['responses'] = $inf_result['responses'];
            $inst_data['responseMeta'] = $inf_result['responseMeta'];
            if ($inf_result['inferred_count'] > 0) {
                error_log("ElevateHER: Inference pass resolved {$inf_result['inferred_count']} earlier-stage items");
            }
            $inst_data['scores'] = compute_all_stage_scores($inst_data['responses']);
            $inst_data['placement'] = compute_placement($inst_data);
            $inst_data['hardWallStatus'] = compute_hard_wall_status($inst_data['kpiResponses']);

            // ── Step 10: Save assistant message to conv_log with display text ──
            $conv_log[] = [
                'role' => 'assistant',
                'content' => $raw_ai_content ?? ($parsed['display_message'] ?? ''),
                'display' => $parsed['display_message'] ?? '',
                'choices' => !empty($parsed['choices']) ? $parsed['choices'] : null,
                'choices_source' => $choices_source ?? null,
                'ts' => date('c'),
            ];

            save_instance_file($uid, $sid, $inst_data, 'ai-assessment');

            child_db_query_shard_prepared($sid,
                "UPDATE assessment_sessions SET conversation_log = ?, phase = ? WHERE session_id = ?",
                [json_encode($conv_log), $new_phase, $session_id]
            );

            // ── Step 11: Recompute userState ──
            $user_state = compute_user_state($uid, $sid);

            // ── Step 12: Get content recommendations for resolved items ──
            // Determine the stage being assessed for content visibility window
            $assessed_stage = null;
            if (!empty($inferred_items)) {
                $assessed_stage = $inferred_items[0]['stage'] ?? null;
            }
            if (!$assessed_stage && $cur_q) {
                $target_stages = $cur_q['targetStages'] ?? [];
                $assessed_stage = !empty($target_stages) ? $target_stages[0] : null;
            }
            if (!$assessed_stage) {
                $assessed_stage = $inst_data['placement']['aiSuggestedStage']
                    ?? $inst_data['placement']['placedStage']
                    ?? $inst_data['placement']['currentStage']
                    ?? null;
            }

            $content_recs = [];
            if ($growth_resolved && !empty($growth_content_ids)) {
                // Path A: Growth question — surface content from the selected growth choice
                $content_recs = get_content_by_ids($growth_content_ids);
                foreach ($content_recs as &$gc) {
                    $gc['reason'] = 'Chosen for your ' . ($growth_dimension ?: 'growth') . ' path';
                    $gc['growth_routed'] = true;
                }
                unset($gc);
                error_log("ElevateHER: Growth content routing — " . count($content_recs) . " items surfaced for dimension: $growth_dimension");
            } elseif (!empty($inferred_items)) {
                // Path B: MC or AI-inferred items — match content to resolved items
                $resolved_ids = array_map(fn($i) => $i['id'], $inferred_items);
                $recs = get_content_for_resolved_items($resolved_ids, $user_state, $assessed_stage);
                foreach (array_slice($recs, 0, 2) as $rec) {
                    $item = $rec['item'];
                    $item['thumbnail'] = get_content_thumbnail_url($item);
                    $item['reason'] = $rec['state'] === 'recommended'
                        ? 'Addresses areas you just explored'
                        : 'Related to your current progress';
                    $content_recs[] = $item;
                }
            } elseif ($cur_q && !empty($cur_q['resolvableItems']) && ($cur_q['tier'] ?? '') !== 'profile') {
                // Path C: No items resolved yet but we have question context —
                // use the current question's resolvable items to find relevant content.
                // Zero AI cost — pure catalog lookup.
                $context_ids = array_map(fn($ri) => $ri['itemId'] ?? '', $cur_q['resolvableItems']);
                $context_ids = array_filter($context_ids);
                if (!empty($context_ids)) {
                    $recs = get_content_for_resolved_items($context_ids, $user_state, $assessed_stage);
                    foreach (array_slice($recs, 0, 2) as $rec) {
                        $item = $rec['item'];
                        $item['thumbnail'] = get_content_thumbnail_url($item);
                        $item['reason'] = 'Related to what we\'re exploring';
                        $content_recs[] = $item;
                    }
                    if (!empty($content_recs)) {
                        error_log("ElevateHER: Path C contextual content — " . count($content_recs) . " items from question {$cur_q['id']}");
                    }
                }
            }

            // ── Step 13: Determine response choices ──
            // IMPORTANT: Do NOT re-pick $next_q here — the AI already responded to the
            // question picked before the AI call. Re-picking after AI changes $inst_data
            // often returns a DIFFERENT question, causing choice/question mismatch.
            // The scaffold logic (Step 2b) already generates appropriate choices for the
            // current question. Only use $parsed['choices'] (from AI or scaffold).
            if ($skip_ai) {
                // Choices came directly from the matrix — no AI involved
                $response_choices = $parsed['choices'] ?? null;
                $choices_source = 'matrix';
            } else {
                // AI was called — use AI choices or scaffold choices only
                // Do NOT fall back to a different question's choices
                $response_choices = $parsed['choices'] ?? null;
                $choices_source = !empty($parsed['choices']) ? 'ai' : null;
            }

            // ── Step 14: Capture progress snapshot (throttled: once per session) + mark session complete ──
            // Snapshot on every session end, phase transition, or first time in this session
            $_snap_key = '_snap_' . $session_id;
            if (!isset($_SESSION[$_snap_key]) || $new_phase === 'score' || ($parsed['phase_signal'] ?? '') === 'session_complete') {
                $_SESSION[$_snap_key] = true;
                if (function_exists('capture_progress_snapshot')) {
                    capture_progress_snapshot($uid, $sid, 'assessment');
                }
            }
            if ($new_phase === 'score' || ($parsed['phase_signal'] ?? '') === 'session_complete') {
                // Mark session as completed so get_active_session() stops returning it
                child_db_query_shard_prepared($sid,
                    "UPDATE assessment_sessions SET completed_at = NOW() WHERE session_id = ? AND completed_at IS NULL",
                    [$session_id]
                );
            }

            // ── Build profile progress for frontend ──
            $profile = $inst_data['subjectProfile'] ?? [];
            $p_who = $profile['who'] ?? [];
            $p_what = $profile['what'] ?? [];
            $p_why = $profile['why'] ?? [];
            $profile_fields_map = [
                'businessTenure'    => ['section' => 'what', 'label' => 'Business Tenure',    'icon' => 'bi-calendar3',      'value' => $p_what['businessTenure'] ?? null],
                'engagementLevel'   => ['section' => 'what', 'label' => 'Engagement Level',   'icon' => 'bi-activity',       'value' => $p_what['engagementLevel'] ?? null],
                'primaryMotivation' => ['section' => 'why',  'label' => 'Primary Motivation', 'icon' => 'bi-bullseye',       'value' => $p_why['primaryMotivation'] ?? null],
                'lifeStage'         => ['section' => 'who',  'label' => 'Life Stage',          'icon' => 'bi-person-circle',  'value' => $p_who['lifeStage'] ?? null],
                'teamSize'          => ['section' => 'what', 'label' => 'Team Size',           'icon' => 'bi-people',         'value' => $p_what['teamSize'] ?? null],
            ];
            $profile_items = [];
            $profile_collected = 0;
            foreach ($profile_fields_map as $key => $meta) {
                $has_value = !empty($meta['value']);
                if ($has_value) $profile_collected++;
                $profile_items[] = [
                    'key'   => $key,
                    'label' => $meta['label'],
                    'icon'  => $meta['icon'],
                    'value' => $meta['value'],
                    'collected' => $has_value,
                ];
            }
            $data['profile_progress'] = [
                'collected' => $profile_collected,
                'total'     => count($profile_fields_map),
                'needed'    => 3,
                'items'     => $profile_items,
            ];

            // ── Build response ──
            $data['display_message'] = $parsed['display_message'] ?? '';
            // Question in a separate bubble: from AI schema (question_text) or from canned profile flow
            $data['question_bubble'] = $profile_question_text ?? ($parsed['question_text'] ?? null);

            // ── Catchall: choices without a question = mismatch ──
            // If we have choices but no question was asked, either inject the current
            // question prompt or strip choices entirely. This catches edge cases like
            // resume after login, MC→free-form transitions, and AI non-compliance.
            if (!empty($response_choices) && empty($data['question_bubble']) && empty($data['display_message'])) {
                // No message at all — find current question and present it
                if ($next_q && !empty($next_q['prompt'])) {
                    $data['question_bubble'] = $next_q['prompt'];
                    error_log("ElevateHER: Catchall injected question bubble for {$next_q['id']} (choices without question)");
                } else {
                    $response_choices = null;
                    error_log("ElevateHER: Catchall stripped orphaned choices (no question available)");
                }
            } elseif (!empty($response_choices) && empty($data['question_bubble'])) {
                // We have a display_message (ack) but no question — inject the question
                if ($next_q && !empty($next_q['prompt'])) {
                    $data['question_bubble'] = $next_q['prompt'];
                    error_log("ElevateHER: Catchall injected question bubble for {$next_q['id']} (ack without question)");
                } else {
                    // No question to ask — strip choices to avoid confusing the user
                    $response_choices = null;
                    error_log("ElevateHER: Catchall stripped orphaned choices (ack but no question available)");
                }
            }

            // ── Content priority: if content is being shown, suppress question/choices ──
            // The frontend will show the content with a contextual message and a
            // "Continue Assessment" button. The question comes when the user continues.
            if (!empty($content_recs) && !empty($response_choices)) {
                $data['question_bubble'] = null;
                $response_choices = null;
                // Replace the ack with a content-contextual message
                $content_msgs = [
                    "I found some training that addresses what we just discussed — take a look!",
                    "Here's some training content that's relevant to where you are right now.",
                    "Based on what you've shared, this training might really help — check it out!",
                    "Great progress! Here's some content that connects to what we're exploring.",
                ];
                $data['display_message'] = $content_msgs[array_rand($content_msgs)];
                error_log("ElevateHER: Content priority — suppressed question/choices, showing content instead");
            }

            $data['choices'] = $response_choices;
            $data['choices_source'] = $choices_source; // 'ai' or 'matrix'
            $data['phase'] = $new_phase;
            $data['score_update'] = $inst_data['scores'] ?? null;
            $data['placement'] = $inst_data['placement'] ?? null;
            $data['inferred_items'] = $inferred_items;
            $data['session_complete'] = ($new_phase === 'score');

            // Enrich hard wall with KPI details for frontend display
            $hard_wall_enriched = $inst_data['hardWallStatus'] ?? null;
            if ($hard_wall_enriched) {
                foreach ($hard_wall_enriched as $wid => &$wdata) {
                    if (!empty($wdata['blockingKpis'])) {
                        $kpi_details = [];
                        foreach ($wdata['blockingKpis'] as $kpi_id) {
                            $kpi_meta = find_kpi_meta($kpi_id);
                            $current_resp = $inst_data['kpiResponses'][$kpi_id] ?? null;
                            $current_level = is_array($current_resp)
                                ? ($current_resp['value'] ?? $current_resp['level'] ?? 'none')
                                : ($current_resp ?: 'none');
                            $kpi_details[] = [
                                'id' => $kpi_id,
                                'label' => $kpi_meta['label'] ?? $kpi_meta['metric'] ?? $kpi_id,
                                'currentLevel' => $current_level,
                                'requiredLevel' => 'developing',
                            ];
                        }
                        $wdata['kpiDetails'] = $kpi_details;
                    }
                }
                unset($wdata);
            }
            $data['hard_wall'] = $hard_wall_enriched;
            $data['content_recommendations'] = $content_recs;
            $data['user_state'] = [
                'currentStage' => $user_state['currentStage'] ?? '00',
                'currentTier' => $user_state['currentTier'] ?? 'pre-spark',
                'wallStatus' => $user_state['wallStatus'] ?? [],
            ];

            // ── Admin debug metadata ──
            if (function_exists('has_role') && has_role('admin')) {
                $ai_vendor_used = 'unknown';
                $ai_model_used  = 'unknown';
                $ai_tokens      = ['input' => 0, 'output' => 0];
                $ai_cost        = 0;

                // Extract from the last record_cost call metadata if AI responded
                if (!empty($ai_response) && isset($ai_response['raw'])) {
                    $raw = $ai_response['raw'];
                    // Anthropic format
                    if (isset($raw['usage'])) {
                        $ai_tokens = [
                            'input'  => $raw['usage']['input_tokens'] ?? $raw['usage']['prompt_tokens'] ?? 0,
                            'output' => $raw['usage']['output_tokens'] ?? $raw['usage']['completion_tokens'] ?? 0,
                        ];
                    }
                    $ai_model_used = $raw['model'] ?? 'unknown';
                }

                // Get vendor from user pref (reflects what was just used)
                $ai_vendor_used = (function_exists('get_user_ai_vendor') ? get_user_ai_vendor($uid, $sid) : null)
                    ?? ($ai_config['default_vendor'] ?? 'anthropic');

                // Recalculate cost for display
                if (($ai_tokens['input'] + $ai_tokens['output']) > 0 && function_exists('get_vendor_cost_rates')) {
                    $rates = get_vendor_cost_rates($ai_vendor_used, $ai_model_used);
                    $ai_cost = ($ai_tokens['input'] * $rates['input'] / 1000000)
                             + ($ai_tokens['output'] * $rates['output'] / 1000000);
                }

                $data['_debug'] = [
                    'vendor'         => $skip_ai ? 'none (matrix direct)' : $ai_vendor_used,
                    'model'          => $skip_ai ? 'n/a' : $ai_model_used,
                    'tokens'         => $ai_tokens,
                    'cost_usd'       => round($ai_cost, 6),
                    'skip_ai'        => $skip_ai,
                    'phase_before'   => $session['phase'],
                    'phase_after'    => $new_phase,
                    'phase_signal'   => $parsed['phase_signal'] ?? 'continue',
                    'suggested_stage' => $ai_suggested_stage ?? null,
                    'question_id'    => $parsed['question_id'] ?? null,
                    'mc_resolved'    => $mc_resolved,
                    'growth_resolved' => $growth_resolved,
                    'growth_dimension' => $growth_dimension,
                    'growth_content_ids' => $growth_content_ids,
                    'inferred_count' => count($parsed['inferred_responses'] ?? []),
                    'kpi_count'      => count($parsed['kpi_signals'] ?? []),
                    'profile_hints'  => $parsed['profile_hints'] ?? [],
                    'session_notes_count' => count($parsed['session_notes'] ?? []),
                    'phase_enforced' => ($new_phase !== $session['phase'] && ($parsed['phase_signal'] ?? 'continue') === 'continue'),
                    'question_gaps'  => $inst_data['questionSuggestions'] ?? [],
                    'system_prompt'  => $system_prompt,
                    'raw_ai_response' => $raw_ai_content,
                    'content_debug' => [
                        'assessed_stage' => $assessed_stage,
                        'content_path' => ($growth_resolved && !empty($growth_content_ids)) ? 'A-growth'
                            : (!empty($inferred_items) ? 'B-inferred' : (($cur_q && !empty($cur_q['resolvableItems']) && ($cur_q['tier'] ?? '') !== 'profile') ? 'C-context' : 'none')),
                        'cur_q_id' => $cur_q['id'] ?? null,
                        'cur_q_tier' => $cur_q['tier'] ?? null,
                        'cur_q_resolvable_count' => count($cur_q['resolvableItems'] ?? []),
                        'inferred_count_total' => count($inferred_items),
                        'content_recs_count' => count($content_recs),
                    ],
                ];
            }
        }
    } else {
        $_SESSION['error'] = implode('<br>', $errs);
    }
    end_chat:
}

// ─── Assessment Start ───────────────────────────────────────────────────────

if (($_POST['action'] ?? '') === 'assessment_start') {
    $uid = $_SESSION['user_id'] ?? 0;
    $sid = $_SESSION['shard_id'] ?? '';

    if ($uid) {
        // Check for existing session
        $active = get_active_session($uid, $sid);
        if ($active) {
            $data['session_id'] = $active['session_id'];
            $data['phase'] = $active['phase'];
            $data['resuming'] = true;
        } else {
            // Ensure instance file exists
            $inst_data = load_instance_file($uid, $sid, 'ai-assessment');
            if (!$inst_data) {
                $inst_data = create_instance_v23($uid, $sid, 'ai-assessment');
                save_instance_file($uid, $sid, $inst_data, 'ai-assessment');
            }

            // Get instance row ID for session FK
            $stmt = child_db_query_shard_prepared($sid,
                "SELECT id FROM assessment_instances WHERE user_id = ? AND instance_type = 'ai-assessment' LIMIT 1",
                [$uid]
            );
            $inst_row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
            $instance_id = $inst_row ? $inst_row['id'] : 0;

            $session_id = start_assessment_session($uid, $sid, $instance_id, 'profile');
            $data['session_id'] = $session_id;
            $data['phase'] = 'profile';
            $data['resuming'] = false;
        }
    }
}

// ─── Lotus Canvas State ─────────────────────────────────────────────────────

if (($_GET['action'] ?? $_POST['action'] ?? '') === 'get_lotus_state') {
    $uid = $_SESSION['user_id'] ?? 0;
    $sid = $_SESSION['shard_id'] ?? '';

    if ($uid) {
        $user_state = load_user_state($uid, $sid);
        if ($user_state) {
            $data['itemProficiency'] = $user_state['itemProficiency'] ?? [];
            $data['kpiScores'] = $user_state['kpiScores'] ?? [];
            $data['wallStatus'] = $user_state['wallStatus'] ?? [];
            $data['dimensionStrength'] = $user_state['dimensionStrength'] ?? [];
            $data['currentStage'] = $user_state['currentStage'] ?? '00';
            $data['currentTier'] = $user_state['currentTier'] ?? 'pre-spark';
        }

        // Also include instance scores for backward compat
        $inst_data = load_instance_file($uid, $sid, 'ai-assessment');
        if ($inst_data) {
            $data['scores'] = $inst_data['scores'] ?? null;
            $data['placement'] = $inst_data['placement'] ?? null;
            $data['responses'] = $inst_data['responses'] ?? null;
            $data['responseMeta'] = $inst_data['responseMeta'] ?? null;
            $data['kpiResponses'] = $inst_data['kpiResponses'] ?? null;
        }
    }
}

// ─── Profile Field Update (from Getting to Know You card) ──────────────────

if (($_POST['action'] ?? '') === 'profile_field_update') {
    $uid = $_SESSION['user_id'] ?? 0;
    $sid = $_SESSION['shard_id'] ?? '';
    $field = trim($_POST['field'] ?? '');
    $value = trim($_POST['value'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (!$uid) { $data['error'] = 'Login required.'; goto end_chat; }
    if (!$field || !$value) { $data['error'] = 'Field and value required.'; goto end_chat; }

    $inst_data = load_instance_file($uid, $sid, 'ai-assessment');
    if (!$inst_data) { $data['error'] = 'No assessment instance.'; goto end_chat; }

    // Validate field is a known profile field
    $valid_fields = ['primaryMotivation', 'lifeStage', 'businessTenure', 'engagementLevel', 'teamSize'];
    if (!in_array($field, $valid_fields)) { $data['error'] = 'Invalid field.'; goto end_chat; }

    // Set the profile field
    set_profile_field($inst_data['subjectProfile'], $field, $value);
    save_instance_file($uid, $sid, $inst_data, 'ai-assessment');

    // Recompute profile progress for response
    $profile = $inst_data['subjectProfile'] ?? [];
    $p_who = $profile['who'] ?? [];
    $p_what = $profile['what'] ?? [];
    $p_why = $profile['why'] ?? [];
    $profile_fields_map = [
        'businessTenure'    => ['section' => 'what', 'label' => 'Business Tenure',    'icon' => 'bi-calendar3',      'value' => $p_what['businessTenure'] ?? null],
        'engagementLevel'   => ['section' => 'what', 'label' => 'Engagement Level',   'icon' => 'bi-activity',       'value' => $p_what['engagementLevel'] ?? null],
        'primaryMotivation' => ['section' => 'why',  'label' => 'Primary Motivation', 'icon' => 'bi-bullseye',       'value' => $p_why['primaryMotivation'] ?? null],
        'lifeStage'         => ['section' => 'who',  'label' => 'Life Stage',          'icon' => 'bi-person-circle',  'value' => $p_who['lifeStage'] ?? null],
        'teamSize'          => ['section' => 'what', 'label' => 'Team Size',           'icon' => 'bi-people',         'value' => $p_what['teamSize'] ?? null],
    ];
    $profile_items = [];
    $profile_collected = 0;
    foreach ($profile_fields_map as $key => $meta) {
        $has_value = !empty($meta['value']);
        if ($has_value) $profile_collected++;
        $profile_items[] = [
            'key'   => $key,
            'label' => $meta['label'],
            'icon'  => $meta['icon'],
            'value' => $meta['value'],
            'collected' => $has_value,
        ];
    }

    $data['profile_progress'] = [
        'collected' => $profile_collected,
        'total'     => count($profile_fields_map),
        'needed'    => 3,
        'items'     => $profile_items,
    ];
    $data['field'] = $field;
    $data['display_value'] = $value;

    // If user included a message, add it to the conversation log
    if ($message) {
        $active_session = get_active_session($uid, $sid);
        if ($active_session) {
            $conv_log = $active_session['conversation_log'] ? json_decode($active_session['conversation_log'], true) : [];
            $conv_log[] = ['role' => 'user', 'content' => $message, 'display' => $message, 'ts' => date('c')];
            child_db_query_shard_prepared($sid,
                "UPDATE assessment_sessions SET conversation_log = ? WHERE session_id = ?",
                [json_encode($conv_log), $active_session['session_id']]
            );
        }
    }
}

// ─── Profile Matrix Choices (for Getting to Know You modals) ────────────────

if (($_GET['action'] ?? $_POST['action'] ?? '') === 'get_profile_choices') {
    $matrix = get_profile_matrix_data();
    $choices_by_field = [];
    foreach ($matrix as $q) {
        $field = $q['field'] ?? '';
        if (!$field) continue;
        $choices_by_field[$field] = [
            'prompt' => $q['prompt'] ?? '',
            'choices' => array_filter($q['choices'] ?? [], function($c) {
                return ($c['value'] ?? '') !== 'free-form';
            }),
        ];
        // Re-index after filter
        $choices_by_field[$field]['choices'] = array_values($choices_by_field[$field]['choices']);
    }
    $data['profile_choices'] = $choices_by_field;
}

// ─── Content Recommendations ────────────────────────────────────────────────

if (($_GET['action'] ?? $_POST['action'] ?? '') === 'get_recommendations') {
    $uid = $_SESSION['user_id'] ?? 0;
    $sid = $_SESSION['shard_id'] ?? '';
    if ($uid) {
        $recs = get_content_recommendations($uid, $sid);
        $data['recommendations'] = array_map(function($rec) {
            $item = $rec['item'];
            $item['thumbnail'] = get_content_thumbnail_url($item);
            $item['state'] = $rec['state'];
            $item['score'] = $rec['score'];
            $item['reason'] = $rec['reason'];
            return $item;
        }, array_slice($recs, 0, 10));
    }
}

// ─── All Content States (Browse View) ───────────────────────────────────────

if (($_GET['action'] ?? $_POST['action'] ?? '') === 'get_all_content') {
    $uid = $_SESSION['user_id'] ?? 0;
    $sid = $_SESSION['shard_id'] ?? '';
    if ($uid) {
        $all = resolve_all_content_states($uid, $sid);
        $data['items'] = array_map(function($entry) {
            $item = $entry['item'];
            $item['thumbnail'] = get_content_thumbnail_url($item);
            $item['contentState'] = $entry['state'];
            return $item;
        }, $all);
    }
}

// ─── Content Catalog (filtered) ─────────────────────────────────────────────

if (($_GET['action'] ?? $_POST['action'] ?? '') === 'get_content_catalog') {
    $stage_filter = $_GET['stage'] ?? null;
    $dim_filter = strtolower($_GET['dimension'] ?? '');
    $catalog = get_content_catalog();
    $items = isset($catalog['contentCatalog']) ? $catalog['contentCatalog'] : (isset($catalog['items']) ? $catalog['items'] : ($catalog ?: []));

    $filtered = [];
    foreach ($items as $item) {
        if ($stage_filter && !in_array($stage_filter, (array)($item['stages'] ?? []))) continue;
        if ($dim_filter) {
            $dims = array_map('strtolower', (array)($item['dimensions'] ?? []));
            if (!in_array($dim_filter, $dims)) continue;
        }
        $item['thumbnail'] = get_content_thumbnail_url($item);
        $filtered[] = $item;
    }
    $data['items'] = $filtered;
}

// ─── Content Progress ───────────────────────────────────────────────────────

if (($_GET['action'] ?? $_POST['action'] ?? '') === 'get_content_progress') {
    $uid = $_SESSION['user_id'] ?? 0;
    $sid = $_SESSION['shard_id'] ?? '';
    $status = $_GET['status'] ?? null;
    if ($uid) {
        $data['progress'] = get_content_progress_list($uid, $sid, $status);
    }
}

// ─── Framework Summary ──────────────────────────────────────────────────────

if (($_GET['action'] ?? $_POST['action'] ?? '') === 'get_framework_summary') {
    $header = get_framework_header();
    $hard_walls_raw = $header['hardWalls'] ?? [];
    $advance_threshold = (float)($header['schema']['advanceThreshold'] ?? $header['advanceThreshold'] ?? 0.80);

    // Load user KPI responses for current level display
    $uid_sum = $_SESSION['user_id'] ?? 0;
    $sid_sum = $_SESSION['shard_id'] ?? '';
    $inst_sum = ($uid_sum && $sid_sum) ? get_user_instance($uid_sum, $sid_sum, 'ai-assessment') : null;
    $kpi_resp_sum = $inst_sum ? (array)($inst_sum['instance_data']['kpiResponses'] ?? []) : [];

    $stages = [];
    for ($i = 0; $i <= 10; $i++) {
        $stage = get_stage($i);
        if (!$stage) continue;
        $key = sprintf('%02d', $i);

        $dim_counts = ['mindset' => 0, 'toolset' => 0, 'skillset' => 0];
        foreach (['mindset', 'toolset', 'skillset'] as $dim) {
            foreach ($stage[$dim] ?? [] as $item) {
                if ($item['deprecated'] ?? false) continue;
                $dim_counts[$dim]++;
            }
        }

        // Include KPI details with user's current level
        $kpis_detail = [];
        foreach ($stage['kpis'] ?? [] as $kpi) {
            $kpi_id = $kpi['id'] ?? '';
            $current = $kpi_resp_sum[$kpi_id] ?? null;
            $level = is_array($current) ? ($current['value'] ?? $current['level'] ?? null) : $current;
            $kpis_detail[] = [
                'id' => $kpi_id,
                'metric' => $kpi['metric'] ?? $kpi_id,
                'beginner' => $kpi['beginner'] ?? '',
                'developing' => $kpi['developing'] ?? '',
                'mastery' => $kpi['mastery'] ?? '',
                'nonNeg' => $kpi['nonNeg'] ?? false,
                'currentLevel' => $level ?: 'unanswered',
            ];
        }

        $stages[] = [
            'id' => $key,
            'name' => $stage['name'] ?? "Stage $key",
            'tagline' => $stage['tagline'] ?? '',
            'kpiCount' => count($stage['kpis'] ?? []),
            'kpis' => $kpis_detail,
            'itemCounts' => $dim_counts,
            'hardWallAfter' => !empty($stage['hardWallAfter']),
        ];
    }

    $hard_walls = [];
    foreach ($hard_walls_raw as $w) {
        $hard_walls[] = [
            'id' => $w['id'] ?? '',
            'label' => $w['label'] ?? '',
            'from' => $w['from'] ?? '',
            'to' => $w['to'] ?? '',
        ];
    }

    $data['summary'] = [
        'stages' => $stages,
        'dimensionKeys' => ['mindset', 'toolset', 'skillset'],
        'hardWalls' => $hard_walls,
        'advanceThreshold' => $advance_threshold,
    ];
}

// ─── Stage Detail (full framework data for a single stage) ──────────────────

if (($_GET['action'] ?? $_POST['action'] ?? '') === 'get_stage_detail') {
    $stage_num = $_POST['stage'] ?? $_GET['stage'] ?? null;
    if ($stage_num === null) {
        $data['error'] = 'Missing stage parameter';
    } else {
        $stage = get_stage((int)$stage_num);
        if (!$stage) {
            $data['error'] = 'Stage not found';
        } else {
            $uid = $_SESSION['user_id'] ?? 0;
            $sid = $_SESSION['shard_id'] ?? '';

            // Build diagnostic items grouped by dimension
            $items = [];
            foreach (['mindset', 'toolset', 'skillset'] as $dim) {
                foreach ($stage[$dim] ?? [] as $item) {
                    if ($item['deprecated'] ?? false) continue;
                    $items[] = [
                        'id' => $item['id'],
                        'dimension' => $dim,
                        'label' => $item['label'],
                        'sub' => $item['sub'] ?? '',
                        'weight' => $item['weight'] ?? 1.0,
                    ];
                }
            }

            // User proficiency for this stage's items
            $proficiency = [];
            $user_state = null;
            if ($uid) {
                $user_state = load_user_state($uid, $sid);
                $proficiency = $user_state['itemProficiency'] ?? [];
            }

            // Get responses/observations from instance file
            $responses = [];
            $response_meta = [];
            if ($uid) {
                $inst = load_instance_file($uid, $sid, 'ai-assessment');
                if ($inst) {
                    $responses = $inst['responses'] ?? [];
                    $response_meta = $inst['responseMeta'] ?? [];
                }
            }

            // Self-reported reflections from instance file
            $self_reported = [];
            if ($uid) {
                $inst = $inst ?? load_instance_file($uid, $sid, 'ai-assessment');
                if ($inst) {
                    $self_reported = $inst['selfReported'] ?? [];
                }
            }

            // KPIs with user scores
            $kpis = [];
            $kpi_scores = ($user_state['kpiScores'] ?? []);
            foreach ($stage['kpis'] ?? [] as $kpi) {
                $kpis[] = [
                    'id' => $kpi['id'],
                    'metric' => $kpi['metric'],
                    'beginner' => $kpi['beginner'] ?? '',
                    'developing' => $kpi['developing'] ?? '',
                    'mastery' => $kpi['mastery'] ?? '',
                    'currentLevel' => is_array($kpi_scores[$kpi['id']] ?? null)
                        ? ($kpi_scores[$kpi['id']]['level'] ?? 'none')
                        : ($kpi_scores[$kpi['id']] ?? 'none'),
                ];
            }

            // Content for this stage (from catalog, filtered)
            $content_items = [];
            $catalog = get_content_catalog();
            $cat_items = isset($catalog['contentCatalog']) ? $catalog['contentCatalog'] : (isset($catalog['items']) ? $catalog['items'] : ($catalog ?: []));
            $stage_key = sprintf('%02d', (int)$stage_num);
            foreach ($cat_items as $ci) {
                if (!in_array($stage_key, (array)($ci['stages'] ?? []))) continue;
                if (($ci['status'] ?? '') === 'draft' && empty($ci['url'])) continue; // skip empty drafts
                $ci['thumbnail'] = get_content_thumbnail_url($ci);
                $content_items[] = $ci;
            }

            // Question matrix entries for this stage's items
            $question_lookup = [];
            $qm = get_question_matrix();
            $qm_items = $qm['questionMatrix'] ?? $qm;
            if (is_array($qm_items)) {
                foreach ($qm_items as $q) {
                    foreach ($q['resolvableItems'] ?? [] as $ri) {
                        $question_lookup[$ri['itemId']] = $q['prompt'] ?? '';
                    }
                }
            }

            $data['stage'] = [
                'id' => sprintf('%02d', (int)$stage_num),
                'name' => $stage['name'] ?? '',
                'tagline' => $stage['tagline'] ?? '',
                'tierId' => $stage['tierId'] ?? '',
                'description' => $stage['description'] ?? '',
                'behavior' => $stage['behavior'] ?? '',
                'belief' => $stage['belief'] ?? '',
                'stuck' => $stage['stuck'] ?? '',
                'unlocks' => $stage['unlocks'] ?? '',
                'items' => $items,
                'kpis' => $kpis,
                'fieldWisdom' => $stage['fieldWisdom'] ?? [],
                'content' => $content_items,
            ];
            $data['proficiency'] = $proficiency;
            $data['responses'] = $responses;
            $data['responseMeta'] = $response_meta;
            $data['selfReported'] = $self_reported;
            $data['questionLookup'] = $question_lookup;
        }
    }
}

// ─── Save Explorer Reflection ───────────────────────────────────────────────

if (($_POST['action'] ?? '') === 'save_explorer_reflection') {
    $uid = $_SESSION['user_id'] ?? 0;
    $sid = $_SESSION['shard_id'] ?? '';
    $item_id = trim($_POST['item_id'] ?? '');
    $reflection = trim($_POST['reflection'] ?? '');

    if (!$uid) {
        $data['error'] = 'Not logged in';
    } elseif (!$item_id || !$reflection) {
        $data['error'] = 'Missing item_id or reflection';
    } else {
        // Load instance file and save self-reported reflection
        $inst = load_instance_file($uid, $sid, 'ai-assessment');
        if (!$inst) {
            $inst = create_instance_v23($uid, $sid, 'ai-assessment');
        }
        if (!isset($inst['selfReported'])) {
            $inst['selfReported'] = [];
        }
        $inst['selfReported'][$item_id] = [
            'text' => $reflection,
            'savedAt' => date('c'),
            'source' => 'explorer',
        ];
        save_instance_file($uid, $sid, $inst, 'ai-assessment');

        // Track in pending reflections for chat relay (WP7)
        if (!isset($inst['pendingReflections'])) {
            $inst['pendingReflections'] = [];
        }
        $inst['pendingReflections'][] = [
            'itemId' => $item_id,
            'text' => $reflection,
            'savedAt' => date('c'),
        ];
        save_instance_file($uid, $sid, $inst, 'ai-assessment');

        $data['saved'] = true;
    }
}

// ─── Chat History (for session resume without AI call) ──────────────────────

if (($_GET['action'] ?? $_POST['action'] ?? '') === 'get_chat_history') {
    $uid = $_SESSION['user_id'] ?? 0;
    $sid = $_SESSION['shard_id'] ?? '';
    $req_session_id = $_POST['session_id'] ?? $_GET['session_id'] ?? '';

    if ($uid && $req_session_id) {
        $session = get_session_by_id($req_session_id, $sid);
        if ($session && $session['user_id'] == $uid) {
            $conv_log = $session['conversation_log'] ? json_decode($session['conversation_log'], true) : [];
            // Return only entries that have a display field (visible messages)
            $display_history = [];
            foreach ($conv_log as $entry) {
                if (!empty($entry['display'])) {
                    // Safety net: if display was stored as raw JSON, extract display_message
                    $disp = $entry['display'];
                    if (str_starts_with(trim($disp), '{"display_message"')) {
                        $dj = json_decode($disp, true);
                        if ($dj && isset($dj['display_message'])) {
                            $disp = $dj['display_message'];
                        } elseif (preg_match('/"display_message"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/s', $disp, $dm)) {
                            $disp = stripcslashes($dm[1]);
                        }
                    }
                    $display_history[] = [
                        'role' => $entry['role'],
                        'display' => $disp,
                        'choices' => $entry['choices'] ?? null,
                        'choices_source' => $entry['choices_source'] ?? null,
                        'ts' => $entry['ts'] ?? null,
                    ];
                }
            }
            $data['history'] = $display_history;
            $data['phase'] = $session['phase'] ?? 'profile';

            // ── Include next question's choices for resume ──
            // The question matrix should always drive the UX. On resume, the client
            // needs the next MC choices so the user isn't left with just a text box.
            $cur_phase = $session['phase'] ?? 'profile';
            if ($cur_phase !== 'score') {
                $inst_data = load_instance_file($uid, $sid, 'ai-assessment');
                if ($inst_data) {
                    $asked = get_asked_questions_from_instance($inst_data);

                    if ($cur_phase === 'growth') {
                        $growth_stage = $inst_data['placement']['placedStage'] ?? $inst_data['placement']['currentStage'] ?? '00';
                        $next_q = pick_growth_question($growth_stage, $asked);
                    } elseif ($cur_phase === 'profile') {
                        $next_q = pick_next_profile_question($inst_data);
                    } else {
                        $next_q = pick_next_question($cur_phase, $inst_data, $asked);
                    }

                    if ($next_q) {
                        $is_growth_q = ($next_q['tier'] ?? '') === 'growth';
                        $is_profile_q = ($next_q['tier'] ?? '') === 'profile';
                        $nq_format = $next_q['answerFormat'] ?? 'free-form';

                        // Always send the next question prompt so the client can display it
                        $data['next_question_id'] = $next_q['id'] ?? null;
                        $data['next_question_prompt'] = $next_q['prompt'] ?? null;
                        $data['next_question_format'] = $nq_format;

                        if (!empty($next_q['choices']) && ($is_growth_q || $is_profile_q || in_array($nq_format, ['multiple-choice', 'hybrid']))) {
                            // MC/hybrid/growth/profile: send choices for direct display
                            $data['next_choices'] = array_map(function($c) use ($is_growth_q, $is_profile_q) {
                                return [
                                    'label' => $c['label'] ?? '',
                                    'value' => ($is_growth_q || $is_profile_q) ? ($c['value'] ?? $c['label'] ?? '') : ($c['id'] ?? $c['label'] ?? ''),
                                ];
                            }, $next_q['choices']);
                        } elseif ($nq_format === 'free-form' && !empty($next_q['resolvableItems'])) {
                            // Free-form with resolvable items: generate scaffold choices
                            // so the user has quick-answer options on resume
                            $ri_items = $next_q['resolvableItems'];
                            $first_ri = $ri_items[0] ?? [];
                            $true_sig = $first_ri['trueSignal'] ?? '';
                            $false_sig = $first_ri['falseSignal'] ?? '';
                            $strong_label = $true_sig ?: 'Yes, absolutely — this feels natural to me';
                            $partial_label = 'Somewhat — I\'m getting there';
                            $weak_label = 'Not really — I still struggle with this';
                            $struggle_label = $false_sig ?: 'No, this is a real challenge for me';
                            if (mb_strlen($strong_label) > 70) $strong_label = mb_substr($strong_label, 0, 67) . '...';
                            if (mb_strlen($struggle_label) > 70) $struggle_label = mb_substr($struggle_label, 0, 67) . '...';

                            $resolves_map = [];
                            foreach ($ri_items as $ri) {
                                $iid = $ri['itemId'] ?? '';
                                if ($iid) $resolves_map[$iid] = true;
                            }
                            $data['next_choices'] = [
                                ['label' => $strong_label,                    'value' => 'strong-true',   'resolves' => array_map(fn() => ['value' => true, 'confidence' => 0.85], $resolves_map)],
                                ['label' => $partial_label,                   'value' => 'partial-true',  'resolves' => array_map(fn() => ['value' => true, 'confidence' => 0.6], $resolves_map)],
                                ['label' => $weak_label,                      'value' => 'partial-false', 'resolves' => array_map(fn() => ['value' => false, 'confidence' => 0.6], $resolves_map)],
                                ['label' => $struggle_label,                  'value' => 'strong-false',  'resolves' => array_map(fn() => ['value' => false, 'confidence' => 0.85], $resolves_map)],
                                ['label' => 'I\'d like to share more details', 'value' => 'free-form',    'resolves' => []],
                            ];
                        }
                    }
                }
            }
        }
    }
}

// ─── User State ─────────────────────────────────────────────────────────────

if (($_GET['action'] ?? $_POST['action'] ?? '') === 'get_user_state') {
    $uid = $_SESSION['user_id'] ?? 0;
    $sid = $_SESSION['shard_id'] ?? '';
    if ($uid) {
        $data['userState'] = load_user_state($uid, $sid);
    }
}

// ─── User Progress Timeline ─────────────────────────────────────────────────

if (($_GET['action'] ?? $_POST['action'] ?? '') === 'get_user_timeline') {
    $uid = $_SESSION['user_id'] ?? 0;
    $sid = $_SESSION['shard_id'] ?? '';
    $from = $_GET['from'] ?? null;
    $to = $_GET['to'] ?? null;
    if ($uid && function_exists('get_user_progress_timeline')) {
        $data['timeline'] = get_user_progress_timeline($uid, $sid, $from, $to);
        $data['milestones'] = get_user_milestones($uid, $sid);
        $data['growth'] = compute_growth_rate($uid, $sid, 30);
    }
}

// ─── Health Check ───────────────────────────────────────────────────────────

if (($_GET['action'] ?? '') === 'health') {
    global $ai_config, $childShardConfigs;
    $version = get_active_framework_version();
    $data['status'] = 'ok';
    $data['framework_version'] = $version ? $version['version_label'] : 'none';
    $data['schema_version'] = '2.4';
    $data['database'] = 'connected';
    $data['shards'] = count($childShardConfigs);
    // Check if any vendor has a configured API key
    $vendors_configured = [];
    foreach ($ai_config['vendors'] ?? [] as $v => $vcfg) {
        if (!empty($vcfg['api_key'])) $vendors_configured[] = $v;
    }
    $data['ai_configured'] = count($vendors_configured) > 0;
    $data['ai_vendors'] = $vendors_configured;
    $data['last_sync'] = $version ? $version['loaded_at'] : null;
}

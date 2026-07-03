<?php
/**
 * aiHelpers.php
 * AI API integration helpers for the assessment chat.
 * AI is the BACKSTOP — the question matrix drives the assessment.
 * AI provides: conversational wrapper, profile harvesting, free-form inference.
 * AI does NOT: score MC choices, decide phase advancement, control question selection.
 */

function build_assessment_system_prompt($instance_data, $session) {
    $profile = $instance_data['subjectProfile'] ?? [];
    $scores = $instance_data['scores'] ?? [];
    $responses = $instance_data['responses'] ?? [];
    $placement = $instance_data['placement'] ?? [];
    $phase = $session['phase'] ?? 'profile';

    // Extract profile data from structured who/what/why
    $who = $profile['who'] ?? [];
    $what = $profile['what'] ?? [];
    $why = $profile['why'] ?? [];
    $has_profile = !empty($who['completedAt'] ?? $profile['completedAt'] ?? null);

    $current_stage = $placement['placedStage'] ?? $placement['currentStage'] ?? '00';
    $stage_data = get_stage((int)$current_stage);

    $asked_questions = get_asked_questions_from_instance($instance_data);
    if ($phase === 'growth') {
        $next_question = pick_growth_question($current_stage, $asked_questions);
    } else {
        $next_question = pick_next_question($phase, $instance_data, $asked_questions);
    }
    $unresolved = get_unresolved_items($instance_data);
    $is_complete = check_assessment_completion($instance_data, $phase);

    // ── Role ──
    $prompt = "You are the " . CHILD_APP_NAME . " AI assistant.\n\n";
    $prompt .= "ROLE: Present matrix questions and collect answers. The question matrix drives EVERYTHING.\n";
    $prompt .= "STRICT RULES:\n";
    $prompt .= "1. ALWAYS present the CURRENT QUESTION from the matrix below. Do NOT invent your own questions.\n";
    $prompt .= "2. Keep your conversational wrapper to 1-2 SHORT sentences, then present the question.\n";
    $prompt .= "3. Do NOT ask follow-up questions. After the user answers, the SERVER picks the next matrix question.\n";
    $prompt .= "4. Do NOT go on tangents. Your ONLY job is: brief warm wrapper → matrix question → choices.\n";
    $prompt .= "5. When the user selects a choice, acknowledge briefly (1 sentence max), then present the NEXT question.\n";
    $prompt .= "6. When scoring a free-form answer, you MUST set suggested_stage to your best estimate of the subject's stage (00-10).\n";
    $prompt .= "7. After scoring a free-form answer, the SERVER will pick the next question. Do NOT try to decide what to ask next.\n\n";

    // ── Subject ──
    $prompt .= "SUBJECT:\n";
    $subject = $instance_data['subject'] ?? [];
    $prompt .= "- Name: " . ($subject['name'] ?? 'Unknown') . "\n";
    if ($has_profile) {
        if (!empty($what['businessTenure'])) $prompt .= "- Business Tenure: " . $what['businessTenure'] . "\n";
        if (!empty($why['primaryMotivation'])) $prompt .= "- Primary Motivation: " . $why['primaryMotivation'] . "\n";
        if (!empty($what['engagementLevel'])) $prompt .= "- Engagement Level: " . $what['engagementLevel'] . "\n";
        if (!empty($what['teamSize'])) $prompt .= "- Team Size: " . $what['teamSize'] . "\n";
        if (!empty($who['lifeStage'])) $prompt .= "- Life Stage: " . $who['lifeStage'] . "\n";
        if (!empty($why['drivingCircumstance'])) $prompt .= "- Driving Circumstance: " . $why['drivingCircumstance'] . "\n";
    } else {
        $prompt .= "- Profile: NOT YET COLLECTED\n";
    }
    $prompt .= "\n";

    // ── Current State ──
    $prompt .= "STATE: Phase=$phase, Stage=$current_stage, Unresolved=" . count($unresolved) . ", Asked=" . count($asked_questions) . "\n";
    if ($phase !== 'profile' && !empty($scores[$current_stage])) {
        $s = $scores[$current_stage];
        $prompt .= "Stage $current_stage scores: mindset=" . round(($s['mindset'] ?? 0) * 100) . "%, toolset=" . round(($s['toolset'] ?? 0) * 100) . "%, skillset=" . round(($s['skillset'] ?? 0) * 100) . "%\n";
    }
    $prompt .= "\n";

    // ── Tone ──
    $prompt .= "TONE: Warm, encouraging, conversational. Use their name. One question at a time. Never judgmental.\n\n";

    // ── Output Format (enforced by structured schema — these are behavioral instructions) ──
    $prompt .= "OUTPUT FORMAT: Your response is enforced by a structured JSON schema. Focus on CONTENT, not format.\n\n";

    $prompt .= "FIELD GUIDELINES:\n";
    $prompt .= "- display_message: Brief acknowledgment or warm wrapper (1-2 sentences). Do NOT include the question here.\n";
    $prompt .= "- question_text: The matrix question to present. Copy it EXACTLY from the CURRENT QUESTION section below. Set to null only when session_complete.\n";
    $prompt .= "- inferred_responses: ONLY for free-form questions. Map item IDs to {value:bool, confidence:0-1, reasoning:\"...\"}. For MC questions leave as empty object {}.\n";
    $prompt .= "- kpi_signals: Map KPI IDs to {level:\"beginner\"|\"developing\"|\"mastery\"}. Only for free-form.\n";
    $prompt .= "- profile_hints: Key-value profile data. Keys: lifeStage, ageRange, industry, householdRole, businessTenure, priorDsExperience, teamSize, engagementLevel, productRelationship, primaryMotivation, secondaryMotivations, drivingCircumstance\n";
    $prompt .= "- session_notes: [{observation, stage, dimension, focus, surfaceInCanvas:bool, canvasLabel}]\n";
    $prompt .= "- phase_signal: continue | advance_phase | session_complete\n";
    $prompt .= "- suggested_stage: REQUIRED after scoring a free-form answer. 2-digit stage string \"00\" to \"10\". Use the STAGE REFERENCE below. Set to null when presenting a new question.\n";
    $prompt .= "- choices: REQUIRED on every response.\n";
    $prompt .= "  * For MC/hybrid questions: COPY CHOICES EXACTLY from the question data below.\n";
    $prompt .= "  * For free-form questions: GENERATE 3-4 choices. Each choice needs label, value, and resolves.\n";
    $prompt .= "    - value: one of \"strong-true\", \"partial-true\", \"partial-false\", \"strong-false\", or \"free-form\"\n";
    $prompt .= "    - resolves: map resolvableItem IDs from the CURRENT QUESTION to inferred true/false with confidence.\n";
    $prompt .= "      strong-true → {value:true, confidence:0.85}, partial-true → {value:true, confidence:0.6}\n";
    $prompt .= "      partial-false → {value:false, confidence:0.6}, strong-false → {value:false, confidence:0.85}\n";
    $prompt .= "    - Last choice: {label:\"I'd like to share more details\", value:\"free-form\", resolves:{}}\n";
    $prompt .= "  * For profile phase: choices with {label:\"...\", value:\"profile-key:value\"} plus a free-form option.\n";
    $prompt .= "- question_id: ID of the question you are presenting\n\n";

    $prompt .= "CRITICAL RULE: Every response MUST include choices. The user should always see clickable options.\n";
    $prompt .= "FLOW RULE: When a user gives a free-form answer, your job is to (1) score it via inferred_responses/kpi_signals, (2) set suggested_stage, (3) give a brief warm acknowledgment in display_message, and (4) include choices for the CURRENT question. The SERVER will then pick the next matrix question — you do NOT need to decide what to ask next. Just score, acknowledge, and include choices.\n";
    $prompt .= "If the user's answer is off-topic or doesn't contain scorable signals, set inferred_responses to {} and still include the choices so the conversation has a way back to the assessment.\n\n";

    // ── Phase Instructions ──
    // NOTE: Profile phase is handled entirely by the profile matrix — no AI calls.
    // AI is only called during profile if the user chooses "free-form" on a profile question.
    if ($phase === 'profile') {
        $prompt .= "PHASE: PROFILE — The user typed a free-form response to a profile question.\n";
        $prompt .= "Extract profile data from their answer. Set profile_hints with the relevant fields.\n";
        $prompt .= "Keep your response warm and brief (1-2 sentences). The server will present the next question.\n\n";
    } elseif ($phase === 'floor') {
        $prompt .= "PHASE: FLOOR — Establish baseline. Present the question below. Follow branching rules.\n\n";
    } elseif ($phase === 'ceiling') {
        $prompt .= "PHASE: CEILING — Find upper bounds. Detect hard walls. Present the question below.\n\n";
    } elseif ($phase === 'zone') {
        if ($is_complete) {
            $prompt .= "PHASE: ZONE — ASSESSMENT READY TO COMPLETE. Set phase_signal to session_complete. Give personalized summary.\n\n";
        } else {
            $prompt .= "PHASE: ZONE — Pinpoint placement. Ask targeted questions for stages " . max(0, (int)$current_stage - 1) . "-" . min(10, (int)$current_stage + 1) . ".\n\n";
        }
    } elseif ($phase === 'growth') {
        $prompt .= "PHASE: GROWTH — The user has hit a hard wall and assessment questions are exhausted.\n";
        $prompt .= "Present the growth question below warmly and encouragingly. This is about content routing, not diagnosis.\n";
        $prompt .= "After they select a choice, the system surfaces relevant content automatically.\n";
        $prompt .= "Be celebratory about the work they've done so far. Frame the wall as a natural part of the growth journey.\n";
        $prompt .= "After the growth question is answered, set phase_signal to session_complete.\n\n";
    }

    // ── Current Question (structured JSON) ──
    if ($next_question) {
        $prompt .= build_question_context($next_question);
    } else {
        // No matrix questions left — check for unresolved items the AI can cover conversationally
        $stage_unresolved_items = [];
        $stage_unresolved_kpis = [];
        if ($phase !== 'profile') {
            $gap_stage = get_stage((int)$current_stage);
            if ($gap_stage) {
                foreach (['mindset', 'toolset', 'skillset'] as $dim) {
                    foreach ($gap_stage[$dim] ?? [] as $item) {
                        $id = $item['id'] ?? '';
                        if (!$id) continue;
                        $conf = $instance_data['responseMeta'][$id]['confidence'] ?? 0;
                        if (!isset($instance_data['responses'][$id]) || $conf < 0.60) {
                            $stage_unresolved_items[] = [
                                'id' => $id,
                                'dimension' => $dim,
                                'label' => $item['label'] ?? $id,
                                'trueSignal' => $item['trueSignal'] ?? '',
                                'falseSignal' => $item['falseSignal'] ?? '',
                            ];
                        }
                    }
                }
                $kpi_responses = (array)($instance_data['kpiResponses'] ?? []);
                foreach ($gap_stage['kpis'] ?? [] as $kpi) {
                    $kpi_id = $kpi['id'] ?? '';
                    if (!$kpi_id) continue;
                    $kpi_level = is_array($kpi_responses[$kpi_id] ?? null) ? ($kpi_responses[$kpi_id]['value'] ?? $kpi_responses[$kpi_id]['level'] ?? 'none') : ($kpi_responses[$kpi_id] ?? 'none');
                    if ($kpi_level === 'none' || $kpi_level === 'beginner') {
                        $stage_unresolved_kpis[] = [
                            'id' => $kpi_id,
                            'label' => $kpi['label'] ?? $kpi_id,
                            'currentLevel' => $kpi_level,
                        ];
                    }
                }
            }
        }

        if (!empty($stage_unresolved_items)) {
            $prompt .= "NO MORE MATRIX QUESTIONS FOR THIS PHASE.\n";
            $prompt .= "However, the following stage $current_stage items are still UNRESOLVED. Ask ONE conversational question that covers as many of these as possible. Infer responses (with confidence scores) for any items you can assess from the user's answer.\n\n";
            $prompt .= "UNRESOLVED ITEMS (stage $current_stage):\n";
            foreach ($stage_unresolved_items as $ui) {
                $prompt .= "- {$ui['id']} ({$ui['dimension']}): \"{$ui['label']}\"\n";
                $prompt .= "  trueSignal: {$ui['trueSignal']}\n";
                $prompt .= "  falseSignal: {$ui['falseSignal']}\n";
            }
            if (!empty($stage_unresolved_kpis)) {
                $prompt .= "\nUNRESOLVED KPIs (stage $current_stage):\n";
                foreach ($stage_unresolved_kpis as $uk) {
                    $prompt .= "- {$uk['id']}: \"{$uk['label']}\" (current: {$uk['currentLevel']})\n";
                }
                $prompt .= "Listen for signals that indicate beginner/developing/mastery for these KPIs.\n";
            }
            $prompt .= "\nGenerate 3-4 choices based on the trueSignal/falseSignal descriptions above. Include 'I'd like to share more details' as the last option.\n";
            $prompt .= "After this exchange, set phase_signal to advance_phase.\n";
            $prompt .= "Set question_id to null (this is a free-form gap-coverage question, not a matrix question).\n\n";
        } else {
            if ($phase === 'zone' || $is_complete) {
                $prompt .= "NO MORE QUESTIONS. Set phase_signal to session_complete.\n\n";
            } else {
                $prompt .= "NO MORE QUESTIONS FOR THIS PHASE. Set phase_signal to advance_phase.\n\n";
            }
        }
    }

    // ── Current Stage Context ──
    if ($stage_data && $phase !== 'profile') {
        $prompt .= "CURRENT STAGE: " . ($stage_data['name'] ?? "Stage $current_stage") . " — " . ($stage_data['tagline'] ?? '') . "\n";
        if (!empty($stage_data['behavior'])) $prompt .= "Behavior at this stage: " . $stage_data['behavior'] . "\n";
        if (!empty($stage_data['belief'])) $prompt .= "Belief at this stage: " . $stage_data['belief'] . "\n";
        $prompt .= "\n";
    }

    // ── Stage Reference (only for free-form scoring — skip for MC questions to save tokens) ──
    $has_freeform_next = $next_question && (($next_question['answerFormat'] ?? 'free-form') === 'free-form') && empty($next_question['choices']);
    if ($phase !== 'profile' && $has_freeform_next) {
        $prompt .= "STAGE REFERENCE — Use this to set suggested_stage after a free-form answer:\n";
        // Only include nearby stages (current ± 2) to reduce tokens
        $cur = (int)$current_stage;
        for ($si = max(0, $cur - 2); $si <= min(10, $cur + 2); $si++) {
            $s = get_stage($si);
            if (!$s) continue;
            $snum = sprintf('%02d', $si);
            $prompt .= "  $snum: " . ($s['name'] ?? '') . " — " . ($s['tagline'] ?? '') . "\n";
            $prompt .= "      Behavior: " . ($s['behavior'] ?? '') . "\n";
        }
        $prompt .= "\nWhen scoring a free-form answer, compare what the subject described against these stage behaviors. Set suggested_stage to the stage that BEST matches their current reality — not where they aspire to be.\n";
        $prompt .= "If the subject clearly overshoots their current stage (e.g., talks about leading teams but they're at Stage 01), set suggested_stage to WHERE THEY ACTUALLY ARE based on the signals, not where they say they are.\n";
        $prompt .= "If the subject undershoots (e.g., says 'no I don't do that' to a high-stage question), set suggested_stage to where they DO operate.\n\n";
    }

    $prompt .= "If message is [resume], greet warmly and continue.\n";

    return $prompt;
}

/**
 * Get the user's saved AI vendor preference from shard prefs.
 *
 * @param int    $uid  User ID
 * @param string $sid  Shard ID
 * @return string|null Vendor slug or null if no preference saved
 */
function get_user_ai_vendor($uid, $sid) {
    if (!$uid || !$sid) return null;
    $stmt = child_db_query_shard_prepared($sid,
        "SELECT pref_value FROM childapp_user_prefs WHERE user_id = ? AND pref_key = 'ai_vendor'",
        [$uid]
    );
    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
    return $row ? $row['pref_value'] : null;
}

/**
 * Save the user's AI vendor preference to shard prefs.
 *
 * @param int    $uid    User ID
 * @param string $sid    Shard ID
 * @param string $vendor Vendor slug (anthropic, openai, deepseek)
 */
function set_user_ai_vendor($uid, $sid, $vendor) {
    if (!$uid || !$sid) return;
    child_db_query_shard_prepared($sid,
        "INSERT INTO childapp_user_prefs (user_id, pref_key, pref_value) VALUES (?, 'ai_vendor', ?)
         ON DUPLICATE KEY UPDATE pref_value = VALUES(pref_value)",
        [$uid, $vendor]
    );
}

/**
 * Get cost-per-token rates for a vendor/model combination.
 * Rates are per 1M tokens.
 */
function get_vendor_cost_rates($vendor, $model) {
    if ($vendor === 'anthropic') {
        if (stripos($model, 'haiku') !== false) return ['input' => 0.25, 'output' => 1.25];
        return ['input' => 3.0, 'output' => 15.0];
    }
    if ($vendor === 'openai') {
        if (stripos($model, 'mini') !== false) return ['input' => 0.15, 'output' => 0.60];
        return ['input' => 2.5, 'output' => 10.0];
    }
    if ($vendor === 'deepseek') {
        return ['input' => 0.14, 'output' => 0.28];
    }
    return ['input' => 3.0, 'output' => 15.0];
}

/**
 * Call Anthropic's Messages API.
 * Returns ['content' => string, 'vendor' => string, 'model' => string, 'usage' => array] or null on failure.
 */
function call_anthropic_api($api_key, $model, $system_prompt, $messages, $max_tokens, $temperature, $tool_schema = null) {
    $payload = [
        'model'       => $model,
        'max_tokens'  => $max_tokens,
        'temperature' => $temperature,
        'system'      => $system_prompt,
        'messages'    => $messages,
    ];

    // If a tool schema is provided, use Anthropic's tool_use for guaranteed structured JSON
    if ($tool_schema) {
        $payload['tools'] = [$tool_schema];
        $payload['tool_choice'] = ['type' => 'tool', 'name' => $tool_schema['name']];
    }

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: ' . $api_key,
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT    => 60,
    ]);

    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    if ($http_code !== 200 || !$response) {
        error_log("Anthropic API error: HTTP $http_code, cURL: $curl_err, Response: " . substr($response ?: '', 0, 500));
        return null;
    }

    $data = json_decode($response, true);
    if (!$data || empty($data['content'])) return null;

    // Extract content: tool_use blocks return structured JSON, text blocks return raw text
    $text = '';
    $structured = null;
    foreach ($data['content'] as $block) {
        if (($block['type'] ?? '') === 'tool_use' && !empty($block['input'])) {
            // Structured response — encode as JSON string for parse_ai_response compatibility
            $structured = $block['input'];
            $text = json_encode($structured);
        } elseif (($block['type'] ?? '') === 'text') {
            $text .= $block['text'];
        }
    }

    $usage = $data['usage'] ?? [];
    return [
        'content'    => $text,
        'structured' => $structured,
        'raw'        => $data,
        'vendor'     => 'anthropic',
        'model'      => $model,
        'usage'      => [
            'input_tokens'  => $usage['input_tokens'] ?? 0,
            'output_tokens' => $usage['output_tokens'] ?? 0,
        ],
    ];
}

/**
 * Call an OpenAI-compatible API (OpenAI, DeepSeek, etc.).
 * Returns ['content' => string, 'vendor' => string, 'model' => string, 'usage' => array] or null on failure.
 */
function call_openai_compatible_api($vendor, $api_key, $model, $endpoint, $system_prompt, $messages, $max_tokens, $temperature, $tool_schema = null) {
    // OpenAI format: system prompt as first message
    $oai_messages = [['role' => 'system', 'content' => $system_prompt]];
    foreach ($messages as $msg) {
        $oai_messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
    }

    $payload = [
        'model'       => $model,
        'max_tokens'  => $max_tokens,
        'temperature' => $temperature,
        'messages'    => $oai_messages,
    ];

    // Use structured output: OpenAI supports response_format with json_schema,
    // DeepSeek supports response_format with json_object type
    if ($tool_schema) {
        if ($vendor === 'openai') {
            // OpenAI structured outputs — strict JSON schema enforcement
            $payload['response_format'] = get_assessment_response_format_openai();
        } else {
            // DeepSeek and others: use json_object mode (less strict but still forces valid JSON)
            $payload['response_format'] = ['type' => 'json_object'];
        }
    }

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT    => 60,
    ]);

    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    if ($http_code !== 200 || !$response) {
        error_log("$vendor API error: HTTP $http_code, cURL: $curl_err, Response: " . substr($response ?: '', 0, 500));
        return null;
    }

    $data = json_decode($response, true);
    if (!$data || empty($data['choices'][0]['message']['content'])) return null;

    $content = $data['choices'][0]['message']['content'];
    $structured = json_decode($content, true);

    $usage = $data['usage'] ?? [];
    return [
        'content'    => $content,
        'structured' => $structured ?: null,
        'raw'        => $data,
        'vendor'     => $vendor,
        'model'      => $model,
        'usage'      => [
            'input_tokens'  => $usage['prompt_tokens'] ?? 0,
            'output_tokens' => $usage['completion_tokens'] ?? 0,
        ],
    ];
}

/**
 * Vendor endpoints for OpenAI-compatible APIs.
 */
function get_vendor_endpoint($vendor) {
    $endpoints = [
        'openai'   => 'https://api.openai.com/v1/chat/completions',
        'deepseek' => 'https://api.deepseek.com/v1/chat/completions',
    ];
    return $endpoints[$vendor] ?? null;
}

/**
 * Try a single vendor API call. Dispatches to the correct function based on vendor.
 */
function try_vendor_call($vendor, $api_key, $model, $system_prompt, $messages, $max_tokens, $temperature, $tool_schema = null) {
    if (!$api_key) return null;

    if ($vendor === 'anthropic') {
        return call_anthropic_api($api_key, $model, $system_prompt, $messages, $max_tokens, $temperature, $tool_schema);
    }

    $endpoint = get_vendor_endpoint($vendor);
    if (!$endpoint) return null;

    return call_openai_compatible_api($vendor, $api_key, $model, $endpoint, $system_prompt, $messages, $max_tokens, $temperature, $tool_schema);
}

/**
 * Call AI API with automatic vendor fallback.
 *
 * Fallback chain: user's saved vendor → anthropic → openai → deepseek.
 * On success with a different vendor, updates user's preference.
 * On total failure, resets preference to default (anthropic).
 *
 * @param string $system_prompt  System prompt text
 * @param array  $conversation_log  Conversation messages
 * @param array  $ai_config  Multi-vendor config from config.php
 * @return array|null  ['content' => string, 'raw' => array] or null
 */
function call_ai_api($system_prompt, $conversation_log, $ai_config, $tool_schema = null) {
    $vendors     = $ai_config['vendors'] ?? [];
    $max_tokens  = $ai_config['max_tokens'] ?? 1024;
    $temperature = $ai_config['temperature'] ?? 0.7;
    $default     = $ai_config['default_vendor'] ?? 'anthropic';

    if (empty($vendors)) return null;

    // Build messages array
    $messages = [];
    foreach ($conversation_log as $msg) {
        $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
    }

    // Determine user's preferred vendor
    $uid = $_SESSION['user_id'] ?? 0;
    $sid = $_SESSION['shard_id'] ?? '';
    $user_vendor = ($uid && $sid) ? get_user_ai_vendor($uid, $sid) : null;
    $preferred = $user_vendor ?: $default;

    // Build fallback chain: preferred first, then default order, deduplicated
    $chain = array_unique(array_merge([$preferred], [$default, 'anthropic', 'openai', 'deepseek']));

    $result = null;
    $used_vendor = null;

    foreach ($chain as $vendor) {
        $vcfg = $vendors[$vendor] ?? null;
        if (!$vcfg || empty($vcfg['api_key'])) continue;

        $result = try_vendor_call($vendor, $vcfg['api_key'], $vcfg['model'], $system_prompt, $messages, $max_tokens, $temperature, $tool_schema);
        if ($result) {
            $used_vendor = $vendor;
            break;
        }
        error_log("AI fallback: $vendor failed, trying next vendor");
    }

    // All vendors failed — reset preference to default so next request retries from the top
    if (!$result) {
        if ($user_vendor && $user_vendor !== $default) {
            set_user_ai_vendor($uid, $sid, $default);
        }
        return null;
    }

    // If we fell back to a different vendor, persist the new preference
    if ($used_vendor !== $preferred) {
        set_user_ai_vendor($uid, $sid, $used_vendor);
    }

    // Log cost with correct vendor
    $model         = $result['model'];
    $input_tokens  = $result['usage']['input_tokens'] ?? 0;
    $output_tokens = $result['usage']['output_tokens'] ?? 0;
    if (($input_tokens + $output_tokens) > 0 && function_exists('record_cost')) {
        $rates = get_vendor_cost_rates($used_vendor, $model);
        $cost  = ($input_tokens * $rates['input'] / 1000000) + ($output_tokens * $rates['output'] / 1000000);
        record_cost('cogs', getenv('CHILD_APP_SLUG') ?: 'child-app', 'AI assessment chat (' . $model . ')', $cost, [
            'user_id'  => $uid ?: null,
            'vendor'   => $used_vendor,
            'metadata' => json_encode([
                'model'         => $model,
                'vendor'        => $used_vendor,
                'input_tokens'  => $input_tokens,
                'output_tokens' => $output_tokens,
            ]),
        ]);
    }

    return ['content' => $result['content'], 'structured' => $result['structured'] ?? null, 'raw' => $result['raw']];
}

/**
 * Parse AI response into structured data.
 *
 * @param string     $content    Raw text content from the AI
 * @param array|null $structured Pre-parsed structured data from tool_use/json_schema (bypasses text parsing)
 * @return array Parsed response with all expected fields
 */
function parse_ai_response($content, $structured = null) {
    $result = [
        'display_message' => '',
        'question_text' => null,
        'inferred_responses' => [],
        'kpi_signals' => [],
        'profile_hints' => [],
        'session_notes' => [],
        'phase_signal' => 'continue',
        'suggested_stage' => null,
        'choices' => null,
        'question_id' => null,
    ];

    // If we have structured data from tool_use or json_schema, use it directly — no parsing needed
    if ($structured && isset($structured['display_message'])) {
        return array_merge($result, [
            'display_message'    => $structured['display_message'],
            'question_text'      => $structured['question_text'] ?? null,
            'inferred_responses' => $structured['inferred_responses'] ?? [],
            'kpi_signals'        => $structured['kpi_signals'] ?? [],
            'profile_hints'      => $structured['profile_hints'] ?? [],
            'session_notes'      => $structured['session_notes'] ?? [],
            'phase_signal'       => $structured['phase_signal'] ?? 'continue',
            'suggested_stage'    => $structured['suggested_stage'] ?? null,
            'choices'            => !empty($structured['choices']) ? $structured['choices'] : null,
            'question_id'        => $structured['question_id'] ?? null,
        ]);
    }

    // Fallback: parse from raw text (legacy path for non-structured responses)
    $content = trim($content);
    if (str_starts_with($content, '```')) {
        $content = preg_replace('/^```(?:json)?\s*/i', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);
    }

    $json = json_decode($content, true);

    // If direct decode failed, try extracting JSON object from surrounding text
    if (!$json || !isset($json['display_message'])) {
        if (preg_match('/\{(?:[^{}]|(?:\{[^{}]*\}))*"display_message"(?:[^{}]|(?:\{[^{}]*\}))*\}/s', $content, $m)) {
            $json = json_decode($m[0], true);
        }
        if ((!$json || !isset($json['display_message'])) && preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $content, $m)) {
            $json = json_decode($m[1], true);
        }
    }

    if ($json && isset($json['display_message'])) {
        $result['display_message'] = $json['display_message'];
        $result['question_text'] = $json['question_text'] ?? null;
        $result['inferred_responses'] = $json['inferred_responses'] ?? [];
        $result['kpi_signals'] = $json['kpi_signals'] ?? [];
        $result['profile_hints'] = $json['profile_hints'] ?? [];
        $result['session_notes'] = $json['session_notes'] ?? [];
        $result['phase_signal'] = $json['phase_signal'] ?? 'continue';
        $result['suggested_stage'] = $json['suggested_stage'] ?? null;
        $result['choices'] = !empty($json['choices']) ? $json['choices'] : null;
        $result['question_id'] = $json['question_id'] ?? null;
    } else {
        $result['display_message'] = $content;
    }

    return $result;
}

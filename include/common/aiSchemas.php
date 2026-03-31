<?php
/**
 * aiSchemas.php
 * Structured JSON schemas for all AI API calls.
 * Used with Anthropic tool_use and OpenAI response_format to guarantee valid JSON.
 *
 * NEVER call AI without a structured schema. Raw text responses are unreliable.
 */

/**
 * Get the assessment chat response schema.
 * Used for all assessment_chat AI calls (profile, floor, ceiling, zone, growth phases).
 *
 * Anthropic: Passed as a tool definition, forced via tool_choice.
 * OpenAI/DeepSeek: Passed as response_format with json_schema.
 */
function get_assessment_response_schema() {
    return [
        'name' => 'assessment_response',
        'description' => 'Structured response for the ElevateHER assessment chat. Contains the display message, inferred data, and choices.',
        'input_schema' => [
            'type' => 'object',
            'required' => ['display_message', 'question_text', 'inferred_responses', 'kpi_signals', 'profile_hints', 'session_notes', 'phase_signal', 'suggested_stage', 'choices', 'question_id'],
            'properties' => [
                'display_message' => [
                    'type' => 'string',
                    'description' => 'Brief acknowledgment or conversational wrapper (1-2 sentences). Shown in its own bubble BEFORE the question. Do NOT include the question here.',
                ],
                'question_text' => [
                    'type' => ['string', 'null'],
                    'description' => 'The matrix question text to present to the user. Shown in a SEPARATE bubble after display_message. Copy the question exactly from the CURRENT QUESTION section. Set to null only when no question is being presented (e.g., session_complete).',
                ],
                'inferred_responses' => [
                    'type' => 'object',
                    'description' => 'Map of resolvable item IDs to inferred values. ONLY for free-form answers. For MC questions leave as empty object {}.',
                    'additionalProperties' => [
                        'type' => 'object',
                        'properties' => [
                            'value' => ['type' => 'boolean', 'description' => 'Whether the item is true/met for this subject.'],
                            'confidence' => ['type' => 'number', 'description' => 'Confidence 0.0-1.0 in this inference.'],
                            'reasoning' => ['type' => 'string', 'description' => 'Brief explanation of why this was inferred.'],
                        ],
                        'required' => ['value', 'confidence'],
                    ],
                ],
                'kpi_signals' => [
                    'type' => 'object',
                    'description' => 'Map of KPI IDs to observed level. Only for free-form answers.',
                    'additionalProperties' => [
                        'type' => 'object',
                        'properties' => [
                            'level' => [
                                'type' => 'string',
                                'enum' => ['none', 'beginner', 'emerging', 'developing', 'mastery'],
                                'description' => 'Observed KPI level based on the user response.',
                            ],
                        ],
                        'required' => ['level'],
                    ],
                ],
                'profile_hints' => [
                    'type' => 'object',
                    'description' => 'Key-value profile data extracted from the conversation. Keys: lifeStage, ageRange, industry, householdRole, businessTenure, priorDsExperience, teamSize, engagementLevel, productRelationship, primaryMotivation, secondaryMotivations, drivingCircumstance.',
                    'additionalProperties' => ['type' => 'string'],
                ],
                'session_notes' => [
                    'type' => 'array',
                    'description' => 'Observations worth surfacing in the coaching canvas.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'observation' => ['type' => 'string'],
                            'stage' => ['type' => 'string'],
                            'dimension' => ['type' => 'string'],
                            'focus' => ['type' => 'string'],
                            'surfaceInCanvas' => ['type' => 'boolean'],
                            'canvasLabel' => ['type' => 'string'],
                        ],
                        'required' => ['observation'],
                    ],
                ],
                'phase_signal' => [
                    'type' => 'string',
                    'enum' => ['continue', 'advance_phase', 'session_complete'],
                    'description' => 'Signal to the server about phase progression.',
                ],
                'suggested_stage' => [
                    'type' => ['string', 'null'],
                    'description' => 'REQUIRED after scoring a free-form answer. 2-digit stage string "00" to "10". Null when presenting a new question.',
                ],
                'choices' => [
                    'type' => ['array', 'null'],
                    'description' => 'Array of structured choice objects for the user to select from. Should always be provided.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'label' => ['type' => 'string', 'description' => 'Human-readable choice text shown to the user.'],
                            'value' => ['type' => 'string', 'description' => 'Machine value: strong-true, partial-true, partial-false, strong-false, free-form, or profile-key:value.'],
                            'resolves' => [
                                'type' => 'object',
                                'description' => 'Map of resolvable item IDs to inferred values if this choice is selected.',
                                'additionalProperties' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'value' => ['type' => 'boolean'],
                                        'confidence' => ['type' => 'number'],
                                    ],
                                    'required' => ['value', 'confidence'],
                                ],
                            ],
                        ],
                        'required' => ['label', 'value'],
                    ],
                ],
                'question_id' => [
                    'type' => ['string', 'null'],
                    'description' => 'ID of the matrix question being presented, or null for free-form gap-coverage.',
                ],
            ],
        ],
    ];
}

/**
 * Convert our tool schema to OpenAI response_format json_schema format.
 * OpenAI strict mode requires:
 *   - additionalProperties: false on every object
 *   - No type arrays like ["string", "null"] — must use anyOf
 *   - All properties must be in required
 */
function get_assessment_response_format_openai() {
    $schema = get_assessment_response_schema();
    $converted = convert_schema_for_openai($schema['input_schema']);
    return [
        'type' => 'json_schema',
        'json_schema' => [
            'name' => $schema['name'],
            'strict' => true,
            'schema' => $converted,
        ],
    ];
}

/**
 * Recursively convert Anthropic-style schema to OpenAI strict-mode compatible schema.
 */
function convert_schema_for_openai($schema) {
    if (!is_array($schema)) return $schema;

    $out = [];
    foreach ($schema as $key => $value) {
        if ($key === 'type' && is_array($value)) {
            // Convert ["string", "null"] to anyOf format
            if (in_array('null', $value)) {
                $non_null = array_values(array_filter($value, fn($t) => $t !== 'null'));
                $out['anyOf'] = [
                    ['type' => count($non_null) === 1 ? $non_null[0] : $non_null],
                    ['type' => 'null'],
                ];
                continue;
            }
        }

        if ($key === 'properties' && is_array($value)) {
            // Recurse into each property
            $out['properties'] = [];
            foreach ($value as $prop_name => $prop_schema) {
                $out['properties'][$prop_name] = convert_schema_for_openai($prop_schema);
            }
            // OpenAI strict: all properties must be required
            $out['required'] = array_keys($value);
            $out['additionalProperties'] = false;
            continue;
        }

        if ($key === 'additionalProperties' && is_array($value)) {
            // OpenAI doesn't support additionalProperties with a schema in strict mode
            // Skip it — the AI will still produce the right shape from the system prompt
            continue;
        }

        if ($key === 'items' && is_array($value)) {
            $out['items'] = convert_schema_for_openai($value);
            continue;
        }

        if ($key === 'required') {
            // Will be set by properties handler above
            continue;
        }

        $out[$key] = is_array($value) ? convert_schema_for_openai($value) : $value;
    }

    return $out;
}

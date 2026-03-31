# Assessment Engine

> **RE-ENGINEERING NOTES:**
> 1. **assessmentApiActions.php is 1,268 lines** — The `assessment_chat` handler alone contains the entire chat loop: MC resolution, AI calling, instance updating, phase progression, scoring, petal animations, and conversation logging. This single function is doing 8+ distinct jobs. However, **class-based extraction is not viable** — the entire scope model (`global $ai_config`, `child_db_query_shard_prepared()`, `$_POST` dispatch) comes from admin's global pattern and cannot be changed. Refactoring should stay within the flat-function pattern: extract helper functions into `common/` files (e.g., `chatLoopHelpers.php`) rather than classes.
> 2. **questionMatrixHelpers.php is 661 lines with complex coverage scoring** — `pick_next_question()` iterates all unresolved items + KPIs for every question candidate to compute coverage scores. The algorithm is O(questions × items) per chat turn. Pre-compute a coverage index when the instance is loaded.
> 3. **Three separate MC resolution paths** — Profile MC (`resolve_profile_choice`), Assessment MC (`resolve_mc_choice`), and Growth MC (`resolve_growth_choice`) are three distinct code paths with different return shapes. Unify into a single `resolve_choice($phase, $question, $input)` dispatcher.
> 4. **AI is both backstop AND free-form handler** — The system prompt tries to serve two roles: (a) wrapping matrix questions in conversational language, and (b) generating original assessment insights from free-form text. These are fundamentally different tasks that would benefit from different prompts/temperatures.
> 5. **Signal hint parsing is fragile** — `determine_signal_direction()` and `determine_kpi_level()` parse free-text signal hint strings to extract true/false direction and KPI levels. A structured enum in the framework data would be more reliable than string parsing.
>
> **BY DESIGN:** Instance files follow a strict schema (v2.3) defined by the framework spec. The filesystem + DB dual-write is intentional — filesystem is the canonical store for structured JSON, DB row is an index/backup for queries. The schema is enforced by the framework, not ad-hoc.

---

## Assessment Flow Overview

```
User starts assessment
    │
    ▼
Profile Phase ──── MC choices resolve profile fields (zero AI)
    │               pick_next_profile_question() selects from profile matrix
    │               resolve_profile_choice() maps "field:value" to instance
    ▼
Floor Phase ─────  Matrix questions for stages below current
    │               pick_next_question('floor', ...)
    ▼
Ceiling Phase ──── Matrix questions for stages above current
    │               pick_next_question('ceiling', ...)
    ▼
Zone Phase ──────  Matrix questions for current stage band
    │               pick_next_question('zone', ...)
    │               check_assessment_completion() at 80% resolution
    ▼
Growth Phase ───── Activated when hard wall blocks advancement
    │               pick_growth_question() selects content-routing MC
    │               resolve_growth_choice() maps to content catalog
    ▼
Scoring ─────────  compute_all_stage_scores()
    │               compute_placement()
    │               compute_hard_wall_status()
    │               compute_user_state()
    ▼
Complete
```

## Question Matrix (questionMatrixHelpers.php — 661 lines)

### Question Selection

| Function | Phase | Behavior |
|----------|-------|----------|
| `pick_next_profile_question()` | profile | Selects from profile matrix based on unfilled fields |
| `pick_next_question($phase, ...)` | floor/ceiling/zone | Coverage-scored: boosts questions that resolve the most unresolved items; stage-relevant questions boosted +3 if suggested_stage matches |
| `pick_growth_question($stage, ...)` | growth | Selects growth-tier questions for current stage |

### MC Resolution (Deterministic — No AI)

| Function | Input | Output |
|----------|-------|--------|
| `resolve_profile_choice($question, $message)` | Profile MC choice | `{field, value}` pairs for instance profile |
| `resolve_mc_choice($question, $choice_id)` | Assessment MC choice ID | `{resolved_items: [{itemId, value, confidence:1.0}], resolved_kpis: [{kpiId, level}]}` |
| `resolve_growth_choice($question, $choice_input)` | Growth MC choice | `{choice, dimension, content_ids, stage}` |

### Coverage Scoring Algorithm

For each candidate question, compute:
```
score = Σ(unresolved items this question can resolve)
      + Σ(unresolved KPIs this question can resolve)
      + 3 if question.stage == suggested_stage
```

Pick the highest-scoring unasked question.

### Growth Activation

Conditions (all must be true):
1. Hard wall is currently blocking advancement
2. All floor/ceiling/zone questions for current stage exhausted
3. Growth-tier questions exist for current stage and haven't been asked

## Scoring Engine (scoringEngine.php — 279 lines)

| Function | Purpose |
|----------|---------|
| `compute_dimension_score($responses, $stage_items, $dimension)` | Weighted average for mindset/toolset/skillset |
| `compute_overall_score($responses, $stage_items)` | Weighted average of all items in a stage |
| `check_stage_complete($responses, $stage_items, $threshold)` | Overall >= threshold AND no nonNeg items are false |
| `compute_all_stage_scores($responses)` | Per-stage scores for all stages with data |
| `check_hard_wall($wall, $kpi_responses)` | All gating KPIs at developing+ |
| `compute_placement($instance_data)` | `{placedStage, placedDate, assessedThrough, confidence}` |
| `compute_hard_wall_status($kpi_responses)` | Per-wall `{detectedAt, currentlyBlocking, blockingKpis, crossedAt}` |

## Instance Files (instanceFileHelpers.php — 626 lines)

### Storage

```
storage/instances/{hashed_user_id}/{shard_id}/
  ├── instance_ai_2026-03-26.json       (assessment instance)
  └── userState.json                     (computed aggregate)
```

Also indexed in `assessment_instances` table (instance_json column as backup).

### Instance Schema (v2.3)

```json
{
  "schemaVersion": "2.3",
  "assessor": { "type": "ai|coach|self", "model": "...", "sessionId": "..." },
  "subject": { "userId": 1, "who": {}, "what": {}, "why": {} },
  "sessions": [],
  "responses": { "item-id": { "value": true, "answeredAt": "..." } },
  "kpiResponses": { "kpi-id": { "level": "developing", "answeredAt": "..." } },
  "placement": { "placedStage": "03", "confidence": 0.85 },
  "scores": { "03": { "overall": 0.72, "mindset": 0.8, "toolset": 0.65, "skillset": 0.7 } },
  "hardWallStatus": {},
  "recommendations": [],
  "contentGaps": [],
  "coachNotes": []
}
```

### userState Computation

`compute_user_state()` merges all instances for a user:
- Responses: highest confidence wins when same item appears in multiple instances
- Builds `itemProficiency` levels per item
- Computes placement from best instance
- Computes wall status from all KPI responses
- Computes `dimensionStrength` per stage

## AI Integration (aiHelpers.php — 605 lines)

### System Prompt Construction

`build_assessment_system_prompt()` builds a detailed prompt containing:
- Role definition (follow matrix questions strictly)
- Subject profile
- Current assessment state (phase, stage, scores)
- Phase-specific instructions
- Current question context (if matrix question selected)
- Unresolved items (for free-form AI inference)
- Stage reference data

### Multi-Vendor API

| Vendor | Function | Structured Response |
|--------|----------|-------------------|
| Anthropic | `call_anthropic_api()` | tool_use (forced tool call) |
| OpenAI | `call_openai_compatible_api()` | json_schema (strict mode) |
| DeepSeek | `call_openai_compatible_api()` | json_object mode |

Fallback chain: user's saved vendor → default → anthropic → openai → deepseek.

### AI Response Schema (aiSchemas.php — 197 lines)

```json
{
  "display_message": "...",
  "question_text": "...",
  "inferred_responses": [{ "itemId": "...", "value": true, "confidence": 0.7, "reasoning": "..." }],
  "kpi_signals": [{ "kpiId": "...", "level": "developing", "confidence": 0.6 }],
  "profile_hints": [{ "field": "...", "value": "..." }],
  "session_notes": [{ "note": "...", "tag": "..." }],
  "phase_signal": "advance|hold|complete",
  "suggested_stage": "03",
  "choices": [],
  "question_id": "qm-..."
}
```

## Gap Detection (QUESTION_GAP_DETECTION.md)

When `pick_next_question()` returns null but unresolved items remain:

1. **AI Free-Form** — Unresolved items injected into system prompt; AI asks about them directly
2. **Server-Side Phase Enforcement** — Forces phase advancement if AI doesn't
3. **Suggestion Logging** — Gaps logged to instance file under `questionSuggestions[]` for framework authors

# Question Gap Detection System

> **This document describes the ElevateHER assessment engine** that ships as the reference feature set in this child-app template. If your child app doesn't use this engine, you can delete this file.

## Overview

The assessment engine uses a **question matrix** to drive structured evaluation through phases (floor → ceiling → zone → score). When the matrix runs out of questions for a phase but stage items remain unresolved, the system detects this as a **question gap** and activates fallback behaviors to keep the assessment moving.

## How It Works

### 1. Gap Detection Trigger

During each chat turn, `pick_next_question()` selects the next matrix question for the current phase. When it returns `null` (no more questions available), the system checks whether unresolved items remain for the current stage.

**Location:** `include/actions/apiActions/assessmentApiActions.php` — after Step 8 (phase enforcement block)

### 2. AI Free-Form Coverage

When a gap is detected, unresolved stage items and KPIs are injected directly into the AI system prompt. The AI then asks free-form questions to try to resolve those items without a predefined matrix question.

**Location:** `include/common/aiHelpers.php` — inside `build_assessment_prompt()`

The injected prompt includes:
- Each unresolved item's ID, dimension, label, trueSignal, and falseSignal
- Each unresolved KPI's ID, label, and current level
- Instruction for the AI to set `phase_signal: "advance_phase"` after the exchange

### 3. Server-Side Phase Enforcement

If the AI still doesn't advance the phase (ignores `phase_signal`), a server-side safety net forces advancement when `pick_next_question()` returns null and all matrix questions for the phase have been exhausted.

**Location:** `include/actions/apiActions/assessmentApiActions.php` — phase enforcement block after AI response processing

### 4. Question Suggestion Logging

Every detected gap is logged to the instance file under `questionSuggestions[]` so framework authors can review and add new matrix questions to fill the gaps.

**Location:** `include/actions/apiActions/assessmentApiActions.php` — Step 8b

Each logged suggestion contains:

```json
{
  "suggestedAt": "2026-03-25T14:30:00+00:00",
  "sessionId": "abc123",
  "phase": "floor",
  "stage": "00",
  "unresolvedItems": [
    {
      "id": "s00-i-003",
      "dimension": "knowledge",
      "label": "Understands basic safety protocols"
    }
  ],
  "note": "Matrix questions exhausted for phase 'floor' but 3 item(s) in stage 00 remain unresolved. Consider adding targeted questions."
}
```

## Reviewing Suggestions

### Where to Find Them

Suggestions are stored per-user in the instance file (JSON on filesystem):

```
storage/instances/{user_id}/instance.json → questionSuggestions[]
```

### What to Do With Them

1. **Review** the `unresolvedItems` array — these are items the matrix couldn't resolve
2. **Identify patterns** — if the same items appear across multiple users, the matrix has a systematic gap
3. **Author new questions** — add questions to the framework's question matrix targeting those items
4. **Map signals** — ensure new questions have `trueSignal` and `falseSignal` mappings that resolve the identified items

### Adding New Matrix Questions

New questions go into the framework's question definitions and are synced to the DB via framework sync. Each question needs:

- `phase` — which phase it belongs to (floor/ceiling/zone)
- `stage` — which stage's items it evaluates
- `dimension` — knowledge, skill, attitude, or behavior
- `text` — the question prompt
- `choices` — answer options with signal mappings
- `priority` — ordering weight within the phase

## Debug Output

The `_debug` object in API responses includes:
- `phase_enforced` — boolean, true if server forced a phase change
- `question_gaps` — array of gap details detected this turn

These are visible in browser console via the `[chat]` prefixed debug logging in `chat-overlay.js`.

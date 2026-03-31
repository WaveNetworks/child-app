# Content System

> **RE-ENGINEERING NOTES:**
> 1. **Content state resolution is a 7-branch decision tree in a single function** — `resolve_content_state()` (in contentHelpers.php, 432 lines total) evaluates visibility, in-progress, proficiency, completion, recommendation score, then defaults. Each branch has sub-conditions. Extract into a proper state machine with named transitions.
> 2. **Recommendation scoring mixes concerns** — `get_content_recommendations()` computes three scoring factors (item-need, dimension-balance, sequence-position) inline. The 1.5x dimension balance boost is a magic number. Make scoring weights configurable via framework data.
> 3. **`update_content_progress()` recomputes entire userState** — Calling `compute_user_state()` after every content status change is expensive. Only the content-related portions of userState need updating.
> 4. **Dual-source loading for rules/sequences** — Content rules and sequences try filesystem first, then DB fallback. This is inconsistent with stage data which is DB-only via `frameworkHelpers.php`. Standardize on one source.

---

## Content States

Every catalog item resolves to exactly one of 7 states:

```
locked         Stage not accessible (outside ±1 range or wall-gated)
in-progress    User has started but not completed
mastered       User's item proficiency is at mastery level
proficient     User's item proficiency is at developing level
completed      User has marked as completed or skipped
recommended    Recommendation score > 0 (content matches user needs)
available      Default state (visible, no special status)
```

### Resolution Order

```
1. Is stage accessible?                    NO → locked
2. Has user started this item?             YES → in-progress
3. Is user proficient at mastery?          YES → mastered
4. Is user proficient at developing?       YES → proficient
5. Has user completed or skipped?          YES → completed
6. Does item have recommendation score?    YES → recommended
7. Default                                     → available
```

## Content Recommendations

`get_content_recommendations()` scores items in the `recommended` state:

| Factor | Weight | Description |
|--------|--------|-------------|
| `rec-item-need` | 1.0 | Item's `itemRefs` overlap with unresolved diagnostic items |
| `rec-dimension-balance` | 1.5x | Boost for items in user's weakest dimension |
| `rec-sequence-position` | varies | Items earlier in content sequence score higher |

Returns sorted by score descending.

## Content Progress Tracking

### States

```
started → completed
started → skipped
```

### Functions

| Function | Purpose |
|----------|---------|
| `update_content_progress($user_id, $shard_id, $catalog_item_id, $new_status, $notes)` | Insert or update progress record |
| `get_content_progress_list($user_id, $shard_id, $status)` | List progress records with optional status filter |
| `get_user_completed_items($user_id, $shard_id)` | Get IDs of completed/skipped items |

### Action Handlers (contentActions.php — 61 lines)

- `content_start` — Marks started, logs `content_started`
- `content_complete` — Marks completed with notes, logs `content_completed`
- `content_skip` — Marks skipped, logs `content_skipped`

## Content Catalog

Loaded from framework via `get_content_catalog()`.

```json
{
  "id": "cat-001",
  "title": "...",
  "description": "...",
  "type": "video|article|worksheet|training",
  "duration": "15 min",
  "dimension": "mindset|toolset|skillset",
  "stages": ["03", "04"],
  "itemRefs": ["diag-s03-m01", "diag-s03-t02"],
  "learningObjectives": ["..."],
  "thumbnail": "...",
  "ctaUrl": "..."
}
```

## Growth Phase Content Routing

When a growth MC choice is resolved:
1. `resolve_growth_choice()` returns `{dimension, content_ids, stage}`
2. `get_content_by_ids($content_ids)` resolves catalog items
3. Content items displayed to user with thumbnails and CTAs
4. No diagnostic scoring occurs (growth phase is content-routing only)

## Framework Data Files

| File | Helper |
|------|--------|
| `v5.0_contentCatalog.json` | `get_content_catalog()` |
| `v5.0_contentRules.json` | `get_content_rules()` (filesystem → DB fallback) |
| `v5.0_contentSequence.json` | `get_content_sequence()` (filesystem → DB fallback) |

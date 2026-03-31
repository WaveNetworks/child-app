# Framework System

> **RE-ENGINEERING NOTES:**
> 1. **Framework loaded from DB on every helper call** — `get_stage()`, `get_content_catalog()`, `get_question_matrix()`, etc. each run a DB query. While some use static caching within a single request, there's no shared cache object. A single `FrameworkData` class that loads once and provides all accessors would cut DB queries significantly.
> 2. ~~**Dual-source loading (filesystem + DB)**~~ — **BY DESIGN.** `contentHelpers.php` tries filesystem first, then falls back to DB. This is intentional: on fresh install or after framework update, the DB tables are empty until `frameworkSync` runs. Removing the filesystem fallback would cause null returns and break the assessment engine, explorer, and content system. **Do not standardize to DB-only.**
> 3. **`find_item_stage_dimension()` scans all stages** — To locate which stage/dimension owns an item, this function iterates through all 11 stages and all 3 dimension arrays. This runs in the hot path during assessment scoring. Pre-building a lookup map during framework sync would be O(1) instead of O(n).
> 4. **SHA256 versioning without schema validation** — `frameworkSync.php` hashes file contents for change detection but doesn't validate that the loaded JSON conforms to the expected schema. A malformed framework file will only fail at runtime during assessment.

---

## Framework Data Flow

```
dswa-framework/framework/          (local JSON files)
        │
        ▼
frameworkSync.php                  (sync_from_local)
        │  SHA256 checksum comparison
        │  Deactivate old version → Insert new version
        ▼
framework_versions table           (version_id, checksum, active flag)
framework_data table               (version_id, file_type, file_key, json_data)
        │
        ▼
frameworkHelpers.php               (runtime access via get_stage(), get_content_catalog(), etc.)
```

## frameworkSync.php (118 lines)

| Function | Purpose |
|----------|---------|
| `sync_framework_data()` | Entry point; dispatches to `sync_from_local()` or returns error for remote |
| `sync_from_local($base_path)` | Loads JSON files from disk, computes SHA256 checksum, skips if unchanged, deactivates old version, inserts new version + all framework_data rows |
| `rollback_framework($target_version_id)` | Deactivate current, activate target version |
| `get_framework_versions()` | List all versions ordered by loaded_at DESC |

**File type mapping during sync:**
- `v5.0_header.json` → type: `header`
- `v5.0_tiers.json` → type: `tiers`
- `v5.0_contentCatalog.json` → type: `content_catalog`
- `v5.0_contentRules.json` → type: `content_rules`
- `v5.0_contentSequence.json` → type: `content_sequence`
- `v5.0_questionMatrix.json` → type: `question_matrix`
- `v5.0_profileQuestions.json` → type: `profile_questions`
- `v5.0_quotesBank.json` → type: `quotes_bank`
- `v5.0_stage_00.json` through `v5.0_stage_10.json` → type: `stage`, key: `00`–`10`

## frameworkHelpers.php (168 lines)

All functions query the active framework version from `framework_data` table.

| Function | Returns |
|----------|---------|
| `get_active_framework_version()` | Active version record |
| `get_framework_data($file_type, $file_key)` | Decoded JSON for a specific file type |
| `get_all_stages()` | Array of 11 stages (00–10) |
| `get_stage($stage_num)` | Single stage data |
| `get_content_catalog()` | Full content catalog |
| `get_question_matrix()` | Full question matrix |
| `get_quotes_bank()` | Quotes bank |
| `get_framework_header()` | Header (contains hardWalls, tiers, etc.) |
| `get_tiers()` | Tier definitions |
| `get_hard_walls()` | Hard wall definitions (extracted from header) |
| `find_item_stage_dimension($item_id)` | `{stage, dimension}` — scans all stages (cached per-request) |
| `find_kpi_meta($kpi_id)` | KPI metadata — scans all stages (cached per-request) |
| `get_contextual_quote($stage, $dimension)` | Random matching quote with fallback |

## Framework JSON Structure

### Stage File (v5.0_stage_XX.json)
```json
{
  "stageNumber": "00",
  "title": "...",
  "description": "...",
  "typicalBehavior": "...",
  "coreBelief": "...",
  "stuckText": "...",
  "unlockText": "...",
  "mindset": [{ "id": "diag-s00-m01", "label": "...", "weight": 1.0, "trueSignal": "...", "falseSignal": "...", "nonNeg": false }],
  "toolset": [...],
  "skillset": [...],
  "kpis": [{ "id": "kpi-s00-01", "label": "...", "levels": { "beginner": "...", "developing": "...", "mastery": "..." } }]
}
```

### Hard Walls (from header)
```json
{
  "hardWalls": [
    { "afterStage": "03", "label": "Wall 1", "gatingKpis": ["kpi-s03-01", "kpi-s03-02"] }
  ]
}
```

### Tiers
```
00–03 = starter | 04–06 = builder | 07–08 = pro | 09–10 = elite
```

## Cron: framework_sync.php

Daily cron that calls `sync_framework_data()` if `$framework_config['auto_sync']` is enabled.

## Admin Action: frameworkActions.php (45 lines)

- `sync_framework` — Admin-only manual sync trigger
- `rollback_framework` — Admin-only rollback to previous version

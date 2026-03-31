# Coach & Team System

> **RE-ENGINEERING NOTES:**
> 1. **Coach notes stored in instance JSON, not a dedicated table** — `coachNoteActions.php` appends notes to the seller's `instance_data.coachNotes[]` array. This means notes can't be queried independently and the instance file grows unbounded. However, this keeps notes co-located with the assessment data they reference.
> 2. **Team API caching is coarse** — `cohort_summary` and `cohort_by_coach` are cached for 1 hour with no invalidation. A coach adding a new seller won't see them in the cohort view for up to an hour.
>
> **BY DESIGN:** Each user is assigned one admin shard and one child shard at registration and never moves. Cross-shard relationship writes (coach shard + seller shard) are intentional — each party needs a local copy for fast reads from their own shard. The daily `cohort_snapshot` cron pre-computes aggregates so the multi-shard iteration in cohort APIs is bounded.

---

## Invite System (coachHelpers.php — 133 lines + inviteActions.php — 112 lines)

### Flow

```
Coach generates invite token (Pro+ tier required)
    │  generate_invite_token() — 256-bit entropy, rate limited (max 20 active)
    ▼
Invite link URL: /auth/register_invite.php?token=...
    │
    ▼
Seller clicks link → register_invite.php
    │  validate_invite_token() — checks active, expiration, usage limit
    ▼
Seller registers/logs in → accept_invite
    │  redeem_invite_token() — creates coach_relationships on BOTH shards
    │  Sends notifications to both parties
    ▼
Relationship active
```

### Action Handlers

| Action | Role | Description |
|--------|------|-------------|
| `generate_invite` | Coach (Pro+) | Create token with optional max_uses, expires_hours |
| `disable_invite` | Coach | Deactivate link (creator-only) |
| `accept_invite` | Seller | Redeem token, create relationship |
| `decline_invite` | Seller | Flash info message, no relationship |
| `end_relationship` | Either | End on both shards |

### Tables

- `invite_links` (child main) — token, coach_user_id, type, max_uses, use_count, expires_at, is_active
- `coach_relationships` (child shard) — coach_user_id, seller_user_id, shard_ids, status, created_at, ended_at

## Coach Notes (coachNoteActions.php — 92 lines)

| Action | Description |
|--------|-------------|
| `add_coach_note` | Adds note to seller's instance (stage, dimension, text, visibility). Verifies active relationship. Sends notification if shared. |
| `delete_coach_note` | Removes note by noteId (coach-owned only) |

Notes are stored in `instance_data.coachNotes[]`:
```json
{
  "noteId": "note-abc123",
  "coachUserId": 5,
  "stage": "03",
  "dimension": "mindset",
  "text": "...",
  "visibility": "shared|private",
  "createdAt": "2026-03-26T12:00:00Z"
}
```

## Team API (teamApiActions.php — 285 lines)

### Endpoints

| Action | Role | Description |
|--------|------|-------------|
| `team_list` | Coach | Active sellers with placement, scores, wall status, last_active |
| `team_member_detail` | Coach | Full seller detail (instance data, content progress). Relationship verified. |
| `cohort_summary` | Org Lead | Aggregated KPIs: total_sellers, active_7d, avg_stage, at_wall_count, content_engagement_rate. Cached 1hr. |
| `cohort_by_coach` | Org Lead | Per-coach stats: team_size, avg_stage, avg_score, at_wall, active_7d. Cached 1hr. |
| `cohort_timeline` | Coach/Org Lead | Historical snapshots (max 365 days) |
| `wall_distribution` | Coach/Org Lead | Hard wall blocking counts by label |

### Helper Functions (cohortProgressHelpers.php — 166 lines)

| Function | Description |
|----------|-------------|
| `get_cohort_progress_timeline()` | Aggregate progress_snapshots across all shards by day |
| `get_coach_team_progress()` | Team members' growth rates + milestones |
| `compute_content_impact($catalog_item_id)` | Compare avg score of users who completed item vs. those who didn't |
| `get_wall_crossing_rates()` | Per-wall crossing rate (%) from milestone snapshots |

## Progress Snapshots (progressHelpers.php — 151 lines)

| Function | Description |
|----------|-------------|
| `capture_progress_snapshot()` | Snapshot user's state (stage, scores, items, content, walls) |
| `get_user_progress_timeline()` | Query snapshot history with date range |
| `get_user_milestones()` | Milestone-type snapshots |
| `compute_growth_rate($user_id, $shard_id, $days)` | Stage delta, score delta, dimension deltas over time window |

Snapshots stored in `progress_snapshots` table (child shard) as JSON.

## Role Hierarchy

```
org_lead > coach > seller
```

| Function | Purpose |
|----------|---------|
| `get_user_role()` | Returns roles ordered by priority |
| `is_coach()` | True if coach or org_lead |
| `is_org_lead()` | True if org_lead |
| `can_be_coach()` | True if tier >= Pro |
| `ensure_coach_role()` | Inserts coach role if missing |
| `ensure_seller_role()` | Inserts seller role if missing |

## Tier System

| Stages | Tier | Can Coach? |
|--------|------|-----------|
| 00–03 | Starter | No |
| 04–06 | Builder | No |
| 07–08 | Pro | Yes |
| 09–10 | Elite | Yes |

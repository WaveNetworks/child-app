# Actions Reference

> **RE-ENGINEERING NOTES:**
> 1. ~~**No action registry or routing**~~ — Actions are dispatched by matching `$_REQUEST['action']` against flat `if` blocks. This mirrors admin's dispatch pattern exactly and **cannot be replaced** without forking admin. A lightweight registry-as-wrapper (adding unknown-action logging at the end of `api/index.php`) is safe; replacing the dispatch mechanism is not.
> 2. **Mixed auth models** — `memberActions/` files assume session auth (via common.php's session guard), but `apiActions/` files must self-check auth with `$_SESSION['user_id']`. This is implicit and easy to forget — an unauthenticated API action would silently execute. This is admin's pattern and cannot be structurally changed, but a guard comment convention at the top of each API action file would help.
> 3. **Action handler return shapes are inconsistent** — Some set `$_SESSION['success']` + redirect, others set `$results['data']` for JSON, and a few do both. Member actions use session flash; API actions use `$results`. This split is by design (member = form POST + redirect, API = JSON response), but some actions blur the line.

---

## Member Actions (12 files, 35 handlers)

### assessmentActions.php (105 lines)

| Action | Method | Description |
|--------|--------|-------------|
| `assessment_start` | POST | Create/load instance + create/resume session |
| `assessment_complete` | POST | Final scoring pipeline, mark session complete |
| `assessment_state` | GET/POST | Read-only state query |

### coachNoteActions.php (92 lines)

| Action | Method | Description |
|--------|--------|-------------|
| `add_coach_note` | POST | Add note to seller's instance (relationship-verified) |
| `delete_coach_note` | POST | Remove note by ID (coach-owned only) |

### contentActions.php (61 lines)

| Action | Method | Description |
|--------|--------|-------------|
| `content_start` | POST | Mark content started |
| `content_complete` | POST | Mark content completed (with notes) |
| `content_skip` | POST | Mark content skipped |

### exampleActions.php (21 lines)

| Action | Method | Description |
|--------|--------|-------------|
| `sendFeedback` | POST | Demo modal validation pattern |

### feedbackActions.php (134 lines)

| Action | Method | Description |
|--------|--------|-------------|
| `submitUserFeedback` | POST | Submit feedback (type, message, page URL) |
| `getUserFeedback` | POST | Paginated user feedback with filters |
| `upvoteUserFeedback` | POST | Toggle upvote on feedback item |
| `getRoadmapData` | POST | Grouped change requests by status |

### frameworkActions.php (45 lines)

| Action | Method | Description |
|--------|--------|-------------|
| `sync_framework` | POST | Admin-only framework sync |
| `rollback_framework` | POST | Admin-only rollback to previous version |

### inviteActions.php (112 lines)

| Action | Method | Description |
|--------|--------|-------------|
| `generate_invite` | POST | Create invite token (Pro+ coach) |
| `disable_invite` | POST | Deactivate invite (creator-only) |
| `accept_invite` | POST | Redeem token, create relationship |
| `decline_invite` | POST | Flash info, no relationship |
| `setUserTier` | POST | Dev/test utility to set tier manually |

### itemActions.php (143 lines)

| Action | Method | Description |
|--------|--------|-------------|
| `saveItem` | POST | Create or update item (ownership-verified) |
| `deleteItem` | POST | Delete item (ownership-verified) |
| `updateItemStatus` | POST | Change status inline |

### onboardingActions.php (78 lines)

| Action | Method | Description |
|--------|--------|-------------|
| `save_onboarding` | POST | 3-step profile collection, instance creation, invite redemption |

### privacyActions.php (229 lines)

| Action | Method | Description |
|--------|--------|-------------|
| `updateConsent` | POST | Toggle consent preferences |
| `requestDataExport` | POST | Build and serve data export (JSON/CSV) |
| `downloadExport` | GET | Serve completed export file |
| `requestAccountDeletion` | POST | 30-day deletion with "DELETE" confirmation |
| `cancelAccountDeletion` | POST | Cancel pending deletion |
| `revokeSession` | POST | Revoke single device session |
| `revokeAllSessions` | POST | Revoke all other sessions |

### profileActions.php (163 lines)

| Action | Method | Description |
|--------|--------|-------------|
| `updateProfile` | POST | Update name/email (admin shard) |
| `changePassword` | POST | Change password (blocks OAuth-only) |
| `updateAppPreferences` | POST | Save user prefs to child shard |

### relationshipActions.php (38 lines)

| Action | Method | Description |
|--------|--------|-------------|
| `end_relationship` | POST | End coach-seller relationship on both shards |

---

## API Actions (4 files, 12 handlers)

### assessmentApiActions.php (1,268 lines)

| Action | Method | Description |
|--------|--------|-------------|
| `assessment_chat` | POST | Main chat engine: MC resolution, AI call, scoring, phase progression |
| `assessment_state` | GET | Read-only state query (duplicate of member action) |
| `get_user_state` | POST | Load computed userState |
| `get_user_timeline` | POST | Progress timeline + milestones + growth rate |

### exampleApiActions.php (52 lines)

| Action | Method | Description |
|--------|--------|-------------|
| `getItems` | POST | Paginated item list with status filter |

### searchActions.php (38 lines)

| Action | Method | Description |
|--------|--------|-------------|
| `liveSearch` | POST | Nav bar search: items by title/description (min 2 chars, limit 8) |

### teamApiActions.php (285 lines)

| Action | Method | Description |
|--------|--------|-------------|
| `team_list` | POST | Coach's active sellers |
| `team_member_detail` | POST | Detailed seller profile (relationship-verified) |
| `cohort_summary` | POST | Org lead aggregated KPIs (cached 1hr) |
| `cohort_by_coach` | POST | Per-coach stats (cached 1hr) |
| `cohort_timeline` | POST | Historical snapshots |
| `wall_distribution` | POST | Hard wall blocking counts |

---

## Action Dispatch Pattern

```php
// In any action file (auto-included via glob):
if ($_REQUEST['action'] === 'myAction') {
    // validate inputs
    // perform operation
    // set $_SESSION['success'] or $_SESSION['error']
    // redirect (member) or set $results['data'] (API)
}
```

## Total: 47 action handlers across 16 files

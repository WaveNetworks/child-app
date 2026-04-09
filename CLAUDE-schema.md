# child-app -- Schema

## Database Architecture

```
+-------------------------------------------------+
|  Admin Databases (read-only from child app)      |
+-------------------------------------------------+
|  admin_main        | users, auth, feedback,      |
|                    | change_requests, consent     |
|  admin_shard_1     | user_profile, notifications  |
|  admin_shard_2     | user_profile, notifications  |
+-------------------------------------------------+

+-------------------------------------------------+
|  Child Databases (read/write)                    |
+-------------------------------------------------+
|  child_main        | items, app_cache,           |
|                    | invite_links,               |
|                    | framework_versions,          |
|                    | framework_data,              |
|                    | cohort_snapshots,            |
|                    | app_settings                 |
|                    |                              |
|  child_shard_1     | assessment_instances,        |
|  child_shard_2     | assessment_sessions,         |
|                    | coach_relationships,          |
|                    | content_progress,             |
|                    | user_history,                 |
|                    | childapp_user_prefs,          |
|                    | progress_snapshots,           |
|                    | user_roles                    |
+-------------------------------------------------+
```

Each user is assigned one admin shard and one child shard at registration and stays there permanently. No foreign keys across shards -- referential integrity is enforced at the application layer.

## Child Main Tables

### items
| Column | Type | Description |
|--------|------|-------------|
| id | INT AUTO_INCREMENT | Primary key |
| user_id | INT | Owner (references admin user) |
| title | VARCHAR(255) | Item title |
| description | TEXT | Item description |
| status | ENUM('draft','active','archived') | Current status |
| created_at | DATETIME | Creation timestamp |
| updated_at | DATETIME | Last update timestamp |

### app_cache
| Column | Type | Description |
|--------|------|-------------|
| cache_key | VARCHAR(100) | Unique cache key (PK) |
| data_json | LONGTEXT | JSON-encoded data |
| expires_at | DATETIME | TTL expiration |

### invite_links
| Column | Type | Description |
|--------|------|-------------|
| id | INT AUTO_INCREMENT | Primary key |
| token | VARCHAR(64) | Unique invite token |
| creator_user_id | INT UNSIGNED | Creating coach |
| creator_shard_id | VARCHAR(20) | Creator's shard |
| type | ENUM('coach_to_seller','org_to_coach') | Invite type |
| max_uses | INT UNSIGNED | Usage limit (NULL = unlimited) |
| use_count | INT UNSIGNED | Current usage count |
| expires_at | DATETIME | Expiration (NULL = never) |
| is_active | TINYINT(1) | Active flag |
| created_at | DATETIME | Creation timestamp |

### framework_versions
| Column | Type | Description |
|--------|------|-------------|
| id | INT AUTO_INCREMENT | Primary key |
| version_label | VARCHAR(50) | Version label |
| schema_version | VARCHAR(20) | Schema version (default '5.0') |
| source | ENUM('local','remote') | Sync source |
| checksum | VARCHAR(64) | SHA256 of all framework files |
| file_checksums | JSON | Per-file checksums |
| is_active | TINYINT(1) | Active flag (only one active) |
| loaded_by | INT UNSIGNED | User who triggered sync |
| loaded_at | DATETIME | Sync timestamp |
| notes | TEXT | Sync notes |

### framework_data
| Column | Type | Description |
|--------|------|-------------|
| id | INT AUTO_INCREMENT | Primary key |
| version_id | INT UNSIGNED | References framework_versions.id (FK cascade) |
| file_type | VARCHAR(50) | Type: header, stage, content_catalog, etc. |
| file_key | VARCHAR(100) | Sub-key (e.g., stage number "03") |
| data_json | LONGTEXT | Full JSON content |
| checksum | VARCHAR(64) | File checksum |
| created_at | DATETIME | Creation timestamp |

### cohort_snapshots
| Column | Type | Description |
|--------|------|-------------|
| id | INT AUTO_INCREMENT | Primary key |
| snapshot_date | DATE | Date of snapshot (unique) |
| data_json | JSON | JSON aggregate data |
| created_at | DATETIME | Creation timestamp |

### app_settings
| Column | Type | Description |
|--------|------|-------------|
| setting_key | VARCHAR(100) | Setting name (PK) |
| setting_value | TEXT | Setting value |
| updated | DATETIME | Last update timestamp |

### app_roles
| Column | Type | Description |
|--------|------|-------------|
| id | INT AUTO_INCREMENT | Primary key |
| user_id | INT UNSIGNED | User |
| shard_id | VARCHAR(20) | User's shard |
| role | ENUM('seller','coach','org_lead') | Role |
| granted_by | INT UNSIGNED | Granting user |
| granted_at | DATETIME | Assignment timestamp |

## Child Shard Tables

### assessment_instances
| Column | Type | Description |
|--------|------|-------------|
| id | INT AUTO_INCREMENT | Primary key |
| user_id | INT | Subject user |
| shard_id | VARCHAR(16) | Shard identifier |
| instance_type | VARCHAR(32) | Type: ai, coach, self |
| instance_json | LONGTEXT | Full instance data (v2.3 schema) |
| status | VARCHAR(16) | active, completed |
| created_at | DATETIME | Creation timestamp |
| updated_at | DATETIME | Last update timestamp |

Coach notes live in `instance_json` to stay co-located with the assessment data they annotate.

### assessment_sessions
| Column | Type | Description |
|--------|------|-------------|
| id | INT AUTO_INCREMENT | Primary key |
| user_id | INT | Subject user |
| instance_id | INT | References assessment_instances.id |
| phase | VARCHAR(16) | Current phase: profile, floor, ceiling, zone, growth |
| conversation_log | LONGTEXT | JSON chat history |
| completed_at | DATETIME | NULL if active |
| created_at | DATETIME | Creation timestamp |

### coach_relationships
| Column | Type | Description |
|--------|------|-------------|
| id | INT AUTO_INCREMENT | Primary key |
| coach_user_id | INT | Coach user |
| coach_shard_id | VARCHAR(16) | Coach's shard |
| seller_user_id | INT | Seller user |
| seller_shard_id | VARCHAR(16) | Seller's shard |
| status | VARCHAR(16) | active, ended |
| created_at | DATETIME | Creation timestamp |
| ended_at | DATETIME | End timestamp |

### content_progress
| Column | Type | Description |
|--------|------|-------------|
| id | INT AUTO_INCREMENT | Primary key |
| user_id | INT | User |
| catalog_item_id | VARCHAR(32) | References content catalog |
| status | VARCHAR(16) | started, completed, skipped |
| notes | TEXT | Completion notes |
| created_at | DATETIME | Start timestamp |
| updated_at | DATETIME | Last update |

Note: content_progress is on the shard (per-user) but items table is on child main. Queries needing both require application-level joining.

### user_history
| Column | Type | Description |
|--------|------|-------------|
| id | INT AUTO_INCREMENT | Primary key |
| user_id | INT | User |
| event_type | VARCHAR(64) | Event name (item_created, content_started, etc.) |
| event_data | LONGTEXT | JSON event payload |
| ip_address | VARCHAR(45) | Client IP |
| user_agent | VARCHAR(512) | Browser user agent |
| created_at | DATETIME | Event timestamp |

### childapp_user_prefs
| Column | Type | Description |
|--------|------|-------------|
| user_id | INT | User (PK) |
| items_per_page | INT | Pagination preference |
| default_status | VARCHAR(16) | Default item status |
| email_on_item_change | TINYINT | Email notification toggle |
| bio | TEXT | User biography |
| ai_vendor | VARCHAR(16) | Preferred AI vendor |

### progress_snapshots
| Column | Type | Description |
|--------|------|-------------|
| id | INT AUTO_INCREMENT | Primary key |
| user_id | INT UNSIGNED | User |
| snapshot_type | ENUM('assessment','milestone','periodic') | Snapshot trigger |
| stage | VARCHAR(5) | Current stage |
| overall_score | DECIMAL(5,4) | Overall score |
| mindset_score | DECIMAL(5,4) | Mindset dimension score |
| toolset_score | DECIMAL(5,4) | Toolset dimension score |
| skillset_score | DECIMAL(5,4) | Skillset dimension score |
| items_resolved | INT UNSIGNED | Resolved item count |
| items_total | INT UNSIGNED | Total item count |
| hard_wall_id | VARCHAR(50) | Blocking hard wall |
| content_started | INT UNSIGNED | Content items started |
| content_completed | INT UNSIGNED | Content items completed |
| session_count | INT UNSIGNED | Assessment sessions completed |
| snapshot_data | JSON | Full JSON snapshot (stage, scores, items, walls) |
| created_at | DATETIME | Snapshot timestamp |

### user_roles
| Column | Type | Description |
|--------|------|-------------|
| id | INT AUTO_INCREMENT | Primary key |
| user_id | INT | User |
| role | VARCHAR(16) | seller, coach, org_lead |
| created_at | DATETIME | Assignment timestamp |

## History / Activity Logging

The `user_history` table on each shard records all significant user events:

| event_type | When logged |
|------------|-------------|
| `item_created` | New item saved |
| `item_updated` | Item edited |
| `item_deleted` | Item removed |
| `content_started` | Content item begun |
| `content_completed` | Content item finished |
| `content_skipped` | Content item skipped |
| `assessment_started` | New assessment session |
| `assessment_completed` | Assessment scoring done |
| `coach_linked` | Coach relationship created |
| `coach_unlinked` | Coach relationship ended |
| `profile_updated` | Profile fields changed |
| `consent_changed` | Privacy consent toggled |
| `data_export` | Data export requested |
| `deletion_requested` | Account deletion requested |
| `deletion_cancelled` | Account deletion cancelled |

Each event stores JSON `event_data` with relevant context (item IDs, old/new values, etc.), plus `ip_address` and `user_agent` for audit.

## GDPR Privacy & Data

### Privacy Page (views/privacy.php)

Standalone GDPR management page with tabs for consent, data export, session management, and account deletion. Duplicates some profile.php tabs -- both exist.

### Privacy Action File (privacyActions.php -- 229 lines)

| Action | Description |
|--------|-------------|
| `updateConsent` | Toggle: marketing_email, cookie_analytics, cookie_marketing. Only records changes. |
| `requestDataExport` | Builds export synchronously. Includes admin user data + child app items, prefs, history. Formats: JSON or CSV. |
| `downloadExport` | Serves completed export file with MIME type and expiry check. |
| `requestAccountDeletion` | Requires typing "DELETE" as confirmation. Creates 30-day cooling-off period. |
| `cancelAccountDeletion` | Cancels pending deletion during grace period. |
| `revokeSession` | Revoke single device by device_id (user-scoped). |
| `revokeAllSessions` | Revoke all sessions except current. |

Admin-managed tables used: `consent_records`, `consent_versions`, `account_deletion_requests`, `device_sessions`.

### Re-Consent Page (auth/consent.php)

Shown after ToS/privacy policy updates. User must accept updated terms before proceeding. Uses `common_auth.php` (no session guard).

### Registration Consent

`auth/register.php` includes a ToS/Privacy consent checkbox. Consent is recorded in admin's `consent_records` table at registration time.

## All Action Names (Complete List)

### Member Actions (12 files, 35 handlers)

**assessmentActions.php:**
`assessment_start`, `assessment_complete`, `assessment_state`

**coachNoteActions.php:**
`add_coach_note`, `delete_coach_note`

**contentActions.php:**
`content_start`, `content_complete`, `content_skip`

**exampleActions.php:**
`sendFeedback`

**feedbackActions.php:**
`submitUserFeedback`, `getUserFeedback`, `upvoteUserFeedback`, `getRoadmapData`

**frameworkActions.php:**
`sync_framework`, `rollback_framework`

**inviteActions.php:**
`generate_invite`, `disable_invite`, `accept_invite`, `decline_invite`, `setUserTier`

**itemActions.php:**
`saveItem`, `deleteItem`, `updateItemStatus`

**onboardingActions.php:**
`save_onboarding`

**privacyActions.php:**
`updateConsent`, `requestDataExport`, `downloadExport`, `requestAccountDeletion`, `cancelAccountDeletion`, `revokeSession`, `revokeAllSessions`

**profileActions.php:**
`updateProfile`, `changePassword`, `updateAppPreferences`

**relationshipActions.php:**
`end_relationship`

### API Actions (4 files, 12 handlers)

**assessmentApiActions.php:**
`assessment_chat`, `assessment_state`, `get_user_state`, `get_user_timeline`

**exampleApiActions.php:**
`getItems`

**searchActions.php:**
`liveSearch`

**teamApiActions.php:**
`team_list`, `team_member_detail`, `cohort_summary`, `cohort_by_coach`, `cohort_timeline`, `wall_distribution`

**Total: 47 action handlers across 16 files.**

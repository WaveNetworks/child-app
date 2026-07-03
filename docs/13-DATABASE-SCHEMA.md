# Database Schema

> **RE-ENGINEERING NOTES:**
> 1. **Schema is implicit** — There is no single schema definition file. Tables are created by migrations in `db_migrations/main/` and `db_migrations/shard/`. The only way to know the full schema is to read all migration files in order. A `schema.sql` dump or entity-relationship diagram should be maintained.
> 2. **content_progress on shard but items on main** — Content progress tracking is on the shard (per-user), but the items table is on the child main DB. Queries that need both require application-level joining.
>
> **BY DESIGN:** Each user is assigned one admin shard and one child shard at registration and stays there permanently. No foreign keys across shards — referential integrity is enforced at the application layer, which is standard for sharded architectures. Coach notes live in instance JSON to stay co-located with the assessment data they annotate. Instance data follows a strict v2.3 schema defined by the framework spec.

---

## Database Architecture

```
┌─────────────────────────────────────────────────┐
│  Admin Databases (read-only from child app)      │
├─────────────────────────────────────────────────┤
│  admin_main        │ users, auth, feedback,      │
│                    │ change_requests, consent     │
│  admin_shard_1     │ user_profile, notifications  │
│  admin_shard_2     │ user_profile, notifications  │
└─────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────┐
│  Child Databases (read/write)                    │
├─────────────────────────────────────────────────┤
│  child_main        │ items, app_cache,           │
│                    │ invite_links,               │
│                    │ app_settings                 │
│                    │                              │
│  child_shard_1     │ user_event_log,              │
│  child_shard_2     │ childapp_user_prefs          │
└─────────────────────────────────────────────────┘
```

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
| cache_key | VARCHAR(255) | Unique cache key (PK) |
| cache_data | LONGTEXT | JSON-encoded data |
| expires_at | DATETIME | TTL expiration |

### invite_links
| Column | Type | Description |
|--------|------|-------------|
| id | INT AUTO_INCREMENT | Primary key |
| token | VARCHAR(64) | Unique invite token |
| coach_user_id | INT | Creating coach |
| type | VARCHAR(32) | Invite type |
| max_uses | INT | Usage limit (NULL = unlimited) |
| use_count | INT | Current usage count |
| expires_at | DATETIME | Expiration (NULL = never) |
| is_active | TINYINT | Active flag |
| created_at | DATETIME | Creation timestamp |

### framework_versions
| Column | Type | Description |
|--------|------|-------------|
| id | INT AUTO_INCREMENT | Primary key |
| checksum | VARCHAR(64) | SHA256 of all framework files |
| is_active | TINYINT | Active flag (only one active) |
| loaded_at | DATETIME | Sync timestamp |
| file_count | INT | Number of files loaded |

### framework_data
| Column | Type | Description |
|--------|------|-------------|
| id | INT AUTO_INCREMENT | Primary key |
| version_id | INT | References framework_versions.id |
| file_type | VARCHAR(32) | Type: header, stage, content_catalog, etc. |
| file_key | VARCHAR(32) | Sub-key (e.g., stage number "03") |
| json_data | LONGTEXT | Full JSON content |

### cohort_snapshots
| Column | Type | Description |
|--------|------|-------------|
| id | INT AUTO_INCREMENT | Primary key |
| snapshot_date | DATE | Date of snapshot |
| snapshot_data | LONGTEXT | JSON aggregate data |
| created_at | DATETIME | Creation timestamp |

### app_settings
| Column | Type | Description |
|--------|------|-------------|
| setting_key | VARCHAR(255) | Setting name (PK) |
| setting_value | TEXT | Setting value |

## Child Shard Tables

### assessment_instances
| Column | Type | Description |
|--------|------|-------------|
| id | INT AUTO_INCREMENT | Primary key |
| user_id | INT | Subject user |
| shard_id | VARCHAR(16) | Shard identifier |
| instance_type | VARCHAR(32) | Type: ai, coach, self |
| instance_json | LONGTEXT | Full instance data (backup) |
| status | VARCHAR(16) | active, completed |
| created_at | DATETIME | Creation timestamp |
| updated_at | DATETIME | Last update timestamp |

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

### user_event_log
| Column | Type | Description |
|--------|------|-------------|
| id | INT AUTO_INCREMENT | Primary key |
| user_id | INT | User |
| event_type | VARCHAR(64) | Event name (item_created, page_view, etc.) |
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

### user_roles
| Column | Type | Description |
|--------|------|-------------|
| id | INT AUTO_INCREMENT | Primary key |
| user_id | INT | User |
| role | VARCHAR(16) | seller, coach, org_lead |
| created_at | DATETIME | Assignment timestamp |

## Migration System

Migrations live in `db_migrations/main/` and `db_migrations/shard/`.

**Naming:** `{version}.sql` (e.g., `1.0.sql`, `1.1.sql`)

**Version tracking:** `db_version` table (managed by admin's migration infrastructure)

**Current targets:**
- Child main: 1.1
- Child shard: 1.4

**Execution:** `child_check_and_migrate()` runs on every page load. Compares current DB version to target; runs pending migrations sequentially.

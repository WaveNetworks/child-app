# Bootstrap & Configuration

> **RE-ENGINEERING NOTES:**
> 1. **Legacy config upgrade in hot path** — `common.php` and `common_api.php` contain inline logic to detect and upgrade old single-vendor AI config to multi-vendor format. This runs on every request. Move to a one-time migration or installer step.
> 2. ~~**Global variables**~~ — Configuration is stored in bare globals (`$child_db`, `$childShardConfigs`, `$ai_config`, etc.). This mirrors admin's pattern. Since admin provides `$db`, `db_query_shard()`, and the entire session/shard infrastructure as globals, **the child app must follow the same pattern.** Admin cannot be modified. A config container would only work if admin also used one.
>
> **BY DESIGN:** The three bootstrap files (`common.php`, `common_auth.php`, `common_api.php`) are deliberately separate for security. Auth pages load minimal code with no session guard. App pages enforce session auth. API pages set JSON headers and handle auth per-action. Different migration targets per bootstrap are intentional — auth pages need fewer tables than the full app.

---

## definition.php (17 lines)

Constants and request variable extraction.

```php
WN_CHILD_APP_VERSION = '1.0.0'        // Semantic version shown in admin dashboard
CHILD_APP_NAME  = 'Wave Networks Child App'   // env-driven display name for this app
CHILD_APP_SLUG  = 'child-app'                 // env-driven, lowercase URL-safe slug for this app
```

Request variables (from `$_REQUEST`):
- `$seller_id` — invitation/tracking identifier
- `$stage` — current assessment stage
- `$token` — auth token for public links
- `$instance_id` — unique assessment session ID

## common.php — Authenticated Bootstrap (150 lines)

Called by: `app/index.php`, `app/modal_controller.php`

1. Includes admin core (`admin/include/common.php`) — provides session guard, `$db`, admin helpers
2. Loads `.env` if present (local dev)
3. Loads config (priority: `config/config.php` → env vars → defaults)
4. Opens child main DB (`$child_db` via PDO)
5. Configures multi-vendor AI (`$ai_config`)
6. Configures framework source (`$framework_config`)
7. Configures app settings (`$app_config`)
8. Runs migrations: main → 1.1, shards → 1.4
9. Glob-includes all `include/common/*.php`
10. Glob-includes all `include/actions/memberActions/*.php`
11. Glob-includes all `include/actions/apiActions/*.php`

## common_auth.php — Auth Page Bootstrap (61 lines)

Called by: `auth/*.php`

Same as common.php but:
- No session guard (users aren't logged in yet)
- No AI config (not needed for auth)
- No memberActions (only apiActions + admin loginActions)
- Migration targets: main → 1.0, shard → 1.0

## common_api.php — API Bootstrap (148 lines)

Called by: `api/index.php`

Same as common.php but:
- Sets `Content-Type: application/json` before any output
- Uses admin's API bootstrap (different session handling)
- Full config stack (AI, framework, app, QR)
- Migration targets: main → 1.1, shard → 1.2
- Includes both memberActions and apiActions

## config.sample.php (79 lines)

Template for `config/config.php`. Sections:

| Section | Key Variables |
|---------|--------------|
| Child DB main | `$child_db_host`, `$child_db_name`, `$child_db_user`, `$child_db_pass` |
| Child DB shards | `$childShardConfigs['shard1']`, `$childShardConfigs['shard2']` |
| AI config | `$ai_config['default_vendor']`, per-vendor `api_key` + `model`, `max_tokens`, `temperature` |
| Framework | `$framework_config['source']` (local/remote), `local_path`, `remote_url`, `auto_sync` |
| App | `$app_config['app_url']`, `invite_max_per_coach`, `session_timeout_hours`, `cohort_cache_hours`, `snapshot_enabled` |
| QR | `$qr_config['scale']`, `logo_path`, `foreground_color`, `background_color` |

## Multi-Vendor AI Config

```php
$ai_config = [
    'default_vendor' => 'anthropic',
    'vendors' => [
        'anthropic' => ['api_key' => '...', 'model' => 'claude-sonnet-4-6'],
        'openai'    => ['api_key' => '...', 'model' => 'gpt-4o'],
        'deepseek'  => ['api_key' => '...', 'model' => 'deepseek-chat'],
    ],
    'max_tokens'  => 2048,
    'temperature' => 0.7,
];
```

Fallback order: user's saved vendor → default → anthropic → openai → deepseek.

# Cron System

Entry point: `cron/cron.php` — run via crontab every minute:
```
* * * * * /usr/bin/php /path/to/app/cron/cron.php >> /tmp/app_cron.log 2>&1
```

**Schedule layout:**
| Directory | When |
|---|---|
| `cron/minutes/{N}/*.php` | Every N minutes (e.g. `minutes/5/` runs at :00 :05 :10 …) |
| `cron/days/*.php` | Every day |
| `cron/weeks/*.php` | Every Monday |
| `cron/months/*.php` | 1st of each month |

**Adding a job:**
- Create a `.php` file in the appropriate directory.
- Do NOT call `require_once common.php` — `cron.php` bootstraps it first; all globals are available.
- Use `child_db_query_prepared()` / `child_db_query_shard_prepared()` for DB access.

**Rules:**
- `cron/.htaccess` blocks web access to the entire `cron/` tree — never remove it.
- Never do HTTP-only work (headers, sessions) inside cron jobs.
- Keep jobs idempotent where possible.

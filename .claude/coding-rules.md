# Coding Rules

**DO:**
- Escape out of PHP for HTML — never echo HTML from PHP strings
- `<?= $var ?>` shorthand, always `h()` for user-supplied data
- `sanitize($val, SQL)` for DB values
- Flat `if($_POST['action'] == 'x')` blocks in action files
- Collect errors in `$errs` array before setting `$_SESSION['error']`
- Always set `$_SESSION['success']` when an action succeeds
- Asset paths: `../../admin/assets/` for admin shared assets
- `__DIR__`-based paths for all includes

**DO NOT:**
- Write to admin's DB tables (user, device, api_key, etc.)
- Create a new `$db` PDO connection — use the global from admin
- Edit `vendor/` ever
- Store credentials anywhere except `config/config.php` (gitignored)
- Create new API endpoint files — use action files instead
- Edit existing migration files — always create a new version
- Skip `h()` or `sanitize()` for any output
- Set `$_SESSION['error']` on first validation failure (collect all first)

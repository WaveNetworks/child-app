<?php
/**
 * tools/install.php — the post-deploy install step. Idempotent; runs on EVERY deploy.
 *
 * WHY THIS EXISTS
 * ---------------
 * Provisioning creates the repo, the secrets and the deploy workflow, and then
 * stops. But an app is not installed when its files are on the host: there is a
 * class of once-per-host steps that nothing was running, so provisioned apps
 * landed half-installed and looked completely fine.
 *
 * yes4yelm was the worked example. Its public site sat empty for days:
 *
 *   - tools/seed_reference.php had never been run, so the table its share pages
 *     read was empty. That file's own docstring said "this must run before the
 *     first publish or /share/{slug} pages have nothing to render" — and nothing
 *     ran it. Eight finished share pieces shipped and reached nobody.
 *   - No publish job had ever written the site's JSON snapshots (they are
 *     .gitignore'd, so a fresh checkout has none), so every public route rendered
 *     its empty state: "will appear here once published".
 *
 * Neither was visible to a coverage manifest: all the code existed and was
 * correct. The step that turns code into a working install was simply missing
 * from the machine. This is that step.
 *
 * DESIGN RULES
 *   - Idempotent. It runs on every deploy, so every action must be safe to repeat.
 *   - Never destructive. It seeds and publishes. It does not migrate, truncate or
 *     delete — schema changes belong in db_migrations/.
 *   - Non-fatal by default. A failed install must not roll back a good deploy; it
 *     reports loudly and exits 0 unless --strict is passed.
 *   - Generic. Everything app-specific is behind file_exists/function_exists, so
 *     an app that has no seeder or no publish seam simply skips those steps.
 *   - Reports what it DID, not what it intended, so the deploy log is evidence.
 *
 * WIRING IT UP (per app): add a step to .github/workflows/deploy.yml after rsync,
 * over the SSH connection the deploy already holds:
 *
 *     ssh -i ~/.ssh/deploy_key -o StrictHostKeyChecking=no \
 *       "$SSH_USER@$SSH_HOST" \
 *       'cd public_html/<slug> && /usr/bin/php83 tools/install.php' \
 *       || echo "post-deploy install failed (non-fatal)"
 *
 * Usage:  php tools/install.php [--strict]
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "CLI only.\n";
    exit(1);
}

$strict   = in_array('--strict', $argv, true);
$problems = [];
$root     = dirname(__DIR__);

require_once $root . '/include/common.php';

echo "=== post-deploy install ===\n";
echo "started: " . date('Y-m-d H:i:s') . "\n\n";

// ─── 1. Reference / seed data ────────────────────────────────────────────────
// Apps that ship reference data need it loaded before anything reads it. The
// seeder must be idempotent (upsert), because this runs on every deploy.
echo "[1/4] Seeding reference data\n";
$seeder = __DIR__ . '/seed_reference.php';
if (is_file($seeder)) {
    try {
        include $seeder;
    } catch (Throwable $e) {
        $problems[] = 'seed_reference: ' . $e->getMessage();
        echo "  ERROR: " . $e->getMessage() . "\n";
    }
} else {
    echo "  skipped — this app ships no tools/seed_reference.php\n";
}

// ─── 2. Publish any read seam ────────────────────────────────────────────────
// An app with a DB-free public surface reads generated snapshots that are
// .gitignore'd, so a fresh deploy has NONE of them and every public route renders
// blank. A cron refreshes them in steady state, but a just-deployed app must not
// have to wait for a cron that may not even be installed yet. Forced, not
// due-gated: the seam must exist the moment the deploy finishes.
echo "\n[2/4] Publishing public snapshots\n";
$publisher = null;
foreach (['pac_publish_public', 'app_publish_public', 'publish_public'] as $fn) {
    if (function_exists($fn)) { $publisher = $fn; break; }
}
if ($publisher) {
    try {
        $r = $publisher();
        if (!empty($r['ok'])) {
            echo "  wrote: " . implode(', ', (array) ($r['written'] ?? [])) . "\n";
            if (!empty($r['dir'])) echo "  into:  " . $r['dir'] . "\n";
        } else {
            $problems[] = 'publish: failed for ' . implode(', ', (array) ($r['failed'] ?? ['unknown']));
            echo "  ERROR: failed to write " . implode(', ', (array) ($r['failed'] ?? ['unknown'])) . "\n";
        }
    } catch (Throwable $e) {
        $problems[] = 'publish: ' . $e->getMessage();
        echo "  ERROR: " . $e->getMessage() . "\n";
    }
} else {
    echo "  skipped — this app defines no publish seam\n";
}

// ─── 3. No wasted spec ───────────────────────────────────────────────────────
// Authored content that nothing consumes is the quietest failure in this stack:
// it passes every review, because the file exists and is correct — it just never
// reaches a human. yes4yelm's reference/pieces.json was the worst case: 8 finished
// share pieces, written, deployed, and unreachable because nothing seeded them.
//
// So every asset under reference/ must be named by some code path. Anything that
// is not gets reported on EVERY deploy until it is wired up or deliberately
// deleted. Both are fine outcomes; silently shipping it is not.
echo "\n[3/4] Checking for unused reference assets (no wasted spec)\n";
$ref_dir   = $root . '/reference';
$scan_dirs = ['include', 'site', 'tools', 'cron', 'app', 'scripts', 'views', 'public-api', 'api'];
$orphans   = [];

if (is_dir($ref_dir)) {
    $haystack = '';
    foreach ($scan_dirs as $d) {
        $path = $root . '/' . $d;
        if (!is_dir($path)) continue;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (!$f->isFile()) continue;
            if (!preg_match('/\.(php|py|sh|js)$/', $f->getFilename())) continue;
            $haystack .= (string) @file_get_contents($f->getPathname());
        }
    }
    foreach (glob($ref_dir . '/*') as $asset) {
        if (!is_file($asset)) continue;
        if (strpos($haystack, basename($asset)) === false) $orphans[] = basename($asset);
    }
    if (!$orphans) {
        echo "  ok — every reference asset is read by something.\n";
    } else {
        echo "  " . count($orphans) . " asset(s) authored but never read:\n";
        foreach ($orphans as $o) echo "    · reference/$o\n";
        echo "  Wire these into a code path or delete them. Shipping authored content\n";
        echo "  that no page can reach is the failure this check exists to catch.\n";
    }
} else {
    echo "  skipped — no reference/ directory\n";
}

// ─── 4. Report what still needs a human ──────────────────────────────────────
// Surfaced on every deploy so they stay visible, rather than living in a doc
// nobody re-reads.
echo "\n[4/4] Outstanding manual steps\n";
$manual = [];

// Has any cron ever run? cron_run is written by every job including skipped ones,
// so an empty table means "never scheduled" rather than "nothing to do". On
// makershost this is ONE shell entry, every minute, pointed at ADMIN's runner —
// admin/cron/cron.php globs ../../*/cron/cron.php and dispatches every child app.
// It must be every minute: the dispatcher gates minutes/{N} on $minute % N === 0,
// so a 5-minute cron that is not aligned to :00/:05 silently never fires them.
if (function_exists('child_db_query')) {
    try {
        $res = child_db_query("SELECT COUNT(*) FROM cron_run WHERE started_at >= (NOW() - INTERVAL 1 DAY)");
        if ($res && (int) $res->fetchColumn() === 0) {
            $manual[] = 'No cron_run rows in the last 24h — the crontab is probably not '
                      . 'installed. ONE shell entry, EVERY MINUTE: '
                      . '/usr/bin/php83 <home>/public_html/admin/cron/cron.php';
        }
    } catch (Throwable $e) {
        // No cron_run table is normal for an app with no cron jobs — not a problem.
    }
}

if (!$manual) {
    echo "  none — install is complete.\n";
} else {
    foreach ($manual as $i => $m) echo '  ' . ($i + 1) . '. ' . $m . "\n";
}

// ─── Result ──────────────────────────────────────────────────────────────────
echo "\n";
if ($problems) {
    echo "INSTALL FINISHED WITH ERRORS:\n";
    foreach ($problems as $p) echo "  - $p\n";
    if ($strict) { echo "\n--strict: failing the deploy.\n"; exit(1); }
    echo "\nNon-fatal: the deploy itself is fine, but the app is not fully installed.\n";
    exit(0);
}

echo "Install OK.\n";
exit(0);

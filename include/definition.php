<?php
/**
 * definition.php — child app variable pre-declarations.
 * Admin's definition.php already handles core session/request vars.
 *
 * CHILD_APP_SLUG and CHILD_APP_NAME are driven by env vars so this
 * scaffold can be reused for any child app without editing source.
 * A new child app should set:
 *   CHILD_APP_SLUG=myapp                (lowercase, URL-safe)
 *   CHILD_APP_NAME="My App"              (display name)
 * in its config.php / .env / docker-compose env block.
 */

// ─── VERSION + APP IDENTITY ─────────────────────────────────────────────────
define('WN_CHILD_APP_VERSION', '1.0.0');
define('CHILD_APP_SLUG', getenv('CHILD_APP_SLUG') ?: 'child-app');
define('CHILD_APP_NAME', getenv('CHILD_APP_NAME') ?: 'Wave Networks Child App');

// ─── CHILD APP REQUEST VARIABLES ────────────────────────────────────────────
$seller_id   = $_REQUEST['seller_id'] ?? null;
$stage       = $_REQUEST['stage'] ?? null;
$token       = $_REQUEST['token'] ?? null;
$instance_id = $_REQUEST['instance_id'] ?? null;

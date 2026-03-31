<?php
/**
 * definition.php — ElevateHER Learning Suite variable pre-declarations.
 * Admin's definition.php already handles core session/request vars.
 */

// ─── VERSION ────────────────────────────────────────────────────────────────
define('WN_CHILD_APP_VERSION', '1.0.0');
define('ELEVATEHER_APP_NAME', 'ElevateHER Learning Suite');
define('ELEVATEHER_APP_SLUG', 'elevateher');

// ─── CHILD APP REQUEST VARIABLES ────────────────────────────────────────────
$seller_id   = $_REQUEST['seller_id'] ?? null;
$stage       = $_REQUEST['stage'] ?? null;
$token       = $_REQUEST['token'] ?? null;
$instance_id = $_REQUEST['instance_id'] ?? null;

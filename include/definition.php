<?php
/**
 * definition.php — Child app variable pre-declarations.
 * Admin's definition.php already handles core session/request vars.
 * This file adds child-app-specific variables.
 */

// ─── CHILD APP SESSION VARIABLES ────────────────────────────────────────────
// Add child-app-specific session vars here as needed.
// Core session vars (user_id, shard_id, email, roles, flash messages) are
// already declared by admin's definition.php.

// ─── CHILD APP REQUEST VARIABLES ────────────────────────────────────────────
// Add child-app-specific request params here.
$item_id     = $_REQUEST['item_id'] ?? null;
$status      = $_REQUEST['status'] ?? null;

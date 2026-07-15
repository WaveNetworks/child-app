#!/usr/bin/env bash
#
# release-mobile.sh — thin per-app wrapper (child-app spec 05).
#
# All the mobile build logic lives in wave-networks-core. This wrapper just names YOUR app
# and hands off. Copy it into a new child app and edit the values below.
#
#   scripts/release-mobile.sh <version> [--push]
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# ── Fill these in for your app ──────────────────────────────────────────────
export APP_ROOT="$ROOT"
export ADMIN_ROOT="${ADMIN_ROOT:-$ROOT/../admin}"        # wave-networks-core checkout
export APP_ORIGIN="${APP_ORIGIN:-https://childapp.example.com}"   # your production origin
export APP_SLUG="${APP_SLUG:-child-app}"
export THEME_SCSS="${THEME_SCSS:-assets/scss/custom.scss}"
export BRAND_TILE="${BRAND_TILE:-assets/img/app-tile.svg}"
export SHELL_REPO="${SHELL_REPO:-$ROOT/../${APP_SLUG}-cordova}"

exec bash "$ADMIN_ROOT/scripts/release-mobile.sh" "$@"

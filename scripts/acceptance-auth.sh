#!/usr/bin/env bash
#
# scripts/acceptance-auth.sh — does the app work for someone SIGNED IN?
#
# WHY THIS EXISTS, SEPARATELY FROM acceptance.sh
# ----------------------------------------------
# acceptance.sh is unauthenticated by design: it checks what the public sees. That
# leaves the entire logged-in surface unverified, which is where a run of real bugs
# hid on yes4yelm — a dashboard that was still the scaffold placeholder, a branding
# logo pointing at a directory that does not exist, a console loading the wrong
# theme, staff pages gated on a role that could never be true.
#
# Every provisioned app HAS a usable test account: admin/install/provision.php
# creates nokemo@nokemo.com as is_admin=1 / is_test_account=1 on each app's admin
# DB. So there is no reason for the authenticated surface to go unchecked.
#
# It also catches a failure mode single-request checks structurally cannot: an
# endpoint that works once and then rate-limits. whoami shipped throttled at 8 hits
# per 10 minutes and "worked" in every one-shot test, while silently failing for any
# real visitor by their ninth page view. --repeat exists for that.
#
# USAGE
#   scripts/acceptance-auth.sh <base-app-url> <admin-base-url>
#   e.g. scripts/acceptance-auth.sh https://ex.com/myapp https://ex.com/admin
#
# Credentials come from the environment — never from a file in the repo:
#   TEST_USER_EMAIL / TEST_USER_PASSWORD   (fleet values live in ~/.openclaw/test_user.env)
#
# If they are unset it SKIPS rather than fails, so this stays safe to wire into a
# deploy where the secret may legitimately be absent.

set -uo pipefail

APP="${1:-${APP_URL:-}}"
ADMIN="${2:-${ADMIN_URL:-}}"
if [ -z "$APP" ] || [ -z "$ADMIN" ]; then
  echo "usage: $0 <app-url> <admin-url>" >&2; exit 2
fi
APP="${APP%/}"; ADMIN="${ADMIN%/}"

if [ -z "${TEST_USER_EMAIL:-}" ] || [ -z "${TEST_USER_PASSWORD:-}" ]; then
  echo "SKIP — TEST_USER_EMAIL / TEST_USER_PASSWORD not set; authenticated checks not run."
  exit 0
fi

FAILURES=0; CHECKS=0
JAR="$(mktemp)"; TMP="$(mktemp)"
trap 'rm -f "$JAR" "$TMP"' EXIT

red()   { printf '\033[31mFAIL\033[0m  %s\n' "$1"; FAILURES=$((FAILURES+1)); }
green() { printf '\033[32m ok \033[0m  %s\n' "$1"; }

echo "Authenticated acceptance: $APP"
echo

# ── Sign in ─────────────────────────────────────────────────────────────────
curl -sS -m 25 -c "$JAR" -b "$JAR" -o /dev/null \
  -X POST -d "action=login" \
  --data-urlencode "email=$TEST_USER_EMAIL" \
  --data-urlencode "password=$TEST_USER_PASSWORD" \
  "$ADMIN/auth/login.php" 2>/dev/null

# Consent gate, when the deployment has one. Harmless if it does not.
curl -sS -m 25 -b "$JAR" -c "$JAR" -o /dev/null \
  -X POST -d "action=acceptReconsent" -d "accept_updated=1" \
  "$ADMIN/auth/consent.php" 2>/dev/null

CHECKS=$((CHECKS+1))
CODE="$(curl -sS -m 25 -b "$JAR" -o "$TMP" -w '%{http_code}' "$APP/app/index.php?page=dashboard" 2>/dev/null)"
if [ "$CODE" = "200" ] && ! grep -qi "sign in\|log in to continue" "$TMP"; then
  green "signed in — dashboard reachable"
else
  red "could not sign in (HTTP $CODE) — every check below is meaningless; fix this first"
  echo; echo "FAIL — $CHECKS checks, $FAILURES failure(s)"; exit 1
fi

# ── The dashboard is BUILT, not the scaffold ────────────────────────────────
for bad in "waiting to be built" "Nothing here yet" "Replace this page with"; do
  CHECKS=$((CHECKS+1))
  if grep -qF -- "$bad" "$TMP"; then red "dashboard is still the scaffold: \"$bad\""
  else green "dashboard carries no scaffold text: \"$bad\""; fi
done

# ── Chrome that only a signed-in user ever sees ─────────────────────────────
CHECKS=$((CHECKS+1))
if grep -qE 'id="themeStylesheet"[^>]*' "$TMP" && grep -qE 'themeStylesheet' "$TMP"; then
  THEME="$(grep -oE '<link[^>]*id="themeStylesheet"[^>]*>' "$TMP" | grep -oE 'href="[^"]*"' | head -1)"
  case "$THEME" in
    *custom.css*) green "console is on the app's compiled theme ($THEME)" ;;
    *bootswatch*|*cdn*) red "console is on a CDN Bootswatch theme, not the app theme — $THEME" ;;
    *) green "console theme: $THEME" ;;
  esac
else
  red "no themeStylesheet on the dashboard"
fi

# Branding images must RESOLVE. A logo pointing at a directory that does not exist
# renders as a broken frame and nothing server-side notices.
CHECKS=$((CHECKS+1))
BAD_IMG=0; N_IMG=0
for src in $(grep -oE 'src="[^"]+\.(png|jpg|jpeg|svg|webp)"' "$TMP" | sed 's/^src="//; s/"$//' | sort -u); do
  case "$src" in
    http*) url="$src" ;;
    /*)    url="$(printf '%s' "$APP" | sed -E 's|(https?://[^/]+).*|\1|')$src" ;;
    *)     url="$APP/app/$src" ;;
  esac
  N_IMG=$((N_IMG+1))
  c="$(curl -sS -m 20 -b "$JAR" -o /dev/null -w '%{http_code}' -L "$url" 2>/dev/null)"
  [ "$c" = "200" ] || { red "broken image on the dashboard: $c  $src"; BAD_IMG=1; }
done
[ "$BAD_IMG" -eq 0 ] && green "all $N_IMG dashboard images resolve"

# ── Repeat-call check: catches an endpoint that works once, then throttles ──
if [ -n "${REPEAT_ENDPOINT:-}" ]; then
  CHECKS=$((CHECKS+1))
  N="${REPEAT_COUNT:-12}"; okc=0
  for _ in $(seq 1 "$N"); do
    c="$(curl -sS -m 15 -b "$JAR" -o /dev/null -w '%{http_code}' \
         -X POST -H "Origin: $(printf '%s' "$APP" | sed -E 's|(https?://[^/]+).*|\1|')" \
         -d "action=${REPEAT_ACTION:-whoami}" "$REPEAT_ENDPOINT" 2>/dev/null)"
    [ "$c" = "200" ] && okc=$((okc+1))
  done
  if [ "$okc" -eq "$N" ]; then green "${REPEAT_ACTION:-whoami} survived $N consecutive calls"
  else red "${REPEAT_ACTION:-whoami} succeeded only $okc/$N times — rate-limited or flaky, and it will fail for a real visitor mid-session"; fi
fi

echo
if [ "$FAILURES" -eq 0 ]; then echo "PASS — $CHECKS checks, 0 failures"; exit 0; fi
echo "FAIL — $CHECKS checks, $FAILURES failure(s)"
exit 1

#!/usr/bin/env bash
#
# scripts/acceptance.sh — does the DEPLOYED app actually work?
#
# WHY THIS EXISTS
# ---------------
# Coverage manifests (docs/spec-coverage.md) verify that code EXISTS in the repo.
# That is a static property. Nothing else in the build loop ever loads the running
# application, so a whole class of defect is structurally invisible:
#
#   - yes4yelm shipped its dashboard as the untouched scaffold placeholder
#     ("This is your dashboard. It is waiting to be built") — no coverage row named
#     a general dashboard, so nothing measured the first screen every user saw.
#   - Its public site had no sign-in link at all. No row asks "can a user get in?"
#   - Every public page rendered "[COMMITTEE NAME]" and "will appear here once
#     published" for days while the manifest read 239/255.
#   - A theme switcher pointed at a CSS file the build never produced; the row
#     checked only that the theme CSS existed somewhere.
#
# Every one of those is obvious within 60 seconds of opening the app, and none of
# them needed a tool the builder did not already have. It simply never looked.
# This script is the looking. Run it after deploy; fail the build on a red.
#
# USAGE
#   scripts/acceptance.sh https://example.com/myapp
#   BASE_URL=https://example.com/myapp scripts/acceptance.sh
#
# Per-app expectations live in acceptance.json at the repo root (optional; see
# acceptance.sample.json). With no config the generic checks below still run, and
# they are the ones that catch scaffold-shipped-to-production.
#
# Dependencies: curl and grep. Nothing else — deliberately, so the autonomous
# builder can run it with the permissions it already has.

set -uo pipefail

BASE_URL="${1:-${BASE_URL:-}}"
if [ -z "$BASE_URL" ]; then
  echo "usage: $0 <base-url>   (or set BASE_URL)" >&2
  exit 2
fi
BASE_URL="${BASE_URL%/}"

CONFIG="$(dirname "$0")/../acceptance.json"
FAILURES=0
CHECKS=0

red()   { printf '\033[31mFAIL\033[0m  %s\n' "$1"; FAILURES=$((FAILURES+1)); }
green() { printf '\033[32m ok \033[0m  %s\n' "$1"; }
info()  { printf '      %s\n' "$1"; }

# fetch <url> <outfile> -> echoes the HTTP status, body lands in <outfile>.
# NOT "body on stdout while setting a global": callers use $(fetch ...), which runs
# in a subshell, so any variable set inside is lost the moment it returns — under
# `set -u` that surfaces as "HTTP_CODE: unbound variable" rather than a wrong
# answer. Status out, body to a file, is the shape that survives substitution.
fetch() {
  curl -sS -L -m 25 -o "$2" -w '%{http_code}' "$1" 2>/dev/null || echo 000
}

# code_for <url> -> just the status code (follows redirects)
code_for() {
  curl -sS -L -m 25 -o /dev/null -w '%{http_code}' "$1" 2>/dev/null || echo 000
}

check_reachable() {
  local path="$1" label="$2" code
  CHECKS=$((CHECKS+1))
  code="$(code_for "$BASE_URL$path")"
  if [ "$code" = "200" ]; then green "$label reachable ($path)"
  else red "$label returned HTTP $code ($BASE_URL$path)"; fi
}

# Strings that must NEVER appear on a deployed page. This is the heart of it:
# scaffold text and unsubstituted placeholders reaching production is the single
# most common way an app looks "built" to a manifest and broken to a person.
FORBIDDEN=(
  "waiting to be built"
  "Nothing here yet"
  "Replace this page with"
  "will appear here once published"
  "has not been published yet"
  "[COMMITTEE NAME]"
  "PO BOX 000"
  "{{"
  "Lorem ipsum"
  "TODO:"
  "FIXME"
  "__PLACEHOLDER"
)

check_no_placeholders() {
  local path="$1" label="$2" body hit tmp code
  CHECKS=$((CHECKS+1))
  tmp="$(mktemp)"; code="$(fetch "$BASE_URL$path" "$tmp")"; body="$(cat "$tmp")"; rm -f "$tmp"
  if [ "$code" != "200" ]; then
    red "$label unreachable (HTTP $code) — cannot check placeholders"
    return
  fi
  hit=""
  for s in "${FORBIDDEN[@]}"; do
    if printf '%s' "$body" | grep -qF -- "$s"; then hit="$hit\n        · $s"; fi
  done
  if [ -z "$hit" ]; then green "$label carries no scaffold/placeholder text"
  else
    red "$label is serving placeholder text:"
    printf "$hit\n"
  fi
}

# An OG image is what a shared link looks like on Facebook/LinkedIn/Slack. A page
# that declares og:image pointing at a missing file shares as a bare grey link —
# indistinguishable from success unless you resolve the URL.
check_og_image() {
  local path="$1" label="$2" body url code tmp
  CHECKS=$((CHECKS+1))
  tmp="$(mktemp)"; fetch "$BASE_URL$path" "$tmp" >/dev/null; body="$(cat "$tmp")"; rm -f "$tmp"
  url="$(printf '%s' "$body" | grep -o '<meta property="og:image" content="[^"]*"' | head -1 | sed 's/.*content="//; s/"$//')"
  if [ -z "$url" ]; then
    red "$label declares no og:image — shared links render as a bare URL"
    return
  fi
  case "$url" in http*) ;; /*) url="${BASE_URL%/*}$url" ;; *) url="$BASE_URL/$url" ;; esac
  code="$(code_for "$url")"
  ctype="$(curl -sS -L -m 25 -o /dev/null -w '%{content_type}' "$url" 2>/dev/null || echo '')"
  # A rewrite-everything-to-index.php htaccess hands back an HTML 404 page for a
  # missing asset, so status alone is not proof. Facebook needs a real raster.
  if [ "$code" = "200" ] && printf '%s' "$ctype" | grep -qi '^image/'; then
    green "$label og:image resolves ($ctype)"
  elif [ "$code" = "200" ]; then
    red "$label og:image returns 200 but is $ctype, not an image — $url"
  else
    red "$label og:image is declared but returns HTTP $code — $url"
  fi
}

# Every link on the page must resolve. A campaign that says "check the receipts"
# and then cites a 404 has spent its credibility, and nothing else in the build
# loop looks at an outbound URL. Real case: yes4yelm's receipts page cited
# thurstoncountywa.gov/departments/assessor/tax-levy-rates and
# .../auditor/elections/election-results on 4 of its 5 claims — both had 404'd,
# so almost every "Source:" link under the campaign's central argument was dead.
#
# mailto:/tel:/#fragments are skipped: curl cannot resolve them and they are not
# broken. Internal links are resolved against the base URL.
check_links() {
  local path="$1" label="$2" body tmp bad n u full code
  CHECKS=$((CHECKS+1))
  tmp="$(mktemp)"; fetch "$BASE_URL$path" "$tmp" >/dev/null; body="$(cat "$tmp")"; rm -f "$tmp"
  bad=""; n=0
  for u in $(printf '%s' "$body" | grep -oE 'href="[^"]+"' | sed 's/^href="//; s/"$//' | sort -u); do
    case "$u" in
      mailto:*|tel:*|javascript:*|\#*|"") continue ;;
      http*) full="$u" ;;
      /*)    full="$(printf '%s' "$BASE_URL" | sed -E 's|(https?://[^/]+).*|\1|')$u" ;;
      *)     full="$BASE_URL/$u" ;;
    esac
    n=$((n+1))
    code="$(code_for "$full")"
    case "$code" in 200|301|302|303|307|308) ;; *) bad="$bad\n        · $code  $u" ;; esac
  done
  if [ -z "$bad" ]; then green "$label — all $n links resolve"
  else red "$label has broken links:"; printf "$bad\n"; fi
}

check_contains() {
  local path="$1" needle="$2" label="$3" body tmp
  CHECKS=$((CHECKS+1))
  tmp="$(mktemp)"; fetch "$BASE_URL$path" "$tmp" >/dev/null; body="$(cat "$tmp")"; rm -f "$tmp"
  if printf '%s' "$body" | grep -qF -- "$needle"; then green "$label"
  else red "$label — expected to find: $needle"; fi
}

echo "Acceptance: $BASE_URL"
echo

# ── Generic checks, every app ────────────────────────────────────────────────
check_reachable "/" "app root"
check_no_placeholders "/" "app root"
check_og_image "/" "app root"
check_links "/" "app root"

# A logged-out visitor must be able to find the way in. An app whose only entry
# point is a URL you have to already know is not finished.
CHECKS=$((CHECKS+1))
_T="$(mktemp)"; fetch "$BASE_URL/" "$_T" >/dev/null; BODY="$(cat "$_T")"; rm -f "$_T"
if printf '%s' "$BODY" | grep -qiE 'href="[^"]*(login|signin|sign-in|auth)[^"]*"'; then
  green "a sign-in route is linked from the root"
else
  red "no sign-in/login link anywhere on the root page"
fi

# ── Per-app checks from acceptance.json ──────────────────────────────────────
# Format: {"paths": ["/a","/b"], "must_contain": {"/a": "Some text"}}
if [ -f "$CONFIG" ] && command -v php >/dev/null 2>&1; then
  echo
  info "applying $CONFIG"
  while IFS= read -r p; do
    [ -n "$p" ] || continue
    check_reachable "$p" "page $p"
    check_no_placeholders "$p" "page $p"
    check_links "$p" "page $p"
  done < <(php -r '$c=json_decode(file_get_contents($argv[1]),true); foreach(($c["paths"]??[]) as $p) echo $p,"\n";' "$CONFIG" 2>/dev/null)

  while IFS=$'\t' read -r p needle; do
    [ -n "$p" ] || continue
    check_contains "$p" "$needle" "page $p contains expected content"
  done < <(php -r '$c=json_decode(file_get_contents($argv[1]),true); foreach(($c["must_contain"]??[]) as $p=>$n) echo $p,"\t",$n,"\n";' "$CONFIG" 2>/dev/null)
fi

echo
if [ "$FAILURES" -eq 0 ]; then
  echo "PASS — $CHECKS checks, 0 failures"
  exit 0
fi
echo "FAIL — $CHECKS checks, $FAILURES failure(s)"
echo
echo "These are defects a coverage manifest cannot see: it verifies that code"
echo "exists, not that the deployed app works. Fix them before claiming v1."
exit 1

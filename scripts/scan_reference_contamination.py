#!/usr/bin/env python3
"""
scan_reference_contamination.py — enforce a repo's reference-cleanliness contract.

WHAT COUNTS AS CONTAMINATION
----------------------------
Every app in this platform is scaffolded from WaveNetworks/child-app and runs on
wave-networks-core, so the apps legitimately share a great deal: the same helper
names, the same file layout, the same English. Shared lineage is the design, not
a defect. Contamination is narrower and specific:

    ANOTHER TENANT'S CODE running in this repo.

So this scanner classifies every hit by CONTEXT before it decides anything:

  * code  — an identifier, a string literal, a path, a table name, a require.
            Matched as a substring, so a slug fused into a longer name
            (acmeAnalyticsHooks.php, $acmeDeck) is still caught.
            `require 'acmeAnalyticsHooks.php'` or a table `acme_session`
            really is another app's code in this repo. HARD FAIL.
  * prose — a comment, a docblock, a markdown body, a JSON `_comment`.
            "unlike acme's, these SVGs carry no webfont" names another app but
            ships none of its code. Reported as a mention; never fails.

The same distinction governs the domain-term heuristics: `scoringEngine.php`
and `$cohortId` are another app's domain model leaking in, while "the top-scoring
find" and "our first cohort" are just English. Tier 3 therefore only fires when
the term is part of an IDENTIFIER or a FILENAME, never when it is a word in a
sentence.

TIERS
  Tier 1  forbidden_tokens   tenant identity in CODE                      HARD FAIL
          (the same tokens in prose are reported as mentions, advisory)
  Tier 2a forbidden_paths    known app-specific files present             HARD FAIL
  Tier 2b forbidden_tables   app-specific CREATE TABLE in db_migrations   HARD FAIL
  Tier 3  warn_tokens        domain terms used as identifiers/filenames   WARN
                             (fails only with --strict)

Exit code: 0 = clean (no Tier 1/2; no Tier 3 unless --strict), 1 = violations.
With --baseline it always exits 0 (report only) — used to quantify cleanup progress.

Usage:
  scan_reference_contamination.py --target <repo> [--config <yaml>] [--json out.json]
                                  [--strict] [--baseline] [--max-hits N]
                                  [--explain]

Dependencies: pyyaml (CI: `pip install pyyaml`).

Tokens and paths can also come from the environment, one per line, merged into
the contract: REFERENCE_FORBIDDEN_TOKENS and REFERENCE_FORBIDDEN_PATHS. A public
repo keeps the names of other apps in CI secrets that way instead of in its
committed contract.

CANONICAL COPY: the platform's tooling repo, scripts/scan_reference_contamination.py.
Every app repo carries a vendored copy; do not edit those by hand — change the
canonical file and run its sync-shared-tooling.py so every repo gets the same fix.
"""
import argparse
import json
import os
import re
import sys

try:
    import yaml
except ImportError:
    sys.stderr.write("ERROR: pyyaml required (pip install pyyaml)\n")
    sys.exit(2)


# ── file classification ──────────────────────────────────────────────────────

# Languages where we can cheaply tell comments from code.
C_LIKE = {".php", ".js", ".mjs", ".cjs", ".ts", ".css", ".scss", ".java", ".go"}
HASH_LIKE = {".sh", ".bash", ".py", ".yml", ".yaml", ".conf", ".ini"}
SQL_LIKE = {".sql"}
# Files that are prose end to end — a hit in them is never running code.
PROSE_FILES = {".md", ".markdown", ".txt", ".rst", ".html.twig"}
# JSON is data: string values are code-ish (slugs, paths) unless the key says
# otherwise, which is how app-model.json carries its `_comment` blocks.
JSON_FILES = {".json"}
PROSE_JSON_KEYS = re.compile(r"(^_|comment|description|note|summary|blurb|copy|text)", re.I)


def prose_mask(text, ext, rel):
    """Return a bytearray-ish list marking each char of `text` as prose (True).

    Comments and documentation are prose; identifiers, string literals, paths and
    table names are code. String literals count as CODE on purpose — a slug like
    'acme' passed to register_theme() is exactly the contamination we care
    about, and it is indistinguishable from any other literal at this level.
    """
    n = len(text)
    mask = [False] * n

    base = os.path.basename(rel).lower()
    if ext in PROSE_FILES or base in ("readme", "license", "changelog"):
        return [True] * n

    if ext in JSON_FILES:
        return _json_prose_mask(text)

    line_starts = ("//",) if ext in C_LIKE else ()
    if ext in HASH_LIKE:
        line_starts = ("#",)
    elif ext == ".php":
        line_starts = ("//", "#")
    elif ext in SQL_LIKE:
        line_starts = ("--",)
    block = ext in C_LIKE or ext in SQL_LIKE or ext == ".php"

    i = 0
    quote = None
    while i < n:
        c = text[i]
        if quote:
            if c == "\\":
                i += 2
                continue
            if c == quote:
                quote = None
            i += 1
            continue
        if c in ("'", '"', "`"):
            quote = c
            i += 1
            continue
        if block and text.startswith("/*", i):
            j = text.find("*/", i + 2)
            j = n if j == -1 else j + 2
            for k in range(i, j):
                mask[k] = True
            i = j
            continue
        started = next((s for s in line_starts if text.startswith(s, i)), None)
        if started:
            j = text.find("\n", i)
            j = n if j == -1 else j
            for k in range(i, j):
                mask[k] = True
            i = j
            continue
        i += 1
    return mask


def _json_prose_mask(text):
    """Mark JSON string values whose key looks documentary as prose."""
    n = len(text)
    mask = [False] * n
    try:
        import json as _j
        _j.loads(text)
    except Exception:
        return mask  # malformed: treat it all as code, i.e. fail loudly

    # Walk the raw text tracking the most recent key, so we keep byte offsets.
    key_re = re.compile(r'"((?:[^"\\]|\\.)*)"\s*:')
    str_re = re.compile(r'"((?:[^"\\]|\\.)*)"')
    current_prose_key = False
    pos = 0
    while pos < n:
        km = key_re.search(text, pos)
        sm = str_re.search(text, pos)
        if not sm:
            break
        if km and km.start() == sm.start():
            current_prose_key = bool(PROSE_JSON_KEYS.search(km.group(1)))
            pos = km.end()
            continue
        if current_prose_key:
            for k in range(sm.start(), sm.end()):
                mask[k] = True
        pos = sm.end()
    return mask


# ── matching helpers ─────────────────────────────────────────────────────────

def glob_to_regex(pat):
    """Translate a glob (supporting ** across dirs, * within a segment) to regex."""
    out, i, n = [], 0, len(pat)
    while i < n:
        c = pat[i]
        if pat[i:i + 2] == "**":
            out.append(".*")
            i += 2
            if i < n and pat[i] == "/":
                out.append("(?:)")
                i += 1
        elif c == "*":
            out.append("[^/]*")
            i += 1
        elif c == "?":
            out.append("[^/]")
            i += 1
        else:
            out.append(re.escape(c))
            i += 1
    return re.compile("^" + "".join(out) + "$")


def token_regex(tok):
    """Word-boundary, case-insensitive matcher. Handles multi-word phrases."""
    if re.search(r"\w", tok[0]) and re.search(r"\w", tok[-1]):
        return re.compile(r"\b" + re.escape(tok) + r"\b", re.IGNORECASE)
    return re.compile(re.escape(tok), re.IGNORECASE)


def loose_regex(tok):
    """Case-insensitive matcher with NO word boundary, so it can find a term
    buried inside an identifier (scoringEngine, eh_assessment_result)."""
    return re.compile(re.escape(tok), re.IGNORECASE)


IDENT_CHAR = re.compile(r"[A-Za-z0-9_$]")

def at_segment_start(text, start):
    """True when a match begins at the start of a name or of one of its parts.

    A tenant slug is contamination when it NAMES something — `acmeHooks`,
    `$acmeDeck`, `theme_acme`, `'acme'`. It is a coincidence when it merely
    falls inside an unrelated word: 'tch' hides in ma(tch)Handler, and matching
    that would fail a build over a match handler. So require the match to start
    at a segment boundary: after a non-identifier character, after a separator,
    or on a camelCase hump.
    """
    if start == 0:
        return True
    prev = text[start - 1]
    if not IDENT_CHAR.match(prev):
        return True
    # '$' is an identifier char for PHP/JS, but it opens a name: $acmeDeck.
    if prev in "_-$":
        return True
    # camelCase hump: lowercase/digit then an uppercase letter.
    return bool(prev.islower() or prev.isdigit()) and text[start].isupper()


# A token this short can hide inside an unrelated word from either side: 'acme'
# in `acmeter`, 'tch' in `tchotchke`. Checking only where the match STARTS failed a
# build over a DOM id in the embedded map. Short tokens must also END on a
# segment boundary. Longer tokens (e.g. northwind) are distinctive
# enough that a start boundary alone is safe, which keeps an all-lowercase fusion
# like `northwindhooks.php` caught.
SHORT_TOKEN_LEN = 5

def at_segment_end(text, end):
    """True when a match ends at the end of a name or of one of its parts.

    'acme'     -> True   'acme_deck' -> True  'acme.com' -> True
    'acmeDeck' -> True   (camelCase hump)     'ACMEDeck' -> True
    'acmeter'  -> False  (runs on into a lowercase letter)
    """
    if end >= len(text):
        return True
    nxt = text[end]
    if not IDENT_CHAR.match(nxt) or nxt in "_-" or nxt.isdigit():
        return True
    return nxt.isupper()


def is_named_token(text, start, end, tok):
    """Tier 1 code-context test: the match names something, not a coincidence."""
    if not at_segment_start(text, start):
        return False
    if len(tok) <= SHORT_TOKEN_LEN and not at_segment_end(text, end):
        return False
    return True


def in_identifier(text, start, end):
    """True when text[start:end] is part of a longer identifier or filename.

    'scoringEngine'      -> True  (followed by 'E')
    'eh_assessment_x'    -> True  (preceded by '_')
    'the top-scoring f'  -> False (hyphen and space are not identifier chars)
    """
    before = text[start - 1] if start > 0 else ""
    after = text[end] if end < len(text) else ""
    return bool((before and IDENT_CHAR.match(before)) or (after and IDENT_CHAR.match(after)))


def is_ignored(rel, ignore_res):
    return any(r.match(rel) for r in ignore_res)


def iter_files(root, ignore_res):
    for dirpath, dirnames, filenames in os.walk(root):
        rel_dir = os.path.relpath(dirpath, root)
        rel_dir = "" if rel_dir == "." else rel_dir
        keep = []
        for d in dirnames:
            rd = os.path.join(rel_dir, d) if rel_dir else d
            if not is_ignored(rd + "/", ignore_res) and not is_ignored(rd, ignore_res):
                keep.append(d)
        dirnames[:] = keep
        for fn in filenames:
            rel = os.path.join(rel_dir, fn) if rel_dir else fn
            if not is_ignored(rel, ignore_res):
                yield rel, os.path.join(dirpath, fn)


def _env_list(name):
    raw = os.environ.get(name, "")
    return [ln.strip() for ln in raw.replace(",", "\n").splitlines() if ln.strip()]


def load_config(path):
    with open(path, "r", encoding="utf-8") as f:
        cfg = yaml.safe_load(f) or {}
    # Names kept out of a public repo arrive from CI secrets, one per line.
    for key, env in (("forbidden_tokens", "REFERENCE_FORBIDDEN_TOKENS"),
                     ("forbidden_paths", "REFERENCE_FORBIDDEN_PATHS")):
        extra = _env_list(env)
        if extra:
            cfg[key] = list(cfg.get(key) or []) + [e for e in extra if e not in (cfg.get(key) or [])]
    return cfg


# ── scan ─────────────────────────────────────────────────────────────────────

def scan(target, cfg, max_hits):
    ignore_res = [glob_to_regex(p) for p in cfg.get("ignore_paths", [])]
    # Tier 1 matches WITHOUT a word boundary on purpose: the worst
    # contamination fuses the slug into an identifier or filename
    # (acmeAnalyticsHooks.php, $acmeDeck, acme_scoring), and \b would
    # walk straight past all of it.
    forbidden_tok = [(t, loose_regex(t)) for t in cfg.get("forbidden_tokens", [])]
    warn_tok = [(t, loose_regex(t), token_regex(t), " " in t)
                for t in cfg.get("warn_tokens", [])]
    forbidden_path_res = [(p, glob_to_regex(p)) for p in cfg.get("forbidden_paths", [])]
    forbidden_table_res = [(p, glob_to_regex(p)) for p in cfg.get("forbidden_tables", [])]

    report = {
        "tier1_token_hits": [],      # code context — HARD FAIL
        "tier1_prose_mentions": [],  # comments/docs — advisory only
        "tier2a_paths": [],
        "tier2b_tables": [],
        "tier3_warnings": [],
        "files_scanned": 0,
    }
    tier1_total = 0
    tier1_fileset = set()
    prose_total = 0

    create_re = re.compile(
        r"CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?\s+`?([A-Za-z0-9_]+)`?", re.IGNORECASE)

    for rel, full in iter_files(target, ignore_res):
        for pat, rgx in forbidden_path_res:
            if rgx.match(rel):
                report["tier2a_paths"].append({"pattern": pat, "file": rel})
                break

        try:
            with open(full, "r", encoding="utf-8", errors="strict") as f:
                text = f.read()
        except (UnicodeDecodeError, OSError):
            continue  # binary / unreadable
        report["files_scanned"] += 1

        ext = os.path.splitext(rel)[1].lower()
        mask = prose_mask(text, ext, rel)
        # Offset -> line number, computed once per file.
        line_of = []
        ln = 1
        for ch in text:
            line_of.append(ln)
            if ch == "\n":
                ln += 1
        line_of.append(ln)

        def line_text(off):
            s = text.rfind("\n", 0, off) + 1
            e = text.find("\n", off)
            e = len(text) if e == -1 else e
            return text[s:e].strip()[:160]

        if "/db_migrations/" in ("/" + rel) or rel.startswith("db_migrations/"):
            for m in create_re.finditer(text):
                tbl = m.group(1)
                for pat, rgx in forbidden_table_res:
                    if rgx.match(tbl):
                        report["tier2b_tables"].append(
                            {"pattern": pat, "table": tbl, "file": rel,
                             "line": line_of[m.start()]})
                        break

        # Tier 1 — identity tokens, split by context.
        for tok, rgx in forbidden_tok:
            for m in rgx.finditer(text):
                is_prose = mask[m.start()] if m.start() < len(mask) else False
                if not is_prose and not is_named_token(text, m.start(), m.end(), tok):
                    continue  # coincidence inside an unrelated word
                entry = {"token": tok, "file": rel, "line": line_of[m.start()],
                         "text": line_text(m.start())}
                if is_prose:
                    prose_total += 1
                    if len(report["tier1_prose_mentions"]) < max_hits * 20:
                        report["tier1_prose_mentions"].append(entry)
                else:
                    tier1_total += 1
                    tier1_fileset.add(rel)
                    same = sum(1 for h in report["tier1_token_hits"]
                               if h["token"] == tok and h["file"] == rel)
                    if same < max_hits:
                        report["tier1_token_hits"].append(entry)

        # Tier 3 — domain terms, only as identifiers/filenames, only in code.
        warn_counts = {}
        for tok, loose, strict_rgx, is_phrase in warn_tok:
            if is_phrase:
                # A multi-word phrase can never be an identifier; require code context.
                for m in strict_rgx.finditer(text):
                    if not (mask[m.start()] if m.start() < len(mask) else False):
                        warn_counts[tok] = warn_counts.get(tok, 0) + 1
                continue
            if loose.search(os.path.basename(rel)):
                warn_counts[tok] = warn_counts.get(tok, 0) + 1
            for m in loose.finditer(text):
                if mask[m.start()] if m.start() < len(mask) else False:
                    continue
                if at_segment_start(text, m.start()) and in_identifier(text, m.start(), m.end()):
                    warn_counts[tok] = warn_counts.get(tok, 0) + 1
        for tok, cnt in warn_counts.items():
            report["tier3_warnings"].append({"token": tok, "file": rel, "count": cnt})

    report["tier1_total"] = tier1_total
    report["tier1_files"] = len(tier1_fileset)
    report["tier1_prose_total"] = prose_total
    return report


def fmt_human(report, target, explain=False):
    L = []
    t1 = report["tier1_token_hits"]
    pm = report.get("tier1_prose_mentions", [])
    t2a = report["tier2a_paths"]
    t2b = report["tier2b_tables"]
    t3 = report["tier3_warnings"]
    L.append(f"Reference contamination scan: {target}")
    L.append(f"  files scanned: {report['files_scanned']}")
    L.append("")

    by_tok = {}
    for h in t1:
        by_tok.setdefault(h["token"], set()).add(h["file"])
    L.append(f"TIER 1  tenant identity in CODE (HARD FAIL): "
             f"{report.get('tier1_total', len(t1))} hits across "
             f"{report.get('tier1_files', 0)} files")
    for tok in sorted(by_tok):
        L.append(f"    '{tok}': {len(by_tok[tok])} files")
    for h in t1[:40]:
        L.append(f"      {h['file']}:{h['line']}: {h['text']}")
    if len(t1) > 40:
        L.append(f"      ... (+{len(t1) - 40} more, see --json)")
    L.append("")

    L.append(f"        mentions in comments/docs (advisory, not a failure): "
             f"{report.get('tier1_prose_total', 0)}")
    if explain:
        for h in pm[:40]:
            L.append(f"      {h['file']}:{h['line']}: {h['text']}")
    L.append("")

    L.append(f"TIER 2a forbidden files present (HARD FAIL): {len(t2a)}")
    for p in t2a:
        L.append(f"      {p['file']}   (pattern {p['pattern']})")
    L.append("")
    L.append(f"TIER 2b forbidden tables (HARD FAIL): {len(t2b)}")
    for p in t2b:
        L.append(f"      {p['table']}  in {p['file']}:{p['line']}  (pattern {p['pattern']})")
    L.append("")

    files3 = set(w["file"] for w in t3)
    L.append(f"TIER 3  domain terms as identifiers/filenames (advisory): "
             f"{sum(w['count'] for w in t3)} hits across {len(files3)} files")
    by_tok3 = {}
    for w in t3:
        by_tok3[w["token"]] = by_tok3.get(w["token"], 0) + w["count"]
    for tok in sorted(by_tok3, key=lambda k: -by_tok3[k]):
        L.append(f"    '{tok}': {by_tok3[tok]} hits")
    if explain:
        for w in sorted(t3, key=lambda w: -w["count"])[:40]:
            L.append(f"      {w['file']}: {w['token']} x{w['count']}")
    return "\n".join(L)


def main():
    ap = argparse.ArgumentParser(
        description="Enforce a repo's reference-cleanliness contract.")
    ap.add_argument("--target", default=".", help="repo root to scan")
    ap.add_argument("--config", default=None,
                    help="contract yaml (default: <target>/.reference-clean.yml)")
    ap.add_argument("--json", default=None, help="write JSON report to this path")
    ap.add_argument("--strict", action="store_true",
                    help="treat Tier 3 identifier warnings as failures")
    ap.add_argument("--baseline", action="store_true", help="report only; always exit 0")
    ap.add_argument("--max-hits", type=int, default=8,
                    help="max recorded token hits per token per file")
    ap.add_argument("--explain", action="store_true",
                    help="also list the advisory prose mentions and tier-3 files")
    args = ap.parse_args()

    target = os.path.abspath(args.target)
    cfg_path = args.config or os.path.join(target, ".reference-clean.yml")
    if not os.path.exists(cfg_path):
        sys.stderr.write(f"ERROR: contract not found: {cfg_path}\n")
        sys.exit(2)
    cfg = load_config(cfg_path)

    report = scan(target, cfg, args.max_hits)
    print(fmt_human(report, target, args.explain))

    if args.json:
        with open(args.json, "w", encoding="utf-8") as f:
            json.dump(report, f, indent=2)
        print(f"\nJSON report → {args.json}")

    hard = bool(report["tier1_token_hits"] or report["tier2a_paths"] or report["tier2b_tables"])
    soft = bool(report["tier3_warnings"])
    failed = hard or (args.strict and soft)
    print("\nRESULT:", "FAIL" if (failed and not args.baseline)
          else ("CLEAN" if not hard else "FAIL (baseline)"))
    if args.baseline:
        sys.exit(0)
    sys.exit(1 if failed else 0)


if __name__ == "__main__":
    main()

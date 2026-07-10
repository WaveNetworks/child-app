#!/usr/bin/env python3
"""
scan_reference_contamination.py — enforce the child-app cleanliness contract.

The child-app repo is the GENERIC reference scaffold for the Wave Networks
platform. It must never carry tenant/app-specific domain logic or identity.
This scanner reads a contract (reference_clean.sample.yml / .reference-clean.yml)
and reports violations in three tiers:

  Tier 1  forbidden_tokens   app-identity tokens (tenant app slugs)        HARD FAIL
  Tier 2a forbidden_paths    known app-specific files present              HARD FAIL
  Tier 2b forbidden_tables   app-specific CREATE TABLE in db_migrations    HARD FAIL
  Tier 3  warn_tokens        domain-term heuristics                        WARN (fail only with --strict)

Exit code: 0 = clean (no Tier 1/2; no Tier 3 unless --strict), 1 = violations.
With --baseline it always exits 0 (report only) — used to quantify cleanup progress.

Usage:
  scan_reference_contamination.py --target <repo> [--config <yaml>] [--json out.json]
                                  [--strict] [--baseline] [--max-hits N]

Dependencies: pyyaml (CI: `pip install pyyaml`).

Part of the nokemo "Reference Harvest & Sync" process — see
nokemo/docs/reference-maintenance.md
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


def glob_to_regex(pat):
    """Translate a glob (supporting ** across dirs, * within a segment) to regex."""
    out, i, n = [], 0, len(pat)
    while i < n:
        c = pat[i]
        if pat[i:i + 2] == "**":
            # ** = any chars including '/'
            out.append(".*")
            i += 2
            if i < n and pat[i] == "/":  # collapse "**/" so it can match zero dirs
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


def load_config(path):
    with open(path, "r", encoding="utf-8") as f:
        cfg = yaml.safe_load(f) or {}
    return cfg


def is_ignored(rel, ignore_res):
    return any(r.match(rel) for r in ignore_res)


def iter_files(root, ignore_res):
    for dirpath, dirnames, filenames in os.walk(root):
        # prune ignored dirs early for speed
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


def scan(target, cfg, max_hits):
    ignore_res = [glob_to_regex(p) for p in cfg.get("ignore_paths", [])]
    forbidden_tok = [(t, token_regex(t)) for t in cfg.get("forbidden_tokens", [])]
    warn_tok = [(t, token_regex(t)) for t in cfg.get("warn_tokens", [])]
    forbidden_path_res = [(p, glob_to_regex(p)) for p in cfg.get("forbidden_paths", [])]
    forbidden_table_res = [(p, glob_to_regex(p)) for p in cfg.get("forbidden_tables", [])]

    report = {
        "tier1_token_hits": [],     # {token,file,line,text}
        "tier2a_paths": [],         # {pattern,file}
        "tier2b_tables": [],        # {pattern,table,file,line}
        "tier3_warnings": [],       # {token,file,count}
        "files_scanned": 0,
    }

    create_re = re.compile(r"CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?\s+`?([A-Za-z0-9_]+)`?", re.IGNORECASE)

    for rel, full in iter_files(target, ignore_res):
        # Tier 2a: forbidden path present
        for pat, rgx in forbidden_path_res:
            if rgx.match(rel):
                report["tier2a_paths"].append({"pattern": pat, "file": rel})
                break

        try:
            with open(full, "r", encoding="utf-8", errors="strict") as f:
                lines = f.readlines()
        except (UnicodeDecodeError, OSError):
            continue  # binary / unreadable
        report["files_scanned"] += 1

        # Tier 2b: forbidden CREATE TABLE (db_migrations only)
        if "/db_migrations/" in ("/" + rel) or rel.startswith("db_migrations/"):
            for ln, text in enumerate(lines, 1):
                m = create_re.search(text)
                if not m:
                    continue
                tbl = m.group(1)
                for pat, rgx in forbidden_table_res:
                    if rgx.match(tbl):
                        report["tier2b_tables"].append(
                            {"pattern": pat, "table": tbl, "file": rel, "line": ln})
                        break

        # Tier 1 tokens + Tier 3 warns (line scan)
        warn_counts = {}
        for ln, text in enumerate(lines, 1):
            for tok, rgx in forbidden_tok:
                if rgx.search(text):
                    hits = [h for h in report["tier1_token_hits"] if h["token"] == tok and h["file"] == rel]
                    if len(hits) < max_hits:
                        report["tier1_token_hits"].append(
                            {"token": tok, "file": rel, "line": ln, "text": text.strip()[:160]})
            for tok, rgx in warn_tok:
                if rgx.search(text):
                    warn_counts[tok] = warn_counts.get(tok, 0) + 1
        for tok, cnt in warn_counts.items():
            report["tier3_warnings"].append({"token": tok, "file": rel, "count": cnt})

    return report


def fmt_human(report, target):
    L = []
    t1 = report["tier1_token_hits"]
    t2a = report["tier2a_paths"]
    t2b = report["tier2b_tables"]
    t3 = report["tier3_warnings"]
    L.append(f"Reference contamination scan: {target}")
    L.append(f"  files scanned: {report['files_scanned']}")
    L.append("")
    # Tier 1
    by_tok = {}
    for h in t1:
        by_tok.setdefault(h["token"], set()).add(h["file"])
    L.append(f"TIER 1  app-identity tokens (HARD FAIL): {len(t1)} hits across {len(set(h['file'] for h in t1))} files")
    for tok in sorted(by_tok):
        L.append(f"    '{tok}': {len(by_tok[tok])} files")
    for h in t1[:40]:
        L.append(f"      {h['file']}:{h['line']}: {h['text']}")
    if len(t1) > 40:
        L.append(f"      ... (+{len(t1) - 40} more, see --json)")
    L.append("")
    # Tier 2a
    L.append(f"TIER 2a forbidden files present (HARD FAIL): {len(t2a)}")
    for p in t2a:
        L.append(f"      {p['file']}   (pattern {p['pattern']})")
    L.append("")
    # Tier 2b
    L.append(f"TIER 2b forbidden tables (HARD FAIL): {len(t2b)}")
    for p in t2b:
        L.append(f"      {p['table']}  in {p['file']}:{p['line']}  (pattern {p['pattern']})")
    L.append("")
    # Tier 3
    files3 = set(w["file"] for w in t3)
    L.append(f"TIER 3  domain-term warnings (advisory): {sum(w['count'] for w in t3)} hits across {len(files3)} files")
    by_tok3 = {}
    for w in t3:
        by_tok3[w["token"]] = by_tok3.get(w["token"], 0) + w["count"]
    for tok in sorted(by_tok3, key=lambda k: -by_tok3[k]):
        L.append(f"    '{tok}': {by_tok3[tok]} hits")
    return "\n".join(L)


def main():
    ap = argparse.ArgumentParser(description="Enforce the child-app cleanliness contract.")
    ap.add_argument("--target", default=".", help="repo root to scan")
    ap.add_argument("--config", default=None, help="contract yaml (default: <target>/.reference-clean.yml)")
    ap.add_argument("--json", default=None, help="write JSON report to this path")
    ap.add_argument("--strict", action="store_true", help="treat Tier 3 warnings as failures")
    ap.add_argument("--baseline", action="store_true", help="report only; always exit 0")
    ap.add_argument("--max-hits", type=int, default=8, help="max recorded token hits per token per file")
    args = ap.parse_args()

    target = os.path.abspath(args.target)
    cfg_path = args.config or os.path.join(target, ".reference-clean.yml")
    if not os.path.exists(cfg_path):
        sys.stderr.write(f"ERROR: contract not found: {cfg_path}\n")
        sys.exit(2)
    cfg = load_config(cfg_path)

    report = scan(target, cfg, args.max_hits)
    print(fmt_human(report, target))

    if args.json:
        with open(args.json, "w", encoding="utf-8") as f:
            json.dump(report, f, indent=2)
        print(f"\nJSON report → {args.json}")

    hard = bool(report["tier1_token_hits"] or report["tier2a_paths"] or report["tier2b_tables"])
    soft = bool(report["tier3_warnings"])
    failed = hard or (args.strict and soft)
    print("\nRESULT:", "FAIL" if (failed and not args.baseline) else ("CLEAN" if not hard else "FAIL (baseline)"))
    if args.baseline:
        sys.exit(0)
    sys.exit(1 if failed else 0)


if __name__ == "__main__":
    main()

#!/usr/bin/env python3
"""
Remove the reference scaffold's example surface from a scaffolded app.

WHY THIS EXISTS
---------------
child-app is a REFERENCE. It ships an Items CRUD page, an Examples page and a
UI Guide so a developer can see the patterns working. A real app provisioned
from it must not inherit them — a customer opening their brand-new product and
finding "Items", "Examples" and "UI Guide" in the nav is looking at our demo,
not their app. Provisioned apps shipped that way until 2026-08-13.

HOW IT WORKS
------------
Nothing here hardcodes what "demo" means; the TEMPLATE declares it:

  * files listed in .scaffold-demo.json are deleted
  * anything between SCAFFOLD:DEMO-START and SCAFFOLD:DEMO-END is removed,
    in whichever comment syntax the file uses

So when the reference gains a new example page, updating the manifest and
wrapping its route/nav is all that is required — the provisioning runner does
not change.

Idempotent: safe to run twice, and a no-op on a tree that has already been
stripped or never had the markers.

Usage:  python3 scripts/strip_demo_surface.py [--dry-run] [target_dir]
"""
import json
import os
import re
import shutil
import sys

MARKER_START = 'SCAFFOLD:DEMO-START'
MARKER_END = 'SCAFFOLD:DEMO-END'

# Any comment style the scaffold uses. Each pattern spans start → end marker.
BLOCK_PATTERNS = [
    # <!-- SCAFFOLD:DEMO-START --> … <!-- SCAFFOLD:DEMO-END -->
    re.compile(r'[ \t]*<!--\s*' + MARKER_START + r'\s*-->.*?<!--\s*' + MARKER_END + r'\s*-->[ \t]*\n?', re.S),
    # // SCAFFOLD:DEMO-START … // SCAFFOLD:DEMO-END
    re.compile(r'[ \t]*//\s*' + MARKER_START + r'.*?//\s*' + MARKER_END + r'[ \t]*\n?', re.S),
    # # SCAFFOLD:DEMO-START … # SCAFFOLD:DEMO-END
    re.compile(r'[ \t]*#\s*' + MARKER_START + r'.*?#\s*' + MARKER_END + r'[ \t]*\n?', re.S),
]

SCAN_EXTENSIONS = {'.php', '.js', '.css', '.scss', '.html', '.md', '.json', '.sql'}
SKIP_DIRS = {'.git', 'node_modules', 'vendor', '.github'}
# The manifest and this script both NAME the markers while documenting them;
# scanning either would trip the unbalanced-marker guard on its own prose.
SKIP_FILES = {'.scaffold-demo.json', 'strip_demo_surface.py'}


def load_manifest(root):
    path = os.path.join(root, '.scaffold-demo.json')
    if not os.path.isfile(path):
        return {}
    with open(path) as fh:
        return json.load(fh)


def strip_blocks(root, dry_run):
    changed = []
    for dirpath, dirnames, filenames in os.walk(root):
        dirnames[:] = [d for d in dirnames if d not in SKIP_DIRS]
        for name in filenames:
            if os.path.splitext(name)[1] not in SCAN_EXTENSIONS or name in SKIP_FILES:
                continue
            full = os.path.join(dirpath, name)
            try:
                with open(full, encoding='utf-8') as fh:
                    original = fh.read()
            except (UnicodeDecodeError, OSError):
                continue
            if MARKER_START not in original:
                continue

            text = original
            for pattern in BLOCK_PATTERNS:
                text = pattern.sub('', text)

            # A marker left behind means an unbalanced or unrecognised pair.
            # Fail loudly: silently shipping a half-stripped file is worse than
            # stopping, because the leftover is invisible until a customer sees it.
            if MARKER_START in text or MARKER_END in text:
                raise SystemExit(
                    'ERROR: unbalanced %s marker in %s — strip aborted.'
                    % (MARKER_START, os.path.relpath(full, root)))

            if text != original:
                changed.append(os.path.relpath(full, root))
                if not dry_run:
                    with open(full, 'w', encoding='utf-8') as fh:
                        fh.write(text)
    return changed


def replace_files(root, manifest, dry_run):
    """Swap a file for its scaffold variant, then remove the variant.

    Used where marking up the original would mean many fragile edits inside one
    file — the reference dashboard is 364 lines built around the example table.
    """
    done = []
    for target, source in (manifest.get('replace_files') or {}).items():
        src, dst = os.path.join(root, source), os.path.join(root, target)
        if not os.path.isfile(src):
            continue
        done.append('%s <- %s' % (target, source))
        if not dry_run:
            with open(src, encoding='utf-8') as fh:
                body = fh.read()
            with open(dst, 'w', encoding='utf-8') as fh:
                fh.write(body)
            os.remove(src)
    return done


def find_dangling(root):
    """Report references to surface that was just removed.

    The first version of the manifest was too shallow — snippets, the mobile
    view map, the topnav search and the GDPR export all still pointed at the
    example pages after a 'successful' strip. Nothing failed; the app was simply
    broken in ways only a person clicking around would find. This makes that
    visible instead.
    """
    needles = ['page=items', 'page=examples', 'page=ui-guide',
               '$item_count', 'itemActions', 'FROM items']
    hits = []
    for dirpath, dirnames, filenames in os.walk(root):
        dirnames[:] = [d for d in dirnames if d not in SKIP_DIRS]
        for name in filenames:
            if os.path.splitext(name)[1] not in SCAN_EXTENSIONS or name in SKIP_FILES:
                continue
            full = os.path.join(dirpath, name)
            rel = os.path.relpath(full, root)
            if rel.startswith('seeds' + os.sep) or rel.startswith('db_migrations' + os.sep):
                continue  # migrations keep their history; seeds are data
            if rel == 'CLAUDE.md':
                continue  # regenerated per app by provisioning step 4a
            try:
                with open(full, encoding='utf-8') as fh:
                    text = fh.read()
            except (UnicodeDecodeError, OSError):
                continue
            for n in needles:
                if n in text:
                    hits.append((rel, n))
    return hits


def delete_files(root, manifest, dry_run):
    removed, missing = [], []
    for rel in manifest.get('delete_files', []):
        full = os.path.join(root, rel)
        if os.path.isfile(full):
            removed.append(rel)
            if not dry_run:
                os.remove(full)
        else:
            missing.append(rel)
    return removed, missing


def delete_dirs(root, manifest, dry_run):
    """Whole directories that are template material for a DIFFERENT repo.

    mobile-shell/ is the source for <slug>-cordova; the provisioner copies it out
    before this runs, and a provisioned app must not carry a template it will
    never use.
    """
    removed, missing = [], []
    for rel in manifest.get('delete_dirs', []):
        full = os.path.join(root, rel)
        if os.path.isdir(full):
            removed.append(rel)
            if not dry_run:
                shutil.rmtree(full)
        else:
            missing.append(rel)
    return removed, missing


def main():
    args = [a for a in sys.argv[1:] if a != '--dry-run']
    dry_run = '--dry-run' in sys.argv
    root = os.path.abspath(args[0]) if args else os.getcwd()

    if not os.path.isdir(root):
        raise SystemExit('ERROR: not a directory: %s' % root)

    manifest = load_manifest(root)
    if not manifest:
        print('No .scaffold-demo.json in %s — nothing declared as demo surface.' % root)

    removed, missing = delete_files(root, manifest, dry_run)
    dremoved, dmissing = delete_dirs(root, manifest, dry_run)
    replaced = replace_files(root, manifest, dry_run)
    changed = strip_blocks(root, dry_run)

    prefix = '[dry-run] ' if dry_run else ''
    print('%sdeleted %d demo file(s):' % (prefix, len(removed)))
    for r in removed:
        print('   -', r)
    if missing:
        # Not an error: a file may already be gone, or the app may never have
        # had it. Reported so a stale manifest entry is visible.
        print('   (not present: %s)' % ', '.join(missing))
    print('%sdeleted %d demo dir(s):' % (prefix, len(dremoved)))
    for r in dremoved:
        print('   -', r + '/')
    if dmissing:
        print('   (not present: %s)' % ', '.join(dmissing))
    print('%sreplaced %d file(s):' % (prefix, len(replaced)))
    for r in replaced:
        print('   -', r)
    print('%sstripped demo blocks from %d file(s):' % (prefix, len(changed)))
    for c in changed:
        print('   -', c)

    if not dry_run:
        dangling = find_dangling(root)
        if dangling:
            print('\nWARNING: %d reference(s) to removed demo surface remain:' % len(dangling))
            for rel, needle in dangling:
                print('   %-52s %s' % (rel, needle))
            print('Add these to .scaffold-demo.json or wrap them in SCAFFOLD:DEMO markers.')
        else:
            print('\nNo dangling references to the removed surface.')


if __name__ == '__main__':
    main()

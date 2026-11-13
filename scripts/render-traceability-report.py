#!/usr/bin/env python3
"""Render documentation/ACCEPTANCE-TRACEABILITY-REPORT.md from the generated register and the 17 manual records.

The report is generated, never hand-edited. It reads only committed files and changes no status: it cannot accept a criterion,
complete a record or change the register. `--check` fails (exit 1) when the committed report is out of date.

Usage: python3 -I scripts/render-traceability-report.py [--check] [--out FILE]
"""
import argparse
import os
import re
import sys

import yaml

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DOC = os.path.join(ROOT, 'documentation')

# What a machine can and cannot do for each record. This is analysis, not evidence: nothing here completes a record.
RECORDS = {
    'C01-M01': ('Dashboard walk-through of the theme', 'WP-CLI install and activation, empty error log and front-end load pass in hosted CI (C01-001 to C01-003); package checksum and clean install pass (C19).', 'A person on a staging copy built from the release packages, reading the checksums and the debug log.'),
    'C02-M01': ('Dashboard walk-through of the plugin', 'Activation, schema creation and unsupported-environment refusal pass (C02 PHPUnit and WP-CLI).', 'A person on staging, observing the administration.'),
    'C03-M01': ('Both together, front end and administration', 'Integration tests and front-end smoke at three widths pass in hosted CI.', 'A person on staging with both active.'),
    'C06-M01': ('Admin walk-through: create and edit content', 'A real-browser script creates all five content types through the administration (hosted CI).', 'A person using separate editor, officer and approver accounts.'),
    'C10-M01': ('Incomplete clearance blocks publication (UI)', 'All server-side paths (editor, quick edit, bulk edit, scheduling, REST, XML-RPC, import, WP-CLI) are tested; browser admin workflow passes.', 'A person confirming the messages and behaviour in the interface.'),
    'C12-M01': ('No unfinished placeholders appear publicly', 'Crawl for placeholder tokens passes (hosted, including the core sitemap).', 'A person reading the rendered pages against the owner content list; no approved content exists yet.'),
    'C13-M01': ('Layout at 360, 768 and 1280 px', 'Scripted smoke at the three widths, overflow and console checks pass in Chromium.', 'A person on real devices and other browsers (Firefox and WebKit untested); screenshots kept privately.'),
    'C14-M01': ('Keyboard navigation (the acceptance test)', 'Automated tab order, focus visibility and menu operation pass (165 checks).', 'A person doing the keyboard pass; this manual pass is the frozen plan\'s acceptance test.'),
    'C15-M01': ('WCAG AA contrast, manual review', 'Token contrast for every pair and every preset in light and dark, axe on rendered pages (no violations).', 'A person, with a screen reader and real devices, judging what automation cannot.'),
    'C17-M01': ('Naming scan and screenshot review (owner)', 'The scan tooling exists; CI never runs it by design. Status item C17-S01 reports only whether owner evidence is outstanding.', 'The owner, with the private term list that must never enter the repository.'),
    'C19-M01': ('Manual install check of both packages', 'Package build, checksum and clean install of both zips pass in hosted CI.', 'A person on a clean WordPress, verifying the checksums by hand.'),
    'C20-M01': ('Review of the operator documents', 'Link check passes; documents exist and are current.', 'A person reading and following them.'),
    'C20-M02': ('Rehearsal of the recovery steps', 'The key custody rehearsal (69 checks) and upgrade-refusal tests exercise the commands on a disposable site.', 'A person rehearsing backup, restore and rollback on staging; not rehearsed by anyone.'),
    'C25-M01': ('Backup-confirmation workflow before an upgrade', 'Migration refusal without a confirmed backup, success after confirmation, and the upgrade notice are tested (30 and 9 checks, PHPUnit).', 'A person performing the workflow with the operator commands.'),
    'C27-M01': ('Review of the audit trail (supplementary; not in the frozen plans)', 'C27 PHPUnit tests pass (7); the audit commands are tested.', 'A person reading the audit trail; the owner decides whether this record is required (see C27-M01-PROVENANCE.md).'),
    'C31-M01': ('Cache invalidation: hosting purge on a test address (owner)', 'Purge list and notice behaviour are tested without a persistent cache.', 'The owner, on a real host or CDN, if one is used.'),
    'C34-M01': ('Audit key custody and recovery rehearsal (owner)', 'The automated rehearsal passes locally and in hosted CI (not a substitute for this record).', 'The owner, on staging with throwaway keys and a second administrator as recovery authority.'),
}


def read(p):
    with open(p, encoding='utf-8') as fh:
        return fh.read()


def esc(t):
    return str(t).replace('|', '\\|').replace('\n', ' ')


def render():
    reg = yaml.safe_load(read(os.path.join(DOC, 'acceptance-criteria-traceability.yml')))
    s = reg['summary']
    plan = read(os.path.join(DOC, 'CI-ACCEPTANCE-TEST-PLAN.md'))
    out = []
    out.append('# Acceptance-criteria traceability report')
    out.append('')
    out.append('**Generated by `scripts/render-traceability-report.py` from the committed register and the 17 records. Do not edit by hand; CI checks it is current.** It changes no status. The frozen plans are unchanged.')
    out.append('')
    out.append(f"* Criteria: {len(reg['criteria'])}. Accepted: **{s['accepted_count']}**. Planned, implemented, executed and accepted are four independent fields.")
    out.append(f"* PHPUnit cases recorded in the register: {s['phpunit_total_tests']}.")
    out.append(f"* Reviewer designation and independence: {s['manual_review']}")
    out.append('* C26-003 to C26-006: ' + str(s['c26_003_to_c26_006']))
    out.append('')
    out.append('## 1. The 34 criteria')
    out.append('')
    out.append('| # | Criterion | Planned | Implemented | Executed | Accepted | Manual record |')
    out.append('|---|-----------|---------|-------------|----------|----------|---------------|')
    for c in reg['criteria']:
        out.append(f"| {c['id']} | {esc(c['title'])} | {c['planned']} | {esc(c['implemented'])} | {esc(c['executed'])} | {c['accepted']} | {esc(c.get('manual_records_pending', 'none'))} |")
    out.append('')
    out.append('"local_full" and similar values describe the execution recorded when the register was generated. Where hosted CI also ran the tests, [RELEASE-READINESS-REPORT.md](RELEASE-READINESS-REPORT.md) lists the run ids.')
    out.append('')
    out.append('## 2. Open gaps recorded per criterion')
    out.append('')
    for c in reg['criteria']:
        g = c.get('gaps') or []
        if g:
            out.append(f"* **{c['id']} {c['title']}:** " + '; '.join(str(x) for x in g))
    out.append('')
    out.append('## 3. The 17 manual records')
    out.append('')
    out.append('Every record is PENDING. "Machine evidence" is supporting automated evidence that already exists; it does not complete or replace the record. "Needs a human" says what only a person can do.')
    out.append('')
    out.append('| Record | Subject | Executor (frozen plan) | Status | Machine evidence that exists | Needs a human |')
    out.append('|--------|---------|------------------------|--------|------------------------------|---------------|')
    for rid, (subj, machine, human) in RECORDS.items():
        t = read(os.path.join(DOC, 'acceptance-records', rid + '.md'))
        ex = re.search(r'^\| Executor \(frozen plan\) \| (.+?) \|$', t, re.M)
        st = re.search(r'^\| Decision \| (.+?) \|$', t, re.M)
        out.append(f"| [{rid}](acceptance-records/{rid}.md) | {esc(subj)} | {ex.group(1) if ex else '?'} | {st.group(1) if st else '?'} | {esc(machine)} | {esc(human)} |")
    out.append('')
    out.append('Also owner-only and not among the 17: C21-021, C21-022 and C21-M01 (decided in `owner-acceptance-decisions.yml`, all pending), the owner naming scan C17-001, and every acceptance decision.')
    out.append('')
    out.append('## 4. Register against the frozen plans')
    out.append('')
    out.append('`tests-cli/plan-register-consistency-test.py` compares the register and records with both frozen plans in CI. The one documented difference is C27-M01, which neither plan defines ([C27-M01-PROVENANCE.md](C27-M01-PROVENANCE.md)). The CI plan file used here has ' + str(len(plan.splitlines())) + ' lines and is not modified.')
    out.append('')
    return '\n'.join(out) + '\n'


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--check', action='store_true')
    ap.add_argument('--out', default=os.path.join(DOC, 'ACCEPTANCE-TRACEABILITY-REPORT.md'))
    a = ap.parse_args()
    text = render()
    if a.check:
        cur = read(a.out) if os.path.exists(a.out) else ''
        if cur != text:
            print(f'{a.out} is out of date; run scripts/render-traceability-report.py', file=sys.stderr)
            return 1
        print('ACCEPTANCE-TRACEABILITY-REPORT.md is current')
        return 0
    with open(a.out, 'w', encoding='utf-8') as fh:
        fh.write(text)
    print('wrote', a.out)
    return 0


if __name__ == '__main__':
    sys.exit(main())

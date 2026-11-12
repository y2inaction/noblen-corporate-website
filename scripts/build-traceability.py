#!/usr/bin/env python3
"""Generate documentation/acceptance-criteria-traceability.yml.

PHPUnit counts come from a JUnit file (run with --log-junit). Results of the shell and browser
suites are passed on the command line, so nothing is typed in by hand without a source.

Four independent statuses per criterion: planned, implemented, executed, accepted.
  implemented: none | partial | automated_complete  (automated parts only; manual parts are separate)
  executed:    none | partial | local_full          (local, except where an entry cites a hosted run: the first hosted run, commit de59ce1, and for criterion 21 the capture run 43286920565 and the strict run 43316091579)
  accepted:    always false here. Acceptance needs reviewed evidence and, where listed, manual records.
Usage: build-traceability.py junit.xml --cli 20/20 --pkg 11/11 --admin 21/21 --a11y 43/43 --smoke PASS
"""
import argparse, collections, datetime, json, re, sys
import xml.etree.ElementTree as ET

ap = argparse.ArgumentParser()
ap.add_argument('junit'); ap.add_argument('--cli'); ap.add_argument('--pkg'); ap.add_argument('--admin'); ap.add_argument('--a11y'); ap.add_argument('--smoke'); ap.add_argument('--content'); ap.add_argument('--classic'); ap.add_argument('--crawl'); ap.add_argument('--partial'); ap.add_argument('--we'); ap.add_argument('--classic-a')
ap.add_argument('--out', default='documentation/acceptance-criteria-traceability.yml')
a = ap.parse_args()

counts, failed = collections.Counter(), collections.Counter()
total = 0
for tc in ET.parse(a.junit).iter('testcase'):
    total += 1
    m = re.search(r'test_C(\d\d)', tc.get('name') or '')
    if m:
        counts[int(m.group(1))] += 1
        if tc.find('failure') is not None or tc.find('error') is not None:
            failed[int(m.group(1))] += 1

CLI = f'tests-cli/cli-integration.sh (real WP-CLI 2.12.0): {a.cli}'
PKG = f'tests-cli/package-install.sh: {a.pkg}'
ADM = f'tests-e2e/admin.js (real browser, WordPress admin): {a.admin}'
A11 = f'tests-e2e/a11y.js (axe-core, token contrast, keyboard): {a.a11y}'
SMK = f'tests-e2e/smoke.js (360/768/1280 px, console, third-party requests, overflow, placeholder tokens): {a.smoke}'

CNT = f'tests-e2e/content-types.js (real browser; project, product, company, industry, service created and edited through the admin UI; PHP built-in server, not wp-env): {a.content}'
CLS = f'tests-e2e/classic-editor.js CONFIG=B (real browser; Classic Editor plugin not active; PHP built-in server, not wp-env): {a.classic}'
CRL = f'tests-e2e/crawl.js (real browser; same-site crawl, placeholder tokens in text, title and meta description; PHP built-in server, so the core sitemap was NOT exercised): {a.crawl}'
PRT = f'tests-cli/partial-data.sh (empty site, then companies, industries, projects added one at a time; run through a SHIM that routes wp-env commands to local WP-CLI, not wp-env itself): {a.partial}'
CLA = f'ENGINEERING EVIDENCE ONLY, NOT RELEASE EVIDENCE: tests-e2e/classic-editor.js CONFIG=A and tests-cli/classic-editor-config-a.sh were run LOCALLY against a STAND-IN package built from the official GitHub source (commit bb8d0cc) with the official-source checks switched off. They validate the test logic. They say nothing about the WordPress.org package: {a.classic_a}'
CLH_CAP = 'HOSTED capture run 43286920565 (commit cb211fc39b05ebfffe5ea9fd6574206b089d3500, job 131422642102, artifact 13974709232): the official WordPress.org package was downloaded (SHA-256 7cc7799b0b7d820fbb7528dbff8027c736e52513992416cb37279f44d3d9dd77), WP-CLI verified every file against the published checksums, and the Configuration A suite passed 13/13 in wp-env on WordPress 6.4.3. Mode CAPTURE: review material, C21-021 recorded as NOT SATISFIED'
CLH_REV = 'INDEPENDENT REVIEW, reference NOBLEN-CORP-CE-INDEPENDENT-REVIEW-2026-11-12 (an assigned identifier; the owner holds the record): the package hash was reproduced across two independent downloads; six of six files are identical to the WordPress.org SVN tag tags/1.7.0; the only difference from the GitHub development commit is one comment line in classic-editor.php (no executable code differs). The SVN revision was not recorded'
CLH_STR = 'HOSTED STRICT run 43316091579 (commit ec38d8991a64a0ea0b666acd268964f02012cc00, job 131524371513, run conclusion success): pin verified; official host; package SHA-256 equals the verified pin; header and installed version 1.7.0; WP-CLI verified every file against the published checksums; installer 14/14; Configuration A suite 13/13; Configuration B restored. Record artifact 13988531774 inspected by the owner: mode STRICT, test_only false, c21_021 satisfied by this run against a verified pin, settings classic-editor-replace=block and classic-editor-allow-users not stored (plugin default)'
WES = f'tests-cli/we-integration.sh incl. C26 logical-dataset hashing (SHIM, not wp-env itself): {a.we}'

# key: (title, implemented, executed, manual_pending, extra evidence, gaps)
C = {
 1: ('Theme installs and activates', 'automated_complete', 'local_full', 'C01-M01 dashboard walk-through', [CLI, PKG, SMK], ['Not run on the CI matrix (MySQL 8.4, PHP 8.4)']),
 2: ('Plugin installs and activates', 'automated_complete', 'local_full', 'C02-M01', [CLI, PKG], ['C02-004 covers the unsupported-WordPress path only; unsupported-PHP path cannot be exercised on PHP 8.3 without a constant override']),
 3: ('Both work together', 'partial', 'partial', 'C03-M01', [SMK, CLI + ' (plugin inactive renders)'], ['C03-001 accessor-to-theme is exercised only through the browser fixtures, not a dedicated test']),
 4: ('Theme deactivation does not delete structured content', 'partial', 'partial', None, [CLI], ['C04-001 compares record counts only; C04-002 (content still viewable after switch) not tested']),
 5: ('All five custom post types work', 'automated_complete', 'local_full', None, [], []),
 6: ('Dashboard content can be created and edited', 'automated_complete', 'partial', 'C06-M01 admin walk-through', [ADM, CNT], ['C06-001 and C06-002 are implemented for all five types and pass locally in a real browser, but have not run in the WE environment (wp-env), which the plan requires']),
 7: ('Content relationships work', 'automated_complete', 'local_full', None, [], []),
 8: ('Private clearance data cannot be retrieved publicly', 'partial', 'partial', None, [], ['In-process probes only (accessor, query, meta, search, REST, routes, export). Rendered HTML, feeds and sitemap bodies are not probed over HTTP with sentinels']),
 9: ('Unauthorised users cannot approve publication', 'partial', 'partial', None, [ADM], ['Forged-request test covers one handler; no full per-role HTTP matrix']),
 10: ('Incomplete clearance blocks publication server-side', 'automated_complete', 'local_full', 'C10-M01', [CLI, ADM], ['C10 WP-CLI path now real (see C24); manual walk-through pending']),
 11: ('Empty homepage sections stay hidden', 'automated_complete', 'partial', None, [PKG + ' (empty site shows no section headings)', PRT], ['C11-001 and C11-002 are implemented, but the available execution used a shim, not wp-env, which the plan requires']),
 12: ('No unfinished placeholders appear publicly', 'automated_complete', 'partial', 'C12-M01', [SMK, CRL], ['C12-001 is implemented; the core sitemap seed needs Apache (wp-env) and has not been exercised. WordPress default sample content (Hello world, Sample Page) is reported as a warning and must be deleted before launch']),
 13: ('Layout works at 360, 768 and 1280 px', 'partial', 'partial', 'C13-M01', [SMK], ['No archived screenshot evidence in the repository; manual review pending']),
 14: ('Keyboard navigation works', 'partial', 'partial', 'C14-M01 (the acceptance test)', [A11], ['Automated support only; manual keyboard pass is the acceptance test']),
 15: ('WCAG AA contrast met', 'partial', 'partial', 'C15-M01', [A11], ['Automated axe covers 11 pages at two widths; manual review pending']),
 16: ('PHP lint and PHPCS pass', 'automated_complete', 'partial', None, ['HOSTED (commit de59ce1, run 43259095276): lint, PHPCS and PHPCompatibility 8.3 and 8.4 green on PHP 8.3 and 8.4; PHP 8.5 informational job green','PHPCS (WordPress-Core/Extra + PHPCompatibilityWP): 0 errors, 0 warnings on theme and plugin', 'PHPCompatibility static check clean for 8.3-, 8.4-, 8.5- (C16-005/006)'], ['Lint executed on PHP 8.3 only; PHP 8.4 lint and the PHP 8.5 informational run need CI']),
 17: ('Naming compliance passes without exposing the term', 'partial', 'none', 'C17-M01, C17-S01 (owner)', ['scripts/naming-scan.sh (owner-run tool; verified only with a dummy list)'], ['The owner has not run the scan. The term list is never in the repository']),
 18: ('No unnecessary third-party requests', 'automated_complete', 'local_full', None, [SMK, 'scripts/check-no-third-party.sh: PASS'], []),
 19: ('Both ZIPs install on a clean WordPress', 'automated_complete', 'local_full', 'C19-M01', [PKG], ['Clean site is a copy of the local WordPress, not the CI-fetched release']),
 20: ('Installation and recovery instructions documented', 'partial', 'none', 'C20-M01, C20-M02 (rehearsal)', ['documentation/INSTALLATION.md', 'documentation/RECOVERY.md'], ['Link check not run on the new documents; recovery rehearsal not done']),
 21: ('Classic Editor compatibility', 'automated_complete', 'partial', 'C21-M01 manual editor check', [CLS, CLA, CLH_CAP, CLH_REV, CLH_STR], ['C21-021 (version and source match the verified pin): EVIDENCED by hosted strict run 43316091579. AWAITING OWNER ACCEPTANCE. Not accepted', 'C21-022 (record of the settings used): EVIDENCED by the settings record of strict run 43316091579 (classic-editor-replace=block; classic-editor-allow-users not stored, the plugin default applies). AWAITING OWNER ACCEPTANCE. Not accepted', 'Pin limits: the whole-ZIP SHA-256 was observed stable on one day only; the SVN revision was not recorded; the pin\'s checksums URL is the WP-CLI form and its live reachability is not independently confirmed; the SVN tag and the download host share one trust anchor', 'Configuration A tests C21-001 to C21-005, 011, 013, 015, 017, 019 passed 13/13 against the real package in capture run 43286920565 and strict run 43316091579. Configuration B ran in the wp-env job of run 43316091579 (step passed; its check count was not re-read)', 'C21-M01 (manual editor check) is still pending, so criterion 21 as a whole is not accepted']),
 22: ('Private approval status isolation', 'partial', 'partial', None, [], ['In-process only; HTML and feed bodies not sentinel-probed over HTTP; editor and anonymous reads covered']),
 23: ('Snapshot invalidation after editing public content', 'partial', 'partial', None, [], ['Every public scalar field is covered via the registry; relationship, term, thumbnail, gallery membership and order, alt, caption, title covered once each; media file replacement covered by C29; video URL covered as a field; presentation flag covered as a field']),
 24: ('Failed publication attempts through alternative write paths', 'automated_complete', 'local_full', None, [CLI + ' (real WP-CLI write paths)'], ['REST and XML-RPC paths are tested in-process with the endpoints enabled, not over HTTP']),
 25: ('Database migration and upgrade integrity', 'automated_complete', 'partial', 'C25-M01', [], ['Upgrade tested from a simulated v1 (index dropped), the only earlier schema; MySQL 8.4 not run locally']),
 26: ('Preservation through theme switching and plugin upgrades', 'partial', 'partial', None, [CLI, WES], ['C26-001 and C26-002 now compare content hashes, metadata, relationship ID sets, clearance, approvals, media rights, settings and the existing audit entries, with a sensitivity check; executed through a shim, not wp-env', 'C26-003 to C26-006 are NOT implemented. They belong to M2-D and have prerequisites that need the owner (see documentation/C26-M2D-BOUNDARY-REVIEW.md): no earlier release exists to register, and the attestation needs a reviewer and a hosted run']),
 27: ('Audit trail behaviour', 'automated_complete', 'local_full', 'C27-M01', [], ['C27-001 is a static scan of includes/']),
 28: ('Related-record dependency mechanics', 'partial', 'partial', None, [], ['Loop-prevention, republish-after-lapse and snapshot-excludes-related-content not each asserted']),
 29: ('Media rights confirmation and invalidation', 'automated_complete', 'local_full', None, [], []),
 30: ('Publisher grant scoping', 'automated_complete', 'local_full', None, [], []),
 31: ('Cache invalidation', 'partial', 'partial', 'C31-M01 (owner)', [], ['Core object cache only; no persistent object-cache drop-in test; purge action asserted per trigger, not "exactly once"']),
 32: ('Ineligible content is not served through supported public interfaces', 'automated_complete', 'local_full', None, [], ['In-process queries as an anonymous user; REST has no routes by design; get_posts() with suppress_filters bypasses the read-time filter (documented limit)']),
 33: ('Required and optional relationship behaviour', 'partial', 'partial', None, [], ['Presentation of the grace state is covered by the exposure test only']),
 34: ('Audit key: missing, invalid, rotation and recovery', 'partial', 'partial', 'C34-M01 (owner)', [], ['Wrong-key-for-historical-segment, compromise rotation and old-key-available are not each tested']),
}

lines = [
 '# M2 acceptance-criteria traceability (generated by scripts/build-traceability.py)',
 f'# Generated: {datetime.date.today().isoformat()}',
 '# Executions are LOCAL (PHP 8.3.6, MariaDB 10.11, WordPress 6.4.3) unless an entry cites a hosted run: the first hosted run (commit de59ce1, run 43259095276, 6 of 11 jobs green) and, for criterion 21, the capture run 43286920565 and the strict run 43316091579 (both green).',
 '# accepted is false everywhere: acceptance needs reviewed evidence and, where listed, manual records.',
 'summary:',
 f'  phpunit_total_tests: {total}',
 '  environment: "local: PHP 8.3.6, MariaDB 10.11, WordPress 6.4.3, WP-CLI 2.12.0, Chromium (Playwright 1.63.0), axe-core 4.10.2"',
 '  accepted_count: 0',
 '  classic_editor_pin: "verified (1.7.0; independent review 2026-11-12, reference NOBLEN-CORP-CE-INDEPENDENT-REVIEW-2026-11-12; strict hosted run 43316091579 passed). C21-021 and C21-022: EVIDENCED, AWAITING OWNER ACCEPTANCE"',
 '  c26_003_to_c26_006: "deferred to M2-D by owner decision; not implemented; not waived; not accepted"',
 'criteria:',
]
for n in range(1, 35):
    t, impl, ex, manual, extra, gaps = C[n]
    cnt = counts.get(n, 0)
    ev = ([f'PHPUnit: {cnt - failed.get(n, 0)}/{cnt} tests named test_C{n:02d}_* pass ({"all" if not failed.get(n) else "FAILURES"})'] if cnt else []) + extra
    lines += [
        f'  - id: {n}',
        f'    title: {json.dumps(t)}',
        '    planned: true',
        f'    implemented: {impl}',
        f'    executed: {ex}  # local only',
        '    accepted: false',
        f'    manual_records_pending: {json.dumps(manual) if manual else "none"}',
        '    evidence:' if ev else '    evidence: []',
    ] + [f'      - {json.dumps(e)}' for e in ev] + (['    gaps:'] + [f'      - {json.dumps(g)}' for g in gaps] if gaps else ['    gaps: []'])
open(a.out, 'w').write('\n'.join(lines) + '\n')
print('wrote', a.out, 'criteria with failures:', [n for n in failed])

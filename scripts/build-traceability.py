#!/usr/bin/env python3
"""Generate documentation/acceptance-criteria-traceability.yml.

PHPUnit counts come from a JUnit file (run with --log-junit). Results of the shell and browser
suites are passed on the command line, so nothing is typed in by hand without a source.

Four independent statuses per criterion: planned, implemented, executed, accepted.
  implemented: none | partial | automated_complete  (automated parts only; manual parts are separate)
  executed:    none | partial | local_full          (local, except where an entry cites a hosted run: the first hosted run, commit de59ce1, and for criterion 21 the capture run 43286920565 and the strict run 43316091579)
  accepted:    false unless the OWNER has recorded acceptance of every required sub-criterion (REQUIRED_SUBS) in
               documentation/owner-acceptance-decisions.yml. It is never inferred from test results. Missing entries are pending.
               No test result, generator run, developer action or technical approval can change an owner decision. The 'evidence'
               strings in that file are audit references typed by the owner: this script does not verify that the records exist.
Usage: build-traceability.py junit.xml --cli 20/20 --pkg 11/11 --admin 21/21 --a11y 43/43 --smoke PASS
       build-traceability.py --check-dependencies     (prints the PyYAML version and exits 0 if it meets the minimum)
Exit status: 0 only when a new register was written. Any non-zero status means NO register was written, and any existing
register file must NOT be treated as current (it is left untouched, never deleted). The '# Generated:' line carries a UTC
timestamp with seconds, so a successful generation can be told apart from a stale file.
"""
import argparse, collections, datetime, json, os, re, sys, tempfile
import xml.etree.ElementTree as ET

NOT_CURRENT = 'No register was written. Any existing register file must NOT be treated as current.'


def die(msg):
    sys.stderr.write(f'{msg}\n{NOT_CURRENT}\n')
    sys.exit(1)


def _excepthook(exc_type, exc, tb):
    sys.__excepthook__(exc_type, exc, tb)
    sys.stderr.write(f'build-traceability: generation FAILED. {NOT_CURRENT}\n')


sys.excepthook = _excepthook

# PyYAML: the minimum is the version this tooling is tested with, and it must be a STABLE release (no alpha, beta,
# release-candidate, dev or other pre-release, whatever its number). Local install: apt-get install python3-yaml, or
# python3 -m pip install "PyYAML>=6.0.1". Documented in documentation/DEPENDENCIES.md.
MIN_PYYAML = (6, 0, 1)
MIN_PYYAML_TEXT = '.'.join(map(str, MIN_PYYAML))
try:
    import yaml
except ImportError:
    die(f'build-traceability.py needs a stable PyYAML release >= {MIN_PYYAML_TEXT} (Debian/Ubuntu: apt-get install python3-yaml; otherwise: python3 -m pip install "PyYAML>={MIN_PYYAML_TEXT}"). See documentation/DEPENDENCIES.md')


def _stable_release(text):
    """Return the release tuple when `text` is a stable release (N.N[.N], optionally followed by .postN and/or a +local
    suffix); return None for anything else. Every pre-release (a, b, c, rc, alpha, beta, pre, preview, dev) and every text
    that is not recognised is therefore refused, so a pre-release of a LATER version is refused as well."""
    m = re.fullmatch(r'(\d+)\.(\d+)(?:\.(\d+))?([^+]*)(?:\+[A-Za-z0-9.]+)?', str(text).strip())
    if not m or not re.fullmatch(r'(?:[._-]?post[._-]?\d*)?', m.group(4).lower()):
        return None
    return tuple(int(x or 0) for x in m.groups()[:3])


_YAML_VERSION = getattr(yaml, '__version__', 'unknown')
_YAML_RELEASE = _stable_release(_YAML_VERSION)
if _YAML_RELEASE is None or _YAML_RELEASE < MIN_PYYAML:
    die(f'build-traceability.py requires a stable PyYAML release >= {MIN_PYYAML_TEXT} (no pre-releases); found {_YAML_VERSION}. Install one (apt-get install python3-yaml, or python3 -m pip install "PyYAML>={MIN_PYYAML_TEXT}"). See documentation/DEPENDENCIES.md')
if '--check-dependencies' in sys.argv[1:]:
    print(f'PyYAML {_YAML_VERSION} (minimum {MIN_PYYAML_TEXT}): ok')
    sys.exit(0)


class _Parser(argparse.ArgumentParser):
    def error(self, message):
        self.print_usage(sys.stderr)
        die(f'{self.prog}: error: {message}')


ap = _Parser()
ap.add_argument('junit'); ap.add_argument('--cli'); ap.add_argument('--pkg'); ap.add_argument('--admin'); ap.add_argument('--a11y'); ap.add_argument('--smoke'); ap.add_argument('--content'); ap.add_argument('--classic'); ap.add_argument('--crawl'); ap.add_argument('--partial'); ap.add_argument('--we'); ap.add_argument('--classic-a')
ap.add_argument('--out', default='documentation/acceptance-criteria-traceability.yml')
ap.add_argument('--decisions', default='documentation/owner-acceptance-decisions.yml')
ap.add_argument('--designation', default='documentation/review-designation.yml')
ap.add_argument('--records', default='documentation/acceptance-records')
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

# ---- Owner acceptance decisions: hand-maintained input, never generated, fail-closed. -------------------------
# Only the owner's recorded decisions can make a sub-criterion 'accepted'. A criterion is accepted only when EVERY
# required sub-criterion listed here is accepted. Criteria not listed here stay 'accepted: false'.
REQUIRED_SUBS = {21: ('C21-021', 'C21-022', 'C21-M01')}
OWNER_ROLE = 'owner'
SCHEMA_VERSION = 1


class _StrictLoader(yaml.SafeLoader):
    """SafeLoader that rejects duplicate mapping keys instead of silently keeping the last one."""


def _no_duplicate_keys(loader, node, deep=False):
    seen = set()
    for key_node, _ in node.value:
        key = loader.construct_object(key_node, deep=deep)
        try:
            hash(key)
        except TypeError:
            raise ValueError(f'a mapping key of type {type(key).__name__} is not allowed (keys must be plain scalars)') from None
        if key in seen:
            raise ValueError(f'duplicate key {key!r}')
        seen.add(key)
    return yaml.SafeLoader.construct_mapping(loader, node, deep)


_StrictLoader.add_constructor(yaml.resolver.BaseResolver.DEFAULT_MAPPING_TAG, _no_duplicate_keys)


def load_decisions(path, today):
    def fail(msg):
        die(f'owner-acceptance-decisions: {msg}')
    try:
        with open(path, encoding='utf-8') as fh:
            raw = yaml.load(fh, Loader=_StrictLoader)
    except FileNotFoundError:
        fail(f'{path} is missing; it is required')
    except OSError as e:
        fail(f'cannot read {path}: {e.strerror or e}')
    except (yaml.YAMLError, ValueError, RecursionError) as e:
        fail(f'cannot parse {path}: {e}')
    except Exception as e:  # last resort: never a traceback, never silent; the file is rejected and the run fails
        fail(f'unexpected problem while reading {path} ({type(e).__name__}: {e}); the file is rejected')
    try:
        return _validate_decisions(raw, today, fail)
    except SystemExit:
        raise
    except Exception as e:  # malformed input must never end in a traceback; the file is rejected
        fail(f'unexpected problem while validating ({type(e).__name__}: {e}); the file is rejected')


def _validate_decisions(raw, today, fail):
    if not isinstance(raw, dict) or not all(isinstance(k, str) for k in raw) or set(raw) != {'schema_version', 'decisions'}:
        fail("top level must be a mapping with exactly the keys 'schema_version' and 'decisions'")
    if type(raw['schema_version']) is not int or raw['schema_version'] != SCHEMA_VERSION:
        fail(f'schema_version must be the integer {SCHEMA_VERSION}')
    if not isinstance(raw['decisions'], list):
        fail("'decisions' must be a list")
    known = {s for subs in REQUIRED_SUBS.values() for s in subs}
    need = {'id', 'status', 'evidence', 'accepted_on', 'accepted_by'}
    out = {}
    for i, e in enumerate(raw['decisions']):
        where = f'decisions[{i}]'
        if not isinstance(e, dict) or not all(isinstance(k, str) for k in e):
            fail(f'{where} must be a mapping with string keys')
        if not need <= set(e) or set(e) - need - {'note'}:
            fail(f"{where} must have exactly the keys {sorted(need)} (and optionally 'note'); got {sorted(e)}")
        sid = e['id']
        if not isinstance(sid, str):
            fail(f'{where}: id must be a string')
        if sid not in known:
            fail(f'{where}: unknown id {sid!r}; known ids are {sorted(known)}')
        if sid in out:
            fail(f'{where}: duplicate entry for {sid}')
        st = e['status']
        if not isinstance(st, str) or st not in ('pending', 'accepted'):
            fail(f"{where} ({sid}): status must be 'pending' or 'accepted', got {st!r}")
        ev = e['evidence']
        if not isinstance(ev, list) or any(not isinstance(x, str) or not x.strip() for x in ev):
            fail(f'{where} ({sid}): evidence must be a list of non-empty strings')
        if 'note' in e and not isinstance(e['note'], str):
            fail(f'{where} ({sid}): note must be a string')
        on, by = e['accepted_on'], e['accepted_by']
        if isinstance(on, datetime.datetime):
            fail(f'{where} ({sid}): accepted_on must be a date without a time')
        if isinstance(on, datetime.date):
            on = on.isoformat()
        if on is not None and not isinstance(on, str):
            fail(f'{where} ({sid}): accepted_on must be a YYYY-MM-DD date or null')
        if by is not None and not isinstance(by, str):
            fail(f'{where} ({sid}): accepted_by must be a string or null')
        if st == 'pending':
            if on is not None or by is not None:
                fail(f'{where} ({sid}): a pending entry must have accepted_on and accepted_by set to null')
        else:
            if not ev:
                fail(f'{where} ({sid}): an accepted entry needs at least one evidence reference')
            if not (isinstance(on, str) and re.fullmatch(r'\d{4}-\d{2}-\d{2}', on)):
                fail(f'{where} ({sid}): accepted_on must be a YYYY-MM-DD date')
            try:
                day = datetime.date.fromisoformat(on)
            except ValueError:
                fail(f'{where} ({sid}): accepted_on {on!r} is not a real date')
            if day > today:
                fail(f'{where} ({sid}): accepted_on {on} is in the future')
            if by != OWNER_ROLE:
                fail(f"{where} ({sid}): accepted_by must be the role label {OWNER_ROLE!r}")
        out[sid] = {'status': st, 'evidence': ev, 'accepted_on': on, 'accepted_by': by}
    return out


DEC = load_decisions(a.decisions, datetime.date.today())
PENDING = {'status': 'pending', 'evidence': [], 'accepted_on': None, 'accepted_by': None}


def sub_status(sid):
    return DEC.get(sid, PENDING)


def criterion_accepted(n):
    subs = REQUIRED_SUBS.get(n)
    return bool(subs) and all(sub_status(s)['status'] == 'accepted' for s in subs)


def sub_lines(n):
    if n not in REQUIRED_SUBS:
        return []
    out = ['    subcriteria_acceptance:']
    for sid in REQUIRED_SUBS[n]:
        d = sub_status(sid)
        out += [f'      - id: {sid}', f"        status: {d['status']}"]
        out += ['        evidence: []'] if not d['evidence'] else ['        evidence:'] + [f'          - {json.dumps(x)}' for x in d['evidence']]
        out += [f"        accepted_on: {json.dumps(d['accepted_on'])}", f"        accepted_by: {json.dumps(d['accepted_by'])}"]
    return out


def _owner(sid):
    d = sub_status(sid)
    return f"ACCEPTED by the owner on {d['accepted_on']}" if d['status'] == 'accepted' else 'AWAITING OWNER ACCEPTANCE. Not accepted'


def _pin_acceptance():
    """Owner-acceptance wording of the register summary, derived from the decisions for C21-021 and C21-022 independently.
    Both pending keeps the original wording; otherwise each sub-criterion states its own state and date."""
    if sub_status('C21-021')['status'] != 'accepted' and sub_status('C21-022')['status'] != 'accepted':
        return 'C21-021 and C21-022: EVIDENCED, AWAITING OWNER ACCEPTANCE'

    def one(sid):
        d = sub_status(sid)
        return f"ACCEPTED BY THE OWNER on {d['accepted_on']}" if d['status'] == 'accepted' else 'AWAITING OWNER ACCEPTANCE'
    return f"C21-021: EVIDENCED, {one('C21-021')}. C21-022: EVIDENCED, {one('C21-022')}"



# ---- Reviewer designation and manual records: hand-maintained inputs, never generated, fail-closed. ---------------
# The designation names who the owner designated and what is still unverified. It never changes an acceptance status.
# The records folder is only COUNTED here: a record's decision is read from its Decision row, and no record is created or
# completed by this script. A completed record is evidence; it does not make anything accepted.
EXPECTED_RECORDS = ['C01-M01', 'C02-M01', 'C03-M01', 'C06-M01', 'C10-M01', 'C12-M01', 'C13-M01', 'C14-M01', 'C15-M01', 'C17-M01',
                    'C19-M01', 'C20-M01', 'C20-M02', 'C25-M01', 'C27-M01', 'C31-M01', 'C34-M01']
RECORD_DECISIONS = ('PENDING', 'ACCEPTED', 'NOT ACCEPTED')


def load_review(designation_path, records_dir):
    def fail(msg):
        die(f'review-designation: {msg}')
    try:
        with open(designation_path, encoding='utf-8') as fh:
            raw = yaml.load(fh, Loader=_StrictLoader)
    except FileNotFoundError:
        fail(f'{designation_path} is missing; it is required')
    except OSError as e:
        fail(f'cannot read {designation_path}: {e.strerror or e}')
    except (yaml.YAMLError, ValueError, RecursionError) as e:
        fail(f'cannot parse {designation_path}: {e}')
    try:
        if not isinstance(raw, dict) or not all(isinstance(k, str) for k in raw):
            fail('the top level must be a mapping with string keys')
        if type(raw.get('schema_version')) is not int or raw['schema_version'] != 1:
            fail('schema_version must be the integer 1')
        dr = raw.get('designated_reviewer')
        if not isinstance(dr, dict):
            fail("'designated_reviewer' must be a mapping")
        name = dr.get('role_label')
        if not isinstance(name, str) or not name.strip():
            fail('designated_reviewer.role_label must be a non-empty string')
        if not isinstance(dr.get('name'), str) or not dr['name'].strip():
            fail('designated_reviewer.name must be a non-empty string')
        if dr.get('declarations_held_privately') is not True:
            fail('designated_reviewer.declarations_held_privately must be true: declarations are not recorded in this public repository')
        if dr.get('accountability') != 'designated_reviewer' or dr.get('human_sign_off') != 'designated_reviewer':
            fail('accountability and human_sign_off must both stay with the designated_reviewer')
        tis = raw.get('technical_inspection_support')
        if not isinstance(tis, dict) or not isinstance(tis.get('name'), str) or not tis['name'].strip():
            fail('technical_inspection_support.name must be a non-empty string')
        if tis.get('separate_reviewer') is not False or tis.get('may_sign_or_declare_for_reviewer') is not False:
            fail('technical inspection support must not be a separate reviewer and must not sign or declare for the reviewer')
        eng = raw.get('engagement')
        if not isinstance(eng, dict) or eng.get('correspondence_held_privately') is not True or eng.get('verifies_independence') is not False:
            fail('engagement must keep its correspondence private and must not claim to verify independence')
        if eng.get('owner_accepted_arrangement') is not True:
            fail('engagement.owner_accepted_arrangement must be true once recorded')
        if dr.get('designated_by') != 'owner':
            fail("designated_reviewer.designated_by must be 'owner'")
        when = dr.get('instruction_recorded')
        if isinstance(when, datetime.date) and not isinstance(when, datetime.datetime):
            when = when.isoformat()
        if not (isinstance(when, str) and re.fullmatch(r'\d{4}-\d{2}-\d{2}', when)):
            fail('designated_reviewer.instruction_recorded must be a YYYY-MM-DD date')
        try:
            day = datetime.date.fromisoformat(when)
        except ValueError:
            fail(f'instruction_recorded {when!r} is not a real date')
        if day > datetime.datetime.now(datetime.timezone.utc).date():
            fail(f'instruction_recorded {when} is in the future')
        scope = dr.get('scope')
        if not isinstance(scope, dict) or type(scope.get('reviewer_run_records')) is not int or type(scope.get('owner_run_records')) is not int:
            fail('designated_reviewer.scope must give integer reviewer_run_records and owner_run_records')
        if scope['reviewer_run_records'] + scope['owner_run_records'] != len(EXPECTED_RECORDS):
            fail(f'the scope must add up to {len(EXPECTED_RECORDS)} records')
        if dr.get('contact_details_recorded') is not False:
            fail('contact_details_recorded must be false: no contact details are recorded in this repository')
        ind = raw.get('independence')
        if not isinstance(ind, dict) or ind.get('status') not in ('not_verified', 'verified'):
            fail("independence.status must be 'not_verified' or 'verified'")
        checks = ind.get('checks')
        if not isinstance(checks, list) or not checks:
            fail('independence.checks must be a non-empty list')
        ids = set()
        for i, c in enumerate(checks):
            if not isinstance(c, dict) or set(c) != {'id', 'requirement', 'state', 'note'} or not all(isinstance(v, str) and v.strip() for v in c.values()):
                fail(f'independence.checks[{i}] must have exactly the non-empty string keys id, requirement, state and note')
            if c['id'] in ids:
                fail(f"independence.checks[{i}]: duplicate id {c['id']}")
            ids.add(c['id'])
        open_checks = [c['id'] for c in checks if c['state'] != 'verified']
        if ind['status'] == 'verified' and open_checks:
            fail(f"independence.status cannot be 'verified' while checks are open: {open_checks}")
        # The records: exactly the expected files; each Decision row is read, nothing else is trusted.
        try:
            present = sorted(f[:-3] for f in os.listdir(records_dir) if f.endswith('.md') and f != 'README.md')
        except OSError as e:
            fail(f'cannot read the records folder {records_dir}: {e.strerror or e}')
        if present != sorted(EXPECTED_RECORDS):
            fail(f'the records folder must hold exactly the {len(EXPECTED_RECORDS)} expected records; found {present}')
        tally = collections.Counter()
        for rid in EXPECTED_RECORDS:
            with open(os.path.join(records_dir, rid + '.md'), encoding='utf-8') as fh:
                m = re.search(r'^\| Decision \| (PENDING|ACCEPTED|NOT ACCEPTED) \|$', fh.read(), re.M)
            if not m:
                fail(f'record {rid} has no valid Decision row')
            tally[m.group(1)] += 1
        return {'name': name.strip(), 'when': when, 'open_checks': open_checks, 'independence': ind['status'], 'tally': tally,
                'reviewer_run': scope['reviewer_run_records'], 'owner_run': scope['owner_run_records']}
    except SystemExit:
        raise
    except Exception as e:
        fail(f'unexpected problem while validating ({type(e).__name__}: {e}); the file is rejected')


REVIEW = load_review(a.designation, a.records)
_done = REVIEW['tally']['ACCEPTED'] + REVIEW['tally']['NOT ACCEPTED']
MANUAL_REVIEW = (f"Reviewer: {REVIEW['name']}, designated by the owner (instruction recorded {REVIEW['when']}); declarations are held privately by the owner. Technical inspection support (a service the reviewer directs) is not a separate reviewer and cannot sign or declare for the reviewer; the owner accepted this arrangement on 2026-11-13, which verifies no independence check. "
                 + ('Independence: VERIFIED.' if REVIEW['independence'] == 'verified' else f"Independence: NOT VERIFIED ({len(REVIEW['open_checks'])} independence checks are open; see documentation/REVIEW-PLAN.md).")
                 + f" Manual records prepared in documentation/acceptance-records/: {len(EXPECTED_RECORDS)} ({REVIEW['reviewer_run']} reviewer-run, {REVIEW['owner_run']} owner-run); "
                 + f"completed: {_done}; pending: {REVIEW['tally']['PENDING']}. A completed record is evidence, not acceptance, and does not change accepted_count.")

SUB_TOTAL = sum(len(v) for v in REQUIRED_SUBS.values())
SUB_ACCEPTED = sum(1 for v in REQUIRED_SUBS.values() for sid in v if sub_status(sid)['status'] == 'accepted')
G21_021 = f'C21-021 (version and source match the verified pin): EVIDENCED by hosted strict run 43316091579. {_owner("C21-021")}'
G21_022 = f'C21-022 (record of the settings used): EVIDENCED by the settings record of strict run 43316091579 (classic-editor-replace=block; classic-editor-allow-users not stored, the plugin default applies). {_owner("C21-022")}'
M21 = 'C21-M01 manual editor check' if sub_status('C21-M01')['status'] != 'accepted' else 'none'
G21_M01 = ('C21-M01 (manual editor check) is still pending, so criterion 21 as a whole is not accepted'
           if sub_status('C21-M01')['status'] != 'accepted' else f"C21-M01 (manual editor check): {_owner('C21-M01')}")

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
 21: ('Classic Editor compatibility', 'automated_complete', 'partial', M21, [CLS, CLA, CLH_CAP, CLH_REV, CLH_STR], [G21_021, G21_022, 'Pin limits: the whole-ZIP SHA-256 was observed stable on one day only; the SVN revision was not recorded; the pin\'s checksums URL is the WP-CLI form and its live reachability is not independently confirmed; the SVN tag and the download host share one trust anchor', 'Configuration A tests C21-001 to C21-005, 011, 013, 015, 017, 019 passed 13/13 against the real package in capture run 43286920565 and strict run 43316091579. Configuration B ran in the wp-env job of run 43316091579 (step passed; its check count was not re-read)', G21_M01]),
 22: ('Private approval status isolation', 'partial', 'partial', None, [], ['In-process only; HTML and feed bodies not sentinel-probed over HTTP; editor and anonymous reads covered']),
 23: ('Snapshot invalidation after editing public content', 'partial', 'partial', None, [], ['Every public scalar field is covered via the registry; relationship, term, thumbnail, gallery membership and order, alt, caption, title covered once each; media file replacement covered by C29; video URL covered as a field; presentation flag covered as a field']),
 24: ('Failed publication attempts through alternative write paths', 'automated_complete', 'local_full', None, [CLI + ' (real WP-CLI write paths)'], ['REST and XML-RPC paths are tested in-process with the endpoints enabled, not over HTTP']),
 25: ('Database migration and upgrade integrity', 'automated_complete', 'partial', 'C25-M01', [], ['Upgrade tested from a simulated v1 (index dropped), the only earlier schema; MySQL 8.4 not run locally']),
 26: ('Preservation of structured records through theme switching and plugin upgrades', 'partial', 'partial', None, [CLI, WES], ['C26-001 and C26-002 now compare content hashes, metadata, relationship ID sets, clearance, approvals, media rights, settings and the existing audit entries, with a sensitivity check; executed through a shim, not wp-env', 'C26-003 to C26-006 are NOT implemented. They belong to M2-D and have prerequisites that need the owner (see documentation/C26-M2D-BOUNDARY-REVIEW.md): no earlier release exists to register, and the attestation needs a reviewer and a hosted run']),
 27: ('Audit trail behaviour', 'automated_complete', 'local_full', 'C27-M01 (supplementary, NOT in the frozen plans; see documentation/C27-M01-PROVENANCE.md)', [], ['C27-001 is a static scan of includes/']),
 28: ('Related-record dependency mechanics', 'partial', 'partial', None, [], ['Loop-prevention, republish-after-lapse and snapshot-excludes-related-content not each asserted']),
 29: ('Media rights confirmation and invalidation', 'automated_complete', 'local_full', None, [], []),
 30: ('Publisher grant scoping', 'automated_complete', 'local_full', None, [], []),
 31: ('Cache invalidation', 'partial', 'partial', 'C31-M01 (owner)', [], ['Core object cache only; no persistent object-cache drop-in test; purge action asserted per trigger, not "exactly once"']),
 32: ('Ineligible content is not served through supported public interfaces', 'automated_complete', 'local_full', None, [], ['In-process queries as an anonymous user; REST has no routes by design; get_posts() with suppress_filters bypasses the read-time filter (documented limit)']),
 33: ('Required and optional relationship behaviour', 'partial', 'partial', None, [], ['Presentation of the grace state is covered by the exposure test only']),
 34: ('Audit key: missing, invalid, rotation and recovery', 'automated_complete', 'local_full', 'C34-M01 (owner)', ['tests-cli/key-custody-rehearsal.py (disposable site, real WP-CLI): 69/69, covering wrong, missing and invalid keys, planned, interrupted and compromise rotation, loss and recovery, old-key-available, restore from backup and tamper detection; also run in hosted CI from run 64'], ['Supplementary automated evidence only: the rehearsal is not the owner record C34-M01. No human has rehearsed rotation or recovery']),
}

_missing = [n for n in REQUIRED_SUBS if n not in C]
if _missing:
    die(f'owner-acceptance-decisions: REQUIRED_SUBS names criteria that are not in the criterion table: {_missing}')
ACCEPTED_COUNT = sum(1 for n in C if criterion_accepted(n))

lines = [
 '# M2 acceptance-criteria traceability (generated by scripts/build-traceability.py)',
 f'# Generated: {datetime.datetime.now(datetime.timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")}',
 '# Executions are LOCAL (PHP 8.3.6, MariaDB 10.11, WordPress 6.4.3) unless an entry cites a hosted run: the first hosted run (commit de59ce1, run 43259095276, 6 of 11 jobs green) and, for criterion 21, the capture run 43286920565 and the strict run 43316091579 (both green).',
 f'# accepted is false unless the owner records acceptance of every required sub-criterion in documentation/owner-acceptance-decisions.yml ({SUB_ACCEPTED} of {SUB_TOTAL} required sub-criteria accepted); acceptance also needs reviewed evidence and, where listed, manual records.',
 'summary:',
 f'  phpunit_total_tests: {total}',
 '  environment: "local: PHP 8.3.6, MariaDB 10.11, WordPress 6.4.3, WP-CLI 2.12.0, Chromium (Playwright 1.63.0), axe-core 4.10.2"',
 f'  accepted_count: {ACCEPTED_COUNT}',
 '  owner_acceptance_decisions: "documentation/owner-acceptance-decisions.yml (hand-maintained by the owner; an entry that is absent is pending; evidence entries are audit references that the generator does not verify)"',
 f'  classic_editor_pin: "verified (1.7.0; independent review 2026-11-12, reference NOBLEN-CORP-CE-INDEPENDENT-REVIEW-2026-11-12; strict hosted run 43316091579 passed). {_pin_acceptance()}"',
 '  c26_003_to_c26_006: "M2-D tooling is implemented and tested (documentation/UPGRADE-VERIFICATION.md); the criteria are NOT satisfied, not waived and not accepted: they need a registered source and target, an independent reviewer, an approved attestation and an authorised hosted run"',
 f'  manual_review: {json.dumps(MANUAL_REVIEW)}',
 'criteria:',
]
for n in sorted(C):
    t, impl, ex, manual, extra, gaps = C[n]
    cnt = counts.get(n, 0)
    ev = ([f'PHPUnit: {cnt - failed.get(n, 0)}/{cnt} tests named test_C{n:02d}_* pass ({"all" if not failed.get(n) else "FAILURES"})'] if cnt else []) + extra
    lines += [
        f'  - id: {n}',
        f'    title: {json.dumps(t)}',
        '    planned: true',
        f'    implemented: {impl}',
        f'    executed: {ex}  # local only',
        f'    accepted: {"true" if criterion_accepted(n) else "false"}',
        f'    manual_records_pending: {json.dumps(manual) if manual else "none"}',
        '    evidence:' if ev else '    evidence: []',
    ] + [f'      - {json.dumps(e)}' for e in ev] + (['    gaps:'] + [f'      - {json.dumps(g)}' for g in gaps] if gaps else ['    gaps: []']) + sub_lines(n)
# Write to a temporary file in the same directory and replace the output only after everything above has succeeded,
# so a failed run can never leave a half-written register behind.
_dir = os.path.dirname(os.path.abspath(a.out))
_fd, _tmp = tempfile.mkstemp(prefix='.traceability-', suffix='.tmp', dir=_dir)
try:
    with os.fdopen(_fd, 'w') as _fh:
        _fh.write('\n'.join(lines) + '\n')
        _fh.flush()
        os.fsync(_fh.fileno())
    os.chmod(_tmp, 0o644)
    os.replace(_tmp, a.out)
except BaseException:
    try:
        os.unlink(_tmp)
    except OSError:
        pass
    raise
print('wrote', a.out, 'criteria with failures:', [n for n in failed])

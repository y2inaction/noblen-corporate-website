#!/usr/bin/env python3
"""Regression tests for the owner-acceptance mechanism of scripts/build-traceability.py.

Needs only python3 and PyYAML (no WordPress, database or network). It never touches the repository's register: every
scenario writes its own decisions file and its own output into a temporary directory.

What it proves:
  - nothing is accepted unless the owner's decisions file says so, and a missing entry is pending;
  - a criterion is accepted only when EVERY required sub-criterion is accepted;
  - missing, malformed, duplicate and unknown entries make generation fail closed (no output file is written);
  - malformed input of any type is rejected with a controlled message, never a Python traceback;
  - a failed run leaves an existing output file untouched and no temporary file behind (atomic replacement);
  - the generator never modifies the owner's decisions file;
  - every failure path says that no register was written and that any existing file is not current;
  - the register carries a UTC generation timestamp with seconds;
  - the PyYAML minimum version (6.0.1) is enforced and only a STABLE release is accepted: every pre-release (6.0.1.dev0,
    6.0.1rc1, and also 6.0.2.dev0, 6.1.0b1, ...) and any unrecognised version text is refused;
  - the last-resort guards in the decisions loader turn an unexpected internal error into a controlled diagnostic, a non-zero
    exit and the not-current notice (never a traceback, never silence);
  - this script removes its own temporary directory, whether the tests pass, fail, raise, are interrupted or are terminated;
  - changing acceptance state changes nothing in the other 33 criteria (byte-identical blocks);
  - the acceptance wording of the register summary ('classic_editor_pin') is derived from the owner's decisions for C21-021
    and C21-022 independently (both pending, either one accepted, both accepted) and stays consistent with the
    sub-criteria block, the gap texts, the criterion flag, the accepted count and the header for every combination;
  - the committed register agrees with the committed decisions file.
The 'evidence' strings in the decisions file are audit references typed by the owner. Nothing here (or in the generator)
verifies that the referenced records exist.
"""
import atexit, datetime, glob, hashlib, os, re, shutil, signal, stat, subprocess, sys, tempfile
import yaml

REPO = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
GEN = os.path.join(REPO, 'scripts', 'build-traceability.py')
REG = os.path.join(REPO, 'documentation', 'acceptance-criteria-traceability.yml')
DEC = os.path.join(REPO, 'documentation', 'owner-acceptance-decisions.yml')
T = tempfile.mkdtemp(prefix='accdec-')


def _unlock(path):
    """Make a directory tree deletable again (a test may leave a read-only directory behind when it fails)."""
    try:
        os.chmod(path, 0o755)
        names = os.listdir(path)
    except OSError:
        return
    for name in names:
        sub = os.path.join(path, name)
        if os.path.isdir(sub) and not os.path.islink(sub):
            _unlock(sub)


def _cleanup():
    _unlock(T)
    shutil.rmtree(T, ignore_errors=True)


# Runs on a normal finish, on sys.exit, and on an unhandled exception. SIGTERM and SIGHUP are turned into a normal exit so
# that the cleanup also runs when the script is terminated (SIGINT already raises KeyboardInterrupt). Only SIGKILL cannot be handled.
atexit.register(_cleanup)
for _sig in (signal.SIGTERM, signal.SIGHUP):
    signal.signal(_sig, lambda num, frame: sys.exit(128 + num))
pass_n = fail_n = 0


def ok(name):
    global pass_n
    pass_n += 1
    print(f'ok   {name}')


def bad(name, why=''):
    global fail_n
    fail_n += 1
    print(f'FAIL {name} :: {why}')


def check(name, cond, why=''):
    ok(name) if cond else bad(name, why)


# A synthetic junit built from the committed register's own per-criterion PHPUnit counts, so the evidence lines can be
# regenerated. Only the acceptance behaviour is under test here.
committed = yaml.safe_load(open(REG))
cases, named = [], 0
for c in committed['criteria']:
    for e in c['evidence']:
        m = re.match(r'PHPUnit: (\d+)/(\d+) tests named test_C(\d\d)_', e)
        if m:
            named += int(m.group(2))
            cases += [f'test_C{m.group(3)}_x{i}' for i in range(int(m.group(2)))]
cases += [f'testOther{i}' for i in range(committed['summary']['phpunit_total_tests'] - named)]
JUNIT = os.path.join(T, 'junit.xml')
open(JUNIT, 'w').write('<testsuites><testsuite>' + ''.join(f'<testcase name="{c}"/>' for c in cases) + '</testsuite></testsuites>')
FLAGS = ['--cli', '20/20', '--pkg', '12/12', '--admin', '21/21', '--a11y', '43/43', '--smoke', 'PASS', '--content', '41/41',
         '--classic', '13/13', '--crawl', 'PASS', '--partial', '13/13', '--we', '25/25', '--classic-a', '13/13']
_n = [0]


def generate(decisions_text=None, decisions_path=None, decisions_bytes=None, out_path=None, junit=None, python_args=('-I',)):
    _n[0] += 1
    out = out_path or os.path.join(T, f'out{_n[0]}.yml')
    path = decisions_path
    if decisions_text is not None:
        path = os.path.join(T, f'dec{_n[0]}.yml')
        open(path, 'w').write(decisions_text)
    if decisions_bytes is not None:
        path = os.path.join(T, f'dec{_n[0]}.bin')
        open(path, 'wb').write(decisions_bytes)
    r = subprocess.run([sys.executable, *python_args, GEN, junit or JUNIT, *FLAGS, '--decisions', path, '--out', out],
                       capture_output=True, text=True, cwd=REPO)
    return r.returncode, (r.stdout + r.stderr), out


def blocks(path):
    lines = open(path).read().split('\n')
    idx = [(i, int(re.match(r'  - id: (\d+)$', l).group(1))) for i, l in enumerate(lines) if re.match(r'  - id: \d+$', l)]
    out = {}
    for k, (i, n) in enumerate(idx):
        out[n] = '\n'.join(lines[i: idx[k + 1][0] if k + 1 < len(idx) else len(lines)])
    return out


def entry(sid, status='pending', evidence='[]', on='null', by='null', extra=''):
    return f'  - id: {sid}\n    status: {status}\n    evidence: {evidence}\n    accepted_on: {on}\n    accepted_by: {by}\n{extra}'


def doc(*entries, head='schema_version: 1\ndecisions:\n'):
    return head + ''.join(entries)


EV = '\n      - "CI run 43316091579, job 131524371513, artifact 13988531774"'
PAST = '2026-11-12'


def accepted(sid, on=PAST):
    return entry(sid, 'accepted', EV, on, 'owner')


def crit21(path):
    return next(c for c in yaml.safe_load(open(path))['criteria'] if c['id'] == 21)


# ---- Baseline: every decision pending.
PENDING = doc(entry('C21-021'), entry('C21-022'), entry('C21-M01'))
rc, msg, base = generate(PENDING)
check('baseline: all pending generates', rc == 0, msg)
b = yaml.safe_load(open(base))
c21 = crit21(base)
check('baseline: criterion 21 is not accepted', c21['accepted'] is False)
check('baseline: accepted_count is 0', b['summary']['accepted_count'] == 0)
check('baseline: every criterion is accepted: false', all(c['accepted'] is False for c in b['criteria']))
check('baseline: sub-criteria listed and pending', [(s['id'], s['status']) for s in c21['subcriteria_acceptance']] ==
      [('C21-021', 'pending'), ('C21-022', 'pending'), ('C21-M01', 'pending')])
check('baseline: manual record C21-M01 still listed as pending', c21['manual_records_pending'] == 'C21-M01 manual editor check')
BASE_BLOCKS = blocks(base)

# ---- A missing entry or a missing file is never acceptance.
rc, msg, out = generate(doc(entry('C21-021'), entry('C21-022')))
check('missing entry (C21-M01 absent) generates and is pending', rc == 0 and next(s for s in crit21(out)['subcriteria_acceptance'] if s['id'] == 'C21-M01')['status'] == 'pending', msg)
rc, msg, out = generate(doc(accepted('C21-021'), accepted('C21-022')))
check('missing C21-M01 keeps criterion 21 unaccepted even if the other two are accepted', rc == 0 and crit21(out)['accepted'] is False, msg)
rc, msg, out = generate(doc(head='schema_version: 1\ndecisions: []\n'))
check('empty decisions list generates with everything pending', rc == 0 and crit21(out)['accepted'] is False, msg)

# ---- Partial and full acceptance, and the other 33 criteria stay byte-identical.
rc, msg, out = generate(doc(accepted('C21-021'), accepted('C21-022'), entry('C21-M01')))
check('two accepted + C21-M01 pending: criterion 21 stays unaccepted', rc == 0 and crit21(out)['accepted'] is False and yaml.safe_load(open(out))['summary']['accepted_count'] == 0, msg)
rc, msg, out = generate(doc(accepted('C21-021'), entry('C21-022'), accepted('C21-M01')))
check('C21-022 pending: criterion 21 stays unaccepted', rc == 0 and crit21(out)['accepted'] is False, msg)
rc, msg, out = generate(doc(accepted('C21-021'), accepted('C21-022'), accepted('C21-M01')))
full = yaml.safe_load(open(out)) if rc == 0 else None
check('all three accepted: criterion 21 accepted and accepted_count 1', rc == 0 and crit21(out)['accepted'] is True and full['summary']['accepted_count'] == 1, msg)
check('all three accepted: manual record no longer pending', rc == 0 and crit21(out)['manual_records_pending'] == 'none')
check('all three accepted: gaps say ACCEPTED by the owner with the date', rc == 0 and sum('ACCEPTED by the owner on 2026-11-12' in g for g in crit21(out)['gaps']) == 3)
nb = blocks(out) if rc == 0 else {}
same = [n for n in range(1, 35) if n != 21 and nb.get(n) == BASE_BLOCKS.get(n)]
check('all three accepted: the other 33 criteria are byte-identical to the baseline', len(same) == 33, f'identical {len(same)}/33')
check('all three accepted: the other 33 criteria are all still unaccepted', rc == 0 and all(c['accepted'] is False for c in full['criteria'] if c['id'] != 21))
check('accepted_count equals the number of accepted criteria (computed)', rc == 0 and full['summary']['accepted_count'] == sum(c['accepted'] for c in full['criteria']))
check('unquoted and quoted dates are both accepted', generate(doc(accepted('C21-021', '"2026-11-12"'), accepted('C21-022'), accepted('C21-M01')))[0] == 0)
check("an optional 'note' is allowed", generate(doc(entry('C21-021', extra='    note: "x"\n'), entry('C21-022'), entry('C21-M01')))[0] == 0)

# ---- The summary wording about the pin follows the owner's decisions, for C21-021 and C21-022 independently.
PIN_HEAD = 'verified (1.7.0; independent review 2026-11-12, reference NOBLEN-CORP-CE-INDEPENDENT-REVIEW-2026-11-12; strict hosted run 43316091579 passed). '
D21, D22, DM = '2026-11-10', '2026-11-11', '2026-11-12'   # distinct dates, so that a mix-up between the entries shows
AWAIT = 'AWAITING OWNER ACCEPTANCE'


def pin_line(path):
    return next(l for l in open(path).read().split('\n') if l.startswith('  classic_editor_pin: '))


def pin_states(line):
    """Parse the wording back into {'C21-021': 'pending'|date, 'C21-022': ...}; None if it matches no allowed form."""
    if line == f'  classic_editor_pin: "{PIN_HEAD}C21-021 and C21-022: EVIDENCED, {AWAIT}"':
        return {'C21-021': 'pending', 'C21-022': 'pending'}
    m = re.fullmatch(re.escape(f'  classic_editor_pin: "{PIN_HEAD}') + r'C21-021: EVIDENCED, (ACCEPTED BY THE OWNER on (\d{4}-\d\d-\d\d)|' + AWAIT +
                     r'). C21-022: EVIDENCED, (ACCEPTED BY THE OWNER on (\d{4}-\d\d-\d\d)|' + AWAIT + r')"', line)
    return {'C21-021': m.group(2) or 'pending', 'C21-022': m.group(4) or 'pending'} if m else None


def entry_for(sid, day):
    return accepted(sid, day) if day else entry(sid)


rc, msg, out = generate(PENDING)
BASE_EVIDENCE = crit21(out)['evidence']
for label, d21, d22, want in (
        ('both pending', None, None, f'  classic_editor_pin: "{PIN_HEAD}C21-021 and C21-022: EVIDENCED, {AWAIT}"'),
        ('C21-021 accepted, C21-022 pending', D21, None, f'  classic_editor_pin: "{PIN_HEAD}C21-021: EVIDENCED, ACCEPTED BY THE OWNER on {D21}. C21-022: EVIDENCED, {AWAIT}"'),
        ('C21-021 pending, C21-022 accepted', None, D22, f'  classic_editor_pin: "{PIN_HEAD}C21-021: EVIDENCED, {AWAIT}. C21-022: EVIDENCED, ACCEPTED BY THE OWNER on {D22}"'),
        ('both accepted (C21-M01 still pending)', D21, D22, f'  classic_editor_pin: "{PIN_HEAD}C21-021: EVIDENCED, ACCEPTED BY THE OWNER on {D21}. C21-022: EVIDENCED, ACCEPTED BY THE OWNER on {D22}"')):
    rc, msg, out = generate(doc(entry_for('C21-021', d21), entry_for('C21-022', d22), entry('C21-M01')))
    line = pin_line(out) if rc == 0 else ''
    check(f'pin wording ({label}): exactly the expected text', rc == 0 and line == want, f'got: {line}')
    check(f'pin wording ({label}): the pin-verification and strict-run description is preserved', PIN_HEAD in line)
    check(f'pin wording ({label}): criterion 21 is not claimed accepted while C21-M01 is pending',
          rc == 0 and crit21(out)['accepted'] is False and yaml.safe_load(open(out))['summary']['accepted_count'] == 0 and 'criterion' not in line.lower())
    check(f'pin wording ({label}): the evidence descriptions of criterion 21 (pin review and strict run) are unchanged', rc == 0 and crit21(out)['evidence'] == BASE_EVIDENCE)
    check(f'pin wording ({label}): the line stays one valid YAML scalar', rc == 0 and isinstance(yaml.safe_load(open(out))['summary']['classic_editor_pin'], str))

rc, msg, out = generate(doc(accepted('C21-021', D21), accepted('C21-022', D22), accepted('C21-M01', DM)))
reg_all = yaml.safe_load(open(out)) if rc == 0 else {}
check('all three accepted: the summary wording says both C21-021 and C21-022 are accepted, with their own dates',
      rc == 0 and pin_line(out) == f'  classic_editor_pin: "{PIN_HEAD}C21-021: EVIDENCED, ACCEPTED BY THE OWNER on {D21}. C21-022: EVIDENCED, ACCEPTED BY THE OWNER on {D22}"', msg)
check('all three accepted: no wording anywhere still says AWAITING OWNER ACCEPTANCE or Not accepted for criterion 21',
      rc == 0 and 'AWAITING OWNER ACCEPTANCE' not in pin_line(out) and not any('AWAITING' in g or 'Not accepted' in g or 'still pending' in g for g in crit21(out)['gaps']))
check('all three accepted: summary wording, sub-criteria block, gaps, flag, count and header agree',
      rc == 0 and crit21(out)['accepted'] is True and reg_all['summary']['accepted_count'] == 1
      and [s['status'] for s in crit21(out)['subcriteria_acceptance']] == ['accepted'] * 3
      and '(3 of 3 required sub-criteria accepted)' in open(out).read().split('\n')[3] and crit21(out)['manual_records_pending'] == 'none')

# Every combination of the three decisions (distinct dates): the wording is consistent with everything else in the register.
import itertools
for combo in itertools.product((False, True), repeat=3):
    days = (D21, D22, DM)
    rc, msg, out = generate(doc(*(entry_for(sid, days[i] if combo[i] else None) for i, sid in enumerate(('C21-021', 'C21-022', 'C21-M01')))))
    tag = ''.join('A' if c else 'p' for c in combo)
    if rc != 0:
        bad(f'consistency matrix {tag}: generated', msg)
        continue
    states, c21x = pin_states(pin_line(out)), crit21(out)
    want_states = {'C21-021': D21 if combo[0] else 'pending', 'C21-022': D22 if combo[1] else 'pending'}
    subs = {s['id']: s for s in c21x['subcriteria_acceptance']}
    gaps = {g[:7]: g for g in c21x['gaps'] if g[:4] == 'C21-'}
    check(f'consistency matrix {tag}: the summary wording is one of the allowed forms and matches the decisions', states == want_states, f'{pin_line(out)}')
    check(f'consistency matrix {tag}: the sub-criteria block matches the decisions',
          [(subs[k]['status'], subs[k]['accepted_on']) for k in ('C21-021', 'C21-022', 'C21-M01')] ==
          [('accepted', days[i]) if combo[i] else ('pending', None) for i in range(3)])
    check(f'consistency matrix {tag}: each gap text matches its own sub-criterion',
          all((f'ACCEPTED by the owner on {days[i]}' in gaps[sid]) == bool(combo[i]) and (('AWAITING OWNER ACCEPTANCE' in gaps[sid]) == (not combo[i]))
              for i, sid in enumerate(('C21-021', 'C21-022')))
          and (('C21-M01 (manual editor check) is still pending' in ' '.join(c21x['gaps'])) == (not combo[2])))
    check(f'consistency matrix {tag}: criterion flag, count and header count follow the decisions',
          c21x['accepted'] is all(combo) and yaml.safe_load(open(out))['summary']['accepted_count'] == (1 if all(combo) else 0)
          and f'({sum(combo)} of 3 required sub-criteria accepted)' in open(out).read().split('\n')[3])

# ---- Fail closed: each of these must fail, name the problem, and write no output.
future = (datetime.date.today() + datetime.timedelta(days=1)).isoformat()
bad_cases = {
    'missing decisions file': (None, os.path.join(T, 'does-not-exist.yml'), 'missing'),
    'unparseable YAML': ('schema_version: 1\ndecisions: [\n', None, 'cannot parse'),
    'top level is not a mapping': ('- a\n- b\n', None, 'top level'),
    'extra top-level key': (doc(entry('C21-021'), head='schema_version: 1\nextra: 1\ndecisions:\n'), None, 'top level'),
    'schema_version 2': (doc(entry('C21-021'), head='schema_version: 2\ndecisions:\n'), None, 'schema_version'),
    'schema_version true': (doc(entry('C21-021'), head='schema_version: true\ndecisions:\n'), None, 'schema_version'),
    'decisions is not a list': ('schema_version: 1\ndecisions: {a: 1}\n', None, "must be a list"),
    'entry is not a mapping': (doc('  - just-a-string\n'), None, 'must be a mapping'),
    'entry missing a key': (doc('  - id: C21-021\n    status: pending\n'), None, 'must have exactly the keys'),
    'entry with an extra key': (doc(entry('C21-021', extra='    approved: true\n')), None, 'must have exactly the keys'),
    'unknown id (typo)': (doc(entry('C21-099')), None, 'unknown id'),
    'id belonging to another criterion': (doc(entry('C22-001')), None, 'unknown id'),
    'duplicate id': (doc(entry('C21-021'), entry('C21-021')), None, 'duplicate entry'),
    'duplicate mapping key in one entry': (doc('  - id: C21-021\n    status: pending\n    status: accepted\n    evidence: []\n    accepted_on: null\n    accepted_by: null\n'), None, 'duplicate key'),
    'invalid status value': (doc(entry('C21-021', 'done')), None, "status must be"),
    'accepted without evidence': (doc(entry('C21-021', 'accepted', '[]', PAST, 'owner')), None, 'needs at least one evidence'),
    'evidence is not a list': (doc(entry('C21-021', 'accepted', '"run 1"', PAST, 'owner')), None, 'evidence must be a list'),
    'evidence item is empty': (doc(entry('C21-021', 'accepted', '\n      - ""', PAST, 'owner')), None, 'evidence must be a list'),
    'accepted without a date': (doc(entry('C21-021', 'accepted', EV, 'null', 'owner')), None, 'YYYY-MM-DD'),
    'accepted date in the wrong format': (doc(entry('C21-021', 'accepted', EV, '"12/11/2026"', 'owner')), None, 'YYYY-MM-DD'),
    'accepted date that does not exist': (doc(entry('C21-021', 'accepted', EV, '"2026-02-30"', 'owner')), None, 'not a real date'),
    'accepted date in the future': (doc(entry('C21-021', 'accepted', EV, future, 'owner')), None, 'in the future'),
    'accepted_by is a person, not the role label': (doc(entry('C21-021', 'accepted', EV, PAST, '"Some Person"')), None, "role label"),
    'accepted_by is null': (doc(entry('C21-021', 'accepted', EV, PAST, 'null')), None, 'role label'),
    'pending entry with a date': (doc(entry('C21-021', 'pending', '[]', PAST, 'null')), None, 'must have accepted_on'),
    'pending entry with accepted_by': (doc(entry('C21-021', 'pending', '[]', 'null', 'owner')), None, 'must have accepted_on'),
    # Type tightening: every one of these must give a controlled diagnostic, never a traceback.
    'id is a list': (doc(entry('[a]')), None, 'id must be a string'),
    'id is a number': (doc(entry('21')), None, 'id must be a string'),
    'id is null': (doc(entry('null')), None, 'id must be a string'),
    'status is a list': (doc(entry('C21-021', '[x]')), None, 'status must be'),
    'status is a number': (doc(entry('C21-021', '1')), None, 'status must be'),
    'status is null': (doc(entry('C21-021', 'null')), None, 'status must be'),
    'evidence is a mapping': (doc(entry('C21-021', 'accepted', '{a: 1}', PAST, 'owner')), None, 'evidence must be a list'),
    'evidence items are numbers': (doc(entry('C21-021', 'accepted', '[1, 2]', PAST, 'owner')), None, 'evidence must be a list'),
    'note is a list': (doc(entry('C21-021', extra='    note: [a]\n')), None, 'note must be a string'),
    'note is a number': (doc(entry('C21-021', extra='    note: 5\n')), None, 'note must be a string'),
    'accepted_on is a list': (doc(entry('C21-021', 'accepted', EV, '[1]', 'owner')), None, 'accepted_on must be'),
    'accepted_on is a number': (doc(entry('C21-021', 'accepted', EV, '20261112', 'owner')), None, 'accepted_on must be'),
    'accepted_on is a boolean': (doc(entry('C21-021', 'accepted', EV, 'true', 'owner')), None, 'accepted_on must be'),
    'accepted_on carries a time': (doc(entry('C21-021', 'accepted', EV, '2026-11-12 10:00:00', 'owner')), None, 'without a time'),
    'accepted_by is a list': (doc(entry('C21-021', 'accepted', EV, PAST, '[owner]')), None, 'accepted_by must be'),
    'accepted_by is a number': (doc(entry('C21-021', 'accepted', EV, PAST, '7')), None, 'accepted_by must be'),
    'entry has a non-string key': (doc(entry('C21-021', extra='    5: x\n')), None, 'string keys'),
    'top level has a non-string key': (doc(entry('C21-021'), head='schema_version: 1\n5: x\ndecisions:\n'), None, 'top level'),
    'an entry is a list': (doc('  - [a, b]\n'), None, 'must be a mapping'),
    'top level is a scalar': ('just text\n', None, 'top level'),
    'empty file': ('', None, 'top level'),
    'decisions path is a directory': (None, T, 'cannot read'),
    # Unhashable mapping keys (a sequence or a mapping used as a key) must be rejected explicitly, not crash the loader.
    'unhashable key at the top level (sequence)': ('? [a]\n: 1\nschema_version: 1\ndecisions: []\n', None, 'is not allowed'),
    'unhashable key at the top level (mapping)': ('? {a: 1}\n: 1\nschema_version: 1\ndecisions: []\n', None, 'is not allowed'),
    'unhashable key inside a decision entry (sequence)': (doc(entry('C21-021', extra='    ? [a]\n    : x\n')), None, 'is not allowed'),
    'unhashable key inside a decision entry (mapping)': (doc(entry('C21-021', extra='    ? {a: 1}\n    : x\n')), None, 'is not allowed'),
    'unhashable key inside the evidence list': (doc(entry('C21-021', 'accepted', '\n      - ? [a]\n        : x', PAST, 'owner')), None, 'is not allowed'),
    'deeply nested input': ('schema_version: 1\ndecisions: ' + '[' * 20000 + '\n', None, 'cannot parse'),
}
BAD_BYTES = {'binary (not UTF-8) file': (b'\xff\xfe\x00\x01', 'cannot parse')}
for name, (text, path, needle) in bad_cases.items():
    rc, msg, out = generate(text, path)
    check(f'fails closed: {name}', rc != 0 and needle in msg and not os.path.exists(out) and 'Traceback' not in msg and 'owner-acceptance-decisions:' in msg,
          f'rc={rc} wrote={os.path.exists(out)} msg={msg.strip()[-160:]}')
    check(f'not-current notice: {name}', 'must NOT be treated as current' in msg, msg.strip()[-160:])
# The same unhashable-key inputs, run against an existing register: it must come out byte-for-byte unchanged.
UNHASHABLE = {k: v for k, v in bad_cases.items() if k.startswith('unhashable')}
check('unhashable-key regression cases are present (top level and inside an entry)', len(UNHASHABLE) == 5)
for name, (text, path, needle) in UNHASHABLE.items():
    keep = os.path.join(T, 'unhashable-existing.yml')
    open(keep, 'w').write('previous register content\n')
    rc, msg, _ = generate(text, path, out_path=keep)
    check(f'unhashable key leaves the existing register unmodified: {name}',
          rc != 0 and open(keep).read() == 'previous register content\n' and 'owner-acceptance-decisions:' in msg and 'must NOT be treated as current' in msg
          and 'Traceback' not in msg and 'TypeError' not in msg and not glob.glob(os.path.join(T, '.traceability-*')), f'rc={rc} msg={msg.strip()[-200:]}')
for name, (data, needle) in BAD_BYTES.items():
    rc, msg, out = generate(decisions_bytes=data)
    check(f'fails closed: {name}', rc != 0 and needle in msg and not os.path.exists(out) and 'Traceback' not in msg, f'rc={rc} msg={msg.strip()[-160:]}')

# ---- Atomic output: a failed run never touches an existing register and leaves no temporary file behind.
SENT = os.path.join(T, 'existing-register.yml')
open(SENT, 'w').write('previous register content\n')
before = open(SENT).read()
rc, msg, _ = generate(doc(entry('C21-099')), out_path=SENT)
check('atomic: a failed run leaves the existing output byte-for-byte unchanged', rc != 0 and open(SENT).read() == before)
check('atomic: a failed run leaves no temporary file behind', not glob.glob(os.path.join(T, '.traceability-*')))
OUTDIR = os.path.join(T, 'out-is-a-directory')
os.mkdir(OUTDIR)
rc, msg, _ = generate(PENDING, out_path=OUTDIR)
check('atomic: a failure during the final replace cleans up its temporary file and leaves the target alone', rc != 0 and os.path.isdir(OUTDIR) and not glob.glob(os.path.join(T, '.traceability-*')), msg[-200:])
if os.geteuid() != 0:  # root ignores directory permissions, so this check only means something for an ordinary user
    RODIR = os.path.join(T, 'read-only-dir')
    os.mkdir(RODIR)
    RO_OUT = os.path.join(RODIR, 'register.yml')
    open(RO_OUT, 'w').write('previous register content\n')
    os.chmod(RODIR, 0o555)
    rc, msg, _ = generate(PENDING, out_path=RO_OUT)
    os.chmod(RODIR, 0o755)
    check('atomic: when a temporary file cannot be created the existing output is not modified in place', rc != 0 and open(RO_OUT).read() == 'previous register content\n', msg[-200:])
else:
    print('skip read-only-directory atomicity check (running as root)')
# A successful run must REPLACE the file (new inode), not rewrite it in place: a second hard link to the old register keeps
# the old content. (An in-place rewrite would change what every existing reader of the old file sees, half-written or not.)
HL_OUT = os.path.join(T, 'hardlink-register.yml')
HL_OLD = os.path.join(T, 'hardlink-register.previous')
open(HL_OUT, 'w').write('previous register content\n')
os.link(HL_OUT, HL_OLD)
rc, msg, _ = generate(PENDING, out_path=HL_OUT)
check('atomic: a successful run replaces the file instead of rewriting it in place (old hard link keeps the old content)',
      rc == 0 and open(HL_OLD).read() == 'previous register content\n' and open(HL_OUT).read().startswith('# M2 acceptance-criteria traceability'), msg[-200:])
rc, msg, _ = generate(PENDING, out_path=SENT)
check('atomic: a successful run replaces the output', rc == 0 and open(SENT).read().startswith('# M2 acceptance-criteria traceability'))
check('atomic: a successful run leaves no temporary file and sets mode 0644', not glob.glob(os.path.join(T, '.traceability-*')) and stat.S_IMODE(os.stat(SENT).st_mode) == 0o644)

# ---- Any failure (not only a bad decisions file) says the output is not current and leaves the old register alone.
BAD_JUNIT = os.path.join(T, 'junit-broken.xml')
open(BAD_JUNIT, 'w').write('<testsuites><testsuite>')
rc, msg, _ = generate(PENDING, out_path=SENT, junit=BAD_JUNIT)
check('unparseable junit: fails, says the output is not current, and leaves the existing register untouched',
      rc != 0 and 'must NOT be treated as current' in msg and open(SENT).read().startswith('# M2 acceptance-criteria traceability'), msg[-200:])
rc, msg, _ = generate(PENDING, out_path=SENT, junit=os.path.join(T, 'no-such-junit.xml'))
check('missing junit: fails and says the output is not current', rc != 0 and 'must NOT be treated as current' in msg, msg[-200:])
r = subprocess.run([sys.executable, '-I', GEN], capture_output=True, text=True, cwd=REPO)
check('usage error: fails and says the output is not current', r.returncode != 0 and 'must NOT be treated as current' in r.stderr)

# ---- Generation timestamp: UTC, with seconds, so a successful run is distinguishable from a stale file.
STAMP = re.compile(r'^# Generated: (\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})Z$')
t0 = datetime.datetime.now(datetime.timezone.utc).replace(microsecond=0)
rc, msg, out = generate(PENDING)
t1 = datetime.datetime.now(datetime.timezone.utc)
m = STAMP.match(open(out).read().split('\n')[1]) if rc == 0 else None
stamp = datetime.datetime.strptime(m.group(1), '%Y-%m-%dT%H:%M:%S').replace(tzinfo=datetime.timezone.utc) if m else None
check('timestamp: the Generated line is a UTC ISO-8601 time with seconds', m is not None, msg)
check('timestamp: it lies within the time the generator ran', stamp is not None and t0 <= stamp <= t1, f'{stamp} not in [{t0}, {t1}]')
check('timestamp: the committed register carries a timestamp with time precision', STAMP.match(open(REG).read().split('\n')[1]) is not None, open(REG).read().split('\n')[1])

# ---- PyYAML minimum version: below 6.0.1 is refused, 6.0.1 and above is accepted (a stand-in module reports the version).
def with_yaml_version(version, *args):
    d = os.path.join(T, 'stub-' + re.sub(r'\W', '_', version))
    os.makedirs(d, exist_ok=True)
    open(os.path.join(d, 'yaml.py'), 'w').write(f'__version__ = {version!r}\n')
    code = f'import runpy, sys; sys.path.insert(0, {d!r}); sys.argv = [{GEN!r}] + {list(args)!r}; runpy.run_path({GEN!r}, run_name="__main__")'
    r = subprocess.run([sys.executable, '-I', '-c', code], capture_output=True, text=True, cwd=REPO)
    return r.returncode, r.stdout + r.stderr
for v in ('5.4.1', '6.0', '6.0.0', 'not-a-version',
          # every pre-release is refused, including a pre-release of a LATER version
          '6.0.1.dev0', '6.0.1rc1', '6.0.1a1', '6.0.1b2', '6.0.2.dev0', '6.0.2rc1', '6.0.3a1', '6.1.0b1', '7.0.dev1', '7.0rc1',
          '6.0.2.post1.dev0', '6.0.2.junk'):
    rc, msg = with_yaml_version(v, '--check-dependencies')
    check(f'PyYAML {v} is refused (a stable release >= 6.0.1 is required)', rc != 0 and 'requires a stable PyYAML release >= 6.0.1' in msg and f'found {v}' in msg and 'Traceback' not in msg and 'must NOT be treated as current' in msg, msg[-200:])
for v in ('6.0.1', '6.0.1.post1', '6.0.1+ubuntu1', '6.0.2', '6.0.3', '6.1.0', '7.0'):
    rc, msg = with_yaml_version(v, '--check-dependencies')
    check(f'PyYAML {v} is accepted', rc == 0 and f'PyYAML {v} (minimum 6.0.1): ok' in msg, msg[-200:])
r = subprocess.run([sys.executable, '-I', GEN, '--check-dependencies'], capture_output=True, text=True, cwd=REPO)
check('--check-dependencies prints the installed version and the minimum', r.returncode == 0 and re.fullmatch(r'PyYAML \S+ \(minimum 6\.0\.1\): ok\n', r.stdout) is not None, r.stdout + r.stderr)

# ---- The generator never modifies the owner's decisions file, and test results cannot create an acceptance.
def sha(path):
    return hashlib.sha256(open(path, 'rb').read()).hexdigest()
d_before = sha(DEC)
rc, msg, _ = generate(decisions_path=DEC)
check('the generator leaves the owner decisions file unchanged', rc == 0 and sha(DEC) == d_before, msg)
failing = os.path.join(T, 'junit-failing.xml')
open(failing, 'w').write('<testsuites><testsuite><testcase name="test_C21_a"><failure/></testcase></testsuite></testsuites>')
rc, msg, out = generate(PENDING, junit=failing)
check('a failing or passing test run cannot create an acceptance (all pending stays unaccepted)', rc == 0 and crit21(out)['accepted'] is False, msg)

# ---- PyYAML guard: without PyYAML the generator stops with an instruction, not a traceback.
probe = subprocess.run([sys.executable, '-I', '-S', '-c', 'import yaml'], capture_output=True)
if probe.returncode != 0:
    rc, msg, out = generate(PENDING, python_args=('-I', '-S'))
    check('missing PyYAML: controlled message naming the dependency', rc != 0 and 'needs a stable PyYAML release >= 6.0.1' in msg and 'Traceback' not in msg and 'must NOT be treated as current' in msg and not os.path.exists(out), msg)
else:
    print('skip missing-PyYAML guard check (PyYAML is importable without site-packages here)')

# ---- The committed register agrees with the committed decisions file.
dec = yaml.safe_load(open(DEC))
status = {d['id']: d['status'] for d in dec['decisions']}
reg21 = next(c for c in committed['criteria'] if c['id'] == 21)
check('committed register: sub-criteria statuses equal the committed decisions file',
      {s['id']: s['status'] for s in reg21['subcriteria_acceptance']} == {sid: status.get(sid, 'pending') for sid in ('C21-021', 'C21-022', 'C21-M01')})
want_accepted = all(status.get(sid) == 'accepted' for sid in ('C21-021', 'C21-022', 'C21-M01'))
check('committed register: criterion 21 accepted flag equals what the decisions imply', reg21['accepted'] is want_accepted)
check('committed register: accepted_count equals the number of accepted criteria', committed['summary']['accepted_count'] == sum(c['accepted'] for c in committed['criteria']))
DEC_DAYS = {d['id']: str(d['accepted_on']) for d in dec['decisions'] if d['status'] == 'accepted'}
check('committed register: the summary wording matches the committed decisions for C21-021 and C21-022',
      pin_states(pin_line(REG)) == {sid: DEC_DAYS.get(sid, 'pending') for sid in ('C21-021', 'C21-022')}, pin_line(REG))
check('committed register: no criterion other than 21 is accepted', all(c['accepted'] is False for c in committed['criteria'] if c['id'] != 21))

# ---- Last-resort guards: an unexpected internal error is a controlled failure, never a traceback and never silent.
# The generator source is run with one internal step replaced by code that raises something unexpected.
def run_injected(inject, out_path):
    src = open(GEN).read()
    marker = 'DEC = load_decisions('
    assert src.count(marker) == 1
    patched = src.replace(marker, inject + '\n' + marker, 1)
    argv = [GEN, JUNIT, *FLAGS, '--decisions', DEC, '--out', out_path]
    code = (f'import sys; sys.argv = {argv!r}; '
            f'exec(compile({patched!r}, {GEN!r}, "exec"), {{"__name__": "__main__", "__file__": {GEN!r}}})')
    r = subprocess.run([sys.executable, '-I', '-c', code], capture_output=True, text=True, cwd=REPO)
    return r.returncode, r.stdout + r.stderr
for label, inject, needle in (
        ('validation step', "def _validate_decisions(raw, today, fail):\n    raise ZeroDivisionError('injected')", 'unexpected problem while validating (ZeroDivisionError: injected)'),
        ('reading step', "yaml.load = lambda *args, **kw: (_ for _ in ()).throw(KeyError('injected'))", 'unexpected problem while reading')):
    keep = os.path.join(T, 'guard-existing.yml')
    open(keep, 'w').write('previous register content\n')
    rc, msg = run_injected(inject, keep)
    check(f'last-resort guard ({label}): controlled diagnostic, exit status 1, not-current notice, no traceback, register untouched',
          rc == 1 and needle in msg and 'owner-acceptance-decisions:' in msg and 'rejected' in msg and 'must NOT be treated as current' in msg
          and 'Traceback' not in msg and open(keep).read() == 'previous register content\n' and not glob.glob(os.path.join(T, '.traceability-*')),
          f'rc={rc} msg={msg.strip()[-300:]}')

# ---- Temporary-directory cleanup. Each scenario runs a truncated copy of this very script (everything before the baseline
# section, then a scenario-specific ending) with TMPDIR pointing at an empty directory, and requires that directory to be
# empty afterwards.
SRC = open(os.path.abspath(__file__)).read()
MARK = '# ---- ' + 'Baseline: every decision pending.'  # built in two parts so that this line does not match itself
assert SRC.count(MARK) == 1


def run_copy(tail, signal_after_ready=None):
    case = tempfile.mkdtemp(prefix='cleanup-case-', dir=T)
    tmp = os.path.join(case, 'tmp')
    os.mkdir(tmp)
    os.makedirs(os.path.join(case, 'tests-cli'))
    os.symlink(os.path.join(REPO, 'scripts'), os.path.join(case, 'scripts'))
    os.symlink(os.path.join(REPO, 'documentation'), os.path.join(case, 'documentation'))
    copy = os.path.join(case, 'tests-cli', 'acceptance-decisions-test.py')
    open(copy, 'w').write(SRC.split(MARK)[0] + tail)
    proc = subprocess.Popen([sys.executable, '-I', copy], stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True,
                            env=dict(os.environ, TMPDIR=tmp))
    if signal_after_ready is not None:
        for line in proc.stdout:
            if line.strip() == 'ready':
                break
        proc.send_signal(signal_after_ready)
    try:
        out = proc.communicate(timeout=60)[0]
    except subprocess.TimeoutExpired:  # a hung scenario is a failed check, not a crash of this script
        proc.kill()
        out = 'TIMEOUT: ' + (proc.communicate()[0] or '')
        return 'timeout', out, sorted(os.listdir(tmp))
    return proc.returncode, out, sorted(os.listdir(tmp))


LOCKED = ("d = os.path.join(T, 'locked')\nos.makedirs(os.path.join(d, 'inner'))\nopen(os.path.join(d, 'inner', 'f'), 'w').write('x')\n"
          "os.chmod(os.path.join(d, 'inner'), 0o555)\nos.chmod(d, 0o555)\n")
for label, tail, want_rc, sig in (
        ('the tests pass', "sys.exit(0)\n", 0, None),
        ('a check fails', "check('forced failure', False, 'forced')\nsys.exit(0 if fail_n == 0 else 1)\n", 1, None),
        ('an exception is raised', "raise RuntimeError('boom')\n", 1, None),
        ('an exception is raised with read-only directories left behind', LOCKED + "raise RuntimeError('boom')\n", 1, None),
        ('the script is terminated (SIGTERM)', "print('ready', flush=True)\nimport time\ntime.sleep(60)\n", 143, signal.SIGTERM),
        ('the script is interrupted (SIGINT)', "signal.signal(signal.SIGINT, signal.default_int_handler)  # a parent may have left SIGINT ignored\nprint('ready', flush=True)\nimport time\ntime.sleep(60)\n", -signal.SIGINT, signal.SIGINT)):  # Python re-raises SIGINT, so the exit status is -2
    rc, out, left = run_copy(tail, sig)
    check(f'cleanup: the temporary directory is removed when {label}', left == [] and rc == want_rc, f'rc={rc} (want {want_rc}) leftover={left} out={out.strip()[-200:]}')

print(f'{pass_n} passed, {fail_n} failed')
sys.exit(0 if fail_n == 0 else 1)

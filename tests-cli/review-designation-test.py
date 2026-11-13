#!/usr/bin/env python3
"""Regression tests for the reviewer-designation input of scripts/build-traceability.py.

The generator reads documentation/review-designation.yml and counts the records in documentation/acceptance-records/.
These tests prove it fails closed on bad input (no register is written), and that a COMPLETED record is counted but never
changes accepted_count: a record is evidence, not acceptance. They use scratch copies and synthetic data only.

Run: python3 -I tests-cli/review-designation-test.py
"""
import datetime
import os
import re
import shutil
import subprocess
import sys
import tempfile

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SCRIPT = os.path.join(ROOT, 'scripts', 'build-traceability.py')
passed = failed = 0


def ok(cond, name, extra=''):
    global passed, failed
    if cond:
        passed += 1
        print('ok  ', name)
    else:
        failed += 1
        print('FAIL', name, extra)


JUNIT = '<?xml version="1.0"?><testsuites><testsuite name="s" tests="2"><testcase name="test_C01_a"/><testcase name="test_C02_a"/></testsuite></testsuites>'
VALID = open(os.path.join(ROOT, 'documentation', 'review-designation.yml'), encoding='utf-8').read()


def run(designation_text=None, mutate_records=None, drop_records=(), extra_files=()):
    """Run the generator in a scratch tree; return (exit status, stderr, register text or None)."""
    with tempfile.TemporaryDirectory(prefix='rdt-') as tmp:
        rec = os.path.join(tmp, 'records')
        shutil.copytree(os.path.join(ROOT, 'documentation', 'acceptance-records'), rec)
        for rid in drop_records:
            os.unlink(os.path.join(rec, rid + '.md'))
        for name in extra_files:
            open(os.path.join(rec, name), 'w').write('# extra\n')
        if mutate_records:
            for rid, fn in mutate_records.items():
                path = os.path.join(rec, rid + '.md')
                with open(path, encoding='utf-8') as fh:
                    before = fh.read()
                with open(path, 'w', encoding='utf-8') as fh:
                    fh.write(fn(before))
        des = os.path.join(tmp, 'designation.yml')
        if designation_text is not None:
            open(des, 'w', encoding='utf-8').write(designation_text)
        junit = os.path.join(tmp, 'junit.xml')
        open(junit, 'w').write(JUNIT)
        out = os.path.join(tmp, 'register.yml')
        r = subprocess.run([sys.executable, '-I', SCRIPT, junit, '--out', out, '--designation', des, '--records', rec],
                           cwd=ROOT, capture_output=True, text=True, timeout=120)
        text = open(out, encoding='utf-8').read() if os.path.exists(out) else None
        return r.returncode, r.stderr, text


def fails(name, **kw):
    rc, err, text = run(**kw)
    ok(rc != 0 and text is None and 'review-designation:' in err and 'Traceback' not in err and 'No register was written' in err, name, f'rc={rc} err={err.strip()[:160]}')


# ---- Valid input.
rc, err, text = run(designation_text=VALID)
ok(rc == 0 and text is not None, 'the real designation and records produce a register', err.strip()[:160])
if text:
    ok('Reviewer: Nominated independent reviewer, designated by the owner' in text and 'Sultan' not in text, 'the register names the reviewer by role only')
    ok('Independence: NOT VERIFIED' in text, 'the register says independence is NOT VERIFIED')
    ok('completed: 0; pending: 17' in text, 'the register reports 0 completed and 17 pending records')
    ok(re.search(r'^  accepted_count: 0$', text, re.M) is not None, 'accepted_count is 0')
    ok(len(re.findall(r'^    accepted: false$', text, re.M)) == 34, 'all 34 criteria are accepted: false')

# ---- A completed record is counted but accepts nothing.
done = lambda t: t.replace('| Decision | PENDING |', '| Decision | ACCEPTED |')
rc, err, text = run(designation_text=VALID, mutate_records={'C01-M01': done})
ok(rc == 0 and text is not None and 'completed: 1; pending: 16' in text, 'one completed record is counted (completed: 1; pending: 16)', err.strip()[:120])
ok(text is not None and re.search(r'^  accepted_count: 0$', text, re.M) is not None and len(re.findall(r'^    accepted: false$', text, re.M)) == 34, 'a completed record does not change accepted_count or any criterion')
rc, err, text = run(designation_text=VALID, mutate_records={rid: done for rid in ['C01-M01', 'C02-M01', 'C03-M01', 'C06-M01', 'C10-M01', 'C12-M01', 'C13-M01', 'C14-M01', 'C15-M01', 'C17-M01', 'C19-M01', 'C20-M01', 'C20-M02', 'C25-M01', 'C27-M01', 'C31-M01', 'C34-M01']})
ok(text is not None and 'completed: 17; pending: 0' in text and re.search(r'^  accepted_count: 0$', text, re.M) is not None, 'even with all 17 records completed, accepted_count stays 0')

# ---- Verified independence only when every check is verified.
all_verified = re.sub(r'state: \w+', 'state: verified', VALID).replace('status: not_verified', 'status: verified')
rc, err, text = run(designation_text=all_verified)
ok(rc == 0 and text is not None and 'Independence: VERIFIED.' in text, 'independence can be recorded as verified only when every check is verified (synthetic)')
fails('independence "verified" with open checks is refused', designation_text=VALID.replace('status: not_verified', 'status: verified'))

# ---- Bad designation input fails closed.
fails('a missing designation file is refused', designation_text=None)
fails('an empty file is refused', designation_text='')
fails('a list at the top level is refused', designation_text='- a\n- b\n')
fails('an unparseable file is refused', designation_text='a: [unclosed\n')
fails('a wrong schema version is refused', designation_text=VALID.replace('schema_version: 1', 'schema_version: 2'))
fails('an empty reviewer role label is refused', designation_text=VALID.replace('role_label: "Nominated independent reviewer"', 'role_label: ""'))
fails('a personal name recorded in the public designation is refused', designation_text=VALID.replace('  role_label:', '  name: "X"\n  role_label:'))
fails('identity not held privately is refused', designation_text=VALID.replace('identity_held_privately: true', 'identity_held_privately: false'))
fails('a designation not made by the owner is refused', designation_text=VALID.replace('designated_by: owner', 'designated_by: implementer'))
fails('a future instruction date is refused', designation_text=VALID.replace('"2026-11-13"', '"2099-01-01"'))
fails('a malformed instruction date is refused', designation_text=VALID.replace('"2026-11-13"', '"yesterday"'))
fails('an impossible date is refused', designation_text=VALID.replace('"2026-11-13"', '"2026-02-31"'))
fails('recorded contact details are refused', designation_text=VALID.replace('contact_details_recorded: false', 'contact_details_recorded: true'))
fails('a scope that does not add up to 17 is refused', designation_text=VALID.replace('reviewer_run_records: 14', 'reviewer_run_records: 13'))
fails('an unknown independence status is refused', designation_text=VALID.replace('status: not_verified', 'status: probably_fine'))
fails('a duplicate key is refused', designation_text=VALID.replace('schema_version: 1', 'schema_version: 1\nschema_version: 1', 1))
fails('a duplicate check id is refused', designation_text=VALID.replace('id: I2', 'id: I1', 1))
fails('a check with a missing field is refused', designation_text=VALID.replace('      note: "The reviewer\'s GitHub login has not been supplied."\n', ''))

# ---- Bad records fail closed.
fails('a missing record is refused', designation_text=VALID, drop_records=['C19-M01'])
fails('an extra record file is refused', designation_text=VALID, extra_files=['C17-S01.md'])
fails('a record with an invalid decision is refused', designation_text=VALID, mutate_records={'C02-M01': lambda t: t.replace('| Decision | PENDING |', '| Decision | PROBABLY FINE |')})
fails('a record with no decision row is refused', designation_text=VALID, mutate_records={'C03-M01': lambda t: t.replace('| Decision | PENDING |', '')})

# ---- Nothing in the real files claims more than the evidence supports.
real = open(os.path.join(ROOT, 'documentation', 'acceptance-criteria-traceability.yml'), encoding='utf-8').read()
ok('manual_review:' in real and 'NOT VERIFIED' in real, 'the committed register carries the reviewer designation and the independence state')
ok(re.search(r'^  accepted_count: 0$', real, re.M) is not None, 'the committed register has accepted_count 0')

print(f'\n{passed} passed, {failed} failed')
sys.exit(1 if failed else 0)

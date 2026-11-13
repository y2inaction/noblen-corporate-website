#!/usr/bin/env python3
"""Compare the generated register and the 17 manual records with the two frozen plans.

Read-only. Nothing is written and no plan is touched. It fails if the register, the records or the plans drift apart in a way
that is not already a documented, named discrepancy. The one documented discrepancy is C27-M01 (see
documentation/C27-M01-PROVENANCE.md): it exists in the register and as a record but in neither frozen plan.

Run: python3 -I tests-cli/plan-register-consistency-test.py
"""
import os
import re
import sys

import yaml

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
D = os.path.join(ROOT, 'documentation')
passed = failed = 0


def ok(cond, name, extra=''):
    global passed, failed
    if cond:
        passed += 1
        print('ok  ', name)
    else:
        failed += 1
        print('FAIL', name, extra)


def read(p):
    with open(p, encoding='utf-8') as fh:
        return fh.read()


m2 = read(os.path.join(D, 'M2-IMPLEMENTATION-PLAN.md'))
ci = read(os.path.join(D, 'CI-ACCEPTANCE-TEST-PLAN.md'))
reg = yaml.safe_load(read(os.path.join(D, 'acceptance-criteria-traceability.yml')))

# M2 plan, section 10: criterion number -> type (A, M, A + M, M + A) and title.
m2_rows = {}
for line in m2.splitlines():
    m = re.match(r'^\| (\d{1,2}) \| (.+?) \| (A|M|A \+ M|M \+ A|M \(\+ A partial\)|A \(owner-run\) \+ M) \|', line)
    if m and 1 <= int(m.group(1)) <= 34 and int(m.group(1)) not in m2_rows:
        m2_rows[int(m.group(1))] = (re.sub(r'\*', '', m.group(2)).strip(), m.group(3))
ok(len(m2_rows) == 34, 'the M2 plan lists 34 criteria with a type', str(len(m2_rows)))

# CI plan: manual ids by criterion row.
ci_manual = {}
for line in ci.splitlines():
    m = re.match(r'^\| (\d{1,2}) \| ', line)
    if not m:
        continue
    n = int(m.group(1))
    for mm in re.finditer(r'\b(C\d\d-M\d\d)\b', line):
        ci_manual.setdefault(n, set()).add(mm.group(1))
ci_ids = set().union(*ci_manual.values())
ok(len(ci_ids) == 17, 'the CI plan defines 17 manual (-M) ids, C21-M01 included', str(sorted(ci_ids)))

records = sorted(f[:-3] for f in os.listdir(os.path.join(D, 'acceptance-records')) if f.endswith('.md') and f != 'README.md')
ok(len(records) == 17, '17 record files exist')

# The records are the plan's ids, minus C21-M01 (an owner decision in owner-acceptance-decisions.yml), plus C27-M01.
expected_records = (ci_ids - {'C21-M01'}) | {'C27-M01'}
ok(set(records) == expected_records, 'records = plan manual ids - C21-M01 + C27-M01 (the one documented addition)', str(set(records) ^ expected_records))
ok('C27-M01' not in ci and 'C27-M01' not in m2, 'C27-M01 is in neither frozen plan')

# Type consistency: a criterion needs a manual id exactly when the M2 plan says M is part of its type.
for n, (title, typ) in sorted(m2_rows.items()):
    has_plan_manual = n in ci_manual
    ok(('M' in typ) == has_plan_manual, f'criterion {n}: M2 type "{typ}" agrees with the CI plan ({sorted(ci_manual.get(n, []))})')

# Register: titles and manual ids.
by_id = {c['id']: c for c in reg['criteria']}
ok(sorted(by_id) == list(range(1, 35)), 'the register has criteria 1 to 34')
for n, (title, typ) in sorted(m2_rows.items()):
    c = by_id.get(n)
    ok(c is not None and c['title'].lower().rstrip('.') == title.lower().rstrip('.'), f'criterion {n}: register title matches the M2 plan', f"{c['title'] if c else None!r} vs {title!r}")
    pend = (c or {}).get('manual_records_pending', 'none')
    mentioned = set(re.findall(r'C\d\d-M\d\d', pend))
    planned = ci_manual.get(n, set())
    extra = mentioned - planned
    missing = planned - mentioned
    ok(not missing, f'criterion {n}: register names every manual id the CI plan defines', str(missing))
    ok(extra <= {'C27-M01'}, f'criterion {n}: register names no manual id the plan lacks, except the documented C27-M01', str(extra))
    if 'C27-M01' in mentioned:
        ok('NOT in the frozen plans' in pend, 'the register marks C27-M01 as outside the frozen plans')

ok(reg['summary']['accepted_count'] == 0 and not any(c.get('accepted') for c in reg['criteria']), 'nothing is accepted')
print(f'\n{passed} passed, {failed} failed')
sys.exit(1 if failed else 0)

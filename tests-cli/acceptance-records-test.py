#!/usr/bin/env python3
"""Fail-closed checks on the manual acceptance records and the reviewer designation.

What it enforces (it never creates or completes a record):
  * exactly the 17 manual records exist (the `-M` items of the frozen plan; C17-S01 is a CI status item, not a record),
    each with the sections and fields the template defines;
  * each record's executor matches the frozen CI acceptance plan (reviewer or owner);
  * a PENDING record has EVERY fill-in field still at the placeholder, no declaration text and no signature;
  * a record that claims ACCEPTED or NOT ACCEPTED has every field genuinely filled in (date, performer, a 40-hex commit,
    environment, evidence reference, signature reference, findings) and a private declaration reference, so a half-completed or
    inferred record is rejected;
  * the index agrees with the records, and no record claims an acceptance the index does not show;
  * the designation file is valid and names the same reviewer everywhere, and its independence status is not "verified"
    unless every independence check is in a verified state.

Run: python3 -I tests-cli/acceptance-records-test.py [--root DIR]    (DIR defaults to the repository root)
"""
import argparse
import os
import re
import sys

PLACEHOLDER = '[not yet completed]'
EXPECTED = ['C01-M01', 'C02-M01', 'C03-M01', 'C06-M01', 'C10-M01', 'C12-M01', 'C13-M01', 'C14-M01', 'C15-M01', 'C17-M01',
            'C19-M01', 'C20-M01', 'C20-M02', 'C25-M01', 'C27-M01', 'C31-M01', 'C34-M01']
SECTIONS = ['## 1. Purpose', '## 2. Prerequisites', '## 3. Procedure and expected results', '## 4. Overall expected result',
            '## 5. Evidence to keep', '## 6. Who performs and reviews', '## 7. Independence and qualification (held privately)',
            '## 8. Findings', '## 9. Decision']
DECISIONS = {'PENDING', 'ACCEPTED', 'NOT ACCEPTED'}
passed = failed = 0


def ok(cond, name, extra=''):
    global passed, failed
    if cond:
        passed += 1
        print('ok  ', name)
    else:
        failed += 1
        print('FAIL', name, extra)


def read(path):
    with open(path, encoding='utf-8') as fh:
        return fh.read()


def field(text, label):
    m = re.search(r'^\| ' + re.escape(label) + r' \| (.*) \|$', text, re.M)
    return m.group(1).strip() if m else None


def plan_executor(plan, rid):
    """The executor the frozen plan gives a manual record: reviewer or owner (None when it cannot be found)."""
    if rid in ('C31-M01', 'C34-M01'):
        return 'owner' if re.search(r'\| ' + rid.split('-')[0] + r'-M01, C34-M01 \| manual \| owner \|', plan) or re.search(r'C31-M01, C34-M01 \| manual \| owner', plan) else None
    m = re.search(re.escape(rid) + r'[^{\n]*\{(\w+)\\?\|(?:MAN|OWN)\\?\|E7\}', plan)
    if not m:
        return None
    return {'reviewer': 'reviewer', 'owner': 'owner'}.get(m.group(1))


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--root', default=os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
    root = ap.parse_args().root
    docs = os.path.join(root, 'documentation')
    rdir = os.path.join(docs, 'acceptance-records')
    plan = read(os.path.join(docs, 'CI-ACCEPTANCE-TEST-PLAN.md'))

    files = sorted(f for f in os.listdir(rdir) if f.endswith('.md') and f != 'README.md')
    ok(files == [r + '.md' for r in EXPECTED], 'exactly the 17 manual records exist, and no C17-S01 record', str(files))
    ok(not os.path.exists(os.path.join(rdir, 'C17-S01.md')), 'C17-S01 (a CI status item) is not a record')

    designation = read(os.path.join(docs, 'review-designation.yml'))
    name_m = re.search(r'^\s+role_label:\s*"([^"]+)"', designation, re.M)
    designated = name_m.group(1) if name_m else None
    ok(bool(designated), 'the designation file gives the reviewer a role label')
    ok('declarations_held_privately: true' in designation and 'separate_reviewer: false' in designation and 'may_sign_or_declare_for_reviewer: false' in designation, 'declarations are private and technical inspection support is not a separate reviewer and cannot sign')
    ok('designated_by: owner' in designation, 'the designation says it was made by the owner')
    ok(re.search(r'instruction_recorded:\s*"\d{4}-\d{2}-\d{2}"', designation) is not None, 'the designation records the date the instruction was received')
    state_lines = re.findall(r'state:\s*(\w+)', designation)
    status = re.search(r'^independence:\s*\n\s+status:\s*(\w+)', designation, re.M)
    ok(status is not None, 'the designation has an independence status')
    if status and status.group(1) == 'verified':
        ok(all(s == 'verified' for s in state_lines), 'independence is only "verified" when every check is verified', str(state_lines))
    else:
        ok(status is not None and status.group(1) == 'not_verified', 'independence is recorded as not verified while any check is open', status.group(1) if status else '')
    ok('contact_details_recorded: false' in designation, 'no contact details are recorded for the reviewer')

    index = read(os.path.join(rdir, 'README.md'))
    ok(designated is not None and designated in index, 'the index names the reviewer role')
    ok(designated is not None and designated in read(os.path.join(docs, 'REVIEW-PLAN.md')), 'the review plan names the reviewer role')

    completed = []
    for rid in EXPECTED:
        path = os.path.join(rdir, rid + '.md')
        if not os.path.isfile(path):
            ok(False, f'{rid}: file exists')
            continue
        t = read(path)
        ok(t.startswith(f'# {rid}:'), f'{rid}: title starts with the record ID')
        ok(field(t, 'Record ID') == rid, f'{rid}: Record ID field matches the file')
        ex = field(t, 'Executor (frozen plan)')
        pe = plan_executor(plan, rid)
        if rid == 'C27-M01':
            # Not in the frozen plan (criterion 27 is automated only there): it must say so, and the register must list it.
            ok(pe is None and 'Not defined in the frozen' in (field(t, 'Source') or ''), 'C27-M01: states that it is not defined in the frozen plan', str(field(t, 'Source')))
            reg = read(os.path.join(docs, 'acceptance-criteria-traceability.yml'))
            ok('manual_records_pending: "C27-M01 (supplementary, NOT in the frozen plans' in reg, 'C27-M01: the implementation register lists it as pending')
        else:
            ok(pe is not None and ex == pe, f'{rid}: executor "{ex}" matches the frozen plan ("{pe}")')
        ok(all(s in t for s in SECTIONS), f'{rid}: has every template section')
        ok(designated is not None and designated in t, f'{rid}: names the reviewer role')
        dec = field(t, 'Decision')
        status_field = field(t, 'Status')
        ok(dec in DECISIONS, f'{rid}: decision is one of the allowed values', str(dec))
        ok(status_field == dec, f'{rid}: Status ({status_field}) agrees with Decision ({dec})')
        proc = [l for l in t.splitlines() if re.match(r'^\| \d+ \|', l)]
        ok(len(proc) >= 4, f'{rid}: has a numbered procedure with expected results ({len(proc)} steps)')
        results = [[c.strip() for c in re.split(r'(?<!\\)\|', l)[1:-1]] for l in proc]
        ok(all(len(c) == 5 and c[2] != '' for c in results), f'{rid}: every step has an action and an expected result')
        sec7 = t.split('## 7.', 1)[1].split('## 8.', 1)[0] if '## 7.' in t else ''
        ok(not re.search(r'\[( |x|X)\]', sec7) and 'I hold no' not in sec7 and 'qualification' in sec7.lower() and 'not** recorded in this public repository' in sec7,
           f'{rid}: personal declarations and qualifications are not recorded in the public file (private reference only)')
        ok(field(t, 'Independence state (from review-designation.yml)') == 'NOT VERIFIED', f'{rid}: independence is shown as NOT VERIFIED')
        fill_labels = ['Date and time performed (UTC)', 'Commit checked (40-character SHA)', 'Environment (address, WordPress, PHP, browser, devices)',
                       'Evidence reference', 'Decision date (UTC)', 'Signature or signature reference',
                       'Private declaration reference (identifier only; no personal details)']
        if ex == 'owner':
            fill_labels += ['Performed by (the owner, as the frozen plan requires)', 'Independent verification performed by the designated reviewer', 'Independent verification outcome']
        else:
            fill_labels += ['Performed by (name and role)']
        vals = {lab: field(t, lab) for lab in fill_labels}
        findings = t.split('## 8. Findings', 1)[1].split('## 9. Decision', 1)[0].strip() if '## 8. Findings' in t else ''
        if dec == 'PENDING':
            ok(all(v == PLACEHOLDER for v in vals.values()), f'{rid}: PENDING, every fill-in field is still the placeholder', str({k: v for k, v in vals.items() if v != PLACEHOLDER}))
            ok(all(c[3] == PLACEHOLDER and c[4] == PLACEHOLDER for c in results), f'{rid}: PENDING, no step has a recorded result or note')
            ok(findings == PLACEHOLDER, f'{rid}: PENDING, findings are empty')
        else:
            completed.append(rid)
            required = [k for k in fill_labels if not (ex == 'owner' and (k.startswith('Independent verification') or k.startswith('Private declaration')))]
            ok(all(vals[k] not in (None, '', PLACEHOLDER) for k in required), f'{rid}: a completed record has every required field filled in', str([k for k in required if vals[k] in (None, '', PLACEHOLDER)]))
            ok(bool(re.fullmatch(r'[0-9a-f]{40}', vals['Commit checked (40-character SHA)'] or '')), f'{rid}: a completed record names a 40-hex commit')
            ok(bool(re.match(r'\d{4}-\d{2}-\d{2}', vals['Date and time performed (UTC)'] or '')) and bool(re.match(r'\d{4}-\d{2}-\d{2}', vals['Decision date (UTC)'] or '')), f'{rid}: a completed record has real dates')
            ok(all(c[3] in ('PASS', 'FAIL') for c in results), f'{rid}: a completed record has PASS or FAIL for every step')
            ok(findings not in ('', PLACEHOLDER), f'{rid}: a completed record has findings (write "none" if there are none)')
            if dec == 'ACCEPTED':
                ok(all(c[3] == 'PASS' for c in results), f'{rid}: ACCEPTED requires every step to PASS')

    # The index must agree with the records.
    for rid in EXPECTED:
        row = re.search(r'^\| \[' + re.escape(rid) + r'\]\(.*?\) \|.*\| (\w[\w ]*) \|$', index, re.M)
        t = read(os.path.join(rdir, rid + '.md')) if os.path.isfile(os.path.join(rdir, rid + '.md')) else ''
        ok(row is not None and row.group(1).strip() == field(t, 'Decision'), f'{rid}: the index status agrees with the record', row.group(1) if row else 'no row')
    m = re.search(r'Completed: (\d+)\.', index)
    ok(m is not None and int(m.group(1)) == len(completed), 'the index total of completed records matches the records', f'{m.group(1) if m else None} vs {len(completed)}')

    # No other document may claim these records are done.
    for doc in ('ACCEPTANCE-RECORDS-GUIDE.md', 'REVIEW-PLAN.md'):
        text = read(os.path.join(docs, doc))
        ok('PENDING' in text, f'{doc} states that records are pending')

    print(f'\n{passed} passed, {failed} failed')
    sys.exit(1 if failed else 0)


if __name__ == '__main__':
    main()

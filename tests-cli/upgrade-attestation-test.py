#!/usr/bin/env python3
"""Regression tests for scripts/upgrade-attestation.py: each of the four C26-006 failure conditions, using SYNTHETIC records only.

No real attestation, reviewer or run is involved. A pass here shows the checker's behaviour, not that criterion 26 is satisfied.
Run: python3 -I tests-cli/upgrade-attestation-test.py
"""
import copy
import hashlib
import json
import os
import shutil
import subprocess
import sys
import tempfile

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SCRIPT = os.path.join(ROOT, 'scripts', 'upgrade-attestation.py')
passed = failed = 0


def ok(cond, name, extra=''):
    global passed, failed
    if cond:
        passed += 1
        print('ok  ', name)
    else:
        failed += 1
        print('FAIL', name, extra)


def run(*args):
    r = subprocess.run([sys.executable, '-I', SCRIPT, *args], capture_output=True, text=True, timeout=60)
    return r.returncode, r.stdout, r.stderr


def sh(*cmd, cwd):
    return subprocess.run(cmd, cwd=cwd, capture_output=True, text=True, check=True).stdout.strip()


tmp = tempfile.mkdtemp(prefix='attest-')
try:
    repo = os.path.join(tmp, 'repo')
    os.mkdir(repo)
    sh('git', 'init', '-q', cwd=repo)
    sh('git', 'config', 'user.email', 'a@example.invalid', cwd=repo)
    sh('git', 'config', 'user.name', 'synthetic', cwd=repo)
    for i in range(2):
        open(os.path.join(repo, 'f'), 'w').write(str(i))
        sh('git', 'add', 'f', cwd=repo)
        sh('git', 'commit', '-q', '-m', f'c{i}', cwd=repo)
    old = sh('git', 'rev-parse', 'HEAD~1', cwd=repo)
    new = sh('git', 'rev-parse', 'HEAD', cwd=repo)
    sh('git', 'checkout', '-q', '-b', 'other', old, cwd=repo)
    open(os.path.join(repo, 'g'), 'w').write('x')
    sh('git', 'add', 'g', cwd=repo)
    sh('git', 'commit', '-q', '-m', 'diverge', cwd=repo)
    diverged = sh('git', 'rev-parse', 'HEAD', cwd=repo)

    manifest = {'schema_version': 1, 'id': 'synthetic-manifest'}
    base_att = {
        'schema_version': 1, 'manifest_id': 'synthetic-manifest', 'manifest_sha256': '', 'reviewed_commit': new,
        'author': {'role': 'implementer', 'id': 'synthetic-author'}, 'approver': {'role': 'independent reviewer', 'id': 'synthetic-reviewer'},
        'approved_at': '2030-01-01T10:00:00Z', 'approval_ref': 'synthetic-review-record-1',
        'source': {'kind': 'fixture', 'description': 'synthetic source', 'db_version': 1},
        'target': {'kind': 'fixture', 'description': 'synthetic target', 'db_version': 2},
    }
    base_bind = {
        'schema_version': 1, 'run_id': '123456', 'run_attempt': 1, 'run_started_at': '2030-01-01T10:05:00Z', 'checked_out_commit': new,
        'manifest_sha256_loaded': '', 'attestation_sha256': '', 'attestation_ref': 'synthetic-review-record-1',
    }
    case = [0]

    def build(att_edit=None, bind_edit=None, man_edit=None, repo_arg=True, write_att=True, write_bind=True, write_man=True, raw_att=None):
        case[0] += 1
        d = os.path.join(tmp, f'c{case[0]}')
        os.mkdir(d)
        man = copy.deepcopy(manifest)
        if man_edit:
            man_edit(man)
        man_bytes = (json.dumps(man) + '\n').encode()
        att = copy.deepcopy(base_att)
        att['manifest_sha256'] = hashlib.sha256((json.dumps(manifest) + '\n').encode()).hexdigest()
        if att_edit:
            att_edit(att)
        att_bytes = raw_att if raw_att is not None else (json.dumps(att) + '\n').encode()
        bind = copy.deepcopy(base_bind)
        bind['manifest_sha256_loaded'] = hashlib.sha256(man_bytes).hexdigest()
        bind['attestation_sha256'] = hashlib.sha256(att_bytes).hexdigest()
        if bind_edit:
            bind_edit(bind)
        paths = {k: os.path.join(d, k) for k in ('att', 'bind', 'man')}
        if write_att:
            open(paths['att'], 'wb').write(att_bytes)
        if write_bind:
            open(paths['bind'], 'w').write(json.dumps(bind))
        if write_man:
            open(paths['man'], 'wb').write(man_bytes)
        cmd = ['check', '--attestation', paths['att'], '--binding', paths['bind'], '--manifest', paths['man']]
        if repo_arg:
            cmd += ['--repo', repo]
        return run(*cmd)

    def expect(name, result, code):
        rc, out, err = result
        if code == 'OK':
            ok(rc == 0 and out.startswith('OK'), name, err.strip()[:120])
        else:
            ok(rc == 1 and err.startswith(code + ' ') and 'C26-006 is not satisfied' in err and 'Traceback' not in err, name, f'rc={rc} err={err.strip()[:120]}')

    expect('a valid attestation approved before the run passes', build(), 'OK')
    expect('the reviewed commit may be an ancestor of the checked-out commit', build(att_edit=lambda a: a.update(reviewed_commit=old)), 'OK')

    # MISSING
    expect('MISSING: no attestation file', build(write_att=False), 'MISSING')
    expect('MISSING: empty attestation file', build(raw_att=b''), 'MISSING')
    expect('MISSING: no runtime binding', build(write_bind=False), 'MISSING')
    expect('MISSING: no manifest', build(write_man=False), 'MISSING')

    # INVALID
    expect('INVALID: attestation is not JSON', build(raw_att=b'not json'), 'INVALID')
    expect('INVALID: attestation is a list', build(raw_att=b'[1]'), 'INVALID')
    for key in ('manifest_id', 'manifest_sha256', 'reviewed_commit', 'author', 'approver', 'approved_at', 'approval_ref', 'source', 'target', 'schema_version'):
        expect(f'INVALID: attestation without {key}', build(att_edit=lambda a, k=key: a.pop(k)), 'INVALID')
    expect('INVALID: manifest hash is not 64 hex characters', build(att_edit=lambda a: a.update(manifest_sha256='abc')), 'INVALID')
    expect('INVALID: approval time without Z', build(att_edit=lambda a: a.update(approved_at='2030-01-01T10:00:00')), 'INVALID')
    expect('INVALID: approval time is not a real date', build(att_edit=lambda a: a.update(approved_at='2030-02-31T10:00:00Z')), 'INVALID')
    expect('INVALID: the approver is the author', build(att_edit=lambda a: a['approver'].update(id='synthetic-author')), 'INVALID')
    expect('INVALID: the approver is the author (different letter case)', build(att_edit=lambda a: a['approver'].update(id='Synthetic-AUTHOR')), 'INVALID')
    expect('INVALID: empty approver role', build(att_edit=lambda a: a['approver'].update(role='  ')), 'INVALID')
    expect('INVALID: source version is a boolean', build(att_edit=lambda a: a['source'].update(db_version=True)), 'INVALID')
    expect('INVALID: run attempt is a boolean', build(bind_edit=lambda b: b.update(run_attempt=True)), 'INVALID')
    expect('INVALID: run id is not digits', build(bind_edit=lambda b: b.update(run_id='12ab')), 'INVALID')
    expect('INVALID: run start time malformed', build(bind_edit=lambda b: b.update(run_started_at='yesterday')), 'INVALID')

    # MISMATCHED
    expect('MISMATCHED: manifest changed after approval', build(man_edit=lambda m: m.update(extra=1)), 'MISMATCHED')
    expect('MISMATCHED: binding records another manifest hash', build(bind_edit=lambda b: b.update(manifest_sha256_loaded='0' * 64)), 'MISMATCHED')
    expect('MISMATCHED: binding records another attestation hash', build(bind_edit=lambda b: b.update(attestation_sha256='1' * 64)), 'MISMATCHED')
    expect('MISMATCHED: manifest id differs', build(att_edit=lambda a: a.update(manifest_id='another-manifest')), 'MISMATCHED')
    expect('MISMATCHED: reviewed commit is not an ancestor', build(att_edit=lambda a: a.update(reviewed_commit=diverged)), 'MISMATCHED')
    expect('MISMATCHED: a different commit without a repository to prove ancestry', build(att_edit=lambda a: a.update(reviewed_commit=old), repo_arg=False), 'MISMATCHED')

    # LATE
    expect('LATE: approval equals the run start', build(att_edit=lambda a: a.update(approved_at='2030-01-01T10:05:00Z')), 'LATE')
    expect('LATE: approval after the run start', build(att_edit=lambda a: a.update(approved_at='2030-01-01T10:06:00Z')), 'LATE')
    expect('approval one second before the run start passes', build(att_edit=lambda a: a.update(approved_at='2030-01-01T10:04:59Z')), 'OK')

    # bind round trip
    d = os.path.join(tmp, 'rt')
    os.mkdir(d)
    man_p, att_p, bind_p = (os.path.join(d, n) for n in ('m', 'a', 'b'))
    man_b = (json.dumps(manifest) + '\n').encode()
    open(man_p, 'wb').write(man_b)
    att = copy.deepcopy(base_att)
    att['manifest_sha256'] = hashlib.sha256(man_b).hexdigest()
    open(att_p, 'w').write(json.dumps(att))
    rc, out, err = run('bind', '--run-id', '99', '--run-attempt', '2', '--run-started-at', '2030-01-01T10:05:00Z', '--checked-out-commit', new,
                       '--manifest', man_p, '--attestation', att_p, '--attestation-ref', 'synthetic-review-record-1', '--out', bind_p)
    ok(rc == 0, 'bind writes a runtime binding', err)
    rc, out, err = run('check', '--attestation', att_p, '--binding', bind_p, '--manifest', man_p)
    ok(rc == 0 and out.startswith('OK'), 'a generated binding passes the checker', err)
    rc, out, err = run('bind', '--run-id', '99', '--run-attempt', '2', '--run-started-at', 'bad', '--checked-out-commit', new,
                       '--manifest', man_p, '--attestation', att_p, '--attestation-ref', 'x', '--out', bind_p + '2')
    ok(rc == 1 and err.startswith('INVALID') and not os.path.exists(bind_p + '2'), 'bind refuses a malformed start time and writes nothing')
    ok(not any('/home/' in s for s in (out, err)), 'messages carry no file paths')
finally:
    shutil.rmtree(tmp, ignore_errors=True)

print(f'\n{passed} passed, {failed} failed')
sys.exit(1 if failed else 0)

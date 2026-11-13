#!/usr/bin/env python3
"""C26-006 tooling: pre-run attestation and runtime binding for the upgrade comparison.

  upgrade-attestation.py bind  --run-id ID --run-attempt N --run-started-at UTC --checked-out-commit SHA
                               --manifest FILE --attestation FILE --attestation-ref TEXT --out FILE
  upgrade-attestation.py check --attestation FILE --binding FILE --manifest FILE [--repo DIR]

`bind` writes the runtime binding: the run, the platform-recorded start time, the checked-out commit and the SHA-256 of the
manifest and attestation files actually loaded. `check` exits 0 only when the attestation is valid for that run. It fails closed
with one of four conditions, printed as the first word of the message:
  MISSING     a required file is absent or empty
  INVALID     a file is not valid JSON, has a missing or malformed field, or the approver is the author
  MISMATCHED  a recorded hash, manifest id or commit does not match what was actually loaded or checked out
  LATE        the approval time is not strictly earlier than the platform-recorded start of the run attempt

The tool cannot make an attestation genuine. It checks form, binding and chronology; the independence of the reviewer
and the route that approved the attestation are decisions for people (see documentation/UPGRADE-VERIFICATION.md).
"""
import argparse
import datetime
import hashlib
import json
import os
import re
import subprocess
import sys

HEX40 = re.compile(r'^[0-9a-f]{40}$')
HEX64 = re.compile(r'^[0-9a-f]{64}$')
UTC = re.compile(r'^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$')
MAX_BYTES = 1024 * 1024


class Failure(Exception):
    def __init__(self, code, message):
        super().__init__(message)
        self.code = code


def fail(code, message):
    raise Failure(code, message)


def read_bytes(path, label):
    if not path or not os.path.isfile(path):
        fail('MISSING', f'{label} file is missing')
    if os.path.getsize(path) == 0:
        fail('MISSING', f'{label} file is empty')
    if os.path.getsize(path) > MAX_BYTES:
        fail('INVALID', f'{label} file is unexpectedly large')
    with open(path, 'rb') as handle:
        return handle.read()


def sha256(data):
    return hashlib.sha256(data).hexdigest()


def load_json(data, label):
    try:
        value = json.loads(data.decode('utf-8'))
    except (UnicodeDecodeError, ValueError) as exc:
        fail('INVALID', f'{label} is not valid JSON ({type(exc).__name__})')
    if not isinstance(value, dict):
        fail('INVALID', f'{label} must be a JSON object')
    return value


def parse_utc(text, label):
    if not isinstance(text, str) or not UTC.match(text):
        fail('INVALID', f'{label} must be a UTC time like 2026-01-31T09:30:00Z')
    try:
        return datetime.datetime.strptime(text, '%Y-%m-%dT%H:%M:%SZ')
    except ValueError:
        fail('INVALID', f'{label} is not a real date and time')


def need(obj, key, kind, label):
    if key not in obj or not isinstance(obj[key], kind) or isinstance(obj[key], bool) and kind is not bool:
        fail('INVALID', f'{label}.{key} is missing or has the wrong type')
    return obj[key]


def need_text(obj, key, label):
    value = need(obj, key, str, label)
    if not value.strip():
        fail('INVALID', f'{label}.{key} is empty')
    return value


def need_person(obj, key, label):
    person = need(obj, key, dict, label)
    need_text(person, 'role', f'{label}.{key}')
    need_text(person, 'id', f'{label}.{key}')
    return person


def validate_attestation(att):
    if att.get('schema_version') != 1:
        fail('INVALID', 'attestation.schema_version must be 1')
    for key in ('manifest_id', 'approval_ref'):
        need_text(att, key, 'attestation')
    for key, pattern, what in (('manifest_sha256', HEX64, '64 hex characters'), ('reviewed_commit', HEX40, '40 hex characters')):
        if not isinstance(att.get(key), str) or not pattern.match(att[key]):
            fail('INVALID', f'attestation.{key} must be {what}')
    author, approver = need_person(att, 'author', 'attestation'), need_person(att, 'approver', 'attestation')
    if author['id'].strip().lower() == approver['id'].strip().lower():
        fail('INVALID', 'the approver is the author: an attestation approved by its author is not independent')
    for side in ('source', 'target'):
        part = need(att, side, dict, 'attestation')
        need_text(part, 'kind', f'attestation.{side}')
        need_text(part, 'description', f'attestation.{side}')
        if not isinstance(part.get('db_version'), int) or isinstance(part.get('db_version'), bool) or part['db_version'] < 1:
            fail('INVALID', f'attestation.{side}.db_version must be a positive integer')
    return parse_utc(att.get('approved_at'), 'attestation.approved_at')


def validate_binding(bind):
    if bind.get('schema_version') != 1:
        fail('INVALID', 'binding.schema_version must be 1')
    if not isinstance(bind.get('run_id'), str) or not re.match(r'^\d{1,20}$', bind['run_id']):
        fail('INVALID', 'binding.run_id must be digits')
    if not isinstance(bind.get('run_attempt'), int) or isinstance(bind.get('run_attempt'), bool) or bind['run_attempt'] < 1:
        fail('INVALID', 'binding.run_attempt must be a positive integer')
    for key, pattern, what in (('checked_out_commit', HEX40, '40 hex characters'), ('manifest_sha256_loaded', HEX64, '64 hex characters'),
                               ('attestation_sha256', HEX64, '64 hex characters')):
        if not isinstance(bind.get(key), str) or not pattern.match(bind[key]):
            fail('INVALID', f'binding.{key} must be {what}')
    need_text(bind, 'attestation_ref', 'binding')
    return parse_utc(bind.get('run_started_at'), 'binding.run_started_at')


def is_ancestor(repo, older, newer):
    try:
        result = subprocess.run(['git', '-C', repo, 'merge-base', '--is-ancestor', older, newer], capture_output=True, timeout=30)
    except (OSError, subprocess.SubprocessError):
        fail('MISMATCHED', 'the commit ancestry could not be checked')
    return result.returncode == 0


def check(args):
    att_raw = read_bytes(args.attestation, 'attestation')
    bind_raw = read_bytes(args.binding, 'runtime binding')
    man_raw = read_bytes(args.manifest, 'manifest')
    att, bind, man = load_json(att_raw, 'attestation'), load_json(bind_raw, 'runtime binding'), load_json(man_raw, 'manifest')
    approved = validate_attestation(att)
    started = validate_binding(bind)
    actual_manifest = sha256(man_raw)
    if att['manifest_sha256'] != actual_manifest:
        fail('MISMATCHED', 'the attestation was approved for a different manifest than the one on disk')
    if bind['manifest_sha256_loaded'] != actual_manifest:
        fail('MISMATCHED', 'the runtime binding records a different manifest than the one on disk')
    if bind['attestation_sha256'] != sha256(att_raw):
        fail('MISMATCHED', 'the runtime binding records a different attestation than the one on disk')
    if man.get('id') != att['manifest_id']:
        fail('MISMATCHED', 'the manifest id differs from the attestation')
    if att['reviewed_commit'] != bind['checked_out_commit']:
        if not args.repo or not is_ancestor(args.repo, att['reviewed_commit'], bind['checked_out_commit']):
            fail('MISMATCHED', 'the reviewed commit is not the checked-out commit or one of its ancestors')
    if approved >= started:
        fail('LATE', 'the approval is not strictly earlier than the start of the run attempt')
    return f"OK attestation approved {att['approved_at']} before run {bind['run_id']} attempt {bind['run_attempt']} started {bind['run_started_at']}"


def bind(args):
    parse_utc(args.run_started_at, '--run-started-at')
    man_raw, att_raw = read_bytes(args.manifest, 'manifest'), read_bytes(args.attestation, 'attestation')
    record = {
        'schema_version': 1,
        'run_id': args.run_id,
        'run_attempt': args.run_attempt,
        'run_started_at': args.run_started_at,
        'checked_out_commit': args.checked_out_commit,
        'manifest_sha256_loaded': sha256(man_raw),
        'attestation_sha256': sha256(att_raw),
        'attestation_ref': args.attestation_ref,
    }
    validate_binding(record)
    with open(args.out, 'w', encoding='utf-8') as handle:
        json.dump(record, handle, indent=2, sort_keys=True)
        handle.write('\n')
    return f'binding written for run {args.run_id} attempt {args.run_attempt}'


def main():
    parser = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    sub = parser.add_subparsers(dest='cmd', required=True)
    c = sub.add_parser('check')
    c.add_argument('--attestation', required=True)
    c.add_argument('--binding', required=True)
    c.add_argument('--manifest', required=True)
    c.add_argument('--repo')
    b = sub.add_parser('bind')
    b.add_argument('--run-id', required=True)
    b.add_argument('--run-attempt', required=True, type=int)
    b.add_argument('--run-started-at', required=True)
    b.add_argument('--checked-out-commit', required=True)
    b.add_argument('--manifest', required=True)
    b.add_argument('--attestation', required=True)
    b.add_argument('--attestation-ref', required=True)
    b.add_argument('--out', required=True)
    args = parser.parse_args()
    try:
        print(check(args) if args.cmd == 'check' else bind(args))
    except Failure as exc:
        sys.stderr.write(f'{exc.code} {exc}\nC26-006 is not satisfied.\n')
        sys.exit(1)


if __name__ == '__main__':
    try:
        main()
    except SystemExit:
        raise
    except Exception as exc:  # never a traceback for a gate
        sys.stderr.write(f'INVALID unexpected problem ({type(exc).__name__})\nC26-006 is not satisfied.\n')
        sys.exit(1)

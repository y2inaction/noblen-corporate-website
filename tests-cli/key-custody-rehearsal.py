#!/usr/bin/env python3
"""Controlled audit-key rotation and recovery rehearsal on a DISPOSABLE WordPress site.

What it is: an automated rehearsal of the operator procedures in documentation/RECOVERY.md (section "Audit key custody")
using the real `wp noblen key|audit|migrate` commands against a real WordPress and database. It exercises backup, planned
rotation, interrupted rotation, compromise rotation, suspension, loss of every key and recovery with a recovery authority,
restore from backup, tampering detection, wrong, missing and invalid keys, capability and confirmation failures, and it
keeps audit evidence. It never prints or stores a key.

What it is NOT: it is not the owner's C34-M01 record and not a human rehearsal. It proves the commands and the procedure
work as documented; a person still has to perform C34-M01 on staging. Throwaway keys are generated at random for each run
and exist only in a temporary directory.

Safety: it builds its OWN copy of WordPress and its own database (a new name each run) and refuses to run unless
NOBLEN_REHEARSAL_DISPOSABLE=yes is set. It never touches an existing site, wp-config.php or database, and it never
reads any key from an existing site. Keys are switched per command by pointing NOBLEN_REHEARSAL_KEYS at a small PHP file
that the rehearsal site's own wp-config.php includes; that is how a real operator edit of wp-config.php is emulated.

Environment:
  NOBLEN_REHEARSAL_DISPOSABLE=yes   required
  WP_SRC        a WordPress core directory to copy (its wp-config.php, uploads and wp-content/plugins/noblen* are ignored)
  REHEARSAL_DB_HOST (default localhost), REHEARSAL_DB_USER (default root), REHEARSAL_DB_PASS (default empty)
  WP_CLI        path to wp (default: vendor/bin/wp in the repository, else wp)
  REHEARSAL_EVIDENCE  directory for the evidence report (default: inside the temporary directory, printed at the end)

Run: NOBLEN_REHEARSAL_DISPOSABLE=yes WP_SRC=/path/to/wordpress python3 -I tests-cli/key-custody-rehearsal.py
"""
import json
import os
import re
import secrets
import shutil
import subprocess
import sys
import tempfile

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
PLUGIN = os.path.join(ROOT, 'wordpress-plugin', 'noblen-corporate-core')
THEME = os.path.join(ROOT, 'wordpress-theme', 'noblen-corporate')

if os.environ.get('NOBLEN_REHEARSAL_DISPOSABLE') != 'yes':
    sys.exit('Refusing to run: set NOBLEN_REHEARSAL_DISPOSABLE=yes to confirm this builds and destroys its own disposable site.')
WP_SRC = os.environ.get('WP_SRC', '')
if not WP_SRC or not os.path.isfile(os.path.join(WP_SRC, 'wp-load.php')):
    sys.exit('Set WP_SRC to a WordPress core directory (it is copied, never modified).')
DB_HOST = os.environ.get('REHEARSAL_DB_HOST', 'localhost')
DB_USER = os.environ.get('REHEARSAL_DB_USER', 'root')
DB_PASS = os.environ.get('REHEARSAL_DB_PASS', '')
WPBIN = os.environ.get('WP_CLI') or (os.path.join(ROOT, 'vendor', 'bin', 'wp') if os.path.exists(os.path.join(ROOT, 'vendor', 'bin', 'wp')) else 'wp')

TMP = tempfile.mkdtemp(prefix='noblen-rehearsal-')
CORE = os.path.join(TMP, 'wp')
KEYDIR = os.path.join(TMP, 'keys')
EVID = os.environ.get('REHEARSAL_EVIDENCE') or os.path.join(TMP, 'evidence')
os.makedirs(KEYDIR)
os.makedirs(EVID, exist_ok=True)
DBNAME = 'noblen_rh_' + secrets.token_hex(4)

# Throwaway secrets: random per run, distinctive so a leak is detectable.
SECRETS = {k: 'REHEARSAL-' + k.upper() + '-' + secrets.token_hex(20) for k in ('k1', 'k2', 'k3', 'k4', 'k5')}
SECRETS['k1_wrong'] = 'REHEARSAL-WRONG-' + secrets.token_hex(20)
evidence = []
captured = []  # all command output, scanned for leaks at the end
passed = failed = 0


def ok(cond, name, extra=''):
    global passed, failed
    evidence.append({'check': name, 'passed': bool(cond)})
    if cond:
        passed += 1
        print('ok  ', name)
    else:
        failed += 1
        print('FAIL', name, extra)


def keyfile(name, keys, current):
    path = os.path.join(KEYDIR, name + '.php')
    body = '<?php\n'
    if keys is not None:
        body += 'define( "NOBLEN_AUDIT_KEYS", ' + 'array( ' + ', '.join(f'"{k}" => "{v}"' for k, v in keys.items()) + ' )' + ' );\n'
    if current:
        body += f'define( "NOBLEN_AUDIT_KEY_ID", "{current}" );\n'
    with open(path, 'w') as fh:
        fh.write(body)
    return path


def S(*ids):
    return {i: SECRETS[i] for i in ids}


KEYSETS = {
    'none': keyfile('none', None, None),
    'k1': keyfile('k1', S('k1'), 'k1'),
    'k1_wrong': keyfile('k1_wrong', {'k1': SECRETS['k1_wrong']}, 'k1'),
    'k1k2_sign1': keyfile('k1k2_sign1', S('k1', 'k2'), 'k1'),
    'k1k2_sign2': keyfile('k1k2_sign2', S('k1', 'k2'), 'k2'),
    'k1k2k3_sign2': keyfile('k1k2k3_sign2', S('k1', 'k2', 'k3'), 'k2'),
    'k1k2k3_sign3': keyfile('k1k2k3_sign3', S('k1', 'k2', 'k3'), 'k3'),
    'k1k2k3k4_sign3': keyfile('k1k2k3k4_sign3', S('k1', 'k2', 'k3', 'k4'), 'k3'),
    'k1k2k3k4_sign4': keyfile('k1k2k3k4_sign4', S('k1', 'k2', 'k3', 'k4'), 'k4'),
    'k2_noid': keyfile('k2_noid', S('k2'), None),
    'k1wrong_k2': keyfile('k1wrong_k2', {'k1': SECRETS['k1_wrong'], 'k2': SECRETS['k2']}, 'k1'),
    'k5_only': keyfile('k5_only', S('k5'), 'k5'),
    'k5_and_k4': keyfile('k5_and_k4', S('k5', 'k4'), 'k5'),
}


def wp(keys, *args, user=None, check=False):
    """Run wp against the rehearsal site with a given key file. Returns (exit status, combined output)."""
    cmd = [WPBIN, '--allow-root', f'--path={CORE}'] + list(args)
    if user:
        cmd.append(f'--user={user}')
    env = dict(os.environ, NOBLEN_REHEARSAL_KEYS=KEYSETS[keys] if keys else '')
    r = subprocess.run(cmd, cwd=TMP, env=env, capture_output=True, text=True, timeout=300)
    out = (r.stdout or '') + (r.stderr or '')
    captured.append(out)
    if check and r.returncode != 0:
        sys.exit(f'setup command failed: {" ".join(args[:3])}\n{out[:500]}')
    return r.returncode, out


def status(keys):
    rc, out = wp(keys, 'noblen', 'audit', 'status')
    m = re.search(r'^integrity_state\s+(\S+)', out, re.M)
    return m.group(1) if m else 'ERR:' + out[:120]


def audit_field(keys, name):
    rc, out = wp(keys, 'noblen', 'audit', 'status')
    m = re.search(r'^' + re.escape(name) + r'\s+(\S*)', out, re.M)
    return m.group(1) if m else None


def export(keys, filename):
    path = os.path.join(EVID, filename)
    rc, out = wp(keys, 'noblen', 'audit', 'export', f'--file={path}', user='reh_approver')
    if rc != 0:
        return None
    with open(path) as fh:
        return json.load(fh)


def sql(query):
    rc, out = wp('none', 'db', 'query', query, '--skip-column-names')
    return out.strip()


try:
    # ---- Build the disposable site: a copy of core, its own database and its own wp-config.php.
    shutil.copytree(WP_SRC, CORE, symlinks=True, ignore=shutil.ignore_patterns('wp-config.php', 'uploads', 'debug.log', 'noblen-corporate*'))
    for sub, src in (('plugins/noblen-corporate-core', PLUGIN), ('themes/noblen-corporate', THEME)):
        dest = os.path.join(CORE, 'wp-content', sub)
        os.makedirs(os.path.dirname(dest), exist_ok=True)
        if os.path.lexists(dest):
            os.unlink(dest) if os.path.islink(dest) else shutil.rmtree(dest)
        os.symlink(src, dest)
    salts = '\n'.join(f"define( '{n}', '{secrets.token_hex(32)}' );" for n in ('AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT'))
    with open(os.path.join(TMP, 'wp-config.php'), 'w') as fh:
        fh.write(f"""<?php
define( 'DB_NAME', '{DBNAME}' ); define( 'DB_USER', '{DB_USER}' ); define( 'DB_PASSWORD', '{DB_PASS}' );
define( 'DB_HOST', '{DB_HOST}' ); define( 'DB_CHARSET', 'utf8mb4' ); define( 'DB_COLLATE', '' );
{salts}
$table_prefix = 'wp_';
define( 'WP_DEBUG', true ); define( 'WP_DEBUG_LOG', '{TMP}/debug.log' ); define( 'WP_DEBUG_DISPLAY', false );
// Emulates the operator editing wp-config.php: the keys come from the file named by this variable, or are absent.
$noblen_keys = getenv( 'NOBLEN_REHEARSAL_KEYS' );
if ( $noblen_keys && is_readable( $noblen_keys ) ) {{ require $noblen_keys; }}
if ( ! defined( 'ABSPATH' ) ) {{ define( 'ABSPATH', __DIR__ . '/wp/' ); }}
require_once ABSPATH . 'wp-settings.php';
""")
    wp('none', 'db', 'create', check=True)
    wp('none', 'core', 'install', '--url=http://rehearsal.invalid', '--title=Rehearsal', '--admin_user=reh_root', '--admin_password=Tmp-Rehearsal-1!', '--admin_email=root@example.invalid', '--skip-email', check=True)
    wp('k1', 'theme', 'activate', 'noblen-corporate', check=True)
    wp('k1', 'plugin', 'activate', 'noblen-corporate-core', check=True)
    for login, role in (('reh_admin', 'administrator'), ('reh_authority', 'administrator'), ('reh_approver', 'noblen_publication_approver'), ('reh_sub', 'subscriber')):
        wp('k1', 'user', 'create', login, f'{login}@example.invalid', f'--role={role}', '--user_pass=Tmp-Rehearsal-1!', check=True)
    print(f'Rehearsal site: {TMP} (database {DBNAME})')

    # ---- A. Baseline with the first key and some audit history.
    ok(status('k1') == 'verified', 'A1 a fresh site with a configured key reports verified')
    for _ in range(3):
        wp('k1', 'noblen', 'migrate', 'confirm-backup', user='reh_admin', check=True)
    ex0 = export('k1', 'export-0-baseline.json')
    ok(ex0 is not None and ex0['entry_count'] >= 3, 'A2 three signed audit entries exist and an approver can export them')
    ok(status('k1') == 'verified' and audit_field('k1', 'unverifiable') == '0', 'A3 the chain verifies with every entry signed and no unverifiable entry')
    ok(audit_field('k1', 'key_fingerprint') and re.fullmatch(r'[0-9a-f]{16}', audit_field('k1', 'key_fingerprint')), 'A4 status shows only a 16-hex fingerprint')

    # ---- B. Backup: database dump plus an independent audit export, taken before any change.
    backup1 = os.path.join(EVID, 'backup-1-before-rotation.sql')
    rc, out = wp('none', 'db', 'export', backup1)
    ok(rc == 0 and os.path.getsize(backup1) > 1000, 'B1 database backup written')
    head0 = ex0['chain_head']
    ok(bool(re.fullmatch(r'[0-9a-f]{64}', head0)), 'B2 the export carries a 64-hex chain head for independent custody')

    # ---- C. Refusals before anything changes.
    n0 = ex0['entry_count']
    rc, out = wp('k1k2_sign1', 'noblen', 'key', 'rotate', '--new-key-id=k2', '--confirm-new-key-id=k2')
    ok(rc != 0 and 'not allowed' in out.lower(), 'C1 rotation with no user is refused')
    rc, out = wp('k1k2_sign1', 'noblen', 'key', 'rotate', '--new-key-id=k2', '--confirm-new-key-id=k2', user='reh_sub')
    ok(rc != 0 and 'not allowed' in out.lower(), 'C2 rotation by a subscriber is refused')
    rc, out = wp('k1k2_sign1', 'noblen', 'key', 'rotate', '--new-key-id=k2', '--confirm-new-key-id=nope', user='reh_admin')
    ok(rc != 0 and 'confirmation failed' in out.lower(), 'C3 rotation with a mistyped confirmation is refused')
    rc, out = wp('k1', 'noblen', 'key', 'rotate', '--new-key-id=k2', '--confirm-new-key-id=k2', user='reh_admin')
    ok(rc != 0 and 'configured' in out.lower(), 'C4 rotation to a key that is not configured is refused')
    rc, out = wp('k1k2_sign1', 'noblen', 'key', 'rotate', '--new-key-id=k1', '--confirm-new-key-id=k1', user='reh_admin')
    ok(rc != 0 and 'different' in out.lower(), 'C5 rotation to the current key is refused')
    rc, out = wp('k1k2_sign1', 'noblen', 'key', 'rotate', '--new-key-id=k2', '--confirm-new-key-id=k2', '--reason=whenever', user='reh_admin')
    ok(rc != 0 and 'planned' in out, 'C6 an unknown rotation reason is refused')
    rc, out = wp('k1k2_sign1', 'noblen', 'key', 'recover', '--sign-off-ref=X', '--reason=Y', '--authority-name=Z', '--confirm=yes please', user='reh_admin')
    ok(rc != 0 and 'confirmation' in out.lower(), 'C7 recovery without the exact confirmation phrase is refused')
    rc, out = wp('k1k2_sign1', 'noblen', 'key', 'recover', '--sign-off-ref=X', '--reason=Y', '--authority-name=Z', '--confirm=ESTABLISH NEW KEY', user='reh_admin')
    ok(rc != 0 and 'lost or unavailable' in out.lower(), 'C8 recovery while the old key works is refused (a recovery is only for a lost key)')
    exc = export('k1', 'export-1-after-refusals.json')
    ok(exc is not None and exc['entry_count'] == n0 and exc['chain_head'] == head0, 'C9 none of the refusals wrote an audit entry')

    # ---- D. Missing and invalid keys.
    ok(status('none') == 'key_missing', 'D1 no key configured gives key_missing (approvals blocked)')
    rc, out = wp('k2_noid', 'noblen', 'key', 'rotate', '--new-key-id=k2', '--confirm-new-key-id=k2', user='reh_admin')
    ok(status('k2_noid') == 'key_missing' and rc != 0 and 'must be verified' in out, 'D2 rotation is refused while the signing key is missing, even though the new key is configured', out[:200])
    ok(status('k1_wrong') == 'key_invalid', 'D3 a wrong secret under the same identifier gives key_invalid')
    rc, out = wp('k1wrong_k2', 'noblen', 'key', 'rotate', '--new-key-id=k2', '--confirm-new-key-id=k2', user='reh_admin')
    ok(status('k1wrong_k2') == 'key_invalid' and rc != 0 and 'must be verified' in out, 'D4 rotation is refused while the signing key is invalid, even though the new key is configured', out[:200])
    ok(status('k1') == 'verified', 'D5 putting the correct key back restores verified (no data was changed)')

    # ---- E. Planned rotation.
    rc, out = wp('k1k2_sign1', 'noblen', 'key', 'rotate', '--new-key-id=k2', '--confirm-new-key-id=k2', user='reh_admin')
    ok(rc == 0 and 'Rotation recorded' in out, 'E1 planned rotation succeeds with both keys configured', out[:200])
    ok(status('k1k2_sign1') == 'key_invalid', 'E2 until the operator switches the signing key in wp-config.php the state is key_invalid (a deliberate guard)')
    ok(status('k1k2_sign2') == 'verified', 'E3 after switching NOBLEN_AUDIT_KEY_ID to the new key the chain verifies, with the old key kept for verification')
    wp('k1k2_sign2', 'noblen', 'migrate', 'confirm-backup', user='reh_admin', check=True)
    ex1 = export('k1k2_sign2', 'export-2-after-rotation.json')
    ok(ex1 is not None and ex1['entry_count'] > n0, 'E4 new entries are appended after rotation')
    same_prefix = ex1 is not None and all(a['record_hash'] == b['record_hash'] and a['id'] == b['id'] for a, b in zip(ex0['entries'], ex1['entries']))
    ok(same_prefix, 'E5 every entry from before the rotation is byte-for-byte unchanged (history is never rewritten or re-signed)')
    kinds = [e['event_type'] for e in (ex1 or {'entries': []})['entries']]
    ok('key_rotation_started' in kinds and 'key_rotation_confirmed' in kinds and kinds.index('key_rotation_started') < kinds.index('key_rotation_confirmed'), 'E6 the audit trail records rotation started then confirmed')
    started = next((e for e in ex1['entries'] if e['event_type'] == 'key_rotation_started'), None)
    confirmed = next((e for e in ex1['entries'] if e['event_type'] == 'key_rotation_confirmed'), None)
    ok(started and confirmed and started['key_id'] == 'k1' and confirmed['key_id'] == 'k2', 'E7 the start is signed with the old key and the confirmation with the new key (both keys proven held)')
    rc, out = wp('k1k2_sign2', 'noblen', 'key', 'status')
    ok(rc == 0 and 'current_key_id' in out and 'k2' in out and 'verification only' in out, 'E8 key status lists the signing key and the verification-only key')
    backup2 = os.path.join(EVID, 'backup-2-after-rotation.sql')
    wp('none', 'db', 'export', backup2, check=True)

    # ---- F. Interrupted rotation (the test seam is a filter; WP-CLI cannot interrupt itself, so wp eval installs it), then completion.
    interrupt = ('add_filter("noblen_core_rotation_interrupt","__return_true");'
                 '$r=Noblen\\Core\\Audit\\KeyCustody::rotate("k3");echo is_wp_error($r)?"ERR ".$r->get_error_message():("complete=".var_export($r["complete"],true));')
    rc, out = wp('k1k2k3_sign2', 'eval', interrupt, user='reh_admin')
    ok(rc == 0 and 'complete=false' in out, 'F1 an interrupted rotation leaves a start entry and no confirmation', out[:200])
    ok(status('k1k2k3_sign2') == 'rotation_incomplete', 'F2 the state is rotation_incomplete')
    rc, out = wp('k1k2_sign2', 'noblen', 'key', 'complete-rotation', user='reh_admin')
    ok(rc != 0, 'F3 completing is refused while the new key is not configured (no verified state to continue from)')
    rc, out = wp('k1k2k3_sign2', 'noblen', 'key', 'complete-rotation', user='reh_admin')
    ok(rc == 0 and 'Rotation completed' in out, 'F4 completing succeeds once the new key is configured', out[:200])
    ok(status('k1k2k3_sign3') == 'verified', 'F5 after switching the signing key the chain verifies')
    rc, out = wp('k1k2k3k4_sign3', 'eval', interrupt.replace('"k3"', '"k4"'), user='reh_admin')
    ok(rc == 0 and status('k1k2k3k4_sign3') == 'rotation_incomplete', 'F6 a second interrupted rotation is detected')
    rc, out = wp('k1k2k3k4_sign3', 'noblen', 'key', 'abandon-rotation', '--reason=rehearsal abandon', '--yes', user='reh_admin')
    ok(rc == 0 and status('k1k2k3k4_sign3') == 'verified', 'F7 abandoning an interrupted rotation returns to verified and keeps the old signing key', out[:200])
    rc, out = wp('k1k2k3k4_sign3', 'noblen', 'key', 'abandon-rotation', '--reason=again', '--yes', user='reh_admin')
    ok(rc != 0, 'F8 abandoning when nothing is waiting is refused')

    # ---- G. Suspension and compromise rotation.
    rc, out = wp('k1k2k3k4_sign3', 'noblen', 'key', 'suspend', user='reh_admin')
    ok(rc != 0 and 'reason' in out.lower(), 'G1 suspension without a reason is refused')
    rc, out = wp('k1k2k3k4_sign3', 'noblen', 'key', 'suspend', '--reason=suspected exposure of a key file', user='reh_admin')
    ok(rc == 0 and status('k1k2k3k4_sign3') == 'approvals_suspended', 'G2 suspension blocks approvals at once')
    rc, out = wp('k1k2k3k4_sign3', 'noblen', 'key', 'rotate', '--new-key-id=k4', '--confirm-new-key-id=k4', '--reason=compromise', '--suspect-from=2', '--suspect-to=9', user='reh_admin')
    ok(rc == 0 and 'Rotation recorded' in out, 'G3 a compromise rotation is allowed while approvals are suspended', out[:200])
    exg = export('k1k2k3k4_sign4', 'export-3-after-compromise-rotation.json')
    start = next((e for e in reversed(exg['entries']) if e['event_type'] == 'key_rotation_started'), None) if exg else None
    detail = json.loads(start['detail']) if start else {}
    ok(detail.get('reason') == 'compromise' and detail.get('suspect_range', {}).get('from_id') == 2 and detail.get('suspect_range', {}).get('to_id') == 9, 'G4 the suspected range is recorded in the signed entry')
    rc, out = wp('k1k2k3k4_sign4', 'noblen', 'key', 'resume', '--yes', user='reh_admin')
    ok(rc == 0 and status('k1k2k3k4_sign4') == 'verified', 'G5 approvals resume after the new key is in place')

    # ---- H. Tamper detection and restore from backup.
    table = sql("SHOW TABLES LIKE '%noblen_audit'").splitlines()[-1]
    backup3 = os.path.join(EVID, 'backup-3-before-tamper.sql')
    wp('none', 'db', 'export', backup3, check=True)
    mid = sql(f"SELECT id FROM {table} WHERE key_id='k2' ORDER BY id LIMIT 1")
    sql(f"UPDATE {table} SET actor_login='tampered' WHERE id={mid}")
    ok(status('k1k2k3k4_sign4') == 'chain_broken', 'H1 an edit to a signed audit row is detected (chain_broken)')
    rc, out = wp('none', 'db', 'import', backup3)
    ok(rc == 0 and status('k1k2k3k4_sign4') == 'verified', 'H2 restoring the backup taken before the edit returns the chain to verified')

    # ---- I. Total loss of every key, and recovery.
    pre_loss = export('k1k2k3k4_sign4', 'export-4-before-loss.json')
    ok(status('k5_only') == 'key_invalid', 'I1 with every old key lost and only a new key configured the state is key_invalid')
    base = ['noblen', 'key', 'recover']
    full = ['--sign-off-ref=REHEARSAL-SIGNOFF-1', '--reason=rehearsal: all keys lost', '--authority-user=reh_authority', '--confirm=ESTABLISH NEW KEY']
    rc, out = wp('k5_only', *base, *[a for a in full if not a.startswith('--confirm')], user='reh_admin')
    ok(rc != 0 and 'confirm' in out.lower(), 'I2 recovery without the confirmation option is refused by WP-CLI before any code runs', out[:300])
    rc, out = wp('k5_only', *base, *[a for a in full if not a.startswith('--sign-off')], user='reh_admin')
    ok(rc != 0 and 'sign-off' in out.lower(), 'I3 recovery without a sign-off reference is refused')
    rc, out = wp('k5_only', *base, *[a for a in full if not a.startswith('--authority')], user='reh_admin')
    ok(rc != 0 and 'authority' in out.lower(), 'I4 recovery without a named recovery authority is refused')
    rc, out = wp('k5_only', *base, *[a if not a.startswith('--authority') else '--authority-user=reh_admin' for a in full], user='reh_admin')
    ok(rc != 0 and 'different person' in out.lower(), 'I5 the executing administrator cannot be their own recovery authority (separation of duties)')
    rc, out = wp('k5_only', *base, *full, user='reh_sub')
    ok(rc != 0 and 'not allowed' in out.lower(), 'I6 a subscriber cannot run a recovery')
    rc, out = wp('none', *base, *full, user='reh_admin')
    ok(rc != 0 and 'configure the new key' in out.lower(), 'I7 recovery without a new key configured is refused (the plugin never creates a key)')
    rc, out = wp('k5_only', *base, *[a if not a.startswith('--authority') else '--authority-user=nobody_here' for a in full], user='reh_admin')
    ok(rc != 0 and 'not found' in out.lower(), 'I8 an unknown recovery authority user is refused')
    rc, out = wp('k5_only', *base, *full, '--last-export=/nonexistent/export.json', user='reh_admin')
    ok(rc != 0 and 'cannot be read' in out.lower(), 'I9 an unreadable comparison export is refused')
    ok(status('k5_only') == 'key_invalid', 'I10 none of the refusals changed the state')
    rc, out = wp('k5_only', *base, *full, f'--last-export={os.path.join(EVID, "export-4-before-loss.json")}', '--export-ref=REHEARSAL-EXPORT-REF-1', user='reh_admin')
    ok(rc == 0 and 'matches_up_to_export_point' in out, 'I11 recovery succeeds and the database matches the earlier export up to the export point', out[:300])
    ok(status('k5_only') == 'recovery_incomplete', 'I12 approvals stay blocked: the state is recovery_incomplete until the owner completes it')
    rc, out = wp('k5_only', 'noblen', 'key', 'complete-recovery', user='reh_admin')
    ok(rc != 0, 'I13 completion without the owner completion reference is refused')
    rc, out = wp('k5_only', 'noblen', 'key', 'complete-recovery', '--completion-ref=REHEARSAL-COMPLETION-1', user='reh_admin')
    ok(rc == 0 and 'Recovery completed' in out, 'I14 completion succeeds with the owner reference and a verifying new segment', out[:200])
    ok(status('k5_only') == 'verified', 'I15 the chain is verified again, with earlier entries reported as unverifiable, not verified')
    ok(int(audit_field('k5_only', 'unverifiable') or 0) > 0, 'I16 earlier entries are honestly reported as unverifiable while their keys are absent')
    rc, out = wp('k5_only', 'noblen', 'key', 'complete-recovery', '--completion-ref=AGAIN', user='reh_admin')
    ok(rc != 0, 'I17 completing twice is refused')
    exr = export('k5_only', 'export-5-after-recovery.json')
    marker = next((e for e in reversed(exr['entries']) if e['event_type'] == 'key_recovery_marker'), None) if exr else None
    md = json.loads(marker['detail']) if marker else {}
    ok(marker and marker['key_id'] == 'k5' and md.get('sign_off_ref') == 'REHEARSAL-SIGNOFF-1' and md.get('export_ref') == 'REHEARSAL-EXPORT-REF-1' and md.get('comparison', {}).get('result') == 'matches_up_to_export_point', 'I18 the signed marker records the sign-off, the authority, the export reference and the comparison result')
    ok(exr and all(a['record_hash'] == b['record_hash'] for a, b in zip(pre_loss['entries'], exr['entries'])), 'I19 no earlier entry was changed by the recovery')
    rc, out = wp('k5_and_k4', 'noblen', 'key', 'old-key-available', '--key-id=k4', user='reh_admin')
    ok(rc == 0 and 'Recorded' in out, 'I20 an old key that turns up later can be recorded as verification-only')
    ok(int(audit_field('k5_and_k4', 'unverifiable') or 0) < int(audit_field('k5_only', 'unverifiable') or 0), 'I21 more of the older entries verify once the old key is available again')

    # ---- J. Restore a backup, with and without the key that signed it.
    wp('none', 'db', 'import', backup1, check=True)
    ok(status('k1') == 'verified', 'J1 restoring the pre-rotation backup with its original key verifies')
    ok(status('k5_only') == 'key_invalid', 'J2 the same backup with only a different key does NOT verify: a backup is useless for audit purposes without its key')

    # ---- K. Secrets never leak.
    blob = '\n'.join(captured)
    for fname in os.listdir(EVID):
        with open(os.path.join(EVID, fname), errors='replace') as fh:
            blob += fh.read()
    leaked = [k for k, v in SECRETS.items() if v in blob]
    ok(not leaked, 'K1 no key secret appears in any command output, export, backup or evidence file', str(leaked))
    dbdump = subprocess.run([WPBIN, '--allow-root', f'--path={CORE}', 'db', 'export', '-'], cwd=TMP, env=dict(os.environ, NOBLEN_REHEARSAL_KEYS=''), capture_output=True, text=True).stdout
    ok(not any(v in dbdump for v in SECRETS.values()), 'K2 no key secret is stored in the database')
    log = os.path.join(TMP, 'debug.log')
    logtext = open(log).read() if os.path.exists(log) else ''
    ok(not re.search(r'PHP (Warning|Fatal|Notice|Parse)', logtext), 'K3 the PHP log has no warning, notice or fatal error', logtext[:300])

    with open(os.path.join(EVID, 'rehearsal-report.json'), 'w') as fh:
        json.dump({'kind': 'automated rehearsal on a disposable site; NOT the owner record C34-M01', 'checks': evidence, 'passed': passed, 'failed': failed}, fh, indent=1)
    print(f'\nEvidence kept in {EVID}')
finally:
    # Always drop the disposable database and directory. The keys in them were throwaway.
    try:
        subprocess.run([WPBIN, '--allow-root', f'--path={CORE}', 'db', 'drop', '--yes'], cwd=TMP, env=dict(os.environ, NOBLEN_REHEARSAL_KEYS=''), capture_output=True, timeout=120)
    except Exception:  # noqa: BLE001
        pass
    if os.environ.get('REHEARSAL_KEEP') != 'yes' and not os.environ.get('REHEARSAL_EVIDENCE'):
        pass  # evidence is inside TMP; keep it so the path printed above stays valid

print(f'\n{passed} passed, {failed} failed')
sys.exit(1 if failed else 0)

#!/usr/bin/env bash
# Operator WP-CLI commands: wp noblen audit status|list|export and wp noblen migrate status|confirm-backup|run.
# Needs a WordPress site with the Noblen core plugin active. It creates two temporary users and deletes them through WP-CLI,
# and it appends one audit entry (the backup confirmation), so run it on a disposable site only. The one export file it writes
# stays in the temporary directory (its name is unique).
# Usage: WP_DIR=/path/to/wordpress tests-cli/noblen-cli-test.sh
set -u
REPO="$(cd "$(dirname "$0")/.." && pwd)"
WP_DIR="${WP_DIR:-${NOBLEN_WP_PATH:-}}"
[ -n "$WP_DIR" ] || { echo "Set WP_DIR to a WordPress directory." >&2; exit 2; }
W() { "$REPO/vendor/bin/wp" --allow-root --path="$WP_DIR" "$@"; }
pass=0; fail=0
ok()  { pass=$((pass+1)); echo "ok   $1"; }
bad() { fail=$((fail+1)); echo "FAIL $1 :: ${2:-}"; }
has() { grep -q -- "$2" <<<"$1" && ok "$3" || bad "$3" "missing [$2] in: $(head -c 200 <<<"$1")"; }
stamp="$$$(date +%s)"
APPROVER="cli_approver_$stamp"; ADMIN="cli_admin_$stamp"
W user create "$APPROVER" "$APPROVER@example.invalid" --role=noblen_publication_approver --user_pass="Tmp-$stamp-aA1!" >/dev/null 2>&1
W user create "$ADMIN" "$ADMIN@example.invalid" --role=administrator --user_pass="Tmp-$stamp-aA1!" >/dev/null 2>&1
cleanup() { W user delete "$APPROVER" --yes >/dev/null 2>&1; W user delete "$ADMIN" --yes >/dev/null 2>&1; }
trap cleanup EXIT
W user get "$APPROVER" --field=ID >/dev/null 2>&1 && ok "temporary approver created" || bad "temporary approver created"

# ---- audit status
out=$(W noblen audit status 2>&1); rc=$?
[ $rc -eq 0 ] && ok "audit status succeeds" || bad "audit status succeeds" "$out"
for k in integrity_state key_configured key_id key_fingerprint entries verified unsigned unverifiable chain_ok; do has "$out" "^$k" "audit status reports $k"; done
fp=$(awk '$1=="key_fingerprint"{print $2}' <<<"$out")
if [ -n "$fp" ]; then [[ "$fp" =~ ^[0-9a-f]{16}$ ]] && ok "the key fingerprint is 16 hex characters (never the secret)" || bad "key fingerprint shape" "$fp"; else ok "no key configured: fingerprint is empty"; fi

# ---- audit list and export need a user who may read the audit log
out=$(W noblen audit list 2>&1); rc=$?
[ $rc -ne 0 ] && grep -q "not allowed" <<<"$out" && ok "audit list without a user is refused with a plain message" || bad "audit list without a user is refused" "$out"
out=$(W noblen audit list --user="$ADMIN" 2>&1); rc=$?
[ $rc -ne 0 ] && grep -q "not allowed" <<<"$out" && ok "an administrator who is not an approver cannot read the audit log" || bad "administrator refused" "$out"
out=$(W noblen audit list --user="$APPROVER" --format=json --limit=2 2>&1); rc=$?
if [ $rc -eq 0 ] && python3 -c 'import json,sys; d=json.loads(sys.argv[1]); assert isinstance(d,list) and len(d)<=2; [d[0][k] for k in ("id","event_time","event_type","actor","key_id")] if d else None' "$out" 2>/dev/null; then ok "an approver can list entries as JSON with who, when and what (limit respected)"; else bad "approver audit list" "$out"; fi
out=$(W noblen audit list --user="$APPROVER" --limit=1 2>&1); [ $? -eq 0 ] && has "$out" "event_type" "audit list prints a table by default" || bad "audit list table" "$out"

f="${TMPDIR:-/tmp}/noblen-export-$stamp.json"
out=$(W noblen audit export 2>&1); [ $? -ne 0 ] && ok "audit export without a user is refused" || bad "export without a user" "$out"
out=$(W noblen audit export --user="$APPROVER" --file="$f" 2>&1); rc=$?
[ $rc -eq 0 ] && ok "audit export writes a new file" || bad "audit export writes a file" "$out"
if python3 -c 'import json,sys,re; d=json.load(open(sys.argv[1])); assert d["format"]==1 and d["entry_count"]==len(d["entries"]) and re.fullmatch("[0-9a-f]{64}", d["chain_head"])' "$f" 2>/dev/null; then ok "the export is valid JSON with the entry count and a 64-hex chain head"; else bad "export content" "$(head -c 200 "$f" 2>/dev/null)"; fi
out=$(W noblen audit export --user="$APPROVER" --file="$f" 2>&1); [ $? -ne 0 ] && grep -q "never overwritten" <<<"$out" && ok "an existing export file is never overwritten" || bad "overwrite refused" "$out"

# ---- migrate
out=$(W noblen migrate status 2>&1); rc=$?
[ $rc -eq 0 ] && ok "migrate status succeeds" || bad "migrate status" "$out"
has "$out" "installed_version" "migrate status reports the installed version"
has "$out" "upgrade_pending[[:space:]]*no" "no upgrade is pending on a current site"
has "$out" "healthy[[:space:]]*yes" "the schema is healthy"
out=$(W noblen migrate confirm-backup 2>&1); [ $? -ne 0 ] && grep -q "not allowed" <<<"$out" && ok "confirming a backup without a user is refused" || bad "confirm-backup without a user" "$out"
out=$(W noblen migrate confirm-backup --user="$ADMIN" 2>&1); rc=$?
[ $rc -eq 0 ] && ok "an administrator can confirm a backup" || bad "confirm-backup" "$out"
out=$(W noblen migrate status 2>&1); has "$out" "backup_confirmed_for_version" "migrate status shows the confirmed backup"
out=$(W noblen migrate run 2>&1); [ $? -ne 0 ] && ok "migrate run without a user is refused" || bad "migrate run without a user" "$out"
out=$(W noblen migrate run --user="$ADMIN" 2>&1); rc=$?
[ $rc -eq 0 ] && grep -q "already current" <<<"$out" && ok "migrate run reports that a current database needs no upgrade" || bad "migrate run" "$out"
out=$(W help noblen audit 2>&1); has "$out" "status" "wp help documents the audit commands"

echo; echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]

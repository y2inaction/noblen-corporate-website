#!/usr/bin/env bash
# Repository hygiene audit: tracked artifacts, credentials, local paths and development data in shipped code.
# Exit 1 on any finding. Runs in CI (static job) and before any push.
set -u
cd "$(git rev-parse --show-toplevel)"
fail=0
bad() { echo "FAIL: $1"; shift; printf '  %s\n' "$@"; fail=1; }

# 1. Generated or private artifacts must not be tracked.
hits=$(git ls-files | grep -iE '(\.(sql|sqlite|db|log|env|pem|key|zip|tar|gz|cache)$|(^|/)wp-config\.php$|(^|/)(vendor|node_modules|dist)/)' || true)
[ -n "$hits" ] && bad "tracked generated or private files" $hits

# 2. Credential shapes anywhere in tracked files.
hits=$(git grep -InE 'AKIA[0-9A-Z]{16}|gh[pousr]_[A-Za-z0-9]{30,}|BEGIN [A-Z ]*PRIVATE KEY|xox[abp]-[A-Za-z0-9-]{10,}|sk-[A-Za-z0-9]{32,}' -- . ':!composer.lock' ':!tests-e2e/package-lock.json' || true)
[ -n "$hits" ] && bad "credential-shaped strings" "$hits"

# 3. Local machine paths in anything that ships or is used by CI.
hits=$(git grep -InE '/home/[a-z]+|/Users/[A-Za-z]+|C:\\Users|/root/' -- wordpress-theme wordpress-plugin scripts tests-cli tests-e2e .github ':!scripts/audit-repo.sh' ':!tests-cli/package-install.sh' || true)
[ -n "$hits" ] && bad "local paths" "$hits"

# 4. Development fixtures and test credentials must not be inside the shipped packages.
hits=$(git grep -InE 'Dev Fixture|DEV FIXTURE|fx_(editor|officer|approver)|dev-only|NOBLEN_ALLOW_E2E_SEED' -- wordpress-theme "wordpress-plugin/noblen-corporate-core/includes" "wordpress-plugin/noblen-corporate-core/noblen-corporate-core.php" "wordpress-plugin/noblen-corporate-core/uninstall.php" || true)
[ -n "$hits" ] && bad "development data inside shipped code" "$hits"

# 5. The audit key must never be defined with a value in tracked non-test code.
hits=$(git grep -InE "define\( *'NOBLEN_AUDIT_KEYS'.*=> *'[^<'][^']{7,}'" -- . ':!tests-e2e' ':!scripts/ci-setup-wordpress.sh' ':!.github' ':!documentation/INSTALLATION.md' ':!wordpress-plugin/noblen-corporate-core/tests' || true)
[ -n "$hits" ] && bad "audit key defined in shipped code" "$hits"

[ "$fail" -eq 0 ] && echo "PASS: repository audit clean"
exit "$fail"

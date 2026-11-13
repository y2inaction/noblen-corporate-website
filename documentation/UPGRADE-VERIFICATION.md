# Upgrade verification (C26-003 to C26-006)

**Status: tooling is in the repository and tested. Criteria C26-003 to C26-006 are NOT satisfied and nothing here is accepted.** They need inputs that only people can supply (see "What is still needed"). This document follows the boundary recorded in [C26-M2D-BOUNDARY-REVIEW.md](C26-M2D-BOUNDARY-REVIEW.md). The frozen plans are unchanged.

## What exists

| Part | File | What it does |
|------|------|--------------|
| Manifest | `tests-cli/upgrade/manifest-db-1-to-2.json` | Declares every expected logical difference of the database upgrade from schema version 1 to 2: seven categories identical, the audit history append-only with two named entries, two option changes, one physical index. No wildcards. No preservation mappings are needed, because no value moves. |
| Fixture and comparison | `tests-cli/upgrade-comparison.php` | Builds a schema-version-1 **fixture** (posts, metadata, relationships, clearance, approvals, media rights, epochs, settings, a signed audit chain), exports a normalised logical dataset, runs the real migrator (it refuses to run without a confirmed backup), exports again, and classifies every difference against the manifest. It fails on an undeclared difference and on a declared one that never happened. It verifies the audit chain with the key before and after (C26-004). |
| Logical export | `tests-e2e/wp-env/probes/logical-export.php` | The shared normalised export, also used by the theme-switch tests. |
| Attestation checker | `scripts/upgrade-attestation.py` | `bind` writes the runtime binding (run, attempt, platform start time, commit, SHA-256 of the manifest and attestation actually loaded). `check` fails closed on the four C26-006 conditions: MISSING, INVALID, MISMATCHED, LATE. |
| Checker tests | `tests-cli/upgrade-attestation-test.py` | 41 checks on synthetic records. Eight mutants of the checker were each caught. |

## Evidence so far

- Comparison harness: 25 checks pass on a disposable local site; four mutant manifests (an undeclared audit entry, an undeclared option change, a declared change that never happens, a wildcard) each fail as intended.
- Checker: 41 checks pass; 8 of 8 mutants caught.
- Both run in CI (the checker in the static job, the comparison as the last step of the disposable-site browser job). Hosted results are recorded in [IMPLEMENTATION-STATUS.md](IMPLEMENTATION-STATUS.md) after the run.

## What this does not prove

- **Schema version 1 was never released.** The source is a fixture built by code. No tag, version or package stands in for an earlier release, and no document may say schema 1 shipped.
- The run is not the registered source and target comparison. It tests the tooling and the migration logic.
- The checker verifies form, binding and chronology. It cannot tell whether a reviewer is genuinely independent.

## What is still needed (people, not code)

1. **The owner registers the source and target** (the recommended registration is the schema-version-1 fixture as source and the release commit as target, recorded as fixed fields), or waits for a first tagged release.
2. **An independent reviewer** and an agreed approval route (a protected repository review on the manifest commit, or a separately retained signed record).
3. **The attestation**, written in the form checked by `scripts/upgrade-attestation.py check`, approved by that reviewer strictly before the run starts. A failed run needs a new attestation.
4. **An authorised hosted run** to supply the run ID, attempt and platform start time.

## Running it locally on a throwaway site

The comparison **deletes all Noblen data** on the site it runs on and refuses to start without `NOBLEN_UPGRADE_FIXTURE_RESET=1` or on a non-local host.

```bash
NOBLEN_UPGRADE_FIXTURE_RESET=1 NOBLEN_WP_PATH=/path/to/disposable/wordpress php tests-cli/upgrade-comparison.php
```

```bash
python3 -I tests-cli/upgrade-attestation-test.py
```

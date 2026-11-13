# Dependencies

## Runtime

WordPress 6.4 or newer and PHP 8.3 or newer. **Nothing else.** No Composer or npm package ships inside either package, and no shipped code requires another plugin. `scripts/audit-repo.sh` fails the build if shipped code declares or references the Classic Editor plugin.

## Development and CI test tooling (never shipped)

| Tool | Pin | Source |
|------|-----|--------|
| PHPCS, WPCS, PHPCompatibilityWP, WP-CLI | `composer.lock` | Packagist |
| Playwright 1.63.0, axe-core 4.10.2, wp-env 12.1.0 | `tests-e2e/package-lock.json` | npm registry |
| GitHub Actions | full commit SHAs with version comments in the workflows | GitHub |
| WordPress core for tests | 6.4.3, SHA-1 verified (PHPUnit and package jobs) or the `6.4.3` tag (wp-env) | wordpress.org, GitHub |
| MySQL service containers | `mysql:8.4` pinned by digest | Docker Hub |
| **Classic Editor plugin, Configuration A of criterion 21** | **1.7.0**, exact version, SHA-256 pinned and **verified**; C21-021 and C21-022 evidenced, awaiting owner acceptance; see below | **downloads.wordpress.org** |

## Classic Editor plugin (Configuration A of criterion 21)

**Decision record:** the owner approved the official WordPress.org Classic Editor plugin, pinned to an exact version, **as the intended test dependency for the development and CI test environment only.** The pin is **verified** (independent review of 2026-11-12, reference `NOBLEN-CORP-CE-INDEPENDENT-REVIEW-2026-11-12`; reviewed change `ec38d89`). Configuration A is implemented and **EVIDENCED — AWAITING OWNER ACCEPTANCE**. It is **not accepted**.

**Pin:** `tests-e2e/wp-env/classic-editor.pin.json`: slug `classic-editor`, version **1.7.0**, source `https://downloads.wordpress.org/plugin/classic-editor.1.7.0.zip` (exact version, never "latest"), `zip_sha256` `7cc7799b0b7d820fbb7528dbff8027c736e52513992416cb37279f44d3d9dd77`, `verification.state` **verified**.

**Why 1.7.0:** it is the stable tag in the plugin's official repository. It requires WordPress 4.9 or newer and PHP 5.2.4 or newer, so it is compatible with the pinned WordPress 6.4.3 and the test PHP 8.3 by header. "Tested up to 7.0" is advisory. The previous stable release is 1.6.7. Hosted runs 43286920565 (capture) and 43316091579 (strict) ran the Configuration A suite, 13 of 13, with WordPress 6.4.3.

**Provenance:** the per-file release reference is the WordPress.org SVN tag `tags/1.7.0` (six of six files identical to the package; SVN revision not recorded). The secondary source is the plugin's development repository, `github.com/WordPress/classic-editor`, branch `trunk`, commit `bb8d0cc7bfafa7458d90503c44e3c7723cb7324c` (2026-05-27), which has no tags; five of six files are identical and `classic-editor.php` differs by one comment line, with no executable difference.

**What remains limited:**
- **C21-021 and C21-022 are evidenced but not accepted.** Acceptance is the owner's decision, and criterion 21 still has the manual record C21-M01 pending.
- The whole-ZIP SHA-256 was observed stable on one day only. Whether WordPress.org serves identical bytes over time is not established.
- **Checksum clarification.** The pin's `official_checksums` URL is the WP-CLI form (`https://downloads.wordpress.org/plugin-checksums/<slug>/<version>.json`). The hosted runs reported WP-CLI per-file verification as successful, but the WP-CLI version and the exact URL those runs requested were not recorded, and no reviewer has independently fetched this URL, so its live reachability has not been independently confirmed. The `api.wordpress.org/plugins/checksums/1.0/` form returned HTTP 404 for the independent reviewer and is not used.
- The SVN tag and the download host are both operated by WordPress.org (one trust anchor).
- The local stand-in package tests (built from the GitHub source, official checks off) are engineering evidence about the test logic only. They are **not** release evidence.

**How the pin is protected** (`tests-cli/classic-editor-config-a.sh`, `scripts/check-pins.sh`):
- **Strict mode** (the default) refuses to run while the pin is unverified, so a green strict run is only possible against a verified pin.
- **Capture mode** (its own workflow, started by a push that changes the marker file; see the procedure) downloads and records the package SHA-256 and a source-parity report for human review, and reports C21-021 as not satisfied. It refuses to run once the pin has a hash.
- **No script ever writes the pin.** A hash mismatch fails the run and never updates anything.
- Download hardening: HTTPS only, TLS 1.2 or newer, at most 3 redirects, 5 MB cap, final-host check, zip integrity check, every entry confined to `classic-editor/` with no traversal or absolute paths, header version equal to the pin, then WP-CLI's checksum verification against WordPress.org's per-file checksums.
- `check-pins.sh` fails on an inconsistent pin, on a workflow that disagrees with the pin state (the Configuration A job must be non-blocking while unverified and blocking once verified), and if the installer is used outside its own job.
- Only the setting the plan requires is changed (default editor: block). Both plugin options are recorded exactly as read (C21-022).

**Verification procedure (completed for 1.7.0):** [CLASSIC-EDITOR-VERIFICATION-PROCEDURE.md](CLASSIC-EDITOR-VERIFICATION-PROCEDURE.md).

**Containment:** the plugin is installed into the disposable test site by its own CI job and removed afterwards, so the main WE job (Configuration B) never sees it. It is never in the repository or in either package, and `audit-repo.sh` and `check-pins.sh` fail if shipped code references it.

## Inputs that are still not pinned (known and accepted for now)

| Input | Why it is not pinned | Mitigation |
|-------|----------------------|------------|
| Container images chosen by wp-env (`wordpress:php8.3`, `wordpress:cli-php8.3`, `mariadb:lts`, `phpmyadmin`) | wp-env 12.1.0 chooses them and offers no option to pin them | The WE jobs record the digests actually used in the job summary |
| Debian and Alpine packages installed inside wp-env's image builds | Same | Same |
| Runner `mysql-client` package | Installed from the runner's package index | Used only to create test databases |
| Composer constraints in `composer.json` | Ranges are resolved by the committed `composer.lock` | The lockfile pins exact versions |
| Browser binaries | Downloaded by Playwright | The Playwright version is pinned exactly; browsers follow it |
| PyYAML (Python package; `python3-yaml` on Debian and Ubuntu) | Comes from the runner's system packages, or PyPI locally; no lockfile covers it and no installation step is added. Used by `scripts/check-pins.sh`, `scripts/build-traceability.py` and `tests-cli/acceptance-decisions-test.py`. **Minimum supported and tested version: 6.0.1, and it must be a stable release**, enforced by `scripts/build-traceability.py` (it refuses an older PyYAML, a missing one, and any pre-release, including a pre-release of a later version, for example 6.0.1.dev0, 6.0.1rc1 or 6.0.2.dev0; version text it does not recognise is refused too). 6.0.1 is the Ubuntu 24.04 system package; the tooling was also exercised with 6.0.3 | The static CI job runs `python3 scripts/build-traceability.py --check-dependencies` as its own step before `check-pins.sh`. It fails below 6.0.1 or on a pre-release and prints the version actually installed in that CI environment, which is recorded in the job log. Local install: `sudo apt-get install python3-yaml`, or `python3 -m pip install "PyYAML>=6.0.1"` |

Pinned by digest or exact version: GitHub Actions (full commit SHAs), MySQL service images (digest), PHPUnit (exact version), npm packages (exact, with a lockfile), WordPress core (exact version, SHA-1 verified), wp-env's core source (exact tag).

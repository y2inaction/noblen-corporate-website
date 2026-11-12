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
| **Classic Editor plugin, Configuration A of criterion 21** | **1.7.0**, exact version, **hash unverified**, see below | **downloads.wordpress.org** |

## Classic Editor plugin (Configuration A of criterion 21)

**Decision record:** the owner approved the official WordPress.org Classic Editor plugin, pinned to an exact version, **as the intended test dependency for the development and CI test environment only. Verification is pending.** Configuration A is implemented but **not accepted**. The pin is **unverified**.

**Pin:** `tests-e2e/wp-env/classic-editor.pin.json`: slug `classic-editor`, version **1.7.0**, source `https://downloads.wordpress.org/plugin/classic-editor.1.7.0.zip` (exact version, never "latest"), `zip_sha256` **blank on purpose**, `verification.state` **unverified**.

**Why 1.7.0:** it is the stable tag in the plugin's official repository. It requires WordPress 4.9 or newer and PHP 5.2.4 or newer, so it is compatible with the pinned WordPress 6.4.3 and the test PHP 8.3 by header. "Tested up to 7.0" is advisory. The previous stable release is 1.6.7. The first hosted run is what will actually demonstrate it works with 6.4.3.

**Provenance so far (candidate identification only):** the plugin's official development repository, `github.com/WordPress/classic-editor`, branch `trunk`, commit `bb8d0cc7bfafa7458d90503c44e3c7723cb7324c` (2026-05-27): readme `Stable tag: 1.7.0`, plugin header `Version: 1.7.0`, changelog entry for 1.7.0.

**What is NOT verified:**
- The official WordPress.org package has not been retrieved. The environment this was prepared in cannot reach any WordPress.org host.
- Its SHA-256 is unknown and is not pinned.
- The GitHub commit has not been tied to the published package (the repository has no tags).
- **C21-021 and C21-022 are unverified.**
- The local stand-in package tests (built from the GitHub source, official checks off) are useful engineering evidence about the test logic. They are **not** release evidence.

**How the pin is protected** (`tests-cli/classic-editor-config-a.sh`, `scripts/check-pins.sh`):
- **Strict mode** (the default) refuses to run while the pin is unverified, so a green strict run is only possible against a verified pin.
- **Capture mode** (manual trigger, first hosted run only) downloads and records the package SHA-256 and a source-parity report for human review, and reports C21-021 as not satisfied. It refuses to run once the pin has a hash.
- **No script ever writes the pin.** A hash mismatch fails the run and never updates anything.
- Download hardening: HTTPS only, TLS 1.2 or newer, at most 3 redirects, 5 MB cap, final-host check, zip integrity check, every entry confined to `classic-editor/` with no traversal or absolute paths, header version equal to the pin, then WP-CLI's checksum verification against WordPress.org's per-file checksums.
- `check-pins.sh` fails on an inconsistent pin, on a workflow that disagrees with the pin state (the Configuration A job must be non-blocking while unverified and blocking once verified), and if the installer is used outside its own job.
- Only the setting the plan requires is changed (default editor: block). Both plugin options are recorded exactly as read (C21-022).

**Steps required to verify it:** [CLASSIC-EDITOR-VERIFICATION-PROCEDURE.md](CLASSIC-EDITOR-VERIFICATION-PROCEDURE.md).

**Containment:** the plugin is installed into the disposable test site by its own CI job and removed afterwards, so the main WE job (Configuration B) never sees it. It is never in the repository or in either package, and `audit-repo.sh` and `check-pins.sh` fail if shipped code references it.

## Inputs that are still not pinned (known and accepted for now)

| Input | Why it is not pinned | Mitigation |
|-------|----------------------|------------|
| Container images chosen by wp-env (`wordpress:php8.3`, `wordpress:cli-php8.3`, `mariadb:lts`, `phpmyadmin`) | wp-env 12.1.0 chooses them and offers no option to pin them | The WE jobs record the digests actually used in the job summary |
| Debian and Alpine packages installed inside wp-env's image builds | Same | Same |
| Runner `mysql-client` package | Installed from the runner's package index | Used only to create test databases |
| Composer constraints in `composer.json` | Ranges are resolved by the committed `composer.lock` | The lockfile pins exact versions |
| Browser binaries | Downloaded by Playwright | The Playwright version is pinned exactly; browsers follow it |

Pinned by digest or exact version: GitHub Actions (full commit SHAs), MySQL service images (digest), PHPUnit (exact version), npm packages (exact, with a lockfile), WordPress core (exact version, SHA-1 verified), wp-env's core source (exact tag).

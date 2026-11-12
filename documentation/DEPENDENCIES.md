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
| **Classic Editor plugin, Configuration A of criterion 21** | **1.7.0**, exact, see below | **downloads.wordpress.org** |

## Classic Editor plugin (owner-approved for M2 Configuration A)

**Decision:** the owner approved the official WordPress.org Classic Editor plugin, pinned to an exact version, for the development and CI test environment only.

**Pin:** `tests-e2e/wp-env/classic-editor.pin.json`: slug `classic-editor`, version **1.7.0**, source `https://downloads.wordpress.org/plugin/classic-editor.1.7.0.zip` (exact version, never "latest").

**Why 1.7.0:** it is the stable tag in the plugin's official repository. It requires WordPress 4.9 or newer and PHP 5.2.4 or newer, so it is compatible with the pinned WordPress 6.4.3 and the test PHP 8.3. "Tested up to 7.0" is advisory and above the pinned version. The previous stable release is 1.6.7. Neither release is reported as dropping support for WordPress 6.4. The first hosted run is what will actually demonstrate it works with 6.4.3.

**Provenance recorded:**
- Identified from the plugin's official development repository, `github.com/WordPress/classic-editor`, branch `trunk`, commit `bb8d0cc7bfafa7458d90503c44e3c7723cb7324c` (2026-05-27): readme `Stable tag: 1.7.0`, plugin header `Version: 1.7.0`, changelog entry for 1.7.0.

**What is NOT yet verified (the owner asked for verification against WordPress.org):**
- The environment this was prepared in cannot reach any WordPress.org host (the proxy refuses them), so the WordPress.org package, its download address, its checksums and its release date were **not** checked here. The repository has no tags, so the release commit is not tied to the 1.7.0 package.
- The package SHA-256 is not known and is therefore not pinned yet.

**How verification happens in CI** (`tests-cli/classic-editor-config-a.sh`): download the exact URL; record the package SHA-256 in the job summary and compare it with `zip_sha256` once that is filled in; check the plugin header version equals the pin; install it; run `wp plugin verify-checksums` against WordPress.org's published per-file checksums for that version; check the installed version equals the pin (C21-021); set the plugin's default editor to the block editor and record the settings used (C21-022). Any failure fails the job. After the first green hosted run, copy the recorded SHA-256 into `zip_sha256` so later runs fail on any change.

**Containment:** installed into the disposable test site only, removed after the Configuration A tests (Configuration B then runs against a site without it), never in the repository or in either package.

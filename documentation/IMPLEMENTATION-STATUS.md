# M2 Implementation Status

Branch `feature/m2-implementation` (pushed; no pull request). Baseline `5739f75`. Specification: [M2-IMPLEMENTATION-PLAN.md](M2-IMPLEMENTATION-PLAN.md) Revision 5.1 and the frozen [CI-ACCEPTANCE-TEST-PLAN.md](CI-ACCEPTANCE-TEST-PLAN.md). Per-criterion detail: [acceptance-criteria-traceability.yml](acceptance-criteria-traceability.yml), generated from the actual test results.

Planned, Implemented, Executed and Accepted are four independent statuses. **Accepted is false for all 34 criteria.** Almost every execution is local. Six CI runs and one Classic Editor capture run exist on this branch (below). CI run 6 (`ec38d89`) is fully green, including the strict Configuration A job. **C21-021 and C21-022 are evidenced and awaiting owner acceptance.**

## Local test evidence

Environment: PHP 8.3.6, MariaDB 10.11, WordPress 6.4.3, WP-CLI 2.12.0, PHPUnit 9.6.17, Chromium with Playwright 1.63.0, axe-core 4.10.2, PHPCS with WPCS 3.x and PHPCompatibilityWP 2.1.

| Suite | Result |
|-------|--------|
| PHPUnit (real WordPress, one transaction per test, leak guard) | 146 tests, all pass |
| `tests-e2e/content-types.js` (real browser: all five types created and edited through the admin UI) | 41 of 41, four consecutive runs |
| `tests-e2e/classic-editor.js` Configuration B (real browser) | 13 of 13 |
| `tests-e2e/crawl.js` (real browser, placeholder-token crawl) | pass, 11 pages (sitemap not exercised) |
| `tests-cli/partial-data.sh` and `tests-cli/we-integration.sh` (**shim**, not wp-env) | 13 of 13 and 25 of 25 |
| PHPCS WordPress-Core/Extra + PHPCompatibilityWP | 0 errors, 0 warnings |
| PHPCompatibility static check, PHP 8.3-, 8.4-, 8.5- | clean (static only; PHP 8.4 and 8.5 were not executed) |
| `tests-cli/cli-integration.sh`, real WP-CLI (activation, write paths, deactivate, theme switch, uninstall retention and opt-in removal, error log canary) | 20 of 20 |
| `tests-cli/package-install.sh` (build ZIPs, install into a clean copy, activate, empty-site render) | 12 of 12 |
| `tests-e2e/admin.js` (real browser, editor, clearance officer, approver, administrator) | 21 of 21 |
| `tests-e2e/a11y.js` (axe-core WCAG 2.0/2.1 A and AA on 11 pages at 2 widths, token contrast, keyboard) | 43 of 43 |
| `tests-e2e/smoke.js` (360, 768, 1280 px, console, third-party requests, overflow, placeholder tokens) | pass |
| `scripts/check-no-third-party.sh` (static host scan) | pass |
| Markdown link check (CI logic, run locally) | pass |

The browser recipe was also run end to end on a freshly installed, freshly seeded database (as CI will), with the same results.

## First hosted CI run (commit `de59ce1`, run 43259095276): 6 of 11 jobs green

| Job | Result | Cause and correction |
|-----|--------|----------------------|
| Static analysis PHP 8.3 and 8.4, PHP 8.5 compatibility (informational), package build, non-WE parity browser suites (informational) | pass | |
| PHPUnit PHP 8.3 and 8.4 | fail | The CI setup never activated the plugin. `scripts/ci-setup-wordpress.sh` now activates the plugin and theme and asserts the state in a fresh process |
| Host WP-CLI and package install PHP 8.3 and 8.4 | fail | Three table counts called the bare `mysql` client over a socket. They now count tables through WP-CLI. The package step now runs even if the integration step fails |
| WE (wp-env) | fail | The Noblen theme was never active (wp-env activates plugins, not themes). Corrected with `we-site-state.sh` and a refusal in `partial-data.sh` |
| WE Configuration A (non-blocking) | fail, by design | Strict mode refused the unverified Classic Editor pin. The gate is preserved |

Also corrected: root-owned mapping directories blocking cleanup, an empty diagnostics artifact, and Node 20 action warnings.

**Local verification of the corrections** (labelled by what actually ran):
- *Real, local:* PHPUnit 146 of 146 on the pinned MySQL 8.4.13 image through the real setup script; host WP-CLI 20 of 20 and package install 12 of 12 on that image with the workflow's own configuration and no database client; actionlint, pin checks, PHPCS.
- *Real Apache and MariaDB 12.3 in containers, wp-env commands shimmed to a WP-CLI container:* the full WE sequence in CI order passed: partial data 13 of 13, integration 25 of 25, smoke, admin 21 of 21, content types 41 of 41, Configuration B 13 of 13, axe 43 of 43, and a crawl of 17 pages through the required core sitemap.
- *Not run anywhere:* wp-env itself since the corrections, PHP 8.4, and any hosted run of the corrections.

## Second and third hosted CI runs (runs 43277471319 on `997faaf`, 43279338951 on `c690450`)

| Job | Run 2 | Run 3 | Cause and correction |
|-----|-------|-------|----------------------|
| Static analysis, PHP 8.5 compatibility, package build, PHPUnit PHP 8.3 and 8.4 (MySQL 8.4), host WP-CLI and package install PHP 8.3, parity browser (informational) | pass | pass | |
| WE (wp-env, WordPress 6.4.3, PHP 8.3) | fail | **pass** | Run 2: the browser steps run from `tests-e2e`, and wp-env resolves its environment from the current directory only, so post locks could not be cleared and a lock dialog blocked the click. The lock-clearing command now runs from the workspace root. Run 3 is the first green WE job: admin, all five content types, Configuration B, axe and the core-sitemap crawl all ran and passed on hosted wp-env |
| Host WP-CLI and package install PHP 8.4 | fail | fail | Every functional check passes. The only entries in the PHP error log are one WordPress 6.4.3 core deprecation (see `UPSTREAM-PHP84-DEPRECATION.md`). Corrected locally, **not yet run on GitHub** |
| WE Configuration A (non-blocking) | fail, by design | fail, by design | Strict mode refuses the unverified Classic Editor pin. The gate is preserved; C21-021 and C21-022 are not accepted |

*Local verification of the PHP 8.4 correction* is listed in `UPSTREAM-PHP84-DEPRECATION.md`. It is not hosted evidence.

## Fourth to sixth CI runs and the Classic Editor capture run

| Run | Commit | Result |
|-----|--------|--------|
| CI run 4, 43283195271 | `606a4fa` | All jobs green except Configuration A (by design). The PHP 8.4 host job passed with the one exact WordPress 6.4.3 core deprecation set aside and printed (`UPSTREAM-PHP84-DEPRECATION.md`); PHP 8.3 stayed strict |
| CI run 5, 43286920209 | `cb211fc` | Green overall. Configuration A failed at the pin gate by design (pin unverified) |
| Classic Editor capture run 1, 43286920565 | `cb211fc` | Success. Official package SHA-256 `7cc7799b0b7d820fbb7528dbff8027c736e52513992416cb37279f44d3d9dd77`; WP-CLI checksums verified; Configuration A suite 13/13; record `CAPTURE`, C21-021 NOT SATISFIED; artifact 13974709232 |
| CI run 6, 43316091579 | `ec38d89` | **Fully green.** Strict Configuration A job passed (installer 14/14, suite 13/13); record artifact 13988531774 inspected by the owner: `STRICT`, C21-021 satisfied by this run. The wp-env job and all other jobs passed |

The Classic Editor pin was verified by the reviewed change `ec38d89` after the independent review of 2026-11-12 (reference `NOBLEN-CORP-CE-INDEPENDENT-REVIEW-2026-11-12`). Known remaining limits: whole-ZIP stability seen on one day only, SVN revision not recorded, checksums URL reachability not independently confirmed. The Configuration A job name still reads "non-blocking until the pin is verified" although the job is blocking (cosmetic; unchanged by decision).

## Owner acceptance decisions

Acceptance is recorded only in [owner-acceptance-decisions.yml](owner-acceptance-decisions.yml), a hand-maintained file edited on the owner's explicit instruction. `scripts/build-traceability.py` reads it and fails closed: a missing file, a malformed, duplicate or unknown entry, or an acceptance without evidence, an owner role label and a real past date stops generation. A missing entry is pending. A criterion is accepted only when every required sub-criterion is accepted; criterion 21 requires C21-021, C21-022 and C21-M01. Today all three entries are **pending**, so no criterion is accepted. The wording about C21-021 and C21-022 in the register summary is derived from the same decisions, independently for each sub-criterion (so a partial acceptance is stated as such), and is never typed by hand. No test result, generator run, developer action or technical approval can change an owner decision. The `evidence` strings are audit references typed by the owner: the generator does not verify that the referenced runs, artifacts or reviews exist. The generator writes its output atomically: a failed run leaves the previous register untouched (never deleted) and no temporary file behind. **A non-zero exit means no register was written and any existing register must not be treated as current**; every failure path says so, and the `# Generated:` line carries a UTC timestamp with seconds so a successful generation can be told apart from a stale file. Verification steps must stop at the first failed generation. The generator needs a stable PyYAML release of 6.0.1 or newer; pre-releases are refused ([DEPENDENCIES.md](DEPENDENCIES.md)). An unexpected internal error while reading or validating the decisions file also ends the run with a controlled diagnostic, a non-zero exit and the not-current notice, never silently. `tests-cli/acceptance-decisions-test.py` checks this behaviour and runs in the static CI job.

## CI workflow review

- Every action is pinned to a verified full commit SHA with a version comment (checkout v5.1.0, setup-node v5.0.0, upload-artifact v6.0.0, setup-php 2.40.0, all declaring the Node 24 runtime). Each SHA was fetched from its official repository and confirmed to be a commit. Workflow permissions are `contents: read`; checkout does not persist credentials; there are no secrets.
- Jobs: static analysis and compatibility (PHP 8.3 and 8.4), PHP 8.5 informational (`continue-on-error`; it must never be made a required status check in repository settings), PHPUnit (PHP 8.3 and 8.4, MySQL 8.4, WordPress 6.4.3 pinned and SHA-1 verified), WE on wp-env, host WP-CLI with package install and uninstall retention, an informational non-WE browser parity job, and package build.
- WE environment: the plan names `wp-env`. The `we` job now uses wp-env 12.1.0 with WordPress 6.4.3 and PHP 8.3, and runs the WP-CLI, browser, admin and accessibility suites inside it. **It has not been run**: this sandbox cannot build wp-env's Docker images. The previous PHP built-in-server jobs are kept as informational parity checks. See [CI-WE-ENVIRONMENT-NOTES.md](CI-WE-ENVIRONMENT-NOTES.md), which also holds a contingency deviation proposal, not accepted.
- Not implemented: the CI validator for the traceability register (rules 1-16), the E8, E9 and E11 evidence artefacts, and the C26-006 run-attempt evidence binding. The register is generated and valid YAML but is not yet checked by CI.
- Bugs found and fixed by reviewing the wiring before any push: the browser job's setup script targeted the wrong database, the WP-CLI job never installed the theme, and the WordPress download had no integrity check.

- Package ZIPs are now reproducible: file times are fixed to the commit time and entries are sorted, so the same source gives the same checksum (verified by rebuilding after touching a file).

## Criteria that were reported as gaps

| Criterion | State now |
|-----------|-----------|
| C06-002 (company, industry, service through the UI) | Implemented for all five types (also C06-001 product). Passes locally in a real browser. Not yet run in wp-env |
| C11-002 (partial data) | Implemented. Passes through a shim. Not yet run in wp-env |
| C12-001 (full-site crawl) | Implemented. Passes locally; sitemap seed needs Apache, so it is required only in the wp-env job |
| C21 Configuration A (Classic Editor plugin) | Classic Editor 1.7.0 is approved as the **intended test dependency** ([DEPENDENCIES.md](DEPENDENCIES.md)). The Configuration A tests and the installer are implemented, and the installer refuses to run in strict mode unless the pin is verified. **The pin is verified** (independent review 2026-11-12; strict hosted run 43316091579). **C21-021 and C21-022 are EVIDENCED — AWAITING OWNER ACCEPTANCE** and are not accepted; C21-M01 (manual editor check) is pending. Configuration A ran 13 of 13 against the real package in hosted runs 43286920565 and 43316091579. The local stand-in tests (13 of 13 plus a sensitivity check) are engineering evidence about the test logic only, not release evidence. Configuration B passed (13 of 13 locally; hosted wp-env step passed) |
| C26-001 (content hashing) | Implemented with a sensitivity check; C26-001 and C26-002 pass through a shim. C26-003 to C26-006 are not implemented |

## Test-harness finding

Clicking Save Draft while WordPress's permalink request is still in flight is silently ignored. This made the browser tests stall intermittently. The tests now wait for that request and re-click without reloading, logging every re-click; the content-types suite counts them and fails above two. It is WordPress behaviour, not a plugin defect.

## Defects found and fixed during verification

1. Clearance and media-rights forms were nested inside the post form (invalid HTML), so the browser dropped them. Now saved with the record, nonce and capability checked. Found only by the real browser test.
2. The media-rights file checksum was cached by size and time, so a same-size replacement kept a stale confirmation. Now always hashed.
3. The oEmbed filter returned 0, and `get_post(0)` falls back to the global `$post`. It now returns -1.
4. Test isolation: schema-change tests committed data past their rollback. A leak guard now fails any test that leaks, and the affected tests purge explicitly.
5. Theme: the detail column did not align with the header grid, the footer floated on short pages, search overflowed at 360 px, a missing favicon caused a 404 request, and core's emoji and REST discovery added third-party hosts. All fixed.
6. Theme had no activation guard. Added, with a test.

## Gaps against Revision 5.1 (actual, not fixed)

- Theme: no `screenshot.png`, no partnerships section, no section order or visibility settings, no breadcrumbs, no split token/base CSS, no reveal animation script, no self-hosted fonts (system fonts only). The plan lists these; none is invented content, but the homepage skeleton is simpler than the plan's.
- Plugin: the gallery field is a comma-separated list of media IDs, not a media picker. This is poor editor usability and should be replaced before launch.
- Plugin: no `languages/` folder; no separate Deactivator and Compat classes (their work is done in `Activator` and the main file).
- Contact and partnership forms are deferred to M7 by the approved plan. Only a mailto call to action exists.
- `get_posts()` and any query with `suppress_filters` bypass the read-time filter. Theme code must use the accessor. This is documented, not a defect in a listed interface.
- No persistent object-cache drop-in test (criterion 31). Core object cache only.
- Configuration A of criterion 21: owner-approved and pinned (1.7.0), verified by independent review and a strict hosted run. C21-021 and C21-022 await the owner's acceptance decision; C21-M01 is still pending.
- C26-003 to C26-006 are not implemented. They belong to M2-D, and the recommended boundary and prerequisites are in [C26-M2D-BOUNDARY-REVIEW.md](C26-M2D-BOUNDARY-REVIEW.md) (for owner review, not accepted).
- What has actually been exercised on GitHub: **PHP 8.4** runs in hosted CI (static analysis and compatibility, PHPUnit, and the host WP-CLI and package-install jobs; on the host jobs one exact WordPress 6.4.3 core deprecation is set aside and printed, see `UPSTREAM-PHP84-DEPRECATION.md`). **PHP 8.5** has only the informational compatibility job (lint and PHPCompatibility 8.5), which passed; it is not a strict gate and PHP 8.5 is not run against WordPress. **MySQL 8.4** (digest-pinned service container) is used by the hosted PHPUnit and host WP-CLI jobs; the wp-env job runs PHP 8.3 with MariaDB, and the local runs use MariaDB 10.11. **The CI workflow** has been executed on GitHub, and CI run 6 (`ec38d89`) was fully green.
- The WordPress download step cannot be exercised here (wordpress.org returns 403). Its checksum logic was tested against a local server for both a matching and a tampered checksum.
- Hazard: `tests-cli/cli-integration.sh` runs `wp plugin uninstall`, which deletes the plugin directory. It copies the plugin into the target WordPress and refuses to run against a symlink, but running it against a development site replaces a symlinked plugin folder.

## Manual records still required (none exist)

C01-M01, C02-M01, C03-M01, C06-M01, C10-M01, C12-M01, C13-M01, C14-M01 (the acceptance test for criterion 14), C15-M01, C17-M01 and C17-S01 (owner), C19-M01, C20-M01 and C20-M02, C25-M01, C27-M01, C31-M01 and C34-M01 (owner).

## Owner inputs

See [OWNER-CONTENT-REQUIREMENTS.md](OWNER-CONTENT-REQUIREMENTS.md).

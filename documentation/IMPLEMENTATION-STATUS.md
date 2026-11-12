# M2 Implementation Status

Branch `feature/m2-implementation` (local, **not pushed**). Baseline `5739f75`. Specification: [M2-IMPLEMENTATION-PLAN.md](M2-IMPLEMENTATION-PLAN.md) Revision 5.1 and the frozen [CI-ACCEPTANCE-TEST-PLAN.md](CI-ACCEPTANCE-TEST-PLAN.md). Per-criterion detail: [acceptance-criteria-traceability.yml](acceptance-criteria-traceability.yml), generated from the actual test results.

Planned, Implemented, Executed and Accepted are four independent statuses. **Accepted is false for all 34 criteria.** Every execution is local; no hosted CI run exists.

## Verified evidence (local environment)

Environment: PHP 8.3.6, MariaDB 10.11, WordPress 6.4.3, WP-CLI 2.12.0, PHPUnit 9.6.17, Chromium with Playwright 1.63.0, axe-core 4.10.2, PHPCS with WPCS 3.x and PHPCompatibilityWP 2.1.

| Suite | Result |
|-------|--------|
| PHPUnit (real WordPress, one transaction per test, leak guard) | 146 tests, all pass |
| PHPCS WordPress-Core/Extra + PHPCompatibilityWP | 0 errors, 0 warnings |
| PHPCompatibility static check, PHP 8.3-, 8.4-, 8.5- | clean (static only; PHP 8.4 and 8.5 were not executed) |
| `tests-cli/cli-integration.sh`, real WP-CLI (activation, write paths, deactivate, theme switch, uninstall retention and opt-in removal, error log canary) | 20 of 20 |
| `tests-cli/package-install.sh` (build ZIPs, install into a clean copy, activate, empty-site render) | 11 of 11 |
| `tests-e2e/admin.js` (real browser, editor, clearance officer, approver, administrator) | 21 of 21 |
| `tests-e2e/a11y.js` (axe-core WCAG 2.0/2.1 A and AA on 11 pages at 2 widths, token contrast, keyboard) | 43 of 43 |
| `tests-e2e/smoke.js` (360, 768, 1280 px, console, third-party requests, overflow, placeholder tokens) | pass |
| `scripts/check-no-third-party.sh` (static host scan) | pass |
| Markdown link check (CI logic, run locally) | pass |

The browser recipe was also run end to end on a freshly installed, freshly seeded database (as CI will), with the same results.

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
- Configuration A of criterion 21 (Classic Editor plugin installed) was not run: it is not an approved dependency.
- Not executed anywhere: PHP 8.4 or 8.5, MySQL 8.4, the CI workflow itself.
- The CI workflow downloads WordPress from wordpress.org, which this environment cannot reach, so its setup step is unverified here. Its other steps use the same scripts that ran locally.
- GitHub Actions are referenced by major version tag, not by commit SHA. Pinning by SHA is recommended before merge.
- Hazard: `tests-cli/cli-integration.sh` runs `wp plugin uninstall`, which deletes the plugin directory. It copies the plugin into the target WordPress and refuses to run against a symlink, but running it against a development site replaces a symlinked plugin folder.

## Manual records still required (none exist)

C01-M01, C02-M01, C03-M01, C06-M01, C10-M01, C12-M01, C13-M01, C14-M01 (the acceptance test for criterion 14), C15-M01, C17-M01 and C17-S01 (owner), C19-M01, C20-M01 and C20-M02, C25-M01, C27-M01, C31-M01 and C34-M01 (owner).

## Owner inputs

See [OWNER-CONTENT-REQUIREMENTS.md](OWNER-CONTENT-REQUIREMENTS.md).

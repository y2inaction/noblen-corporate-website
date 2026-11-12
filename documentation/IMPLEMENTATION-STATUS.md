# M2 Implementation Status

Branch `feature/m2-implementation` (local, **not pushed**). Baseline `5739f75`. Specification: [M2-IMPLEMENTATION-PLAN.md](M2-IMPLEMENTATION-PLAN.md) Revision 5.1 and the frozen [CI-ACCEPTANCE-TEST-PLAN.md](CI-ACCEPTANCE-TEST-PLAN.md).

The four coverage fields are independent. **Accepted is false for every criterion**: acceptance needs reviewed evidence, and no hosted CI run or manual review has taken place.

## What exists

| Component | State |
|-----------|-------|
| Core plugin `noblen-corporate-core` | Content model, structured fields, private clearance/audit storage with migrations, publication gate and Publisher, dependencies, withdrawal and cache epochs, public accessor, admin UI (fields, publication box, clearance box, media rights, settings, columns, notices), retention-by-default uninstall. Draft seeder **not written**. |
| Theme `noblen-corporate` | Header and navigation, home page, archives and details for the five types, posts, search, 404, footer and legal menus, blue design tokens, mobile menu, no third-party hosts. Sections hide when the plugin has no public data. |
| Tests | 58 PHPUnit tests against real WordPress. Front-end smoke script (Playwright, 3 widths). |
| CI | `plugin-tests.yml` (PHP 8.3/8.4, MySQL 8.4) and packaging written, **never run**. |

## Test evidence (executed locally only)

Environment: PHP 8.3.6, MariaDB 10.11, WordPress 6.4.3, PHPUnit 9.6.17. Result: **OK, 58 tests, 667 assertions.** Front-end smoke at 1440, 820 and 375 px: pass (status, no console errors, no third-party requests, no horizontal overflow, menu toggle and Escape).

This is not the CI environment the plan specifies (MySQL 8.4, PHP 8.4/8.5, WP-CLI, WP test library), so none of it counts as the specified evidence.

## Criteria coverage

| Criterion | Planned | Implemented | Executed | Accepted | Note |
|-----------|---------|-------------|----------|----------|------|
| 5, 7, 9, 10, 21 (plugin side), 24, 30 | Yes | Yes (automated, plugin side) | Locally, PHP 8.3/MariaDB only | No | C24 WP-CLI path is simulated |
| 23, 25, 26, 27, 28, 29, 31, 32, 34 | Yes | Partial | Locally, partial | No | Representative tests only. Per-component, per-trigger (T01-T09), per-interface and key-rotation matrices are incomplete |
| 2, 8, 22, 33 | Yes | Partial | Partial | No | Sentinel probes, activation guard and relationship-class tests still to write |
| 1, 3, 4, 6, 11, 12 | Yes | Theme and plugin exist | Smoke only | No | Not run via WP-CLI/CI as specified |
| 13, 18 | Yes | Smoke script covers widths, third-party requests | Locally | No | No screenshots archived as evidence, no static scan |
| 14, 15 | Yes | Menu keyboard test only | Partial | No | No axe run (not available offline), no contrast test, manual pass pending |
| 16 | Yes | PHP lint | Local lint only | No | PHPCS/WPCS/PHPCompatibility not installable here |
| 17 | Yes | No | No | No | Owner-executed local naming scan; never run by CI |
| 19 | Yes | Build script | ZIP build verified locally | No | Clean-install test not run |
| 20 | Yes | No | No | No | Link check and recovery rehearsal pending |

## Known gaps

- No push, so no hosted CI evidence. Pushing needs separate authorisation.
- Missing locally: WP-CLI, WPCS, PHPCompatibilityWP, the WordPress test library, MySQL 8.4, PHP 8.4/8.5, axe-core.
- Draft seeder (names-only drafts) not written.
- No logo, fonts, photography, or group-level content. The theme ships no invented content, so a fresh site shows only structure until the owner enters and approves content.
- The traceability YAML register required by the plan is not generated.
- Admin screens (meta boxes, workflow buttons) have no browser-driven tests.

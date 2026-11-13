# Remaining-work register

Established from the repository at commit `2901ceba929acb64a4c1a64f9e6ef49a58c10d00` (branch `feature/m2-implementation`, clean tree, local tip equal to remote tip), the frozen M2 plan (Rev 5.1) and the frozen CI acceptance plan. Hosted CI at that commit was green (CI run 14 and 15, Validate run 11). This register replaces no frozen document and records no owner acceptance. Status values: **open**, **in progress**, **done (evidence)**, **owner** (needs the owner or another human), **reserved** (separate approval gate).

## A. Theme (M2 section 8)

| ID | Requirement | Status |
|----|-------------|--------|
| T1 | Design tokens for colour, type scale, spacing, radii, shadows, breakpoints, motion (Rev 5.1 section 8). Only colour, radius and width existed. | done (tokens.css; hosted CI green at 663c356) |
| T2 | Header, navigation and footer polish; footer shows only owner-entered facts. | open |
| T3 | Breadcrumbs on archives and records. | done (hosted CI green at 663c356) |
| T4 | Homepage: group introduction, partnerships section (renders nothing without verified data), section order and visibility theme settings. | done in the theme: Customizer order and visibility for nine sections, tested by tests-cli/home-sections-test.php (15 checks, mutation-checked). Partnerships renders nothing until verified partner data exists: the plugin has no partner record type, and adding one changes the content model, which needs an owner decision. The theme reads the filter noblen_partnership_items. |
| T5 | Internal templates: record layout with facts panel, related items, gallery, empty and error states. | partly done: breadcrumbs, page headers, empty states, record facts panel (tests-cli/record-facts-test.php, 8 checks). Still open: richer record layouts are limited by the fields the plugin exposes. |
| T6 | `screenshot.png` for the theme. | done: 1200x900 screenshot.png rendered from the theme CSS with neutral structural labels only (no business claims); it is a design preview, replace it with a real capture once approved content exists |
| T7 | Self-hosted fonts. Blocked on a licence-checked font file supplied or approved by the owner; until then the system font stack is the documented, licence-free choice. | owner |
| T8 | Reveal animation script (optional, reduced-motion safe). | open |
| T9 | Approved logo. A brand sheet (logo lockups, colour variants, sector icons, values) was shared in chat as an image only. No logo file is in the repository, so none is installed and none is recreated. The owner must supply the master file (SVG preferred, or PNG with transparency) and confirm which lockup is the approved one. The theme already supports the WordPress custom logo, so it can be uploaded in the Customizer or committed as an asset. The sheet also shows a green accent and a gold edition. The approved palette (M2 section 8) has no gold, so the gold edition is not used. Any green accent needs an owner decision. | The logo can now be uploaded by an administrator (Site Identity, no code). The master logo file itself is still owner-supplied; the brand sheet was shared as an image only. |

## B. Plugin and integration

| ID | Requirement | Status |
|----|-------------|--------|
| P1 | Gallery field stores comma-separated media IDs; needs a media picker before launch. | done locally (tests-e2e/gallery.js, 9 checks: choose, dedupe, remove, no-JavaScript fallback). Hosted result pending. The local dev site is Debian-packaged WordPress and needed a symlink for underscore.min.js in the scratch site (not in the repo), so the local run is real-local evidence only. |
| P2 | `languages/` folder and text-domain loading. | done: languages/ folder, Domain Path header and load_plugin_textdomain. Administration text is English only in this release; wrapping strings for translation is not required by the plan and is not done. |
| P3 | Audit of input validation, output escaping, nonces, capabilities, SQL preparation and upload handling, with evidence. | reviewed and written up in SECURITY-REVIEW.md. No defect found; open items are host-level security headers, an independent dynamic scan, and an accepted dev-tooling advisory exception (npm audit: 10 moderate, 1 high, test tools only). |
| P4 | Contact forms are deferred to a later milestone by the approved plan (M7). The theme offers an email link only. | deferred by plan |
| P5 | Persistent object-cache behaviour (criterion 31) is tested only without a persistent cache. | open |

## B2. Quality, SEO and performance

| ID | Requirement | Status |
|----|-------------|--------|
| Q1 | SEO fundamentals and performance: meta description, Open Graph, Organization data from owner-entered settings only, archive heading order. Lighthouse 12.8.2 on the local dev site (home, archive, record; Chromium, local server, no compression): accessibility 100, best practices 100, SEO 100, performance 99 to 100, LCP 1.7 s, CLS 0, TBT 0 ms. Tests: tests-e2e/seo.js (51 checks), tests-cli/seo-test.php (12 checks). Real-local evidence on fixture content; re-measure on staging with real hosting and compression. | done locally; hosted result pending |

## B3. Configuration validation

| ID | Requirement | Status |
|----|-------------|--------|
| V1 | Operator-facing configuration validation in Tools, Site Health (audit key and chain, migration, debug display, HTTPS, permalinks, theme, separation of duties, uninstall data removal, search visibility). Read-only. tests-cli/site-health-test.php: 39 checks. Confirmed visible on the real Site Health screen locally. | done locally; hosted result pending |

## B4. Brand identity management (approved additional requirement)

| ID | Requirement | Status |
|----|-------------|--------|
| B4-1 | Logo management through Site Identity and the Media Library: upload, replace, remove; PNG, WebP and GIF with transparency; SVG for administrators, rebuilt from an allow-list; responsive heights and preserved aspect ratio; alternative text; wordmark fallback; optional dark-appearance logo. Nothing hard-coded. `tests-cli/svg-test.php` (56 checks, mutation-tested), `tests-e2e/brand-logo.js` (62 checks). | done locally; hosted result pending |
| B4-2 | Brand Identity in the Customizer: 18 colour controls (primary, secondary, accent, heading, text, background, surface, header, footer, links, buttons with hover and active states), WordPress colour pickers, live preview, Publish. `tests-e2e/brand-customizer.js` (51 checks). | done locally; hosted result pending |
| B4-3 | Logo-based palette suggestions: server-side extraction (GD for raster, text parsing for SVG), no external service; suggested palette is reviewed, adjustable, previewed, and applied only by explicit action; gold and yellow ignored by design. `tests-cli/palette-test.php` (28 checks including a 300-logo property test), plus the browser flow. Limits documented (complex SVG gradients, very large images, undecodable formats). | done locally; hosted result pending |
| B4-4 | Central tokens: component CSS uses only custom properties (a test enforces no colour literals); semantic status colours fixed; derived tints via `color-mix()` with fallbacks. Propagation verified for header, mobile menu, home sections, buttons, links, cards, forms, archives, records, footer. | done locally |
| B4-5 | Light and dark appearance (derived from the brand and contrast-checked), saved presets (up to 12), built-in presets, restore defaults, contrast warnings. `tests-e2e/brand-parity.js` (40 checks: PHP and JavaScript produce identical output; every preset passes contrast in light and dark). | done locally; hosted result pending |
| B4-6 | Persistence and robustness: settings survive a theme switch; nothing configured is graceful; hostile stored values never reach the CSS. `tests-cli/brand-persistence-test.php` (23 checks). | done locally; hosted result pending |
| B4-7 | Manual verification by a person (C13-M01, C15-M01 updated in the acceptance guide): real devices, screen reader, and a designer's judgement of the suggestions. Cross-browser: only Chromium was available. | owner or reviewer |
| B4-8 | Admin documentation: CUSTOMIZATION.md rewritten; ADMINISTRATOR-GUIDE.md, OWNER-CONTENT-REQUIREMENTS.md, SECURITY-REVIEW.md and the release report updated. | done |

## C. M2-D, packaging and release

| ID | Requirement | Status |
|----|-------------|--------|
| D1 | C26-003 to C26-006 (upgrade comparison, audit chain before and after, declared transformations, attestation and runtime binding). Boundary review recorded; prerequisites need an independent reviewer, a registered source and target, and an authorised hosted run. Part A (repository-resident tooling) is implementable. | tooling done and tested (UPGRADE-VERIFICATION.md): manifest, code-built schema-1 fixture, comparison harness (25 checks, 4 mutants caught), attestation checker (41 checks, 8 mutants caught). The criteria are NOT satisfied: they still need the registered source/target, an independent reviewer and approved attestation, and an authorised hosted run. |
| D2 | Release packaging reproducibility, checksums, fresh-install and package-install tests. | partly done; extend |
| D3 | Upgrade test from an earlier real release. None exists; a fixture must be described as a fixture. | owner |
| D4 | Container images used by the browser tests are not pinned (wp-env 12.1.0 offers no pinning). Record digests and document the exception. | open |
| D5 | Deployment, customisation, administrator, troubleshooting and rollback documents. `DEPLOYMENT.md` and `CUSTOMIZATION.md` are stubs. | written: DEPLOYMENT.md, CUSTOMIZATION.md, ADMINISTRATOR-GUIDE.md (roles, workflow, troubleshooting, security, maintenance), and backup, restore and rollback sections in RECOVERY.md. The recovery procedure is documented but not rehearsed (C20-M02, owner or reviewer). |
| D6 | Fresh install on a real WordPress.org download is unverified from this sandbox (the host is blocked). The hosted CI downloads it. | evidence from hosted CI only |

## D. Acceptance and owner inputs

| ID | Requirement | Status |
|----|-------------|--------|
| A1 | 17 manual acceptance records: C01-M01, C02-M01, C03-M01, C06-M01, C10-M01, C12-M01, C13-M01, C14-M01, C15-M01, C17-M01, C17-S01 (owner), C19-M01, C20-M01, C20-M02, C25-M01, C27-M01, C31-M01, C34-M01 (owner). None exist and none may be created by the implementer. Human-verification instructions are to be prepared. | instructions prepared in ACCEPTANCE-RECORDS-GUIDE.md (what to check, evidence to keep, blank template). No record exists and none was created. Each must be produced by the named reviewer or owner. |
| A2 | C21-021, C21-022 and C21-M01 stay pending until the owner decides. `accepted_count` stays 0. | owner |
| A3 | Owner naming scan (`scripts/naming-scan.sh`) to be run by the owner. | owner |
| A4 | Owner content: see `OWNER-CONTENT-REQUIREMENTS.md`. No corporate claims, people, addresses or contact details are invented. | owner |

## E. Reserved gates (not executed)

Merge of PR #2, production deployment, DNS, production database changes, destructive production operations, recording owner acceptance, and paid infrastructure.

## F. Known limitations carried forward

- The Configuration A job name still says "non-blocking until the pin is verified". It is cosmetic and the name is intentionally unchanged.
- CI runs twice per push on the PR branch (push and pull_request). No concurrency group; accepted.
- Hosted JUnit artifacts could not be downloaded from this sandbox; per-criterion evidence comes from the job logs.

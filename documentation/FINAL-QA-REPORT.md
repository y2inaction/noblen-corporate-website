# Final quality report

Tip assessed: `7228471ebb22338bcab2e1c6ebaf1376b75dbbaa` on `feature/m2-implementation` (local and remote identical when checked; base `main` `da432a6531f2239cc2d04437af5d002a3c38c95a`). Application code is unchanged since `28bbdbd`; later commits are documentation, tests of documentation, and register regeneration. Evidence is separated into hosted, local, manual and unverified. Nothing here is acceptance.

## 1. Hosted evidence (GitHub Actions, read on 2026-11-13)

For the tip: **CI run 70** (push, id 43471253584), **CI run 71** (pull request event, id 43471265831) and **Validate run 39** (id 43471265837) all completed with success. Run 70 has 11 of 11 jobs, each with every step concluded `success`; no job or step was skipped, neutral or cancelled.

| Job (run 70) | Result | Notable steps confirmed executed |
|--------------|--------|----------------------------------|
| Static analysis, PHP 8.3 and 8.4 | success | lint, PHPCS, PHP compatibility, no third-party hosts, repository audit, pins, owner-decision generator tests, PHPUnit report tests, attestation tests, brand parity, SVG, palette, reviewer designation and manual records, **register and records against the two frozen plans** |
| PHPUnit, PHP 8.3 and 8.4 (MySQL 8.4) | success | PHPUnit, per-criterion count step (fails if output missing, malformed or not clean) |
| Host WP-CLI, package install and uninstall retention, PHP 8.3 and 8.4 | success | WP-CLI integration, package build and clean install, **key custody rehearsal** |
| WE: wp-env (WordPress 6.4.3, PHP 8.3) | success | pristine and partial data, WP-CLI and HTTP integration, smoke, admin, content types, gallery, Classic Editor Configuration B, accessibility, SEO, brand Customizer, logo, crawl including sitemap |
| Non-WE parity, browser suites on the PHP built-in server | success (informational by design) | the same suites plus operator WP-CLI commands, upgrade notice, upgrade comparison |
| WE Configuration A, pinned Classic Editor | success | pin check C21-021 and C21-022, Configuration A suite (the job name says "non-blocking until the pin is verified"; it is cosmetic and left unchanged by decision) |
| Package build, PHP 8.5 compatibility (informational) | success | |

Earlier hosted history for the same code is in [RELEASE-READINESS-REPORT.md](RELEASE-READINESS-REPORT.md): runs 52 to 71 and Validate 30 to 39, all success. Hosted evidence is **Chromium only** and uses WordPress 6.4.3 and PHP 8.3 and 8.4.

## 2. Local evidence (this session, disposable site, PHP 8.3.6, MariaDB 10.11, WordPress 6.4.3, Chromium)

| Suite | Result |
|-------|--------|
| PHP lint, PHPCS WordPress standards, PHPCompatibility 8.3 and later, no third-party hosts, repository audit, dependency pins, actionlint | all pass |
| PHPUnit | 146 tests, **1,722 assertions** on a fresh site (matches the hosted baseline); per-criterion counts identical to the committed register |
| Owner-decision generator tests / PHPUnit report tests / attestation checker | 241 / 41 / 41 |
| Reviewer designation and records / register against the plans | 42 + 270 / 144 |
| PHP error-log check tests | 32 |
| Brand parity / SVG sanitiser / palette | 40 / 56 / 28 |
| Operator WP-CLI / upgrade notice / brand persistence / Site Health / SEO helpers / home sections / record facts | 30 / 9 / 23 / 39 / 12 / 15 / 8 |
| **Key custody rehearsal** (disposable site) | 69 of 69 |
| Browser, Chromium: smoke at 360, 768, 1280 px | PASS |
| Accessibility (axe, keyboard, contrast) | 165 of 165 |
| SEO and document structure / crawl | 51 of 51 / PASS (11 pages, warnings expected because the built-in server cannot serve sitemaps) |
| Admin workflow / five content types / gallery | 21 / 41 / 9 |
| Brand Identity in the Customizer / logo management | 51 / 62 |

Not run locally this session: Classic Editor suites (the WordPress.org plugin download is blocked from the sandbox; hosted CI ran them), wp-env containers, host WP-CLI integration and package install (hosted ran them), the upgrade comparison. A local seeded site shows more assertions than the hosted baseline; the fresh-site figure (1,722) is the comparable one. One initial local browser failure was a missing test account in my scratch site (not a defect) and passed once created.

Lighthouse (local, earlier, fixture content, uncompressed): accessibility 100, best practices 100, SEO 100, performance 98 to 99, LCP 1.7 to 1.9 s, CLS 0, TBT 0 ms. Not re-measured this session.

## 3. Manual evidence

**None.** All 17 manual records are PENDING and none has been performed or signed. No human has used a screen reader, tested on real devices or other browsers, rehearsed a restore or a key recovery, or reviewed the audit trail.

## 4. Unverified claims and gaps

* Firefox, WebKit, real devices and screen readers (R-05).
* Behaviour on a real host: compression, caching, CDN purge, security headers, persistent object cache (R-04, R-14, R-15).
* An upgrade from a real earlier release (R-07); only a code-built fixture was used.
* Independent security assessment (R-01).
* Hosted artifacts (JUnit, rehearsal report) were not downloaded because artifact blobs need sign-in and are blocked from the sandbox; counts come from step lists and logs.
* The reviewer's independence, qualification and receipt of the pack.

## 5. Quality determination

The automated suite is green, reproducible locally and hosted, and not neutralised: the register generator and per-criterion counter fail closed, and the three mutation-tested suites (SVG, key rehearsal, records) caught their mutants. That is **necessary but not sufficient** for release: see [RELEASE-READINESS-REPORT.md](RELEASE-READINESS-REPORT.md) for the determination.

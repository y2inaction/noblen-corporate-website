# Release readiness report

**Classification: NOT READY for production.** The technically achievable work is largely complete and verified, including the brand and logo management system. Required inputs, human acceptance records and the unmet criteria C26-003 to C26-006 still stand between this branch and a release. Nothing here records owner acceptance, authorises a merge, or authorises a deployment.

Code evidence commit: `a2bdb1fe6188159458effeb0a018924ac49bd83e` on `feature/m2-implementation` (base `main` at `da432a6531f2239cc2d04437af5d002a3c38c95a`). Pull request 2 stays an open draft. This report is a documentation-only commit on top of that commit.

## 1. Requirements: completed and remaining

The authoritative list is [REMAINING-WORK-REGISTER.md](REMAINING-WORK-REGISTER.md).

| Area | Completed | Remaining |
|------|-----------|-----------|
| Theme | Design tokens, rebuilt components, page headers, breadcrumbs, empty and error states, record facts panel, home section order and visibility settings, partnerships section, screenshot, SEO tags | Approved logo file (owner), self-hosted fonts (owner licence), optional reveal animation not built |
| **Brand and logo management** (approved additional requirement) | Logo upload, replace and remove through Site Identity and the Media Library; safe SVG; responsive sizes; alt text; wordmark fallback; dark-appearance logo. Brand Identity in the Customizer with 18 colour controls, live preview, light and dark appearance, built-in and saved presets, restore defaults, contrast warnings, and optional logo-based palette suggestions. Central tokens, so one change reaches every template | Human review of the suggestions' taste and on real devices; Firefox and WebKit untested |
| Plugin | Gallery media picker with fallback, Site Health configuration validation, languages folder | Partner record type (owner decision) |
| M2-D | Upgrade manifest, code-built schema-1 fixture and comparison, attestation and binding checker | C26-003 to C26-006 not satisfied: needs registered source and target, independent reviewer, approved attestation, authorised hosted run |
| Documentation | Deployment, customization (rewritten for brand identity), administrator guide, acceptance-records guide, upgrade verification, security review, backup and rollback, owner checklist | Recovery not rehearsed (owner or reviewer) |
| Acceptance | Instructions for all 17 manual records, updated for the brand work | None of the 17 exists; C21-021, C21-022, C21-M01 pending; `accepted_count` is 0 |

## 2. Theme and brand quality

Reviewed by rendering at 360, 768 and 1280 pixels, in light and dark appearance and with different presets, and by Lighthouse on the home page, an archive and a record (re-measured after the brand work: accessibility 100, best practices 100, SEO 100, performance 98 to 99, LCP 1.7 to 1.9 s, CLS 0, TBT 0 ms; these are local measurements on fixture content over an uncompressed local server). The default identity is the approved direction: deep navy and electric blue dominant, a restrained green accent, white and light grey; gold and yellow are not used and are deliberately ignored by the logo analysis. The default accent changed from teal to a restrained green when the brand direction was clarified; a "teal accent" preset is provided. The site still cannot look like a finished corporate site until the approved logo and content exist.

How one change propagates: every colour is a CSS custom property printed from the saved settings; component CSS contains no colour literals (a test fails if one appears); a browser test publishes a distinct palette and verifies it on the home page, archives, a record, a group page, search, the 404 page and a blog post. Heading, footer and hover colours are independent controls, so changing the primary colour does not move them; presets and logo suggestions set everything together.

## 3. Plugin and theme integration

PHPUnit passes; the theme reads data only through the plugin's public accessor; a section with no data renders nothing; the partnerships section has no data source in this release. The theme and plugin install independently (package tests), and the brand settings survive a switch to another theme and back (tested). Cross-browser: only Chromium was available.

## 4. Security findings

See [SECURITY-REVIEW.md](SECURITY-REVIEW.md), updated for this work. New in this phase: SVG uploads are allowed for administrators only and rebuilt from an allow-list (56 hostile-input checks, mutation-tested, plus browser uploads through the REST API and the admin uploader; one design gap, uploads through routes with their own prefilter, was found while writing the tests and closed with a safety net on attachment creation); three administration-only AJAX actions each check a nonce and a capability; stored colour values cannot inject CSS. No injection, authorisation, XSS or CSRF defect was found. Open items: XML-RPC and import upload routes are covered by design but not browser-tested, an inline style block needs a CSP that allows inline styles, host-level security headers, an independent dynamic scan, and an accepted dev-tooling advisory exception (`npm audit`: 10 moderate and 1 high, test toolchain only).

## 5. Accessibility

`tests-e2e/a11y.js` passes 165 checks (axe on WCAG A and AA, keyboard behaviour, and contrast for every built-in preset in light and dark). The brand browser tests run axe on the real rendered home page for four preset and appearance combinations (no contrast violations), and check keyboard operation of the Customizer tools. The Customizer shows contrast warnings (4.5 to 1 for text, 3 to 1 for the focus ring) that advise but never block. The manual keyboard and screen-reader passes (C14-M01, C15-M01) are not done.

## 6. Performance

Lighthouse figures above. The brand tokens add about 1.3 KB of inline CSS (no extra request); there are no web fonts and no third-party requests. Compression, caching and a CDN are hosting decisions.

## 7. Automated and manual testing evidence

| Suite | Result | Kind |
|-------|--------|------|
| PHPUnit | 146 tests; **1,722 assertions on the hosted fresh site** (verified for earlier commits; seeded local dev sites show 2,742 to 3,206 and are not the baseline) | hosted and real-local |
| Brand colour parity (PHP and JavaScript identical on 160 palettes; presets pass contrast; no colour literals) | 40 | local and hosted (static job) |
| SVG sanitiser | 56 (mutation-tested) | local and hosted |
| Logo palette analysis | 28, including a 300-logo property test over four seeds | local and hosted |
| Brand persistence and bad stored values | 23 | local and hosted |
| Brand Identity in the Customizer (browser) | 51, repeated runs stable | local; hosted green for the commit before the final test additions |
| Logo management and suggestions (browser) | 62, repeated runs stable | local and hosted |
| Home sections, record facts, SEO helpers, SEO structure, Site Health, gallery, upgrade comparison, attestation checker | as listed in the register | local and hosted |
| Accessibility, smoke, crawl, admin, content types | pass | local and hosted |
| Manual acceptance records | none exist | owner or reviewer |

## 8. CI results

Hosted CI was verified green (all 11 jobs) for `663c356`, `f2fe4de`, `a71cbd4`, `7146586`, `06caca5`, `c1ae25a`, `c9dbf7a`, `3289081`, `6f97f56`, `d8fc8a5`, `d2ae0a1`, `eeb4738`, `63586a7` and **`c0823a2`**, the first commit to run the Customizer and logo browser suites on GitHub (in the wp-env job they ran for about 15 and 42 seconds and passed). Runs for `0a9291b` (documentation) and `a2bdb1f` (extra brand tests) were still in progress when this was written; confirm them, and the run for the commit containing this report, on the pull request. The Configuration A job name is unchanged by decision.

## 9. Acceptance records awaiting humans

C01-M01, C02-M01, C03-M01, C06-M01, C10-M01, C12-M01, C13-M01, C14-M01, C15-M01, C17-M01 (owner), C17-S01 (owner), C19-M01, C20-M01, C20-M02, C25-M01, C27-M01, C31-M01, C34-M01 (owner). Instructions: [ACCEPTANCE-RECORDS-GUIDE.md](ACCEPTANCE-RECORDS-GUIDE.md). Owner decisions: C21-021, C21-022, C21-M01.

## 10. Owner-provided content and decisions outstanding

See [OWNER-CONTENT-REQUIREMENTS.md](OWNER-CONTENT-REQUIREMENTS.md), items 1 to 23. Changed by the brand work: the **master logo file** can now simply be uploaded by an administrator in Site Identity (no code), and the **default colours** (navy and blue dominant, restrained green accent) are for the owner to confirm. Still the owner's: verified content for every section, contact details, legal pages, the audit key, the naming scan, named reviewers, hosting and domain choices, the sector icons and values, the partner record type, and the C26 registration.

## 11. Production deployment prerequisites

[DEPLOYMENT.md](DEPLOYMENT.md): green CI on the exact release commit, owner acceptance, naming scan, complete content, a tested backup, built packages checked against checksums, the audit key in custody, HTTPS, host security headers (and a Content-Security-Policy that allows the inline brand style block).

## 12. Rollback and recovery readiness

Documented in [RECOVERY.md](RECOVERY.md) (backup, restore order, rollback table) and wired to a Site Health check. Brand settings live in the database and survive theme file replacement; restoring a backup restores them. Not rehearsed by a person, so not proven.

## 13. Reserved gates (not executed)

Merge of pull request 2, production deployment, DNS, production database changes, destructive production operations, recording owner acceptance, and paid infrastructure.

## 14. Why NOT READY

1. Criteria C26-003 to C26-006 are not satisfied.
2. The 17 manual records and the three owner decisions do not exist.
3. There is no approved logo file and no approved content; the site shows structure only.
4. Recovery has not been rehearsed and no independent security scan has been run.

It would become CONDITIONALLY READY when items 1 and 2 are closed by the people who must close them and CI is green on the release commit; PRODUCTION READY needs items 3 and 4 as well and the separate production approvals.

# Release readiness report

**Classification: NOT READY for production.** The technically achievable work is largely complete and verified, but required inputs, human acceptance records and the unmet criteria C26-003 to C26-006 stand between this branch and a release. Details and evidence follow. Nothing here records owner acceptance, authorises a merge, or authorises a deployment.

Evidence commit: `c97c2bc0ca4453f0acc7ea9efc1e25c2141ec28b` on `feature/m2-implementation` (base `main` at `da432a6531f2239cc2d04437af5d002a3c38c95a`). Pull request 2 stays an open draft. This report is a documentation-only commit on top of that evidence commit.

## 1. Requirements: completed and remaining

The authoritative list is [REMAINING-WORK-REGISTER.md](REMAINING-WORK-REGISTER.md).

| Area | Completed in this phase | Remaining |
|------|--------------------------|-----------|
| Theme | Design tokens, rebuilt components, page headers, breadcrumbs, empty and error states, polished header and footer, record facts panel, home section order and visibility settings (Customizer), partnerships section, screenshot, SEO tags | Approved logo (owner), self-hosted fonts (owner licence), optional reveal animation not built |
| Plugin | Gallery media picker with fallback, Site Health configuration validation, languages folder | Partner record type (owner decision) |
| M2-D | Upgrade manifest, code-built schema-1 fixture and comparison, attestation and binding checker | C26-003 to C26-006 not satisfied: needs registered source and target, independent reviewer, approved attestation, authorised hosted run |
| Documentation | Deployment, customization, administrator guide, acceptance-records guide, upgrade verification, security review, backup and rollback, owner checklist | Recovery not rehearsed (owner or reviewer) |
| Acceptance | Instructions for all 17 manual records | None of the 17 exists; C21-021, C21-022, C21-M01 pending; `accepted_count` is 0 |

## 2. Theme quality

Reviewed by rendering at 360, 768 and 1280 pixels, plus Lighthouse on the home page, an archive and a record: accessibility 98 to 100 before the heading fix and 100 after, best practices 100, SEO 90 before the description fix and 100 after, performance 99 to 100, LCP 1.7 s, CLS 0, TBT 0 ms. The visual identity follows the approved navy, blue and teal palette; gold and yellow are not used; the green swoosh is not used in the interface. These are real-local measurements on fixture content over an uncompressed local server and are not production performance figures. The site cannot yet look like a finished corporate site because it has no approved logo and no approved content.

## 3. Plugin and theme integration

PHPUnit passes; the theme reads data only through the plugin's public accessor; a section with no data renders nothing; the partnerships section has no data source in this release. The theme and plugin install independently (package tests). Cross-browser: only Chromium was available, so Firefox and WebKit are not tested.

## 4. Security findings

See [SECURITY-REVIEW.md](SECURITY-REVIEW.md). No injection, authorisation, XSS or CSRF defect was found in the reviewed code. Open items: HTTP security headers (host level), an independent dynamic scan, and an accepted dev-tooling advisory exception (`npm audit`: 10 moderate and 1 high, all in the test toolchain, none shipped).

## 5. Accessibility

`tests-e2e/a11y.js` passes 43 checks (axe on WCAG A and AA, token contrast, keyboard behaviour). The manual keyboard and screen-reader passes (C14-M01, C15-M01) are not done.

## 6. Performance

Lighthouse figures above. Page weight is dominated by the HTML and two small stylesheets with no web fonts and no third-party requests. Compression, caching and a CDN are hosting decisions.

## 7. Automated and manual testing evidence

| Suite | Result | Kind |
|-------|--------|------|
| PHPUnit | 146 tests; **1,722 assertions on the hosted fresh site**; 2,742 to 3,102 on the seeded local dev site (it varies with seeded data and is not the hosted baseline) | hosted and real-local |
| Home sections | 15 checks | local and hosted |
| Record facts | 8 | local and hosted |
| SEO helpers and SEO structure | 12 and 51 | local and hosted |
| Site Health rules | 39 | local; hosted run pending |
| Gallery picker (browser) | 9 | local and hosted |
| Upgrade comparison on the fixture | 25; four mutant manifests each fail | local and hosted |
| Attestation checker | 41; eight mutants caught | local and hosted |
| Accessibility, smoke, crawl, admin, content types | pass | local and hosted |
| Manual acceptance records | none exist | owner or reviewer |

## 8. CI results

Hosted CI was verified green (all 11 jobs) for `663c356` (runs 16 and 17), `f2fe4de`, `a71cbd4`, `7146586`, `06caca5`, `c1ae25a`, `c9dbf7a`, `3289081` and `6f97f56`. For `d8fc8a5` (run 34) every job passed; the platform had not finalised the run when this was written. Runs for `47dad7f`, `881653d` and `c97c2bc` were in progress. The state of those runs, and of the run for the commit containing this report, must be confirmed on the pull request before relying on this report. The Configuration A job name is unchanged by decision.

## 9. Acceptance records awaiting humans

C01-M01, C02-M01, C03-M01, C06-M01, C10-M01, C12-M01, C13-M01, C14-M01, C15-M01, C17-M01 (owner), C17-S01 (owner), C19-M01, C20-M01, C20-M02, C25-M01, C27-M01, C31-M01, C34-M01 (owner). Instructions: [ACCEPTANCE-RECORDS-GUIDE.md](ACCEPTANCE-RECORDS-GUIDE.md). Owner decisions: C21-021, C21-022, C21-M01.

## 10. Owner-provided content and decisions outstanding

See [OWNER-CONTENT-REQUIREMENTS.md](OWNER-CONTENT-REQUIREMENTS.md), items 1 to 23: master logo file, verified content for every section, contact details, legal pages, audit key, naming-scan run, named reviewers, hosting and domain choices, decisions on the green accent, sector icons, values, partner record type, and the C26 registration.

## 11. Production deployment prerequisites

[DEPLOYMENT.md](DEPLOYMENT.md): green CI on the exact release commit, owner acceptance, naming scan, complete content, a tested backup, built packages checked against checksums, the audit key in custody, HTTPS, host security headers.

## 12. Rollback and recovery readiness

Documented in [RECOVERY.md](RECOVERY.md) (backup, restore order, rollback table) and wired to a Site Health check. Not rehearsed by a person, so not proven.

## 13. Reserved gates (not executed)

Merge of pull request 2, production deployment, DNS, production database changes, destructive production operations, recording owner acceptance, and paid infrastructure.

## 14. Why NOT READY

1. Criteria C26-003 to C26-006 are not satisfied.
2. The 17 manual records and the three owner decisions do not exist.
3. There is no approved logo and no approved content; the site shows structure only.
4. Recovery has not been rehearsed and no independent security scan has been run.

It would become CONDITIONALLY READY when items 1 and 2 are closed by the people who must close them and CI is green on the release commit; PRODUCTION READY needs items 3 and 4 as well and the separate production approvals.

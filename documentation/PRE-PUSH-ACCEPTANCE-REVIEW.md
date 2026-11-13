# Pre-Push Acceptance Review

Branch `feature/m2-implementation`, local only. Baseline `5739f75`. The frozen M2 plan and CI plan are unchanged. **Nothing is accepted: 0 of 34 criteria.** This review covers what the evidence does and does not support.

## 1. Register summary (from [acceptance-criteria-traceability.yml](acceptance-criteria-traceability.yml))

| Implemented / Executed (local only) | Criteria |
|---|---|
| automated_complete / local_full | 12 |
| automated_complete / partial | 6 |
| partial / none | 2 |
| partial / partial | 14 |

Planned is true for all 34. **Executed means executed locally**, with one exception: the first hosted run (commit `de59ce1`) gave hosted evidence for lint, PHPCS and compatibility on PHP 8.3 and 8.4, the PHP 8.5 informational check, and package building. Everything else in that run failed for developer-owned reasons that are corrected locally ([IMPLEMENTATION-STATUS.md](IMPLEMENTATION-STATUS.md)) but not yet re-run. wp-env, PHPUnit on MySQL 8.4 and the host WP-CLI jobs have no passing hosted result yet.

## 2. Classic Editor (criterion 21)

- Classic Editor 1.7.0 is the owner-approved **intended** test dependency, **verification pending**.
- The pin is **unverified**: no WordPress.org package, blank SHA-256, no tie between the GitHub commit and the published package.
- **C21-021 is not satisfied** (it requires a *verified* pin). **C21-022 is implemented but not executed** in any hosted run.
- Configuration A is implemented and **not accepted**. Configuration B is preserved and passes locally.
- Stand-in package results are engineering evidence about the test logic, **not release evidence**.
- Required steps: [CLASSIC-EDITOR-VERIFICATION-PROCEDURE.md](CLASSIC-EDITOR-VERIFICATION-PROCEDURE.md).

## 3. C26-003 to C26-006

Deferred to M2-D by owner decision, **not implemented, not waived, not accepted** ([C26-M2D-BOUNDARY-REVIEW.md](C26-M2D-BOUNDARY-REVIEW.md)). No synthetic earlier release exists or may be created. C26-001 and C26-002 are implemented and passed locally through a shim; they have not run in wp-env.

## 4. Remaining manual acceptance items

None of these records exists. The plan tags reviewer-run `-M` tests as reviewer, and C17, C31-M01 and C34-M01 as owner.

| Test | Criterion | Pending record |
|---|---|---|
| 1 | Theme installs and activates | C01-M01 dashboard walk-through |
| 2 | Plugin installs and activates | C02-M01 |
| 3 | Both work together | C03-M01 |
| 6 | Dashboard content can be created and edited | C06-M01 admin walk-through |
| 10 | Incomplete clearance blocks publication server-side | C10-M01 |
| 12 | No unfinished placeholders appear publicly | C12-M01 |
| 13 | Layout works at 360, 768 and 1280 px | C13-M01 |
| 14 | Keyboard navigation works | C14-M01 (the acceptance test) |
| 15 | WCAG AA contrast met | C15-M01 |
| 17 | Naming compliance passes without exposing the term | C17-M01, C17-S01 (owner) |
| 19 | Both ZIPs install on a clean WordPress | C19-M01 |
| 20 | Installation and recovery instructions documented | C20-M01, C20-M02 (rehearsal) |
| 21 | Classic Editor compatibility | C21-M01 manual editor check |
| 25 | Database migration and upgrade integrity | C25-M01 |
| 27 | Audit trail behaviour | C27-M01 |
| 31 | Cache invalidation | C31-M01 (owner) |
| 34 | Audit key: missing, invalid, rotation and recovery | C34-M01 (owner) |

## 5. Who does what

**Owner-controlled**
- Authorise the first push and the draft pull request (separate decision).
- Appoint an independent reviewer (distinct from the implementer), and decide the approval route for the C26 attestation.
- Perform or witness the independent Classic Editor inspection and approve the pin change.
- Run the naming scan (C17-001) with the private term list; review screenshots (C17-M01); C17-S01.
- Owner-run manual records C31-M01 and C34-M01.
- Supply content and assets ([OWNER-CONTENT-REQUIREMENTS.md](OWNER-CONTENT-REQUIREMENTS.md)), including the audit key held outside the repository, and delete WordPress's default sample content before launch.
- Repository settings after the first push: do **not** make the PHP 8.5 job a required check; make `we-classic-editor-a` required only after the pin is verified.
- Register the C26 source and target (M2-D).

**Reviewer (appointed by the owner)**
- The `-M` records: C01, C02, C03, C06, C10, C12, C13, C14 (the acceptance test for criterion 14), C15, C19, C20 (including the recovery rehearsal), C21, C25, C27.
- Independent inspection of the Classic Editor package and review of the pin change.

**Developer**
- Run the first hosted CI and fix whatever it reveals, especially the never-run wp-env jobs.
- Run the Classic Editor capture run, prepare the reviewed pin change, and watch the strict run.
- M2-D work: C26-003 to C26-006 implementation (after prerequisites), the register validator (rules 1 to 16), the E8, E9 and E11 evidence artefacts, a persistent object-cache test for criterion 31.
- Known product gaps: gallery media picker, partnerships section and section settings, breadcrumbs, `screenshot.png`, custom fonts. Forms are M7.

## 6. Claims review

| Statement | Supported by |
|---|---|
| 146 PHPUnit tests pass | Executed locally on PHP 8.3, MariaDB 10.11, WordPress 6.4.3 |
| Browser, accessibility and crawl suites pass | Executed locally in Chromium against PHP's built-in server, not wp-env |
| WE integration and partial-data scripts pass | A **shim** that routes wp-env commands to local WP-CLI; wp-env itself never ran |
| C21 Configuration A passes | Only against a stand-in package; not release evidence |
| Packages are reproducible | Same checksums for the same commit; they change per commit by design |
| Actions, MySQL images, PHPUnit and npm packages are pinned | `scripts/check-pins.sh`, run locally and in CI |
| Nothing unpinned remains | **Not true.** wp-env's own container images and image-build packages are not pinned; see [DEPENDENCIES.md](DEPENDENCIES.md) |

## 7. Push readiness

See the report accompanying this commit. The code and documents contain no credentials, personal data or production dependency; the first push is itself the first test of CI, so some jobs, especially the wp-env jobs, may fail and need developer fixes. A red or non-blocking `we-classic-editor-a` job is expected until the pin is verified.

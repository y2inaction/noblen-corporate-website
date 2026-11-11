# CI and Acceptance-Test Planning Reference

**Status: FROZEN PLANNING REFERENCE. PLANNING ONLY. NO IMPLEMENTATION AUTHORIZED.**

This document is the authoritative **test register and validator specification** for the M2 acceptance criteria. It is a planning reference. It does not authorise tests, workflows, dependency installation, implementation work, deployment or any production change.

| Item | Value |
|------|-------|
| Technical baseline | M2 Revision 5.1, repository commit `8f920ff` |
| Criteria and stage allocation (B1) | **Incorporated by reference.** They are section 10 and its "Allocation of criteria to implementation stages" table in [M2-IMPLEMENTATION-PLAN.md](M2-IMPLEMENTATION-PLAN.md) as committed at `8f920ff`. They are not copied here. If this document and that text ever differ, the approved baseline text governs |
| Coverage status | **Planned = true. Implemented = false. Executed = false. Accepted = false.** No test is implemented or has run, and no criterion has passed |
| Implementation gates | G1 implementation, G2 and SG-A are **not authorised** |

## 1. Test attributes

Every test carries four attributes.

| Attribute | Values |
|-----------|--------|
| **Type** | `automated`, `manual`, `status` |
| **Executor** | `ci`, `owner`, `reviewer` |
| **Environment** | **PU** PHPUnit on pinned WordPress core and MySQL 8.4. **WE** `wp-env`. **ST** static analysis and repository checks. **PK** clean packaging environment. **OWN** the owner's machine. **CI** CI workflow or status execution. **MAN** human manual verification |
| **Evidence** | **E1** PHPUnit report. **E2** CI job log summary. **E3** traceability report (generated for every criterion). **E4** reviewed Playwright output. **E5** axe report. **E6** package build report with checksums. **E7** signed manual or owner record. **E8** PHP and database compatibility report. **E9** WP-CLI and integration run log. **E10** static-analysis and scan report. **E11** normalised dataset comparison report with its transformation manifest and records |

**Types.** `automated` is an executable test or automated check, including owner-executed checks such as `C17-001`. `manual` is a human-performed verification or acceptance activity. `status` is a reporting item only, and it cannot establish test execution or criterion acceptance.

**Type is derived from the ID:** `C<nn>-<nnn>` is automated, `C<nn>-M<nn>` is manual, and `C<nn>-S<nn>` is status. Only `C17-S01` exists.

**Explicit assignments**

| Test | Type | Executor | Environment | Evidence |
|------|------|----------|-------------|----------|
| C17-001 | automated | owner | OWN | E7 |
| C17-M01 | manual | owner | OWN | E7 |
| C17-S01 | status | ci | CI | E2 |
| C03-M01, and every other reviewer-run `-M` test | manual | reviewer | MAN | E7 |
| C31-M01, C34-M01 | manual | owner | MAN | E7 |
| All other `-nnn` tests | automated | ci | as tagged in section 2 | as tagged in section 2 |

**Tag format in section 2:** `{executor | environment | evidence}`, written with escaped pipes inside the tables. **Pass rule:** a criterion passes only when every listed automated test passes, every manual record is accepted, and the approved Method holds. **Evidence rule:** evidence must come from the test type and environment that produced it. A PHPUnit report is never accepted for a Playwright test.

## 2. Test register

Stage values are from the approved allocation in B1.

| # | Stage | Test IDs and attributes | Class |
|---|-------|-------------------------|-------|
| 1 | M2-B (re-run M2-C) | C01-001 theme activates via WP-CLI in a clean site, C01-002 error log empty `{ci\|WE\|E9}`; C01-003 front end and admin load `{ci\|WE\|E4}`; C01-M01 dashboard walk-through `{reviewer\|MAN\|E7}` | A+M |
| 2 | M2-A | C02-001 plugin activates via WP-CLI in a clean site, C02-002 error log empty `{ci\|PU\|E9}`; C02-003 tables and schema version created, C02-004 unsupported PHP or WordPress refused with no fatal `{ci\|PU\|E1}`; C02-M01 dashboard walk-through `{reviewer\|MAN\|E7}` | A+M |
| 3 | M2-C | C03-001 accessor data reaches the theme, C03-003 plugin inactive gives no PHP errors `{ci\|WE\|E9}`; C03-002 front-end smoke test with both active `{ci\|WE\|E4}`; C03-M01 reviewer verifies the integrated front-end and administrative behaviour `{reviewer\|MAN\|E7}` | A+M |
| 4 | M2-C | C04-001 counts and settings equal before and after switching to a default theme `{ci\|WE\|E9}`; C04-002 content still viewable `{ci\|WE\|E4}` | A |
| 5 | M2-A | C05-001 project, C05-002 product, C05-003 company, C05-004 industry, C05-005 service (each: create, read, update, trash, restore) `{ci\|PU\|E1}` | A |
| 6 | M2-A content; M2-C end to end | C06-001 browser script for project and product, C06-002 same for company, industry, service `{ci\|WE\|E4}`; C06-M01 admin walk-through `{reviewer\|MAN\|E7}` | A+M |
| 7 | M2-A | C07-001 each relationship field, C07-002 integrity rules, C07-003 reverse queries `{ci\|PU\|E1}` | A |
| 8 | M2-A | With sentinel values: C08-001 HTML and source, C08-002 every REST route (anonymous and lower-privilege), C08-003 feeds, C08-004 search, C08-005 public query variables and meta-style queries, C08-006 accessor and structured-data keys, C08-007 export, C08-008 attachments and media enumeration `{ci\|PU\|E1}` | A |
| 9 | M2-A | C09-001 role and capability matrix, C09-002 forged and nonce-less approvals, C09-003 editor cannot publish or view clearance detail `{ci\|PU\|E1}` | A |
| 10 | M2-A | With **incomplete clearance**, one result per section 5.6 path: C10-001 editor, C10-002 quick edit, C10-003 bulk edit, C10-004 scheduling, C10-005 REST (enabled for the test), C10-006 XML-RPC, C10-008 programmatic updates, C10-009 restore from trash, C10-010 import `{ci\|PU\|E1}`; C10-007 WP-CLI `{ci\|PU\|E9}`; C10-M01 UI check `{reviewer\|MAN\|E7}` | A+M |
| 11 | M2-B | C11-001 empty database, C11-002 partial data `{ci\|WE\|E4}` | A |
| 12 | M2-C | C12-001 crawl rendered pages for placeholder tokens `{ci\|WE\|E4}`; C12-M01 manual review `{reviewer\|MAN\|E7}` | A+M |
| 13 | M2-B (re-check M2-C) | C13-001 360 px, C13-002 768 px, C13-003 1280 px (screenshots and horizontal-scroll check) `{ci\|WE\|E4}`; C13-M01 manual review `{reviewer\|MAN\|E7}` | A+M |
| 14 | M2-B (re-check M2-C) | Automated support: C14-001 tab order, C14-002 visible focus, C14-003 menu operable by keyboard `{ci\|WE\|E4}`. **Acceptance: C14-M01 manual keyboard pass** `{reviewer\|MAN\|E7}` | M (+ A partial) |
| 15 | M2-B (re-check M2-C) | C15-001 token contrast for every pair `{ci\|ST\|E10}`; C15-002 axe checks `{ci\|WE\|E5}`; C15-M01 manual review `{reviewer\|MAN\|E7}` | A+M |
| 16 | M2-A plugin; M2-B theme; final M2-D | C16-001 lint PHP 8.3, C16-002 lint PHP 8.4, C16-003 PHPCS plugin, C16-004 PHPCS theme `{ci\|ST\|E10}`; C16-005 compatibility sniffs, C16-006 PHP 8.5 informational and never a required check `{ci\|ST\|E8}` | A |
| 17 | M2-A plugin; M2-B theme; final M2-D | C17-001 local naming scan, **owner-executed, never run by CI** `{owner\|OWN\|E7}`; C17-M01 owner's manual screenshot review `{owner\|OWN\|E7}`; C17-S01 CI status item reporting only whether the owner's evidence is outstanding or supplied `{ci\|CI\|E2}` | A (owner-run) + M |
| 18 | M2-B (re-check M2-C) | C18-001 browser network log of each tested page, **fails on any third-party host** `{ci\|WE\|E4}`; C18-002 static scan of markup, stylesheets, scripts and fonts for external references `{ci\|ST\|E10}` | A |
| 19 | M2-D | C19-001 build both ZIPs `{ci\|PK\|E6}`; C19-002 clean install of both, C19-003 activation and smoke `{ci\|PK\|E9}`; C19-M01 manual install check `{reviewer\|MAN\|E7}` | A+M |
| 20 | M2-D | C20-001 link check `{ci\|ST\|E10}`; C20-M01 document review, C20-M02 rehearsal of recovery steps `{reviewer\|MAN\|E7}` | M + A |
| 21 | M2-A | **Configuration A:** Noblen core plugin active, Classic Editor plugin active. **Configuration B:** Noblen core plugin active, Classic Editor plugin inactive. The five custom types stay registered in both. Custom types open in the classic editor: **A** C21-001 project, C21-002 product, C21-003 company, C21-004 industry, C21-005 service; **B** C21-006 project, C21-007 product, C21-008 company, C21-009 industry, C21-010 service `{ci\|WE\|E4}`. REST off: C21-011 (A), C21-012 (B) `{ci\|WE\|E9}`. Structured fields save: C21-013 (A), C21-014 (B); validate: C21-015 (A), C21-016 (B) `{ci\|WE\|E4}`. **Standard posts unaffected by the Noblen plugin** (no Noblen boxes or fields, same behaviour): C21-017 (A), C21-018 (B) `{ci\|WE\|E4}`. **Standard posts actually open in the block editor:** C21-019 (A), C21-020 (B) `{ci\|WE\|E4}`. C21-021 Classic Editor plugin version and source match the verified pin; C21-022 records the Classic Editor settings used for the run `{ci\|WE\|E9}`; C21-M01 manual editor check `{reviewer\|MAN\|E7}` | A+M |
| 22 | M2-A | Sentinel approval, clearance and audit values absent from C22-001 HTML, C22-002 REST, C22-003 feeds, C22-004 search, C22-005 exports, C22-006 accessor output; C22-007 unreadable to editors, C22-008 unreadable to anonymous users `{ci\|PU\|E1}` | A |
| 23 | M2-A | One test per component: C23-001 each text field (reported per field), C23-002 display switch, C23-003 relationship, C23-004 status, C23-005 term, C23-006 featured image, C23-007 gallery order, C23-008 media file replacement, C23-009 alt text, C23-010 video URL, C23-011 presentation flag, C23-012 registry test fails if a public field is missing from the snapshot `{ci\|PU\|E1}` | A |
| 24 | M2-A | Bypass attempts, one result per path: C24-001 editor, C24-002 quick edit, C24-003 bulk edit, C24-004 scheduling, C24-005 REST (enabled for the test), C24-006 XML-RPC, C24-008 programmatic updates, C24-009 restore from trash, C24-010 import `{ci\|PU\|E1}`; C24-007 WP-CLI `{ci\|PU\|E9}` (each ends as draft and an anonymous request is not found); C24-011 the Publisher succeeds only with a valid approval `{ci\|PU\|E1}` | A |
| 25 | M2-A | C25-001 fresh install, C25-002 upgrade from each earlier-schema fixture, C25-003 repeated runs (idempotent), C25-004 simulated failed migration (publication blocked, no data lost), C25-005 recovery from that failure, C25-006 backup confirmation recorded, C25-007 concurrent-run lock `{ci\|PU\|E1}`; C25-M01 backup-confirmation workflow `{reviewer\|MAN\|E7}` | A+M |
| 26 | M2-D (theme switch also M2-C) | **Method and records in section 3.** C26-001 theme switch, C26-002 plugin deactivate and reactivate `{ci\|WE\|E9, E11}`; C26-003 plugin upgrade from the **registered source version and schema fixture to the registered target version** `{ci\|PK\|E9, E11}`; C26-004 audit chain verifies with the key before and after, C26-005 every logical difference is covered by a declared transformation and every declared transformation is exercised, **C26-006 pre-run attestation and runtime binding check** `{ci\|PK\|E11}` | A |
| 27 | M2-A | C27-001 no update or delete code path (static) `{ci\|ST\|E10}`; C27-002 runtime update and delete attempts fail, C27-003 read access by role, C27-004 entries record who, when, what changed, snapshot, C27-005 modified middle entry detected, C27-006 removed middle entry detected, C27-007 reordered middle entry detected, C27-008 missing key blocks approvals, C27-009 export includes the chain value, C27-010 section 5.10 limits documented `{ci\|PU\|E1}` | A |
| 28 | M2-A | C28-001 cascades stop at one level, C28-002 cascades cannot loop, C28-003 lapsed dependent keeps its approval, C28-004 republished by the Publisher once valid, C28-005 snapshot excludes referenced content, C28-006 page shows the related record's currently approved content `{ci\|PU\|E1}` | A |
| 29 | M2-A | C29-001 blocked without a current rights confirmation, C29-002 file change, C29-003 caption, C29-004 alt text, C29-005 title, C29-006 gallery membership, C29-007 gallery order (each makes the approval Stale), C29-008 file replacement voids the confirmation `{ci\|PU\|E1}` | A |
| 30 | M2-A | C30-001 cross-record, C30-002 cross-snapshot, C30-003 cross-user, C30-004 single use, C30-005 no grant without an authenticated approver, C30-007 a request cannot supply a grant, C30-008 no global flag at runtime `{ci\|PU\|E1}`; C30-006 no global flag (static) `{ci\|ST\|E10}` | A |
| 31 | M2-A (re-check M2-C; manual M2-D) | **Each of C31-001 to C31-007 runs for every trigger T01 to T09** and is reported per trigger: C31-001 record cache entries cleared (core object cache), C31-002 same with the persistent object-cache test drop-in, C31-003 epoch makes plugin caches unreachable, C31-004 dependents' related-content caches rebuilt, C31-005 purge action fires once with the expected URL list and no private data, C31-006 administrator notice lists the URLs, C31-007 audit entry records reason and list `{ci\|PU\|E1}`; C31-008 integrated-site re-check of C31-001, C31-005 and C31-006 `{ci\|WE\|E9}`; C31-M01 owner confirms the CDN or hosting purge on a test address, if one is in use `{owner\|MAN\|E7}` | A+M |
| 32 | M2-A (re-check M2-C) | One test per state, each asserting every interface separately (single address, main search, archives, classification archives, related-content sections, feeds, core sitemap, oEmbed, REST, accessor): C32-001 Stale, C32-002 Revoked, C32-003 dependency lapsed, C32-004 draft, C32-005 pending, C32-006 trash, C32-007 missing approval after simulated direct database change, C32-008 mismatched approval after the same, C32-009 previously published valid content stays available when approvals cannot be written `{ci\|PU\|E1}`; C32-010 integrated-site re-check of the browser-visible interfaces `{ci\|WE\|E4}` | A |
| 33 | M2-A plugin; M2-B presentation (re-check M2-C) | C33-001 every relationship field has a class, C33-002 required missing blocks publication, C33-003 required unavailable blocks publication, C33-004 revoke, C33-005 reject, C33-006 unpublish, C33-007 trash, C33-008 delete (each reverts the dependent, reason audited), C33-009 Stale grace state and its end, C33-010 optional missing does not block, C33-011 optional unavailable does not unpublish `{ci\|PU\|E1}`; C33-012 presentation with the plugin, C33-013 presentation without the plugin, C33-014 no empty headings, dead links, placeholders or count leaks `{ci\|WE\|E4}` | A |
| 34 | M2-A (manual M2-D) | C34-001 missing key, C34-002 invalid key, C34-003 wrong key for a historical segment, C34-004 rotation verifies before and after, C34-005 existing rows byte-identical, C34-006 interrupted rotation, C34-007 old entries remain verifiable, C34-008 recovery requirements (sign-off reference, recovery authority, different person when separation of duties is on), C34-009 every row preserved, C34-010 comparison with the last export, C34-011 marker contents, C34-012 never re-signs history, C34-013 earlier entries only corroborated or unverifiable, C34-014 approvals blocked until the new segment verifies and the owner records completion, C34-015 old key later added reclassifies, C34-016 never silent, C34-017 compromise suspension `{ci\|PU\|E1}`; C34-018 no key-generation code `{ci\|ST\|E10}`; C34-M01 custody and recovery rehearsal `{owner\|MAN\|E7}` | A+M |

## 3. Criterion-specific specifications

### Criterion 17: owner-controlled naming scan
`C17-001` is automated but **owner-executed** on the owner's machine. CI never executes the scan, never shows blocked terms, and never treats `C17-S01` as a pass. `C17-S01` reports only whether the owner's evidence reference (pass or fail, date and reviewer, with no terms) is outstanding or supplied. The criterion cannot be considered for acceptance until that evidence is supplied.

### Criterion 18: no third-party hosts
The approved rule is retained: the browser network log has no third-party hosts. The report records every request and its destination, and any third-party host fails `C18-001`. Any exception requires a separately approved change to the baseline, not a CI configuration.

### Criterion 21: configurations
Configurations differ only in the Classic Editor plugin. The Noblen core plugin that registers the five custom types is active in both, so the custom-type editing screens exist in both. In Configuration A the Classic Editor plugin's default editor is the block editor, so the block editor stays enabled for standard posts. Custom types open in the classic editor by their own registration. Standard-post behaviour (`C21-017` to `C21-020`) is tested separately from custom-type behaviour, and the actual editor behaviour is tested, not only a stored setting.

### Criteria 10 and 24
Criterion 10 proves that incomplete clearance blocks publication on every section 5.6 path. Criterion 24 proves that bypass attempts leave records unpublished and that the Publisher succeeds only with a valid approval. Each path has its own result in each criterion, and neither compensates for the other.

### Criterion 26: comparison method and records
**Normalised logical dataset.** The comparison uses a logical export, independent of physical table layout, covering posts and editorial content (with content hashes), metadata and relationships (as ID sets), clearance records, approvals and existing audit entries.

**Transformation manifest.** Each migration declares its expected schema or representation changes in a manifest that maps every change to the logical field it preserves. A logical difference not covered by the manifest fails, and a declared transformation that is never exercised fails. There are no wildcard exceptions, no logical value may be lost or altered without an explicit preservation mapping, and audit history must remain intact and verifiable (`C26-004`). The source plugin version, target version and schema fixture are registered fields, fixed once the builds exist.

**Two linked records** (checked by `C26-006`):

*A. Pre-run review attestation.* Created and approved before any comparison. It contains the migration ID, source version and schema fixture, and target version; the SHA-256 of the exact manifest bytes reviewed; the reviewer's identity and approval timestamp (UTC); and a verifiable approval record, which is either a protected repository review approval on the commit that contains that manifest or a separately retained signed record with its signature fingerprint. It contains no run details.

*B. CI runtime binding record.* Generated by CI at the start of each comparison attempt. It contains the comparison-run ID **and run attempt number**; the platform-supplied UTC start timestamp of that attempt; the **reviewed-manifest commit**; the **checked-out commit** of the run; the SHA-256 of the manifest actually loaded; and a reference to the pre-run attestation.

**`C26-006` fails when any of these is true:**
1. **Missing:** the attestation or the binding record is absent, including for any run attempt.
2. **Invalid:** either record is not schema-valid, or the approval record cannot be resolved or verified.
3. **Mismatched:** the manifest hash in the attestation, the hash of the manifest at the reviewed-manifest commit, and the hash of the manifest loaded at run time are not all identical; the migration ID, source version, fixture or target version differ between the records and the run; the reviewed-manifest commit differs from the commit covered by the verified approval record; or the reviewer identity does not match the approval record.
4. **Late:** the approval timestamp is not strictly earlier than the platform-recorded start of that run attempt.

A rerun, even under the same run ID, needs its own binding record, and two records for one attempt with different hashes fail. The run's checked-out commit may differ from the reviewed commit, and only the manifest bytes must be identical.

**Immutability and limits.** Both records are retained as E11 evidence, with the SHA-256 of each written to the run's job log and the traceability report. Neither may be altered after a comparison to change its outcome. A failed comparison is corrected only by a new attestation (new manifest hash and approval time) and a new run, and the failed run's records are kept. This makes tampering detectable, not impossible, because someone with write access to the artifact store or repository could still alter evidence. A repository review proves that an account approved the commit, not who controlled the account. A separately retained signed record carries a reviewer-asserted timestamp unless a trusted timestamp is attached, so the repository review route gives the stronger chronology.

### Criterion 31: ineligibility triggers
| Trigger | Source in the approved baseline |
|---------|---------------------------------|
| T01 Publisher revoke | 5.7c withdrawal procedure |
| T02 Unpublish, including a manual change to draft or pending | 5.7c, 5.7a |
| T03 Rejection after publication | 5.7a, 5.7c |
| T04 Edit of a published record through a non-Publisher path (Stale) | 5.6 R3 |
| T05 Change outside the normal save path, such as direct metadata, term or attachment changes (Stale) | 5.6 R4 |
| T06 Transition safety-net revert | 5.6 R6 |
| T07 Dependency lapse of a dependent | 5.7a |
| T08 Trash | 5.7c |
| T09 Permanent delete | 5.7c |

`C31-001` to `C31-007` run for **each** of T01 to T09, and the gate fails if any trigger has no result for any of them. The read-time failure case (R5) is not a withdrawal trigger in the baseline, so it is covered by `C32-007` and `C32-008`. The manual CDN or hosting check (`C31-M01`) stays separate and conditional on an external cache being in use. The plugin's tests cannot prove CDN or hosting behaviour.

### Criteria 14, 20, 31 and 34
Automated and manual evidence are kept as separate items. Criterion 14 is accepted by the manual keyboard pass, with automated checks as support. Criterion 34 combines automated key-state and cryptographic tests with a manual custody and recovery rehearsal.

## 4. Coverage status model

Four independent fields per criterion.

| Field | Meaning | Today |
|-------|---------|-------|
| **Planned** | Test IDs defined and bound to the criterion | **True** |
| **Implemented** | Test code carrying the exact ID is discovered in the suite | **False** |
| **Executed** | The tests have run in their environment on a named commit | **False** |
| **Accepted** | A reviewer examined all required evidence and accepted the criterion | **False** |

A populated register is not evidence of completion. A green pipeline alone never accepts a security-sensitive criterion.

## 5. Register validator rules

CI fails when any of these is violated:

1. Test IDs are unique and match `C<nn>-<nnn>`, `C<nn>-M<nn>` or `C<nn>-S<nn>`.
2. Every criterion reference exists.
3. Every test records all four attributes: type, executor, environment and evidence.
4. The type matches the ID pattern.
5. Only valid combinations are used:

| Type | Executor | Environment |
|------|----------|-------------|
| automated | ci | PU, WE, ST, PK |
| automated | owner | OWN |
| manual | reviewer | MAN |
| manual | owner | OWN or MAN |
| status | ci | CI |

6. Evidence is compatible with the environment:

| Environment | Allowed evidence |
|-------------|------------------|
| PU | E1, E9 |
| WE | E4, E5, E9, E11 |
| ST | E8, E10 |
| PK | E6, E9, E11 |
| CI | E2 |
| OWN, MAN | E7 |

7. Coverage counts only the matching type. Automated coverage counts `automated` tests, and manual coverage counts `manual` tests. `status` items count for neither, and a status item can never set a criterion's Executed or Accepted state.
8. Every automated test maps to at least one valid criterion, and every automated criterion has at least one registered automated test.
9. Every criterion classified A + M, M + A or M (+ A partial) in the approved text has at least one `manual` test, and at least one `automated` test where the classification includes automated checks.
10. Owner-executed automated tests are never run by CI. The CI test selection excludes them, and the traceability report shows them as "owner-run: evidence outstanding or supplied".
11. Manual acceptance is never inferred from automated results.
12. A test is "implemented" only when test code carrying that exact ID is discovered, not because the ID appears in the register.
13. A criterion cannot be "accepted" until all required automated and manual evidence is present and a reviewer has recorded acceptance.
14. The four status fields are independent, and a later state cannot be set without the earlier ones.
15. Every trigger T01 to T09 has results for every `C31-001` to `C31-007`.
16. Every logical difference in criterion 26 is covered by a declared transformation, every declared transformation is exercised, and `C26-006` passes with a valid pre-run attestation and a CI runtime binding record for each run attempt that meet the four conditions in section 3.

## 6. Machine-readable register and report

The register will be a YAML file at `documentation/acceptance-criteria-traceability.yml`. **It is not created by this document.** It will be derived from this specification when implementation is authorised. CI will validate its structure against the rules above and generate a criterion-to-test traceability report that identifies covered criteria, missing coverage, tests without a valid criterion reference, manual-only criteria, skipped tests, failed criteria and evidence references. The report is retained as an artifact only after confirming that it contains no sensitive information. The public register must contain no credentials, personal information, private clearance details, audit secrets or sensitive operational information.

## 7. What this document does not authorise

Application code, tests or CI workflow changes. Dependency installation or lockfile changes. Implementation branches or implementation work. Merging or deploying anything. G1 implementation, G2 or SG-A. Any change to production, hosting, DNS or databases.

# M2 Implementation Plan: Brand System, Theme, Core Plugin and Publication Gate

**Status: REVISION 5.1 — FINAL OWNER APPROVAL COPY. NO IMPLEMENTATION AUTHORIZED.**

**Approval record:** **G0 (final M2 technical plan) is APPROVED. Revision 5.1 is the approved M2 technical baseline.** G0b (documentation-only commit), G1 (dependency installation), G2 (implementation branch and commit permissions) and the stage gates SG-A to SG-D remain **not authorised**. No implementation is authorised.

_Revision 5.1. Nothing in this document has been built, installed, run or tested. Every statement about behaviour is a requirement or intention, and no acceptance criterion has been met. Existing milestone approvals are unchanged. Approving this copy confirms the recommended defaults recorded in section 15._

## 0. Revision history

| Revision | Main changes |
|----------|--------------|
| 2 | Theme and core plugin separated. Taxonomy synchronisation removed. Clearance moved to private storage with a server-side gate. PHP 7.4 target dropped. Naming check made owner-controlled. Placeholder page templates removed |
| 3 | Classic Editor and structured meta boxes. PHP 8.3 baseline. Four separate status concepts. Canonical content snapshot. Audit trail limits. Database lifecycle |
| 4 | Precise security boundary in two tiers, and a **scoped, single-use Publisher grant** bound to the record, snapshot and user (5.6). **Related-record dependency rules** (5.7a). **Media rights policy** with enforcement (5.7b). **Audit operating procedure** and a mandatory audit key (5.10). Gate applies to all five content types. Hosted naming check dropped for M2 (6). **Staged implementation M2-A to M2-D, with a review stop after each** (14). Recommended defaults recorded (15). New acceptance criteria 28 to 30, and 27 extended (10) |
| **5** | **Ineligible content: cache invalidation and public exposure** (5.7c). **Relationships explicitly classified required or optional**, with publication rules and graceful degradation (4.1, 5.7a). **Audit-key custody, loss, compromise, rotation and recovery** procedure, with new approvals blocked whenever audit integrity cannot be established (5.10a). New acceptance criteria 31 to 34, and 28 rewritten (10). All Revision 4 decisions retained, and the implementation scope is not expanded |
| **5.1** | A narrow amendment only: the **audit-key loss recovery** procedure now explains how the recovery marker is authenticated when the old key no longer exists, and never lets the new key vouch for history it cannot verify (5.10a). Added the distinction between temporarily unavailable and deliberately withdrawn relationships (5.7a). Recorded the Revision 5 technical review decisions (14, 15). No other architectural change |

Related planning documents were aligned only where needed: `WEBSITE-ARCHITECTURE.md` and `PROJECT-PROGRESS.md`.

## 1. Scope and milestone boundaries

### In M2

Brand system and design tokens. Theme foundation. Companion core plugin. Content types and taxonomies. Private clearance and publication gate. Header, footer and navigation. CMS-fed homepage skeleton. Responsive and accessibility baseline. CI and initial QA.

### Deferred (unchanged roadmap)

| Milestone | Scope |
|-----------|-------|
| M3 | Full homepage design and refinement |
| M4 | Group directory and subsidiary pages |
| M5 | Industry and solution pages |
| M6 | Projects, portfolio and Insights presentation |
| M7 | Contact and partnership forms |
| M8 | SEO, security and performance hardening |
| M9 | Full QA, documentation and release preparation |

### Items kept in M2 that touch deferred areas, with reasons (approval requested)

| Item | Why it is in M2 | Limit |
|------|-----------------|-------|
| Minimal **functional** single and archive templates for project and product | Needed to test publication, relationships and "content stays available after theme switch" | Structural only. No portfolio design, filters or case-study layout (M6) |
| Basic `search.php` and `404.php` | A theme without them is incomplete and untestable | Plain layout |
| Public-data accessor returning an allowlist | Part of the security boundary | No Schema.org output (M8). M8 will use the accessor |
| Analytics ID setting (empty by default) | The settings framework is built once | Nothing loads without an ID, and no analytics code ships in M2 |

Anything else deferred stays deferred unless the owner approves otherwise.

## 2. Architecture: two independently installable components

| | **Noblen Corporate Theme** | **Noblen Corporate Core plugin** |
|---|---|---|
| Location | `wordpress-theme/noblen-corporate/` | `wordpress-plugin/noblen-corporate-core/` |
| Owns | Presentation | Content architecture and CMS |
| Responsibilities | Brand identity and design tokens, typography, header, footer, navigation, homepage layout, page templates, content presentation, responsive components, accessibility, theme-specific assets | Custom post types, taxonomies, custom fields and metadata, content registration, admin interfaces, publication approval workflow, clearance management, custom capabilities, content validation, database tables, business settings (company details, contact details, social links, analytics ID, homepage content) |
| Never contains | Post types, taxonomies, clearance logic, business content | Visual design, templates for a specific look |

### Dependency rules

1. The plugin has no dependency on the theme and works under any theme.
2. The theme has a soft dependency on the plugin. It is the recommended companion, and the theme must work without it.
3. Install order: plugin first, then theme. Both orders must work.
4. **Version handshake:** the plugin exposes a constant and a function reporting an API version. The theme declares the minimum it needs. If the plugin is missing or older, the theme hides dynamic sections and shows an administrator-only notice. It never calls an undefined function.
5. **Graceful degradation:** every plugin call from the theme goes through one guarded wrapper. With the plugin inactive there are no PHP or fatal errors, the front end works on core WordPress content, and administration is unbroken.
6. **Data survives theme changes:** all business content and settings are stored by the plugin. Theme-owned data is limited to presentation (menu location assignments, and homepage section order and visibility). WordPress keeps menu location assignments per theme, so after a switch the menus exist but locations must be reassigned. This is documented.
7. **Plugin deactivation and upgrade never delete data.** Uninstall behaviour is in section 5.11.

## 3. Planned file structures (planning only, none exist)

### Theme: `wordpress-theme/noblen-corporate/`

```
style.css                      theme header, Requires PHP / WP fields, proprietary licence line
functions.php                  bootstrap only
index.php front-page.php header.php footer.php page.php single.php
archive.php search.php searchform.php 404.php
single-noblen_project.php single-noblen_product.php         minimal, functional
archive-noblen_project.php archive-noblen_product.php       minimal, functional
screenshot.png
assets/css/  tokens.css base.css layout.css components.css
assets/js/   navigation.js reveal.js                         vanilla, deferred
assets/fonts/ assets/images/
inc/         setup.php enqueue.php core-bridge.php compat.php template-tags.php customizer.php
template-parts/  hero.php group-intro.php companies.php industries.php solutions.php
                 projects.php products.php insights.php partnerships.php cta.php
                 cards/  project.php product.php company.php industry.php post.php
```

`core-bridge.php` is the only file that talks to the plugin. There are no `page-templates/` in M2.

### Plugin: `wordpress-plugin/noblen-corporate-core/`

```
noblen-corporate-core.php      header (Requires PHP / WP, licence), bootstrap
uninstall.php                  retains everything unless the owner explicitly opted in
includes/
  Plugin.php  Activator.php  Deactivator.php  Compat.php  Autoloader.php  Capabilities.php
  Content/        Project.php Product.php Company.php Industry.php Service.php
  Taxonomies/     PortfolioArea.php ProductType.php
  Fields/         Registry.php PublicFields.php Sanitizers.php Statuses.php
  Editor/         ClassicEditor.php MetaBoxes.php MediaPicker.php
  Relationships/  Relations.php Integrity.php
  Clearance/      Repository.php Checklist.php Approval.php Snapshot.php Publisher.php Grant.php
                  Gate.php Invalidation.php ReadCheck.php Dependencies.php MediaRights.php
  Audit/          AuditLog.php Chain.php Key.php KeyCustody.php Verifier.php Export.php
  Database/       Schema.php Migrator.php Lock.php Migrations/ (numbered, additive)
  Admin/          Menus.php ClearanceScreen.php ListColumns.php Notices.php SettingsPage.php
  Exposure/       RestLockdown.php FeedAndSearchRules.php Withdrawal.php CacheInvalidation.php
  PublicData/     Accessor.php                                   the allowlist
  Settings/       Company.php Contact.php Social.php Analytics.php HomeContent.php
  Seed/           DraftSeeder.php                                optional, drafts and titles only
assets/admin/     admin.css admin.js
languages/
tests/            phpunit/ integration/ fixtures/
```

### Repository tooling (planned)

```
bin/  build-packages.sh  naming-scan.sh  check-structure.sh  contrast-check.*
.github/workflows/validate.yml    extended to both components
composer.json (development only)  phpcs.xml.dist
```

No runtime Composer or npm dependency ships inside either package.

## 4. CMS data model

### 4.1 Authoritative sources and relationships

Each fact has exactly one home.

| Fact | Single source of truth | Referenced by |
|------|------------------------|---------------|
| Subsidiaries and business units | `noblen_company` records | projects, products, industries, services |
| Industries | `noblen_industry` records | projects, products, companies, services |
| Services | `noblen_service` records | projects, companies, industries |
| Insights | standard `post` | none |

**Relationships are stored as references to record IDs** on the referring record, not as copies and not as taxonomies. Renaming a company renames it everywhere. The reverse direction (for example "projects for this company") is a query, not stored data.

| Referring record | **Required** relationships | Optional relationships |
|------------------|----------------------------|------------------------|
| Project | responsible business unit | industries, services, related products |
| Product | responsible business unit | industries, related projects |
| Company | none | industries, services |
| Industry | none | companies, services |
| Service | none | industries, companies |

**Every relationship field is declared required or optional in the field registry.** A relationship field cannot be registered without a class, and a test enforces this. The rules that follow from the classes are in section 5.7a.

**Integrity rules:** references to unpublished, trashed or deleted records are not displayed. The plugin cleans references when a record is permanently deleted.

### 4.2 Taxonomies (classification only)

| Taxonomy | Applies to | Terms |
|----------|------------|-------|
| `noblen_portfolio_area` | project | Starter terms A to E, editable by administrators |
| `noblen_product_type` | product | Starter terms "Platform" and "Operating system", editable |

There is no business-unit, industry or service taxonomy, because those are records. Filters on those dimensions use the relationships above.

### 4.3 Business status fields (controlled vocabularies, validated server-side)

| Type | Values |
|------|--------|
| Project | Completed Assignment, Ongoing Engagement, Strategic Initiative |
| Product | Concept, In Development, Pilot, Live |
| Industry | Active operation, Available service, Strategic growth area |

These are fixed vocabularies defined by the owner, stored as metadata and validated on save. Adding a value is a deliberate code change. A product may be saved as Live only when an evidence reference is recorded in the private clearance data. **Business status is a separate concept from publication, clearance and approval** (section 5.5).

### 4.4 Public editorial fields (allowlisted)

For projects and products these are the structured fields:

| # | Field |
|---|-------|
| 1 | Project overview |
| 2 | Client or commissioning organisation |
| 3 | Responsible Noblen business unit |
| 4 | Industry |
| 5 | Services delivered |
| 6 | Project scope |
| 7 | Noblen's contribution |
| 8 | Project approach |
| 9 | Outcomes and impact |
| 10 | Gallery |
| 11 | Project status |
| 12 | Supporting evidence and publication clearance (**private, not an editorial field**) |

Fields 1 to 11 are editorial. Field 12 lives only in the private clearance storage (section 5.2) and has no editorial meta box. Each narrative section has a "verified and approved for display" switch, and the accessor returns a section only when it has content and the switch is on.

### 4.5 Future additions without code changes

Administrators can, through the dashboard alone: add companies, industries, services, projects, products, product types and portfolio areas; create relationships; publish Insights; edit business settings. Only a new status value, a new content type or a new field requires a code change.

### 4.6 Editing architecture (decided for M2)

- **Classic Editor** for all five custom content types, with **structured native WordPress meta boxes**. There is no Gutenberg dependency for these types in M2.
- Mechanism: the custom types are registered with `show_in_rest` off, so the block editor does not load for them. An explicit filter also returns "no block editor" for these types, so the behaviour does not depend on a single setting. The Classic Editor plugin is **not** required.
- **Projects and products** have no free-form body editor. Their content is the structured fields in section 4.4, grouped in meta boxes: overview and client; business relationships; scope and contribution; approach; outcomes (verified only); status; gallery; display switches. **Companies, industries and services** keep a classic body editor plus meta boxes.
- Rich-text fields use a limited editor toolbar and a server-side allowlist filter (no scripts, frames or embedded objects). Plain fields are sanitised as text, relationship fields must reference existing records of the right type, and status fields must match their vocabulary.
- **Media:** the WordPress media library handles approved public images. The featured image uses the core box. The gallery uses the core media modal, with no third-party picker. Videos are a validated URL field that loads on click.
- **Media caveat (documented for administrators):** a file uploaded to the media library is reachable by its direct URL even while the record is a draft. Only approved images should be uploaded. Evidence and unapproved material never go in the media library. The media rights policy and its enforcement are in section 5.7b.
- **Private clearance information is entirely outside these editorial fields.** It is entered on a separate "Clearance and approval" screen, shown only to users with the right capability, with its own nonces and save handler.
- **Standard posts (Insights)** keep WordPress's default editing experience. This plan does not change it.
- **Future Gutenberg support** for these types would require enabling REST for them. That needs a **separately reviewed REST permission architecture**: permission callbacks on every route, metadata registration with authorisation callbacks, schema review so that no private data can appear, a re-run of the exposure matrix and of the publication gate tests, and a separate milestone approval. It is not part of M2.

## 5. Private clearance and publication approval

### 5.1 Four separated classes of data

| Class | Examples | Stored | Public? |
|-------|----------|--------|---------|
| Editorial content | Text, images and relationships intended for display | Post tables and allowlisted metadata | Only through the accessor, only when approved |
| Evidence and supporting documentation | Source references, notes on where proof is held | Private clearance storage, as references only | Never |
| Private clearance information | Client consent, logo permission, image release, confidentiality check, per-image media rights confirmations | Private clearance storage | Never |
| Publication approval | Approver, decision, timestamps, approved snapshot | Private approval storage and the audit log | Never |

### 5.2 Storage design

- Clearance, approval and audit data are held in **dedicated database tables owned by the plugin**. They are not posts, post meta, term meta, options, comments or attachments, so WordPress's query, search, REST, feed, export and attachment systems have no path to them.
- **No evidence files are stored on the website in M2.** The record holds a short reference to where the owner keeps the document. No upload control exists.
- Access goes through one repository class that checks capabilities on every read and write.
- The protection does not rely on hiding fields in the interface or leaving them out of templates. The data is not in public storage at all.

### 5.3 Exposure controls and how each is verified

| Exposure path | Control | Verification (automated unless stated) |
|---------------|---------|--------------------------------------|
| Public HTML and page source | Templates read only the accessor | Crawl rendered pages for sentinel values |
| REST API | Custom types and taxonomies have REST off, so no routes and no search entries. The plugin adds no clearance routes. A defensive filter blocks any private key | Request listing, single, search and media routes as anonymous and as lower-privilege users |
| Feeds | Custom types excluded, comments disabled | Fetch every feed |
| Search | Private data is not in searchable tables. Only published, approved records appear | Search for sentinel values |
| Public metadata queries | Private data is not metadata. Public query variables cannot reach custom tables | Attempt query variables and meta-style queries |
| JSON-LD and structured data | Built only from the accessor allowlist | Test the accessor's output keys |
| Public exports | WordPress export reads post tables, and the private tables are not in them | Run an export and scan it |
| Unauthorised administrative access | Capability check on every read and write, nonce on every write | Role matrix tests |
| Public attachments and downloads | No evidence files exist as attachments | Enumerate the media library and endpoints |

Sentinel values are unique strings placed in the clearance and approval data of test records, then searched for everywhere.

### 5.4 Threat model, stated plainly

The controls protect against anonymous visitors, lower-privileged users and alternative interfaces. They do **not** protect against a malicious administrator, direct database access, or a stolen database backup. Backups containing the plugin tables must be treated as confidential.

### 5.5 Four separate data concepts

These are four independent pieces of data. None is derived from another, and none is a synonym for another.

| Concept | Where it lives | Values | Visible | Meaning |
|---------|----------------|--------|---------|---------|
| **1. WordPress publication status** | `post_status` | draft, pending, publish (future and private are not allowed for gated types) | Public effect | The only thing WordPress uses to decide whether a page is served |
| **2. Business status** | Allowlisted post metadata | Section 4.3 vocabularies | Public when published | A statement about the project, product or industry. "Completed Assignment" says nothing about approval |
| **3. Private clearance status** | Private clearance table | Incomplete or Complete, with the checklist items | Never public | Whether consents, permissions and checks have been recorded |
| **4. Publication approval status** | Private approvals table | None, Submitted, Approved, Rejected, Revoked, Stale | Never public | Whether an authorised person approved one exact version of the content (section 5.7) |

**Rules between them:** a record may be published only if its approval is Approved **and** the approved snapshot equals the current content **and** clearance is Complete **and** the other requirements in section 5.6 hold. Business status never grants publication. An Approved record can remain a draft until it is published. Revoking or invalidating approval returns the record to draft.

### 5.6 Authorised publication path and enforcement

**Gated types.** All five custom types use the same Publisher, so approval applies uniformly and related-record checks can rely on it. The clearance checklist differs by type: **full** for projects and products, and a single "content reviewed" item for companies, industries and services.

#### Security boundary (two tiers, stated precisely)

| Tier | Paths | Position |
|------|-------|----------|
| **1. WordPress-supported write paths** | Editor, quick edit, bulk edit, scheduling, REST (if ever enabled), XML-RPC, WP-CLI, programmatic WordPress functions, trash restore, import | The plugin intercepts these and **tests each one** (rules R1 to R7) |
| **2. Not guaranteed** | Direct database manipulation (SQL, database tools, restoring a backup, code that writes to tables directly), code that removes the plugin's hooks, a compromised administrator or server | **No prevention is claimed.** Only detection is offered: the read-time check (R5) and the integrity tool. See the threat model in 5.4 |

#### The Publisher and its scoped grant

**There is exactly one authorised way for a gated record to become publicly visible: the plugin's Publisher service.** It does not use a global flag. It issues a **scoped grant**:

- **Bound to** one post ID, one approval ID, one approved snapshot hash and one user ID.
- **Created only inside the Publisher**, after the capability, clearance, approval, snapshot and dependency checks have passed. It is held in private in-process state: never in options, transients, metadata, cookies or request data.
- **Single use.** It is consumed by the first matching write, or when the Publisher call ends, whichever is first.
- **Rechecked by the save filter:** the incoming post ID must equal the grant's post ID, the snapshot recomputed from persisted data must equal the grant's hash, and the current user must equal the grant's user. A grant for one record cannot authorise another record, another version, or another user.
- **No user, no publication:** WP-CLI or any context without an authenticated user holding the approve capability cannot obtain a grant.

#### Rules

1. **R1, fail-closed default:** any write that would leave a gated record with status publish or future is converted to **draft** before it is stored, unless a matching grant is presented.
2. **R2, Publisher:** verifies the requirements below, issues the grant, publishes, writes an audit entry, and discards the grant (in a `finally` block).
3. **R3, editing a published record:** any edit through a non-Publisher path converts the record to draft **before the write** (R1) and marks the approval Stale. No public window exists. **Strict approach for M2: editing a live record takes it offline until it is re-approved and published.** Editors lack the capability to edit published gated records.
4. **R4, changes outside the normal save path** (direct metadata, term or attachment changes, such as replacing a media file or altering alt text): invalidation hooks mark the approval Stale and revert the record to draft immediately.
5. **R5, read-time check:** a published gated record is served only if it has an Approved, non-Stale approval. Related records shown on a page are verified the same way (5.7a). Otherwise the record returns not found, or the reference is suppressed, and an administrator is notified. This is defence in depth and the only detection for Tier 2 changes. It is not prevention.
6. **R6, transition safety net:** if a gated record reaches publish or future without a grant (for example through a core function that bypasses the save filter), it is reverted.
7. **R7, dependencies:** the dependency rules in 5.7a.

**Requirements checked by the Publisher (all mandatory):**
- valid business status;
- every **required** relationship present and valid (5.7a). Optional relationships never block publication;
- required public fields present;
- clearance Complete, and every image in the snapshot has a current rights confirmation (5.7b);
- approval Approved by a user holding the approve capability, and snapshot match;
- for Live products, an evidence reference;
- not password-protected and no scheduled date;
- audit integrity is established, meaning the audit state is Verified (5.10a);
- when separation of duties is on, the approver did not last edit the content and did not record its clearance.

**Enforcement points**

| Write path | What happens | Mechanism |
|------------|--------------|-----------|
| Normal editor publishing | The "Publish" button is replaced with "Save draft" and "Submit for approval". A forged publish request is converted to draft | R1. Capability limits are the first layer |
| Quick edit | Status change to publish is converted to draft | R1, because quick edit saves through the standard update function. The status choice is also removed from the dropdown, as a convenience only |
| Bulk edit | Same | R1, and the option is removed from the dropdown as a convenience only |
| Scheduled publishing | Scheduling is converted to draft. Any scheduled gated record that reaches publish is reverted | R1 and R6. The scheduled-publish function is tested explicitly, with no assumption about which hooks it fires |
| REST requests | **None enabled in M2**, so no routes exist. If enabled in a later milestone, the same gate applies | R1, and the REST permission architecture review in section 4.6 |
| XML-RPC | Writes pass through the same save path and are converted to draft | R1. Disabling XML-RPC is a hardening decision for M8 |
| WP-CLI | A CLI create or update cannot publish a gated record, and no grant can be issued without an authenticated approver | R1 and the grant rules |
| Programmatic WordPress updates | Same | R1 and R6. Restoring from trash is tested, and no assumption is made about the status it restores |
| Import (WordPress export file) | Imported gated records arrive as drafts | R1 |
| Direct database writes | **Tier 2: not guaranteed** | R5 detects some cases only |

**Avoiding recursion.** The save filter only changes the incoming data and never saves a post. The invalidation and transition handlers never call a post update function. Reverting uses a minimal status correction with a re-entrancy lock, followed by cache clearing. The Publisher runs outside those hooks. Failures write to the audit table, which is not a post, so no further post hooks are triggered.

**Failed approval never leaves a record public.** Every failure path ends in draft. The conversion happens before the write, so there is no moment when unapproved content is public. Revocation reverts the record immediately and clears the WordPress caches. External page caches must also be purged, and this is documented for the hosting setup.

### 5.7 Canonical content snapshot and approval integrity

**What an approval means.** An approval binds one approver's decision to one exact version of a record's public content, identified by a snapshot. It applies to nothing else.

**Contents of the snapshot (everything that affects public presentation):**

| Component | Included |
|-----------|----------|
| Core fields | Title, slug, excerpt, body (where the type has one) |
| Editorial fields | Every public field from the field registry (section 4.4), with each "verified and approved for display" switch |
| Business status | Public business status |
| Relationships | The referenced record IDs, as sorted sets |
| Classification | Assigned portfolio area or product type term IDs |
| Featured image | Attachment ID, **file checksum**, alt text, caption and title |
| Gallery | Ordered attachment IDs, each with the same media data |
| Other media references | Video URLs and their display settings |
| Presentation flags | Any flag that changes how or whether the record is shown (for example "featured on homepage") |

**Excluded:** private clearance and approval data, audit data, modification timestamps, revisions and autosaves, and the WordPress publication status itself.

**Canonical form.** A versioned canonical text built from the registry in a fixed key order, with line endings normalised and UTF-8 normalised, sets sorted, ordered lists (such as the gallery) kept in order, and a SHA-256 hash over it. The format version is stored with the hash, and a format change is treated as a new version requiring re-approval.

**Registry-driven completeness.** The snapshot is built from the same field registry that defines the public fields, so a field cannot be public without being in the snapshot. A test fails if any registered public field is missing from it.

**Storage.** The approval record stores the hash, the format version and a copy of the canonical text, so a reviewer can see exactly what was approved.

**Invalidation.** Any change to any snapshot component, through any path, makes the current snapshot differ from the approved one, so the approval is no longer valid (Stale). Related records are covered by the IDs. The content of a related record is governed by that record's own approval, and the page does not display a related record that is unpublished.

### 5.7a Relationship classes and related-record dependencies

Each relationship field has a class, **required** or **optional**, as listed in section 4.1. A relationship is **unavailable** when the referenced record is missing (the field is empty or the record no longer exists), unpublished (draft, pending or in the trash), not validly approved (Revoked, Rejected or Stale), or itself lapsed.

**Temporarily unavailable versus deliberately withdrawn.** These are different situations and are handled differently.
- **Temporarily unavailable:** the related record is offline only because it was edited and awaits re-approval (Stale). Routine updates to corporate information fall here. Dependents stay online and the relationship is hidden until the record returns.
- **Deliberately withdrawn:** a person revoked, rejected, unpublished, trashed or deleted the related record. For a required relationship this triggers the publication restriction.

#### Rules by class

| Situation | **Required** relationship | Optional relationship |
|-----------|---------------------------|-----------------------|
| **At publication**, relationship missing or unavailable | **Publication is blocked.** The message names the field, not private details | **Not blocked.** The approver is warned on the approval screen, and the warning is recorded in the audit log. The relationship is suppressed in display |
| **After publication**, record is deleted, trashed or missing; revoked; rejected after publication; or unpublished by a person | The dependent **reverts to draft** (the **publication restriction**), with the reason "dependency lapsed" audited and naming both records | The dependent **stays online**. The relationship is suppressed |
| **After publication**, the related record is **temporarily offline because it was edited and awaits re-approval** (Stale) | **Grace state:** the dependent stays online, the required relationship is suppressed in display, and administrators are flagged ("required relationship temporarily unavailable"). The grace ends when the related record is published again (the relationship reappears), or when it is revoked, rejected, trashed, deleted or unpublished by a person (the dependent then lapses) | The dependent stays online. The relationship is suppressed |

- "Unpublished by a person" means a deliberate unpublish, which is recorded as a revocation. Stale arises only from edits (R3 and R4).
- A dependent that lapses **keeps its own approval** if its own snapshot is unchanged. When the required relationship is valid again, the Publisher may republish it without re-approval, and only through the Publisher.
- The grace state has no time limit in M2, because the plugin runs no scheduled task. The administration area lists every record awaiting re-approval with its age, so a long-lived grace cannot go unnoticed.
- **Cascades are one level only** and cycle-safe. Each cascade event is audited.
- **The snapshot contains the referenced IDs but not the referenced records' content or hashes.** Otherwise editing a group company would invalidate every project that references it. Each record's own approval covers its own content, and a page shows the **currently approved** version of any related record.

#### Graceful degradation of presentation

When any relationship is suppressed, the page degrades quietly and stays valid:
- the affected item is omitted, and the surrounding text, layout and structured data are not broken;
- if **all** items of a related-content section are unavailable, the whole section, including its heading, is omitted;
- no placeholder text, empty heading, dead link, or count that reveals hidden items is shown;
- the accessor returns only valid relationships, so the theme never needs to test eligibility itself, and the theme with the plugin inactive simply shows no related sections.

### 5.7b Media rights policy

- **Rule:** only media with **confirmed publication rights** may be uploaded for public website use.
- **Enforcement.** A media rights confirmation is a **private record** in the clearance storage, one per attachment: attachment ID, file checksum, a reference to the basis of the rights, the confirming user and the time. The Publisher blocks publication if any image in the snapshot lacks a **current** confirmation.
- **Invalidation.** Any change to an image file, caption, alt text, title, gallery membership or gallery order changes the snapshot and makes the approval Stale. Because the confirmation is bound to the file checksum, **replacing the file also voids the rights confirmation**, so it must be confirmed again.
- **Limit:** the media library cannot be made private. A file uploaded there is reachable by its URL whatever its status, and the plugin cannot prevent an upload or direct access. The rule therefore depends on people following it, and on the confirmation record being checked at publication. A protected media store is a later option.

### 5.7c Ineligible content: cache invalidation and public exposure

**Ineligible** means a record that must not be publicly served: Stale, Revoked, Rejected after publication, unpublished (draft, pending, private or trash), dependency lapsed, deleted, or lacking a matching valid approval. This section covers what the plugin does when a record becomes ineligible, and what it cannot do.

#### Withdrawal procedure

Every transition into ineligibility (the Publisher's revoke or unpublish, R3, R4, R6, a dependency lapse, trash or delete) runs the same **withdrawal procedure**:

1. **Status:** the record is made non-public by the rules in 5.6.
2. **Content epoch:** a private per-record counter is incremented. Every plugin-managed cache key includes the epoch, so stale plugin cache entries become unreachable even if deleting them fails.
3. **WordPress caches:** the post cache, related term caches and the plugin's own caches are cleared. This covers the object cache, including a persistent object cache that uses WordPress's standard cache functions.
4. **Dependents:** caches for records that display this record (found through the reverse relationship query) are cleared and their epochs incremented, so their related-content sections rebuild.
5. **Purge list:** the plugin computes the URLs that an external cache may hold: the record's address, its archive and classification archive pages, the home page where the record could be featured, and the dependents' addresses.
6. **Purge hook:** it fires a documented action with the record ID, the reason and the URL list, so a caching or CDN integration can purge. The hook carries no private data.
7. **Audit and notice:** the audit entry records the reason and the URL list. The approver sees an administrator notice listing the URLs to purge from any external cache.

If cache clearing fails, the record is already non-public, and the read-time check (R5) still stops the origin from serving it. The failure is logged and shown to administrators.

#### Supported public interfaces

| Interface | Behaviour for ineligible content |
|-----------|----------------------------------|
| WordPress page and object caches | Cleared and epoch-versioned as above |
| Single record address | Not found |
| Search results | Excluded. A read-time filter removes any gated record that fails eligibility, even if its status still says published |
| Archives and classification archives | Same filter |
| Related-content sections | Built only through the accessor with a read-time check, and rebuilt on withdrawal |
| Feeds | Gated types are excluded from feeds, comments are disabled, and the same filter applies if a site configuration includes them |
| REST endpoints | None exist in M2. If enabled in a later milestone, permission callbacks must enforce eligibility (section 4.6) |
| oEmbed endpoint | Returns not found for an ineligible record |
| Core XML sitemaps | Ineligible records are excluded from the sitemap query. Sitemap content design stays in M8 |
| Media files | **Not controlled by this section.** See the media caveat in 5.7b |

When a record is **published**, the same hook fires so external caches can refresh the new content. That is not an exposure control.

#### What the plugin cannot guarantee

**Independently managed CDN and hosting caches** (reverse proxies, edge caches, host page caches, browser caches, search-engine caches and archive services) are outside the plugin. They require **integration-specific purging** (the provider's tool or interface, or a manual purge) or short cache lifetimes. **The plugin alone cannot guarantee removal from them.** After a withdrawal, stale copies can still be served from those layers until they expire or are purged. M2 ships the purge hook, the purge list and the administrator notice, and **no CDN or hosting integration**. The owner must record which caching layers exist as part of the hosting record (section 7), and the deployment documentation will include the purge procedure and a check using a test address.

### 5.8 Capabilities and roles

| Capability | Meaning | Default role |
|------------|---------|--------------|
| Per-type edit capabilities | Create and edit drafts, submit for approval. **No** capability to publish or edit published gated records | **Noblen Content Editor** |
| `noblen_manage_clearance` | Record and edit clearance data | **Noblen Clearance Officer** |
| `noblen_approve_publication` | Approve, reject, revoke, and publish gated records | **Noblen Publication Approver** |
| `noblen_view_audit` | Read the audit log and run integrity checks | Publication Approver |

- Editors cannot see clearance details, only a "complete or incomplete" indicator.
- **Separation of duties is on by default.** The Content Editor, Clearance Officer and Publication Approver are distinct roles. An approver cannot approve content they last edited or whose clearance they recorded.
- Administrators are trusted. The capabilities are granted to the dedicated roles at activation, and the owner assigns people.

### 5.9 Workflow

```
Draft -> Clearance recorded -> Submitted for approval -> Approved -> Published (via Publisher)
   ^                                  |                       |            |
   |---------- Rejected --------------|                       |            |
   |------------------------- Revoked / content edited (Stale) -----------|
```

"Approve and publish" is a single convenience action that runs both steps in order and still passes through the Publisher.

### 5.10 Audit trail

**Purpose:** a record of who did what, when, to which version.

**Storage:** the dedicated audit table.

| Question | Answer |
|----------|--------|
| Who can read it? | Users with `noblen_view_audit` (the Publication Approver role by default). Editors and clearance officers cannot |
| Who can create entries? | Only the plugin's audit service, in response to defined events (clearance and media-rights changes, submission, approval, rejection, revocation, publication, automatic reverts and dependency lapses, integrity checks, migrations, settings that affect the gate). There is no interface or API for writing arbitrary entries |
| Can application users edit or delete entries? | **No.** The plugin has no update or delete code path and grants no capability for it. This is **application-level append-only behaviour**. The database account that WordPress uses can technically modify rows, so this is **not immutability** |
| What does each entry record? | **Who** (user ID and a login snapshot), **when** (UTC time), **what changed** (the event type and the names of the snapshot components affected, never private content), and **which approval snapshot** (approval ID and hash) |
| Approval and revocation | An approval entry carries the snapshot hash and format version. A revocation entry references the approval entry and a reason code. Automatic reverts are recorded with their reason |
| Retention | The plugin never purges audit rows, including on uninstall by default |

**Statement of limits.** A privileged database administrator, especially one who also has access to the application configuration, can potentially alter or recompute audit records. The audit trail raises the cost and the visibility of tampering. It does not prevent it.

**Operating procedure**
1. **Audit key.** The hash chain is keyed with a secret held **outside the database and the repository**. The key is required, and the full custody, loss, compromise, rotation and recovery procedure is in section 5.10a.
2. **Key identifiers.** Every entry records the identifier and a fingerprint (never the key) of the key that signed it.
3. **Periodic external exports.** An approver exports the audit log, including the latest chain value, to a **separate protected location** that is not on the same server and database. The owner sets the cadence. At minimum: before any upgrade, migration or restore, and after any batch of approvals. The plugin runs no scheduled task, so exporting is a manual owner step.
4. **Integrity verification.** An administrator-run function checks the chain for gaps and mismatches, and checks that every Approved approval has a matching audit entry. It is run after upgrades, after restores, and on request, and each run is itself logged.
5. **Detecting a restore.** Compare the plugin's latest entry with the most recent external export.

**Limits of the chain.** It detects changes in the middle (modified, removed or reordered entries). It does not detect truncation at the end unless the owner holds an external copy of the latest value, and it does not stop someone who holds both the key and the database.

**Backup and restoration:** restoring a database backup restores the audit history to that point and **silently loses later entries**, and can bring back older approvals. After a restore, run the integrity tool, compare it with the latest external export, and record the restore outside the site.

### 5.10a Audit key custody, loss, compromise, rotation and recovery

**Principle: the plugin never creates, derives or substitutes an audit key on its own.** A missing or wrong key is never repaired silently. A new key enters the system only through an explicit administrator procedure that is confirmed and recorded.

#### Audit integrity states

| State | Meaning | New approvals and publications | Already-published approved content |
|-------|---------|-------------------------------|------------------------------------|
| **Verified** | The configured key matches the chain and the chain verifies | Allowed | Served |
| **Key missing** | No key is configured | **Blocked** | Served |
| **Key invalid** | The configured key's fingerprint does not match the key recorded in the chain | **Blocked** | Served |
| **Chain broken** | The chain fails verification (gap, mismatch, reorder) | **Blocked** | Served |
| **Rotation incomplete** | A rotation started and did not finish | **Blocked** | Served |
| **Recovery incomplete** | A loss recovery started and its completion conditions are not all met | **Blocked** | Served |
| **Approvals suspended** | An administrator suspended approvals (for example on suspected compromise) | **Blocked** | Served |
| **Unknown** | The audit store cannot be read | **Blocked** | Served |

**New approvals and publications are blocked whenever audit integrity cannot be established, which means in every state except Verified.** Previously published, validly approved content may remain available, as set by the verification-store failure policy (section 15, item 7). An audit failure does not by itself unpublish anything, and every blocked action explains why.

#### Custody and backup

- **Generation:** the owner generates a long random key with a trusted tool outside the plugin. The plugin has no key-generation code.
- **Storage:** protected site configuration (a constant or an environment setting), outside the web-accessible area where hosting allows, with restrictive file permissions. Never in the database, the repository, a ticket, an email or a chat.
- **Backup:** **at least two independent secure copies** held by different authorised people in separate locations (for example a secrets vault and a sealed offline copy). A record of who holds copies and when they last confirmed access is kept outside the site.
- **Fingerprint:** a short fingerprint of each key is recorded in documentation and in audit entries. It identifies a key without revealing it.
- **Site backups:** a site backup that includes the configuration file holds the key together with the database, so it is sensitive. Restoring a site without its key produces the **Key missing** state.

#### Key loss and recovery (no custody copy exists)

**Total loss** means that no custody copy of the key can be found. The old key cannot be recreated and can never again authenticate anything. **Recovery therefore cannot restore the integrity of earlier entries, and the system must never appear to.**

1. **Search the custody copies first.** Restoring the same key into the configuration returns the state to Verified.
2. **Explicit owner authorisation.** Recovery starts only with all of these, recorded: the owner's **sign-off reference** (a document identifier held outside the site), the named **recovery authority** (a person), a stated reason, and a typed confirmation by an administrator. When separation of duties is on, the administrator carrying out the action cannot be the person named as the recovery authority. The plugin records these as statements. It cannot prove them cryptographically.
3. **Preserve history.** The audit log is exported in full before anything changes. No existing row is altered, re-signed, re-hashed or moved.
4. **Compare with the last independent export,** where one exists. The plugin compares the exported chain value and entries with the database and records the result: **matches up to the export point**, **differs** (with the position of the first difference), or **no export available**.
5. **Write one recovery marker.** It is signed **only by the new key**, and it records:
   - the **old key identifier and fingerprint** (taken from the last entry the old key signed);
   - the **new key identifier and fingerprint**;
   - the **recovery authority** and the owner sign-off reference, and the executing user;
   - the **UTC timestamp**;
   - the chain value found in the database at that moment;
   - the last external export used, and the comparison result;
   - the stated reason.
   The marker authenticates **only itself and later entries**. It makes no claim about earlier entries, and **the new key never re-signs or vouches for them.**
6. **Classify history honestly.** Every integrity report and export labels earlier entries as one of:
   - **Verified:** signed by a key that is available (the new segment, and any older segment whose key survives).
   - **Corroborated by an external export, not verified:** entries that match an independent export, up to the export point. Reported as "matches the export dated X", **never** as verified.
   - **Unverifiable:** all other entries signed by the lost key, including everything after the last export. Changes made between the last export and the loss cannot be detected.
7. **Approvals stay blocked** (state **Recovery incomplete**) until the marker is written, the comparison is recorded, verification of the new segment returns Verified, and the owner records completion. The earlier segments continue to be reported as corroborated or unverifiable.
8. **If the old key is found later,** the owner adds it as a **verification-only** key. The integrity tool then re-verifies the older segment and reclassifies it, without rewriting anything, and the discovery is logged.
9. **Never automatic and never silent.** No plugin code path starts, completes or hides a recovery.

#### Key compromise (suspected or confirmed)

1. **Suspend approvals** immediately (state Approvals suspended).
2. Export the audit log, and compare with the latest trusted external export to find the last trustworthy entry.
3. Rotate to a new key (below), with the reason "compromise" recorded.
4. The rotation entry names the suspected range, and entries in it are reported as **suspect**. No row is altered.
5. The owner reviews approvals made in the suspect range and decides which to re-verify or re-approve.
6. The old key is retained for verification only and marked compromised in the owner's records.

#### Planned key rotation

**Before**
1. Run the integrity check. It must return **Verified**.
2. Export the audit log, including the chain value.
3. Confirm that the old key has at least two custody copies.

**Rotate**
4. The owner adds the **new** key, with a new identifier, to the configuration **alongside** the old key, which stays available for verification.
5. An administrator runs "rotate audit key", with a typed confirmation. The plugin first verifies the whole chain with the old key. If that fails, it stops.
6. It writes a **rotation entry signed with the old key**, naming the new identifier and fingerprint and the chain value.
7. It writes a **confirmation entry signed with the new key**, referencing the rotation entry, which proves that both keys are held. Later entries use the new key.

**After**
8. Run the integrity check across the whole chain using both keys. It must return **Verified**.
9. Export the audit log again.
10. **Keep the old key** in custody for as long as its entries must stay verifiable. Destroying it makes those entries unverifiable, which is a recorded owner decision.

If steps 6 and 7 are interrupted, the state is **Rotation incomplete** until the owner completes or abandons the rotation through the same explicit procedure.

#### Preservation of history

Rotation and recovery **never rewrite, re-sign, re-hash, move or delete** any existing audit row. The chain simply continues across keys, and the history is verified segment by segment with the key identified in each entry.

### 5.11 Database lifecycle

| Stage | Behaviour |
|-------|-----------|
| Tables | `clearance`, `approvals` and `audit_log`, created with the site's table prefix and standard character set |
| **Activation** | Checks PHP and WordPress versions and database capability. Creates tables with WordPress's additive schema function. Stores the schema version only after success. If creation fails, activation stops cleanly with a plain message, and the plugin does not run half-installed |
| **Schema version tracking** | A stored schema version and a migration state (idle, running, failed with the failing step) |
| **Safe upgrades** | Numbered, idempotent, additive-first migrations (add, backfill, switch). Destructive changes only in a later release after verified backfill and with explicit owner confirmation. Upgrades run only from the administration area, under a lock that prevents concurrent runs, and each step is logged |
| **Backup before migrations** | A migration that changes the schema cannot start until an administrator confirms a current database backup. The confirmation is logged. Release notes list every schema change |
| **Failed migration** | Stops at the failing step, records the state, and does not retry automatically. Because database definition changes cannot be rolled back, recovery is by restoring the pre-migration backup or re-running after a fix. While a migration is pending or failed, **new approvals and publications are blocked**. Already-published approved records continue to be served, and an administrator notice appears |
| **Deactivation** | Removes nothing. It only clears locks and flushes rewrite rules |
| **Upgrades and theme changes** | Never delete business records, clearance records or audit history |
| **Uninstall (explicit behaviour)** | **Default: delete nothing.** An administrator-only option "Remove plugin data on uninstall" (off by default, with a typed confirmation and a prompt to export the audit log first) removes the plugin's own tables and settings only. Business records (the posts) are **never** deleted automatically, and remain recoverable by reinstalling |
| Multisite | Not supported in M2. Network activation is blocked with a message |

### 5.12 Seed data and repository hygiene

Seed content is optional, creates **draft** records with titles only from the names-only inventory, and carries no clearance data, no notes and no dates. No clearance record, evidence reference or sentinel value is committed to the repository.

## 6. Naming compliance (restricted business name)

- **Permitted public references:** "OnlineMart" and "Online Marketplace" only.
- **The restricted name is never** in source, documentation, comments, commit messages, repository files, screenshots, CI logs, error messages or generated content. This project does not reproduce it, record it or try to discover it.
- **The blocked-term list is held privately by the owner, outside the repository.** No list is created or committed.

### Owner-controlled local scan (primary)

- `bin/naming-scan.sh` reads the owner's list from a file path given by an environment variable on the owner's machine. The file is never inside the repository.
- It normalises case, spacing and punctuation, then scans tracked files, file names, commit messages and branch names.
- **Default output is pass or fail plus a count.** It never prints the term. A local-only verbose mode prints file and line positions (never the term) for the owner's use.
- Screenshots and images are reviewed manually by the owner, because text in images is not machine-scanned.

### Hosted check

**Not used in M2** (owner decision). The owner-controlled local scan is the only naming check. If a hosted check is ever reconsidered, it must be a secure pass or fail mechanism that never reveals the term, and it needs a new approval.

## 7. PHP and WordPress compatibility

**Production hosting is unconfirmed.** No production compatibility is claimed.

| Item | Position |
|------|----------|
| **Preferred minimum PHP** | **8.3** (`Requires PHP: 8.3`) |
| **Primary test targets** | **PHP 8.3 and 8.4** |
| PHP 8.5 | Forward-compatibility testing where WordPress supports it. Its support label is checked at kickoff |
| PHP 8.2 | **Compatibility only, and only if a verified hosting constraint requires it.** It is not a target. If used, the owner records the decision and the end of its security support (31 Dec 2026, per php.net data as reported by third-party trackers, to be re-checked) |
| WordPress minimum | To be fixed at kickoff from the official compatibility handbook. It must fully support the chosen PHP baseline. Core's published guidance recommends 8.3 as the minimum PHP |
| Header fields | `Requires PHP`, `Requires at least`, `Tested up to`, set from test results only |
| Coding rule | No deprecated PHP or WordPress functions, enforced with PHPCompatibilityWP |

### Compatibility test matrix (to be run, none run yet)

| | WordPress minimum | WordPress current stable |
|---|---|---|
| PHP 8.3 | planned (primary) | planned (primary) |
| PHP 8.4 | planned (primary) | planned (primary) |
| PHP 8.5 | | informational, where supported |
| PHP 8.2 | compatibility only, and only if a verified constraint requires it | same |

Each cell records activation, the migration run, the automated test suite and a front-end smoke test, with errors and deprecation notices captured.

### Hosting compatibility decision (prerequisite to implementation)

Before implementation starts, the owner records **one** of these in writing:
- **(a)** the verified PHP and WordPress versions of the intended hosting (from the host's control panel or support), or
- **(b)** a decision to proceed on the PHP 8.3 baseline while production compatibility remains **unverified and flagged** in every report.

Until then, no work starts. A compatibility decision document will be created in M2 to hold this record.

## 8. Brand system and theme foundation (retained)

- **Palette:** deep navy `#0A1B3D`, electric blue `#1F6BFF`, white, light grey `#F2F5FA`, text slate, restrained teal `#12A39A`. No gold or yellow.
- **Tokens:** CSS custom properties for colour, type scale, spacing, radii, shadows, breakpoints and motion.
- **Accessibility:** WCAG AA contrast, visible focus states, keyboard-operable navigation, reduced-motion support.
- **Typography:** self-hosted, licence-checked fonts and a responsive type scale. No third-party font requests.
- **Components:** buttons, cards, status badges, section headers, grids, navigation, breadcrumbs.
- **Logo:** an approved logo if supplied. Otherwise a neutral text wordmark placeholder that is easy to replace. No logo is invented.
- **Third-party requests:** none on page load.

### Homepage skeleton (CMS-fed)

Sections: hero, group introduction, group companies, industries, solutions, featured projects, products and platforms, Insights, partnerships, contact call to action. Section content comes from the plugin. Section order and visibility are theme settings. **A section with no data renders nothing.** No fabricated client names, partnerships, outcomes, statistics, awards, certificates or company claims appear. Only verified content, or unpublished drafts, is used.

## 9. Packaging, installation and recovery

### Deliverables

1. Installable **theme ZIP**.
2. Installable **plugin ZIP**.
3. Installation and activation instructions (`INSTALLATION.md`).
4. Compatibility documentation.
5. Test evidence that the theme works with the plugin.
6. Test evidence that content remains after theme switching.
7. A clean WordPress installation test.
8. A recovery procedure.

### Packaging rules

`bin/build-packages.sh` builds each ZIP from tracked files only, excludes tests and tooling, and writes file lists and checksums. A built package must install on a clean WordPress.

### Recovery procedure (to be written and rehearsed)

- **Activation guard:** both components check PHP and WordPress versions on activation and stop with a plain message instead of a fatal error.
- **Plugin fails or breaks the site:** use WordPress recovery mode, rename the plugin folder via file manager or SFTP, or deactivate with WP-CLI. Data stays in the database.
- **Theme fails:** rename the theme folder so WordPress falls back to a default theme. Content stays because the plugin owns it.
- **Failed migration:** follow section 5.11, restoring the pre-migration backup if needed.
- **Audit key missing, invalid, lost or compromised:** follow section 5.10a. Never regenerate a key silently.
- **Before any install or upgrade:** back up files and database. Backups containing the plugin tables are confidential.

## 10. Acceptance criteria

**Nothing below has been tested. "A" means automated, "M" means manual.**

| # | Criterion | Type | Method |
|---|-----------|------|--------|
| 1 | Theme installs and activates | A + M | WP-CLI install in a clean site with an empty error log. Dashboard walk-through |
| 2 | Plugin installs and activates | A + M | Same |
| 3 | Both work together | A + M | Integration tests, front-end smoke test |
| 4 | Theme deactivation does not delete structured content | A | Record counts and settings before and after switching to a default theme. Content still viewable |
| 5 | All five custom post types work | A | Create, read, update, trash, restore for each |
| 6 | Dashboard content can be created and edited | M + A | Admin walk-through with a browser script |
| 7 | Content relationships work | A | Relationship tests, integrity rules, reverse queries |
| 8 | Private clearance data cannot be retrieved publicly | A | Exposure probe matrix with sentinel values |
| 9 | Unauthorised users cannot approve publication | A | Role and capability matrix |
| 10 | Incomplete clearance blocks publication server-side | A + M | Attempt publish through each write path in section 5.6 |
| 11 | Empty homepage sections stay hidden | A | Render with an empty database and with partial data |
| 12 | No unfinished placeholders appear publicly | A + M | Scan rendered pages for placeholder tokens, plus manual review |
| 13 | Layout works at 360, 768 and 1280 px | A + M | Screenshots, no horizontal scroll check, manual review |
| 14 | Keyboard navigation works | M (+ A partial) | Manual keyboard pass, supported by automated checks |
| 15 | WCAG AA contrast met | A + M | Token contrast check, axe checks, manual review |
| 16 | PHP lint and PHPCS pass | A | CI for both components |
| 17 | Naming compliance passes without exposing the term | A (owner-run) + M | Local scan pass or fail, manual screenshot review by the owner |
| 18 | No unnecessary third-party requests | A | Browser network log has no third-party hosts |
| 19 | Both ZIPs install on a clean WordPress | A + M | Clean-environment install of both packages |
| 20 | Installation and recovery instructions documented | M + A | Document review, link check, rehearsal of recovery steps |
| 21 | **Classic Editor compatibility** | A + M | Each custom type opens in the classic editor with the block editor enabled site-wide, with and without the Classic Editor plugin, and works with REST off. Structured fields save and validate. Standard posts are unaffected |
| 22 | **Private approval status isolation** | A | Approval, clearance and audit values never appear in HTML, REST, feeds, search, exports or accessor output, and are unreadable to editors and anonymous users |
| 23 | **Snapshot invalidation after editing public content** | A | For every snapshot component (each text field, display switch, relationship, status, term, featured image, gallery order, media file replacement, alt text, video URL, presentation flag) a change makes the approval Stale and the record non-public. A registry test confirms no public field is missing from the snapshot |
| 24 | **Failed publication attempts through alternative write paths** | A | Each path in section 5.6 (editor, quick edit, bulk edit, scheduling, REST if enabled for the test, XML-RPC, WP-CLI, programmatic updates, restore from trash, import) ends with a draft, and an anonymous request for the record is not found. The Publisher succeeds only with a valid approval |
| 25 | **Database migration and upgrade integrity** | A + M | Fresh install, upgrade from earlier schema fixtures, repeated runs (idempotent), simulated failed migration with publication blocked and no data lost, backup-confirmation step recorded |
| 26 | **Preservation of structured records through theme switching and plugin upgrades** | A | Counts and content hashes of posts, metadata, relationships, clearance, approvals and audit entries are identical before and after theme switch, plugin deactivate and reactivate, and plugin upgrade |
| 27 | **Audit trail behaviour** | A | No code path updates or deletes entries. Only authorised roles can read. Each entry records who, when, what changed and which snapshot. Chain verification detects a modified, removed or reordered middle entry. A missing audit key blocks approvals. Export includes the chain value. The limits in section 5.10 are documented |
| 28 | **Related-record dependency mechanics** | A | Cascades stop at one level and cannot loop. A dependent that lapses keeps its own approval and can be republished by the Publisher once its required relationship is valid. The snapshot holds referenced IDs but not their content, so editing a related record does not invalidate dependents. A related record's currently approved content is what appears on a page |
| 29 | **Media rights confirmation and invalidation** | A | Publication is blocked for an image without a current rights confirmation. Changing a file, caption, alt text, title, gallery membership or order makes the approval Stale. Replacing a file voids its confirmation |
| 30 | **Publisher grant scoping** | A | A grant for record A cannot publish record B, another snapshot version, or run as another user. A grant is single use and cannot be requested without an authenticated approver. There is no global flag, and a request cannot supply a grant |
| 31 | **Cache invalidation** | A + M | For every ineligibility trigger, with the core object cache and a persistent object-cache test drop-in: the record's cache entries are cleared, plugin cache entries are unreachable through the incremented epoch, dependents' related-content caches are rebuilt, the purge action fires once with the expected URL list and no private data, the administrator notice lists the URLs, and the audit entry records the reason and list. **Manual:** if a CDN or hosting cache is in use, the owner confirms the purge procedure on a test address. The plugin's tests cannot prove CDN or hosting behaviour |
| 32 | **Ineligible content is not served through supported public interfaces** | A | For each ineligible state (Stale, Revoked, dependency lapsed, draft, pending, trash, and a missing or mismatched approval after a simulated direct database change), the record is absent from its single address, main search, archives and classification archives, related-content sections, feeds, core sitemap queries, the oEmbed endpoint and REST (no routes, and not found or forbidden if enabled for the test), and the accessor returns nothing. Previously published valid content stays available when approvals cannot be written |
| 33 | **Required and optional relationship behaviour** | A | A registry test fails if any relationship field lacks a class. A missing or unavailable required relationship blocks publication. After publication, revoking, rejecting, unpublishing, trashing or deleting a required related record reverts the dependent with the reason audited. The Stale grace state behaves as in 5.7a and ends correctly. A missing or unavailable optional relationship neither blocks publication nor unpublishes anything. Presentation degrades gracefully with and without the plugin (no empty headings, dead links, placeholders or count leaks) |
| 34 | **Audit key: missing, invalid, rotation and recovery** | A + M | **Missing key:** approvals and publications are blocked, nothing is generated, published content stays available, and an administrator notice appears. **Invalid key:** a fingerprint mismatch blocks them. **Wrong key for a historical segment** is reported as unverifiable. **Rotation:** verification passes before and after across both keys, existing rows are byte-identical before and after, an interrupted rotation leaves the state Rotation incomplete, and old entries remain verifiable. **Recovery after loss:** it requires a recorded owner sign-off reference and a named recovery authority (a different person from the executing administrator when separation of duties is on). It preserves every existing row, compares with the last external export and records a match, a difference or no export, and writes a marker signed only by the new key that records the old and new key identifiers, the recovery authority, the timestamp and the comparison result. It never re-signs historical entries, reports earlier entries only as corroborated or unverifiable and never as verified, keeps approvals blocked in the Recovery incomplete state until the new segment verifies and the owner records completion, reclassifies correctly if the old key is later added as verification-only, and never runs silently. **Compromise:** suspending approvals blocks them at once. A static check confirms that the plugin contains no key-generation code. **Manual:** the custody and recovery steps are rehearsed with the owner using a backup copy |


### Allocation of criteria to implementation stages

| Stage | Criteria |
|-------|----------|
| M2-A core plugin | 2, 5, 6 (content), 7, 8, 9, 10, 16 (plugin), 17 (plugin), 21, 22, 23, 24, 25, 27, 28, 29, 30, 31, 32, 33 (plugin behaviour), 34 |
| M2-B theme and design | 1, 11, 13, 14, 15, 16 (theme), 17 (theme), 18, 33 (presentation degradation) |
| M2-C integration | 3, 4, 6 (end to end), 12, and re-checks of 13, 14, 15, 18, 31, 32 and 33 on the integrated site |
| M2-D packaging and release review | 19, 20, 26 (including plugin upgrade), final run of 16 and 17, and the manual items of 31 and 34 |

## 11. Testing strategy

- **Static:** PHP syntax across supported versions, PHPCS with WordPress standards, PHPCompatibilityWP, structure check, documentation link check, naming scan.
- **Unit and integration (PHPUnit with the WordPress test suite):** post types, relationships, sanitisation, capabilities, the gate and Publisher, snapshot construction and completeness, accessor allowlist.
- **Security:** the exposure matrix, role matrix, tamper attempts on nonces and capabilities, the enforcement matrix in section 5.6.
- **Database:** fresh install, upgrade fixtures, idempotency, failed-migration simulation, preservation checks.
- **Audit:** append-only behaviour, access control, key handling and rotation, chain verification, tamper detection, export.
- **Dependencies and media:** reference validity at publication and over time, cascade limits, rights confirmation and its invalidation.
- **Cache and exposure:** invalidation on every ineligibility trigger, the public-interface matrix in 5.7c, and a persistent object-cache test drop-in.
- **Relationships:** class registry completeness, required and optional behaviours, graceful degradation.
- **Audit key procedures:** missing, invalid, rotation, interrupted rotation, recovery after loss, compromise handling, and absence of key-generation code.
- **Grant scoping:** attempts to reuse, transfer or forge a grant, and to publish without an authenticated approver.
- **End-to-end (Playwright):** dashboard workflows in the classic editor, front-end rendering, empty-state rendering, third-party request check.
- **Accessibility and visual:** axe plus a manual keyboard pass, and screenshots at 360, 768 and 1280 px.
- **Installation:** clean-environment install of both packages, theme switch test, recovery rehearsal.
- **Evidence:** results go in `QA-REPORT.md` with the exact commit, and progress in `PROJECT-PROGRESS.md`. Failures are reported as failures.

## 12. Dependencies

| Type | Item |
|------|------|
| Runtime | WordPress and PHP only. No Composer or npm packages ship in either component |
| Development (**not installed, needs approval**) | Composer with PHPCS, WordPress Coding Standards, PHPCompatibilityWP, PHPUnit and polyfills. Node with Playwright and axe-core. A WordPress test environment (`wp-env` with Docker, or WordPress Playground) |
| Environment (**needs approval**) | Network access to package registries and WordPress download hosts, which this container's policy may block |
| From the owner | The hosting compatibility record (section 7), font choice, logo and brand files, approved imagery, a WordPress export, role assignments, the audit key and its custody arrangements (section 5.10a), the hosting and CDN caching layers in use, and the items still needed in section 15 |

## 13. Risks and mitigations

| Risk | Mitigation |
|------|------------|
| Hosting runs an older PHP | Hosting record is a prerequisite. Activation guards. 8.2 only as a recorded exception |
| Test environment cannot be set up here | Static and unit checks run anyway. Install tests are reported as not run if blocked |
| Clearance data leaks through an unforeseen path | Separate storage, sentinel tests across all paths, no files on the site |
| Backups expose clearance data | Documented. Treat backups as confidential |
| A privileged user or database administrator bypasses the gate or edits audit rows | Documented threat model. Hash chain with an external export. No claim of protection |
| A publication path bypasses the gate | Single Publisher, fail-closed default, safety net, read-time check, tests for every path |
| A live record goes offline after an edit | Intended and documented, and a decision item |
| Migration fails midway | Additive migrations, backup confirmation, publication blocked until resolved, recovery procedure |
| Media files are reachable by URL before approval | Upload approved images only. Documented. Protected media is a later option |
| A related-record change silently affects dependents | Dependency rules, read-time verification, audited cascades |
| Editing a group company hides its link on dependent pages | Documented. Accepted for the strict approach |
| Audit key lost or compromised | Two independent custody copies, the recovery and compromise procedures in 5.10a, approvals blocked until integrity is established, and history never rewritten |
| CDN or hosting cache keeps serving withdrawn content | Purge hook, purge list and notice. The owner records the caching layers and sets up integration-specific purging or short lifetimes. The plugin cannot guarantee it |
| A required relationship stays in grace for a long time | The list of records awaiting re-approval, with ages |
| Image uploaded without rights | Policy, rights confirmation required at publication, file-bound confirmation |
| Placeholder content reaches public pages | Drafts-only seed, empty sections hidden, placeholder scan |
| Restricted name enters the project | Owner-controlled local scan over files, history and commit messages. Manual screenshot review. No hosted check in M2 |
| Missing brand assets | Marked placeholder wordmark, easy to replace |
| Scope growth | Section 1 boundaries and the approval rule |

## 14. Implementation stages, governance and approval gates

### Implementation stages (each ends with a report and a review stop)

| Stage | Scope | Required outcome |
|-------|-------|------------------|
| **M2-A** | Core plugin, content architecture, database, permissions and publication controls | Secure and functional content-management foundation |
| **M2-B** | WordPress theme, design system, navigation and homepage | Complete branded website foundation |
| **M2-C** | Theme and plugin integration, security, responsive and accessibility testing | Verified integrated website |
| **M2-D** | Packaging, documentation, QA evidence and release review | Installable theme and plugin packages |

**Standing rule: the four stages are never implemented in one uninterrupted operation.** Each stage produces a report (what was completed, what remains, test results, exact commit) and **waits for owner review before the next stage begins.**

### Approval gates

| Gate | Decision | Needed before |
|------|----------|---------------|
| **P1** | Hosting compatibility decision recorded (section 7) | Any implementation |
| G0 | Explicit owner approval of this final plan. **Approved** | Any M2 work |
| G0b | Separate approval for a documentation-only commit of the approved plan | Committing the plan |
| G1 | Approval to install development dependencies and allow network hosts | Installing anything or running test tooling |
| G2 | Confirmation of the working branch and authorisation for commits and pushes | The first implementation commit |
| SG-A, SG-B, SG-C | Review of the stage report | Starting the next stage |
| SG-D | M2 completion review with evidence | Starting M3 |
| G5 | Merge decisions | Always the owner's alone |

### Gate status at Revision 5.1

| Gate | Status |
|------|--------|
| P1 | Option (b) recorded with the G0 approval: PHP 8.3 development baseline, production compatibility unverified |
| G0 | **Approved.** Revision 5.1 is the approved M2 technical baseline |
| G0b | Not authorised |
| G1, G2 | Not authorised |
| SG-A, SG-B, SG-C, SG-D | Not started |
| G5 | Not started |

Standing prohibitions: no production changes, no DNS or hosting changes, no live-database changes, no installation on production, no deployment, no merge to `main`, no scheduled routines, no unapproved commits or pushes. Each milestone records the exact commit and the test results. The work stays traceable and reversible, and the existing M1 branch and PR #1 are preserved.

## 15. Decisions

### Recommended defaults recorded in this copy (confirmed by approving it)

| # | Decision | Default recorded |
|---|----------|------------------|
| 1 | PHP version | **PHP 8.3 development baseline** (the P1 option (b) path, recorded with the G0 approval). Production compatibility stays unverified until hosting is checked |
| 2 | Minimum WordPress | Set at implementation kickoff from the official compatibility requirements and the hosting environment |
| 3 | Editing live records | **Strict approach for M2:** edited records go offline pending re-approval. Versioned publishing is revisited in a later milestone |
| 4 | Audit key | A protected key in site configuration, required before approvals work. Custody, loss, compromise, rotation and recovery as in 5.10a. Periodic external audit exports |
| 5 | Evidence storage | References only in M2. Protected file storage considered later |
| 6 | Separation of duties | **On by default.** Editor, Clearance Officer and Publication Approver are distinct roles |
| 7 | Verification store unavailable, or audit integrity not established | Previously approved content stays online. New approvals and publications are blocked until integrity is established. The failure is logged where possible and always shown to administrators |
| 8 | Hosted naming check | None in M2. Owner-controlled local scan only |
| 9 | Fonts and logo | The existing Noblen logo where available (a neutral placeholder wordmark until supplied), and licensed self-hosted typography |
| 10 | Uninstall | Deletion is opt-in with explicit confirmation and an audit export. Business records are never deleted automatically |
| 11 | Multisite | Excluded from M2 |
| 12 | Branch | The existing branch `claude/cool-feynman-6n4u7n`, preserving M1 and PR #1 |
| 13 | Gate scope | All five content types use the Publisher, with a lighter clearance checklist for companies, industries and services |
| 14 | Relationship classes | Required: the responsible business unit of a project or product. Everything else optional (section 4.1) |
| 15 | **Stale required relationship** (accepted in the Revision 5 technical review) | **Grace state** (5.7a): the dependent stays online with the relationship hidden while the related record awaits re-approval. It lapses only on a deliberate revoke, reject, unpublish, trash or delete. *Alternative:* lapse on any unavailability, which would take down every dependent whenever a group company is edited |
| 16 | **Optional relationship at publication** (accepted in the Revision 5 technical review) | Unavailable optional relationships **warn but do not block** publication. _This clarifies Revision 4, which blocked publication on any unavailable reference_ |
| 17 | **External caches** (accepted in the Revision 5 technical review) | M2 ships the purge hook, purge list and notice, and no CDN or hosting integration. Purging external caches is the owner's responsibility |
| 18 | **Audit-key custody and recovery** | Two independently secured key copies. Recovery after total loss follows 5.10a and needs the owner's recorded authorisation and a named recovery authority. The three operational roles are assigned before production launch |

### Operational items for later

None of these blocks development of the foundation. The role assignments and audit-key custody must be settled **before production launch**.

1. Final PHP and WordPress versions of the production hosting, and which caching layers exist (page cache, CDN, reverse proxy).
2. Noblen logo source files and typography.
3. The names of the three role holders.
4. The named audit-key custodian and recovery approver, and who holds the second key copy.

### Approvals

**G0 (final plan approval) has been given. G0b (documentation-only commit) is a separate approval and has not been given.** G1, G2 and SG-A to SG-D also remain unauthorised.

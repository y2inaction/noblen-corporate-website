# Owner Content and Asset Requirements

Technical development does not wait for these. The site shows only structure until content is entered in the dashboard and approved through the publication gate. Nothing here has been invented: every item below is something only the owner can supply or verify.

## A. Required before public launch

| # | Item | Where it goes | Notes |
|---|------|---------------|-------|
| 1 | Logo files (SVG and PNG), favicon and site icon | Appearance, Customize, Site Identity | Without a logo the site shows a plain text wordmark |
| 2 | Legal name, registration status and group structure (how Noblen Technologies Limited relates to Noblen Media Services, Noblen AI Solutions and Noblen Property Limited) | Noblen settings, About page | Do not publish registration or certificate numbers |
| 3 | Verified contact details: email, phone, WhatsApp, address | Noblen, Settings | Empty fields never appear on the site |
| 4 | Social account URLs (only accounts the owner controls) | Noblen, Settings | |
| 5 | Homepage headline, introduction, group introduction and closing call to action | Noblen, Settings | Wording is the owner's. The creative direction line in the brief is a candidate |
| 6 | Business unit descriptions (at least the three named units), each with owner review | Business units (Companies) | Names-only drafts are available through "Create starter drafts" |
| 7 | The ten industry pages: names, status (active operation, available service, strategic growth area) and wording | Industries | The list of ten is not in the repository, so none were seeded |
| 8 | Services and solutions list | Services | |
| 9 | Privacy policy and terms, reviewed for the business | Pages, assigned to the Legal menu | Legal review is the owner's responsibility |
| 10 | A site audit key, configured outside the database | `wp-config.php`, constants `NOBLEN_AUDIT_KEYS` and `NOBLEN_AUDIT_KEY_ID` | Approvals stay blocked until set. The plugin never creates a key. Keep a custody copy |
| 11 | Decision on hosting, backups and a person who holds each approval role (editor, clearance officer, approver) | Users | The approver must differ from the last editor and the clearance officer |
| 12 | The owner-run naming scan, using the owner's private term list | `scripts/naming-scan.sh` | Result is PASS or FAIL only |
| 13 | Brand fonts, if any, with licence for self-hosting | Theme | The site uses the system font stack until then |

## B. Optional improvements

- Photography and approved imagery (each image needs a recorded rights reference before approval).
- Leadership and team pages (only with the consent of the people shown; none are drafted).
- Case studies for verified projects (twelve structured fields; outcomes only when verified).
- Insights articles and announcements.
- Partnership and contact **forms** (planned for milestone M7; M2 offers a mailto call to action only).
- Further SEO and performance tuning (milestone M8). Basic fundamentals now exist: meta description, Open Graph and Organization data built only from the settings the owner enters, plus the WordPress core sitemap.
- A hosting or CDN purge integration (the plugin supplies the purge list and a hook).

## C. May remain unpublished until verified

These stay as unpublished drafts. Do not publish them until status and evidence are confirmed.

- Products and platforms: CIDU, OnlineMart, ArewaGo and the Noblen AI Platform (what exists today versus roadmap).
- The Operating Systems family.
- Property projects and corporate initiatives.
- Any "Level B" project from the project register.
- Any outcome, statistic, award, certification or client name.
- Client logos and photographs, unless permission is recorded.

The marketplace may appear only as "OnlineMart" or "Online Marketplace".

## D. Added in the completion phase

| # | Item | Why it is the owner's | Notes |
|---|------|-----------------------|-------|
| 14 | **Master logo file**: SVG (preferred) or PNG with a transparent background, and which lockup is approved (horizontal, stacked, or icon only) | A brand sheet was shared as an image only; the repository must use the exact approved artwork and never a redrawn one | Upload through Customize, Site Identity, or supply for commit as an asset. The green swoosh stays in the logo only |
| 15 | Confirmation that the sector icons and the four values on the brand sheet are **verified** and may be published, and what each icon should link to | They read as business claims | The theme is ready to show them; none are published until confirmed |
| 16 | Whether any green accent may be used in the interface | The approved palette is navy, blue and teal; the brand sheet adds a green | Gold is excluded |
| 17 | A decision to add a **partner record type** (with clearance) if the partnerships section is wanted | It changes the content model; no verified partner data exists, so the section renders nothing | See `REMAINING-WORK-REGISTER.md`, item T4 |
| 18 | Domain, hosting, HTTPS certificate, backup service and CDN or page cache choices, and who operates them | Hosting and any cost are the owner's commitment | Production deployment, DNS and database changes stay reserved gates |
| 19 | HTTP security headers (policy for frames, content sources, HSTS) applied at the host or CDN | They depend on the real hosting | See `SECURITY-REVIEW.md` |
| 20 | The named **independent reviewer(s)** for the 17 manual acceptance records, and the owner for the owner-run items | Independence cannot be supplied by the implementer | See `ACCEPTANCE-RECORDS-GUIDE.md` |
| 21 | Owner decisions in `owner-acceptance-decisions.yml` for C21-021, C21-022 and C21-M01 | Acceptance is the owner's | All three are pending |
| 22 | For C26-003 to C26-006: the registered upgrade source and target, an independent reviewer, an approved attestation and an authorised hosted run | They cannot be produced by the implementer | See `UPGRADE-VERIFICATION.md` |
| 23 | A real capture to replace the theme `screenshot.png` design preview, once approved content exists | The current image uses structural labels only | |

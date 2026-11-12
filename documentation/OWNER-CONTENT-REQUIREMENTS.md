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
- Schema.org, sitemap design and performance tuning (milestone M8).
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

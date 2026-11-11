# Website Architecture & Technical Decisions

_Status: M1, revision 5. Planning only. No implementation has started. Aligned with M2 plan Revision 5.1 (theme and core plugin separated, no taxonomy synchronisation, private clearance storage, classic editor with structured meta boxes). See [M2-IMPLEMENTATION-PLAN.md](M2-IMPLEMENTATION-PLAN.md)._

## 1. Positioning

The site represents **Noblen Technologies Limited**, the parent company and corporate identity of the Noblen ecosystem: a diversified technology, media, property and business solutions group serving organisations, institutions, businesses and communities.

- Creative direction: "Building Businesses. Enabling Innovation. Creating Opportunities."
- Noblen AI Solutions, Noblen Media Services and Noblen Property Limited are presented as **distinct operating units** that keep their own identity under the parent.
- No unsupported claims about size, revenue, clients, market share or achievements.

### Standing rules

- The marketplace platform is named **only** "OnlineMart" or "Online Marketplace" in all content, code comments, metadata and SEO text.
- Clearance, approval and audit data are stored in **dedicated private storage owned by the core plugin**, not in posts or post meta. They are never committed to this repository, and no seed file contains them.
- Logos, photographs and client materials are published only with recorded permission.
- Project names follow the exact brochure wording unless verified evidence supports a correction ("Matrix 360%" is retained).
- No gold or yellow as a dominant brand colour.
- Nothing is published where status or evidence is not established. Such entries remain unpublished drafts.

## 2. Technical decisions

| # | Decision | Rationale |
|---|----------|-----------|
| 1 | Custom classic PHP WordPress theme (`noblen-corporate`) | Requested deliverable: an installable theme, not static HTML |
| 2 | Custom post types, taxonomies and CMS logic live in a **companion core plugin**, not the theme | Content survives theme changes. Presentation stays separate from content |
| 3 | No page builder and no third-party plugin required (ACF excluded). Two Noblen components only: theme and core plugin | Maintainability, security, hosting compatibility |
| 4 | Native meta boxes and a plugin settings page for structured fields. **Classic Editor** for the custom content types in M2, with no Gutenberg dependency | Same, with no external dependency. Keeps these types off the REST API |
| 5 | Publication gate: no clearance, no publication | Protects clients, individuals and credibility |
| 6 | CI validates PHP syntax, coding standards and doc links | Keeps `main` stable |
| 7 | Business content lives in the database, not in templates | Admins can extend the site without code changes |

## 3. Sitemap

```
Home
About Noblen            (company, vision and mission, values, why Noblen)
Group
  Group directory       (interactive structure)
  {Business unit pages} (Noblen AI Solutions, Noblen Media Services, Noblen Property Limited, and any added later)
Industries              (10 industry pages, each tagged Active operation | Available service | Strategic growth area)
Solutions and Services
Portfolio               /projects/      (filterable archive, areas A to E)
  Project case-study pages
Products & Platforms    /products/      (area F, separate)
  Product pages
Insights                (news, articles, corporate announcements)
Partnerships            (partnership enquiry)
Contact                 (contact enquiry)
Search
Privacy Policy | Terms
404
```

## 4. Portfolio architecture

### 4.1 Areas

| Area | Name | Content type |
|------|------|--------------|
| A | Media, Broadcasting & Communications | Project |
| B | ICT, Software & Digital Transformation | Project |
| C | Climate, Agriculture & Development Technology | Project |
| D | Real Estate & Property Development | Project |
| E | Corporate & Strategic Projects | Project |
| F | Products & Digital Platforms | Product |

Projects and products are **separate content types with separate status vocabularies**, so a product is never confused with a completed client assignment.

### 4.2 Content types (registered by the core plugin)

| Type | Purpose | URL |
|------|---------|-----|
| `noblen_project` | A project or engagement, with an optional case study | `/projects/{slug}/` |
| `noblen_product` | A Noblen-built product or platform (area F) | `/products/{slug}/` |
| `noblen_company` | Subsidiaries and business units | `/group/{slug}/` |
| `noblen_industry` | Industry pages | `/industries/{slug}/` |
| `noblen_service` | Services and solutions | `/solutions/{slug}/` |
| `post` | Insights: news, articles, announcements | `/insights/{slug}/` |

### 4.3 Taxonomies and extensibility

Company, industry and service **records are the single source of truth** for subsidiaries, industries and services. Projects and products reference those records by ID. Each relationship is classified **required** or **optional**: a missing or unavailable required relationship restricts publication, and an optional one degrades gracefully without unpublishing anything. There is **no synchronisation** into taxonomies, so there are no competing sources of truth. Taxonomies are used only for genuine classification:

| Taxonomy | Applies to | How it is managed |
|----------|------------|-------------------|
| Portfolio area | project | Starter terms A to E, editable |
| Product type | product | Starter terms "Platform" and "Operating system", editable |

Adding a subsidiary, industry or service is an ordinary dashboard action, and filters use the relationships. Area F (products) is the product content type itself.

### 4.4 Status models

- **Projects:** Completed Assignment | Ongoing Engagement | Strategic Initiative.
- **Products:** Concept | In Development | Pilot | Live.

Status is a visible badge on cards and detail pages. A product shows **Live** only when its stage field and an evidence note are both set. If status is not established, the entry stays an unpublished draft.

### 4.5 Case-study fields

Each project has these structured fields. **Each section is displayed only if it is filled in and marked verified and approved.** Empty sections render nothing.

| # | Field | Visibility |
|---|-------|------------|
| 1 | Project overview | Public when approved |
| 2 | Client or commissioning organisation | Public when approved |
| 3 | Responsible Noblen business unit | Public (links to the business unit) |
| 4 | Industry | Public |
| 5 | Services delivered | Public when approved |
| 6 | Project scope | Public when approved |
| 7 | Noblen's contribution | Public when approved. Plain statement of the actual role (for example "videography production for [outlet]") |
| 8 | Project approach | Public when approved |
| 9 | Outcomes and impact | Public **only** when verified, never estimated |
| 10 | Gallery | Public images only where an image release and client permission are recorded |
| 11 | Project status | Public badge |
| 12 | Supporting evidence and publication clearance | **Private**, never rendered |

Products use the same pattern with the product stage in place of the project status.

### 4.6 Publication safeguards

1. A gated record (all five custom types, with the full clearance checklist for projects and products) cannot be published until its clearance is complete, its related records and images are valid, and an authorised approver has approved **the exact version of the content**. Publication and WordPress status, business status, private clearance status and approval status are four separate data concepts. The check is enforced **server-side on every write path** through a single authorised publication path, not only in the editor, and the record stays a draft otherwise.
2. Clearance records live in private plugin-owned storage that no public WordPress interface reads. They are not post meta and are absent from exports and public output.
3. Starter entries ship as **drafts** and contain names only. No clearance data is shipped.
4. Client logos, photographs and videos render only where their permission flag is set.
5. A QA scan checks published content for placeholder tokens before every release.
6. Dates, budgets, contract values, statistics, outcomes, awards and certifications have no automatic or default values. They display only when entered and approved.

## 5. Dashboard capabilities (CMS requirements)

Authorised administrators can, without developer help:

- add and edit projects and products separately;
- upload approved photographs, and add videos and other media (video embeds load on click, to avoid third-party tracking before consent);
- assign business units and industries, and set status;
- write project descriptions and case studies;
- add subsidiaries and business units, industries and services;
- publish news, articles and corporate announcements;
- edit homepage sections, contact details, social links and SEO metadata.

Separate capabilities split editing, clearance management and publication approval. The detail is in the M2 plan.

## 6. Design direction

- **Palette:** deep navy, electric blue, white, light grey, plus restrained complementary accents (for example a muted teal or green). No gold or yellow as a dominant colour. The brochure's gold accent is not carried over.
- **Style:** modern, corporate and sophisticated, with generous spacing, strong hierarchy and storytelling-led layouts.
- **Motion:** elegant, restrained, CSS-first, with a reduced-motion fallback.
- **Photography:** relevant, quality images that have permission. Placeholders are clearly marked, and none ship on live pages.
- **Responsive and accessible:** desktop, tablet and mobile, with WCAG-minded contrast, keyboard navigation and focus states.

## 7. Cross-cutting concerns

- **Forms:** nonce, sanitisation, honeypot and rate limiting, sent through `wp_mail`. Delivery depends on hosting.
- **SEO:** semantic markup, Open Graph, Schema.org Organization markup with subsidiaries, WordPress sitemap compatibility.
- **Search:** native WordPress search extended to the custom types.
- **Performance:** vanilla JS, lazy loading, self-hosted fonts, caching compatibility.
- **Security:** escape on output, sanitise on input, capability checks, no secrets in the repo.
- **Analytics:** readiness only (a configurable ID field). Nothing loads without configuration.

## 8. Governance

No production changes, DNS or hosting changes, live-database changes, production installs, deployment, merge to `main` or scheduled routines. Every milestone records its exact commit and test evidence. See [PROJECT-PROGRESS.md](PROJECT-PROGRESS.md) and [QA-REPORT.md](QA-REPORT.md).

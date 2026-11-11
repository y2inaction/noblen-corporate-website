# Website Architecture & Technical Decisions

_Status: M1, revision 2. Proposal for owner review. No implementation has started._

## 1. Technical decisions

| # | Decision | Rationale |
|---|----------|-----------|
| 1 | Custom classic PHP WordPress theme (`noblen-corporate`) | Requested deliverable: an installable theme, not static HTML |
| 2 | Custom post types and taxonomies for group content and the portfolio | Editable by non-technical admins, with no code changes |
| 3 | No page builder and no required plugin (ACF excluded) | Maintainability, security, hosting compatibility |
| 4 | Native meta boxes and a theme options page for structured fields | Same, with no external dependency |
| 5 | Nothing published without a recorded clearance (section 4) | Protects clients, individuals and the group's credibility |
| 6 | CI validates PHP syntax and coding standards | Keeps `main` stable |

### Standing rules

- The marketplace platform is named **only** "OnlineMart" or "Online Marketplace" in all content, code comments and metadata.
- Clearance records are stored as private fields inside WordPress. They are never committed to this repository.
- Logos, photographs and client materials are published only with recorded permission.
- Project names follow the exact brochure wording unless verified evidence supports a correction ("Matrix 360%" is retained).

## 2. Proposed sitemap

```
Home
About
  Company (profile, vision and mission, values)  |  Why Noblen  |  Leadership (only if approved)
Group
  Group directory (interactive structure)
  Noblen AI Solutions
  Noblen Media Services
  Noblen Property Limited
Industries                                (10 industry pages)
  each tagged: Active operation | Available service | Strategic growth area
Solutions                                 (technology, media, property, business)
Portfolio                                 /projects/   (filterable archive)
  Media, Broadcasting & Communications
  ICT, Software & Digital Transformation
  Climate, Agriculture & Development Technology
  Real Estate & Property Development
  Corporate & Strategic Projects
  Project case-study pages                /projects/{project}/
Products & Platforms                      /products/   (separate section)
  Product pages                           /products/{product}/
Insights                                  (news and editorial)
Partner With Noblen                       (partnership enquiry)
Contact                                   (contact enquiry)
Search
Legal: Privacy | Terms
404
```

## 3. Portfolio architecture

### 3.1 Content types

| Type | Purpose | URL |
|------|---------|-----|
| `noblen_project` | A project, engagement or assignment, with an optional case study | `/projects/{slug}/` |
| `noblen_product` | A Noblen-built product or platform | `/products/{slug}/` |
| `noblen_company` | The group companies | `/group/{slug}/` |
| `noblen_industry` | Industry pages | `/industries/{slug}/` |
| `noblen_service` | Services and solutions | `/solutions/{slug}/` |
| `post` | Insights | `/insights/{slug}/` |

Projects and products are separate types because their lifecycles differ. A project is a piece of work. A product is something Noblen builds and operates.

### 3.2 Taxonomies (all editable in the dashboard)

| Taxonomy | Applies to | Values (starter set) |
|----------|------------|----------------------|
| Portfolio area | project, product | The five areas above |
| Business unit | project, product | Noblen AI Solutions, Noblen Media Services, Noblen Property Limited, Noblen Technologies Limited |
| Industry | project, product | The ten industries |
| Service type | project | Video production, Documentary, Web development, Data analysis, Branding, Event coverage, Consultancy, and so on |
| Project type | project | Client assignment, Own initiative, Strategic initiative |

### 3.3 Status models

**Project status:** Completed assignment | Ongoing engagement | Strategic initiative.

**Product stage (separate):** Concept | In development | Pilot | Live. A product shows "Live" only when its stage field and an evidence note are both set.

Status is a visible badge on every card and detail page.

### 3.4 Project fields

Public fields:
- Title, summary, featured image, gallery
- Responsible Noblen entity (links to a group company)
- Client name (optional)
- **Noblen's role**: a plain statement of the actual contribution, for example "Videography production for DW"
- Period (free text) and location
- Status
- Optional case-study sections: Brief, Approach, Outcome. Each section is shown only if it is filled in and marked verified.
- Related industry, product and project links

Private fields (never rendered):
- **Clearance record**: client consent, logo permission, image release, confidentiality check
- **Evidence source** and an internal note

### 3.5 Publication safeguards

1. A project cannot be published unless all clearance boxes are ticked. The editor shows a clear notice and keeps it as a draft.
2. Empty case-study sections render nothing. There is no filler text on the live site.
3. Starter entries are installed as **drafts**, never as published pages.
4. A QA check scans published content for placeholder tokens before release.
5. Client logos are shown only where logo permission is ticked.

### 3.6 Portfolio experience

- Project cards: image, title, area, business unit, status badge and a short summary.
- Filters by portfolio area, business unit, industry, service type and status. They are accessible, work through the URL, and also function without JavaScript through taxonomy archive pages.
- Detail page: hero, facts panel (entity, client, role, period, status), narrative sections, gallery, related items and an enquiry call to action.
- The homepage portfolio section shows featured, cleared projects only, and hides itself if there are none.

## 4. Starter entries (drafts)

Level A items from [the content inventory](CONTENT-INVENTORY.md) become draft projects. Level B items become draft entries with only the owner's one-line label and **no** description or status. All stay unpublished until the clearance record is complete.

## 5. Other technical architecture (unchanged)

- **Forms:** nonce, sanitisation, honeypot and rate limiting, sent through `wp_mail`. Delivery depends on hosting.
- **SEO:** Schema.org Organization markup with subsidiaries, Open Graph tags, compatibility with WordPress's built-in sitemap.
- **Performance:** vanilla JS, lazy loading, caching compatibility.
- **Security:** escape on output, sanitise on input, capability checks.
- **QA:** PHP lint and PHPCS in CI, Playwright screenshots, and a WordPress install test where the environment allows.

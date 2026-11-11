# M2 Implementation Plan: Brand System and Theme Foundation

_Status: **PLAN ONLY. Awaiting explicit owner approval. Nothing below has been implemented.**_

## 1. Scope

M2 delivers the brand system, an installable theme foundation, the CMS data model, global components and a working homepage skeleton fed from the dashboard. Later milestones (M3 to M9) build out pages, portfolio views, forms, SEO and hardening on this foundation.

| Covered in M2 | Deferred |
|---------------|----------|
| Brand tokens and design system | Full homepage polish and motion refinement (M3) |
| Theme structure, templates, navigation, header and footer | Group directory and business-unit pages (M4) |
| Content types, taxonomies, meta boxes, options page | Industry and solution pages (M5) |
| Publication gate and clearance fields (private) | Portfolio filters and case-study views (M6) |
| Homepage skeleton from CMS data | Forms and partnership flows (M7) |
| Responsive layout and accessibility baseline | SEO schema, security hardening, performance tuning (M8) |
| CI checks and initial QA | Full QA and final documentation (M9) |

## 2. Brand system and design tokens

- **Token file** `assets/css/tokens.css` as CSS custom properties: colour, type scale, spacing scale, radii, shadows, breakpoints, motion durations.
- **Palette (proposed, for approval):** deep navy `#0A1B3D`, electric blue `#1F6BFF`, white, light grey `#F2F5FA`, text slate, with a restrained teal accent `#12A39A`. No gold or yellow. All text and background pairs checked for WCAG AA contrast.
- **Typography:** one contemporary sans-serif family, self-hosted (WOFF2, `font-display: swap`). The family will be chosen with licence checked. No third-party font requests.
- **Components:** buttons, cards, badges (status), section headers, grids, navigation, forms (styling only), breadcrumbs.
- **Motion:** CSS-only reveals using `prefers-reduced-motion`. No animation libraries.
- **Logo:** supplied logo files are used if provided. Otherwise a neutral text wordmark placeholder is used, clearly marked.

## 3. Theme foundation

### Expected files

```
wordpress-theme/noblen-corporate/
  style.css                 theme header and base
  functions.php             bootstrap only
  index.php  front-page.php  header.php  footer.php  page.php  single.php
  archive.php  search.php  404.php  searchform.php
  single-noblen_project.php  single-noblen_product.php
  archive-noblen_project.php  archive-noblen_product.php
  screenshot.png
  assets/css/ (tokens.css, base.css, components.css, layout.css)
  assets/js/ (navigation.js, reveal.js)           vanilla, deferred
  assets/fonts/  assets/images/
  inc/ setup.php  enqueue.php  custom-post-types.php  taxonomies.php
       custom-fields.php  options.php  publication-gate.php
       term-sync.php  seo.php  security.php  template-tags.php
  template-parts/ hero.php  group-companies.php  industries.php  solutions.php
       portfolio.php  products.php  insights.php  partnerships.php  cta.php
       cards/ (project.php, product.php, company.php, industry.php, post.php)
  page-templates/ (about.php, contact.php, partnerships.php)  placeholders in M2
```

### Technical approach

- Classic PHP theme, WordPress coding standards, namespaced helper functions with a `noblen_` prefix, one file per concern in `inc/`.
- Escape on output, sanitise on input, capability checks on every admin action, nonces on forms and meta boxes.
- Assets enqueued conditionally, with versioning from file modification time.
- Navigation: accessible menu, keyboard operable, mobile disclosure pattern, registered menu locations editable in the dashboard.
- Homepage: sections defined as an ordered, toggleable list in the options page. Each section reads dashboard content, and renders nothing if empty.

## 4. CMS architecture (M2)

- **Content types:** `noblen_project`, `noblen_product`, `noblen_company`, `noblen_industry`, `noblen_service` (plus standard `post`).
- **Taxonomies:** portfolio area, business unit, industry and service type. The last three are auto-synced from their source content types (`term-sync.php`) so new subsidiaries, industries and services appear in filters with no code change.
- **Meta boxes:** the 12 case-study fields. Status vocabularies exactly as in the architecture document. Clearance and evidence fields are **private** (never output, never in REST responses, excluded from exports).
- **Publication gate:** blocks publishing when status or clearance is incomplete, and explains why. Entries remain drafts.
- **Capabilities:** a separate "approve for publication" capability so the owner can split editing from approval.
- **Options page:** company information, contact details, social links, analytics ID (empty by default), homepage section order.
- **Seed content:** names-only drafts created from the content inventory, via an optional import that can be run or skipped. No clearance data in seed files, and nothing published.
- **Naming rule:** a CI step fails the build if disallowed marketplace naming appears anywhere. The allowed forms are "OnlineMart" and "Online Marketplace". The blocked term list is held outside the repository by the owner.

## 5. Dependencies and assumptions

| Item | Need |
|------|------|
| Fonts | Chosen and licence-checked in M2, self-hosted |
| Logo and brand files | Supplied by the owner. Otherwise placeholder wordmark |
| Photography | Owner-approved images only. M2 uses neutral, licence-clear placeholders for layout |
| Hosting details | PHP and WordPress versions are unknown. M2 targets PHP 7.4+ and a recent WordPress, to be confirmed |
| Test environment | WordPress Playground or `wp-env` for installation tests, if the network allows. Otherwise static checks plus manual install steps |
| Group-level content | Needed for final copy. M2 works with editable placeholders |
| Live site content | Needs an authorised export, as crawling is not being attempted |

## 6. Acceptance criteria

1. Theme ZIP installs and activates on a clean WordPress with no PHP errors or notices.
2. All templates render with an empty database, with no broken layouts and no placeholder text exposed publicly.
3. Dashboard: an admin can add a project, a product, a subsidiary, an industry and a service, set statuses, upload an image, and see each appear correctly.
4. A new subsidiary or industry appears in the corresponding taxonomy filter without code edits.
5. A project or product cannot be published without the required clearance, and clearance data never appears in page output or the REST API.
6. The homepage renders from dashboard content, and hides any empty section.
7. Layout works at 360, 768 and 1280 px, with no horizontal scroll.
8. Keyboard navigation works for menu and focus states, and contrast meets WCAG AA.
9. PHP lint and PHPCS pass, the documentation link check passes, and the naming check passes.
10. No third-party network requests are made on page load.

## 7. Testing strategy

- **Static:** `php -l` on every file, PHPCS with WordPress standards, documentation link check, naming and secrets scan.
- **Functional:** install and activate on a clean WordPress, create sample content for every type, verify gates, filters, and that private fields stay private.
- **Visual and responsive:** Playwright screenshots at three widths per template.
- **Accessibility:** automated axe checks plus a manual keyboard pass.
- **Performance baseline:** Lighthouse on the homepage, recorded as the M2 baseline. Optimisation is in M8.
- **Evidence:** results go to [QA-REPORT.md](QA-REPORT.md) and progress to [PROJECT-PROGRESS.md](PROJECT-PROGRESS.md), with the exact commit.

## 8. Risks

| Risk | Mitigation |
|------|------------|
| Hosting constraints unknown | Conservative PHP target and no plugin requirement |
| Placeholder content slipping to public pages | Publication gate, drafts-only seed, QA scan |
| Missing brand assets | Marked placeholders, easy to swap |
| Scope growth | Strict milestone boundaries as in section 1 |

## 9. Governance

Development on the existing M1 branch and PR #1 as instructed, or on the branch the owner names at approval. No production changes, no deployment, no merge to `main`, no scheduled routines.

# Customization

The theme is presentation only. Business content, workflow and clearance belong to the **Noblen Corporate Core** plugin, so changing or removing the theme never changes content.

## What you can change without code

| Where | What |
|-------|------|
| Appearance, Customize, Site Identity | Site title, **logo** (the WordPress custom logo; use the approved artwork exactly, never a redrawn one) and site icon |
| Appearance, Customize, **Home page sections** | For each home section: show or hide, and its order (lower numbers first). A section with no published content stays hidden whatever you choose. |
| Appearance, Menus | Primary, Footer and Legal menus |
| Noblen, Settings | Company name, tagline, contact details, social links, home headline and introduction, group introduction, call-to-action headline |

The home page sections are: group introduction, business units, industries, solutions and services, featured projects, products and platforms, Insights, partnerships, and the closing call to action. The partnerships section renders nothing until verified partner data exists.

## Brand and design rules

- Palette (from `assets/css/tokens.css`): deep navy `#0a1b3d`, electric blue `#1f6bff`, white, light grey `#f2f5fa`, slate text, restrained teal `#0e7f78`. Gold and yellow are **not** brand colours and are not used.
- The green swoosh belongs to the approved logo artwork. It is not used elsewhere in the interface unless the owner decides so.
- Fonts are the system font stack. Self-hosted fonts need a licence-checked font file from the owner; no third-party font request is made.
- Text and background pairs are contrast-tested (WCAG AA) by `tests-e2e/a11y.js`. Change a colour token and run that test.

## Changing the design safely

1. Do not edit the installed theme on a live site. Change the files in the repository, run the checks, and release a new package.
2. Colours, spacing, type scale, radii, shadows and motion are CSS custom properties in `assets/css/tokens.css`. Components are in `assets/css/main.css`.
3. Keep reduced-motion support and visible focus styles. Keep the skip link and the `main` landmark.
4. Run the checks: `vendor/bin/phpcs`, the browser suites in `tests-e2e/` and `tests-cli/home-sections-test.php`.

## Extension points

| Filter or hook | Purpose |
|----------------|---------|
| `noblen_partnership_items` (filter) | Supply verified partner entries (`title` and `url` required). Default is none. |
| `noblen_core_audit_keys`, `noblen_core_audit_key_id` (filters) | Test seams for the audit key. Production uses `wp-config.php` constants. |

## What a child theme should not do

Do not read plugin tables or post meta directly. Use the helpers in `inc/core-bridge.php` (`noblen_items()`, `noblen_item()`, `noblen_setting()`), which return only records the plugin's gate allows to be shown. Bypassing them can expose unapproved content.

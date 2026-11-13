# Customization

The theme is presentation only. Business content, workflow and clearance belong to the **Noblen Corporate Core** plugin, so changing or removing the theme never changes content. Everything below is done in **Appearance, Customize**, with no code. Changes preview live and are saved only when you press **Publish**.

## 1. The logo (Site Identity)

Open **Appearance, Customize, Site Identity**.

| Control | What it does |
|---------|--------------|
| **Logo** (Select, Change, Remove) | WordPress's own logo control and the Media Library. Upload a new file, choose an existing one, replace it, or remove it. |
| **Logo alternative text** | Read aloud by screen readers. If left empty the theme uses the Media Library's alternative text, and if that is empty the company name. The logo never has an empty description. |
| **Logo height on large screens / small screens** | 24 to 120 pixels (default 48) and 20 to 80 pixels (default 36). The width follows automatically, so the shape is never stretched. |
| **Logo for dark appearance (optional)** | A second logo shown instead of the main one when the site is in dark appearance (see section 3). It is hidden from screen readers; the main logo carries the description. |
| **Site icon** | The browser tab and phone icon (WordPress's own control). |

**Which files to use.** Best: **SVG**, or a **PNG or WebP with a transparent background**, so the logo sits cleanly on a white or coloured header. A JPEG cannot be transparent, and the Customizer shows a warning if you choose one. Use the logo artwork itself, not a whole brand presentation sheet. Wide, square and tall logos all work: the logo is scaled to the chosen height, and a very wide one is limited to 320 pixels across and contained, never stretched.

**With no logo configured**, the header shows the site name as a text wordmark. Nothing is hard-coded.

### SVG logos are checked before they are stored

An SVG is a small program as well as a picture, so WordPress normally refuses it. This theme allows SVG uploads for **administrators only** (people who may edit theme options; editors cannot upload SVG), and it rebuilds every SVG from a safe list before storing it:

- Removed: scripts, event handlers (`onclick` and the like), links, embedded pages and images, external references, animation that can change links, document-type declarations and entities.
- Kept: shapes, paths, text, gradients, fill and stroke styling, a simple stylesheet, and references to other parts of the same file.
- Refused with a plain message: a file that is not a valid SVG, larger than 256 KB, with more than 5,000 elements, or with a document-type or entity declaration.

This is tested for the Media Library uploader and the REST API; any other route (an import or XML-RPC, for example) is caught by a safety net that rebuilds or deletes the file when the attachment is created, which is covered by design rather than by a browser test. If an upload is refused, export the logo again as a plain SVG ("SVG 1.1", no scripts or embedded fonts) or use a PNG.

## 2. Brand colours (Brand Identity panel)

Open **Appearance, Customize, Brand Identity**. Every colour uses the WordPress colour picker and a hex field. Invalid values are rejected and fall back to the default.

| Section | Controls |
|---------|----------|
| **Brand and text colours** | Primary, secondary, accent, heading, body text, main background, card and surface background |
| **Header and footer** | Header background and text; footer background and text |
| **Links and buttons** | Link, link hover, link active; button background, button text, button hover background, button active (pressed) background |

What each colour reaches: the primary colour drives the hero and call-to-action areas and the "skip to content" link; the secondary colour drives focus rings, the logo rule in the wordmark and the glow in the hero; the accent colour drives badges and small labels; surface is every card, input and panel; the header colours cover the navigation and the mobile menu panel; link and button colours cover content links, archive links, buttons and the search form. Pagination, breadcrumbs, notices and the 404 and search pages use the same tokens.

**Colours that follow automatically.** Section tints, borders, muted text and input borders are blended from your colours, and the text on the hero is chosen as white or near-black, whichever reads better on the primary colour. **Status colours** (information, success, warning, error) are fixed on purpose so they keep their meaning in any brand.

**Independent controls.** Heading, footer and hover colours are separate controls: changing the primary colour does not move them. To change a whole identity at once, use a preset or the logo suggestion below, then adjust individual colours.

The default palette is the approved Noblen direction: deep navy and electric blue as the dominant identity, a restrained green accent, white and light grey. Gold and yellow are not used in the interface.

## 3. Appearance: light, dark, or follow the visitor's device

Under **Brand Identity, Appearance, presets and logo colours**, choose **Light**, **Dark**, or **Follow the visitor's device**. Dark appearance is derived from your brand colours (deep versions of the primary colour for backgrounds, lighter versions of links and the accent for readability) and checked for contrast, so there is nothing extra to configure. Brand colours, buttons and the hero keep their identity. Add a dark-appearance logo in Site Identity if your logo does not read on a dark header.

## 4. Palette presets and restoring defaults

In the same section:

- **Palette presets:** choose a built-in preset (Noblen default, Noblen with teal accent, Deep navy, Slate and blue) or one you saved, then **Preview this preset**. Every built-in preset passes the contrast checks in light and dark.
- **Save current colours as preset:** type a name and save. You can keep up to 12. A saved preset is stored immediately; a preset you merely preview is stored only when you press **Publish**.
- **Delete saved preset:** removes a preset you saved.
- **Restore Noblen defaults:** returns every colour and the appearance to the originals in the preview. Press **Publish** to make it permanent.

## 5. Suggest colours from your logo (optional)

1. Choose your logo in Site Identity (this never changes the palette by itself).
2. In **Colours from your logo**, press **Suggest colours from my logo**.
3. Review the colours found in the logo and the suggested palette. Adjust any colour with the picker or by typing a hex value; the small preview card and the contrast summary update as you go.
4. Press **Preview these colours on the site** to see the whole website with them, or **Discard suggestion** to leave everything unchanged.
5. Press **Publish** to save. Nothing is saved or shown to visitors until you do, and nothing is ever applied automatically.

How it works: the analysis runs on your own server (PHP's image library for PNG, JPEG, GIF and WebP, and text reading for SVG). **No external service and no paid tool is used, and the logo never leaves your server.** It finds the most representative colours (ignoring transparent and white background pixels), proposes primary, secondary and accent colours, then adjusts them until text, links, buttons, badges and the focus ring pass the contrast checks.

Limits to know about:

- **Yellow and gold tones in the logo are ignored**, because the brand direction does not use them in the interface; you are told when this happens.
- A logo with one or two colours still gets a full palette; the notes list what was derived rather than found.
- A pure white, fully transparent or gold-only logo gives no suggestion; choose colours manually.
- SVG analysis reads colours named in fill, stroke, stop-colour and colour declarations. Colours produced by complex gradients, patterns or external styles may be missed.
- Very large images (over 8 MB or 16 megapixels) and formats the server cannot decode are not analysed.
- The suggestion is a starting point. A designer's judgement still beats an algorithm.

## 6. Contrast and accessibility checks

The **Contrast check** at the bottom of the tools section re-checks as you change any colour. It tests body text and headings on the page and on cards, links (including hover and pressed) on the page, cards and header, header text, footer text, button text on buttons (resting, hovered, pressed), badge text, hero text and the focus ring. Text needs at least 4.5 to 1 and the focus ring at least 3 to 1. A failing pair is listed by name with its ratio. **Warnings never block you**: they are advice, and the decision is yours. In dark or follow-the-device appearance the dark palette is checked too.

## 7. How it works, for developers

- **Single source of truth:** `assets/brand-defaults.json` lists every colour (key, label, group, default) and the built-in presets. PHP, JavaScript and the tests all read it.
- **Storage:** settings are theme modifications (`noblen_brand_*`, `noblen_logo_*`, `custom_logo`), stored in the database per theme. They survive theme file updates and a switch to another theme and back. Saved presets are `noblen_brand_presets`.
- **Output:** `inc/brand.php` prints the tokens as CSS custom properties in `<style id="noblen-brand-css">` after the theme stylesheets, and marks `<html data-appearance="light|dark|auto">`. `assets/css/tokens.css` holds the defaults and derived tokens; `assets/css/main.css` uses only tokens and contains no brand colour literals (a test enforces this).
- **Live preview:** `assets/js/customizer-preview.js` rebuilds the same CSS in the browser using `assets/js/brand-lib.js`, a JavaScript twin of `inc/brand-color.php`. `tests-e2e/brand-parity.js` proves PHP and JavaScript produce identical CSS, dark palettes and contrast reports.
- **Content-Security-Policy:** the brand tokens are an inline style block. A strict policy needs to allow it (a nonce or `'unsafe-inline'` for styles).
- **Browser support:** derived tints use CSS `color-mix()` (Chrome 111, Firefox 113, Safari 16.2 and newer). Older browsers fall back to fixed default tints; all main colours still apply.
- **Server endpoints:** three administration-only AJAX actions (logo analysis, save preset, delete preset). Each checks a nonce and the `edit_theme_options` capability.

## 8. Other settings

| Where | What |
|-------|------|
| Appearance, Customize, **Home page sections** | For each home section: show or hide, and its order (lower numbers first). A section with no published content stays hidden whatever you choose. |
| Appearance, Menus | Primary, Footer and Legal menus |
| Noblen, Settings | Company name, tagline, contact details, social links, home headline and introduction, group introduction, call-to-action headline |

Fonts are the system font stack. Self-hosted fonts need a licence-checked font file from the owner; no third-party font request is made.

## 9. Changing the code safely

1. Do not edit the installed theme on a live site. Change the files in the repository, run the checks, and release a new package.
2. Add or change a brand colour in `assets/brand-defaults.json`, `assets/css/tokens.css` and the Customizer will pick it up; run `node tests-e2e/brand-parity.js`.
3. Keep reduced-motion support and visible focus styles. Keep the skip link and the `main` landmark.
4. Run the checks: `vendor/bin/phpcs`, the browser suites in `tests-e2e/` and the scripts in `tests-cli/`.

## 10. Extension points

| Filter or hook | Purpose |
|----------------|---------|
| `noblen_partnership_items` (filter) | Supply verified partner entries (`title` and `url` required). Default is none. |
| `noblen_seo_disabled` (filter) | Return true to switch off the theme's description and Open Graph tags. |
| `noblen_core_audit_keys`, `noblen_core_audit_key_id` (filters) | Test seams for the audit key. Production uses `wp-config.php` constants. |

A child theme should not read plugin tables or post meta directly. Use the helpers in `inc/core-bridge.php` (`noblen_items()`, `noblen_item()`, `noblen_setting()`), which return only records the plugin's gate allows to be shown.

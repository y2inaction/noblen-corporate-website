# Noblen corporate website: installation guide (version 0.2.0)

This guide takes a new WordPress site (for example on GO54 cPanel hosting, PHP 8.3 or later) to a complete, populated Noblen website in about five minutes. You do not need a developer, a command line or a build step.

## What you need

* WordPress 6.4 or later on PHP 8.3 or later, with pretty permalinks (Settings, Permalinks, "Post name").
* An administrator login.
* The two files from this download: `noblen-corporate-theme.zip` and `noblen-corporate-core.zip`.
* A backup of the site if it already holds content (the setup deletes nothing, but a backup is good practice).

## Install

1. In the WordPress dashboard open **Appearance, Themes, Add New Theme, Upload Theme**. Choose `noblen-corporate-theme.zip`, click **Install Now**, then **Activate**.
2. Open **Plugins, Add New Plugin, Upload Plugin**. Choose `noblen-corporate-core.zip`, click **Install Now**, then **Activate Plugin**.
3. A notice appears at the top of the dashboard: **Noblen needs one more step**. Click it (or open **Noblen, Website Setup**).
4. In the box **Install the complete website**, tick the confirmation and click **Install the complete website**.

That is all. The setup creates and publishes the Home, About Noblen, Our Businesses, Industries, Solutions & Services, Portfolio, Products & Platforms, Partnerships, Insights and Contact pages with professionally written, editable content, builds the Primary and Footer navigation, sets Home as the front page and Insights as the posts page (only if you have not already chosen others), and fills empty business details with clearly marked sample values. Visit your site address to see the result.

If you prefer cPanel: File Manager, upload each ZIP into `wp-content/themes` and `wp-content/plugins` respectively, choose **Extract**, then activate both in the dashboard and continue at step 3.

## What it does not do

* It never deletes or overwrites a page, post, menu, media file, record or setting you already have. If something is in the way, it is kept and reported in the result.
* It never publishes the **Privacy Policy** or **Terms and Conditions**. They are created as drafts, labelled "Draft for legal review". Have a qualified adviser review and complete them, remove the notice and the `[Owner to complete: ...]` marker, then publish them and run the setup again to add the Legal menu.
* It never publishes a business record (business units, industries, services, projects, products). Those go through the Noblen approval workflow.
* It does not invent clients, partners, awards, testimonials, registrations, figures or project results.

## Replace before launch

After installation, **Noblen, Website Setup** shows a list titled **Content to review and replace**. The main items:

| Item | Why |
|------|-----|
| Contact details (email `info@noblen.org`, phone `+234 000 000 0000`, address) | Sample values. Replace them under **Noblen, Settings** with the real details |
| Portfolio | The six entries are labelled "Illustrative sample". Replace them with real, cleared projects |
| Our Businesses, Industries, Solutions & Services, Products & Platforms | Descriptions of the Energy and Cloud areas and the lists of services and industries are generic: confirm or correct them |
| Partnerships | Describes areas of collaboration interest only: it names no partner |
| Privacy Policy, Terms and Conditions | Drafts. Legal review required before publishing |
| Logo | A monogram placeholder is used until you upload the logo (Appearance, Customize, Brand) |

Edit any page in the normal WordPress editor. The setup never overwrites your edits when run again.

## Later: publishing business records

Pages and menus need nothing more. Publishing business records (business units, projects and so on) goes through the Noblen approval workflow, which needs the audit key described in `INSTALLATION.md` (a one-time `wp-config.php` entry). Until then the site works and records simply remain drafts.

## If something goes wrong

* **A page shows "Page not found"**: open Settings, Permalinks and click Save.
* **The setup reports a conflict**: you already have a page, menu or setting there. It was left untouched. Edit your page's address or choose it in the setup screen's activation step.
* **You want to start over**: trash the pages under Pages, delete the menus under Appearance, Menus, and run the setup again. See `RECOVERY.md` for backups.

Positioning used throughout: Noblen Technologies Limited, "technology, innovation and impact", Noblen AI as the Autonomous Operating Systems Provider, and the promise "AI That Gets Things Done". Only these owner-established facts are stated as fact.

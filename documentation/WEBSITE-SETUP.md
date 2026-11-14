# Website Setup (Noblen Corporate Core 0.2.0)

**Installing and activating the theme and the plugin does not create a finished website.** A new WordPress site has no Noblen pages, no navigation menus and no records. The Website Setup, under **Noblen, Website Setup** in the dashboard, creates that structure safely, in two deliberate stages, and never at the cost of existing content or of the content approval workflow.

## What it does

| Stage | What happens | What stays unchanged |
|-------|--------------|----------------------|
| **1. Prepare** | Creates seven pages as **drafts**, three menus **empty and unassigned**, and 26 **name-only draft records** | Nothing is public. No menu is shown. The homepage setting, existing pages, menus, records and settings are not touched |
| **2. Activate** | Publishes only the **reviewed pages you select**, adds menu entries only for destinations the public can reach, assigns a menu to a theme location only if the location is free (or you choose to replace), and changes the front page or posts page only if you ask | Records are never published by the setup. Existing content is never overwritten or deleted |

The same actions are available from the command line: `wp noblen setup status|prepare|activate` (run with `--user=<administrator login>`).

## Prerequisites

* Noblen Corporate Core 0.2.0 active, and the **Noblen Corporate** theme active (it provides the menu locations Primary, Footer and Legal; without it you can prepare but not assign menus).
* An administrator account (capability `noblen_manage_settings`). Other roles, including the Publication Approver, cannot run it.
* Pretty permalinks (Settings, Permalinks) so addresses such as `/about/` work.
* A **backup** before you start (see [RECOVERY.md](RECOVERY.md)). The setup deletes nothing, but a backup is good practice before any change to a live site.

## The structure it prepares

**Pages (drafts):** Home (`/home/`), About Noblen (`/about/`), Insights (`/insights/`), Partnerships (`/partnerships/`), Contact (`/contact/`), Privacy Policy (`/privacy-policy/`), Terms and Conditions (`/terms-and-conditions/`). All use the theme's standard page template; nothing is added to the design system.

**Sections served by record archives.** Our Group, Industries, Solutions & Services, Portfolio and Products & Platforms already have their own addresses from Noblen Core (`/group/`, `/industries/`, `/solutions/`, `/projects/`, `/products/`). A WordPress page at one of those addresses would never be reached, so **no duplicate page is created**. Navigation links to the archive once at least one record of that type is approved and published, so a menu never leads to an empty section.

**Menus:** Primary Navigation (Home, About Noblen, Our Group, Industries, Solutions & Services, Portfolio, Products & Platforms, Insights, Partnerships, Contact), Footer Navigation (About Noblen, Our Group, Portfolio, Insights, Partnerships, Contact) and Legal Navigation (Privacy Policy, Terms and Conditions). They are created empty; entries are added at activation.

**Draft records (names only):** 3 business units (Noblen AI Solutions, Noblen Media Services, Noblen Property Limited), 5 industries (AI, Property, Media, Energy, Cloud), 8 services documented in the media brochure, 4 products and platforms (CIDU, OnlineMart, ArewaGo, Noblen AI Platform) and 6 institutional project titles documented in the content inventory. No client name, date, status, outcome or description is added. The setup reuses the existing starter-draft names rather than adding a second mechanism.

**What is written as fact:** only what the owner has established: Noblen Technologies Limited as the parent company, the positioning "Autonomous Operating Systems Provider", the brand promise "AI That Gets Things Done", Noblen AI as the group's central intelligence and execution business, and the business areas AI, Property, Media, Energy and Cloud. Everything else is an editable placeholder written as **`[Owner to complete: ...]`**. No case study, testimonial, partner, statistic, award or outcome is generated.

## Step by step

1. **Open Noblen, Website Setup.** Read the configuration checks and the preview tables.
2. **Prepare.** Click *Prepare website structure*. The result lists what was created, what already existed and any conflicts. This is the start of your review, not a finished site.
3. **Review the pages.** Open each draft from the table. Replace every `[Owner to complete: ...]` placeholder with verified content. A page that still contains one is never published by the setup. Home and Insights need no body text (the theme builds the home page from approved records, and Insights lists posts).
4. **Review the records.** Each is an empty draft. Complete the fields, clearance and media rights and send each through the normal approval workflow. The setup cannot publish a record; a published record is what makes its section appear in navigation and on the home page.
5. **Activate.** In step 2 tick only what you have reviewed: the pages to publish, the menus to assign, and, if you want them, the front page, the posts page, and the fill of the *empty* homepage headline and introduction. Tick the confirmation and click *Activate selected items*.
6. **Repeat as content becomes ready.** Come back after more pages are completed or more records are published and activate again. Entries are added for newly public destinations and nothing is duplicated.

## Status labels

| Label | Meaning |
|-------|---------|
| Existing content (kept as it is) | Something of yours that the setup found. Never changed |
| Generated draft (needs your content) | Created by the setup; still has placeholders or is an unapproved draft record |
| Ready for owner review | A page without placeholders, or a record sent for approval. Review it, then decide |
| Approved and public | Published. For a record, it passed the clearance and approval workflow |
| Conflict (existing content kept) | Something of yours is already there. The setup left it alone |

A successful preparation is **not** equivalent to a published website.

## Conflicts

The setup identifies its own items by a stable key stored with them. Anything else that is in the way is **kept and reported**:

* **A page already at an address** (for example a page at `/about/`, or the draft "Privacy Policy" that WordPress creates on installation): the setup creates no second page and does not edit yours. To use it in navigation, make sure it is published, then tick *Use my existing published page* at activation; it is linked, not changed. Alternatively, edit your page's address.
* **A menu with the same name:** kept; the setup creates no second menu and never adds to it.
* **A location that already has another menu:** kept unless you tick *replace* for that location.
* **A different static front page or posts page:** kept unless you tick the matching *replace* choice.
* **A record with the same title:** kept; the setup does not claim it.

## Interrupted or repeated runs

Run *Prepare* again: it creates only what is missing and skips what exists, with the same result however many times it is run. Your edits to pages the setup created are never overwritten. If you deleted a setup page, a rerun creates a fresh draft. A page in the trash is reported ("restore it from the trash"), not recreated. The screen shows a short activity log.

## What the setup deliberately does not do

* It never publishes a business record, never adds clearance or approval, and never edits a record's content.
* It never publishes a page that still contains a placeholder, or a page it did not create.
* It never deletes or overwrites a page, post, menu, menu entry, media file, record or setting.
* It never changes the homepage, posts page, menu locations, homepage section order or visibility, or brand settings unless you tick the matching choice (and, where something different exists, the replace choice).
* It never links public navigation to a page that is not published, or to a section with nothing approved to show.
* It does not create contact details, legal text, leadership profiles, partnerships or any statement of fact beyond the established positioning above.

## How it fits the approval workflow

The content approval workflow (clearance, approval, separation of duties, signed audit trail) is unchanged and is the only way to publish a business record. The setup creates *drafts* only and asks WordPress for the draft status itself; the publication gate would also refuse anything else. Pages are ordinary WordPress pages and are reviewed by the administrator before activation. Homepage sections and archives appear as records are approved.

## Undoing it

Everything the setup creates is a draft, an empty menu or a menu entry. To undo: trash the draft pages and records, delete the three menus under Appearance, Menus, and unassign any menu location you chose. Published items are ordinary WordPress content; unpublish them the usual way. Restore from your backup for anything else ([RECOVERY.md](RECOVERY.md)).

## Technical notes

Items carry the meta key `_noblen_setup_key` (menus carry it as term meta; menu entries as post meta). Progress is kept in the option `noblen_core_setup_state`. Tests: `wordpress-plugin/noblen-corporate-core/tests/Unit/SetupTest.php` (29 tests: first run, repeat runs, existing page, menu and homepage preservation, conflicts, partial recovery, draft-only records, no workflow bypass, capability and nonce checks, invalid configuration, activation readiness, no menu entry for an unpublished page).

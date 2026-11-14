# Deployment

**This document prepares a deployment. It does not authorise one.** Merging the pull request, deploying to production, changing DNS, changing a production database, and any paid infrastructure each need the owner's separate approval. Nothing in this repository deploys anything.

## Requirements

| Item | Requirement |
|------|-------------|
| PHP | 8.3 or newer (8.3 and 8.4 are tested; 8.5 is informational) |
| WordPress | 6.4 or newer (6.4.3 is the tested version) |
| Database | MySQL or MariaDB (MySQL 8.4 in CI, MariaDB 10.11 locally) |
| PHP extensions | `mysqli`, `gd` (image sizes), `mbstring` |
| HTTPS | Required in production. The site makes no third-party requests on page load. |
| Cron / email | Standard WordPress. The theme offers an email link; contact forms are a later milestone. |

The hosting provider, server size, backup service and CDN are **not chosen by this repository**. Choosing paid infrastructure is an owner decision.

## Before any deployment

1. All required CI jobs are green on the commit to be released, and its exact SHA is recorded.
2. The owner has accepted the items that need owner acceptance (see [ACCEPTANCE-RECORDS-GUIDE.md](ACCEPTANCE-RECORDS-GUIDE.md)). No acceptance is recorded by tests.
3. The owner naming scan has been run (`scripts/naming-scan.sh`) and its result accepted.
4. The owner content checklist ([OWNER-CONTENT-REQUIREMENTS.md](OWNER-CONTENT-REQUIREMENTS.md)) is complete: only verified, owner-approved content is published.
5. A **full backup of files and database** exists and its restore has been tested (see [RECOVERY.md](RECOVERY.md)). Backups that contain the plugin tables are confidential.
6. Packages are built from the release commit with `scripts/build-packages.sh` and checked against `SHA256SUMS`.

## Steps (staging first, then production)

Follow [INSTALLATION.md](INSTALLATION.md) on a **staging copy** first. Production repeats the same steps.

1. Put the site in maintenance mode if one is in use.
2. Back up files and database. Confirm the backup before an upgrade: the plugin refuses to migrate its database without that confirmation.
3. Add the audit key to `wp-config.php` above the line that loads WordPress. The owner generates the secret and keeps a copy in secure custody; the plugin never creates one. Approvals and publication stay blocked without it.
4. Upload and activate **Noblen Corporate Core**, then the **Noblen Corporate** theme.
5. Create the three roles' users (Content Editor, Clearance Officer, Publication Approver). The approver must differ from the last editor and the clearance officer.
6. Enter only verified business details under Noblen, Settings.
7. Run **Noblen, Website Setup** ([WEBSITE-SETUP.md](WEBSITE-SETUP.md)): prepare the draft pages, menus and records, complete the pages, then activate what is reviewed (this assigns the Primary, Footer and Legal menus when their destinations are public). Choose the home page sections in Customize, Home page sections.
8. Verify (below). Remove maintenance mode only after verification passes.

## Post-deployment verification

- The home page, each archive and one record of each type return the expected pages; an unpublished record returns 404.
- The browser console shows no errors and no request leaves the site's own host.
- Noblen, Settings shows no audit-integrity warning; "Approvals are blocked" does not appear in the Publication box.
- The sitemap and `robots.txt` are as intended; search engines are allowed only when the owner decides.
- A page cache or CDN, if any, is purged after publication changes (the plugin lists addresses to purge but cannot reach a cache).

## Rollback

See [RECOVERY.md](RECOVERY.md). In short: for a failed theme, rename the theme folder so WordPress falls back to a default theme; for a failed plugin, rename the plugin folder or deactivate it; for a failed database upgrade, restore the pre-upgrade backup. Content stays in the database throughout.

## Host hardening to decide before launch

Found during the final assurance review ([SECURITY-OPERATIONAL-ASSURANCE.md](SECURITY-OPERATIONAL-ASSURANCE.md)); these depend on the real host and are the owner's decisions:

- **User names are public by WordPress default.** `/wp-json/wp/v2/users` and `/?author=1` reveal login names. Restrict them at the host (or accept the risk) before relying on the approval roles' logins being private. Use strong unique passwords and, if available, two-factor sign-in for all privileged roles.
- **XML-RPC is enabled by WordPress default.** The publication gate still applies to it (tested), but unless a client needs it, block `xmlrpc.php` at the host.
- **Security headers** (frame policy, HSTS, a Content-Security-Policy that allows the inline brand style block) are set at the host or CDN.
- **Hide PHP details** (`X-Powered-By`) and keep `WP_DEBUG_DISPLAY` off.
- **Key custody rehearsal.** Before go-live the owner rehearses rotation and recovery once on staging with throwaway keys (record [C34-M01](acceptance-records/C34-M01.md)). Never use the production key in a rehearsal.

## What the repository cannot verify

- Downloading WordPress from wordpress.org is blocked in the authoring sandbox; hosted CI performs it and checks the checksum.
- Container images used by the browser tests are chosen by `wp-env` 12.1.0, which offers no pinning option. The CI log records their digests as evidence of what ran. This is a documented exception ([DEPENDENCIES.md](DEPENDENCIES.md)).
- The recovery procedure has not been rehearsed by the owner on a staging copy.

# Recovery

Back up files and the database before any install or upgrade. This procedure has **not yet been rehearsed**; the owner should rehearse it on a staging copy.

| Situation | What to do |
|-----------|------------|
| Plugin breaks the site | Use WordPress recovery mode, rename `wp-content/plugins/noblen-corporate-core` over SFTP, or run `wp plugin deactivate noblen-corporate-core`. Data stays in the database. |
| Theme breaks the site | Rename `wp-content/themes/noblen-corporate`. WordPress falls back to a default theme. Content stays because the plugin owns it. |
| Unsupported PHP or WordPress | The plugin shows a plain message and loads nothing else. Upgrade the platform. |
| Migration failed | Approvals and publication are blocked and nothing is lost. Restore the pre-migration backup, or fix the cause and clear the failure, then re-run. |
| Audit key missing | Restore it to `wp-config.php` from custody. Approvals stay blocked until then. Published content stays available. Never generate a replacement silently. |
| Audit key invalid or compromised | Suspend approvals, then rotate to a new configured key (administrator or approver, with the chain verified). |
| Audit key lost with no copy | Use the documented recovery: a new key is configured, the confirmation phrase is entered, a second person acts as recovery authority, and the owner completes it. Earlier history is then reported as unverifiable, not rewritten. |
| A record was published in error | Unpublish or revoke it. The plugin reverts it, bumps its version, lists addresses to purge and notes them in the dashboard. Purge any page cache or CDN yourself: the plugin cannot reach them. |

## Backups and restore

Take a full backup before any install, upgrade or release, and again after it succeeds.

1. **Files:** copy `wp-content` (themes, plugins, uploads) and `wp-config.php`. The audit key lives in `wp-config.php` or its secrets store: keep a separate copy in secure custody.
2. **Database:** export the whole WordPress database, including the plugin's tables (`<prefix>noblen_clearance`, `_media_rights`, `_approvals`, `_epochs`, `_audit`). They are confidential: store and transfer them encrypted.
3. **Test the restore** on a staging copy at least once before launch and after any hosting change. Record the time it took.
4. **Restore order:** restore the database, then the files, then confirm the audit key is in place and that Noblen, Settings shows no integrity warning. Purge any page cache.

## Rollback after a release

| Release change | Rollback |
|----------------|----------|
| Theme only | Re-install the previous theme package, or rename the theme folder to fall back to a default theme. Content is unaffected. |
| Plugin only, no database change | Re-install the previous plugin package. |
| Plugin with a database migration | Restore the pre-upgrade backup (files and database together). Do not run an older plugin against a migrated database. |
| Unwanted publication | Revoke or unpublish the record, then purge caches from the listed addresses. |

Rehearse these on staging; until the owner has done so the procedure is documented but not rehearsed.

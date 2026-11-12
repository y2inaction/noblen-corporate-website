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

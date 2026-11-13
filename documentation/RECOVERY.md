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

## Operator commands that help recovery

- `wp noblen audit status` shows whether the audit chain verifies and which key identifier is in use.
- `wp noblen audit export --user=<approver login> --file=<new file>` writes the audit chain with its chain head. Export before any recovery step and keep the file in secure custody; it is the independent record that recovery compares against.
- `wp noblen migrate status` shows whether a database upgrade is pending and whether a backup is confirmed.

## Audit key custody: rotation and recovery after loss

Operator commands run with `--user=<login>` (an administrator or a Publication Approver). They never create, derive or print a key: **you place every key in `wp-config.php` first**, and the commands only read what is configured. Only key identifiers and short fingerprints are shown.

| Situation | Command (all under `wp noblen key`) | What it does |
|-----------|-------------------------------------|--------------|
| See the state | `status` (and `wp noblen audit status`) | Integrity state, configured key ids with fingerprints, counts of custody events |
| Suspected exposure | `suspend --reason="..."` | Blocks approvals at once. `resume --yes` lifts it |
| Planned rotation | `rotate --new-key-id=<id> --confirm-new-key-id=<id>` | Records the change, signed by the old and then the new key. Then set `NOBLEN_AUDIT_KEY_ID` to the new id and keep the old key configured as a verification key. Until you switch, the state is `key_invalid` on purpose |
| Compromise | `rotate ... --reason=compromise --suspect-from=<id> --suspect-to=<id>` | Allowed while approvals are suspended; records the suspect range |
| Rotation interrupted | `complete-rotation` or `abandon-rotation --reason="..."` | State `rotation_incomplete` until one of these |
| Every key lost | `recover --sign-off-ref=<ref> --reason="..." --authority-user=<other person> --confirm="ESTABLISH NEW KEY" [--last-export=<file> --export-ref=<ref>]`, then `complete-recovery --completion-ref=<ref>` | Needs the new key already configured, a recovery authority who is a different person, and the owner's references. Approvals stay blocked (`recovery_incomplete`) until completion. Earlier entries are reported as unverifiable and never rewritten |
| An old key turns up | `old-key-available --key-id=<id>` | Records it; entries it signed then verify |

Before any of these: export the audit trail (`wp noblen audit export`) and take a database backup. **A database backup cannot be verified without the key that signed it**, so the key custody copies matter as much as the backup.

The procedure is rehearsed automatically on a disposable site in CI (`tests-cli/key-custody-rehearsal.py`: 69 checks covering backup, planned, interrupted and compromise rotation, loss and recovery, restore, tamper detection, wrong and missing keys and the refusals). That proves the commands behave as documented. It is **not** the owner's record C34-M01: a person must still rehearse this on staging with throwaway keys ([C34-M01](acceptance-records/C34-M01.md)). Never use the production key in a rehearsal.

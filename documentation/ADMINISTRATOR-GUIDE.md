# Administrator guide

For the people who run the site day to day. Installation is in [INSTALLATION.md](INSTALLATION.md), deployment in [DEPLOYMENT.md](DEPLOYMENT.md), recovery in [RECOVERY.md](RECOVERY.md).

## Roles

| Role | Can do |
|------|--------|
| Noblen Content Editor | Create and edit records, upload media, submit for approval. Cannot publish. |
| Noblen Clearance Officer | Record clearance (rights and verification evidence) for records and media. |
| Noblen Publication Approver | Approve and publish, reject, revoke, view the audit. Must differ from the last editor and the clearance officer (separation of duties is on by default). |

The standard WordPress Publish button cannot publish these records.

## Publishing a record

1. **Edit** the record (Business Units, Industries, Services, Products, Projects). Fill the structured fields. A section appears publicly only when it is filled in and marked "Verified and approved for display".
2. **Images:** choose the featured image and gallery images from the media library. Each image needs a private rights confirmation by the clearance role before approval.
3. **Clearance:** the Clearance Officer completes the checklist and evidence reference.
4. **Submit for approval** (save first).
5. The Approver reviews and chooses **Approve and publish**, or **Reject**.
6. To withdraw: **Revoke approval** (approver) or **Unpublish**. The plugin reverts the record, lists addresses to purge from any cache and records the action.

Records start as unpublished drafts. Nothing is shown as completed, live or verified because it appears in a plan or inventory.

## Business settings

Noblen, Settings holds the company name, contact details, social links and home page text. Enter only verified details. A blank value is simply not shown. Optional analytics needs an ID and agreed consent handling; with no ID, nothing loads.

## Audit key

Approvals are signed into a tamper-evident audit chain using a key defined in `wp-config.php`. If the key is missing or invalid, approvals are blocked and published content stays available. Keep the key in secure custody and follow [RECOVERY.md](RECOVERY.md) for rotation or loss. Never generate a replacement silently.

## Changing the logo and colours

Everything is in **Appearance, Customize** and needs no code; see [CUSTOMIZATION.md](CUSTOMIZATION.md) for the full guide. In short:

1. **Logo:** Site Identity, Logo. Use SVG or a transparent PNG or WebP. Set the alternative text and the heights there too.
2. **Colours:** Brand Identity. Change any colour and watch the preview; press **Publish** to save. Heading, footer and hover colours are separate controls.
3. **Dark appearance:** Brand Identity, Appearance. Choose Light, Dark or Follow the visitor's device.
4. **Not sure which colours?** Choose a preset, or press **Suggest colours from my logo**, review and adjust, preview it on the site, then Publish. Nothing is applied until you do.
5. **Go back:** **Restore Noblen defaults**, then Publish.
6. **Check readability:** the **Contrast check** lists any pair of colours that is hard to read. Warnings are advice, not blocks.

## Audit trail and database upgrades (command line)

There is no administration screen for the audit trail. Use WP-CLI on the server, with `--user=<login>` so that the same permissions apply as on the website:

```bash
wp noblen audit status
```

```bash
wp noblen audit list --user=<approver login> --limit=20
```

```bash
wp noblen audit export --user=<approver login> --file=<a new file name>
```

```bash
wp noblen migrate status
```

`audit status` shows the integrity state, the key identifier and a fingerprint (never the key) and verifies the whole chain. `audit list` and `audit export` are only for the Publication Approver role; the export includes the chain head and an existing file is never overwritten, so keep each export in secure custody. Before any plugin upgrade that changes the database, take a backup, check it, then run `wp noblen migrate confirm-backup --user=<administrator login>` and `wp noblen migrate run --user=<administrator login>`; the upgrade refuses to run until a backup is confirmed. Key rotation, suspension and recovery after loss use `wp noblen key ...` (there is no screen for them): follow the table in [RECOVERY.md](RECOVERY.md), "Audit key custody". You place every key in `wp-config.php` first; the commands never create or print a key.

## Checking the configuration

Open Tools, Site Health. The **Noblen configuration** test reports, in plain language, whether the audit chain verifies, the database schema is current, errors are hidden from visitors, HTTPS and pretty permalinks are on, the theme is active, separation of duties is enforced, data is kept on uninstall, and search engines are allowed. **Critical** items block approvals or risk data and should be fixed first; **recommended** items are advice (for example, search engines should stay discouraged only until launch). The test only reads; it changes nothing.

## Troubleshooting

| Symptom | Likely cause and action |
|---------|--------------------------|
| "Approvals are blocked: audit integrity is ..." | Key missing, invalid or the chain needs attention. Restore the key from custody; see RECOVERY.md. |
| A record is not visible publicly | It is not approved and published, or a required section or image lacks clearance. Check the Publication and Clearance boxes. |
| Home page section missing | It is hidden in Customize, or has no published content. |
| Footer shows no menu | The Footer menu is unassigned (Appearance, Menus). |
| My logo upload was refused | SVG uploads are for administrators and are checked for safety. Read the message: usually the file contains script or a document-type declaration, is over 256 KB, or is not a real SVG. Export a plain SVG again or use a PNG. |
| The logo looks blurry or has a white box | Use SVG, or a PNG or WebP with a transparent background. A JPEG cannot be transparent. |
| "Suggest colours" says nothing usable was found | The logo is white, transparent, gold-only, too large, or in a format the server cannot read. Choose the colours by hand or use a preset. |
| After changing colours the site looks unchanged | The change is a preview until you press **Publish**. If you use a page cache, purge it. |
| "Choose images" button missing | JavaScript is off or blocked; type media IDs instead. |
| Site shows a plain PHP/WordPress version message | The platform is older than PHP 8.3 or WordPress 6.4; upgrade it. |
| "A Noblen database upgrade is waiting" notice | An upgrade changed the database and needs a confirmed backup first. Take and check a backup, then follow the commands named in the notice (or ask your developer or host). Approvals stay blocked until it is done. |
| Migration failed notice | Approvals stay blocked and nothing is lost. Restore the pre-upgrade backup or fix the cause and clear the failure. |

## Security practices

- Use individual accounts, strong passwords and two-step sign-in where the host supports it. Give people only the role they need.
- Keep WordPress, PHP and the host patched; apply updates on staging first.
- Uploads are validated as images and need rights confirmation; do not install unknown plugins.
- The plugin checks a nonce and a capability on every state-changing action and escapes output; report anything suspicious to the owner.
- Backups contain confidential plugin data: store and transfer them securely.

## Maintenance

| When | Task |
|------|------|
| Weekly | Review pending approvals; check the audit status on Noblen, Settings. |
| Monthly | Apply updates on staging, then production; test a backup restore; review users and roles. |
| Before each release | Follow the checklist in [DEPLOYMENT.md](DEPLOYMENT.md). |
| After publishing | Purge any page cache or CDN for the listed addresses. |

## Removal

Deactivating or deleting the plugin keeps all data by default. Data is removed only if `noblen_core_remove_data_on_uninstall` is set to 1 before uninstalling.

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

## Troubleshooting

| Symptom | Likely cause and action |
|---------|--------------------------|
| "Approvals are blocked: audit integrity is ..." | Key missing, invalid or the chain needs attention. Restore the key from custody; see RECOVERY.md. |
| A record is not visible publicly | It is not approved and published, or a required section or image lacks clearance. Check the Publication and Clearance boxes. |
| Home page section missing | It is hidden in Customize, or has no published content. |
| Footer shows no menu | The Footer menu is unassigned (Appearance, Menus). |
| "Choose images" button missing | JavaScript is off or blocked; type media IDs instead. |
| Site shows a plain PHP/WordPress version message | The platform is older than PHP 8.3 or WordPress 6.4; upgrade it. |
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

# Installation

Two packages are built by `scripts/build-packages.sh`: `noblen-corporate-theme.zip` and `noblen-corporate-core.zip`, with `SHA256SUMS`. Nothing here deploys anything. Follow it on a staging copy first.

## Requirements

- PHP 8.3 or newer, WordPress 6.4 or newer, MySQL or MariaDB.
- A **configured audit key** (below). Without one the site works, but approvals and publication are blocked by design.

## Before you start

Back up the files and the database. Backups that contain the plugin tables are confidential.

## Steps

1. Verify the ZIPs against `SHA256SUMS`.
2. Add the audit key to `wp-config.php`, above the line that loads WordPress. Generate the secret yourself with a password manager. The plugin never creates one.
   ```php
   define( 'NOBLEN_AUDIT_KEYS', array( 'k1' => 'your-long-random-secret' ) );
   define( 'NOBLEN_AUDIT_KEY_ID', 'k1' );
   ```
   Keep a copy in your secure custody. If the key is lost, see [RECOVERY.md](RECOVERY.md).
3. Install and activate **Noblen Corporate Core**, then the **Noblen Corporate** theme (Plugins and Themes, Add New, Upload).
4. Create three users with the roles Noblen Content Editor, Noblen Clearance Officer and Noblen Publication Approver. The approver must be a different person from the last editor and from the clearance officer.
5. Open Noblen, Settings and enter only verified business details. Optionally use "Create starter drafts".
6. Assign menus (Primary, Footer, Legal).

## WP-CLI equivalent

```bash
wp plugin install noblen-corporate-core.zip --activate
wp theme install noblen-corporate-theme.zip --activate
```

## Upgrades

Upgrading from an earlier schema needs a confirmed backup. The plugin refuses to migrate until you confirm one, and blocks approvals if a migration fails.

## Removal

Deactivating or deleting the plugin **keeps all data** by default. Data is removed only if the option `noblen_core_remove_data_on_uninstall` is set to 1 before uninstalling.

## Compatibility

Tested locally on PHP 8.3 and MariaDB 10.11 with WordPress 6.4.3, and in hosted CI on PHP 8.3 and 8.4 with MySQL 8.4 and WordPress 6.4.3. PHP 8.5 is checked informationally. Other WordPress versions are not tested.

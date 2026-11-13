<?php
/**
 * Uninstall. Data is retained by default; removal is opt-in only.
 *
 * @package noblen-corporate-core
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( '1' !== (string) get_option( 'noblen_core_remove_data_on_uninstall', '0' ) ) {
	return; // Default: keep every record, private table and setting.
}

global $wpdb;
foreach ( array( 'clearance', 'media_rights', 'approvals', 'epochs', 'audit' ) as $t ) {
	$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}noblen_{$t}`" ); // phpcs:ignore WordPress.DB
}
foreach ( array( 'noblen_core_settings', 'noblen_core_db_version', 'noblen_core_notices', 'noblen_core_remove_data_on_uninstall' ) as $o ) {
	delete_option( $o );
}

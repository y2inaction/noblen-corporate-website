<?php
// Prints the number of Noblen records in any status and the number of private tables present.
global $wpdb;
$posts = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type LIKE 'noblen\_%' AND post_status <> 'auto-draft'" ); // phpcs:ignore WordPress.DB
$tables = 0;
foreach ( array( 'clearance', 'media_rights', 'approvals', 'epochs', 'audit' ) as $t ) {
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'noblen_' . $t ) ) ) { // phpcs:ignore WordPress.DB
		++$tables;
	}
}
echo $posts . ' ' . $tables . "\n";

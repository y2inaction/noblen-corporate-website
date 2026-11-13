<?php
/**
 * C26: normalised logical dataset, independent of physical table layout.
 * Prints one "<category> <sha256>" line per category plus "audit_max <id>".
 * Covers posts and editorial content (content hashed), metadata, relationships (as sorted ID sets),
 * clearance records, approvals, media-rights confirmations, existing audit entries and business settings.
 * Option noblen_probe_audit_upto limits audit entries to ids up to that value (0 = all), so that history
 * existing before an operation can be compared with the same history afterwards.
 */
global $wpdb;
$upto = (int) get_option( 'noblen_probe_audit_upto', 0 );
$hash = static function ( array $rows ): string {
	$lines = array_map( static fn( $r ) => wp_json_encode( $r ), $rows );
	sort( $lines, SORT_STRING );
	return hash( 'sha256', implode( "\n", $lines ) );
};
$ids   = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type LIKE 'noblen\_%' AND post_status <> 'auto-draft'" ); // phpcs:ignore WordPress.DB
$in    = $ids ? implode( ',', array_map( 'intval', $ids ) ) : '0';
$out   = array();

$rows = array();
foreach ( $wpdb->get_results( "SELECT ID, post_type, post_status, post_title, post_name, post_excerpt, post_content FROM {$wpdb->posts} WHERE ID IN ({$in})", ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB
	$r['post_content'] = hash( 'sha256', (string) $r['post_content'] );
	$rows[]            = $r;
}
$out['posts'] = $hash( $rows );

$meta = $rel = array();
foreach ( $wpdb->get_results( "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id IN ({$in}) AND meta_key LIKE '\_noblen\_%'", ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB
	if ( 0 === strpos( $r['meta_key'], '_noblen_rel_' ) ) {
		$rel[ $r['post_id'] . '|' . $r['meta_key'] ][] = (int) $r['meta_value'];
	} elseif ( ! in_array( $r['meta_key'], array( '_noblen_last_hash' ), true ) ) {
		$meta[] = $r;
	}
}
$sets = array();
foreach ( $rel as $k => $v ) {
	sort( $v );
	$sets[] = array( $k, $v );
}
$out['metadata']      = $hash( $meta );
$out['relationships'] = $hash( $sets );

foreach ( array( 'clearance', 'approvals', 'media_rights' ) as $t ) {
	$table      = $wpdb->prefix . 'noblen_' . $t;
	$out[ $t ] = $hash( (array) $wpdb->get_results( "SELECT * FROM `{$table}`", ARRAY_A ) ); // phpcs:ignore WordPress.DB
}
$audit        = $wpdb->prefix . 'noblen_audit';
$where        = $upto > 0 ? 'WHERE id <= ' . $upto : '';
$out['audit'] = $hash( (array) $wpdb->get_results( "SELECT * FROM `{$audit}` {$where} ORDER BY id", ARRAY_A ) ); // phpcs:ignore WordPress.DB
$out['settings'] = hash( 'sha256', wp_json_encode( get_option( 'noblen_core_settings', array() ) ) );

ksort( $out );
foreach ( $out as $k => $v ) {
	echo $k . ' ' . $v . "\n";
}
echo 'audit_max ' . (int) $wpdb->get_var( "SELECT COALESCE(MAX(id),0) FROM `{$audit}`" ) . "\n"; // phpcs:ignore WordPress.DB

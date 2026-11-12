<?php
/**
 * Seeds a DISPOSABLE test site for the browser tests (run with `wp eval-file`).
 * Creates test accounts with throwaway passwords and clearly labelled fixture records.
 * Never run against a real site.
 */

use Noblen\Core\Clearance\Repository;
use Noblen\Core\Clearance\Workflow;
use Noblen\Core\Fields\Store;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$accounts = array(
	'fx_editor'   => 'noblen_content_editor',
	'fx_officer'  => 'noblen_clearance_officer',
	'fx_approver' => 'noblen_publication_approver',
);
foreach ( $accounts as $login => $role ) {
	$user = get_user_by( 'login', $login );
	$id   = $user ? $user->ID : wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password(), 'role' => $role ) );
	wp_set_password( 'e2e-' . substr( $login, 3 ) . '-dev-only', $id );
}
wp_set_password( 'e2e-admin-dev-only', 1 );
$uid = static fn( string $l ): int => get_user_by( 'login', $l )->ID;

if ( get_posts( array( 'post_type' => 'noblen_company', 'title' => 'Dev Fixture Unit', 'post_status' => 'publish', 'numberposts' => 1 ) ) ) {
	echo "already seeded\n";
	return;
}

$make = static function ( string $type, string $title, array $fields = array() ) use ( $uid ): int {
	wp_set_current_user( $uid( 'fx_editor' ) );
	$id = wp_insert_post( array( 'post_type' => $type, 'post_title' => $title, 'post_status' => 'draft', 'post_content' => 'DEV FIXTURE placeholder body.' ) );
	if ( $fields ) {
		Store::save( $id, $fields );
	}
	return $id;
};
$publish = static function ( int $id ) use ( $uid ): void {
	wp_set_current_user( $uid( 'fx_officer' ) );
	$items = array();
	foreach ( array_keys( Repository::items_for_type( get_post_type( $id ) ) ) as $k ) {
		$items[ $k ] = 'yes';
	}
	Repository::save( $id, $items, 'DEV-FIXTURE' );
	wp_set_current_user( $uid( 'fx_editor' ) );
	$a = Workflow::submit( $id );
	wp_set_current_user( $uid( 'fx_approver' ) );
	$r = is_wp_error( $a ) ? $a : Workflow::approve_and_publish( $id );
	if ( is_wp_error( $r ) ) {
		fwrite( STDERR, 'seed failed: ' . $r->get_error_message() . "\n" );
		exit( 1 );
	}
};

$co = $make( 'noblen_company', 'Dev Fixture Unit', array( 'tagline' => 'Dev fixture tagline' ) );
$publish( $co );
$in = $make( 'noblen_industry', 'Dev Fixture Industry', array( 'status' => 'available_service' ) );
$publish( $in );
$pr = $make( 'noblen_project', 'Dev Fixture Project', array( 'overview' => '<p>Dev fixture overview.</p>', 'overview_approved' => '1', 'status' => 'ongoing_engagement', 'business_unit' => array( $co ), 'featured_home' => '1' ) );
$publish( $pr );
update_option( 'noblen_core_settings', array( 'hero_headline' => 'Dev fixture headline', 'hero_intro' => 'Dev fixture introduction.' ) );
echo "seeded\n";

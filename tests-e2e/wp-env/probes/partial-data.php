<?php
/**
 * C11-002: builds PARTIAL site content one step at a time, so the front page can be checked with some
 * sections filled and others empty. The step comes from the option noblen_probe_step:
 *   companies | industries | projects
 * Disposable test sites only: same guard as the seed script. Prints the created post ID.
 */

use Noblen\Core\Clearance\Repository;
use Noblen\Core\Clearance\Workflow;
use Noblen\Core\Fields\Store;

$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
$ok   = '1' === getenv( 'NOBLEN_ALLOW_E2E_SEED' ) || ( defined( 'NOBLEN_E2E_SITE' ) && NOBLEN_E2E_SITE );
if ( ! $ok || ! in_array( $host, array( '127.0.0.1', 'localhost' ), true ) ) {
	fwrite( STDERR, "Refusing: disposable test sites only.\n" );
	exit( 1 );
}
$accounts = array( 'fx_editor' => 'noblen_content_editor', 'fx_officer' => 'noblen_clearance_officer', 'fx_approver' => 'noblen_publication_approver' );
foreach ( $accounts as $login => $role ) {
	if ( ! get_user_by( 'login', $login ) ) {
		wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password(), 'role' => $role ) );
	}
}
$uid  = static fn( string $l ): int => get_user_by( 'login', $l )->ID;
$step = (string) get_option( 'noblen_probe_step', '' );

$make = static function ( string $type, string $title, array $fields ) use ( $uid ): int {
	wp_set_current_user( $uid( 'fx_editor' ) );
	$id = wp_insert_post( array( 'post_type' => $type, 'post_title' => $title, 'post_status' => 'draft', 'post_content' => 'Partial-data fixture.' ) );
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
	Repository::save( $id, $items, 'PARTIAL-FIXTURE' );
	wp_set_current_user( $uid( 'fx_editor' ) );
	$a = Workflow::submit( $id );
	wp_set_current_user( $uid( 'fx_approver' ) );
	$r = is_wp_error( $a ) ? $a : Workflow::approve_and_publish( $id );
	if ( is_wp_error( $r ) ) {
		fwrite( STDERR, 'publish failed: ' . $r->get_error_message() . "\n" );
		exit( 1 );
	}
};

if ( 'companies' === $step ) {
	$id = $make( 'noblen_company', 'Partial Unit', array( 'tagline' => 'Partial tagline' ) );
} elseif ( 'industries' === $step ) {
	$id = $make( 'noblen_industry', 'Partial Industry', array( 'status' => 'available_service' ) );
} elseif ( 'projects' === $step ) {
	$unit = get_posts( array( 'post_type' => 'noblen_company', 'title' => 'Partial Unit', 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids', 'suppress_filters' => false ) );
	if ( ! $unit ) {
		fwrite( STDERR, "Run the companies step first.\n" );
		exit( 1 );
	}
	$id = $make( 'noblen_project', 'Partial Project', array( 'overview' => '<p>Partial overview.</p>', 'overview_approved' => '1', 'status' => 'ongoing_engagement', 'business_unit' => array( (int) $unit[0] ), 'featured_home' => '1' ) );
} else {
	fwrite( STDERR, "Unknown step.\n" );
	exit( 1 );
}
$publish( $id );
echo $id . "\n";

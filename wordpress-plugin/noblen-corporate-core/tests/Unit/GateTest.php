<?php
use Noblen\Core\Clearance\Approvals;
use Noblen\Core\Clearance\Grant;
use Noblen\Core\Clearance\Publisher;
use Noblen\Core\Clearance\Repository;
use Noblen\Core\Clearance\Snapshot;
use Noblen\Core\Clearance\Workflow;
use Noblen\Core\Content\Types;

require_once ABSPATH . 'wp-admin/includes/admin.php';

/**
 * C10 incomplete clearance blocks publication on every write path.
 * C24 bypass attempts leave records unpublished; the Publisher succeeds only with a valid approval.
 * C30 grant scoping.
 */
class GateTest extends NoblenTestCase {

	/** A project with a VALID Approved approval, not yet published. */
	private function approved_unpublished_project(): int {
		$company = $this->published_company();
		$id      = $this->project( $company, 'Gate project' );
		$this->complete_clearance( $id );
		$this->as_user( $this->editor() );
		$this->assertIsInt( Workflow::submit( $id ) );
		$this->as_user( $this->approver() );
		$this->assertIsInt( Workflow::approve( $id ) );
		$this->assertNotSame( 'publish', get_post_status( $id ) );
		return $id;
	}

	private function assert_not_public( int $id, string $label ): void {
		clean_post_cache( $id );
		$this->assertNotSame( 'publish', get_post_status( $id ), "$label: record must not be stored as published" );
		$this->assertNotSame( 'future', get_post_status( $id ), "$label: record must not be scheduled" );
		wp_set_current_user( 0 );
		$q = new WP_Query( array( 'p' => $id, 'post_type' => get_post_type( $id ) ) );
		$this->assertCount( 0, $q->posts, "$label: an anonymous request must not find the record" );
	}

	/**
	 * Every write path, as closures taking the post ID of an existing record.
	 *
	 * @return array<string,callable>
	 */
	private function existing_record_paths(): array {
		return array(
			'editor'       => function ( int $id ) {
				$this->as_user( $this->approver() );
				wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
			},
			'quick_edit'   => function ( int $id ) {
				$this->as_user( $this->approver() );
				edit_post( array( 'post_ID' => $id, 'post_type' => get_post_type( $id ), 'post_status' => 'publish', 'post_title' => get_the_title( $id ) ) );
			},
			'bulk_edit'    => function ( int $id ) {
				$this->as_user( $this->approver() );
				bulk_edit_posts( array( 'post' => array( $id ), 'post_type' => get_post_type( $id ), '_status' => 'publish' ) );
			},
			'scheduling'   => function ( int $id ) {
				$this->as_user( $this->approver() );
				$when = gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS );
				wp_update_post( array( 'ID' => $id, 'post_status' => 'future', 'post_date' => get_date_from_gmt( $when ), 'post_date_gmt' => $when ) );
			},
			'programmatic' => function ( int $id ) {
				$this->as_user( $this->user( 'administrator', 'admin' ) );
				wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
				wp_publish_post( $id ); // Core function used by cron; bypasses the save filter.
			},
			'wp_cli'       => function ( int $id ) {
				// WP-CLI normally runs with no logged-in user (or --user). Simulated in process.
				wp_set_current_user( 0 );
				wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
				wp_publish_post( $id );
			},
			'restore_from_trash' => function ( int $id ) {
				$this->as_user( $this->approver() );
				wp_trash_post( $id );
				add_filter( 'wp_untrash_post_status', static fn() => 'publish', 99 );
				wp_untrash_post( $id );
				remove_all_filters( 'wp_untrash_post_status' );
			},
		);
	}

	/** @return array<string,array{0:string}> */
	public static function path_names(): array {
		$out = array();
		foreach ( array( 'editor', 'quick_edit', 'bulk_edit', 'scheduling', 'programmatic', 'wp_cli', 'restore_from_trash' ) as $p ) {
			$out[ $p ] = array( $p );
		}
		return $out;
	}

	/**
	 * C10-001..004,007..009: incomplete clearance on an existing record.
	 *
	 * @dataProvider path_names
	 */
	public function test_C10_incomplete_clearance_blocks_publication_on_each_path( string $path ): void {
		$id = $this->approved_unpublished_project();
		// Clearance becomes incomplete after approval.
		$this->as_user( $this->officer() );
		Repository::save( $id, array( 'client_consent' => 'yes' ), '' );
		$this->assertFalse( Repository::is_complete( $id ) );

		$paths = $this->existing_record_paths();
		$paths[ $path ]( $id );
		$this->assert_not_public( $id, $path );
	}

	/**
	 * C24-001..004,008,009 and WP-CLI: even a VALID approval does not allow a non-Publisher path.
	 *
	 * @dataProvider path_names
	 */
	public function test_C24_bypass_attempts_leave_the_record_unpublished_even_with_a_valid_approval( string $path ): void {
		$id = $this->approved_unpublished_project();
		$paths = $this->existing_record_paths();
		$paths[ $path ]( $id );
		$this->assert_not_public( $id, $path );
	}

	/** C10-005 / C24-005 REST (enabled for the test) */
	public function test_C10_C24_rest_create_and_update_cannot_publish(): void {
		$id = $this->approved_unpublished_project();
		add_filter( 'register_post_type_args', array( $this, 'enable_rest' ), 10, 2 );
		foreach ( Types::all() as $t ) {
			unregister_post_type( $t );
		}
		Types::register();
		global $wp_rest_server;
		$wp_rest_server = null;
		$server = rest_get_server();

		$this->as_user( $this->approver() );
		$route = '/wp/v2/' . ( get_post_type_object( Types::PROJECT )->rest_base ?: 'noblen_project' );
		$this->assertArrayHasKey( $route, $server->get_routes(), 'REST must be enabled for this test.' );

		$req = new WP_REST_Request( 'POST', $route . '/' . $id );
		$req->set_body_params( array( 'status' => 'publish' ) );
		rest_do_request( $req );
		$this->assert_not_public( $id, 'rest update' );

		$this->as_user( $this->approver() );
		$new = new WP_REST_Request( 'POST', $route );
		$new->set_body_params( array( 'title' => 'Created via REST', 'status' => 'publish' ) );
		$res = rest_do_request( $new );
		$data = $res->get_data();
		if ( ! empty( $data['id'] ) ) {
			$this->assert_not_public( (int) $data['id'], 'rest create' );
		}

		remove_filter( 'register_post_type_args', array( $this, 'enable_rest' ), 10 );
		foreach ( Types::all() as $t ) {
			unregister_post_type( $t );
		}
		Types::register();
		$wp_rest_server = null;
	}

	public function enable_rest( $args, $type ) {
		return Types::is_ours( $type ) ? array_merge( $args, array( 'show_in_rest' => true, 'rest_base' => $type ) ) : $args;
	}

	/** C10-006 / C24-006 XML-RPC */
	public function test_C10_C24_xmlrpc_create_cannot_publish(): void {
		$approver = $this->approver();
		$user     = get_userdata( $approver );
		require_once ABSPATH . WPINC . '/class-IXR.php';
		require_once ABSPATH . WPINC . '/class-wp-xmlrpc-server.php';
		$server = new wp_xmlrpc_server();
		$result = $server->wp_newPost( array( 1, $user->user_login, 'pw-' . $user->user_login, array( 'post_type' => Types::COMPANY, 'post_title' => 'Via XML-RPC', 'post_status' => 'publish', 'post_content' => 'x' ) ) );
		$this->assertTrue( is_string( $result ) || is_int( $result ), is_object( $result ) ? $result->message : '' );
		$this->assert_not_public( (int) $result, 'xml-rpc' );
	}

	/** C10-010 / C24-010 import (WXR-style insert with an explicit ID) */
	public function test_C10_C24_import_arrives_as_draft(): void {
		$this->as_user( $this->user( 'administrator', 'admin' ) );
		$id = wp_insert_post( array( 'import_id' => 900000 + wp_rand( 1, 9999 ), 'post_type' => Types::PROJECT, 'post_title' => 'Imported', 'post_status' => 'publish' ) );
		$this->assertGreaterThan( 0, $id );
		$this->assert_not_public( $id, 'import' );
	}

	/** C24-011 the Publisher succeeds only with a valid approval */
	public function test_C24_publisher_succeeds_only_with_a_valid_approval(): void {
		$company = $this->published_company();
		$id      = $this->project( $company );
		$this->as_user( $this->approver() );

		$r = Publisher::publish( $id ); // No clearance, no approval.
		$this->assertWPError( $r );
		$this->assertContains( 'approval_missing', $r->get_error_data()['failures'] );
		$this->assertContains( 'clearance_incomplete', $r->get_error_data()['failures'] );
		$this->assert_not_public( $id, 'publisher without approval' );

		$this->complete_clearance( $id );
		$this->as_user( $this->editor() );
		Workflow::submit( $id );
		$this->as_user( $this->approver() );
		$r = Publisher::publish( $id ); // Submitted, not approved.
		$this->assertWPError( $r );
		$this->assertContains( 'approval_missing', $r->get_error_data()['failures'] );

		$this->assertIsInt( Workflow::approve( $id ) );
		// Content changes after approval make it stale.
		update_post_meta( $id, '_noblen_f_scope', 'Changed after approval' );
		$r = Publisher::publish( $id );
		$this->assertWPError( $r );
		$this->assertContains( 'approval_stale', $r->get_error_data()['failures'] );
		$this->assert_not_public( $id, 'publisher with stale approval' );
	}

	public function test_C24_the_publisher_publishes_an_approved_record(): void {
		$id = $this->approved_unpublished_project();
		$this->as_user( $this->approver() );
		$this->assertTrue( Publisher::publish( $id ) );
		$this->assertSame( 'publish', get_post_status( $id ) );
		$this->assertTrue( $this->is_public( $id ) );
	}

	/** C30-001 a grant for record A cannot publish record B */
	public function test_C30_grant_for_one_record_cannot_publish_another(): void {
		$a = $this->approved_unpublished_project();
		$b = $this->approved_unpublished_project();
		$this->as_user( $this->approver() );
		Grant::issue( $a, 1, Snapshot::hash( $a ), get_current_user_id() );
		wp_update_post( array( 'ID' => $b, 'post_status' => 'publish' ) );
		Grant::clear();
		$this->assert_not_public( $b, 'cross-record' );
	}

	/** C30-002 a grant for another snapshot version cannot publish */
	public function test_C30_grant_for_another_snapshot_version_cannot_publish(): void {
		$a = $this->approved_unpublished_project();
		$this->as_user( $this->approver() );
		$old = Snapshot::hash( $a );
		update_post_meta( $a, '_noblen_f_scope', 'A different version' );
		Grant::issue( $a, 1, $old, get_current_user_id() );
		wp_update_post( array( 'ID' => $a, 'post_status' => 'publish' ) );
		Grant::clear();
		$this->assert_not_public( $a, 'cross-snapshot' );
	}

	/** C30-003 a grant cannot be used by another user */
	public function test_C30_grant_cannot_be_used_by_another_user(): void {
		$a = $this->approved_unpublished_project();
		$this->as_user( $this->approver() );
		Grant::issue( $a, 1, Snapshot::hash( $a ), get_current_user_id() );
		$this->as_user( $this->user( 'administrator', 'admin' ) );
		wp_update_post( array( 'ID' => $a, 'post_status' => 'publish' ) );
		Grant::clear();
		$this->assert_not_public( $a, 'cross-user' );
	}

	/** C30-004 a grant is single use */
	public function test_C30_grant_is_single_use(): void {
		$a = $this->approved_unpublished_project();
		$this->as_user( $this->approver() );
		Grant::issue( $a, 1, Snapshot::hash( $a ), get_current_user_id() );
		wp_update_post( array( 'ID' => $a, 'post_status' => 'publish' ) );
		$this->assertSame( 'publish', get_post_status( $a ), 'The first matching write is authorised.' );
		$this->assertFalse( Grant::has_active() );

		global $wpdb;
		$wpdb->update( $wpdb->posts, array( 'post_status' => 'draft' ), array( 'ID' => $a ) );
		clean_post_cache( $a );
		wp_update_post( array( 'ID' => $a, 'post_status' => 'publish' ) ); // Replay.
		Grant::clear();
		$this->assert_not_public( $a, 'replay' );
	}

	/** C30-005 no grant without an authenticated approver */
	public function test_C30_no_grant_without_an_authenticated_approver(): void {
		$a = $this->approved_unpublished_project();
		foreach ( array( 0, $this->editor(), $this->officer(), $this->user( 'administrator', 'admin' ) ) as $uid ) {
			$this->as_user( $uid );
			$r = Publisher::publish( $a );
			$this->assertWPError( $r );
			$this->assertSame( 'noblen_forbidden', $r->get_error_code() );
			$this->assertFalse( Grant::has_active() );
		}
		$this->assert_not_public( $a, 'unauthenticated publisher' );
	}

	/** C30-007 a request cannot supply a grant */
	public function test_C30_a_request_cannot_supply_a_grant(): void {
		$a = $this->approved_unpublished_project();
		$this->as_user( $this->approver() );
		$_POST = $_REQUEST = array( 'noblen_grant' => '1', 'grant' => '1', 'post_id' => $a, 'hash' => Snapshot::hash( $a ) );
		wp_update_post( array( 'ID' => $a, 'post_status' => 'publish' ) );
		$_POST = $_REQUEST = array();
		$this->assert_not_public( $a, 'request-supplied grant' );
	}

	/** C30-008 no global flag at runtime */
	public function test_C30_no_global_flag_at_runtime(): void {
		$a = $this->approved_unpublished_project();
		$this->assertFalse( Grant::has_active() );
		$this->as_user( $this->approver() );
		$this->assertTrue( Publisher::publish( $a ) );
		$this->assertFalse( Grant::has_active(), 'The grant must not outlive the Publisher call.' );
		$this->assertFalse( Grant::was_consumed( $a ), 'Consumption state is cleared with the grant.' );
	}

	/** C30-006 static: the grant is not stored anywhere and no request data is read */
	public function test_C30_static_no_global_flag_or_request_input(): void {
		$dir = dirname( __DIR__, 2 ) . '/includes';
		foreach ( array( 'Clearance/Grant.php', 'Clearance/Publisher.php', 'Clearance/Gate.php' ) as $f ) {
			$src = file_get_contents( "$dir/$f" );
			$this->assertDoesNotMatchRegularExpression( '/\$_(POST|GET|REQUEST|COOKIE|SERVER)\b/', $src, $f );
			$this->assertDoesNotMatchRegularExpression( '/(update_option|set_transient|update_post_meta|add_option|setcookie)\s*\(/', $src, $f );
			$this->assertDoesNotMatchRegularExpression( '/define\s*\(/', $src, $f );
		}
	}
}

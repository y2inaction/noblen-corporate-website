<?php
/**
 * Base test case: real WordPress, real database, one transaction per test.
 */

use Noblen\Core\Audit\Verifier;
use Noblen\Core\Clearance\Approvals;
use Noblen\Core\Clearance\Grant;
use Noblen\Core\Clearance\MediaRights;
use Noblen\Core\Clearance\ReadCheck;
use Noblen\Core\Clearance\Repository;
use Noblen\Core\Clearance\Workflow;
use Noblen\Core\Content\Types;
use Noblen\Core\Fields\Store;

abstract class NoblenTestCase extends PHPUnit\Framework\TestCase {

	/** @var array<string,string> */
	protected array $keys = array( 'k1' => 'test-secret-one-0000000000000000' );
	protected string $key_id = 'k1';

	/** Highest post and user IDs when the test started, to detect and purge leaked data. */
	private int $base_post = 0;
	private int $base_user = 0;

	/** Tests that run DDL (which implicitly commits) set this and clean up through purge_leaks(). */
	protected bool $expects_commit = false;

	/** @var array<string,int> */
	private array $users = array();

	protected function setUp(): void {
		global $wpdb;
		$this->expects_commit = false;
		$this->base_post      = (int) $wpdb->get_var( "SELECT COALESCE(MAX(ID),0) FROM {$wpdb->posts}" ); // phpcs:ignore WordPress.DB
		$this->base_user      = (int) $wpdb->get_var( "SELECT COALESCE(MAX(ID),0) FROM {$wpdb->users}" ); // phpcs:ignore WordPress.DB
		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB
		wp_cache_flush();
		ReadCheck::flush();
		Verifier::flush_cache();
		Grant::clear();
		$this->keys   = array( 'k1' => 'test-secret-one-0000000000000000' );
		$this->key_id = 'k1';
		add_filter( 'noblen_core_audit_keys', array( $this, 'filter_keys' ) );
		add_filter( 'noblen_core_audit_key_id', array( $this, 'filter_key_id' ) );
		delete_option( Verifier::OPT_SUSPENDED );
		delete_option( Verifier::OPT_RECOVERY );
		wp_set_current_user( 0 );
		$this->users = array();
	}

	protected function tearDown(): void {
		global $wpdb;
		remove_all_filters( 'noblen_core_migration_step' );
		remove_all_filters( 'noblen_core_rotation_interrupt' );
		remove_all_actions( 'noblen_core_record_withdrawn' );
		remove_filter( 'noblen_core_audit_keys', array( $this, 'filter_keys' ) );
		remove_filter( 'noblen_core_audit_key_id', array( $this, 'filter_key_id' ) );
		$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB
		$leaked = $this->purge_leaks();
		wp_cache_flush();
		ReadCheck::flush();
		Verifier::flush_cache();
		Grant::clear();
		wp_set_current_user( 0 );
		if ( $leaked && ! $this->expects_commit ) {
			$this->fail( "This test leaked {$leaked} committed row(s) past its rollback." );
		}
	}

	/**
	 * Delete posts and users created after setUp that survived the rollback.
	 *
	 * @return int Number of leaked rows removed.
	 */
	private function purge_leaks(): int {
		global $wpdb;
		$n   = 0;
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE ID > %d", $this->base_post ) ); // phpcs:ignore WordPress.DB
		foreach ( $ids as $id ) {
			wp_delete_post( (int) $id, true );
			++$n;
		}
		$uids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->users} WHERE ID > %d", $this->base_user ) ); // phpcs:ignore WordPress.DB
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( $uids as $uid ) {
			wp_delete_user( (int) $uid );
			++$n;
		}
		return $n;
	}

	public function filter_keys( $keys ) {
		return $this->keys;
	}

	public function filter_key_id( $id ) {
		return $this->key_id;
	}

	/**
	 * A user with a role (created once per test per label).
	 */
	protected function user( string $role, string $label = '' ): int {
		$label = '' === $label ? $role : $label;
		if ( ! isset( $this->users[ $label ] ) ) {
			$login = 'u_' . wp_generate_password( 8, false ) . '_' . $label;
			$this->users[ $label ] = (int) wp_insert_user( array( 'user_login' => $login, 'user_pass' => 'pw-' . $login, 'user_email' => $login . '@example.test', 'role' => $role ) );
		}
		return $this->users[ $label ];
	}

	protected function as_user( int $id ): void {
		wp_set_current_user( $id );
	}

	protected function editor(): int {
		return $this->user( 'noblen_content_editor', 'editor' );
	}

	protected function officer(): int {
		return $this->user( 'noblen_clearance_officer', 'officer' );
	}

	protected function approver(): int {
		return $this->user( 'noblen_publication_approver', 'approver' );
	}

	/**
	 * Create a draft record with fields.
	 *
	 * @param array<string,mixed> $fields Field values.
	 */
	protected function make( string $type, string $title, array $fields = array() ): int {
		$this->as_user( $this->editor() );
		$id = wp_insert_post( array( 'post_type' => $type, 'post_title' => $title, 'post_status' => 'draft', 'post_content' => in_array( $type, array( Types::COMPANY, Types::INDUSTRY, Types::SERVICE ), true ) ? 'Body of ' . $title : '' ), true );
		$this->assertIsInt( $id );
		if ( $fields ) {
			Store::save( $id, $fields );
		}
		return $id;
	}

	protected function complete_clearance( int $id ): void {
		$this->as_user( $this->officer() );
		$type  = get_post_type( $id );
		$items = array();
		foreach ( array_keys( Repository::items_for_type( $type ) ) as $item ) {
			$items[ $item ] = 'yes';
		}
		$this->assertTrue( Repository::save( $id, $items, 'Held in owner archive, ref TEST-' . $id ) );
	}

	/**
	 * Take a draft through clearance, submission, approval and publication.
	 */
	protected function publish_record( int $id ): void {
		$this->complete_clearance( $id );
		$this->as_user( $this->editor() );
		$aid = Workflow::submit( $id );
		$this->assertIsInt( $aid, is_wp_error( $aid ) ? $aid->get_error_message() . wp_json_encode( $aid->get_error_data() ) : '' );
		$this->as_user( $this->approver() );
		$r = Workflow::approve_and_publish( $id );
		$this->assertTrue( $r, is_wp_error( $r ) ? $r->get_error_message() . wp_json_encode( $r->get_error_data() ) : '' );
		$this->assertSame( 'publish', get_post_status( $id ) );
	}

	protected function company( string $title = 'Noblen Test Unit' ): int {
		return $this->make( Types::COMPANY, $title );
	}

	/** A published business unit (required relationship target). */
	protected function published_company( string $title = 'Noblen Test Unit' ): int {
		$id = $this->company( $title );
		$this->publish_record( $id );
		return $id;
	}

	/** A project draft with the minimum valid content, linked to a business unit. */
	protected function project( int $company_id, string $title = 'Test Project', array $extra = array() ): int {
		return $this->make( Types::PROJECT, $title, array_merge( array( 'overview' => '<p>Overview text</p>', 'status' => 'completed_assignment', 'business_unit' => array( $company_id ) ), $extra ) );
	}

	protected function published_project( int $company_id, string $title = 'Test Project', array $extra = array() ): int {
		$id = $this->project( $company_id, $title, $extra );
		$this->publish_record( $id );
		return $id;
	}

	/** Create an image attachment from a generated PNG. */
	protected function image( string $colour = 'red' ): int {
		$dir  = wp_upload_dir();
		$file = $dir['path'] . '/noblen-test-' . wp_generate_password( 8, false ) . '.png';
		$im   = imagecreatetruecolor( 20, 20 );
		imagefill( $im, 0, 0, imagecolorallocate( $im, 'red' === $colour ? 255 : 0, 'green' === $colour ? 255 : 0, 'blue' === $colour ? 255 : 0 ) );
		imagepng( $im, $file );
		$id = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => 'Test image', 'post_status' => 'inherit' ), $file );
		require_once ABSPATH . 'wp-admin/includes/image.php';
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );
		return (int) $id;
	}

	protected function confirm_rights( int $attachment_id ): void {
		$this->as_user( $this->officer() );
		$this->assertTrue( MediaRights::confirm( $attachment_id, 'Licence record ' . $attachment_id ) );
	}

	protected function assertWPError( $thing, string $message = '' ): void {
		$this->assertInstanceOf( WP_Error::class, $thing, $message );
	}

	protected function is_public( int $id ): bool {
		return 'publish' === get_post_status( $id ) && Noblen\Core\Clearance\ReadCheck::eligible( $id );
	}
}

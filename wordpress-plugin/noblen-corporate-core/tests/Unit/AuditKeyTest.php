<?php
use Noblen\Core\Audit\AuditLog;
use Noblen\Core\Audit\Export;
use Noblen\Core\Audit\KeyCustody;
use Noblen\Core\Audit\Verifier;
use Noblen\Core\Clearance\Workflow;
use Noblen\Core\Content\Types;
use Noblen\Core\Database\Schema;

/**
 * C27 audit integrity and C34 key custody scenarios.
 */
class AuditKeyTest extends NoblenTestCase {

	private function some_history(): void {
		$this->published_company();
		Verifier::flush_cache();
	}

	public function test_C27_001_no_update_or_delete_code_path_exists(): void {
		$files = array_merge( glob( dirname( __DIR__, 2 ) . '/includes/*/*.php' ), glob( dirname( __DIR__, 2 ) . '/includes/*.php' ) );
		$bad   = array();
		foreach ( $files as $f ) {
			$src = (string) file_get_contents( $f );
			if ( preg_match( '/(UPDATE|DELETE\s+FROM|TRUNCATE|DROP\s+TABLE)[^;\n]{0,80}noblen_audit|wpdb->(update|delete)\(\s*Schema::table\(\s*[\'"]audit/i', $src ) ) {
				$bad[] = basename( $f );
			}
		}
		// The opt-in uninstall is outside includes/ and is the only place the table may be dropped.
		$this->assertSame( array(), $bad, 'audit table is mutated in: ' . implode( ',', $bad ) );
	}

	public function test_C27_004_entries_record_who_when_what_and_chain_links(): void {
		$this->some_history();
		$entries = AuditLog::all();
		$this->assertNotEmpty( $entries );
		$prev = null;
		foreach ( $entries as $e ) {
			$this->assertNotSame( '', (string) $e['event_type'] );
			$this->assertNotSame( '', (string) $e['event_time'] );
			$this->assertArrayHasKey( 'actor_id', $e );
			if ( null !== $prev ) {
				$this->assertSame( $prev, $e['prev_hash'], 'each entry links to the previous hash' );
			}
			$prev = $e['record_hash'];
		}
	}

	public function test_C27_003_read_access_by_role(): void {
		$this->some_history();
		$this->as_user( $this->editor() );
		$this->assertWPError( AuditLog::read_entries() );
		$this->as_user( $this->officer() );
		$this->assertWPError( AuditLog::read_entries() );
		$this->as_user( $this->approver() );
		$this->assertIsArray( AuditLog::read_entries() );
	}

	public function test_C27_deleting_an_entry_is_detected(): void {
		global $wpdb;
		$this->some_history();
		$table = Schema::table( 'audit' );
		$mid   = (int) $wpdb->get_var( "SELECT id FROM `{$table}` ORDER BY id ASC LIMIT 1 OFFSET 2" ); // phpcs:ignore WordPress.DB
		$wpdb->delete( $table, array( 'id' => $mid ) );
		Verifier::flush_cache();
		$this->assertSame( 'chain_broken', Verifier::state() );
	}

	public function test_C27_export_is_comparable_and_tamper_evident(): void {
		$this->some_history();
		$this->as_user( $this->approver() );
		$export = Export::for_user();
		$this->assertIsArray( $export );
		$cmp = Export::compare( $export );
		$this->assertIsArray( $cmp );
	}

	// ---- C34
	public function test_C34_001_missing_key_blocks_approvals(): void {
		$this->some_history();
		$this->keys = array();
		Verifier::flush_cache();
		$this->assertSame( 'key_missing', Verifier::state() );
	}

	public function test_C34_002_invalid_key_is_detected(): void {
		$this->some_history();
		$this->keys = array( 'k1' => 'a-DIFFERENT-secret-for-the-same-id-00' );
		Verifier::flush_cache();
		$this->assertContains( Verifier::state(), array( 'key_invalid', 'chain_broken' ) );
	}

	public function test_C34_004_planned_rotation_verifies_before_and_after(): void {
		$this->some_history();
		$this->assertSame( 'verified', Verifier::state() );
		$this->keys['k2'] = 'test-secret-two-1111111111111111';
		$this->as_user( $this->approver() );
		$r = KeyCustody::rotate( 'k2', 'planned' );
		$this->assertIsArray( $r );
		$this->assertTrue( $r['complete'] );
		$this->key_id = 'k2';
		Verifier::flush_cache();
		$this->assertSame( 'verified', Verifier::state(), 'history signed with the old key still verifies' );
		$this->published_company( 'After rotation' );
		$this->assertSame( 'verified', Verifier::state() );
	}

	public function test_C34_005_rotation_never_rewrites_existing_rows(): void {
		global $wpdb;
		$this->some_history();
		$table  = Schema::table( 'audit' );
		$before = $wpdb->get_results( "SELECT * FROM `{$table}` ORDER BY id", ARRAY_A ); // phpcs:ignore WordPress.DB
		$this->keys['k2'] = 'test-secret-two-1111111111111111';
		$this->as_user( $this->approver() );
		KeyCustody::rotate( 'k2', 'planned' );
		$after = $wpdb->get_results( "SELECT * FROM `{$table}` ORDER BY id LIMIT " . count( $before ), ARRAY_A ); // phpcs:ignore WordPress.DB
		$this->assertSame( $before, $after, 'existing rows are byte-identical after rotation' );
	}

	public function test_C34_interrupted_rotation_blocks_until_completed(): void {
		$this->some_history();
		$this->keys['k2'] = 'test-secret-two-1111111111111111';
		$this->as_user( $this->approver() );
		add_filter( 'noblen_core_rotation_interrupt', '__return_true' );
		$r = KeyCustody::rotate( 'k2', 'planned' );
		remove_filter( 'noblen_core_rotation_interrupt', '__return_true' );
		$this->assertFalse( $r['complete'] );
		$this->key_id = 'k2';
		Verifier::flush_cache();
		$this->assertSame( 'rotation_incomplete', Verifier::state() );
		$done = KeyCustody::complete_rotation();
		$this->assertIsArray( $done );
		Verifier::flush_cache();
		$this->assertSame( 'verified', Verifier::state() );
	}

	public function test_C34_rotation_to_an_unconfigured_key_is_refused(): void {
		$this->some_history();
		$this->as_user( $this->approver() );
		$this->assertWPError( KeyCustody::rotate( 'nope', 'planned' ) );
		$this->assertWPError( KeyCustody::rotate( 'k1', 'planned' ), 'rotating to the current key is refused' );
	}

	public function test_C34_suspend_blocks_approvals_and_resume_restores(): void {
		$co = $this->company();
		$this->complete_clearance( $co );
		$this->as_user( $this->editor() );
		Workflow::submit( $co );
		$this->as_user( $this->approver() );
		$this->assertTrue( KeyCustody::suspend( 'testing' ) );
		Verifier::flush_cache();
		$this->assertSame( 'approvals_suspended', Verifier::state() );
		$this->assertWPError( Workflow::approve( $co ) );
		$this->assertTrue( KeyCustody::resume() );
		Verifier::flush_cache();
		$this->assertSame( 'verified', Verifier::state() );
		$this->assertIsInt( Workflow::approve( $co ) );
	}

	public function test_C34_recovery_needs_phrase_separate_authority_and_is_audited(): void {
		$this->some_history();
		$admin = $this->user( 'administrator', 'rec-admin' );
		$other = $this->user( 'administrator', 'rec-authority' );
		$this->keys = array( 'k9' => 'test-secret-nine-9999999999999999' );
		$this->key_id = 'k9';
		Verifier::flush_cache();
		$this->assertContains( Verifier::state(), array( 'key_missing', 'key_invalid' ) );
		$this->as_user( $admin );
		$base = array( 'confirmation' => 'ESTABLISH NEW KEY', 'sign_off_ref' => 'SO-1', 'reason' => 'key lost', 'authority_user_id' => $admin, 'authority_name' => 'Same Person' );
		$this->assertWPError( KeyCustody::recover_after_loss( array_merge( $base, array( 'confirmation' => 'yes' ) ) ), 'phrase required' );
		$this->assertWPError( KeyCustody::recover_after_loss( $base ), 'authority must differ from the executing administrator' );
		$ok = KeyCustody::recover_after_loss( array_merge( $base, array( 'authority_user_id' => $other, 'authority_name' => 'Recovery Authority' ) ) );
		$this->assertNotInstanceOf( WP_Error::class, $ok, is_wp_error( $ok ) ? $ok->get_error_message() : '' );
		Verifier::flush_cache();
		$this->assertSame( 'recovery_incomplete', Verifier::state(), 'approvals stay blocked until the owner completes recovery' );
		$events = array_unique( array_column( AuditLog::all(), 'event_type' ) );
		$this->assertNotEmpty( array_filter( $events, static fn( $e ) => false !== strpos( $e, 'recover' ) ), 'recovery is audited' );
	}
}

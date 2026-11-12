<?php
use Noblen\Core\Audit\AuditLog;
use Noblen\Core\Audit\Verifier;
use Noblen\Core\Clearance\Approvals;
use Noblen\Core\Clearance\Workflow;
use Noblen\Core\Content\Types;
use Noblen\Core\Database\Migrator;
use Noblen\Core\Database\Schema;
use Noblen\Core\Fields\Epochs;
use Noblen\Core\Fields\Store;
use Noblen\Core\PublicData\Accessor;

/**
 * C23 invalidation, C28/C33 dependencies, C29 media rights, C27 audit, C25 migrations, C31 epochs, C32 exposure.
 */
class LifecycleTest extends NoblenTestCase {

	public function test_C23_editing_a_live_record_demotes_it_and_stales_the_approval(): void {
		$co = $this->published_company();
		$id = $this->published_project( $co );
		$this->as_user( $this->editor() );
		Store::save( $id, array( 'overview' => '<p>Changed after approval</p>' ) );
		wp_update_post( array( 'ID' => $id, 'post_title' => 'Edited title' ) );
		clean_post_cache( $id );
		$this->assertNotSame( 'publish', get_post_status( $id ) );
		$this->assertSame( 'stale', Approvals::latest( $id )['status'] );
		$this->assertFalse( $this->is_public( $id ) );
	}

	/** @dataProvider field_changes */
	public function test_C23_each_public_component_change_stales_the_approval( string $what ): void {
		$co = $this->published_company();
		$id = $this->published_project( $co );
		$this->as_user( $this->editor() );
		switch ( $what ) {
			case 'meta':
				update_post_meta( $id, '_noblen_f_scope', '<p>x</p>' );
				break;
			case 'switch':
				update_post_meta( $id, '_noblen_f_overview_approved', '0' );
				break;
			case 'relationship':
				add_post_meta( $id, '_noblen_rel_industries', $this->make( Types::INDUSTRY, 'Ind' ) );
				break;
			case 'term':
				wp_set_object_terms( $id, array( 'Platform' ), 'noblen_product_type' );
				wp_set_object_terms( $id, array( 'a-media-broadcasting-communications' ), 'noblen_portfolio_area' );
				break;
			case 'thumbnail':
				set_post_thumbnail( $id, $this->image() );
				break;
		}
		clean_post_cache( $id );
		$this->assertFalse( $this->is_public( $id ), "$what must withdraw the record" );
	}

	public function field_changes(): array {
		return array( 'meta' => array( 'meta' ), 'switch' => array( 'switch' ), 'relationship' => array( 'relationship' ), 'thumbnail' => array( 'thumbnail' ) );
	}

	public function test_C28_required_dependency_withdrawn_hides_dependents(): void {
		$co = $this->published_company();
		$id = $this->published_project( $co );
		$this->assertTrue( $this->is_public( $id ) );
		$this->as_user( $this->approver() );
		$this->assertTrue( Workflow::unpublish( $co ) );
		clean_post_cache( $id );
		$this->assertFalse( $this->is_public( $id ), 'A project cannot stay public without its required business unit.' );
	}

	public function test_C28_optional_dependency_never_unpublishes_dependents(): void {
		$co  = $this->published_company();
		$ind = $this->make( Types::INDUSTRY, 'Opt Industry' );
		$this->publish_record( $ind );
		$id = $this->published_project( $co, 'With optional', array( 'industries' => array( $ind ) ) );
		$this->as_user( $this->approver() );
		Workflow::unpublish( $ind );
		clean_post_cache( $id );
		$this->assertTrue( $this->is_public( $id ), 'Optional relationships never unpublish a dependent.' );
	}

	public function test_C29_media_rights_required_and_voided_by_replacement(): void {
		$co  = $this->published_company();
		$img = $this->image();
		$id  = $this->project( $co, 'With image' );
		set_post_thumbnail( $id, $img );
		$this->complete_clearance( $id );
		$this->as_user( $this->editor() );
		$r = Workflow::submit( $id );
		$this->as_user( $this->approver() );
		$pub = is_wp_error( $r ) ? $r : Workflow::approve_and_publish( $id );
		$this->assertWPError( $pub, 'Unconfirmed image rights must block publication.' );
		$this->confirm_rights( $img );
		$this->as_user( $this->editor() );
		$this->assertIsInt( Workflow::submit( $id ) );
		$this->as_user( $this->approver() );
		$this->assertTrue( Workflow::approve_and_publish( $id ) );
		// Replace the file: the confirmation no longer applies.
		$file = get_attached_file( $img );
		$im   = imagecreatetruecolor( 20, 20 );
		imagefill( $im, 0, 0, imagecolorallocate( $im, 1, 2, 3 ) );
		imagepng( $im, $file );
		$this->assertFalse( Noblen\Core\Clearance\MediaRights::is_confirmed( $img ) );
	}

	public function test_C31_epoch_bumps_on_withdrawal(): void {
		$co = $this->published_company();
		$id = $this->published_project( $co );
		$before = Epochs::get( $id );
		$this->as_user( $this->approver() );
		Workflow::unpublish( $id );
		$this->assertGreaterThan( $before, Epochs::get( $id ) );
	}

	public function test_C32_accessor_returns_nothing_for_ineligible_records(): void {
		$co = $this->published_company();
		$id = $this->published_project( $co );
		$this->assertNotNull( Accessor::get( $id ) );
		$this->as_user( $this->approver() );
		Workflow::unpublish( $id );
		$this->assertNull( Accessor::get( $id ) );
		$ids = array_map( static fn( $r ) => $r['id'] ?? 0, Accessor::query( Types::PROJECT ) );
		$this->assertNotContains( $id, $ids );
	}

	public function test_C32_unpublished_record_absent_from_search_and_archive(): void {
		$co = $this->company( 'Searchable Draft Co' );
		wp_set_current_user( 0 );
		$q = new WP_Query( array( 's' => 'Searchable', 'post_type' => 'any' ) );
		$this->assertCount( 0, $q->posts );
	}

	public function test_C27_audit_chain_verifies_and_detects_tampering(): void {
		$co = $this->published_company();
		$this->assertSame( 'verified', Verifier::state() );
		global $wpdb;
		$table = Schema::table( 'audit' );
		$last  = (int) $wpdb->get_var( "SELECT MAX(id) FROM `{$table}`" );
		$wpdb->update( $table, array( 'event_type' => 'tampered' ), array( 'id' => $last ) );
		Verifier::flush_cache();
		$this->assertSame( 'chain_broken', Verifier::state() );
	}

	public function test_C27_audit_reading_requires_capability(): void {
		$this->as_user( $this->editor() );
		$this->assertWPError( AuditLog::read_entries() );
	}

	public function test_C34_missing_key_blocks_new_approvals(): void {
		$co = $this->company();
		$this->complete_clearance( $co );
		$this->as_user( $this->editor() );
		Workflow::submit( $co );
		add_filter( 'noblen_core_audit_keys', '__return_empty_array', 99 );
		Verifier::flush_cache();
		$this->as_user( $this->approver() );
		$this->assertWPError( Workflow::approve( $co ) );
		remove_filter( 'noblen_core_audit_keys', '__return_empty_array', 99 );
		Verifier::flush_cache();
	}

	public function test_C25_migrator_is_idempotent_and_healthy(): void {
		$v = Migrator::installed_version();
		$this->assertSame( Schema::VERSION, $v );
		$this->assertTrue( Migrator::is_healthy() );
		$r = Migrator::run();
		$this->assertSame( Schema::VERSION, Migrator::installed_version() );
		$this->assertTrue( Schema::has_index( 'approvals', 'post_status' ) );
		$this->assertIsArray( $r );
	}

	public function test_C26_deactivation_preserves_data(): void {
		$co = $this->published_company();
		Noblen\Core\Activator::deactivate();
		$this->assertTrue( Schema::exists( 'approvals' ) );
		$this->assertSame( 'publish', get_post_status( $co ) );
	}
}

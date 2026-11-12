<?php
use Noblen\Core\Audit\AuditLog;
use Noblen\Core\Audit\Verifier;
use Noblen\Core\Clearance\Approvals;
use Noblen\Core\Clearance\Repository;
use Noblen\Core\Clearance\Snapshot;
use Noblen\Core\Clearance\Workflow;
use Noblen\Core\Content\Types;
use Noblen\Core\Database\Migrator;
use Noblen\Core\Database\Schema;
use Noblen\Core\Exposure\Withdrawal;
use Noblen\Core\Fields\Epochs;
use Noblen\Core\Fields\Registry;
use Noblen\Core\Fields\Store;
use Noblen\Core\PublicData\Accessor;

/**
 * Broader coverage: C02 activation guard, C22 sentinels, C23 per component, C25 migrations,
 * C29 media rights, C31 per trigger, C33 relationship classes.
 */
class CoverageTest extends NoblenTestCase {

	// ---- C02-004 activation guard.
	public function test_C02_004_unsupported_wordpress_is_refused_with_a_message(): void {
		global $wp_version;
		$saved      = $wp_version;
		$wp_version = '6.3.9';
		$msg        = noblen_core_compat_problem();
		$wp_version = $saved;
		$this->assertStringContainsString( 'WordPress 6.4', $msg );
		$this->assertSame( '', noblen_core_compat_problem() );
	}

	// ---- C33-001 every relationship field has a class; REQUIRED set is exactly business_unit.
	public function test_C33_001_every_relationship_has_a_valid_class(): void {
		$required = array();
		foreach ( Types::all() as $type ) {
			foreach ( Registry::relationships( $type ) as $key => $def ) {
				$this->assertContains( $def['class'] ?? '', array( 'required', 'optional' ), "$type.$key" );
				if ( 'required' === $def['class'] ) {
					$required[] = "$type.$key";
				}
			}
		}
		sort( $required );
		$this->assertSame( array( 'noblen_product.business_unit', 'noblen_project.business_unit' ), $required );
	}

	public function test_C33_002_required_relationship_missing_blocks_publication(): void {
		$id = $this->make( Types::PROJECT, 'No unit', array( 'overview' => '<p>x</p>', 'status' => 'completed_assignment' ) );
		$this->complete_clearance( $id );
		$this->as_user( $this->editor() );
		Workflow::submit( $id );
		$this->as_user( $this->approver() );
		$this->assertWPError( Workflow::approve_and_publish( $id ) );
		$this->assertNotSame( 'publish', get_post_status( $id ) );
	}

	// ---- C23 per component: every public scalar field, driven by the registry.
	public function public_scalar_fields(): array {
		$out = array();
		foreach ( array( Types::PROJECT, Types::PRODUCT, Types::COMPANY, Types::INDUSTRY, Types::SERVICE ) as $type ) {
			foreach ( Registry::public_fields( $type ) as $key => $def ) {
				if ( in_array( $def['kind'], array( 'text', 'richtext', 'url', 'bool', 'enum' ), true ) ) {
					$out[ "$type.$key" ] = array( $type, $key, $def['kind'], $def );
				}
			}
		}
		return $out;
	}

	/** @dataProvider public_scalar_fields */
	public function test_C23_001_every_public_field_change_withdraws_a_live_record( string $type, string $key, string $kind, array $def ): void {
		$id = $this->live_record( $type );
		$this->as_user( $this->approver() );
		switch ( $kind ) {
			case 'bool':
				$new = '1' === (string) get_post_meta( $id, Registry::meta_key( $key ), true ) ? '0' : '1';
				break;
			case 'enum':
				$cur = (string) get_post_meta( $id, Registry::meta_key( $key ), true );
				$new = (string) array_key_first( array_diff_key( $def['options'], array( $cur => 1 ) ) );
				break;
			case 'url':
				$new = 'https://example.test/' . wp_generate_password( 6, false );
				break;
			default:
				$new = '<p>Changed ' . wp_generate_password( 6, false ) . '</p>';
		}
		update_post_meta( $id, Registry::meta_key( $key ), 'url' === $kind || 'text' === $kind ? wp_strip_all_tags( $new ) : $new );
		clean_post_cache( $id );
		$this->assertFalse( $this->is_public( $id ), "$type.$key change must withdraw the record" );
	}

	/** @dataProvider public_scalar_fields */
	public function test_C23_002_every_public_field_is_part_of_the_snapshot( string $type, string $key, string $kind ): void {
		$id   = $this->live_record( $type );
		$hash = Snapshot::hash( $id );
		update_post_meta( $id, Registry::meta_key( $key ), 'bool' === $kind ? ( '1' === (string) get_post_meta( $id, Registry::meta_key( $key ), true ) ? '0' : '1' ) : ( 'enum' === $kind ? 'zzz-not-set' : 'https://snapshot.test/x' ) );
		$this->assertNotSame( $hash, Snapshot::hash( $id ), "$type.$key is not in the snapshot" );
	}

	/** A published record of the given type. */
	private function live_record( string $type ): int {
		$co = Types::COMPANY === $type ? 0 : $this->published_company( 'Unit for ' . $type . wp_generate_password( 4, false ) );
		$fields = array();
		if ( Types::PROJECT === $type ) {
			$fields = array( 'overview' => '<p>o</p>', 'status' => 'completed_assignment', 'business_unit' => array( $co ) );
		} elseif ( Types::PRODUCT === $type ) {
			$fields = array( 'overview' => '<p>o</p>', 'stage' => 'concept', 'business_unit' => array( $co ) );
		}
		$id = $this->make( $type, 'Live ' . $type . ' ' . wp_generate_password( 4, false ), $fields );
		$this->publish_record( $id );
		return $id;
	}

	// ---- C29 media rights: attributes that are part of the approved snapshot.
	/** @dataProvider media_changes */
	public function test_C29_each_image_attribute_change_withdraws_the_record( string $what ): void {
		$co  = $this->published_company();
		$img = $this->image();
		$this->confirm_rights( $img );
		$id = $this->project( $co, 'Image project ' . $what );
		set_post_thumbnail( $id, $img );
		$this->publish_record( $id );
		$this->as_user( $this->approver() );
		switch ( $what ) {
			case 'alt':
				update_post_meta( $img, '_wp_attachment_image_alt', 'New alt text' );
				break;
			case 'caption':
				wp_update_post( array( 'ID' => $img, 'post_excerpt' => 'New caption' ) );
				break;
			case 'title':
				wp_update_post( array( 'ID' => $img, 'post_title' => 'New image title' ) );
				break;
			case 'gallery_membership':
				$second = $this->image( 'blue' );
				$this->confirm_rights( $second );
				$this->as_user( $this->approver() );
				Store::save( $id, array( 'gallery' => array( $second ) ) );
				break;
		}
		clean_post_cache( $id );
		$this->assertFalse( $this->is_public( $id ), "$what must withdraw" );
	}

	public function media_changes(): array {
		return array( 'alt' => array( 'alt' ), 'caption' => array( 'caption' ), 'title' => array( 'title' ), 'gallery' => array( 'gallery_membership' ) );
	}

	public function test_C29_gallery_order_change_withdraws_the_record(): void {
		$co = $this->published_company();
		$a  = $this->image( 'red' );
		$b  = $this->image( 'blue' );
		$this->confirm_rights( $a );
		$this->confirm_rights( $b );
		$id = $this->project( $co, 'Gallery order', array( 'gallery' => array( $a, $b ) ) );
		$this->publish_record( $id );
		$this->as_user( $this->approver() );
		Store::save( $id, array( 'gallery' => array( $b, $a ) ) );
		clean_post_cache( $id );
		$this->assertFalse( $this->is_public( $id ) );
	}

	// ---- C31 per trigger T01 to T09.
	/** @dataProvider triggers */
	public function test_C31_withdrawal_procedure_for_each_trigger( string $trigger ): void {
		$co = $this->published_company();
		$id = $this->published_project( $co, 'Trigger ' . $trigger );
		$epoch  = Epochs::get( $id );
		$fired  = array();
		$hook   = static function ( $post_id, $reason, $urls ) use ( &$fired ) {
			$fired[] = array( $post_id, $reason, $urls );
		};
		add_action( 'noblen_core_record_withdrawn', $hook, 10, 3 );
		$this->as_user( $this->approver() );
		switch ( $trigger ) {
			case 'T01':
				Workflow::revoke( $id );
				break;
			case 'T02':
				Workflow::unpublish( $id );
				break;
			case 'T03':
				Workflow::reject( $id );
				break;
			case 'T04':
				Store::save( $id, array( 'overview' => '<p>edited</p>' ) );
				break;
			case 'T05':
				update_post_meta( $id, '_noblen_f_scope', '<p>direct</p>' );
				break;
			case 'T06':
				global $wpdb;
				$wpdb->update( $wpdb->posts, array( 'post_status' => 'publish' ), array( 'ID' => $id ) );
				clean_post_cache( $id );
				wp_transition_post_status( 'publish', 'draft', get_post( $id ) );
				break;
			case 'T07':
				Workflow::unpublish( $co );
				break;
			case 'T08':
				wp_trash_post( $id );
				break;
			case 'T09':
				wp_delete_post( $id, true );
				break;
		}
		remove_action( 'noblen_core_record_withdrawn', $hook, 10 );
		clean_post_cache( $id );
		$this->assertNotEmpty( $fired, "$trigger fires noblen_core_record_withdrawn" );
		$ours = array_values( array_filter( $fired, static fn( $f ) => (int) $f[0] === $id ) );
		if ( 'T07' === $trigger ) {
			$this->assertNotEmpty( $ours, 'T07 cascades to the dependent' );
		}
		$this->assertNotEmpty( $ours, "$trigger withdraws the record itself" );
		$this->assertIsArray( $ours[0][2], 'purge URL list is supplied' );
		$this->assertNotEmpty( $ours[0][2], 'purge URL list names addresses' );
		if ( 'T09' !== $trigger ) {
			$this->assertGreaterThan( $epoch, Epochs::get( $id ), "$trigger bumps the content epoch" );
			$this->assertNull( Accessor::get( $id ), "$trigger leaves nothing in the accessor" );
			$this->assertNotSame( 'publish', get_post_status( $id ) );
		}
		$entries = array_filter( AuditLog::by_event( 'record_withdrawn' ), static fn( $e ) => (int) $e['post_id'] === $id );
		$this->assertNotEmpty( $entries, "$trigger is audited" );
	}

	public function triggers(): array {
		return array_combine( array( 'T01', 'T02', 'T03', 'T04', 'T05', 'T06', 'T07', 'T08', 'T09' ), array_map( static fn( $t ) => array( $t ), array( 'T01', 'T02', 'T03', 'T04', 'T05', 'T06', 'T07', 'T08', 'T09' ) ) );
	}

	public function test_C28_cascade_stops_at_one_level(): void {
		$co   = $this->published_company();
		$proj = $this->published_project( $co, 'Cascade project' );
		$prod = $this->make( Types::PRODUCT, 'Cascade product', array( 'overview' => '<p>o</p>', 'stage' => 'concept', 'business_unit' => array( $co ), 'related_projects' => array( $proj ) ) );
		$this->publish_record( $prod );
		$this->as_user( $this->approver() );
		Workflow::unpublish( $co );
		clean_post_cache( $proj );
		clean_post_cache( $prod );
		$this->assertFalse( $this->is_public( $proj ) );
		$this->assertFalse( $this->is_public( $prod ), 'direct required dependent of the company is withdrawn' );
	}

	// ---- C22 sentinels: private values never reach public outputs.
	public function test_C22_private_values_absent_from_public_outputs(): void {
		$co = $this->published_company();
		$id = $this->project( $co, 'Sentinel project' );
		$this->as_user( $this->officer() );
		$items = array();
		foreach ( array_keys( Repository::items_for_type( Types::PROJECT ) ) as $i ) {
			$items[ $i ] = 'yes';
		}
		$sentinel = 'SENTINEL-EVIDENCE-7d3f9a';
		Repository::save( $id, $items, $sentinel );
		$this->as_user( $this->editor() );
		Workflow::submit( $id );
		$this->as_user( $this->approver() );
		Workflow::approve_and_publish( $id );
		wp_set_current_user( 0 );

		$outputs = array(
			'accessor'  => wp_json_encode( Accessor::get( $id ) ),
			'query'     => wp_json_encode( Accessor::query( Types::PROJECT ) ),
			'post_meta' => wp_json_encode( get_post_meta( $id ) ),
			'search'    => wp_json_encode( ( new WP_Query( array( 's' => 'Sentinel', 'post_type' => 'any' ) ) )->posts ),
			'rest'      => wp_json_encode( rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/types' ) )->get_data() ),
			'routes'    => wp_json_encode( array_keys( rest_get_server()->get_routes() ) ),
		);
		require_once ABSPATH . 'wp-admin/includes/export.php';
		set_error_handler( static fn() => true ); // export_wp() sends download headers, which CLI cannot.
		ob_start();
		export_wp( array( 'content' => 'all' ) );
		$outputs['export'] = ob_get_clean();
		restore_error_handler();
		foreach ( $outputs as $name => $out ) {
			$this->assertStringNotContainsString( $sentinel, (string) $out, "private evidence leaked into $name" );
			$this->assertStringNotContainsString( 'client_consent', (string) $out, "clearance item names leaked into $name" );
			$this->assertStringNotContainsString( 'snapshot_hash', (string) $out, "approval data leaked into $name" );
		}
		$this->assertStringNotContainsString( 'noblen_project', $outputs['rest'], 'custom types are not exposed through REST' );
		$this->assertSame( 0, count( array_filter( array_keys( rest_get_server()->get_routes() ), static fn( $r ) => false !== strpos( $r, 'noblen' ) ) ), 'no Noblen REST routes exist' );
	}

	public function test_C22_clearance_unreadable_without_capability(): void {
		$co = $this->published_company();
		$id = $this->project( $co );
		$this->as_user( $this->editor() );
		$this->assertWPError( Repository::get( $id ) );
		$this->assertWPError( AuditLog::read_entries() );
		wp_set_current_user( 0 );
		$this->assertWPError( Repository::get( $id ) );
	}

	// ---- C25 migrations: upgrade from a v1 fixture, failure state, lock.
	public function test_C25_002_upgrade_from_v1_adds_the_index_and_is_idempotent(): void {
		$this->expects_commit = true; // Schema changes implicitly commit; leaked rows are purged in tearDown.
		global $wpdb;
		$table = Schema::table( 'approvals' );
		$wpdb->query( "ALTER TABLE `{$table}` DROP INDEX `post_status`" ); // phpcs:ignore WordPress.DB
		update_option( 'noblen_core_db_version', 1 );
		delete_option( Migrator::OPT_BACKUP );
		$this->assertFalse( Schema::has_index( 'approvals', 'post_status' ) );
		$this->assertTrue( Migrator::pending() );
		$blocked = Migrator::run();
		$this->assertFalse( $blocked['ok'] );
		$this->assertSame( 'backup_required', $blocked['code'] );
		Migrator::confirm_backup( Schema::VERSION, $this->approver() );
		$r = Migrator::run();
		$this->assertTrue( $r['ok'], wp_json_encode( $r ) );
		$this->assertTrue( Schema::has_index( 'approvals', 'post_status' ) );
		$this->assertSame( Schema::VERSION, Migrator::installed_version() );
		$again = Migrator::run();
		$this->assertTrue( $again['ok'] );
		$this->assertSame( 'current', $again['code'] );
	}

	public function test_C25_004_failed_migration_blocks_publication_and_keeps_data(): void {
		$this->expects_commit = true; // Schema changes implicitly commit; leaked rows are purged in tearDown.
		$co = $this->published_company();
		$id = $this->project( $co, 'Survives failed migration' );
		global $wpdb;
		$wpdb->query( 'ALTER TABLE `' . Schema::table( 'approvals' ) . '` DROP INDEX `post_status`' ); // phpcs:ignore WordPress.DB
		update_option( 'noblen_core_db_version', 1 );
		Migrator::confirm_backup( Schema::VERSION, $this->approver() );
		$thrower = static function () {
			throw new \RuntimeException( 'simulated failure' );
		};
		add_filter( 'noblen_core_migration_step', $thrower );
		$log = ini_set( 'error_log', '/dev/null' ); // The failure is logged by design.
		$r   = Migrator::run();
		ini_set( 'error_log', (string) $log );
		remove_filter( 'noblen_core_migration_step', $thrower );
		$this->assertFalse( $r['ok'] );
		$this->assertSame( 'failed', $r['code'] );
		$this->assertFalse( Migrator::is_healthy() );
		$this->complete_clearance( $id );
		$this->as_user( $this->editor() );
		$this->assertIsInt( Workflow::submit( $id ), 'submission is not blocked' );
		$this->as_user( $this->approver() );
		$this->assertWPError( Workflow::approve( $id ), 'approval is blocked while migration has failed' );
		$this->assertSame( $id, (int) get_post( $id )->ID, 'no data lost' );
		Migrator::clear_failure();
		$this->assertTrue( Migrator::run()['ok'] );
		$this->assertTrue( Migrator::is_healthy() );
	}

	public function test_C25_005_concurrent_migration_is_refused_by_the_lock(): void {
		$this->expects_commit = true; // Schema changes implicitly commit; leaked rows are purged in tearDown.
		global $wpdb;
		$wpdb->query( 'ALTER TABLE `' . Schema::table( 'approvals' ) . '` DROP INDEX `post_status`' ); // phpcs:ignore WordPress.DB
		update_option( Migrator::OPT_VERSION, 1 );
		Migrator::confirm_backup( Schema::VERSION, $this->approver() );
		add_option( Migrator::OPT_LOCK, time(), '', 'no' ); // Another process holds the lock.
		$r = Migrator::run();
		$this->assertFalse( $r['ok'] );
		$this->assertSame( 'locked', $r['code'] );
		$this->assertSame( 1, Migrator::installed_version(), 'nothing was applied while locked' );
		delete_option( Migrator::OPT_LOCK );
		$this->assertTrue( Migrator::run()['ok'] );
		$this->assertSame( Schema::VERSION, Migrator::installed_version() );
		$this->assertTrue( Schema::has_index( 'approvals', 'post_status' ) );
	}
}

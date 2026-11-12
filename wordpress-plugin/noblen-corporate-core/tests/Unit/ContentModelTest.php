<?php
use Noblen\Core\Capabilities;
use Noblen\Core\Clearance\Workflow;
use Noblen\Core\Content\Types;
use Noblen\Core\Fields\Registry;
use Noblen\Core\Fields\Store;
use Noblen\Core\Relationships\Relations;

/**
 * C05 content types, C07 relationships, C09 capabilities, C21 classic editor (plugin side).
 */
class ContentModelTest extends NoblenTestCase {

	/** @return array<string,array{0:string}> */
	public static function types(): array {
		return array(
			'project'  => array( Types::PROJECT ),
			'product'  => array( Types::PRODUCT ),
			'company'  => array( Types::COMPANY ),
			'industry' => array( Types::INDUSTRY ),
			'service'  => array( Types::SERVICE ),
		);
	}

	/**
	 * C05-001..005
	 *
	 * @dataProvider types
	 */
	public function test_C05_each_type_create_read_update_trash_restore( string $type ): void {
		$id = $this->make( $type, 'First title' );
		$this->assertSame( $type, get_post_type( $id ) );
		$this->assertSame( 'First title', get_post( $id )->post_title );

		$this->as_user( $this->editor() );
		wp_update_post( array( 'ID' => $id, 'post_title' => 'Second title' ) );
		$this->assertSame( 'Second title', get_post( $id )->post_title );

		wp_trash_post( $id );
		$this->assertSame( 'trash', get_post_status( $id ) );
		wp_untrash_post( $id );
		$this->assertNotSame( 'publish', get_post_status( $id ), 'A restored record must never come back public.' );
		$this->assertSame( 'Second title', get_post( $id )->post_title );
	}

	public function test_C05_a_publish_request_by_an_editor_is_never_stored_as_publish(): void {
		$this->as_user( $this->editor() );
		$id = wp_insert_post( array( 'post_type' => Types::COMPANY, 'post_title' => 'Try', 'post_status' => 'publish' ) );
		$this->assertNotSame( 'publish', get_post_status( $id ) );
	}

	/** C07-001 relationship fields store and read; C07-002 integrity; C07-003 reverse query */
	public function test_C07_relationships_store_validate_and_reverse_query(): void {
		$company = $this->company();
		$industry = $this->make( Types::INDUSTRY, 'Media' );
		$project = $this->project( $company );
		$errors  = Store::save( $project, array( 'industries' => array( $industry ) ) );
		$this->assertSame( array(), $errors );

		$values = Store::get_all( $project );
		$this->assertSame( array( $company ), $values['business_unit'] );
		$this->assertSame( array( $industry ), $values['industries'] );

		$deps = Relations::dependents( $company );
		$this->assertCount( 1, $deps );
		$this->assertSame( $project, $deps[0]['id'] );
		$this->assertSame( 'required', $deps[0]['class'], 'The business unit is the required relationship.' );

		$deps = Relations::dependents( $industry );
		$this->assertSame( 'optional', $deps[0]['class'] );
	}

	public function test_C07_integrity_rejects_wrong_type_missing_and_self_targets(): void {
		$company = $this->company();
		$project = $this->project( $company );
		$errors  = Store::save( $project, array( 'industries' => array( $company ) ) ); // A company is not an industry.
		$this->assertArrayHasKey( 'industries', $errors );
		$errors = Store::save( $project, array( 'industries' => array( 999999 ) ) );
		$this->assertArrayHasKey( 'industries', $errors );
		$errors = Store::save( $project, array( 'business_unit' => array( $company, $company + 1 ) ) ); // Single value only.
		$this->assertArrayHasKey( 'business_unit', $errors );
		$this->assertSame( array( $company ), Store::get_all( $project )['business_unit'], 'Invalid input must not overwrite saved data.' );
	}

	public function test_C07_every_relationship_field_has_a_class(): void {
		foreach ( Types::all() as $type ) {
			foreach ( Registry::relationships( $type ) as $key => $def ) {
				$this->assertContains( $def['class'], array( 'required', 'optional' ), "$type.$key" );
			}
		}
		$this->assertSame( array( 'business_unit' ), array_keys( Registry::relationships( Types::PROJECT, 'required' ) ) );
		$this->assertSame( array( 'business_unit' ), array_keys( Registry::relationships( Types::PRODUCT, 'required' ) ) );
		$this->assertSame( array(), Registry::relationships( Types::COMPANY, 'required' ) );
	}

	/** C09-001 role and capability matrix */
	public function test_C09_role_and_capability_matrix(): void {
		$company = $this->company();

		$this->as_user( $this->editor() );
		$this->assertTrue( current_user_can( 'edit_post', $company ) );
		$this->assertFalse( current_user_can( 'publish_post', $company ) );
		$this->assertFalse( current_user_can( 'noblen_approve_publication' ) );
		$this->assertFalse( current_user_can( 'noblen_manage_clearance' ) );
		$this->assertFalse( current_user_can( 'noblen_view_audit' ) );

		$this->as_user( $this->officer() );
		$this->assertTrue( current_user_can( 'noblen_manage_clearance' ) );
		$this->assertFalse( current_user_can( 'noblen_approve_publication' ) );
		$this->assertFalse( current_user_can( 'publish_post', $company ) );

		$this->as_user( $this->approver() );
		$this->assertTrue( current_user_can( 'noblen_approve_publication' ) );
		$this->assertTrue( current_user_can( 'noblen_view_audit' ) );
		$this->assertTrue( current_user_can( 'publish_post', $company ) );

		// Administrators are trusted but do not automatically hold approval, clearance or audit rights.
		$admin = $this->user( 'administrator', 'admin' );
		$this->as_user( $admin );
		$this->assertTrue( current_user_can( 'edit_post', $company ) );
		$this->assertFalse( current_user_can( 'noblen_approve_publication' ) );
		$this->assertFalse( current_user_can( 'noblen_manage_clearance' ) );
		$this->assertFalse( current_user_can( 'noblen_view_audit' ) );

		// Anonymous.
		wp_set_current_user( 0 );
		$this->assertFalse( current_user_can( 'edit_post', $company ) );
	}

	/** C09-002 forged approval attempts are refused */
	public function test_C09_unauthorised_users_cannot_approve_or_publish(): void {
		$company = $this->company();
		$this->complete_clearance( $company );
		$this->as_user( $this->editor() );
		$this->assertIsInt( Workflow::submit( $company ) );

		foreach ( array( $this->editor(), $this->officer(), $this->user( 'administrator', 'admin' ), 0 ) as $uid ) {
			$this->as_user( $uid );
			$r = Workflow::approve( $company );
			$this->assertWPError( $r );
			$this->assertSame( 'noblen_forbidden', $r->get_error_code() );
		}
		$this->assertNotSame( 'publish', get_post_status( $company ) );
	}

	/** C21-011/012 REST is off; C21-019 standard posts untouched; classic editor for all five types */
	public function test_C21_custom_types_use_classic_editor_and_have_no_rest(): void {
		foreach ( Types::all() as $type ) {
			$this->assertFalse( apply_filters( 'use_block_editor_for_post_type', true, $type ), $type );
			$obj = get_post_type_object( $type );
			$this->assertFalse( $obj->show_in_rest, $type );
		}
		$this->assertTrue( apply_filters( 'use_block_editor_for_post_type', true, 'post' ), 'Standard posts keep the block editor.' );
		$this->assertTrue( get_post_type_object( 'post' )->show_in_rest );

		$routes = rest_get_server()->get_routes();
		foreach ( Types::all() as $type ) {
			$this->assertArrayNotHasKey( '/wp/v2/' . $type, $routes );
			$this->assertArrayNotHasKey( '/wp/v2/' . $type . 's', $routes );
		}
		$this->assertArrayHasKey( '/wp/v2/posts', $routes );
	}

	/** C21-013/015: structured fields save and validate */
	public function test_C21_structured_fields_save_and_validate(): void {
		$company = $this->company();
		$project = $this->project( $company );
		$errors = Store::save( $project, array( 'status' => 'not_a_status', 'video_url' => 'javascript:alert(1)', 'client' => '<b>Acme</b> <script>x</script>' ) );
		$this->assertArrayHasKey( 'status', $errors );
		$this->assertArrayHasKey( 'video_url', $errors );
		$values = Store::get_all( $project );
		$this->assertSame( 'completed_assignment', $values['status'], 'An invalid value must not overwrite the saved one.' );
		$this->assertSame( '', $values['video_url'] );
		$this->assertStringNotContainsString( '<', $values['client'] );

		$errors = Store::save( $project, array( 'overview' => '<p>Ok</p><script>alert(1)</script><a href="javascript:x()">bad</a>' ) );
		$this->assertSame( array(), $errors );
		$overview = Store::get_all( $project )['overview'];
		$this->assertStringNotContainsString( '<script', $overview );
		$this->assertStringNotContainsString( 'javascript:', $overview );
	}

	public function test_taxonomies_are_classification_only_and_seeded(): void {
		$this->assertTrue( taxonomy_exists( Types::TAX_AREA ) );
		$this->assertCount( 5, get_terms( array( 'taxonomy' => Types::TAX_AREA, 'hide_empty' => false ) ) );
		$this->assertFalse( taxonomy_exists( 'noblen_business_unit' ) );
		$this->assertFalse( taxonomy_exists( 'noblen_industry_tax' ) );
		$this->assertFalse( get_taxonomy( Types::TAX_AREA )->show_in_rest );
	}
}

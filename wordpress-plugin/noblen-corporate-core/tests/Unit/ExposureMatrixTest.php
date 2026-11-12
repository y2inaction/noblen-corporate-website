<?php
use Noblen\Core\Clearance\Workflow;
use Noblen\Core\Content\Types;
use Noblen\Core\Fields\Store;
use Noblen\Core\PublicData\Accessor;

/**
 * C32: for each ineligible state, the record is absent from every supported public interface.
 * Interfaces are queried in-process as an anonymous visitor.
 */
class ExposureMatrixTest extends NoblenTestCase {

	public function states(): array {
		return array(
			'draft'              => array( 'draft' ),
			'pending'            => array( 'pending' ),
			'stale'              => array( 'stale' ),
			'revoked'            => array( 'revoked' ),
			'trash'              => array( 'trash' ),
			'dependency_lapsed'  => array( 'dependency_lapsed' ),
			'tampered_directly'  => array( 'tampered_directly' ),
		);
	}

	/** Build a project in the requested ineligible state. Returns its ID. */
	private function project_in_state( string $state, string $title ): int {
		$co = $this->published_company( 'Parent ' . wp_generate_password( 8, false ) );
		$id = $this->project( $co, $title );
		switch ( $state ) {
			case 'draft':
				break;
			case 'pending':
				$this->complete_clearance( $id );
				$this->as_user( $this->editor() );
				Workflow::submit( $id );
				break;
			default:
				$this->publish_record( $id );
				$this->as_user( $this->approver() );
				if ( 'stale' === $state ) {
					Store::save( $id, array( 'overview' => '<p>edited later</p>' ) );
				} elseif ( 'revoked' === $state ) {
					Workflow::revoke( $id );
				} elseif ( 'trash' === $state ) {
					wp_trash_post( $id );
				} elseif ( 'dependency_lapsed' === $state ) {
					Workflow::unpublish( $co );
				} elseif ( 'tampered_directly' === $state ) {
					global $wpdb;
					$wpdb->update( $wpdb->posts, array( 'post_content' => 'Changed straight in the database' ), array( 'ID' => $id ) ); // phpcs:ignore WordPress.DB
					$wpdb->insert( $wpdb->postmeta, array( 'post_id' => $id, 'meta_key' => '_noblen_f_scope', 'meta_value' => '<p>direct db</p>' ) ); // phpcs:ignore WordPress.DB
					clean_post_cache( $id );
					wp_cache_flush();
					\Noblen\Core\Clearance\ReadCheck::flush();
				}
		}
		clean_post_cache( $id );
		wp_set_current_user( 0 );
		return $id;
	}

	/** @dataProvider states */
	public function test_C32_record_absent_from_every_public_interface( string $state ): void {
		$title = 'Matrix ' . $state . ' ' . wp_generate_password( 5, false );
		$id    = $this->project_in_state( $state, $title );
		$url   = (string) get_permalink( $id );

		$single = new WP_Query( array( 'p' => $id, 'post_type' => Types::PROJECT ) );
		$this->assertCount( 0, $single->posts, "$state: single address" );

		$slug = (string) get_post_field( 'post_name', $id );
		if ( '' !== $slug ) { // WordPress ignores an empty name, so only test when a slug exists.
			$by_name = new WP_Query( array( 'name' => $slug, 'post_type' => Types::PROJECT ) );
			$this->assertCount( 0, $by_name->posts, "$state: single address by slug" );
		}

		$search = new WP_Query( array( 's' => $title, 'post_type' => 'any' ) );
		$this->assertCount( 0, $search->posts, "$state: main search" );

		$archive = new WP_Query( array( 'post_type' => Types::PROJECT, 'posts_per_page' => -1 ) );
		$this->assertNotContains( $id, wp_list_pluck( $archive->posts, 'ID' ), "$state: archive" );

		$tax = new WP_Query( array( 'post_type' => Types::PROJECT, 'posts_per_page' => -1, 'tax_query' => array( array( 'taxonomy' => Types::TAX_AREA, 'operator' => 'EXISTS' ) ) ) );
		$this->assertNotContains( $id, wp_list_pluck( $tax->posts, 'ID' ), "$state: classification archive" );

		$feed = new WP_Query();
		$feed->query( array( 'feed' => 'rss2', 'post_type' => Types::PROJECT, 'posts_per_page' => -1 ) );
		$this->assertNotContains( $id, wp_list_pluck( $feed->posts, 'ID' ), "$state: feed" );

		$sitemap = wp_sitemaps_get_server()->registry->get_provider( 'posts' );
		$this->assertNotContains( $url, wp_list_pluck( $sitemap->get_url_list( 1, Types::PROJECT ), 'loc' ), "$state: core sitemap" );

		$req   = new WP_REST_Request( 'GET', '/oembed/1.0/embed' );
		$req->set_param( 'url', $url );
		$embed = rest_do_request( $req );
		$this->assertSame( 404, $embed->get_status(), "$state: oEmbed endpoint " . wp_json_encode( array( $url, $embed->get_data() ) ) );

		$this->assertNull( Accessor::get( $id ), "$state: accessor (single)" );
		$this->assertNotContains( $id, wp_list_pluck( Accessor::query( Types::PROJECT ), 'id' ), "$state: accessor (list)" );

		$this->assertNotContains( $id, wp_list_pluck( get_posts( array( 'post_type' => Types::PROJECT, 'post_status' => 'publish', 'numberposts' => -1, 'suppress_filters' => false ) ), 'ID' ), "$state: get_posts (filters on; get_posts() skips filters by default, so theme code must use the accessor)" );
	}

	public function test_C32_related_content_hides_an_unavailable_business_unit(): void {
		$co   = $this->published_company( 'Related unit' );
		$proj = $this->published_project( $co, 'Has unit' );
		$this->assertSame( $co, Accessor::get( $proj )['business_unit']['id'] ?? 0 );
		$this->as_user( $this->approver() );
		Workflow::unpublish( $co );
		$data = Accessor::get( $proj );
		// The dependent is withdrawn by its required relationship; if it is still served it must not name the unit.
		$this->assertTrue( null === $data || null === $data['business_unit'] );
	}
}

<?php
use Noblen\Core\Clearance\Approvals;
use Noblen\Core\Content\Types;
use Noblen\Core\Setup\Blueprint;
use Noblen\Core\Setup\Setup;
use Noblen\Core\Setup\SetupPage;
use Noblen\Core\Settings\Settings;

/**
 * Website Setup: first run, repeat runs, preservation of existing content, conflicts, recovery, draft-only records, capability and
 * nonce protection, and activation only for destinations the public can reach.
 */
class SetupTest extends NoblenTestCase {

	protected function setUp(): void {
		parent::setUp();
		delete_option( Setup::STATE );
		wp_set_current_user( 0 );
		$this->clean_baseline();
	}

	/**
	 * A known baseline. A fresh WordPress ships a draft "Privacy Policy" page, and a site that has run the setup already holds its
	 * items. Remove anything at a blueprint address, name or title so each test starts clean. Everything here is rolled back with the
	 * test; conflict and preservation behaviour has its own tests.
	 */
	private function clean_baseline(): void {
		global $wpdb;
		foreach ( Blueprint::pages() as $def ) {
			foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_name = %s", $def['slug'] ) ) as $id ) { // phpcs:ignore WordPress.DB
				wp_delete_post( (int) $id, true );
			}
		}
		foreach ( Blueprint::records() as $type => $titles ) {
			foreach ( $titles as $title ) {
				foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_title = %s", $type, $title ) ) as $id ) { // phpcs:ignore WordPress.DB
					wp_delete_post( (int) $id, true );
				}
			}
		}
		foreach ( Blueprint::menus() as $def ) {
			$menu = wp_get_nav_menu_object( $def['name'] );
			if ( $menu ) {
				wp_delete_nav_menu( $menu->term_id );
			}
			$loc = get_nav_menu_locations();
			unset( $loc[ $def['location'] ] );
			set_theme_mod( 'nav_menu_locations', $loc );
		}
	}

	private function admin(): void {
		$this->as_user( $this->user( 'administrator', 'setup-admin' ) );
	}

	/** @return array<string,mixed> */
	private function prepare(): array {
		$this->admin();
		$res = Setup::prepare();
		$this->assertIsArray( $res, is_wp_error( $res ) ? $res->get_error_message() : '' );
		return $res;
	}

	/** @return array<string,mixed> */
	private function activate( array $opts ): array {
		$this->admin();
		$res = Setup::activate( $opts );
		$this->assertIsArray( $res, is_wp_error( $res ) ? $res->get_error_message() : '' );
		return $res;
	}

	private function owned_ids( string $type ): array {
		return get_posts(
			array(
				'post_type'        => $type,
				'post_status'      => 'any',
				'meta_key'         => Setup::META,
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);
	}

	private function page_id( string $key ): int {
		$found = get_posts( array( 'post_type' => 'page', 'post_status' => 'any', 'meta_key' => Setup::META, 'meta_value' => 'page:' . $key, 'posts_per_page' => 1, 'fields' => 'ids' ) );
		return $found ? (int) $found[0] : 0;
	}

	private function menu_term( string $key ): int {
		$t = get_terms( array( 'taxonomy' => 'nav_menu', 'hide_empty' => false, 'fields' => 'ids', 'meta_key' => Setup::META, 'meta_value' => 'menu:' . $key ) );
		return $t ? (int) $t[0] : 0;
	}

	private function outcomes( array $res ): array {
		$o = array();
		foreach ( $res['items'] as $i ) {
			$o[ $i['key'] ?? $i['label'] ] = $i['outcome'];
		}
		return $o;
	}

	/** Complete a page's content so it is ready for review. */
	private function complete_page( string $key ): void {
		wp_update_post( array( 'ID' => $this->page_id( $key ), 'post_content' => '<p>Verified content written by the owner.</p>' ) );
	}

	public function test_first_run_creates_the_structure_as_drafts_only(): void {
		$res = $this->prepare();
		$this->assertSame( 0, $res['counts']['error'] );
		$pages = Blueprint::pages();
		$this->assertCount( 12, $pages );
		foreach ( $pages as $key => $def ) {
			$id = $this->page_id( $key );
			$this->assertGreaterThan( 0, $id, "{$key} page created" );
			$p = get_post( $id );
			$this->assertSame( 'draft', $p->post_status, "{$key} is a draft" );
			$this->assertSame( $def['slug'], $p->post_name );
			$this->assertSame( $def['title'], $p->post_title );
		}
		foreach ( Blueprint::menus() as $key => $def ) {
			$term = wp_get_nav_menu_object( $def['name'] );
			$this->assertNotFalse( $term, "{$key} menu created" );
			$this->assertSame( $this->menu_term( $key ), (int) $term->term_id );
			$this->assertCount( 0, (array) wp_get_nav_menu_items( $term->term_id ), 'menus start empty' );
		}
		// Nothing public changed.
		$this->assertSame( array(), array_filter( get_nav_menu_locations() ), 'no menu is assigned by preparation' );
		$this->assertSame( 'posts', get_option( 'show_on_front' ) );
		$this->assertSame( 0, (int) get_option( 'page_on_front' ) );
	}

	public function test_the_ten_site_sections_and_the_legal_pages_all_have_pages(): void {
		$titles = array();
		foreach ( Blueprint::pages() as $p ) {
			$titles[] = $p['title'];
		}
		foreach ( array( 'Home', 'About Noblen', 'Our Businesses', 'Industries', 'Solutions & Services', 'Portfolio', 'Products & Platforms', 'Insights', 'Partnerships', 'Contact' ) as $t ) {
			$this->assertContains( $t, $titles, "{$t} is provided" );
		}
		$this->assertContains( 'Privacy Policy', $titles );
		$this->assertContains( 'Terms and Conditions', $titles );
		// A page at an archive address would never be reached, so no page may use one.
		foreach ( array( 'group', 'industries', 'solutions', 'projects', 'products' ) as $reserved ) {
			foreach ( Blueprint::pages() as $p ) {
				$this->assertNotSame( $reserved, $p['slug'], "no page duplicates the /{$reserved}/ archive" );
			}
		}
	}

	public function test_repeat_runs_create_no_duplicates(): void {
		$this->prepare();
		$before = array(
			'pages'   => count( $this->owned_ids( 'page' ) ),
			'records' => array_sum( array_map( fn( $t ) => count( $this->owned_ids( $t ) ), Types::all() ) ),
			'menus'   => count( get_terms( array( 'taxonomy' => 'nav_menu', 'hide_empty' => false, 'meta_key' => Setup::META ) ) ),
		);
		$second = $this->prepare();
		$third  = $this->prepare();
		$this->assertSame( 0, $second['counts']['created'] );
		$this->assertSame( 0, $third['counts']['created'] );
		$this->assertSame( $before['pages'], count( $this->owned_ids( 'page' ) ) );
		$this->assertSame( $before['records'], array_sum( array_map( fn( $t ) => count( $this->owned_ids( $t ) ), Types::all() ) ) );
		$this->assertSame( $before['menus'], count( get_terms( array( 'taxonomy' => 'nav_menu', 'hide_empty' => false, 'meta_key' => Setup::META ) ) ) );
		$this->assertSame( 3, $before['menus'] );
		$this->assertSame( 12, $before['pages'] );
	}

	public function test_an_existing_page_is_preserved_and_reported_as_a_conflict(): void {
		$mine = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'Our own About', 'post_name' => 'about', 'post_status' => 'publish', 'post_content' => 'MY ORIGINAL CONTENT' ) );
		$res  = $this->prepare();
		$this->assertSame( 'conflict', $this->outcomes( $res )['page:about'] );
		$kept = get_post( $mine );
		$this->assertSame( 'MY ORIGINAL CONTENT', $kept->post_content );
		$this->assertSame( 'Our own About', $kept->post_title );
		$this->assertSame( 'publish', $kept->post_status );
		$this->assertSame( 0, $this->page_id( 'about' ), 'the setup created no second About page' );
		$status = Setup::status();
		$this->assertSame( 'conflict', $status['pages']['about']['class'] );
	}

	public function test_an_existing_draft_or_trashed_page_at_the_slug_is_also_a_conflict(): void {
		$trashed = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'Old contact', 'post_name' => 'contact', 'post_status' => 'trash' ) );
		$res     = $this->prepare();
		$this->assertSame( 'conflict', $this->outcomes( $res )['page:contact'] );
		$this->assertSame( 'trash', get_post_status( $trashed ), 'the trashed page was not touched' );
	}

	public function test_an_existing_menu_is_preserved_and_never_replaced(): void {
		$menu_id = wp_create_nav_menu( 'Primary Navigation' );
		$item    = wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-title' => 'My link', 'menu-item-url' => 'https://example.test/mine', 'menu-item-status' => 'publish' ) );
		$res     = $this->prepare();
		$this->assertSame( 'conflict', $this->outcomes( $res )['menu:primary'] );
		$this->assertSame( 0, $this->menu_term( 'primary' ), 'no second Primary Navigation was created' );
		$items = wp_get_nav_menu_items( $menu_id );
		$this->assertCount( 1, $items );
		$this->assertSame( 'My link', $items[0]->title );
		$this->assertSame( $item, (int) $items[0]->ID );
		// Activation must not touch it either.
		$this->activate( array( 'assign' => array( 'primary' => true ) ) );
		$this->assertCount( 1, wp_get_nav_menu_items( $menu_id ) );
		$this->assertSame( 0, (int) ( get_nav_menu_locations()['primary'] ?? 0 ), 'a menu the setup did not create is never assigned by it' );
	}

	public function test_existing_homepage_settings_are_preserved_unless_the_administrator_replaces_them(): void {
		$front = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'My front', 'post_status' => 'publish', 'post_content' => 'front' ) );
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $front );
		$this->prepare();
		$this->activate( array() );
		$this->assertSame( $front, (int) get_option( 'page_on_front' ), 'preparing and a plain activation change nothing' );

		$this->complete_page( 'home' );
		$this->activate( array( 'publish' => array( 'home' => true ) ) );
		$res = $this->activate( array( 'front_page' => true ) );
		$this->assertSame( 'conflict', $this->outcomes( $res )['Home page as front page'] );
		$this->assertSame( $front, (int) get_option( 'page_on_front' ), 'a different static front page is kept without the replace choice' );

		$this->activate( array( 'front_page' => true, 'replace_front_page' => true ) );
		$this->assertSame( $this->page_id( 'home' ), (int) get_option( 'page_on_front' ) );
		$this->assertSame( 'page', get_option( 'show_on_front' ) );
		$this->assertSame( 'publish', get_post_status( $front ), 'the old front page itself is untouched' );
	}

	public function test_existing_settings_are_kept_when_hero_defaults_are_applied(): void {
		Settings::save( array_merge( Settings::all(), array( 'hero_headline' => 'My own headline' ) ) );
		$this->prepare();
		$this->activate( array( 'hero_defaults' => true ) );
		$s = Settings::all();
		$this->assertSame( 'My own headline', $s['hero_headline'], 'existing text is kept' );
		$this->assertSame( Blueprint::hero_defaults()['hero_intro'], $s['hero_intro'], 'an empty setting is filled' );
	}

	public function test_partial_import_recovers_without_duplicates_or_lost_edits(): void {
		$this->prepare();
		$contact = $this->page_id( 'contact' );
		$about   = $this->page_id( 'about' );
		wp_update_post( array( 'ID' => $about, 'post_content' => '<p>Edited by the owner.</p>' ) );
		// Simulate an interrupted run: one page and one menu are missing.
		wp_delete_post( $contact, true );
		wp_delete_nav_menu( $this->menu_term( 'footer' ) );
		$res = $this->prepare();
		$o   = $this->outcomes( $res );
		$this->assertSame( 'created', $o['page:contact'] );
		$this->assertSame( 'created', $o['menu:footer'] );
		$this->assertSame( 'existing', $o['page:about'] );
		$this->assertSame( '<p>Edited by the owner.</p>', get_post( $about )->post_content, 'an existing page is never overwritten on a rerun' );
		$this->assertCount( 12, $this->owned_ids( 'page' ) );
	}

	public function test_a_failure_part_way_is_reported_and_a_rerun_completes_it(): void {
		$block = static function ( $maybe, $postarr ) {
			return ( 'Contact' === ( $postarr['post_title'] ?? '' ) ) ? true : $maybe;
		};
		add_filter( 'wp_insert_post_empty_content', $block, 10, 2 );
		$this->admin();
		$first = Setup::prepare();
		remove_filter( 'wp_insert_post_empty_content', $block, 10 );
		$this->assertIsArray( $first );
		$this->assertSame( 'error', $this->outcomes( $first )['page:contact'] );
		$this->assertSame( 0, $this->page_id( 'contact' ) );
		$this->assertGreaterThan( 0, $this->page_id( 'about' ), 'the other pages were still created' );
		$second = $this->prepare();
		$this->assertSame( 'created', $this->outcomes( $second )['page:contact'] );
		$this->assertCount( 12, $this->owned_ids( 'page' ), 'no duplicates after recovery' );
	}

	public function test_records_are_name_only_drafts_with_no_clearance_or_approval(): void {
		$this->prepare();
		$total = 0;
		foreach ( Blueprint::records() as $type => $titles ) {
			foreach ( $titles as $title ) {
				$found = get_posts( array( 'post_type' => $type, 'post_status' => 'any', 'title' => $title, 'posts_per_page' => 1 ) );
				$this->assertNotEmpty( $found, "{$type}: {$title}" );
				$p = $found[0];
				$this->assertSame( 'draft', $p->post_status );
				$this->assertSame( '', trim( $p->post_content ) );
				$this->assertNull( Approvals::latest( $p->ID ), 'no approval exists' );
				++$total;
			}
		}
		$this->assertSame( 3 + 5 + 8 + 4 + 6, $total );
		$this->assertContains( 'Noblen AI Solutions', Blueprint::records()[ Types::COMPANY ] );
		$this->assertContains( 'OnlineMart', Blueprint::records()[ Types::PRODUCT ] );
	}

	public function test_nothing_the_setup_does_can_publish_a_record_or_bypass_clearance(): void {
		$this->prepare();
		$this->activate(
			array(
				'publish'          => array( 'home' => true, 'about' => true, 'insights' => true ),
				'assign'           => array( 'primary' => true, 'footer' => true, 'legal' => true ),
				'front_page'       => true,
				'posts_page'       => true,
				'hero_defaults'    => true,
			)
		);
		foreach ( Types::all() as $type ) {
			foreach ( $this->owned_ids( $type ) as $id ) {
				$this->assertSame( 'draft', get_post_status( $id ), 'setup records stay drafts after activation' );
				$this->assertNull( Approvals::latest( $id ) );
			}
		}
		// A record key is not a publishable page key: it is ignored.
		$res = $this->activate( array( 'publish' => array( 'record:' . Types::COMPANY . ':noblen-ai-solutions' => true ) ) );
		foreach ( $this->owned_ids( Types::COMPANY ) as $id ) {
			$this->assertSame( 'draft', get_post_status( $id ) );
		}
		$this->assertIsArray( $res );
	}

	public function test_capability_is_required_for_every_action(): void {
		$this->as_user( $this->editor() );
		$this->assertWPError( Setup::prepare() );
		$this->assertWPError( Setup::activate( array() ) );
		$this->as_user( $this->approver() );
		$this->assertWPError( Setup::prepare(), 'the approver role cannot run the setup' );
		wp_set_current_user( 0 );
		$this->assertWPError( Setup::prepare() );
		$this->assertCount( 0, $this->owned_ids( 'page' ), 'nothing was created without the capability' );
		$this->admin();
		$this->assertIsArray( Setup::prepare() );
	}

	public function test_a_valid_nonce_is_required_and_is_not_enough_without_the_capability(): void {
		$this->admin();
		$good = wp_create_nonce( 'noblen_setup_prepare' );
		$this->assertTrue( SetupPage::authorize( 'noblen_setup_prepare', $good ) );
		$this->assertWPError( SetupPage::authorize( 'noblen_setup_prepare', 'not-a-nonce' ) );
		$this->assertWPError( SetupPage::authorize( 'noblen_setup_prepare', '' ) );
		$this->assertWPError( SetupPage::authorize( 'noblen_setup_activate', $good ), 'a nonce for another action is refused' );
		$this->as_user( $this->editor() );
		$editor_nonce = wp_create_nonce( 'noblen_setup_prepare' );
		$this->assertWPError( SetupPage::authorize( 'noblen_setup_prepare', $editor_nonce ), 'a valid nonce does not grant the capability' );
	}

	public function test_submitted_options_are_allow_listed(): void {
		$o = SetupPage::parse_options(
			array(
				'publish'  => array( 'about' => '1', 'evil' => '1', '../x' => '1' ),
				'assign'   => array( 'primary' => '1', 'nope' => '1' ),
				'adopt'    => array( 'contact' => '1', '<script>' => '1' ),
				'front_page' => '1',
				'unknown'  => 'x',
			)
		);
		$this->assertSame( array( 'about' => true ), $o['publish'] );
		$this->assertSame( array( 'primary' => true ), $o['assign'] );
		$this->assertSame( array( 'contact' => true ), $o['adopt'] );
		$this->assertTrue( $o['front_page'] );
		$this->assertArrayNotHasKey( 'unknown', $o );
	}

	public function test_invalid_configuration_stops_preparation_and_activation_needs_preparation(): void {
		$this->admin();
		$this->assertWPError( Setup::activate( array() ), 'activate before prepare' );
		unregister_post_type( Types::COMPANY );
		$res = Setup::prepare();
		Types::register();
		$this->assertWPError( $res );
		$this->assertSame( 'noblen_setup_config', $res->get_error_code() );
		$this->assertCount( 0, $this->owned_ids( 'page' ), 'nothing is created when the configuration is invalid' );
	}

	public function test_a_page_with_placeholders_is_never_published(): void {
		$this->prepare();
		$res = $this->activate( array( 'publish' => array( 'privacy' => true, 'terms' => true, 'about' => true, 'contact' => true, 'home' => true, 'insights' => true ) ) );
		$o   = array();
		foreach ( $res['items'] as $i ) {
			$o[ $i['label'] ] = $i['outcome'];
		}
		$this->assertSame( 'blocked', $o['Privacy Policy'] );
		$this->assertSame( 'blocked', $o['Terms and Conditions'] );
		$this->assertSame( 'draft', get_post_status( $this->page_id( 'privacy' ) ) );
		$this->assertSame( 'draft', get_post_status( $this->page_id( 'terms' ) ) );
		foreach ( array( 'About Noblen', 'Contact', 'Home', 'Insights' ) as $label ) {
			$this->assertSame( 'published', $o[ $label ], "{$label} is complete and can be published" );
		}
		// Once the owner replaces the placeholder, the legal page can be published.
		$this->complete_page( 'privacy' );
		$this->activate( array( 'publish' => array( 'privacy' => true ) ) );
		$this->assertSame( 'publish', get_post_status( $this->page_id( 'privacy' ) ) );
	}

	public function test_only_pages_the_setup_created_can_be_published_by_it(): void {
		$other = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'About', 'post_name' => 'about', 'post_status' => 'draft', 'post_content' => 'mine, a draft' ) );
		$this->prepare();
		$this->activate( array( 'publish' => array( 'about' => true ) ) );
		$this->assertSame( 'draft', get_post_status( $other ), "a conflicting page of the administrator's is never published by the setup" );
	}

	public function test_menus_never_link_to_unpublished_destinations(): void {
		$this->prepare();
		$this->activate( array( 'assign' => array( 'primary' => true, 'footer' => true, 'legal' => true ) ) );
		$this->assertSame( array(), array_filter( get_nav_menu_locations() ), 'no menu is assigned while nothing is public' );
		$this->complete_page( 'about' );
		$this->activate( array( 'publish' => array( 'about' => true ), 'assign' => array( 'primary' => true, 'footer' => true, 'legal' => true ) ) );
		$locs = array_filter( get_nav_menu_locations() );
		$this->assertArrayNotHasKey( 'legal', $locs, 'the legal menu has no public destination, so it is not assigned' );
		$primary = (int) ( $locs['primary'] ?? 0 );
		$this->assertSame( $this->menu_term( 'primary' ), $primary );
		foreach ( wp_get_nav_menu_items( $primary ) as $item ) {
			$this->assertSame( 'publish', get_post_status( (int) $item->object_id ), 'every entry is public' );
		}
		$this->assertSame( array( 'About' ), array_values( wp_list_pluck( wp_get_nav_menu_items( $primary ), 'title' ) ), 'only the destinations that are public appear' );
	}

	public function test_entries_appear_as_destinations_become_public_and_never_duplicate(): void {
		$this->prepare();
		$this->complete_page( 'about' );
		$this->complete_page( 'contact' );
		$this->activate( array( 'publish' => array( 'about' => true, 'contact' => true ), 'assign' => array( 'primary' => true, 'footer' => true ) ) );
		$primary = $this->menu_term( 'primary' );
		$this->assertEqualsCanonicalizing( array( 'About', 'Contact' ), wp_list_pluck( wp_get_nav_menu_items( $primary ), 'title' ) );
		$this->activate( array( 'assign' => array( 'primary' => true, 'footer' => true ) ) );
		$this->activate( array( 'assign' => array( 'primary' => true, 'footer' => true ) ) );
		$this->assertCount( 2, wp_get_nav_menu_items( $primary ), 'repeated activation adds no duplicate entries' );
		$this->assertCount( 2, wp_get_nav_menu_items( $this->menu_term( 'footer' ) ) );
		$this->complete_page( 'portfolio' );
		$this->activate( array( 'publish' => array( 'portfolio' => true ), 'assign' => array( 'primary' => true ) ) );
		$this->assertContains( 'Portfolio', wp_list_pluck( wp_get_nav_menu_items( $primary ), 'title' ) );
		$this->assertCount( 3, wp_get_nav_menu_items( $primary ) );
	}

	public function test_a_location_that_already_has_a_menu_is_kept_unless_replacing(): void {
		$mine = wp_create_nav_menu( 'My existing header' );
		set_theme_mod( 'nav_menu_locations', array( 'primary' => $mine ) );
		$this->prepare();
		$this->complete_page( 'about' );
		$res = $this->activate( array( 'publish' => array( 'about' => true ), 'assign' => array( 'primary' => true ) ) );
		$this->assertSame( $mine, (int) get_nav_menu_locations()['primary'] );
		$kinds = array();
		foreach ( $res['items'] as $i ) {
			$kinds[ $i['label'] ] = $i['outcome'];
		}
		$this->assertSame( 'conflict', $kinds['Primary Navigation'] );
		$this->activate( array( 'assign' => array( 'primary' => true ), 'replace_location' => array( 'primary' => true ) ) );
		$this->assertSame( $this->menu_term( 'primary' ), (int) get_nav_menu_locations()['primary'] );
		$this->assertNotFalse( wp_get_nav_menu_object( $mine ), 'the replaced menu itself still exists' );
	}

	public function test_an_existing_published_page_can_be_adopted_without_being_changed(): void {
		$mine = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'Contact us', 'post_name' => 'contact', 'post_status' => 'publish', 'post_content' => 'ORIGINAL' ) );
		$this->prepare();
		$this->activate( array( 'adopt' => array( 'contact' => true ), 'assign' => array( 'primary' => true ) ) );
		$this->assertSame( 'ORIGINAL', get_post( $mine )->post_content );
		$this->assertSame( 'Contact us', get_post( $mine )->post_title );
		$ids = wp_list_pluck( wp_get_nav_menu_items( $this->menu_term( 'primary' ) ), 'object_id' );
		$this->assertContains( (string) $mine, array_map( 'strval', $ids ) );
		$this->assertSame( 0, $this->page_id( 'contact' ), 'no second Contact page was created' );
	}

	public function test_a_draft_existing_page_cannot_be_adopted(): void {
		wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'Privacy Policy', 'post_name' => 'privacy', 'post_status' => 'draft' ) );
		$this->prepare();
		$res = $this->activate( array( 'adopt' => array( 'privacy' => true ) ) );
		$this->assertSame( 'blocked', $res['items'][0]['outcome'] );
	}

	public function test_the_setup_deletes_nothing_that_existed(): void {
		global $wpdb;
		$pages = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'Existing page', 'post_status' => 'publish' ) );
		$post  = wp_insert_post( array( 'post_title' => 'Existing post', 'post_status' => 'publish' ) );
		$menu  = wp_create_nav_menu( 'Existing menu' );
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status <> 'auto-draft'" ); // phpcs:ignore WordPress.DB
		$this->prepare();
		$this->activate( array( 'assign' => array( 'primary' => true ), 'publish' => array( 'home' => true ) ) );
		$after = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status <> 'auto-draft'" ); // phpcs:ignore WordPress.DB
		$this->assertGreaterThanOrEqual( $count, $after, 'no post was removed' );
		$this->assertNotNull( get_post( $pages ) );
		$this->assertNotNull( get_post( $post ) );
		$this->assertNotFalse( wp_get_nav_menu_object( $menu ) );
	}

	public function test_status_classes_distinguish_existing_draft_ready_and_public(): void {
		$this->prepare();
		$s = Setup::status();
		$this->assertSame( 'generated_draft', $s['pages']['privacy']['class'], 'placeholders remain' );
		$this->assertSame( 'ready_for_review', $s['pages']['home']['class'], 'a complete page is ready for review' );
		$this->assertSame( 'ready_for_review', $s['pages']['about']['class'] );
		$this->activate( array( 'publish' => array( 'about' => true ) ) );
		$this->assertSame( 'public', Setup::status()['pages']['about']['class'] );
		$this->assertSame( 'generated_draft', Setup::status()['records'][ 'record:' . Types::COMPANY . ':noblen-ai-solutions' ]['class'] );
		$existing = wp_insert_post( array( 'post_type' => Types::INDUSTRY, 'post_title' => 'Aviation', 'post_status' => 'draft' ) );
		$this->assertGreaterThan( 0, $existing );
	}

	public function test_an_existing_record_with_the_same_title_is_kept_and_not_claimed(): void {
		$mine = wp_insert_post( array( 'post_type' => Types::COMPANY, 'post_title' => 'Noblen AI Solutions', 'post_status' => 'draft', 'post_content' => 'my notes' ) );
		$res  = $this->prepare();
		$this->assertSame( 'existing', $this->outcomes( $res )[ 'record:' . Types::COMPANY . ':noblen-ai-solutions' ] );
		$this->assertSame( 'my notes', get_post( $mine )->post_content );
		$this->assertSame( '', (string) get_post_meta( $mine, Setup::META, true ), 'the setup does not claim a record it did not create' );
		$this->assertCount( 1, get_posts( array( 'post_type' => Types::COMPANY, 'post_status' => 'any', 'title' => 'Noblen AI Solutions', 'numberposts' => -1 ) ) );
	}

	public function test_records_are_requested_as_drafts_before_the_clearance_gate_even_runs(): void {
		// The clearance gate would also refuse a publish, but the setup must not depend on that: it must ask for drafts itself.
		$seen = array();
		$spy  = static function ( $data ) use ( &$seen ) {
			if ( Types::is_ours( (string) ( $data['post_type'] ?? '' ) ) ) {
				$seen[] = (string) $data['post_status'];
			}
			return $data;
		};
		add_filter( 'wp_insert_post_data', $spy, 1 );
		$this->prepare();
		remove_filter( 'wp_insert_post_data', $spy, 1 );
		$this->assertCount( 26, $seen );
		$this->assertSame( array( 'draft' ), array_values( array_unique( $seen ) ) );
	}

	public function test_the_screen_renders_for_administrators_and_escapes_output(): void {
		$this->prepare();
		ob_start();
		SetupPage::render();
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'Website Setup', $html );
		$this->assertStringContainsString( 'does not create a finished website', $html );
		$this->assertStringContainsString( 'Step 1. Prepare', $html );
		$this->assertStringContainsString( 'Step 2. Activate', $html );
		$this->assertStringContainsString( 'noblen_setup_activate', $html );
		$this->assertStringContainsString( '_wpnonce', $html );
		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( '#ffd700', strtolower( $html ) );
	}

	public function test_the_blueprint_invents_no_facts(): void {
		$all = '';
		foreach ( Blueprint::pages() as $p ) {
			$all .= ' ' . $p['content'] . ' ' . $p['purpose'];
		}
		foreach ( Blueprint::records() as $titles ) {
			$all .= ' ' . implode( ' ', $titles );
		}
		foreach ( array( 'testimonial', 'award', 'clients include', 'trusted by', 'years of experience', 'guarantee', 'certified' ) as $bad ) {
			$this->assertStringNotContainsStringIgnoringCase( $bad, $all, "no invented claim: {$bad}" );
		}
		$this->assertSame( 0, preg_match( '/\d+\s?%/', str_replace( 'Matrix 360%', '', $all ) ), 'no statistics' );
		$this->assertStringContainsString( 'Autonomous Operating Systems Provider', Blueprint::pages()['about']['content'] );
		$this->assertStringContainsString( 'AI That Gets Things Done', Blueprint::pages()['about']['content'] );
		// Every page that needs facts the repository does not hold carries an editable placeholder.
		foreach ( Blueprint::pages() as $k => $def ) {
			$has = 1 === preg_match( Blueprint::PLACEHOLDER, $def['content'] );
			$this->assertSame( empty( $def['auto'] ), $has, "{$k}: only pages that are not published automatically carry a placeholder" );
		}
		foreach ( array( 'privacy', 'terms' ) as $k ) {
			$this->assertFalse( Blueprint::pages()[ $k ]['auto'], "{$k} is never published automatically" );
		}
	}

	public function test_quickstart_publishes_the_complete_site_and_keeps_legal_pages_as_drafts(): void {
		$this->admin();
		$res = Setup::quickstart();
		$this->assertIsArray( $res, is_wp_error( $res ) ? $res->get_error_message() : '' );
		foreach ( Blueprint::pages() as $key => $def ) {
			$expected = empty( $def['auto'] ) ? 'draft' : 'publish';
			$this->assertSame( $expected, get_post_status( $this->page_id( $key ) ), "{$key} is {$expected}" );
		}
		$this->assertSame( 'page', get_option( 'show_on_front' ) );
		$this->assertSame( $this->page_id( 'home' ), (int) get_option( 'page_on_front' ) );
		$this->assertSame( $this->page_id( 'insights' ), (int) get_option( 'page_for_posts' ) );
		$locs = array_filter( get_nav_menu_locations() );
		$this->assertSame( $this->menu_term( 'primary' ), (int) $locs['primary'] );
		$this->assertSame( $this->menu_term( 'footer' ), (int) $locs['footer'] );
		$this->assertArrayNotHasKey( 'legal', $locs, 'the legal menu waits for reviewed legal pages' );
		$primary = wp_list_pluck( wp_get_nav_menu_items( $this->menu_term( 'primary' ) ), 'title' );
		$this->assertSame( array( 'About', 'Our Businesses', 'Industries', 'Solutions', 'Portfolio', 'Products', 'Insights', 'Contact' ), array_values( $primary ) );
		$s = Settings::all();
		$this->assertNotSame( '', $s['hero_headline'] );
		$this->assertSame( 'Noblen Technologies Limited', $s['company_name'] );
		foreach ( Types::all() as $type ) {
			foreach ( $this->owned_ids( $type ) as $id ) {
				$this->assertSame( 'draft', get_post_status( $id ), 'records are never published by the setup' );
			}
		}
	}

	public function test_quickstart_is_repeatable_and_never_overwrites_owner_edits(): void {
		$this->admin();
		Setup::quickstart();
		$about = $this->page_id( 'about' );
		wp_update_post( array( 'ID' => $about, 'post_content' => '<p>Rewritten by the owner.</p>' ) );
		$pages = count( $this->owned_ids( 'page' ) );
		$items = count( wp_get_nav_menu_items( $this->menu_term( 'primary' ) ) );
		$again = Setup::quickstart();
		$this->assertIsArray( $again );
		$this->assertSame( 0, $again['counts']['created'] );
		$this->assertSame( $pages, count( $this->owned_ids( 'page' ) ) );
		$this->assertSame( $items, count( wp_get_nav_menu_items( $this->menu_term( 'primary' ) ) ) );
		$this->assertSame( '<p>Rewritten by the owner.</p>', get_post( $about )->post_content );
	}

	public function test_quickstart_keeps_existing_homepage_settings_and_business_details(): void {
		$front = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'My front', 'post_status' => 'publish', 'post_content' => 'front' ) );
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $front );
		Settings::save( array_merge( Settings::all(), array( 'contact_email' => 'owner@example.test', 'hero_headline' => 'Owner headline' ) ) );
		$this->admin();
		Setup::quickstart();
		$this->assertSame( $front, (int) get_option( 'page_on_front' ), 'a chosen front page is kept' );
		$s = Settings::all();
		$this->assertSame( 'owner@example.test', $s['contact_email'], 'existing contact details are kept' );
		$this->assertSame( 'Owner headline', $s['hero_headline'] );
	}

	public function test_quickstart_requires_the_capability(): void {
		$this->as_user( $this->editor() );
		$this->assertWPError( Setup::quickstart() );
		$this->assertCount( 0, $this->owned_ids( 'page' ) );
	}

	public function test_the_content_review_list_names_what_the_owner_must_replace(): void {
		$this->admin();
		Setup::quickstart();
		$rows  = Setup::review_list();
		$items = wp_list_pluck( $rows, 'item' );
		foreach ( array( 'Portfolio', 'Privacy Policy', 'Terms and Conditions', 'Contact' ) as $t ) {
			$this->assertContains( $t, $items, "{$t} is on the review list" );
		}
		$this->assertContains( 'Business details (sample)', $items );
	}

	public function test_every_internal_link_in_the_blueprint_points_to_a_blueprint_page(): void {
		$slugs = array( 'contact' => true );
		foreach ( Blueprint::pages() as $def ) {
			$slugs[ $def['slug'] ] = true;
		}
		$all = '';
		foreach ( Blueprint::pages() as $def ) {
			$all .= $def['content'];
		}
		$all .= $this->home_markup();
		preg_match_all( '#href="/([a-z0-9-]*)/?(?:\#[a-z-]+)?"#', $all, $m );
		$this->assertNotEmpty( $m[1] );
		foreach ( array_unique( $m[1] ) as $slug ) {
			$this->assertArrayHasKey( $slug, $slugs, "/{$slug}/ is a page the setup creates" );
		}
		$this->assertDoesNotMatchRegularExpression( '#href=""|href="\#"#', $all, 'no empty links' );
	}

	/** The home page markup, via the page definition of the front page. */
	private function home_markup(): string {
		return (string) Blueprint::pages()['home']['content'];
	}
}

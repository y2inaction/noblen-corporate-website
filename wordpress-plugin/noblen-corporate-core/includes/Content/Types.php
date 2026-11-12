<?php
namespace Noblen\Core\Content;

use Noblen\Core\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the five content types and the two classification taxonomies.
 *
 * All are registered with REST disabled, which also keeps the block editor from
 * loading for them: they use the Classic Editor with structured meta boxes.
 */
final class Types {

	public const PROJECT  = 'noblen_project';
	public const PRODUCT  = 'noblen_product';
	public const COMPANY  = 'noblen_company';
	public const INDUSTRY = 'noblen_industry';
	public const SERVICE  = 'noblen_service';

	public const TAX_AREA         = 'noblen_portfolio_area';
	public const TAX_PRODUCT_TYPE = 'noblen_product_type';

	/**
	 * All content type slugs.
	 *
	 * @return string[]
	 */
	public static function all(): array {
		return array( self::PROJECT, self::PRODUCT, self::COMPANY, self::INDUSTRY, self::SERVICE );
	}

	/**
	 * Whether a post type is one of ours.
	 *
	 * @param string $type Post type.
	 */
	public static function is_ours( string $type ): bool {
		return in_array( $type, self::all(), true );
	}

	/**
	 * Register types and taxonomies.
	 */
	public static function register(): void {
		$defs = array(
			self::PROJECT  => array( 'Projects', 'Project', 'projects', 'projects', array( 'title', 'thumbnail' ), 'dashicons-portfolio' ),
			self::PRODUCT  => array( 'Products', 'Product', 'products', 'products', array( 'title', 'thumbnail' ), 'dashicons-products' ),
			self::COMPANY  => array( 'Business Units', 'Business Unit', 'group', 'group', array( 'title', 'editor', 'thumbnail', 'excerpt' ), 'dashicons-building' ),
			self::INDUSTRY => array( 'Industries', 'Industry', 'industries', 'industries', array( 'title', 'editor', 'thumbnail', 'excerpt' ), 'dashicons-chart-area' ),
			self::SERVICE  => array( 'Services', 'Service', 'solutions', 'solutions', array( 'title', 'editor', 'thumbnail', 'excerpt' ), 'dashicons-admin-tools' ),
		);

		foreach ( $defs as $type => $d ) {
			$caps = Capabilities::TYPES[ $type ];
			register_post_type(
				$type,
				array(
					'labels'              => array(
						'name'          => $d[0],
						'singular_name' => $d[1],
						'add_new_item'  => 'Add ' . $d[1],
						'edit_item'     => 'Edit ' . $d[1],
					),
					'public'              => true,
					'publicly_queryable'  => true,
					'show_ui'             => true,
					'show_in_menu'        => true,
					'show_in_nav_menus'   => true,
					'show_in_rest'        => false,
					'exclude_from_search' => false,
					'has_archive'         => $d[3],
					'hierarchical'        => false,
					'supports'            => $d[4],
					'menu_icon'           => $d[5],
					'capability_type'     => $caps,
					'capabilities'        => Capabilities::map( $caps[0], $caps[1] ),
					'map_meta_cap'        => true,
					'rewrite'             => array( 'slug' => $d[2], 'with_front' => false, 'feeds' => false ),
					'delete_with_user'    => false,
					'can_export'          => true,
				)
			);
		}

		register_taxonomy(
			self::TAX_AREA,
			array( self::PROJECT ),
			array(
				'labels'            => array( 'name' => 'Portfolio Areas', 'singular_name' => 'Portfolio Area' ),
				'public'            => true,
				'hierarchical'      => false,
				'show_in_rest'      => false,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'portfolio-area', 'with_front' => false ),
				'capabilities'      => array(
					'manage_terms' => 'manage_noblen_taxonomies',
					'edit_terms'   => 'manage_noblen_taxonomies',
					'delete_terms' => 'manage_noblen_taxonomies',
					'assign_terms' => 'edit_noblen_projects',
				),
			)
		);
		register_taxonomy(
			self::TAX_PRODUCT_TYPE,
			array( self::PRODUCT ),
			array(
				'labels'            => array( 'name' => 'Product Types', 'singular_name' => 'Product Type' ),
				'public'            => true,
				'hierarchical'      => false,
				'show_in_rest'      => false,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'product-type', 'with_front' => false ),
				'capabilities'      => array(
					'manage_terms' => 'manage_noblen_taxonomies',
					'edit_terms'   => 'manage_noblen_taxonomies',
					'delete_terms' => 'manage_noblen_taxonomies',
					'assign_terms' => 'edit_noblen_products',
				),
			)
		);
	}

	/**
	 * Create the starter classification terms once. Terms are editable afterwards.
	 */
	public static function seed_terms(): void {
		if ( get_option( 'noblen_core_terms_seeded' ) ) {
			return;
		}
		foreach ( array(
			'Media, Broadcasting & Communications',
			'ICT, Software & Digital Transformation',
			'Climate, Agriculture & Development Technology',
			'Real Estate & Property Development',
			'Corporate & Strategic Projects',
		) as $name ) {
			if ( ! term_exists( $name, self::TAX_AREA ) ) {
				wp_insert_term( $name, self::TAX_AREA );
			}
		}
		foreach ( array( 'Platform', 'Operating System' ) as $name ) {
			if ( ! term_exists( $name, self::TAX_PRODUCT_TYPE ) ) {
				wp_insert_term( $name, self::TAX_PRODUCT_TYPE );
			}
		}
		update_option( 'noblen_core_terms_seeded', 1, false );
	}

	/**
	 * Keep the block editor off for our types, whatever the site-wide setting.
	 *
	 * @param bool   $use       Current value.
	 * @param string $post_type Post type.
	 */
	public static function classic_editor( $use, $post_type ): bool {
		return self::is_ours( (string) $post_type ) ? false : (bool) $use;
	}
}

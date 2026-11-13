<?php
/**
 * Noblen Corporate theme setup.
 *
 * @package noblen-corporate
 */

defined( 'ABSPATH' ) || exit;

define( 'NOBLEN_THEME_VERSION', '0.1.0' );

require_once get_template_directory() . '/inc/compat.php';
require_once get_template_directory() . '/inc/core-bridge.php';
require_once get_template_directory() . '/inc/home-sections.php';

add_action(
	'after_setup_theme',
	static function () {
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
		add_theme_support(
			'custom-logo',
			array(
				'height'      => 80,
				'width'       => 280,
				'flex-height' => true,
				'flex-width'  => true,
			)
		);
		register_nav_menus(
			array(
				'primary' => 'Primary navigation',
				'footer'  => 'Footer navigation',
				'legal'   => 'Legal links',
			)
		);
	}
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		$dir = get_template_directory_uri() . '/assets';
		wp_enqueue_style( 'noblen-tokens', $dir . '/css/tokens.css', array(), NOBLEN_THEME_VERSION );
		wp_enqueue_style( 'noblen-main', $dir . '/css/main.css', array( 'noblen-tokens' ), NOBLEN_THEME_VERSION );
		wp_enqueue_script(
			'noblen-main',
			$dir . '/js/main.js',
			array(),
			NOBLEN_THEME_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}
);

/** Analytics only when an ID has been configured by the owner. Nothing loads otherwise. */
add_action(
	'wp_head',
	static function () {
		$id = noblen_setting( 'analytics_id' );
		if ( '' === $id ) {
			return;
		}
		// Intentionally no third-party script is bundled. Add the provider snippet here once the owner chooses one and consent handling is agreed.
		echo '<meta name="noblen-analytics-configured" content="1">' . "\n";
	},
	1
);

/** Plain-text excerpt length. */
add_filter( 'excerpt_length', static fn() => 28 );

/**
 * Render the card for a public record array.
 *
 * @param array<string,mixed> $item Record from the core accessor.
 */
function noblen_card( array $item ): void {
	get_template_part( 'template-parts/card', null, array( 'item' => $item ) );
}

/**
 * Render a titled section only when it has records.
 *
 * @param string                         $id    Section ID.
 * @param string                         $title Heading.
 * @param array<int,array<string,mixed>> $items Records.
 * @param string                         $more  Archive URL (optional).
 */
function noblen_section( string $id, string $title, array $items, string $more = '' ): void {
	if ( ! $items ) {
		return;
	}
	echo '<section class="section" id="' . esc_attr( $id ) . '" aria-labelledby="' . esc_attr( $id ) . '-h"><div class="wrap"><div class="section__head"><h2 id="' . esc_attr( $id ) . '-h">' . esc_html( $title ) . '</h2>';
	if ( '' !== $more ) {
		echo '<a class="link-more" href="' . esc_url( $more ) . '">View all<span class="sr-only"> ' . esc_html( $title ) . '</span></a>';
	}
	echo '</div><div class="grid">';
	foreach ( $items as $item ) {
		noblen_card( $item );
	}
	echo '</div></div></section>';
}

/**
 * Facts for a record: only values the core plugin returned as public, each shown with its label.
 *
 * @param array<string,mixed> $item Record from the core accessor.
 * @return array<int,array{0:string,1:string}> Pairs of label and trusted HTML (already escaped).
 */
function noblen_record_fact_rows( array $item ): array {
	$rows = array();
	if ( ! empty( $item['business_unit']['title'] ) ) {
		$unit_url = esc_url( (string) ( $item['business_unit']['url'] ?? '' ) );
		$unit     = esc_html( (string) $item['business_unit']['title'] );
		$rows[]   = array( 'Business unit', '' !== $unit_url ? '<a href="' . $unit_url . '">' . $unit . '</a>' : $unit );
	}
	if ( ! empty( $item['client'] ) ) {
		$rows[] = array( 'Client', esc_html( (string) $item['client'] ) );
	}
	$by_tax = array();
	foreach ( (array) ( $item['terms'] ?? array() ) as $term ) {
		if ( ! empty( $term['taxonomy'] ) && ! empty( $term['name'] ) ) {
			$by_tax[ (string) $term['taxonomy'] ][] = $term;
		}
	}
	foreach ( $by_tax as $tax => $terms ) {
		$obj   = get_taxonomy( $tax );
		$label = $obj ? (string) $obj->labels->name : ucfirst( str_replace( '_', ' ', $tax ) );
		$links = array();
		foreach ( $terms as $term ) {
			$url     = is_string( $term['url'] ?? null ) ? (string) $term['url'] : '';
			$links[] = '' !== $url ? '<a href="' . esc_url( $url ) . '">' . esc_html( (string) $term['name'] ) . '</a>' : esc_html( (string) $term['name'] );
		}
		$rows[] = array( $label, implode( ', ', $links ) );
	}
	return $rows;
}

/**
 * Print the facts panel. Prints nothing when there are no facts.
 *
 * @param array<string,mixed> $item Record from the core accessor.
 */
function noblen_record_facts( array $item ): void {
	$rows = noblen_record_fact_rows( $item );
	if ( ! $rows ) {
		return;
	}
	echo '<dl class="facts">';
	foreach ( $rows as $row ) {
		// Values are built from esc_html() and esc_url() in noblen_record_fact_rows().
		echo '<div class="fact"><dt>' . esc_html( $row[0] ) . '</dt><dd>' . $row[1] . '</dd></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	echo '</dl>';
}

/**
 * Render a breadcrumb trail. The last crumb is the current page and is not a link.
 *
 * @param array<int,array{0:string,1:string}> $trail Pairs of label and URL ('' URL for the current page).
 */
function noblen_breadcrumbs( array $trail ): void {
	if ( ! $trail ) {
		return;
	}
	echo '<nav class="breadcrumbs" aria-label="Breadcrumb"><ol><li><a href="' . esc_url( home_url( '/' ) ) . '">Home</a></li>';
	foreach ( $trail as $crumb ) {
		if ( '' !== $crumb[1] ) {
			echo '<li><a href="' . esc_url( $crumb[1] ) . '">' . esc_html( $crumb[0] ) . '</a></li>';
		} else {
			echo '<li aria-current="page">' . esc_html( $crumb[0] ) . '</li>';
		}
	}
	echo '</ol></nav>';
}

/** Search should include the five custom types; only eligible records are ever returned by the plugin gate. */
add_action(
	'pre_get_posts',
	static function ( WP_Query $q ) {
		if ( ! is_admin() && $q->is_main_query() && $q->is_search() ) {
			$q->set( 'post_type', array( 'post', 'page', 'noblen_project', 'noblen_product', 'noblen_company', 'noblen_industry', 'noblen_service' ) );
		}
	}
);

/** No third-party hosts: remove core emoji assets and resource hints that point at wordpress.org hosts. */
add_action(
	'init',
	static function () {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_action( 'wp_head', 'rest_output_link_wp_head' );
		remove_action( 'template_redirect', 'rest_output_link_header', 11 );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	}
);
add_filter(
	'wp_resource_hints',
	static function ( array $urls, string $relation ): array {
		if ( 'dns-prefetch' === $relation ) {
			$urls = array_filter( $urls, static fn( $u ) => ! is_string( $u ) || false === strpos( $u, 's.w.org' ) );
		}
		return $urls;
	},
	10,
	2
);

/** Without a site icon, declare an empty one so browsers do not request a missing /favicon.ico. */
add_action(
	'wp_head',
	static function () {
		if ( ! has_site_icon() ) {
			echo '<link rel="icon" href="data:,">' . "\n";
		}
	},
	2
);

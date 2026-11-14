<?php
/**
 * Helpers for the populated corporate pages: page links, the navigation call to action and the default site icon.
 *
 * @package noblen-corporate
 */

defined( 'ABSPATH' ) || exit;

/**
 * Address of a published page by its slug, or a fallback. Never returns a link to a draft.
 *
 * @param string $slug     Page slug.
 * @param string $fallback Address to use when the page is not published.
 */
function noblen_page_url( string $slug, string $fallback = '' ): string {
	$page = get_page_by_path( $slug, OBJECT, 'page' );
	if ( $page && 'publish' === $page->post_status ) {
		return (string) get_permalink( $page );
	}
	return '' !== $fallback ? $fallback : home_url( '/' );
}

/**
 * The monogram used beside the site name when no logo has been uploaded.
 */
function noblen_brand_mark(): string {
	return '<svg class="brand__mark" viewBox="0 0 64 64" aria-hidden="true" focusable="false"><rect class="bm-bg" width="64" height="64" rx="14"/><path class="bm-n" d="M19 46V18l26 28V18" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/><circle class="bm-dot" cx="48" cy="16" r="5"/></svg>';
}

/**
 * Mark the Contact entry of the primary menu so it is styled as the header call to action.
 *
 * @param string[] $classes Classes.
 * @param WP_Post  $item    Menu item.
 * @param stdClass $args    Menu arguments.
 * @return string[]
 */
add_filter(
	'nav_menu_css_class',
	static function ( $classes, $item, $args ) {
		if ( isset( $args->theme_location ) && 'primary' === $args->theme_location && 'page' === ( $item->object ?? '' ) ) {
			$page = get_post( (int) $item->object_id );
			if ( $page && 'contact' === $page->post_name ) {
				$classes[] = 'menu-cta';
			}
		}
		return $classes;
	},
	10,
	3
);

/**
 * A default site icon, only when the administrator has not chosen one.
 */
add_action(
	'wp_head',
	static function () {
		if ( has_site_icon() ) {
			return;
		}
		echo '<link rel="icon" type="image/svg+xml" href="' . esc_url( get_template_directory_uri() . '/assets/img/favicon.svg' ) . '">' . "\n";
	},
	2
);

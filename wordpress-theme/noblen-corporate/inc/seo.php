<?php
/**
 * SEO fundamentals: meta description, Open Graph and an Organization snippet.
 *
 * Only values that exist and are public are printed. Nothing is invented. A record's text comes from the
 * core plugin's public accessor, so an unapproved record contributes nothing. When a dedicated SEO plugin is
 * active, this file prints nothing so the page never carries duplicate tags.
 *
 * @package noblen-corporate
 */

defined( 'ABSPATH' ) || exit;

/** Whether another plugin already manages SEO tags. */
function noblen_seo_plugin_active(): bool {
	return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' ) || (bool) apply_filters( 'noblen_seo_disabled', false );
}

/**
 * Trim text to a snippet length without cutting a word.
 *
 * @param string $text  Plain text.
 * @param int    $limit Maximum characters.
 */
function noblen_snippet( string $text, int $limit = 160 ): string {
	$text = trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $text ) ) );
	if ( mb_strlen( $text ) <= $limit ) {
		return $text;
	}
	$cut = mb_substr( $text, 0, $limit - 1 );
	$sp  = mb_strrpos( $cut, ' ' );
	return rtrim( false !== $sp && $sp > $limit * 0.6 ? mb_substr( $cut, 0, $sp ) : $cut, ' ,;:.-' ) . "\u{2026}";
}

/**
 * The description, image and type for the current request. Empty values are omitted.
 *
 * @return array{description:string,image:string,type:string}
 */
function noblen_seo_context(): array {
	$out = array(
		'description' => '',
		'image'       => '',
		'type'        => 'website',
	);
	if ( is_front_page() ) {
		$text = noblen_setting( 'hero_intro' );
		if ( '' === $text ) {
			$text = noblen_setting( 'tagline' );
		}
		$out['description'] = noblen_snippet( $text );
		return $out;
	}
	if ( is_post_type_archive() ) {
		$text = noblen_setting( 'tagline' );
		if ( '' === $text ) {
			/* translators: 1: section name such as Projects, 2: company name. */
			$text = sprintf( '%1$s from %2$s.', post_type_archive_title( '', false ), noblen_company_name() );
		}
		$out['description'] = noblen_snippet( $text );
		return $out;
	}
	if ( is_singular() ) {
		$id   = (int) get_queried_object_id();
		$item = noblen_is_record_type( (string) get_post_type( $id ) ) ? noblen_item( $id ) : null;
		if ( $item ) {
			$text = ! empty( $item['tagline'] ) ? (string) $item['tagline'] : (string) ( $item['excerpt'] ?? '' );
			if ( '' === trim( $text ) && ! empty( $item['content_html'] ) ) {
				$text = (string) $item['content_html'];
			}
			$out['description'] = noblen_snippet( $text );
			$out['image']       = ! empty( $item['featured_image']['url'] ) ? (string) $item['featured_image']['url'] : '';
			return $out;
		}
		if ( ! noblen_is_record_type( (string) get_post_type( $id ) ) && ! post_password_required( $id ) ) {
			$out['description'] = noblen_snippet( has_excerpt( $id ) ? (string) get_the_excerpt( $id ) : (string) get_post_field( 'post_content', $id ) );
			$out['image']       = (string) get_the_post_thumbnail_url( $id, 'large' );
			$out['type']        = is_single() ? 'article' : 'website';
		}
	}
	return $out;
}

/**
 * Whether a post type is one of the five record types.
 *
 * @param string $type Post type.
 */
function noblen_is_record_type( string $type ): bool {
	return in_array( $type, array( 'noblen_company', 'noblen_industry', 'noblen_product', 'noblen_project', 'noblen_service' ), true );
}

add_action(
	'wp_head',
	static function () {
		if ( noblen_seo_plugin_active() || is_404() || is_search() ) {
			return;
		}
		$ctx   = noblen_seo_context();
		$title = wp_get_document_title();
		$url   = is_singular() ? (string) get_permalink() : ( is_front_page() ? home_url( '/' ) : '' );
		if ( '' !== $ctx['description'] ) {
			echo '<meta name="description" content="' . esc_attr( $ctx['description'] ) . '">' . "\n";
		}
		echo '<meta property="og:site_name" content="' . esc_attr( noblen_company_name() ) . '">' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
		echo '<meta property="og:type" content="' . esc_attr( $ctx['type'] ) . '">' . "\n";
		if ( '' !== $url ) {
			echo '<meta property="og:url" content="' . esc_url( $url ) . '">' . "\n";
		}
		if ( '' !== $ctx['description'] ) {
			echo '<meta property="og:description" content="' . esc_attr( $ctx['description'] ) . '">' . "\n";
		}
		if ( '' !== $ctx['image'] ) {
			echo '<meta property="og:image" content="' . esc_url( $ctx['image'] ) . '">' . "\n";
		}
		echo '<meta name="twitter:card" content="' . esc_attr( '' !== $ctx['image'] ? 'summary_large_image' : 'summary' ) . '">' . "\n";
	},
	3
);

/**
 * Organization data for the home page, from owner-entered settings only.
 *
 * @return array<string,mixed> Empty when there is nothing verified to say.
 */
function noblen_organization_data(): array {
	$name = noblen_company_name();
	if ( '' === $name ) {
		return array();
	}
	$data = array(
		'@context' => 'https://schema.org',
		'@type'    => 'Organization',
		'name'     => $name,
		'url'      => home_url( '/' ),
	);
	$logo = has_custom_logo() ? wp_get_attachment_image_url( (int) get_theme_mod( 'custom_logo' ), 'full' ) : '';
	if ( $logo ) {
		$data['logo'] = $logo;
	}
	if ( '' !== noblen_setting( 'contact_email' ) ) {
		$data['email'] = noblen_setting( 'contact_email' );
	}
	if ( '' !== noblen_setting( 'contact_phone' ) ) {
		$data['telephone'] = noblen_setting( 'contact_phone' );
	}
	if ( '' !== noblen_setting( 'contact_address' ) ) {
		$data['address'] = preg_replace( '/\s*\R\s*/', ', ', noblen_setting( 'contact_address' ) );
	}
	$same = array_values( array_filter( array( noblen_setting( 'social_linkedin' ), noblen_setting( 'social_x' ), noblen_setting( 'social_facebook' ), noblen_setting( 'social_instagram' ), noblen_setting( 'social_youtube' ) ) ) );
	if ( $same ) {
		$data['sameAs'] = $same;
	}
	return $data;
}

add_action(
	'wp_head',
	static function () {
		if ( noblen_seo_plugin_active() || ! is_front_page() ) {
			return;
		}
		$data = noblen_organization_data();
		if ( $data ) {
			echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON-encoded with tag and ampersand escaping.
		}
	},
	4
);

<?php
/**
 * Logo management. The logo comes from Appearance, Customize, Site Identity (WordPress's own logo control and the Media
 * Library). Nothing is hard-coded: with no logo configured the header shows a text wordmark.
 *
 * @package noblen-corporate
 */

defined( 'ABSPATH' ) || exit;

require_once get_template_directory() . '/inc/svg.php';

/** Bounds for the logo height controls, in pixels. */
function noblen_logo_height_limits(): array {
	return array(
		'desktop' => array( 24, 120, 48 ),
		'mobile'  => array( 20, 80, 36 ),
	);
}

/**
 * A logo height setting, clamped to its limits.
 *
 * @param string $which desktop or mobile.
 */
function noblen_logo_height( string $which ): int {
	$lim = noblen_logo_height_limits()[ 'mobile' === $which ? 'mobile' : 'desktop' ];
	$key = 'mobile' === $which ? 'noblen_logo_height_mobile' : 'noblen_logo_height';
	return (int) max( $lim[0], min( $lim[1], (int) get_theme_mod( $key, $lim[2] ) ) );
}

/**
 * The logo as data: URL, intrinsic size and alternative text. Null when no usable logo is set.
 *
 * @param string $mod Theme modification holding the attachment ID.
 * @return array{url:string,width:int,height:int,alt:string,id:int}|null
 */
function noblen_logo_data( string $mod = 'custom_logo' ): ?array {
	$id = (int) get_theme_mod( $mod, 0 );
	if ( $id <= 0 || 'attachment' !== get_post_type( $id ) || 0 !== strpos( (string) get_post_mime_type( $id ), 'image/' ) ) {
		return null;
	}
	$url = wp_get_attachment_url( $id );
	if ( ! $url ) {
		return null;
	}
	$meta = wp_get_attachment_metadata( $id );
	$w    = (int) ( $meta['width'] ?? 0 );
	$h    = (int) ( $meta['height'] ?? 0 );
	if ( ( $w <= 0 || $h <= 0 ) && 'image/svg+xml' === get_post_mime_type( $id ) ) {
		$path          = get_attached_file( $id );
		$dims          = ( $path && is_readable( $path ) ) ? noblen_svg_dimensions( (string) file_get_contents( $path ) ) : array( 0, 0 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		list( $w, $h ) = $dims;
	}
	return array(
		'url'    => $url,
		'width'  => max( 0, $w ),
		'height' => max( 0, $h ),
		'alt'    => noblen_logo_alt( $id ),
		'id'     => $id,
	);
}

/**
 * Alternative text: the administrator's text, else the Media Library text, else the company name.
 *
 * @param int $id Attachment ID.
 */
function noblen_logo_alt( int $id ): string {
	$custom = trim( (string) get_theme_mod( 'noblen_logo_alt', '' ) );
	if ( '' !== $custom ) {
		return $custom;
	}
	$library = trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) );
	return '' !== $library ? $library : noblen_company_name();
}

/**
 * The brand link for the header: the logo when one is set, otherwise the text wordmark. Escaped.
 */
function noblen_logo_html(): string {
	$logo = noblen_logo_data();
	$link = '<a class="brand" href="' . esc_url( home_url( '/' ) ) . '" rel="home">';
	if ( ! $logo ) {
		// No logo uploaded: the original Noblen monogram beside the site name, until the administrator uploads one.
		return '<a class="brand brand--with-mark" href="' . esc_url( home_url( '/' ) ) . '" rel="home">' . noblen_brand_mark() . '<span class="brand__text">' . esc_html( get_bloginfo( 'name' ) ) . '</span></a>';
	}
	$dark = noblen_logo_data( 'noblen_logo_dark' );
	$img  = static function ( array $d, string $class, string $alt ): string {
		$size = ( $d['width'] > 0 && $d['height'] > 0 ) ? ' width="' . (int) $d['width'] . '" height="' . (int) $d['height'] . '"' : '';
		return '<img class="' . esc_attr( $class ) . '" src="' . esc_url( $d['url'] ) . '" alt="' . esc_attr( $alt ) . '"' . $size . ' decoding="async">';
	};
	$html = $link . $img( $logo, 'brand__logo brand__logo--light' . ( $dark ? ' has-dark-variant' : '' ), $logo['alt'] );
	if ( $dark ) {
		$html .= $img( $dark, 'brand__logo brand__logo--dark', '' );
	}
	return $html . '</a>';
}

// Logo sizes, as custom properties, printed with the brand tokens.
add_action(
	'wp_head',
	static function () {
		echo '<style id="noblen-logo-css">:root{--logo-h:' . (int) noblen_logo_height( 'desktop' ) . 'px;--logo-h-mobile:' . (int) noblen_logo_height( 'mobile' ) . 'px}</style>' . "\n";
	},
	21
);

<?php
/**
 * Brand identity: colour tokens, palettes, dark appearance, CSS output and contrast checks.
 *
 * Administrators change colours in Appearance, Customize, Brand Identity. Values are stored as theme modifications
 * (they survive theme updates), validated as hex colours, and printed as CSS custom properties. Nothing is hard-coded.
 *
 * @package noblen-corporate
 */

defined( 'ABSPATH' ) || exit;

require_once get_template_directory() . '/inc/brand-color.php';

/**
 * The shared definition (tokens, labels, defaults, presets).
 *
 * @return array<string,mixed>
 */
function noblen_brand_definition(): array {
	static $def = null;
	if ( null === $def ) {
		$raw = file_get_contents( get_template_directory() . '/assets/brand-defaults.json' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local theme file.
		$def = is_string( $raw ) ? json_decode( $raw, true ) : null;
		if ( ! is_array( $def ) || empty( $def['tokens'] ) ) {
			$def = array(
				'tokens'           => array(),
				'presets'          => array(),
				'appearance_modes' => array( 'light' => 'Light' ),
			);
		}
	}
	return $def;
}

/**
 * Default palette: key => hex.
 *
 * @return array<string,string>
 */
function noblen_brand_defaults(): array {
	$out = array();
	foreach ( noblen_brand_definition()['tokens'] as $t ) {
		$out[ $t['key'] ] = $t['default'];
	}
	return $out;
}

/**
 * Merge a possibly partial palette over the defaults, keeping only valid hex values.
 *
 * @param array<string,mixed> $colors Partial or full palette.
 * @return array<string,string>
 */
function noblen_brand_complete( array $colors ): array {
	$out = noblen_brand_defaults();
	foreach ( $out as $key => $default ) {
		$hex = noblen_hex( $colors[ $key ] ?? null );
		if ( null !== $hex ) {
			$out[ $key ] = $hex;
		}
	}
	return $out;
}

/**
 * The saved palette (theme modifications over the defaults).
 *
 * @return array<string,string>
 */
function noblen_brand_palette(): array {
	$saved = array();
	foreach ( array_keys( noblen_brand_defaults() ) as $key ) {
		$saved[ $key ] = get_theme_mod( 'noblen_brand_' . $key, null );
	}
	return noblen_brand_complete( $saved );
}

/** Appearance mode: light, dark or auto. */
function noblen_brand_mode(): string {
	$mode = (string) get_theme_mod( 'noblen_brand_appearance', 'light' );
	return in_array( $mode, array( 'light', 'dark', 'auto' ), true ) ? $mode : 'light';
}

/**
 * Lighten an accent colour in fixed steps until its badge pair (accent on a 14 percent tint) reaches 4.5. For dark surfaces.
 *
 * @param string $accent  Hex.
 * @param string $surface Hex.
 */
function noblen_ensure_badge_contrast( string $accent, string $surface ): string {
	for ( $i = 0; $i <= 40; $i++ ) {
		$a = noblen_mix( $accent, '#ffffff', $i * 0.025 );
		if ( noblen_contrast( $a, noblen_mix( $surface, $a, 0.14 ) ) >= 4.5 ) {
			return $a;
		}
	}
	return '#ffffff';
}

/**
 * The dark counterpart of a palette. Brand colours are kept; neutrals are derived and checked for contrast.
 *
 * @param array<string,string> $p Light palette (complete).
 * @return array<string,string> Only the keys that change in dark appearance.
 */
function noblen_brand_dark_palette( array $p ): array {
	$base    = noblen_luminance( $p['primary'] ) <= 0.12 ? $p['primary'] : noblen_mix( $p['primary'], '#000000', 0.75 );
	$bg      = noblen_mix( $base, '#000000', 0.6 );
	$surface = noblen_mix( $bg, '#ffffff', 0.05 );
	$text    = noblen_ensure_contrast( noblen_ensure_contrast( '#e6ecf8', $bg, 7 ), $surface, 7 );
	$footer  = noblen_mix( $bg, '#000000', 0.35 );
	$link    = noblen_ensure_contrast( noblen_ensure_contrast( $p['secondary'], $bg, 4.5 ), $surface, 4.5 );
	$hover   = noblen_ensure_contrast( noblen_mix( $link, '#ffffff', 0.35 ), $bg, 4.5 );
	return array(
		'secondary'   => noblen_ensure_contrast( $p['secondary'], $bg, 3 ),
		'accent'      => noblen_ensure_badge_contrast( $p['accent'], $surface ),
		'heading'     => '#ffffff',
		'text'        => $text,
		'bg'          => $bg,
		'surface'     => $surface,
		'header_bg'   => $surface,
		'header_text' => $text,
		'footer_bg'   => $footer,
		'footer_text' => noblen_ensure_contrast( '#b9c8ee', $footer, 4.5 ),
		'link'        => $link,
		'link_hover'  => $hover,
		'link_active' => $hover,
	);
}

/**
 * CSS custom property name for a token.
 *
 * @param string $key Token key.
 */
function noblen_brand_var( string $key ): string {
	$dash = str_replace( '_', '-', $key );
	return in_array( $key, array( 'primary', 'secondary', 'accent' ), true ) ? '--brand-' . $dash : '--color-' . $dash;
}

/**
 * Declarations for a palette.
 *
 * @param array<string,string> $p Palette (may be partial).
 */
function noblen_brand_declarations( array $p ): string {
	$out = '';
	foreach ( $p as $key => $hex ) {
		$out .= noblen_brand_var( $key ) . ':' . $hex . ';';
	}
	return $out;
}

/**
 * The stylesheet text for a complete light palette. Light tokens on :root; dark tokens for data-appearance="dark" and,
 * for "auto", inside a prefers-color-scheme media query. Identical to buildCss() in assets/js/brand-lib.js.
 *
 * @param array<string,string> $p Complete light palette.
 */
function noblen_brand_css( array $p ): string {
	$dark  = noblen_brand_declarations( noblen_brand_dark_palette( $p ) ) . 'color-scheme:dark;';
	$light = noblen_brand_declarations( $p ) . '--color-on-primary:' . noblen_on_color( $p['primary'] ) . ';color-scheme:light;';
	return ':root{' . $light . "}\n"
		. ':root[data-appearance="dark"]{' . $dark . "}\n"
		. '@media (prefers-color-scheme:dark){:root[data-appearance="auto"]{' . $dark . '}}';
}

/**
 * Contrast checks for a palette.
 *
 * @param array<string,string> $p Complete palette.
 * @return array<int,array{id:string,label:string,fg:string,bg:string,ratio:float,min:float,pass:bool}>
 */
function noblen_brand_contrast_report( array $p ): array {
	$text  = 4.5;
	$ui    = 3.0;
	$badge = noblen_mix( $p['surface'], $p['accent'], 0.14 );
	$pairs = array(
		array( 'text-bg', 'Body text on the page background', $p['text'], $p['bg'], $text ),
		array( 'text-surface', 'Body text on cards', $p['text'], $p['surface'], $text ),
		array( 'heading-bg', 'Headings on the page background', $p['heading'], $p['bg'], $text ),
		array( 'heading-surface', 'Card titles on cards', $p['heading'], $p['surface'], $text ),
		array( 'link-bg', 'Links on the page background', $p['link'], $p['bg'], $text ),
		array( 'link-surface', 'Links on cards', $p['link'], $p['surface'], $text ),
		array( 'hover-bg', 'Link hover on the page background', $p['link_hover'], $p['bg'], $text ),
		array( 'active-bg', 'Link active on the page background', $p['link_active'], $p['bg'], $text ),
		array( 'header', 'Header text on the header', $p['header_text'], $p['header_bg'], $text ),
		array( 'header-link', 'Links on the header', $p['link'], $p['header_bg'], $text ),
		array( 'footer', 'Footer text on the footer', $p['footer_text'], $p['footer_bg'], $text ),
		array( 'button', 'Button text on buttons', $p['button_text'], $p['button_bg'], $text ),
		array( 'button-hover', 'Button text on hovered buttons', $p['button_text'], $p['button_hover'], $text ),
		array( 'button-active', 'Button text on pressed buttons', $p['button_text'], $p['button_active'], $text ),
		array( 'badge', 'Badge text (accent) on its tinted background', $p['accent'], $badge, $text ),
		array( 'hero', 'Hero text on the primary colour', noblen_on_color( $p['primary'] ), $p['primary'], $text ),
		array( 'focus', 'Focus ring (secondary) on the page background', $p['secondary'], $p['bg'], $ui ),
	);
	$out   = array();
	foreach ( $pairs as $row ) {
		$ratio = round( noblen_contrast( $row[2], $row[3] ), 2 );
		$out[] = array(
			'id'    => $row[0],
			'label' => $row[1],
			'fg'    => $row[2],
			'bg'    => $row[3],
			'ratio' => $ratio,
			'min'   => $row[4],
			'pass'  => $ratio >= $row[4],
		);
	}
	return $out;
}

/** Sanitise a colour setting: a valid hex, otherwise the setting's default. */
function noblen_sanitize_brand_color( $value, $setting = null ): string {
	$hex = noblen_hex( $value );
	if ( null !== $hex ) {
		return $hex;
	}
	$default = ( is_object( $setting ) && isset( $setting->default ) ) ? noblen_hex( $setting->default ) : null;
	return $default ?? '#000000';
}

// Print the brand tokens after the theme stylesheets so they override the built-in defaults.
add_action(
	'wp_head',
	static function () {
		$palette = noblen_brand_palette();
		echo '<meta name="theme-color" content="' . esc_attr( $palette['primary'] ) . '">' . "\n";
		echo '<meta name="color-scheme" content="' . esc_attr( 'auto' === noblen_brand_mode() ? 'light dark' : noblen_brand_mode() ) . '">' . "\n";
		echo '<style id="noblen-brand-css">' . noblen_brand_css( $palette ) . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built only from validated hex colours.
	},
	20
);

// Mark the page with its appearance mode for the stylesheet.
add_filter(
	'language_attributes',
	static function ( $output ) {
		return $output . ' data-appearance="' . esc_attr( noblen_brand_mode() ) . '"';
	}
);

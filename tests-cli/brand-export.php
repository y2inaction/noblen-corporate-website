<?php
/**
 * Prints the brand system's PHP results for the palettes given as JSON on standard input, for the parity test.
 * Needs no WordPress: the few WordPress functions the theme file touches at load time are stubbed.
 * Usage: node tests-e2e/brand-parity.js (it runs this script).
 */
define( 'ABSPATH', '/' );
$theme = dirname( __DIR__ ) . '/wordpress-theme/noblen-corporate';
function get_template_directory(): string {
	global $theme;
	return $theme;
}
function add_action(): void {}
function add_filter(): void {}
require $theme . '/inc/brand.php';

$in  = json_decode( (string) stream_get_contents( STDIN ), true );
$out = array();
foreach ( (array) $in as $colors ) {
	$p      = noblen_brand_complete( (array) $colors );
	$dark   = noblen_brand_dark_palette( $p );
	$out[]  = array(
		'palette'  => $p,
		'dark'     => $dark,
		'css'      => noblen_brand_css( $p ),
		'report'   => noblen_brand_contrast_report( $p ),
		'reportD'  => noblen_brand_contrast_report( array_merge( $p, $dark ) ),
	);
}
echo wp_json_encode_compat( $out );

function wp_json_encode_compat( $v ): string {
	return (string) json_encode( $v, JSON_UNESCAPED_SLASHES );
}

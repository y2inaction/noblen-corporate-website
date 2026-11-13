<?php
/**
 * Colour mathematics for the brand system. Pure functions: no WordPress state.
 *
 * The same functions exist in assets/js/brand-lib.js (live preview and Customizer checks). tests-e2e/brand-parity.js
 * proves both produce identical results, so keep the two in step.
 *
 * @package noblen-corporate
 */

defined( 'ABSPATH' ) || exit;

/**
 * Normalise #rgb or #rrggbb to lower-case #rrggbb, or null when it is not a hex colour.
 *
 * @param mixed $hex Value.
 */
function noblen_hex( $hex ): ?string {
	if ( ! is_string( $hex ) ) {
		return null;
	}
	$hex = strtolower( trim( $hex ) );
	if ( preg_match( '/^#([0-9a-f]{3})$/', $hex, $m ) ) {
		return '#' . $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2];
	}
	return preg_match( '/^#[0-9a-f]{6}$/', $hex ) ? $hex : null;
}

/**
 * Red, green and blue as integers.
 *
 * @param string $hex Normalised hex.
 * @return int[]
 */
function noblen_rgb( string $hex ): array {
	return array( hexdec( substr( $hex, 1, 2 ) ), hexdec( substr( $hex, 3, 2 ) ), hexdec( substr( $hex, 5, 2 ) ) );
}

/**
 * Hex from channels, clamped and rounded.
 *
 * @param float[] $rgb Channels.
 */
function noblen_from_rgb( array $rgb ): string {
	$out = '#';
	foreach ( $rgb as $c ) {
		$out .= str_pad( dechex( (int) max( 0, min( 255, round( $c ) ) ) ), 2, '0', STR_PAD_LEFT );
	}
	return $out;
}

/**
 * Mix two colours. $t is the weight of $b (0 gives $a, 1 gives $b).
 *
 * @param string $a Hex.
 * @param string $b Hex.
 * @param float  $t Weight.
 */
function noblen_mix( string $a, string $b, float $t ): string {
	$x = noblen_rgb( $a );
	$y = noblen_rgb( $b );
	return noblen_from_rgb(
		array(
			$x[0] * ( 1 - $t ) + $y[0] * $t,
			$x[1] * ( 1 - $t ) + $y[1] * $t,
			$x[2] * ( 1 - $t ) + $y[2] * $t,
		)
	);
}

/**
 * WCAG relative luminance.
 *
 * @param string $hex Hex.
 */
function noblen_luminance( string $hex ): float {
	$lin = array();
	foreach ( noblen_rgb( $hex ) as $c ) {
		$c     = $c / 255;
		$lin[] = $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
	}
	return 0.2126 * $lin[0] + 0.7152 * $lin[1] + 0.0722 * $lin[2];
}

/**
 * WCAG contrast ratio.
 *
 * @param string $a Hex.
 * @param string $b Hex.
 */
function noblen_contrast( string $a, string $b ): float {
	$la = noblen_luminance( $a );
	$lb = noblen_luminance( $b );
	return ( max( $la, $lb ) + 0.05 ) / ( min( $la, $lb ) + 0.05 );
}

/**
 * Move $fg toward black or white (whichever suits $bg) until it reaches the contrast, in fixed steps.
 *
 * @param string $fg  Hex.
 * @param string $bg  Hex.
 * @param float  $min Required ratio.
 */
function noblen_ensure_contrast( string $fg, string $bg, float $min ): string {
	if ( noblen_contrast( $fg, $bg ) >= $min ) {
		return $fg;
	}
	$target = noblen_luminance( $bg ) > 0.5 ? '#000000' : '#ffffff';
	for ( $i = 1; $i <= 40; $i++ ) {
		$c = noblen_mix( $fg, $target, $i * 0.025 );
		if ( noblen_contrast( $c, $bg ) >= $min ) {
			return $c;
		}
	}
	return $target;
}

/**
 * White or near-black, whichever reads better on $bg.
 *
 * @param string $bg Hex.
 */
function noblen_on_color( string $bg ): string {
	return noblen_contrast( '#ffffff', $bg ) >= noblen_contrast( '#101828', $bg ) ? '#ffffff' : '#101828';
}

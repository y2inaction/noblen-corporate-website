<?php
/**
 * Logo palette analysis and suggestions. Needs PHP with GD, no WordPress (the hooks the theme files register are stubbed).
 * Usage: php tests-cli/palette-test.php
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
require $theme . '/inc/palette.php';
if ( ! function_exists( 'imagecreatetruecolor' ) ) {
	fwrite( STDERR, "GD is required.\n" );
	exit( 2 );
}
$pass = 0;
$fail = 0;
function t( bool $ok, string $name, string $extra = '' ): void {
	global $pass, $fail;
	$ok ? ++$pass : ++$fail;
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $name . ( $ok || '' === $extra ? '' : ' :: ' . $extra ) . "\n";
}
function logo( array $blocks, bool $transparent = false, int $w = 200, int $h = 80 ) {
	$im = imagecreatetruecolor( $w, $h );
	imagesavealpha( $im, true );
	imagealphablending( $im, false );
	imagefill( $im, 0, 0, $transparent ? imagecolorallocatealpha( $im, 255, 255, 255, 127 ) : imagecolorallocate( $im, 255, 255, 255 ) );
	imagealphablending( $im, true );
	foreach ( $blocks as $b ) {
		$c = noblen_rgb( $b[0] );
		imagefilledrectangle( $im, $b[1], $b[2], $b[3], $b[4], imagecolorallocate( $im, $c[0], $c[1], $c[2] ) );
	}
	return $im;
}
function near( string $a, string $b, int $tol = 40 ): bool {
	$x = noblen_rgb( $a );
	$y = noblen_rgb( $b );
	return abs( $x[0] - $y[0] ) <= $tol && abs( $x[1] - $y[1] ) <= $tol && abs( $x[2] - $y[2] ) <= $tol;
}
function all_pass( array $p ): bool {
	foreach ( noblen_brand_contrast_report( $p ) as $r ) {
		if ( ! $r['pass'] ) {
			return false;
		}
	}
	foreach ( noblen_brand_contrast_report( array_merge( $p, noblen_brand_dark_palette( $p ) ) ) as $r ) {
		if ( ! $r['pass'] ) {
			return false;
		}
	}
	return true;
}

// ---- Colour conversions.
$ok = true;
mt_srand( 7 );
for ( $i = 0; $i < 300; $i++ ) {
	$hex = noblen_from_rgb( array( mt_rand( 0, 255 ), mt_rand( 0, 255 ), mt_rand( 0, 255 ) ) );
	$h   = noblen_hsl( $hex );
	$ok  = $ok && near( $hex, noblen_from_hsl( $h[0], $h[1], $h[2] ), 1 );
}
t( $ok, 'HSL conversion round-trips within one channel step (300 colours)' );
t( noblen_hue_distance( 350, 10 ) === 20.0 && noblen_hue_distance( 0, 180 ) === 180.0, 'hue distance wraps around the circle' );
t( noblen_is_gold_tone( '#d4a017' ) && noblen_is_gold_tone( '#c9a227' ) && ! noblen_is_gold_tone( '#177443' ) && ! noblen_is_gold_tone( '#1f6bff' ) && ! noblen_is_gold_tone( '#0a1b3d' ), 'gold and yellow tones are recognised; navy, blue and green are not' );

// ---- A three-colour logo on white: navy wordmark, blue mark, green swoosh.
$im = logo( array( array( '#0a1b3d', 0, 0, 99, 79 ), array( '#1f6bff', 100, 0, 149, 79 ), array( '#2e8b3a', 150, 0, 169, 79 ) ) );
$d  = noblen_palette_from_gd( $im );
t( count( $d ) === 3, 'three colours are found in a three-colour logo', (string) count( $d ) );
t( isset( $d[0] ) && near( $d[0]['hex'], '#0a1b3d', 12 ) && $d[0]['share'] > 0.4, 'the most common colour is the navy, with its share' );
t( isset( $d[1] ) && near( $d[1]['hex'], '#1f6bff', 12 ), 'the second colour is the blue' );
$s = noblen_suggest_palette( $d );
t( null !== $s, 'a palette is suggested' );
$p = $s['palette'];
t( near( $p['primary'], '#0a1b3d', 12 ), 'primary is the navy', $p['primary'] );
t( noblen_hsl( $p['secondary'] )[0] > 200 && noblen_hsl( $p['secondary'] )[0] < 240, 'secondary is a blue', $p['secondary'] );
t( noblen_hsl( $p['accent'] )[0] > 100 && noblen_hsl( $p['accent'] )[0] < 160, 'accent is a green', $p['accent'] );
t( all_pass( $p ), 'the suggested palette passes every contrast check, light and dark' );
t( count( $p ) === count( noblen_brand_defaults() ), 'the suggestion covers every colour token' );

// ---- Transparent background, white-only, tiny noise.
$d2 = noblen_palette_from_gd( logo( array( array( '#0a1b3d', 0, 0, 99, 79 ), array( '#1f6bff', 100, 0, 149, 79 ) ), true ) );
t( count( $d2 ) === 2 && near( $d2[0]['hex'], '#0a1b3d', 12 ), 'transparent pixels are ignored' );
t( array() === noblen_palette_from_gd( logo( array() ) ), 'a blank white image gives no colours' );
t( null === noblen_suggest_palette( array() ), 'no colours gives no suggestion' );
t( array() === noblen_palette_from_gd( logo( array(), true ) ), 'a fully transparent image gives no colours' );

// ---- Single colour logo.
$s1 = noblen_suggest_palette( noblen_palette_from_gd( logo( array( array( '#1f6bff', 0, 0, 199, 79 ) ) ) ) );
t( null !== $s1 && all_pass( $s1['palette'] ), 'a single-colour logo still yields a readable palette' );
t( null !== $s1 && count( $s1['notes'] ) >= 1, 'a single-colour logo explains what was derived' );

// ---- Monochrome black logo.
$s2 = noblen_suggest_palette( noblen_palette_from_gd( logo( array( array( '#000000', 0, 0, 199, 79 ) ) ) ) );
t( null !== $s2 && all_pass( $s2['palette'] ), 'a black logo yields a readable palette' );

// ---- Gold logo: gold is ignored by design.
$sg = noblen_suggest_palette( noblen_palette_from_gd( logo( array( array( '#c9a227', 0, 0, 119, 79 ), array( '#111111', 120, 0, 199, 79 ) ) ) ) );
t( null !== $sg, 'a gold-and-black logo still yields a palette' );
$gold_free = true;
foreach ( array( 'primary', 'secondary', 'accent', 'link', 'button_bg' ) as $k ) {
	$gold_free = $gold_free && ! noblen_is_gold_tone( $sg['palette'][ $k ] );
}
t( $gold_free && str_contains( implode( ' ', $sg['notes'] ), 'gold' ), 'gold tones are not used and the administrator is told' );
t( all_pass( $sg['palette'] ), 'the gold-logo suggestion passes every contrast check' );

// ---- A very light logo (primary must be shaded).
$sl = noblen_suggest_palette( noblen_palette_from_gd( logo( array( array( '#9fc5ff', 0, 0, 199, 79 ) ) ) ) );
t( null !== $sl && noblen_luminance( $sl['palette']['primary'] ) <= 0.2 && all_pass( $sl['palette'] ), 'a pale logo gives a dark primary and a readable palette' );

// ---- SVG colour parsing.
$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><defs><linearGradient id="abc"><stop stop-color="#0a1b3d"/></linearGradient></defs><path fill="#0a1b3d"/><path fill="#0A1B3D"/><path style="fill:rgb(31,107,255)"/><path fill="green"/><rect fill="url(#abc)"/><rect fill="none"/><rect fill="#fff"/></svg>';
$ds  = noblen_palette_from_svg( $svg );
t( count( $ds ) === 3 && near( $ds[0]['hex'], '#0a1b3d', 6 ), 'SVG fills are counted (hex, rgb, named); none and white are ignored', wp_json_encode_t( $ds ) );
t( array() === noblen_palette_from_svg( '<svg xmlns="http://www.w3.org/2000/svg"><rect fill="url(#abc)"/></svg>' ), 'an id that looks like hex inside url(#id) is not read as a colour' );
function wp_json_encode_t( $v ): string {
	return (string) json_encode( $v );
}

// ---- Merging.
$m = noblen_palette_merge( array( array( 10, 20, 60, 100 ), array( 12, 22, 62, 50 ), array( 200, 40, 40, 30 ) ) );
t( count( $m ) === 2 && abs( $m[0]['share'] - 150 / 180 ) < 0.001, 'similar colours merge and shares are weighted' );
t( array() === noblen_palette_merge( array() ), 'merging nothing gives nothing' );

// ---- Property test: any logo gives a palette that passes every check, in light and dark, with no gold tones.
mt_srand( 99 );
$bad   = 0;
$first = '';
for ( $i = 0; $i < 300; $i++ ) {
	$n    = mt_rand( 1, 5 );
	$list = array();
	for ( $k = 0; $k < $n; $k++ ) {
		$list[] = array(
			'hex'   => noblen_from_rgb( array( mt_rand( 0, 255 ), mt_rand( 0, 255 ), mt_rand( 0, 255 ) ) ),
			'share' => ( $n - $k ) / ( $n * ( $n + 1 ) / 2 ),
		);
	}
	$sug = noblen_suggest_palette( $list );
	if ( null === $sug ) {
		continue; // Only gold tones in the list.
	}
	$gold = false;
	foreach ( array( 'primary', 'secondary', 'accent' ) as $key ) {
		$gold = $gold || noblen_is_gold_tone( $sug['palette'][ $key ] );
	}
	if ( ! all_pass( $sug['palette'] ) || $gold ) {
		++$bad;
		$first = $first ?: wp_json_encode_t( $list );
	}
}
t( 0 === $bad, "300 random logos: every suggestion passes every contrast check (light and dark) with no gold tone ($bad failed)", $first );

echo "\n{$pass} passed, {$fail} failed\n";
exit( $fail ? 1 : 0 );

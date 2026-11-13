<?php
/**
 * Logo palette analysis: find the representative colours of a logo and suggest a complete, readable palette.
 *
 * Runs on the server with PHP's GD library and plain text parsing. No external service is used. The result is only a
 * suggestion: the administrator reviews, adjusts and previews it, and nothing is applied until they publish.
 *
 * @package noblen-corporate
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hue, saturation and lightness (0..360, 0..1, 0..1).
 *
 * @param string $hex Hex.
 * @return float[]
 */
function noblen_hsl( string $hex ): array {
	list( $r, $g, $b ) = array_map(
		static fn( $c ) => $c / 255,
		noblen_rgb( $hex )
	);
	$max               = max( $r, $g, $b );
	$min               = min( $r, $g, $b );
	$l                 = ( $max + $min ) / 2;
	if ( $max === $min ) {
		return array( 0.0, 0.0, $l );
	}
	$d = $max - $min;
	$s = $l > 0.5 ? $d / ( 2 - $max - $min ) : $d / ( $max + $min );
	if ( $max === $r ) {
		$h = ( $g - $b ) / $d + ( $g < $b ? 6 : 0 );
	} elseif ( $max === $g ) {
		$h = ( $b - $r ) / $d + 2;
	} else {
		$h = ( $r - $g ) / $d + 4;
	}
	return array( $h * 60, $s, $l );
}

/**
 * Hex from hue, saturation and lightness.
 *
 * @param float $h Hue 0..360.
 * @param float $s Saturation 0..1.
 * @param float $l Lightness 0..1.
 */
function noblen_from_hsl( float $h, float $s, float $l ): string {
	$h = fmod( fmod( $h, 360 ) + 360, 360 ) / 360;
	$s = max( 0, min( 1, $s ) );
	$l = max( 0, min( 1, $l ) );
	if ( 0.0 === $s ) {
		return noblen_from_rgb( array( $l * 255, $l * 255, $l * 255 ) );
	}
	$q   = $l < 0.5 ? $l * ( 1 + $s ) : $l + $s - $l * $s;
	$p   = 2 * $l - $q;
	$hue = static function ( $t ) use ( $p, $q ) {
		$t = $t < 0 ? $t + 1 : ( $t > 1 ? $t - 1 : $t );
		if ( $t < 1 / 6 ) {
			return $p + ( $q - $p ) * 6 * $t;
		}
		if ( $t < 1 / 2 ) {
			return $q;
		}
		if ( $t < 2 / 3 ) {
			return $p + ( $q - $p ) * ( 2 / 3 - $t ) * 6;
		}
		return $p;
	};
	return noblen_from_rgb( array( $hue( $h + 1 / 3 ) * 255, $hue( $h ) * 255, $hue( $h - 1 / 3 ) * 255 ) );
}

/**
 * Angular distance between two hues, 0..180.
 *
 * @param float $a Hue.
 * @param float $b Hue.
 */
function noblen_hue_distance( float $a, float $b ): float {
	$d = abs( $a - $b );
	return $d > 180 ? 360 - $d : $d;
}

/**
 * Whether a colour is a yellow or gold tone, which the brand direction excludes from the interface.
 *
 * @param string $hex Hex.
 */
function noblen_is_gold_tone( string $hex ): bool {
	list( $h, $s, $l ) = noblen_hsl( $hex );
	return $h >= 36 && $h <= 70 && $s >= 0.3 && $l >= 0.25 && $l <= 0.85;
}

/**
 * Whether a hue lies in the yellow and gold range, whatever the lightness (used before a colour is re-toned).
 *
 * @param float $h Hue 0..360.
 */
function noblen_hue_is_gold( float $h ): bool {
	return $h >= 30 && $h <= 75;
}

/**
 * Reduce a list of weighted colours to the representative ones: similar colours are merged and rare ones dropped.
 *
 * @param array<int,array{0:int,1:int,2:int,3:int}> $samples Items of red, green, blue, weight.
 * @param int                                       $max     Maximum colours returned.
 * @return array<int,array{hex:string,share:float}>
 */
function noblen_palette_merge( array $samples, int $max = 6 ): array {
	$total = 0;
	foreach ( $samples as $s ) {
		$total += $s[3];
	}
	if ( $total <= 0 ) {
		return array();
	}
	usort( $samples, static fn( $a, $b ) => $b[3] <=> $a[3] );
	$groups = array();
	foreach ( $samples as $s ) {
		$placed = false;
		foreach ( $groups as &$g ) {
			$dist = sqrt( pow( $g['r'] - $s[0], 2 ) + pow( $g['g'] - $s[1], 2 ) + pow( $g['b'] - $s[2], 2 ) );
			if ( $dist < 48 ) {
				$w      = $g['w'] + $s[3];
				$g['r'] = ( $g['r'] * $g['w'] + $s[0] * $s[3] ) / $w;
				$g['g'] = ( $g['g'] * $g['w'] + $s[1] * $s[3] ) / $w;
				$g['b'] = ( $g['b'] * $g['w'] + $s[2] * $s[3] ) / $w;
				$g['w'] = $w;
				$placed = true;
				break;
			}
		}
		unset( $g );
		if ( ! $placed ) {
			$groups[] = array(
				'r' => (float) $s[0],
				'g' => (float) $s[1],
				'b' => (float) $s[2],
				'w' => (float) $s[3],
			);
		}
	}
	usort( $groups, static fn( $a, $b ) => $b['w'] <=> $a['w'] );
	$out = array();
	foreach ( $groups as $g ) {
		$share = $g['w'] / $total;
		if ( $share < 0.015 && count( $out ) > 0 ) {
			continue;
		}
		$out[] = array(
			'hex'   => noblen_from_rgb( array( $g['r'], $g['g'], $g['b'] ) ),
			'share' => round( $share, 4 ),
		);
		if ( count( $out ) >= $max ) {
			break;
		}
	}
	return $out;
}

/**
 * Representative colours of a raster image held in memory (GD). Transparent pixels and near-white backgrounds are ignored.
 *
 * @param GdImage $im Image.
 * @return array<int,array{hex:string,share:float}>
 */
function noblen_palette_from_gd( $im ): array {
	$w = imagesx( $im );
	$h = imagesy( $im );
	if ( $w > 96 || $h > 96 ) {
		$scale = imagescale( $im, min( 96, $w ), min( 96, $h ) );
		if ( false !== $scale ) {
			$im = $scale;
			$w  = imagesx( $im );
			$h  = imagesy( $im );
		}
	}
	$buckets = array();
	for ( $y = 0; $y < $h; $y++ ) {
		for ( $x = 0; $x < $w; $x++ ) {
			$px    = imagecolorat( $im, $x, $y );
			$alpha = ( $px >> 24 ) & 0x7F;
			if ( $alpha >= 64 ) {
				continue; // Mostly transparent.
			}
			$r = ( $px >> 16 ) & 0xFF;
			$g = ( $px >> 8 ) & 0xFF;
			$b = $px & 0xFF;
			if ( $r >= 238 && $g >= 238 && $b >= 238 ) {
				continue; // White or near-white background.
			}
			$key = ( $r >> 4 ) . '.' . ( $g >> 4 ) . '.' . ( $b >> 4 );
			if ( ! isset( $buckets[ $key ] ) ) {
				$buckets[ $key ] = array( 0, 0, 0, 0 );
			}
			$buckets[ $key ][0] += $r;
			$buckets[ $key ][1] += $g;
			$buckets[ $key ][2] += $b;
			++$buckets[ $key ][3];
		}
	}
	$samples = array();
	foreach ( $buckets as $v ) {
		$samples[] = array( (int) round( $v[0] / $v[3] ), (int) round( $v[1] / $v[3] ), (int) round( $v[2] / $v[3] ), $v[3] );
	}
	return noblen_palette_merge( $samples );
}

/**
 * Representative colours named in an SVG's fill, stroke, stop-color and colour declarations.
 *
 * @param string $svg SVG source (already sanitised).
 * @return array<int,array{hex:string,share:float}>
 */
function noblen_palette_from_svg( string $svg ): array {
	$named   = array(
		'black'     => '#000000',
		'navy'      => '#000080',
		'blue'      => '#0000ff',
		'teal'      => '#008080',
		'green'     => '#008000',
		'maroon'    => '#800000',
		'purple'    => '#800080',
		'red'       => '#ff0000',
		'gray'      => '#808080',
		'grey'      => '#808080',
		'silver'    => '#c0c0c0',
		'orange'    => '#ffa500',
		'darkblue'  => '#00008b',
		'darkgreen' => '#006400',
		'royalblue' => '#4169e1',
		'steelblue' => '#4682b4',
		'white'     => '#ffffff',
	);
	$counts  = array();
	$pattern = '/(?:fill|stroke|stop-color|[^-]color)\s*[:=]\s*["\']?\s*(#[0-9a-fA-F]{3,8}\b|rgb\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*\)|[a-zA-Z]+)/';
	if ( preg_match_all( $pattern, $svg, $m ) ) {
		foreach ( $m[1] as $raw ) {
			$hex = null;
			if ( '#' === $raw[0] ) {
				$digits = substr( $raw, 1 );
				$hex    = noblen_hex( '#' . ( 8 === strlen( $digits ) ? substr( $digits, 0, 6 ) : ( 4 === strlen( $digits ) ? substr( $digits, 0, 3 ) : $digits ) ) );
			} elseif ( 0 === stripos( $raw, 'rgb' ) && preg_match_all( '/\d+/', $raw, $n ) ) {
				$hex = noblen_from_rgb( array_map( 'intval', array_slice( $n[0], 0, 3 ) ) );
			} else {
				$hex = $named[ strtolower( $raw ) ] ?? null;
			}
			if ( null === $hex ) {
				continue;
			}
			$rgb = noblen_rgb( $hex );
			if ( $rgb[0] >= 238 && $rgb[1] >= 238 && $rgb[2] >= 238 ) {
				continue;
			}
			$counts[ $hex ] = ( $counts[ $hex ] ?? 0 ) + 1;
		}
	}
	$samples = array();
	foreach ( $counts as $hex => $n ) {
		$rgb       = noblen_rgb( $hex );
		$samples[] = array( $rgb[0], $rgb[1], $rgb[2], $n );
	}
	return noblen_palette_merge( $samples );
}

/**
 * Suggest a complete palette from a logo's representative colours. Every suggested colour is adjusted until the palette
 * passes the same contrast checks as the Customizer reports. Yellow and gold tones are ignored by design.
 *
 * @param array<int,array{hex:string,share:float}> $dominant Representative colours, most common first.
 * @return array{palette:array<string,string>,notes:string[]}|null Null when no usable colour was found.
 */
function noblen_suggest_palette( array $dominant ): ?array {
	$notes  = array();
	$colors = array();
	foreach ( $dominant as $d ) {
		$hex = noblen_hex( $d['hex'] ?? null );
		if ( null === $hex ) {
			continue;
		}
		if ( noblen_is_gold_tone( $hex ) ) {
			$notes[] = 'Yellow and gold tones in the logo were ignored: the brand direction does not use them in the interface.';
			continue;
		}
		$colors[] = array(
			'hex'   => $hex,
			'share' => (float) ( $d['share'] ?? 0 ),
			'hsl'   => noblen_hsl( $hex ),
			'lum'   => noblen_luminance( $hex ),
		);
	}
	$notes = array_values( array_unique( $notes ) );
	if ( ! $colors ) {
		return null;
	}

	// Primary: the most common dark colour; otherwise the darkest colour, shaded.
	$primary = null;
	foreach ( $colors as $c ) {
		if ( $c['lum'] < 0.15 && ( null === $primary || $c['share'] > $primary['share'] ) ) {
			$primary = $c;
		}
	}
	if ( null === $primary ) {
		usort( $colors, static fn( $a, $b ) => $a['lum'] <=> $b['lum'] );
		$primary = $colors[0];
		$shaded  = noblen_mix( $primary['hex'], '#000000', 0.55 );
		$primary = array(
			'hex'   => $shaded,
			'share' => $primary['share'],
			'hsl'   => noblen_hsl( $shaded ),
			'lum'   => noblen_luminance( $shaded ),
		);
		$notes[] = 'No dark colour was found in the logo, so the primary colour is a darker shade of its darkest colour.';
	}
	$primary_hex          = noblen_mix( $primary['hex'], '#000000', 0.0 );
	list( $ph, $ps, $pl ) = $primary['hsl'];

	// Secondary: the most common vivid colour that differs from the primary.
	$secondary = null;
	foreach ( $colors as $c ) {
		if ( $c['hex'] === $primary['hex'] || $c['hsl'][1] < 0.25 || $c['hsl'][2] < 0.12 || $c['hsl'][2] > 0.9 || noblen_hue_is_gold( $c['hsl'][0] ) ) {
			continue;
		}
		$similar = noblen_hue_distance( $c['hsl'][0], $ph ) < 20 && abs( $c['hsl'][2] - $pl ) < 0.2;
		if ( ! $similar && ( null === $secondary || $c['share'] > $secondary['share'] ) ) {
			$secondary = $c;
		}
	}
	if ( null !== $secondary ) {
		list( $sh, $ss, $sl ) = $secondary['hsl'];
		$secondary_hex        = noblen_from_hsl( $sh, max( $ss, 0.6 ), max( 0.38, min( 0.58, $sl ) ) );
	} else {
		$sh            = ( $ps > 0.1 && ! noblen_hue_is_gold( $ph ) ) ? $ph : 215.0;
		$secondary_hex = noblen_from_hsl( $sh, 0.85, 0.52 );
		$notes[]       = 'The logo has no second vivid colour, so the secondary colour is a brighter tone of the primary.';
	}
	$secondary_hex = noblen_ensure_contrast( $secondary_hex, '#ffffff', 3 );
	list( $sh )    = noblen_hsl( $secondary_hex );

	// Accent: a colour clearly different in hue from both; otherwise a restrained complementary tone.
	$accent = null;
	foreach ( $colors as $c ) {
		if ( $c['hsl'][1] < 0.25 || noblen_hue_is_gold( $c['hsl'][0] ) || noblen_hue_distance( $c['hsl'][0], $ph ) < 45 || noblen_hue_distance( $c['hsl'][0], $sh ) < 45 ) {
			continue;
		}
		if ( null === $accent || $c['share'] > $accent['share'] ) {
			$accent = $c;
		}
	}
	if ( null !== $accent ) {
		$accent_hex = noblen_from_hsl( $accent['hsl'][0], min( 0.7, max( 0.4, $accent['hsl'][1] ) ), 0.28 );
	} else {
		$hue = $sh + 150;
		foreach ( array( 150, 210, 120, 240, 180 ) as $offset ) {
			$candidate = fmod( $sh + $offset, 360 );
			if ( ! noblen_hue_is_gold( $candidate ) && noblen_hue_distance( $candidate, $ph ) >= 30 ) {
				$hue = $candidate;
				break;
			}
		}
		$accent_hex = noblen_from_hsl( $hue, 0.6, 0.28 );
		$notes[]    = 'The logo has no third distinct colour, so the accent colour is a restrained complementary tone.';
	}
	$guard = 0;
	while ( $guard < 40 && noblen_contrast( $accent_hex, noblen_mix( '#ffffff', $accent_hex, 0.14 ) ) < 4.5 ) {
		$accent_hex = noblen_mix( $accent_hex, '#000000', 0.06 );
		++$guard;
	}

	// The rest of the palette, derived so that it is readable.
	$primary_use = noblen_luminance( $primary_hex ) <= 0.2 ? $primary_hex : noblen_mix( $primary_hex, '#000000', 0.5 );
	$text        = noblen_ensure_contrast( noblen_mix( $primary_use, '#000000', 0.5 ), '#ffffff', 10 );
	$heading     = noblen_ensure_contrast( $primary_use, '#ffffff', 7 );
	$button      = $secondary_hex;
	$guard       = 0;
	while ( $guard < 40 && noblen_contrast( '#ffffff', $button ) < 4.5 ) {
		$button = noblen_mix( $button, '#000000', 0.05 );
		++$guard;
	}
	$link      = noblen_ensure_contrast( $secondary_hex, '#ffffff', 4.5 );
	$footer_bg = $primary_use;
	$palette   = noblen_brand_complete(
		array(
			'primary'       => $primary_use,
			'secondary'     => $secondary_hex,
			'accent'        => $accent_hex,
			'heading'       => $heading,
			'text'          => $text,
			'bg'            => '#ffffff',
			'surface'       => '#ffffff',
			'header_bg'     => '#ffffff',
			'header_text'   => $text,
			'footer_bg'     => $footer_bg,
			'footer_text'   => noblen_ensure_contrast( noblen_mix( $footer_bg, '#ffffff', 0.78 ), $footer_bg, 4.5 ),
			'link'          => $link,
			'link_hover'    => $heading,
			'link_active'   => $heading,
			'button_bg'     => $button,
			'button_text'   => '#ffffff',
			'button_hover'  => noblen_mix( $button, '#000000', 0.18 ),
			'button_active' => noblen_mix( $button, '#000000', 0.32 ),
		)
	);
	return array(
		'palette' => $palette,
		'notes'   => $notes,
	);
}

/**
 * Representative colours of an attachment. Raster logos use the smaller generated size when there is one.
 *
 * @param int $attachment_id Attachment ID.
 * @return array<int,array{hex:string,share:float}>|WP_Error
 */
function noblen_palette_from_attachment( int $attachment_id ) {
	$mime = (string) get_post_mime_type( $attachment_id );
	$path = get_attached_file( $attachment_id );
	if ( 'attachment' !== get_post_type( $attachment_id ) || 0 !== strpos( $mime, 'image/' ) || ! $path || ! is_readable( $path ) ) {
		return new WP_Error( 'noblen_palette_file', 'The logo file could not be read.' );
	}
	if ( 'image/svg+xml' === $mime ) {
		$svg    = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$reason = '';
		$clean  = noblen_svg_sanitize( $svg, $reason );
		return null === $clean ? new WP_Error( 'noblen_palette_svg', $reason ) : noblen_palette_from_svg( $clean );
	}
	$meta = wp_get_attachment_metadata( $attachment_id );
	if ( ! empty( $meta['sizes']['medium']['file'] ) ) {
		$small = trailingslashit( dirname( $path ) ) . $meta['sizes']['medium']['file'];
		if ( is_readable( $small ) ) {
			$path = $small;
		}
	}
	if ( filesize( $path ) > 8 * 1024 * 1024 ) {
		return new WP_Error( 'noblen_palette_large', 'The logo file is too large to analyse. Choose the colours manually.' );
	}
	$size = wp_getimagesize( $path );
	if ( ! $size || (int) $size[0] * (int) $size[1] > 16000000 ) {
		return new WP_Error( 'noblen_palette_large', 'The logo is too large to analyse. Choose the colours manually.' );
	}
	$im = function_exists( 'imagecreatefromstring' ) ? @imagecreatefromstring( (string) file_get_contents( $path ) ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	if ( false === $im ) {
		return new WP_Error( 'noblen_palette_decode', 'This image format cannot be analysed on this server. Choose the colours manually.' );
	}
	$dominant = noblen_palette_from_gd( $im );
	imagedestroy( $im );
	return $dominant;
}

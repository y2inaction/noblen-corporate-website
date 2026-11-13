<?php
/**
 * Safe SVG logos.
 *
 * WordPress blocks SVG uploads because an SVG is a document that can carry script. This file allows SVG uploads for users who
 * may edit theme options, and ONLY after the file has been rebuilt from an allow-list: unknown elements and attributes are
 * dropped, no script, no external references, no embedded documents, no entities. A file that cannot be made safe is rejected.
 *
 * @package noblen-corporate
 */

defined( 'ABSPATH' ) || exit;

// The DOM extension's own property names (documentElement, childNodes and so on) are not snake_case.
// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

const NOBLEN_SVG_NS       = 'http://www.w3.org/2000/svg';
const NOBLEN_XLINK_NS     = 'http://www.w3.org/1999/xlink';
const NOBLEN_SVG_MAX_SIZE = 262144;

/**
 * Elements that may appear in a logo.
 *
 * @return string[]
 */
function noblen_svg_allowed_elements(): array {
	return array( 'svg', 'g', 'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon', 'text', 'tspan', 'defs', 'lineargradient', 'radialgradient', 'stop', 'clippath', 'mask', 'title', 'desc', 'symbol', 'use', 'style' );
}

/**
 * Attributes that may appear (lower-case names). Presentation and geometry only.
 *
 * @return string[]
 */
function noblen_svg_allowed_attributes(): array {
	return array(
		'id',
		'class',
		'd',
		'x',
		'y',
		'width',
		'height',
		'cx',
		'cy',
		'r',
		'rx',
		'ry',
		'x1',
		'y1',
		'x2',
		'y2',
		'points',
		'transform',
		'fill',
		'fill-opacity',
		'fill-rule',
		'stroke',
		'stroke-width',
		'stroke-linecap',
		'stroke-linejoin',
		'stroke-miterlimit',
		'stroke-dasharray',
		'stroke-dashoffset',
		'stroke-opacity',
		'opacity',
		'clip-path',
		'clip-rule',
		'mask',
		'viewbox',
		'preserveaspectratio',
		'version',
		'offset',
		'stop-color',
		'stop-opacity',
		'gradientunits',
		'gradienttransform',
		'spreadmethod',
		'fx',
		'fy',
		'clippathunits',
		'maskunits',
		'maskcontentunits',
		'font-family',
		'font-size',
		'font-weight',
		'font-style',
		'letter-spacing',
		'text-anchor',
		'dx',
		'dy',
		'style',
		'role',
		'aria-label',
		'aria-labelledby',
		'aria-hidden',
		'focusable',
		'display',
		'visibility',
		'href',
		'xlink:href',
		'xmlns',
		'xmlns:xlink',
	);
}

/**
 * CSS properties allowed in style attributes and style elements.
 *
 * @return string[]
 */
function noblen_svg_allowed_css(): array {
	return array(
		'fill',
		'fill-opacity',
		'fill-rule',
		'stroke',
		'stroke-width',
		'stroke-linecap',
		'stroke-linejoin',
		'stroke-miterlimit',
		'stroke-dasharray',
		'stroke-dashoffset',
		'stroke-opacity',
		'opacity',
		'stop-color',
		'stop-opacity',
		'font-family',
		'font-size',
		'font-weight',
		'font-style',
		'letter-spacing',
		'text-anchor',
		'clip-rule',
		'display',
		'visibility',
	);
}

/**
 * Whether a value is free of anything that can load or run something.
 *
 * @param string $value Attribute or CSS value.
 */
function noblen_svg_value_is_safe( string $value ): bool {
	if ( preg_match( '/[<>\\\\]|javascript:|vbscript:|data:|expression\s*\(|@import|behaviou?r|-moz-binding|\/\*/i', $value ) ) {
		return false;
	}
	if ( preg_match_all( '/url\s*\(\s*([^)]*)\)/i', $value, $m ) ) {
		foreach ( $m[1] as $target ) {
			if ( ! preg_match( '/^[\'"]?#[A-Za-z0-9_.:-]+[\'"]?$/', trim( $target ) ) ) {
				return false;
			}
		}
	}
	return true;
}

/**
 * Rebuild a style declaration list from the allow-list.
 *
 * @param string $css Declarations such as "fill:#fff;stroke:none".
 */
function noblen_svg_clean_declarations( string $css ): string {
	$out = array();
	foreach ( explode( ';', $css ) as $decl ) {
		if ( false === strpos( $decl, ':' ) ) {
			continue;
		}
		list( $prop, $val ) = array_map( 'trim', explode( ':', $decl, 2 ) );
		$prop               = strtolower( $prop );
		if ( in_array( $prop, noblen_svg_allowed_css(), true ) && '' !== $val && noblen_svg_value_is_safe( $val ) ) {
			$out[] = $prop . ':' . $val;
		}
	}
	return implode( ';', $out );
}

/**
 * Rebuild a style element's text: simple selectors only, allow-listed properties, no at-rules.
 *
 * @param string $css Stylesheet text.
 */
function noblen_svg_clean_stylesheet( string $css ): string {
	$css = (string) preg_replace( '/\/\*.*?\*\//s', '', $css );
	$out = '';
	if ( preg_match_all( '/([^{}@]+)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER ) ) {
		foreach ( $rules as $rule ) {
			$selector = trim( $rule[1] );
			if ( '' === $selector || ! preg_match( '/^[A-Za-z0-9_.#,\s>*-]+$/', $selector ) ) {
				continue;
			}
			$decls = noblen_svg_clean_declarations( $rule[2] );
			if ( '' !== $decls ) {
				$out .= $selector . '{' . $decls . '}';
			}
		}
	}
	return $out;
}

/**
 * Remove everything not on the allow-list, recursively.
 *
 * @param DOMNode $node Node whose children are cleaned.
 * @param int     $count Running node count (limit).
 */
function noblen_svg_clean_node( DOMNode $node, int &$count ): void {
	$allowed_el = noblen_svg_allowed_elements();
	$allowed_at = noblen_svg_allowed_attributes();
	foreach ( iterator_to_array( $node->childNodes ) as $child ) {
		if ( ++$count > 5000 ) {
			throw new RuntimeException( 'too many nodes' );
		}
		if ( XML_TEXT_NODE === $child->nodeType || XML_CDATA_SECTION_NODE === $child->nodeType ) {
			$parent = strtolower( (string) $node->localName );
			if ( ! in_array( $parent, array( 'text', 'tspan', 'title', 'desc', 'style' ), true ) && '' !== trim( (string) $child->nodeValue ) ) {
				$node->removeChild( $child );
			}
			continue;
		}
		if ( XML_ELEMENT_NODE !== $child->nodeType ) {
			$node->removeChild( $child ); // Comments, processing instructions, entity references.
			continue;
		}
		$name = strtolower( (string) $child->localName );
		if ( ! in_array( $name, $allowed_el, true ) || ( '' !== (string) $child->namespaceURI && NOBLEN_SVG_NS !== $child->namespaceURI ) ) {
			$node->removeChild( $child );
			continue;
		}
		foreach ( iterator_to_array( $child->attributes ) as $attr ) {
			$aname = strtolower( $attr->nodeName );
			$value = (string) $attr->nodeValue;
			$keep  = in_array( $aname, $allowed_at, true ) && 0 !== strpos( $aname, 'on' );
			if ( $keep && ( 'href' === $aname || 'xlink:href' === $aname ) ) {
				$keep = 'use' === $name && preg_match( '/^#[A-Za-z0-9_.:-]+$/', $value );
			}
			if ( $keep && ( 'xmlns' === $aname || 'xmlns:xlink' === $aname ) ) {
				$keep = NOBLEN_SVG_NS === $value || NOBLEN_XLINK_NS === $value;
			}
			if ( $keep && 'style' === $aname ) {
				$clean = noblen_svg_clean_declarations( $value );
				if ( '' === $clean ) {
					$keep = false;
				} else {
					$attr->nodeValue = $clean;
				}
			}
			if ( $keep && 'style' !== $aname && ! noblen_svg_value_is_safe( $value ) ) {
				$keep = false;
			}
			if ( ! $keep ) {
				$child->removeAttributeNode( $attr );
			}
		}
		if ( 'style' === $name ) {
			$clean = noblen_svg_clean_stylesheet( (string) $child->textContent );
			if ( '' === $clean ) {
				$node->removeChild( $child );
				continue;
			}
			while ( $child->firstChild ) {
				$child->removeChild( $child->firstChild );
			}
			$child->appendChild( $child->ownerDocument->createTextNode( $clean ) );
			continue;
		}
		noblen_svg_clean_node( $child, $count );
	}
}

/**
 * Make an SVG document safe, or refuse it.
 *
 * @param string $svg   SVG source.
 * @param string $error Receives a plain reason when the file is refused.
 * @return string|null The sanitised SVG, or null when it cannot be made safe.
 */
function noblen_svg_sanitize( string $svg, string &$error = '' ): ?string {
	$error = '';
	if ( '' === trim( $svg ) || strlen( $svg ) > NOBLEN_SVG_MAX_SIZE ) {
		$error = 'The SVG is empty or larger than 256 KB.';
		return null;
	}
	$svg = (string) preg_replace( '/^\xEF\xBB\xBF/', '', $svg );
	if ( preg_match( '/<!(DOCTYPE|ENTITY|ELEMENT|ATTLIST)|<\?(?!xml\b)/i', $svg ) ) {
		$error = 'The SVG contains a document type, entity or processing instruction, which are not allowed.';
		return null;
	}
	if ( false !== strpos( $svg, "\0" ) ) {
		$error = 'The file is not a valid SVG.';
		return null;
	}
	$previous = libxml_use_internal_errors( true );
	$dom      = new DOMDocument();
	$loaded   = $dom->loadXML( $svg, LIBXML_NONET | LIBXML_NOBLANKS );
	libxml_clear_errors();
	libxml_use_internal_errors( $previous );
	$root = $loaded ? $dom->documentElement : null;
	if ( ! $root || 'svg' !== strtolower( (string) $root->localName ) || NOBLEN_SVG_NS !== $root->namespaceURI ) {
		$error = 'The file is not a valid SVG image.';
		return null;
	}
	try {
		$count = 0;
		foreach ( iterator_to_array( $dom->childNodes ) as $top ) {
			if ( $top !== $root ) {
				$dom->removeChild( $top );
			}
		}
		// The root's own attributes are cleaned by treating a fresh document as the parent of the root.
		$wrapper = new DOMDocument();
		$wrapper->appendChild( $wrapper->importNode( $root, true ) );
		noblen_svg_clean_node( $wrapper, $count );
		$clean_root = $wrapper->documentElement;
	} catch ( RuntimeException $e ) {
		$error = 'The SVG is too complex.';
		return null;
	}
	if ( ! $clean_root || 'svg' !== strtolower( (string) $clean_root->localName ) ) {
		$error = 'The file is not a valid SVG image.';
		return null;
	}
	$out = $wrapper->saveXML( $clean_root );
	if ( false === $out || '' === $out ) {
		$error = 'The SVG could not be processed.';
		return null;
	}
	return $out;
}

/**
 * Intrinsic size of a (sanitised) SVG, from width and height or the viewBox. Zero when unknown.
 *
 * @param string $svg SVG source.
 * @return int[] Width and height in pixels.
 */
function noblen_svg_dimensions( string $svg ): array {
	$w = 0.0;
	$h = 0.0;
	if ( preg_match( '/<svg\b[^>]*>/i', $svg, $tag ) ) {
		if ( preg_match( '/\bwidth="([0-9.]+)(px)?"/i', $tag[0], $m ) ) {
			$w = (float) $m[1];
		}
		if ( preg_match( '/\bheight="([0-9.]+)(px)?"/i', $tag[0], $m ) ) {
			$h = (float) $m[1];
		}
		if ( ( $w <= 0 || $h <= 0 ) && preg_match( '/\bviewBox="\s*[-0-9.]+[\s,]+[-0-9.]+[\s,]+([0-9.]+)[\s,]+([0-9.]+)\s*"/i', $tag[0], $m ) ) {
			$w = (float) $m[1];
			$h = (float) $m[2];
		}
	}
	return array( (int) round( $w ), (int) round( $h ) );
}

/** Whether the current user may upload SVG logos. */
function noblen_svg_user_can_upload(): bool {
	return current_user_can( 'edit_theme_options' );
}

add_filter(
	'upload_mimes',
	static function ( $mimes ) {
		if ( noblen_svg_user_can_upload() ) {
			$mimes['svg'] = 'image/svg+xml';
		}
		return $mimes;
	}
);

// WordPress sniffs the real type of an upload; an SVG is reported as plain text or XML. Accept it only when it looks like an SVG.
add_filter(
	'wp_check_filetype_and_ext',
	static function ( $data, $file, $filename ) {
		if ( noblen_svg_user_can_upload() && 'svg' === strtolower( pathinfo( (string) $filename, PATHINFO_EXTENSION ) ) && is_readable( (string) $file ) ) {
			$head = (string) file_get_contents( $file, false, null, 0, 2048 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( preg_match( '/<svg\b/i', $head ) ) {
				return array(
					'ext'             => 'svg',
					'type'            => 'image/svg+xml',
					'proper_filename' => $data['proper_filename'] ?? false,
				);
			}
		}
		return $data;
	},
	10,
	3
);

// Rebuild the uploaded file from the allow-list before WordPress stores it. A file that cannot be made safe is refused.
add_filter(
	'wp_handle_upload_prefilter',
	static function ( $file ) {
		if ( empty( $file['name'] ) || 'svg' !== strtolower( pathinfo( (string) $file['name'], PATHINFO_EXTENSION ) ) ) {
			return $file;
		}
		if ( ! noblen_svg_user_can_upload() ) {
			$file['error'] = 'You are not allowed to upload SVG files.';
			return $file;
		}
		$source = is_readable( (string) ( $file['tmp_name'] ?? '' ) ) ? (string) file_get_contents( $file['tmp_name'] ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$reason = '';
		$clean  = noblen_svg_sanitize( $source, $reason );
		if ( null === $clean ) {
			$file['error'] = 'This SVG was not uploaded: ' . $reason;
			return $file;
		}
		if ( false === file_put_contents( $file['tmp_name'], $clean ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			$file['error'] = 'This SVG could not be processed.';
		}
		return $file;
	}
);

// Give SVG attachments dimensions (from the sanitised file) so layouts keep the aspect ratio.
add_filter(
	'wp_generate_attachment_metadata',
	static function ( $metadata, $attachment_id ) {
		if ( 'image/svg+xml' === get_post_mime_type( $attachment_id ) ) {
			$path = get_attached_file( $attachment_id );
			$dims = ( $path && is_readable( $path ) ) ? noblen_svg_dimensions( (string) file_get_contents( $path ) ) : array( 0, 0 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( $dims[0] > 0 && $dims[1] > 0 ) {
				$metadata           = is_array( $metadata ) ? $metadata : array();
				$metadata['width']  = $dims[0];
				$metadata['height'] = $dims[1];
				$metadata['file']   = _wp_relative_upload_path( (string) $path );
			}
		}
		return $metadata;
	},
	10,
	2
);

// Show an SVG as an image, not as a generic file icon, in the media library and the Customizer.
add_filter(
	'wp_prepare_attachment_for_js',
	static function ( $response, $attachment ) {
		if ( 'image/svg+xml' === ( $response['mime'] ?? '' ) ) {
			$meta               = wp_get_attachment_metadata( $attachment->ID );
			$w                  = (int) ( $meta['width'] ?? 0 );
			$h                  = (int) ( $meta['height'] ?? 0 );
			$response['sizes']  = array(
				'full' => array(
					'url'         => $response['url'],
					'width'       => $w,
					'height'      => $h,
					'orientation' => $h > $w ? 'portrait' : 'landscape',
				),
			);
			$response['width']  = $w;
			$response['height'] = $h;
			$response['icon']   = $response['url'];
		}
		return $response;
	},
	10,
	2
);

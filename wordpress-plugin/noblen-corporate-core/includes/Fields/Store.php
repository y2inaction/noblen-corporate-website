<?php
namespace Noblen\Core\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Reads, validates and writes field values.
 */
final class Store {

	/**
	 * Allowed markup for rich-text fields. No scripts, frames or embedded objects.
	 *
	 * @return array<string,array<string,bool>>
	 */
	public static function allowed_html(): array {
		return array(
			'p'          => array(),
			'br'         => array(),
			'strong'     => array(),
			'b'          => array(),
			'em'         => array(),
			'i'          => array(),
			'ul'         => array(),
			'ol'         => array(),
			'li'         => array(),
			'h3'         => array(),
			'h4'         => array(),
			'blockquote' => array(),
			'a'          => array(
				'href'  => true,
				'title' => true,
				'rel'   => true,
			),
		);
	}

	/**
	 * Write a string meta value (slashed, as WordPress expects).
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param string $value   Value.
	 */
	private static function put( int $post_id, string $key, string $value ): void {
		update_post_meta( $post_id, $key, wp_slash( $value ) );
	}

	/**
	 * Current values of all registered fields for a record.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string,mixed> Field key => value (and key_approved switches).
	 */
	public static function get_all( int $post_id ): array {
		$type = (string) get_post_type( $post_id );
		$out  = array();
		foreach ( Registry::fields( $type ) as $key => $def ) {
			$kind = (string) ( $def['kind'] ?? 'text' );
			if ( 'rel' === $kind ) {
				$ids         = array_map( 'intval', (array) get_post_meta( $post_id, Registry::rel_key( $key ), false ) );
				$out[ $key ] = array_values( array_unique( $ids ) );
				continue;
			}
			if ( 'gallery' === $kind ) {
				$raw         = json_decode( (string) get_post_meta( $post_id, Registry::meta_key( $key ), true ), true );
				$out[ $key ] = is_array( $raw ) ? array_values( array_map( 'intval', $raw ) ) : array();
				continue;
			}
			$val         = (string) get_post_meta( $post_id, Registry::meta_key( $key ), true );
			$out[ $key ] = ( 'bool' === $kind ) ? ( '1' === $val ) : $val;
			if ( ! empty( $def['switch'] ) ) {
				$out[ $key . '_approved' ] = ( '1' === (string) get_post_meta( $post_id, Registry::switch_key( $key ), true ) );
			}
		}
		return $out;
	}

	/**
	 * Validate and save the submitted values.
	 *
	 * Invalid values are not saved. Valid values are saved.
	 *
	 * @param int                 $post_id Post ID.
	 * @param array<string,mixed> $input   Raw (unslashed) input.
	 * @return array<string,string> Validation errors by field key.
	 */
	public static function save( int $post_id, array $input ): array {
		$type   = (string) get_post_type( $post_id );
		$errors = array();

		foreach ( Registry::fields( $type ) as $key => $def ) {
			if ( ! array_key_exists( $key, $input ) && empty( $def['switch'] ) ) {
				continue;
			}
			$kind = (string) ( $def['kind'] ?? 'text' );
			$raw  = $input[ $key ] ?? null;

			if ( array_key_exists( $key, $input ) ) {
				switch ( $kind ) {
					case 'text':
						self::put( $post_id, Registry::meta_key( $key ), sanitize_text_field( (string) $raw ) );
						break;
					case 'richtext':
						self::put( $post_id, Registry::meta_key( $key ), wp_kses( (string) $raw, self::allowed_html() ) );
						break;
					case 'enum':
						$val = (string) $raw;
						if ( '' === $val || isset( $def['options'][ $val ] ) ) {
							self::put( $post_id, Registry::meta_key( $key ), $val );
						} else {
							$errors[ $key ] = 'Choose one of the listed values.';
						}
						break;
					case 'url':
						$val = (string) $raw;
						$url = '' === $val ? '' : esc_url_raw( $val, array( 'https', 'http' ) );
						if ( '' !== $val && '' === $url ) {
							$errors[ $key ] = 'Enter a valid http or https address.';
						} else {
							self::put( $post_id, Registry::meta_key( $key ), $url );
						}
						break;
					case 'bool':
						self::put( $post_id, Registry::meta_key( $key ), filter_var( $raw, FILTER_VALIDATE_BOOLEAN ) ? '1' : '0' );
						break;
					case 'rel':
						$err = self::save_relationship( $post_id, (string) $key, $def, $raw );
						if ( $err ) {
							$errors[ $key ] = $err;
						}
						break;
					case 'gallery':
						$ids = array_values( array_unique( array_map( 'intval', (array) $raw ) ) );
						$bad = array_filter( $ids, static fn( $id ) => ! $id || ! wp_attachment_is_image( $id ) );
						if ( $bad ) {
							$errors[ $key ] = 'Every gallery item must be an image from the media library.';
						} else {
							self::put( $post_id, Registry::meta_key( $key ), (string) wp_json_encode( $ids ) );
						}
						break;
				}
			}

			if ( ! empty( $def['switch'] ) && array_key_exists( $key . '_approved', $input ) ) {
				self::put( $post_id, Registry::switch_key( $key ), filter_var( $input[ $key . '_approved' ], FILTER_VALIDATE_BOOLEAN ) ? '1' : '0' );
			}
		}
		return $errors;
	}

	/**
	 * Save a relationship field after checking its targets.
	 *
	 * @param int                 $post_id Post ID.
	 * @param string              $key     Field key.
	 * @param array<string,mixed> $def     Definition.
	 * @param mixed               $raw     Raw value.
	 * @return string Error message or empty string.
	 */
	private static function save_relationship( int $post_id, string $key, array $def, $raw ): string {
		$ids = array_values( array_unique( array_filter( array_map( 'intval', (array) $raw ) ) ) );
		if ( empty( $def['multiple'] ) && count( $ids ) > 1 ) {
			return 'Choose a single record.';
		}
		foreach ( $ids as $id ) {
			$p = get_post( $id );
			if ( ! $p || $p->post_type !== $def['target'] || (int) $id === $post_id || 'trash' === $p->post_status ) {
				return 'One of the selected records is not valid.';
			}
		}
		delete_post_meta( $post_id, Registry::rel_key( $key ) );
		foreach ( $ids as $id ) {
			add_post_meta( $post_id, Registry::rel_key( $key ), $id );
		}
		return '';
	}
}

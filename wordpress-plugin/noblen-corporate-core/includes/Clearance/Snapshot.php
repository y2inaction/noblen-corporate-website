<?php
namespace Noblen\Core\Clearance;

use Noblen\Core\Fields\Registry;
use Noblen\Core\Fields\Store;

defined( 'ABSPATH' ) || exit;

/**
 * Canonical content snapshot used for approval.
 *
 * It covers everything that affects public presentation: core fields, every
 * registered public field and its display switch, relationship IDs (as sorted
 * sets), classification terms, the featured image and gallery (ID, file
 * checksum, alt text, caption and title), the video address and presentation
 * flags. It is built from the same registry that defines the public fields.
 *
 * Excluded: private clearance, approval and audit data, modification times,
 * revisions and the WordPress publication status.
 */
final class Snapshot {

	public const FORMAT_VERSION = 1;

	/** @var array<string,string> */
	private static array $checksums = array();

	/**
	 * Compute the snapshot for a record.
	 *
	 * @param int $post_id Post ID.
	 * @return array{hash:string,text:string,data:array<string,mixed>,format:int}
	 */
	public static function compute( int $post_id ): array {
		$post = get_post( $post_id );
		$type = $post ? $post->post_type : '';

		$data = array(
			'v'             => self::FORMAT_VERSION,
			'type'          => $type,
			'title'         => $post ? (string) $post->post_title : '',
			'slug'          => $post ? Slug::effective( $post_id ) : '',
			'excerpt'       => $post ? (string) $post->post_excerpt : '',
			'content'       => $post ? (string) $post->post_content : '',
			'fields'        => array(),
			'relationships' => array(),
			'terms'         => array(),
			'featured'      => null,
			'gallery'       => array(),
		);

		$values = Store::get_all( $post_id );
		foreach ( Registry::public_fields( $type ) as $key => $def ) {
			$kind = (string) ( $def['kind'] ?? 'text' );
			if ( 'rel' === $kind ) {
				$ids = array_map( 'intval', (array) ( $values[ $key ] ?? array() ) );
				sort( $ids );
				$data['relationships'][ $key ] = array_values( array_unique( $ids ) );
			} elseif ( 'gallery' === $kind ) {
				foreach ( (array) ( $values[ $key ] ?? array() ) as $att ) {
					$data['gallery'][] = self::media( (int) $att );
				}
			} else {
				$data['fields'][ $key ] = $values[ $key ] ?? ( 'bool' === $kind ? false : '' );
				if ( ! empty( $def['switch'] ) ) {
					$data['fields'][ $key . '_approved' ] = (bool) ( $values[ $key . '_approved' ] ?? false );
				}
			}
		}

		$thumb = (int) get_post_thumbnail_id( $post_id );
		if ( $thumb ) {
			$data['featured'] = self::media( $thumb );
		}

		foreach ( get_object_taxonomies( $type ) as $tax ) {
			$ids = wp_get_object_terms( $post_id, $tax, array( 'fields' => 'ids' ) );
			if ( is_array( $ids ) ) {
				$ids = array_map( 'intval', $ids );
				sort( $ids );
				$data['terms'][ $tax ] = $ids;
			}
		}

		$data = self::normalise( $data );
		$json = (string) wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		$text = 'noblen-snapshot-v' . self::FORMAT_VERSION . "\n" . $json;

		return array(
			'hash'   => hash( 'sha256', $text ),
			'text'   => $text,
			'data'   => $data,
			'format' => self::FORMAT_VERSION,
		);
	}

	/**
	 * Snapshot hash only.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function hash( int $post_id ): string {
		return self::compute( $post_id )['hash'];
	}

	/**
	 * Descriptor of a media item: identity and the text shown with it.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<string,mixed>
	 */
	public static function media( int $attachment_id ): array {
		$att = get_post( $attachment_id );
		return array(
			'id'       => $attachment_id,
			'checksum' => self::checksum( $attachment_id ),
			'alt'      => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
			'caption'  => $att ? (string) $att->post_excerpt : '',
			'title'    => $att ? (string) $att->post_title : '',
		);
	}

	/**
	 * SHA-256 of an attachment's file (cached by size and modification time).
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	public static function checksum( int $attachment_id ): string {
		$file = get_attached_file( $attachment_id );
		if ( ! $file || ! is_readable( $file ) ) {
			return '';
		}
		$key = $attachment_id . '|' . $file . '|' . (int) filesize( $file ) . '|' . (int) filemtime( $file );
		if ( ! isset( self::$checksums[ $key ] ) ) {
			self::$checksums[ $key ] = (string) hash_file( 'sha256', $file );
		}
		return self::$checksums[ $key ];
	}

	/**
	 * Attachments used by a record (featured image and gallery).
	 *
	 * @param int $post_id Post ID.
	 * @return int[]
	 */
	public static function attachments( int $post_id ): array {
		$ids   = array();
		$thumb = (int) get_post_thumbnail_id( $post_id );
		if ( $thumb ) {
			$ids[] = $thumb;
		}
		$values = Store::get_all( $post_id );
		foreach ( Registry::fields( (string) get_post_type( $post_id ) ) as $key => $def ) {
			if ( 'gallery' === ( $def['kind'] ?? '' ) ) {
				foreach ( (array) ( $values[ $key ] ?? array() ) as $id ) {
					$ids[] = (int) $id;
				}
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Canonicalise: normalise line endings and Unicode, sort associative keys.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	private static function normalise( $value ) {
		if ( is_string( $value ) ) {
			$value = str_replace( array( "\r\n", "\r" ), "\n", $value );
			if ( class_exists( '\Normalizer' ) ) {
				$n     = \Normalizer::normalize( $value, \Normalizer::FORM_C );
				$value = false === $n ? $value : $n;
			}
			return $value;
		}
		if ( is_array( $value ) ) {
			$is_list = array_keys( $value ) === range( 0, count( $value ) - 1 );
			$out     = array();
			foreach ( $value as $k => $v ) {
				$out[ $k ] = self::normalise( $v );
			}
			if ( ! $is_list ) {
				ksort( $out );
			}
			return $out;
		}
		return $value;
	}
}

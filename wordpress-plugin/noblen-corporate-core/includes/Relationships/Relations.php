<?php
namespace Noblen\Core\Relationships;

use Noblen\Core\Content\Types;
use Noblen\Core\Fields\Registry;

defined( 'ABSPATH' ) || exit;

/**
 * Relationship queries. Relationships are stored on the referring record as
 * references to record IDs, never as copies and never as taxonomies. The reverse
 * direction is a query.
 */
final class Relations {

	/**
	 * Records that reference a given record, with the field and its class.
	 *
	 * @param int $post_id Referenced record.
	 * @return array<int,array{id:int,field:string,class:string,type:string}>
	 */
	public static function dependents( int $post_id ): array {
		global $wpdb;
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				"SELECT post_id, meta_key FROM {$wpdb->postmeta} WHERE meta_key LIKE %s AND meta_value = %s",
				$wpdb->esc_like( '_noblen_rel_' ) . '%',
				(string) $post_id
			),
			ARRAY_A
		);
		$out = array();
		foreach ( (array) $rows as $row ) {
			$id   = (int) $row['post_id'];
			$type = (string) get_post_type( $id );
			if ( ! Types::is_ours( $type ) ) {
				continue;
			}
			$field = substr( (string) $row['meta_key'], strlen( '_noblen_rel_' ) );
			$defs  = Registry::relationships( $type );
			if ( ! isset( $defs[ $field ] ) ) {
				continue;
			}
			$out[] = array( 'id' => $id, 'field' => $field, 'class' => (string) $defs[ $field ]['class'], 'type' => $type );
		}
		return $out;
	}

	/**
	 * Records that use an image as the featured image or in a gallery.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return int[]
	 */
	public static function posts_using_attachment( int $attachment_id ): array {
		global $wpdb;
		$like_a = '%[' . $attachment_id . ',%';
		$like_b = '%,' . $attachment_id . ',%';
		$like_c = '%,' . $attachment_id . ']%';
		$like_d = '[' . $attachment_id . ']';
		$ids    = $wpdb->get_col( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				"SELECT DISTINCT post_id FROM {$wpdb->postmeta}
				 WHERE ( meta_key = '_thumbnail_id' AND meta_value = %s )
				    OR ( meta_key = %s AND ( meta_value LIKE %s OR meta_value LIKE %s OR meta_value LIKE %s OR meta_value = %s ) )",
				(string) $attachment_id,
				Registry::meta_key( 'gallery' ),
				$like_a,
				$like_b,
				$like_c,
				$like_d
			)
		);
		return array_values( array_filter( array_map( 'intval', (array) $ids ), static fn( $id ) => Types::is_ours( (string) get_post_type( $id ) ) ) );
	}

	/**
	 * Related records of a field that are valid for public display.
	 *
	 * @param int    $post_id Referring record.
	 * @param string $field   Relationship field key.
	 * @return int[] Valid target IDs in stored order.
	 */
	public static function valid_targets( int $post_id, string $field ): array {
		$ids = array_map( 'intval', (array) get_post_meta( $post_id, Registry::rel_key( $field ), false ) );
		return array_values( array_filter( array_unique( $ids ), array( Dependencies::class, 'is_valid_target' ) ) );
	}
}

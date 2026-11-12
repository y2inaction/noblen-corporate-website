<?php
namespace Noblen\Core\Relationships;

use Noblen\Core\Clearance\ReadCheck;
use Noblen\Core\Content\Types;
use Noblen\Core\Fields\Registry;

defined( 'ABSPATH' ) || exit;

/**
 * Required and optional relationship rules.
 *
 * REQUIRED (the responsible business unit): must be present and valid to
 * publish. Optional relationships never block publication and never unpublish a
 * dependent; they are simply left out of the presentation when unavailable.
 */
final class Dependencies {

	/**
	 * Whether a referenced record is valid for public display: published with a
	 * valid, current approval.
	 *
	 * @param int $id Record ID.
	 */
	public static function is_valid_target( int $id ): bool {
		$p = get_post( $id );
		return $p && Types::is_ours( $p->post_type ) && 'publish' === $p->post_status && ReadCheck::eligible( $p );
	}

	/**
	 * Check the relationships of a record before publication.
	 *
	 * @param int $post_id Post ID.
	 * @return array{failures:string[],warnings:string[]}
	 */
	public static function check_for_publish( int $post_id ): array {
		$type     = (string) get_post_type( $post_id );
		$failures = array();
		$warnings = array();
		foreach ( Registry::relationships( $type ) as $field => $def ) {
			$ids = array_map( 'intval', (array) get_post_meta( $post_id, Registry::rel_key( (string) $field ), false ) );
			if ( 'required' === $def['class'] ) {
				if ( ! $ids ) {
					$failures[] = 'required_relationship_missing:' . $field;
				} elseif ( count( array_filter( $ids, array( self::class, 'is_valid_target' ) ) ) !== count( $ids ) ) {
					$failures[] = 'required_relationship_unavailable:' . $field;
				}
			} else {
				foreach ( $ids as $id ) {
					if ( ! self::is_valid_target( $id ) ) {
						$warnings[] = 'optional_relationship_unavailable:' . $field . ':' . $id;
					}
				}
			}
		}
		return array( 'failures' => $failures, 'warnings' => $warnings );
	}

	/**
	 * Published records that depend on a record through a REQUIRED relationship.
	 *
	 * @param int $post_id Referenced record.
	 * @return int[]
	 */
	public static function required_dependents( int $post_id ): array {
		$out = array();
		foreach ( Relations::dependents( $post_id ) as $d ) {
			if ( 'required' === $d['class'] && 'publish' === get_post_status( $d['id'] ) ) {
				$out[] = $d['id'];
			}
		}
		return array_values( array_unique( $out ) );
	}
}

<?php
namespace Noblen\Core\Clearance;

use Noblen\Core\Content\Types;

defined( 'ABSPATH' ) || exit;

/**
 * Read-time eligibility: a published record is served only when it has an
 * Approved, non-Stale approval whose snapshot equals the content as it is now.
 *
 * This is defence in depth. It is also the detection for changes made outside
 * WordPress's supported write paths (for example direct database edits). It does
 * not replace the save-time gate.
 */
final class ReadCheck {

	/** @var array<int,bool> */
	private static array $memo = array();

	/**
	 * Forget memoised results.
	 *
	 * @param int|null $post_id One record, or null for all.
	 */
	public static function flush( ?int $post_id = null ): void {
		if ( null === $post_id ) {
			self::$memo = array();
		} else {
			unset( self::$memo[ $post_id ] );
		}
	}

	/**
	 * Whether a record may be served publicly.
	 *
	 * @param \WP_Post|int $post Post or ID.
	 */
	public static function eligible( $post ): bool {
		$post = get_post( $post );
		if ( ! $post ) {
			return false;
		}
		if ( ! Types::is_ours( $post->post_type ) ) {
			return true;
		}
		if ( 'publish' !== $post->post_status ) {
			return false;
		}
		if ( isset( self::$memo[ $post->ID ] ) ) {
			return self::$memo[ $post->ID ];
		}
		$a                       = Approvals::latest( (int) $post->ID );
		$ok                      = $a && 'approved' === $a['status'] && hash_equals( (string) $a['snapshot_hash'], Snapshot::hash( (int) $post->ID ) );
		self::$memo[ $post->ID ] = (bool) $ok;
		return (bool) $ok;
	}

	/**
	 * Remove ineligible records from query results.
	 *
	 * @param \WP_Post[] $posts Posts.
	 * @return \WP_Post[]
	 */
	public static function filter_posts( $posts ) {
		if ( ! is_array( $posts ) ) {
			return $posts;
		}
		$out = array();
		foreach ( $posts as $p ) {
			if ( $p instanceof \WP_Post && Types::is_ours( $p->post_type ) && 'publish' === $p->post_status && ! self::eligible( $p ) ) {
				continue;
			}
			$out[] = $p;
		}
		return $out;
	}
}

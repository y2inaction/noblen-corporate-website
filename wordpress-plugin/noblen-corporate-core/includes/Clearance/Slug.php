<?php
namespace Noblen\Core\Clearance;

defined( 'ABSPATH' ) || exit;

/**
 * The public slug that a record has, or will be given on publication.
 *
 * WordPress assigns the slug at the moment of publication and may clear a stored
 * slug on draft and pending records. The slug is part of the public address, so
 * it is part of the approved snapshot. The snapshot therefore uses the slug that
 * WordPress would give the record on publication, computed with WordPress's own
 * rules. It does not depend on whatever is currently stored.
 */
final class Slug {

	/**
	 * The slug a record has or would receive when published.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function effective( int $post_id ): string {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}
		$base = '' !== (string) $post->post_name ? (string) $post->post_name : sanitize_title( (string) $post->post_title );
		if ( '' === $base ) {
			$base = 'record-' . $post_id;
		}
		return (string) wp_unique_post_slug( $base, $post_id, 'publish', $post->post_type, 0 );
	}
}

<?php
namespace Noblen\Core\Clearance;

use Noblen\Core\Content\Types;

defined( 'ABSPATH' ) || exit;

/**
 * Server-side publication gate (rule R1, fail-closed).
 *
 * Every WordPress write path goes through wp_insert_post_data: the editor, quick
 * edit, bulk edit, REST, XML-RPC, WP-CLI, programmatic updates and import. Any
 * write that would leave a gated record publish or future is converted to draft
 * BEFORE it is stored, unless a matching Grant is presented. Because the
 * conversion happens before the write, there is never a moment when unapproved
 * content is public.
 *
 * This filter only edits the incoming data. It never saves a post, so it cannot
 * recurse.
 */
final class Gate {

	/** @var array<int,bool> Records demoted from published by this gate (rule R3). */
	private static array $demoted = array();

	/**
	 * The wp_insert_post_data filter.
	 *
	 * @param array<string,mixed> $data    Slashed, sanitised data about to be stored.
	 * @param array<string,mixed> $postarr Original arguments.
	 * @return array<string,mixed>
	 */
	public static function filter_post_data( $data, $postarr = array() ) {
		if ( ! is_array( $data ) || ! isset( $data['post_type'] ) || ! Types::is_ours( (string) $data['post_type'] ) ) {
			return $data;
		}

		// No password-protected or private records: both blur the public/non-public line.
		$data['post_password'] = '';
		if ( 'private' === $data['post_status'] ) {
			$data['post_status'] = 'draft';
		}

		if ( in_array( $data['post_status'], array( 'publish', 'future' ), true ) ) {
			$id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
			if ( ! ( $id && Grant::authorises( $id ) ) ) {
				$data['post_status'] = 'draft';
				if ( $id ) {
					$existing = get_post( $id );
					if ( $existing && 'publish' === $existing->post_status ) {
						// Rule R3: editing a live record takes it offline until re-approved.
						Approvals::mark_stale( $id, 'edit' );
						self::$demoted[ $id ] = true;
					}
				}
			}
		}
		return $data;
	}

	/**
	 * Whether this gate demoted the record in this request.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function was_demoted( int $post_id ): bool {
		return ! empty( self::$demoted[ $post_id ] );
	}

	/**
	 * Forget a demotion once it has been handled.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function clear_demoted( int $post_id ): void {
		unset( self::$demoted[ $post_id ] );
	}
}

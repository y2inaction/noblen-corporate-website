<?php
namespace Noblen\Core\Clearance;

defined( 'ABSPATH' ) || exit;

/**
 * Scoped, single-use publication grant.
 *
 * Not a global flag. A grant is bound to ONE post ID, ONE approval, ONE snapshot
 * hash and ONE user. It lives only in private in-process memory: never in
 * options, transients, metadata, cookies or request data, and nothing in a
 * request can supply one. The save filter rechecks all four bindings. A grant is
 * consumed by the first matching write.
 */
final class Grant {

	/** @var array{post_id:int,approval_id:int,hash:string,user_id:int}|null */
	private static ?array $active = null;

	/** @var array<int,bool> */
	private static array $consumed = array();

	/**
	 * Issue a grant. Called only from the Publisher, after every check has passed.
	 *
	 * @param int    $post_id     Post ID.
	 * @param int    $approval_id Approval ID.
	 * @param string $hash        Approved snapshot hash.
	 * @param int    $user_id     Authenticated approver.
	 */
	public static function issue( int $post_id, int $approval_id, string $hash, int $user_id ): void {
		self::$active = array(
			'post_id'     => $post_id,
			'approval_id' => $approval_id,
			'hash'        => $hash,
			'user_id'     => $user_id,
		);
	}

	/**
	 * Whether a write to this record is authorised right now. Consumes the grant.
	 *
	 * @param int $post_id Post ID being written.
	 */
	public static function authorises( int $post_id ): bool {
		$g = self::$active;
		if ( ! $g || $g['post_id'] !== $post_id || $g['user_id'] <= 0 || get_current_user_id() !== $g['user_id'] ) {
			return false;
		}
		if ( ! hash_equals( $g['hash'], Snapshot::hash( $post_id ) ) ) {
			return false;
		}
		self::$active               = null; // Single use.
		self::$consumed[ $post_id ] = true;
		return true;
	}

	/**
	 * Whether the publication of a record was authorised by a grant in this request.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function was_consumed( int $post_id ): bool {
		return ! empty( self::$consumed[ $post_id ] );
	}

	/**
	 * Discard everything. Always called in a finally block by the Publisher.
	 */
	public static function clear(): void {
		self::$active   = null;
		self::$consumed = array();
	}

	/**
	 * Whether a grant is waiting to be used.
	 */
	public static function has_active(): bool {
		return null !== self::$active;
	}
}

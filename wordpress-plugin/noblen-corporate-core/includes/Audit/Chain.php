<?php
namespace Noblen\Core\Audit;

defined( 'ABSPATH' ) || exit;

/**
 * Hash chain primitives for the audit log.
 */
final class Chain {

	/** Value of the previous hash for the first entry. */
	public const GENESIS = '0000000000000000000000000000000000000000000000000000000000000000';

	/** Fields covered by each entry's hash, in a fixed order. */
	public const FIELDS = array(
		'post_id',
		'event_type',
		'actor_id',
		'actor_login',
		'event_time',
		'approval_id',
		'snapshot_hash',
		'detail',
		'key_id',
		'key_fingerprint',
	);

	/**
	 * Canonical text of the hashed fields.
	 *
	 * @param array<string,mixed> $row Entry fields.
	 */
	public static function canonical( array $row ): string {
		$out = array();
		foreach ( self::FIELDS as $f ) {
			$out[ $f ] = isset( $row[ $f ] ) ? (string) $row[ $f ] : '';
		}
		return (string) wp_json_encode( $out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	/**
	 * Hash of an entry. Keyed (HMAC) when a secret is supplied, otherwise plain.
	 *
	 * @param string              $secret Key secret or empty string.
	 * @param string              $prev   Previous record hash.
	 * @param array<string,mixed> $row    Entry fields.
	 */
	public static function hash( string $secret, string $prev, array $row ): string {
		$data = $prev . '|' . self::canonical( $row );
		return ( '' === $secret ) ? hash( 'sha256', $data ) : hash_hmac( 'sha256', $data, $secret );
	}
}

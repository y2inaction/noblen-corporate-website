<?php
namespace Noblen\Core\Audit;

defined( 'ABSPATH' ) || exit;

/**
 * Audit key configuration.
 *
 * Keys live in protected site configuration, never in the database or the
 * repository. This class only READS configuration. It contains no code that
 * generates, derives or substitutes a key: the owner provides every key.
 *
 *   define( 'NOBLEN_AUDIT_KEYS', array( 'k1' => '<secret>' ) );
 *   define( 'NOBLEN_AUDIT_KEY_ID', 'k1' );
 */
final class Key {

	/**
	 * All configured keys, by identifier.
	 *
	 * @return array<string,string>
	 */
	public static function all(): array {
		$keys = defined( 'NOBLEN_AUDIT_KEYS' ) ? NOBLEN_AUDIT_KEYS : array();
		$keys = apply_filters( 'noblen_core_audit_keys', is_array( $keys ) ? $keys : array() );
		return array_filter( (array) $keys, 'is_string' );
	}

	/**
	 * Identifier of the key used to sign new entries.
	 */
	public static function current_id(): string {
		$id = defined( 'NOBLEN_AUDIT_KEY_ID' ) ? (string) NOBLEN_AUDIT_KEY_ID : '';
		return (string) apply_filters( 'noblen_core_audit_key_id', $id );
	}

	/**
	 * Secret for an identifier, or an empty string when it is not configured.
	 *
	 * @param string $id Key identifier.
	 */
	public static function get( string $id ): string {
		$all = self::all();
		return ( '' !== $id && isset( $all[ $id ] ) ) ? $all[ $id ] : '';
	}

	/**
	 * Secret of the current key.
	 */
	public static function current_secret(): string {
		return self::get( self::current_id() );
	}

	/**
	 * Whether a usable current key is configured.
	 */
	public static function has_current(): bool {
		return '' !== self::current_id() && '' !== self::current_secret();
	}

	/**
	 * Short fingerprint that identifies a key without revealing it.
	 *
	 * @param string $secret Key secret.
	 */
	public static function fingerprint( string $secret ): string {
		return substr( hash( 'sha256', 'noblen-key-fp:' . $secret ), 0, 16 );
	}
}

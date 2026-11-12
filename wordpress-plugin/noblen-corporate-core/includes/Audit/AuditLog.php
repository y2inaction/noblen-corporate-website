<?php
namespace Noblen\Core\Audit;

use Noblen\Core\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Append-only audit log.
 *
 * Application-level append-only behaviour: this class offers INSERT and SELECT
 * only. There is no update or delete code path and no capability for one. The
 * database account WordPress uses can technically modify rows, so this is NOT
 * immutability against a privileged database administrator.
 */
final class AuditLog {

	/**
	 * Append an entry.
	 *
	 * @param string              $event  Event type.
	 * @param array<string,mixed> $args   Optional post_id, approval_id, snapshot_hash, detail (array), actor_id.
	 * @param string|null         $key_id Explicit key identifier. Null uses the current key, or no key when none is configured.
	 * @return int|false Entry ID or false when it could not be written.
	 * @throws \RuntimeException When an explicit key is not configured.
	 */
	public static function append( string $event, array $args = array(), ?string $key_id = null ) {
		global $wpdb;
		if ( ! Schema::exists( 'audit' ) ) {
			return false;
		}

		if ( null === $key_id ) {
			$key_id = Key::has_current() ? Key::current_id() : '';
		}
		$secret = '';
		if ( '' !== $key_id ) {
			$secret = Key::get( $key_id );
			if ( '' === $secret ) {
				throw new \RuntimeException( 'Audit key is not configured.' );
			}
		}

		$actor_id = isset( $args['actor_id'] ) ? (int) $args['actor_id'] : get_current_user_id();
		$user     = $actor_id ? get_userdata( $actor_id ) : false;

		$row = array(
			'post_id'         => isset( $args['post_id'] ) ? (int) $args['post_id'] : 0,
			'event_type'      => $event,
			'actor_id'        => $actor_id,
			'actor_login'     => $user ? (string) $user->user_login : '',
			'event_time'      => gmdate( 'Y-m-d H:i:s' ),
			'approval_id'     => isset( $args['approval_id'] ) ? (int) $args['approval_id'] : 0,
			'snapshot_hash'   => isset( $args['snapshot_hash'] ) ? (string) $args['snapshot_hash'] : '',
			'detail'          => (string) wp_json_encode( $args['detail'] ?? array(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
			'key_id'          => $key_id,
			'key_fingerprint' => '' === $secret ? '' : Key::fingerprint( $secret ),
		);

		$table    = Schema::table( 'audit' );
		$have_lock = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', 'noblen_audit_chain' ) ); // phpcs:ignore WordPress.DB
		try {
			$last           = self::last();
			$row['prev_hash'] = $last ? (string) $last['record_hash'] : Chain::GENESIS;
			$row['record_hash'] = Chain::hash( $secret, $row['prev_hash'], $row );
			$ok = $wpdb->insert( $table, $row ); // phpcs:ignore WordPress.DB
		} finally {
			if ( $have_lock ) {
				$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', 'noblen_audit_chain' ) ); // phpcs:ignore WordPress.DB
			}
		}
		Verifier::flush_cache();
		return $ok ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Most recent entry.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function last(): ?array {
		global $wpdb;
		$table = Schema::table( 'audit' );
		$row   = $wpdb->get_row( "SELECT * FROM `{$table}` ORDER BY id DESC LIMIT 1", ARRAY_A ); // phpcs:ignore WordPress.DB
		return $row ? $row : null;
	}

	/**
	 * Most recent entry signed with a key.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function last_signed(): ?array {
		global $wpdb;
		$table = Schema::table( 'audit' );
		$row   = $wpdb->get_row( "SELECT * FROM `{$table}` WHERE key_id <> '' ORDER BY id DESC LIMIT 1", ARRAY_A ); // phpcs:ignore WordPress.DB
		return $row ? $row : null;
	}

	/**
	 * All entries in order. Internal use only; no capability check.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function all(): array {
		global $wpdb;
		$table = Schema::table( 'audit' );
		$rows  = $wpdb->get_results( "SELECT * FROM `{$table}` ORDER BY id ASC", ARRAY_A ); // phpcs:ignore WordPress.DB
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Entries filtered by event type. Internal use only.
	 *
	 * @param string $event Event type.
	 * @return array<int,array<string,mixed>>
	 */
	public static function by_event( string $event ): array {
		global $wpdb;
		$table = Schema::table( 'audit' );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE event_type = %s ORDER BY id ASC", $event ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Entries for display. Requires the audit-reading capability.
	 *
	 * @param int $limit Maximum entries (newest first).
	 * @return array<int,array<string,mixed>>|\WP_Error
	 */
	public static function read_entries( int $limit = 100 ) {
		if ( ! current_user_can( 'noblen_view_audit' ) ) {
			return new \WP_Error( 'noblen_forbidden', 'You are not allowed to read the audit log.' );
		}
		global $wpdb;
		$table = Schema::table( 'audit' );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` ORDER BY id DESC LIMIT %d", max( 1, $limit ) ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return is_array( $rows ) ? $rows : array();
	}
}

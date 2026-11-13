<?php
namespace Noblen\Core\Fields;

use Noblen\Core\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Private per-record content epoch.
 *
 * Every plugin-managed cache key includes the epoch, so stale plugin cache
 * entries become unreachable the moment a record is withdrawn, even if deleting
 * them fails.
 */
final class Epochs {

	/**
	 * Current epoch.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function get( int $post_id ): int {
		global $wpdb;
		if ( ! Schema::exists( 'epochs' ) ) {
			return 0;
		}
		$table = Schema::table( 'epochs' );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT epoch FROM `{$table}` WHERE post_id = %d", $post_id ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Increment the epoch.
	 *
	 * @param int $post_id Post ID.
	 * @return int New epoch.
	 */
	public static function bump( int $post_id ): int {
		global $wpdb;
		$table = Schema::table( 'epochs' );
		$wpdb->query( $wpdb->prepare( "INSERT INTO `{$table}` (post_id, epoch) VALUES (%d, 1) ON DUPLICATE KEY UPDATE epoch = epoch + 1", $post_id ) ); // phpcs:ignore WordPress.DB
		return self::get( $post_id );
	}

	/**
	 * Cache key that includes the record's epoch.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $name    Cache entry name.
	 */
	public static function cache_key( int $post_id, string $name ): string {
		return 'noblen:' . $post_id . ':' . self::get( $post_id ) . ':' . $name;
	}
}

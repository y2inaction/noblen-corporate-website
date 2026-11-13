<?php
namespace Noblen\Core\Exposure;

use Noblen\Core\Audit\AuditLog;
use Noblen\Core\Clearance\ReadCheck;
use Noblen\Core\Content\Types;
use Noblen\Core\Fields\Epochs;
use Noblen\Core\Fields\Registry;
use Noblen\Core\Fields\Store;
use Noblen\Core\Relationships\Dependencies;
use Noblen\Core\Relationships\Relations;

defined( 'ABSPATH' ) || exit;

/**
 * The withdrawal procedure, run on every transition into ineligibility.
 *
 * Triggers: T01 revoke, T02 unpublish, T03 rejection after publication,
 * T04 edit of a published record (stale), T05 change outside the normal save
 * path (stale), T06 transition safety net, T07 dependency lapse, T08 trash,
 * T09 permanent delete.
 */
final class Withdrawal {

	/** Reasons that cascade to dependents through REQUIRED relationships. */
	public const CASCADE_REASONS = array( 'revoked', 'rejected', 'unpublished', 'trashed', 'deleted' );

	public const TRIGGERS = array(
		'revoked'           => 'T01',
		'unpublished'       => 'T02',
		'rejected'          => 'T03',
		'stale'             => 'T04',
		'safety_net'        => 'T06',
		'dependency_lapsed' => 'T07',
		'trashed'           => 'T08',
		'deleted'           => 'T09',
	);

	/** @var array<int,bool> */
	private static array $running = array();

	/**
	 * Whether a withdrawal is currently running for a record.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function in_progress( int $post_id ): bool {
		return ! empty( self::$running[ $post_id ] );
	}

	/**
	 * Withdraw a record from public view and clear everything that could still serve it.
	 *
	 * @param int                 $post_id Post ID.
	 * @param string              $reason  Reason code.
	 * @param bool                $cascade Cascade to dependents through required relationships (one level only).
	 * @param string              $trigger Trigger code T01 to T09 (derived from the reason when empty).
	 * @param array<string,mixed> $extra   Extra audit detail.
	 */
	public static function run( int $post_id, string $reason, bool $cascade = true, string $trigger = '', array $extra = array() ): void {
		if ( self::in_progress( $post_id ) ) {
			return;
		}
		$post = get_post( $post_id );
		if ( ! $post || ! Types::is_ours( $post->post_type ) ) {
			return;
		}
		self::$running[ $post_id ] = true;
		try {
			$trigger = '' !== $trigger ? $trigger : ( self::TRIGGERS[ $reason ] ?? '' );

			// Addresses that an external cache may hold, captured while still public.
			$dependents = Relations::dependents( $post_id );
			$urls       = self::purge_urls( $post, $dependents );

			// 1. Status: make the record non-public before anything else.
			if ( in_array( $post->post_status, array( 'publish', 'future' ), true ) && ! in_array( $reason, array( 'trashed', 'deleted' ), true ) ) {
				global $wpdb;
				$wpdb->update( $wpdb->posts, array( 'post_status' => 'draft' ), array( 'ID' => $post_id ) ); // phpcs:ignore WordPress.DB
			}

			// 2. Content epoch: stale plugin cache entries become unreachable.
			Epochs::bump( $post_id );

			// 3. WordPress caches, including a persistent object cache.
			clean_post_cache( $post_id );
			clean_object_term_cache( $post_id, $post->post_type );
			ReadCheck::flush( $post_id );

			// 4. Dependents: their related-content caches rebuild.
			foreach ( $dependents as $d ) {
				Epochs::bump( $d['id'] );
				clean_post_cache( $d['id'] );
				ReadCheck::flush( $d['id'] );
			}

			// 5 and 6. Purge list and hook for external-cache integrations.
			do_action( 'noblen_core_record_withdrawn', $post_id, $reason, $urls );

			// 7. Audit and administrator notice.
			AuditLog::append(
				'record_withdrawn',
				array(
					'post_id' => $post_id,
					'detail'  => array_merge(
						array(
							'reason'     => $reason,
							'trigger'    => $trigger,
							'urls'       => $urls,
							'dependents' => count( $dependents ),
						),
						$extra
					),
				)
			);
			self::add_notice( $post_id, $reason, $urls );

			// Cascade one level only through REQUIRED relationships.
			if ( $cascade && in_array( $reason, self::CASCADE_REASONS, true ) ) {
				foreach ( Dependencies::required_dependents( $post_id ) as $dep_id ) {
					self::run( $dep_id, 'dependency_lapsed', false, 'T07', array( 'caused_by' => $post_id ) );
				}
			}
		} finally {
			unset( self::$running[ $post_id ] );
		}
	}

	/**
	 * Addresses an external cache may hold for a record.
	 *
	 * @param \WP_Post                                  $post       Post.
	 * @param array<int,array{id:int,field:string}>     $dependents Records that reference it.
	 * @return string[]
	 */
	public static function purge_urls( \WP_Post $post, array $dependents ): array {
		$urls   = array();
		$urls[] = (string) get_permalink( $post );
		$arch   = get_post_type_archive_link( $post->post_type );
		if ( $arch ) {
			$urls[] = $arch;
		}
		foreach ( get_object_taxonomies( $post->post_type ) as $tax ) {
			foreach ( (array) wp_get_object_terms( $post->ID, $tax ) as $term ) {
				$link = get_term_link( $term );
				if ( is_string( $link ) ) {
					$urls[] = $link;
				}
			}
		}
		$values = Store::get_all( $post->ID );
		if ( ! empty( $values['featured_home'] ) ) {
			$urls[] = home_url( '/' );
		}
		foreach ( $dependents as $d ) {
			$urls[] = (string) get_permalink( $d['id'] );
		}
		return array_values( array_unique( array_filter( $urls ) ) );
	}

	/**
	 * Keep a short list of withdrawals for the administrator notice.
	 *
	 * @param int      $post_id Post ID.
	 * @param string   $reason  Reason.
	 * @param string[] $urls    URLs to purge from external caches.
	 */
	private static function add_notice( int $post_id, string $reason, array $urls ): void {
		$list   = get_option( 'noblen_core_notices', array() );
		$list   = is_array( $list ) ? $list : array();
		$list[] = array(
			'post_id' => $post_id,
			'reason'  => $reason,
			'urls'    => $urls,
			'time'    => time(),
		);
		update_option( 'noblen_core_notices', array_slice( $list, -20 ), false );
	}
}

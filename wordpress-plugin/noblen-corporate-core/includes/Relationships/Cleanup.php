<?php
namespace Noblen\Core\Relationships;

use Noblen\Core\Audit\AuditLog;
use Noblen\Core\Clearance\Approvals;
use Noblen\Core\Clearance\Invalidation;
use Noblen\Core\Clearance\ReadCheck;
use Noblen\Core\Clearance\Snapshot;
use Noblen\Core\Exposure\Withdrawal;
use Noblen\Core\Fields\Registry;

defined( 'ABSPATH' ) || exit;

/**
 * Clean up references to a permanently deleted record.
 *
 * A dependent that stays live (it referenced the deleted record only through an
 * optional relationship) must not go offline for this. Its snapshot changes
 * because the dangling ID is removed, so its approval is re-based, but ONLY when
 * the content still matched its approval just before the clean-up. The re-base is
 * audited with both hashes. Content that already differs is never legitimised.
 */
final class Cleanup {

	/**
	 * Remove references to a deleted record.
	 *
	 * @param int $deleted_id ID of the deleted record.
	 */
	public static function references( int $deleted_id ): void {
		foreach ( Relations::dependents( $deleted_id ) as $d ) {
			$dep = $d['id'];
			$key = Registry::rel_key( $d['field'] );
			$a   = Approvals::latest( $dep );

			$live = 'publish' === get_post_status( $dep ) && $a && 'approved' === $a['status'];
			if ( ! $live ) {
				Invalidation::suppress( true );
				delete_post_meta( $dep, $key, $deleted_id );
				Invalidation::suppress( false );
				continue;
			}

			$before = Snapshot::hash( $dep );
			if ( ! hash_equals( (string) $a['snapshot_hash'], $before ) ) {
				// Already differs from what was approved: do not legitimise it.
				Approvals::mark_stale( $dep, 'reference_cleanup_mismatch' );
				Withdrawal::run( $dep, 'stale', false, 'T05' );
				continue;
			}

			Invalidation::suppress( true );
			delete_post_meta( $dep, $key, $deleted_id );
			Invalidation::suppress( false );

			$snapshot = Snapshot::compute( $dep );
			Approvals::replace_snapshot( (int) $a['id'], $snapshot );
			update_post_meta( $dep, '_noblen_last_hash', $snapshot['hash'] );
			ReadCheck::flush( $dep );
			AuditLog::append(
				'reference_cleanup_rebaselined',
				array(
					'post_id'       => $dep,
					'approval_id'   => (int) $a['id'],
					'snapshot_hash' => $snapshot['hash'],
					'detail'        => array(
						'removed_reference' => $deleted_id,
						'field'             => $d['field'],
						'previous_hash'     => $before,
					),
				)
			);
		}
	}
}

<?php
namespace Noblen\Core\Clearance;

use Noblen\Core\Audit\AuditLog;
use Noblen\Core\Audit\Verifier;
use Noblen\Core\Content\Types;
use Noblen\Core\Database\Migrator;
use Noblen\Core\Exposure\Withdrawal;
use Noblen\Core\Relationships\Relations;

defined( 'ABSPATH' ) || exit;

/**
 * The one and only authorised way for a gated record to become public.
 */
final class Publisher {

	/**
	 * Publish a record.
	 *
	 * @param int $post_id Post ID.
	 * @return true|\WP_Error
	 */
	public static function publish( int $post_id ) {
		$user_id = get_current_user_id();
		$post    = get_post( $post_id );
		if ( ! $user_id || ! $post || ! Types::is_ours( $post->post_type ) ) {
			return new \WP_Error( 'noblen_forbidden', 'You are not allowed to publish this record.' );
		}
		if ( ! current_user_can( 'noblen_approve_publication' ) || ! current_user_can( 'publish_post', $post_id ) ) {
			return new \WP_Error( 'noblen_forbidden', 'You are not allowed to publish this record.' );
		}
		if ( ! Migrator::is_healthy() ) {
			return new \WP_Error( 'noblen_migration', 'Publication is blocked until the database upgrade is resolved.' );
		}
		$state = Verifier::state();
		if ( 'verified' !== $state ) {
			return new \WP_Error( 'noblen_audit_' . $state, 'Publication is blocked because audit integrity is not established (' . $state . ').' );
		}

		$check = Requirements::check( $post_id, $user_id );
		if ( $check['failures'] ) {
			return new \WP_Error( 'noblen_requirements', 'The requirements for publication are not met.', array( 'failures' => $check['failures'] ) );
		}

		$approval = Approvals::latest( $post_id );
		$hash     = Snapshot::hash( $post_id );
		if ( ! $approval || 'approved' !== $approval['status'] || ! hash_equals( (string) $approval['snapshot_hash'], $hash ) ) {
			return new \WP_Error( 'noblen_requirements', 'The requirements for publication are not met.', array( 'failures' => array( 'approval_stale' ) ) );
		}

		Grant::issue( $post_id, (int) $approval['id'], $hash, $user_id );
		try {
			$result = wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ), true );
		} finally {
			Grant::clear();
		}
		clean_post_cache( $post_id );
		ReadCheck::flush( $post_id );

		if ( is_wp_error( $result ) || 'publish' !== get_post_status( $post_id ) ) {
			return new \WP_Error( 'noblen_publish_failed', 'The record could not be published.' );
		}

		if ( ! ReadCheck::eligible( $post_id ) ) {
			// Something changed what was approved during publication (for example a late address collision).
			Withdrawal::run( $post_id, 'safety_net', false, 'T06' );
			Approvals::mark_stale( $post_id, 'changed_during_publication' );
			return new \WP_Error( 'noblen_publish_failed', 'The content changed during publication. Submit it for approval again.' );
		}

		AuditLog::append(
			'record_published',
			array(
				'post_id'       => $post_id,
				'approval_id'   => (int) $approval['id'],
				'snapshot_hash' => $hash,
				'detail'        => array( 'warnings' => $check['warnings'] ),
			)
		);
		$post = get_post( $post_id );
		do_action( 'noblen_core_record_published', $post_id, Withdrawal::purge_urls( $post, Relations::dependents( $post_id ) ) );
		return true;
	}
}

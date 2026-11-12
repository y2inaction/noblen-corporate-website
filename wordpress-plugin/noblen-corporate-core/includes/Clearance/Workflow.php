<?php
namespace Noblen\Core\Clearance;

use Noblen\Core\Audit\AuditLog;
use Noblen\Core\Audit\Verifier;
use Noblen\Core\Content\Types;
use Noblen\Core\Database\Migrator;
use Noblen\Core\Exposure\Withdrawal;

defined( 'ABSPATH' ) || exit;

/**
 * Approval workflow: submit, approve, reject, revoke, unpublish.
 *
 * Draft -> clearance recorded -> submitted -> approved -> published (via Publisher).
 * Each transition writes an audit entry.
 */
final class Workflow {

	/**
	 * Check a record is one of ours.
	 *
	 * @param int $post_id Post ID.
	 */
	private static function gated( int $post_id ): bool {
		return Types::is_ours( (string) get_post_type( $post_id ) );
	}

	/**
	 * Submit a draft for approval.
	 *
	 * @param int $post_id Post ID.
	 * @return int|\WP_Error Approval ID.
	 */
	public static function submit( int $post_id ) {
		if ( ! self::gated( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error( 'noblen_forbidden', 'You are not allowed to submit this record.' );
		}
		if ( ! in_array( get_post_status( $post_id ), array( 'draft', 'pending', 'auto-draft' ), true ) ) {
			return new \WP_Error( 'noblen_state', 'Only drafts can be submitted for approval.' );
		}
		$failures = Requirements::content_failures( $post_id );
		if ( ! Repository::is_complete( $post_id ) ) {
			$failures[] = 'clearance_incomplete';
		}
		if ( $failures ) {
			return new \WP_Error( 'noblen_requirements', 'The record is not ready to submit.', array( 'failures' => $failures ) );
		}

		Approvals::mark_stale( $post_id, 'resubmitted' );
		$snapshot = Snapshot::compute( $post_id );
		$aid      = Approvals::insert( $post_id, $snapshot, (int) get_post_meta( $post_id, '_noblen_last_editor', true ), (int) Repository::raw( $post_id )['recorded_by'] );
		AuditLog::append( 'approval_submitted', array( 'post_id' => $post_id, 'approval_id' => $aid, 'snapshot_hash' => $snapshot['hash'] ) );

		if ( 'pending' !== get_post_status( $post_id ) ) {
			wp_update_post( array( 'ID' => $post_id, 'post_status' => 'pending' ) );
		}
		return $aid;
	}

	/**
	 * Approve the submitted version.
	 *
	 * @param int $post_id Post ID.
	 * @return int|\WP_Error Approval ID.
	 */
	public static function approve( int $post_id ) {
		$user_id = get_current_user_id();
		if ( ! self::gated( $post_id ) || ! $user_id || ! current_user_can( 'noblen_approve_publication' ) ) {
			return new \WP_Error( 'noblen_forbidden', 'You are not allowed to approve publication.' );
		}
		if ( ! Migrator::is_healthy() ) {
			return new \WP_Error( 'noblen_migration', 'Approval is blocked until the database upgrade is resolved.' );
		}
		$state = Verifier::state();
		if ( 'verified' !== $state ) {
			return new \WP_Error( 'noblen_audit_' . $state, 'Approval is blocked because audit integrity is not established (' . $state . ').' );
		}
		$a = Approvals::latest( $post_id );
		if ( ! $a || 'submitted' !== $a['status'] ) {
			return new \WP_Error( 'noblen_state', 'There is no submitted version to approve.' );
		}
		if ( ! hash_equals( (string) $a['snapshot_hash'], Snapshot::hash( $post_id ) ) ) {
			Approvals::mark_stale( $post_id, 'changed_before_approval' );
			return new \WP_Error( 'noblen_stale', 'The content changed after it was submitted. Submit it again.' );
		}
		if ( ! Repository::is_complete( $post_id ) ) {
			return new \WP_Error( 'noblen_requirements', 'Clearance is incomplete.', array( 'failures' => array( 'clearance_incomplete' ) ) );
		}
		$check = Requirements::check( $post_id, $user_id );
		// Approval needs only the separation rules here; the Publisher checks the rest.
		$sep = array_values( array_intersect( $check['failures'], array( 'separation_last_editor', 'separation_clearance' ) ) );
		if ( $sep ) {
			return new \WP_Error( 'noblen_separation', 'Separation of duties prevents you approving this record.', array( 'failures' => $sep ) );
		}

		Approvals::set_status( (int) $a['id'], 'approved', array( 'approver_id' => $user_id, 'decided_at' => gmdate( 'Y-m-d H:i:s' ) ) );
		ReadCheck::flush( $post_id );
		AuditLog::append( 'approval_approved', array( 'post_id' => $post_id, 'approval_id' => (int) $a['id'], 'snapshot_hash' => (string) $a['snapshot_hash'] ) );
		return (int) $a['id'];
	}

	/**
	 * Approve and publish in one action. Still passes through the Publisher.
	 *
	 * @param int $post_id Post ID.
	 * @return true|\WP_Error
	 */
	public static function approve_and_publish( int $post_id ) {
		$r = self::approve( $post_id );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		return Publisher::publish( $post_id );
	}

	/**
	 * Reject a submitted or approved version.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $note    Note.
	 * @return true|\WP_Error
	 */
	public static function reject( int $post_id, string $note = '' ) {
		if ( ! self::gated( $post_id ) || ! current_user_can( 'noblen_approve_publication' ) ) {
			return new \WP_Error( 'noblen_forbidden', 'You are not allowed to reject.' );
		}
		$a = Approvals::latest( $post_id );
		if ( ! $a || ! in_array( $a['status'], array( 'submitted', 'approved' ), true ) ) {
			return new \WP_Error( 'noblen_state', 'There is nothing to reject.' );
		}
		Approvals::set_status( (int) $a['id'], 'rejected', array( 'approver_id' => get_current_user_id(), 'decided_at' => gmdate( 'Y-m-d H:i:s' ), 'note' => sanitize_textarea_field( $note ) ) );
		ReadCheck::flush( $post_id );
		AuditLog::append( 'approval_rejected', array( 'post_id' => $post_id, 'approval_id' => (int) $a['id'], 'snapshot_hash' => (string) $a['snapshot_hash'] ) );
		if ( 'publish' === get_post_status( $post_id ) ) {
			Withdrawal::run( $post_id, 'rejected', true, 'T03' );
		} elseif ( 'pending' === get_post_status( $post_id ) ) {
			wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ) );
		}
		return true;
	}

	/**
	 * Revoke an approval.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $note    Note.
	 * @return true|\WP_Error
	 */
	public static function revoke( int $post_id, string $note = '' ) {
		if ( ! self::gated( $post_id ) || ! current_user_can( 'noblen_approve_publication' ) ) {
			return new \WP_Error( 'noblen_forbidden', 'You are not allowed to revoke.' );
		}
		$a = Approvals::latest( $post_id );
		if ( ! $a || 'approved' !== $a['status'] ) {
			return new \WP_Error( 'noblen_state', 'There is no approval to revoke.' );
		}
		Approvals::set_status( (int) $a['id'], 'revoked', array( 'decided_at' => gmdate( 'Y-m-d H:i:s' ), 'note' => sanitize_textarea_field( $note ) ) );
		ReadCheck::flush( $post_id );
		AuditLog::append( 'approval_revoked', array( 'post_id' => $post_id, 'approval_id' => (int) $a['id'], 'snapshot_hash' => (string) $a['snapshot_hash'], 'detail' => array( 'reason' => 'revoked' ) ) );
		if ( 'publish' === get_post_status( $post_id ) ) {
			Withdrawal::run( $post_id, 'revoked', true, 'T01' );
		}
		return true;
	}

	/**
	 * Deliberately unpublish a published record. Recorded as a revocation.
	 *
	 * @param int $post_id Post ID.
	 * @return true|\WP_Error
	 */
	public static function unpublish( int $post_id ) {
		if ( ! self::gated( $post_id ) || ! current_user_can( 'noblen_approve_publication' ) ) {
			return new \WP_Error( 'noblen_forbidden', 'You are not allowed to unpublish.' );
		}
		if ( 'publish' !== get_post_status( $post_id ) ) {
			return new \WP_Error( 'noblen_state', 'The record is not published.' );
		}
		$a = Approvals::latest( $post_id );
		if ( $a && 'approved' === $a['status'] ) {
			Approvals::set_status( (int) $a['id'], 'revoked', array( 'decided_at' => gmdate( 'Y-m-d H:i:s' ), 'note' => 'unpublished' ) );
			AuditLog::append( 'approval_revoked', array( 'post_id' => $post_id, 'approval_id' => (int) $a['id'], 'snapshot_hash' => (string) $a['snapshot_hash'], 'detail' => array( 'reason' => 'unpublished' ) ) );
		}
		Withdrawal::run( $post_id, 'unpublished', true, 'T02' );
		return true;
	}
}

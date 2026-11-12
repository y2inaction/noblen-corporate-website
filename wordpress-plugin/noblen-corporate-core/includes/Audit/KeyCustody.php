<?php
namespace Noblen\Core\Audit;

use Noblen\Core\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Explicit audit-key procedures: suspend, rotate, recover after loss.
 *
 * Nothing here runs automatically and nothing here creates a key. A new key is
 * introduced only when the owner has already placed it in site configuration
 * and an administrator performs one of these procedures deliberately.
 * Historical entries are never rewritten, re-signed or re-hashed.
 */
final class KeyCustody {

	/** Phrase an administrator must type to establish a new key after total loss. */
	public const RECOVERY_CONFIRMATION = 'ESTABLISH NEW KEY';

	/**
	 * Whether the current user may run these procedures.
	 */
	private static function can(): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'noblen_approve_publication' );
	}

	/**
	 * Suspend approvals immediately (for example on suspected compromise).
	 *
	 * @param string $reason Reason.
	 * @return true|\WP_Error
	 */
	public static function suspend( string $reason ) {
		if ( ! self::can() ) {
			return new \WP_Error( 'noblen_forbidden', 'Not allowed.' );
		}
		update_option( Verifier::OPT_SUSPENDED, 1, false );
		AuditLog::append( 'approvals_suspended', array( 'detail' => array( 'reason' => $reason ) ) );
		Verifier::flush_cache();
		return true;
	}

	/**
	 * Resume approvals after a suspension.
	 *
	 * @return true|\WP_Error
	 */
	public static function resume() {
		if ( ! self::can() ) {
			return new \WP_Error( 'noblen_forbidden', 'Not allowed.' );
		}
		delete_option( Verifier::OPT_SUSPENDED );
		Verifier::flush_cache();
		AuditLog::append( 'approvals_resumed', array() );
		return true;
	}

	/**
	 * Planned or compromise rotation to a key already placed in configuration.
	 *
	 * @param string              $new_id  Identifier of the new key.
	 * @param string              $reason  "planned" or "compromise".
	 * @param array<string,mixed> $suspect Suspected range (compromise only).
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function rotate( string $new_id, string $reason = 'planned', array $suspect = array() ) {
		if ( ! self::can() ) {
			return new \WP_Error( 'noblen_forbidden', 'Not allowed.' );
		}
		$compromise = ( 'compromise' === $reason );
		$state      = Verifier::state();
		if ( 'verified' !== $state && ! ( $compromise && 'approvals_suspended' === $state ) ) {
			return new \WP_Error( 'noblen_audit_state', 'Audit integrity must be verified before rotation.' );
		}
		$new_secret = Key::get( $new_id );
		if ( '' === $new_secret || $new_id === Key::current_id() ) {
			return new \WP_Error( 'noblen_key', 'The new key must be configured and different from the current key.' );
		}
		$report = Verifier::verify();
		if ( ! $report['ok'] ) {
			return new \WP_Error( 'noblen_audit_state', 'The chain does not verify.' );
		}

		$old_id  = Key::current_id();
		$last    = AuditLog::last();
		$detail  = array(
			'old_key_id'      => $old_id,
			'old_fingerprint' => Key::fingerprint( Key::current_secret() ),
			'new_key_id'      => $new_id,
			'new_fingerprint' => Key::fingerprint( $new_secret ),
			'reason'          => $reason,
			'chain_head'      => $last ? (string) $last['record_hash'] : Chain::GENESIS,
			'suspect_range'   => $suspect,
		);

		// Signed with the OLD key, proving the old key is held.
		$started = (int) AuditLog::append( 'key_rotation_started', array( 'detail' => $detail ), $old_id );

		// Test seam for an interrupted rotation.
		if ( apply_filters( 'noblen_core_rotation_interrupt', false ) ) {
			return array( 'started_id' => $started, 'confirmed_id' => 0, 'complete' => false );
		}

		// Signed with the NEW key, proving both keys are held.
		$confirmed = (int) AuditLog::append(
			'key_rotation_confirmed',
			array( 'detail' => array( 'started_id' => $started, 'new_key_id' => $new_id ) ),
			$new_id
		);
		return array( 'started_id' => $started, 'confirmed_id' => $confirmed, 'complete' => true );
	}

	/**
	 * Complete an interrupted rotation.
	 *
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function complete_rotation() {
		if ( ! self::can() ) {
			return new \WP_Error( 'noblen_forbidden', 'Not allowed.' );
		}
		if ( 'rotation_incomplete' !== Verifier::state() ) {
			return new \WP_Error( 'noblen_audit_state', 'No rotation is waiting to be completed.' );
		}
		$started = AuditLog::by_event( 'key_rotation_started' );
		$last    = end( $started );
		$detail  = json_decode( (string) $last['detail'], true );
		$new_id  = (string) ( $detail['new_key_id'] ?? '' );
		if ( '' === Key::get( $new_id ) ) {
			return new \WP_Error( 'noblen_key', 'The new key is not configured.' );
		}
		$confirmed = (int) AuditLog::append(
			'key_rotation_confirmed',
			array( 'detail' => array( 'started_id' => (int) $last['id'], 'new_key_id' => $new_id ) ),
			$new_id
		);
		return array( 'started_id' => (int) $last['id'], 'confirmed_id' => $confirmed, 'complete' => true );
	}

	/**
	 * Abandon an interrupted rotation.
	 *
	 * @param string $reason Reason.
	 * @return true|\WP_Error
	 */
	public static function abandon_rotation( string $reason ) {
		if ( ! self::can() ) {
			return new \WP_Error( 'noblen_forbidden', 'Not allowed.' );
		}
		if ( 'rotation_incomplete' !== Verifier::state() ) {
			return new \WP_Error( 'noblen_audit_state', 'No rotation is waiting.' );
		}
		AuditLog::append( 'key_rotation_abandoned', array( 'detail' => array( 'reason' => $reason ) ) );
		return true;
	}

	/**
	 * Establish a new key after total loss of the old one.
	 *
	 * The marker is signed ONLY by the new key. It authenticates itself and later
	 * entries and makes no claim about earlier entries. Approvals stay blocked
	 * (state "recovery_incomplete") until complete_recovery() succeeds.
	 *
	 * @param array<string,mixed> $a sign_off_ref, authority_user_id, authority_name, reason,
	 *                               confirmation, last_export (array|null), export_ref.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function recover_after_loss( array $a ) {
		if ( ! self::can() ) {
			return new \WP_Error( 'noblen_forbidden', 'Not allowed.' );
		}
		if ( ( $a['confirmation'] ?? '' ) !== self::RECOVERY_CONFIRMATION ) {
			return new \WP_Error( 'noblen_confirmation', 'The confirmation phrase is required.' );
		}
		$sign_off  = trim( (string) ( $a['sign_off_ref'] ?? '' ) );
		$reason    = trim( (string) ( $a['reason'] ?? '' ) );
		$authority = (int) ( $a['authority_user_id'] ?? 0 );
		$auth_name = trim( (string) ( $a['authority_name'] ?? '' ) );
		if ( '' === $sign_off || '' === $reason || ( ! $authority && '' === $auth_name ) ) {
			return new \WP_Error( 'noblen_authorisation', 'A sign-off reference, a reason and a named recovery authority are required.' );
		}
		if ( Settings::separation_of_duties() && $authority && $authority === get_current_user_id() ) {
			return new \WP_Error( 'noblen_separation', 'The recovery authority must be a different person from the executing administrator.' );
		}
		if ( ! Key::has_current() ) {
			return new \WP_Error( 'noblen_key', 'Configure the new key first. The plugin never creates one.' );
		}
		if ( 'key_invalid' !== Verifier::state() ) {
			return new \WP_Error( 'noblen_audit_state', 'Recovery is only for a lost or unavailable signing key.' );
		}

		$last_signed = AuditLog::last_signed();
		$head        = AuditLog::last();
		$export      = isset( $a['last_export'] ) && is_array( $a['last_export'] ) ? $a['last_export'] : null;
		$comparison  = Export::compare( $export );

		$detail = array(
			'old_key_id'       => $last_signed ? (string) $last_signed['key_id'] : '',
			'old_fingerprint'  => $last_signed ? (string) $last_signed['key_fingerprint'] : '',
			'new_key_id'       => Key::current_id(),
			'new_fingerprint'  => Key::fingerprint( Key::current_secret() ),
			'recovery_authority' => $authority ? $authority : $auth_name,
			'sign_off_ref'     => $sign_off,
			'executing_user'   => get_current_user_id(),
			'timestamp'        => gmdate( 'c' ),
			'db_chain_head'    => $head ? (string) $head['record_hash'] : Chain::GENESIS,
			'export_ref'       => (string) ( $a['export_ref'] ?? '' ),
			'comparison'       => $comparison,
			'reason'           => $reason,
		);
		$marker = (int) AuditLog::append( 'key_recovery_marker', array( 'detail' => $detail ), Key::current_id() );
		update_option( Verifier::OPT_RECOVERY, 'incomplete', false );
		Verifier::flush_cache();
		return array( 'marker_id' => $marker, 'comparison' => $comparison );
	}

	/**
	 * Complete a recovery once the new segment verifies and the owner signs off.
	 *
	 * @param string $completion_ref Owner's recorded completion reference.
	 * @return true|\WP_Error
	 */
	public static function complete_recovery( string $completion_ref ) {
		if ( ! self::can() ) {
			return new \WP_Error( 'noblen_forbidden', 'Not allowed.' );
		}
		if ( 'incomplete' !== get_option( Verifier::OPT_RECOVERY, '' ) ) {
			return new \WP_Error( 'noblen_audit_state', 'No recovery is in progress.' );
		}
		if ( '' === trim( $completion_ref ) ) {
			return new \WP_Error( 'noblen_authorisation', 'The owner completion reference is required.' );
		}
		$markers = AuditLog::by_event( 'key_recovery_marker' );
		$marker  = end( $markers );
		$report  = Verifier::verify( (int) $marker['id'] );
		if ( ! $report['ok'] ) {
			return new \WP_Error( 'noblen_audit_state', 'The new segment does not verify.' );
		}
		AuditLog::append( 'key_recovery_completed', array( 'detail' => array( 'completion_ref' => $completion_ref, 'marker_id' => (int) $marker['id'] ) ) );
		update_option( Verifier::OPT_RECOVERY, 'complete', false );
		Verifier::flush_cache();
		return true;
	}

	/**
	 * Record that an old key was added later as a verification-only key.
	 *
	 * Entries signed by it then verify normally. Nothing is rewritten.
	 *
	 * @param string $key_id Identifier of the key that became available.
	 * @return array<string,int>|\WP_Error Counts by class.
	 */
	public static function note_old_key_available( string $key_id ) {
		if ( ! self::can() ) {
			return new \WP_Error( 'noblen_forbidden', 'Not allowed.' );
		}
		if ( '' === Key::get( $key_id ) ) {
			return new \WP_Error( 'noblen_key', 'That key is not configured.' );
		}
		$counts = array_count_values( Verifier::classify() );
		AuditLog::append( 'old_key_reclassified', array( 'detail' => array( 'key_id' => $key_id, 'classes' => $counts ) ) );
		return $counts;
	}
}

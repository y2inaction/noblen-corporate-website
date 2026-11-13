<?php
namespace Noblen\Core\Cli;

use Noblen\Core\Audit\AuditLog;
use Noblen\Core\Audit\Key;
use Noblen\Core\Audit\KeyCustody;
use Noblen\Core\Audit\Verifier;

defined( 'ABSPATH' ) || exit;

/**
 * Operator commands for audit-key custody: suspend, rotate, recover after loss.
 *
 * Every command delegates to Noblen\Core\Audit\KeyCustody, so the same capability checks, state checks and audit entries
 * apply as for any other caller. Run with --user=<login>. Nothing here creates, derives or prints a key: the owner places
 * keys in wp-config.php first, and these commands only read what is configured. Only key identifiers and short fingerprints
 * are ever shown. Historical entries are never rewritten.
 */
final class KeyCommand {

	/**
	 * Show key custody state: integrity state, configured key identifiers (never secrets) and the recent custody events.
	 *
	 * ## EXAMPLES
	 *
	 *     wp noblen key status
	 *
	 * @param array<int,string>   $args       Positional arguments.
	 * @param array<string,mixed> $assoc_args Named arguments.
	 */
	public function status( $args, $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$rows = array(
			array(
				'item'  => 'integrity_state',
				'value' => Verifier::state(),
			),
			array(
				'item'  => 'current_key_id',
				'value' => Key::has_current() ? Key::current_id() : '(none usable)',
			),
		);
		foreach ( Key::all() as $id => $secret ) {
			$rows[] = array(
				'item'  => 'configured_key:' . $id,
				'value' => 'fingerprint ' . Key::fingerprint( $secret ) . ( Key::current_id() === $id ? ' (signing)' : ' (verification only)' ),
			);
		}
		$events = array( 'approvals_suspended', 'approvals_resumed', 'key_rotation_started', 'key_rotation_confirmed', 'key_rotation_abandoned', 'key_recovery_marker', 'key_recovery_completed', 'old_key_reclassified' );
		foreach ( $events as $event ) {
			$rows[] = array(
				'item'  => 'events:' . $event,
				'value' => (string) count( AuditLog::by_event( $event ) ),
			);
		}
		\WP_CLI\Utils\format_items( 'table', $rows, array( 'item', 'value' ) );
	}

	/**
	 * Suspend approvals immediately, for example on suspected compromise. No confirmation is asked: speed matters.
	 *
	 * ## OPTIONS
	 *
	 * --reason=<text>
	 * : Why approvals are being suspended.
	 *
	 * @param array<int,string>   $args       Positional arguments.
	 * @param array<string,mixed> $assoc_args Named arguments.
	 */
	public function suspend( $args, $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$this->require_value( $assoc_args, 'reason' );
		$this->done( KeyCustody::suspend( (string) $assoc_args['reason'] ), 'Approvals are suspended.' );
	}

	/**
	 * Resume approvals after a suspension.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Do not ask for confirmation.
	 *
	 * @param array<int,string>   $args       Positional arguments.
	 * @param array<string,mixed> $assoc_args Named arguments.
	 */
	public function resume( $args, $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		\WP_CLI::confirm( 'Resume approvals?', $assoc_args );
		$this->done( KeyCustody::resume(), 'Approvals are resumed. Run "wp noblen audit status" to confirm the state.' );
	}

	/**
	 * Rotate to a new key that is ALREADY configured in wp-config.php (NOBLEN_AUDIT_KEYS) while the old key is still the signing key.
	 * The audit trail must verify first. Interrupted rotations are finished with complete-rotation or abandoned.
	 *
	 * ## OPTIONS
	 *
	 * --new-key-id=<id>
	 * : Identifier of the new key. It must be configured and different from the current signing key.
	 *
	 * --confirm-new-key-id=<id>
	 * : Type the same identifier again to confirm.
	 *
	 * [--reason=<reason>]
	 * : planned (default) or compromise.
	 *
	 * [--suspect-from=<id>]
	 * : Compromise only: first audit entry id suspected.
	 *
	 * [--suspect-to=<id>]
	 * : Compromise only: last audit entry id suspected.
	 *
	 * @param array<int,string>   $args       Positional arguments.
	 * @param array<string,mixed> $assoc_args Named arguments.
	 */
	public function rotate( $args, $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$this->require_value( $assoc_args, 'new-key-id' );
		if ( ( $assoc_args['confirm-new-key-id'] ?? '' ) !== $assoc_args['new-key-id'] ) {
			\WP_CLI::error( 'Confirmation failed. Pass --confirm-new-key-id with exactly the same identifier as --new-key-id.' );
		}
		$reason = (string) ( $assoc_args['reason'] ?? 'planned' );
		if ( ! in_array( $reason, array( 'planned', 'compromise' ), true ) ) {
			\WP_CLI::error( '--reason must be "planned" or "compromise".' );
		}
		$suspect = array();
		if ( 'compromise' === $reason ) {
			$suspect = array(
				'from_id' => isset( $assoc_args['suspect-from'] ) ? (int) $assoc_args['suspect-from'] : null,
				'to_id'   => isset( $assoc_args['suspect-to'] ) ? (int) $assoc_args['suspect-to'] : null,
			);
		}
		$result = KeyCustody::rotate( (string) $assoc_args['new-key-id'], $reason, $suspect );
		$this->fail_on_error( $result );
		if ( empty( $result['complete'] ) ) {
			\WP_CLI::warning( sprintf( 'Rotation started (entry %d) but not confirmed. Run "wp noblen key complete-rotation" after the new key is the configured signing key, or "wp noblen key abandon-rotation".', (int) $result['started_id'] ) );
			return;
		}
		\WP_CLI::success( sprintf( 'Rotation recorded (started %d, confirmed %d). Now set NOBLEN_AUDIT_KEY_ID in wp-config.php to the new key identifier so that new entries are signed with it, and keep the old key as a verification key.', (int) $result['started_id'], (int) $result['confirmed_id'] ) );
	}

	/**
	 * Complete an interrupted rotation (state rotation_incomplete). The new key must be configured.
	 *
	 * @subcommand complete-rotation
	 *
	 * @param array<int,string>   $args       Positional arguments.
	 * @param array<string,mixed> $assoc_args Named arguments.
	 */
	public function complete_rotation( $args, $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$result = KeyCustody::complete_rotation();
		$this->fail_on_error( $result );
		\WP_CLI::success( sprintf( 'Rotation completed (confirmed entry %d).', (int) $result['confirmed_id'] ) );
	}

	/**
	 * Abandon an interrupted rotation (state rotation_incomplete).
	 *
	 * ## OPTIONS
	 *
	 * --reason=<text>
	 * : Why the rotation is abandoned.
	 *
	 * [--yes]
	 * : Do not ask for confirmation.
	 *
	 * @subcommand abandon-rotation
	 *
	 * @param array<int,string>   $args       Positional arguments.
	 * @param array<string,mixed> $assoc_args Named arguments.
	 */
	public function abandon_rotation( $args, $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$this->require_value( $assoc_args, 'reason' );
		\WP_CLI::confirm( 'Abandon the interrupted rotation?', $assoc_args );
		$this->done( KeyCustody::abandon_rotation( (string) $assoc_args['reason'] ), 'The rotation is abandoned.' );
	}

	/**
	 * Establish a NEW key after total loss of the old one. The new key must already be configured as the signing key in wp-config.php,
	 * the integrity state must be key_invalid, and a recovery authority other than the executing user must be named. Approvals stay
	 * blocked (recovery_incomplete) until complete-recovery. Earlier entries are never rewritten or re-signed.
	 *
	 * ## OPTIONS
	 *
	 * --sign-off-ref=<ref>
	 * : Reference to the owner's written sign-off for this recovery (a ticket or document id, no personal data).
	 *
	 * --reason=<text>
	 * : Why the old key cannot be used.
	 *
	 * [--authority-user=<login-or-id>]
	 * : The recovery authority, a different user from the one running this command.
	 *
	 * [--authority-name=<name>]
	 * : The recovery authority as a role or name, when that person has no account.
	 *
	 * --confirm=<phrase>
	 * : Type exactly: ESTABLISH NEW KEY
	 *
	 * [--last-export=<file>]
	 * : An earlier audit export (JSON) to compare with the database, for example from a backup.
	 *
	 * [--export-ref=<ref>]
	 * : Where that export is kept.
	 *
	 * @param array<int,string>   $args       Positional arguments.
	 * @param array<string,mixed> $assoc_args Named arguments.
	 */
	public function recover( $args, $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$this->require_value( $assoc_args, 'sign-off-ref' );
		$this->require_value( $assoc_args, 'reason' );
		$authority_id = 0;
		if ( ! empty( $assoc_args['authority-user'] ) ) {
			$user = get_user_by( 'login', (string) $assoc_args['authority-user'] );
			if ( ! $user && ctype_digit( (string) $assoc_args['authority-user'] ) ) {
				$user = get_user_by( 'id', (int) $assoc_args['authority-user'] );
			}
			if ( ! $user ) {
				\WP_CLI::error( 'The recovery authority user was not found.' );
			}
			$authority_id = (int) $user->ID;
		}
		$export = null;
		if ( ! empty( $assoc_args['last-export'] ) ) {
			$path = (string) $assoc_args['last-export'];
			if ( ! is_readable( $path ) ) {
				\WP_CLI::error( 'The export file cannot be read.' );
			}
			$decoded = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( ! is_array( $decoded ) ) {
				\WP_CLI::error( 'The export file is not valid JSON.' );
			}
			$export = $decoded;
		}
		$result = KeyCustody::recover_after_loss(
			array(
				'sign_off_ref'      => (string) $assoc_args['sign-off-ref'],
				'reason'            => (string) $assoc_args['reason'],
				'authority_user_id' => $authority_id,
				'authority_name'    => (string) ( $assoc_args['authority-name'] ?? '' ),
				'confirmation'      => (string) ( $assoc_args['confirm'] ?? '' ),
				'last_export'       => $export,
				'export_ref'        => (string) ( $assoc_args['export-ref'] ?? '' ),
			)
		);
		$this->fail_on_error( $result );
		\WP_CLI::success( sprintf( 'Recovery marker %d written. Approvals stay blocked until "wp noblen key complete-recovery". Comparison with the supplied export: %s', (int) $result['marker_id'], (string) wp_json_encode( $result['comparison'] ) ) );
	}

	/**
	 * Complete a recovery once the new segment verifies and the owner has signed off (state recovery_incomplete).
	 *
	 * ## OPTIONS
	 *
	 * --completion-ref=<ref>
	 * : Reference to the owner's recorded completion sign-off.
	 *
	 * @subcommand complete-recovery
	 *
	 * @param array<int,string>   $args       Positional arguments.
	 * @param array<string,mixed> $assoc_args Named arguments.
	 */
	public function complete_recovery( $args, $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$this->require_value( $assoc_args, 'completion-ref' );
		$this->done( KeyCustody::complete_recovery( (string) $assoc_args['completion-ref'] ), 'Recovery completed. Run "wp noblen audit status".' );
	}

	/**
	 * Record that an OLD key has become available again and is configured as a verification-only key. Nothing is rewritten.
	 *
	 * ## OPTIONS
	 *
	 * --key-id=<id>
	 * : Identifier of the configured old key.
	 *
	 * @subcommand old-key-available
	 *
	 * @param array<int,string>   $args       Positional arguments.
	 * @param array<string,mixed> $assoc_args Named arguments.
	 */
	public function old_key_available( $args, $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$this->require_value( $assoc_args, 'key-id' );
		$counts = KeyCustody::note_old_key_available( (string) $assoc_args['key-id'] );
		$this->fail_on_error( $counts );
		\WP_CLI::success( 'Recorded. Entries by class: ' . wp_json_encode( $counts ) );
	}

	/**
	 * Stop with a clear message when a required option is missing or empty.
	 *
	 * @param array<string,mixed> $assoc_args Named arguments.
	 * @param string              $name       Option name.
	 */
	private function require_value( array $assoc_args, string $name ): void {
		if ( ! isset( $assoc_args[ $name ] ) || ! is_string( $assoc_args[ $name ] ) || '' === trim( $assoc_args[ $name ] ) ) {
			\WP_CLI::error( sprintf( 'The option --%s is required.', $name ) );
		}
	}

	/**
	 * Stop when a custody call returned an error.
	 *
	 * @param mixed $result Result from KeyCustody.
	 */
	private function fail_on_error( $result ): void {
		if ( is_wp_error( $result ) ) {
			\WP_CLI::error( $result->get_error_message() . ( 'noblen_forbidden' === $result->get_error_code() ? ' Use --user=<login> with an administrator or Publication Approver.' : '' ) );
		}
	}

	/**
	 * Report success for a call that returns true or an error.
	 *
	 * @param mixed  $result  Result from KeyCustody.
	 * @param string $message Success message.
	 */
	private function done( $result, string $message ): void {
		$this->fail_on_error( $result );
		\WP_CLI::success( $message );
	}
}

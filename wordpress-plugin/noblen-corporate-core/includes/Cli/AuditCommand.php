<?php
namespace Noblen\Core\Cli;

use Noblen\Core\Audit\AuditLog;
use Noblen\Core\Audit\Export;
use Noblen\Core\Audit\Key;
use Noblen\Core\Audit\Verifier;

defined( 'ABSPATH' ) || exit;

/**
 * Operator commands for the audit trail. Read-only. Run with --user=<login> so the same capability checks as the website apply.
 * Secrets are never printed: only the key identifier and a fingerprint.
 */
final class AuditCommand {

	/**
	 * Show the audit status: integrity state, configured key identifier and fingerprint, and a full chain verification.
	 *
	 * ## EXAMPLES
	 *
	 *     wp noblen audit status
	 *
	 * @param array<int,string>   $args       Positional arguments.
	 * @param array<string,mixed> $assoc_args Named arguments.
	 */
	public function status( $args, $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$report = Verifier::verify();
		$rows   = array(
			array(
				'item'  => 'integrity_state',
				'value' => Verifier::state(),
			),
			array(
				'item'  => 'key_configured',
				'value' => Key::has_current() ? 'yes' : 'no',
			),
			array(
				'item'  => 'key_id',
				'value' => Key::has_current() ? Key::current_id() : '',
			),
			array(
				'item'  => 'key_fingerprint',
				'value' => Key::has_current() ? Key::fingerprint( Key::current_secret() ) : '',
			),
			array(
				'item'  => 'entries',
				'value' => (string) $report['entries'],
			),
			array(
				'item'  => 'verified',
				'value' => (string) $report['verified'],
			),
			array(
				'item'  => 'unsigned',
				'value' => (string) $report['unsigned'],
			),
			array(
				'item'  => 'unverifiable',
				'value' => (string) count( (array) $report['unverifiable'] ),
			),
			array(
				'item'  => 'chain_ok',
				'value' => $report['ok'] ? 'yes' : 'no',
			),
			array(
				'item'  => 'break_id',
				'value' => null === $report['break_id'] ? '' : (string) $report['break_id'],
			),
			array(
				'item'  => 'break_reason',
				'value' => (string) $report['break_reason'],
			),
		);
		\WP_CLI\Utils\format_items( 'table', $rows, array( 'item', 'value' ) );
	}

	/**
	 * List audit entries, newest first. Needs a user who may read the audit log (for example --user=<approver>).
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<n>]
	 * : Maximum entries. Default 20.
	 *
	 * [--format=<format>]
	 * : table, json or csv. Default table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp noblen audit list --user=approver1 --limit=10
	 *
	 * @param array<int,string>   $args       Positional arguments.
	 * @param array<string,mixed> $assoc_args Named arguments.
	 * @subcommand list
	 */
	public function list_entries( $args, $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$limit   = max( 1, min( 1000, (int) ( $assoc_args['limit'] ?? 20 ) ) );
		$format  = in_array( $assoc_args['format'] ?? 'table', array( 'table', 'json', 'csv' ), true ) ? (string) ( $assoc_args['format'] ?? 'table' ) : 'table';
		$entries = AuditLog::read_entries( $limit );
		if ( is_wp_error( $entries ) ) {
			\WP_CLI::error( $entries->get_error_message() . ' Use --user=<login> with a user who may read the audit log.' );
		}
		$rows = array();
		foreach ( $entries as $e ) {
			$rows[] = array(
				'id'          => (int) $e['id'],
				'event_time'  => (string) $e['event_time'],
				'event_type'  => (string) $e['event_type'],
				'actor'       => (string) $e['actor_login'],
				'post_id'     => (int) $e['post_id'],
				'approval_id' => (int) $e['approval_id'],
				'key_id'      => (string) $e['key_id'],
			);
		}
		\WP_CLI\Utils\format_items( $format, $rows, array( 'id', 'event_time', 'event_type', 'actor', 'post_id', 'approval_id', 'key_id' ) );
	}

	/**
	 * Export the whole audit chain as JSON, including the chain head value, for independent custody. Needs a user who may read the audit log.
	 *
	 * ## OPTIONS
	 *
	 * [--file=<path>]
	 * : Write to this new file instead of standard output. An existing file is never overwritten.
	 *
	 * ## EXAMPLES
	 *
	 *     wp noblen audit export --user=approver1 --file=/secure/location/audit-export.json
	 *
	 * @param array<int,string>   $args       Positional arguments.
	 * @param array<string,mixed> $assoc_args Named arguments.
	 */
	public function export( $args, $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$export = Export::for_user();
		if ( is_wp_error( $export ) ) {
			\WP_CLI::error( $export->get_error_message() . ' Use --user=<login> with a user who may read the audit log.' );
		}
		$json = (string) wp_json_encode( $export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( empty( $assoc_args['file'] ) ) {
			\WP_CLI::line( $json );
			return;
		}
		$file = (string) $assoc_args['file'];
		if ( file_exists( $file ) ) {
			\WP_CLI::error( 'The file already exists and is never overwritten. Choose a new file name.' );
		}
		if ( false === file_put_contents( $file, $json . "\n", LOCK_EX ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			\WP_CLI::error( 'The export could not be written.' );
		}
		\WP_CLI::success( sprintf( 'Exported %d entries. Chain head: %s', (int) $export['entry_count'], $export['chain_head'] ) );
	}
}

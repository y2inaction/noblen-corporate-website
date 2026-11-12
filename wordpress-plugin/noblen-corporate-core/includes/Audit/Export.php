<?php
namespace Noblen\Core\Audit;

defined( 'ABSPATH' ) || exit;

/**
 * Audit export and comparison with an earlier external export.
 */
final class Export {

	/**
	 * Build an export of the whole audit log, including the chain value.
	 *
	 * @return array<string,mixed>
	 */
	public static function build(): array {
		$entries = AuditLog::all();
		$last    = $entries ? end( $entries ) : null;
		return array(
			'format'       => 1,
			'generated_at' => gmdate( 'c' ),
			'entry_count'  => count( $entries ),
			'chain_head'   => $last ? (string) $last['record_hash'] : Chain::GENESIS,
			'entries'      => $entries,
		);
	}

	/**
	 * Export for a user. Requires the audit-reading capability.
	 *
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function for_user() {
		if ( ! current_user_can( 'noblen_view_audit' ) ) {
			return new \WP_Error( 'noblen_forbidden', 'You are not allowed to export the audit log.' );
		}
		return self::build();
	}

	/**
	 * Compare the database with an earlier export.
	 *
	 * @param array<string,mixed>|null $export Earlier export.
	 * @return array{result:string,first_difference_id:?int,compared:int}
	 */
	public static function compare( ?array $export ): array {
		if ( ! is_array( $export ) || empty( $export['entries'] ) ) {
			return array(
				'result'              => 'no_export',
				'first_difference_id' => null,
				'compared'            => 0,
			);
		}
		$db = array();
		foreach ( AuditLog::all() as $row ) {
			$db[ (int) $row['id'] ] = $row;
		}
		$compared = 0;
		foreach ( $export['entries'] as $row ) {
			$id = (int) $row['id'];
			if ( ! isset( $db[ $id ] ) || ! hash_equals( (string) $db[ $id ]['record_hash'], (string) $row['record_hash'] ) ) {
				return array(
					'result'              => 'differs',
					'first_difference_id' => $id,
					'compared'            => $compared,
				);
			}
			++$compared;
		}
		return array(
			'result'              => 'matches_up_to_export_point',
			'first_difference_id' => null,
			'compared'            => $compared,
		);
	}
}

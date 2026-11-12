<?php
namespace Noblen\Core\Clearance;

use Noblen\Core\Audit\AuditLog;
use Noblen\Core\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Private approval records (data access only; the workflow is in Workflow).
 *
 * Approval status values: submitted, approved, rejected, revoked, stale.
 */
final class Approvals {

	/**
	 * Most recent approval row for a record.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string,mixed>|null
	 */
	public static function latest( int $post_id ): ?array {
		global $wpdb;
		if ( ! Schema::exists( 'approvals' ) ) {
			return null;
		}
		$table = Schema::table( 'approvals' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE post_id = %d ORDER BY id DESC LIMIT 1", $post_id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return $row ? $row : null;
	}

	/**
	 * Approval row by ID.
	 *
	 * @param int $id Approval ID.
	 * @return array<string,mixed>|null
	 */
	public static function get( int $id ): ?array {
		global $wpdb;
		$table = Schema::table( 'approvals' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return $row ? $row : null;
	}

	/**
	 * Insert a submitted approval carrying the snapshot.
	 *
	 * @param int                  $post_id      Post ID.
	 * @param array<string,mixed>  $snapshot     Result of Snapshot::compute().
	 * @param int                  $last_editor  Last editor user ID.
	 * @param int                  $clearance_by User who recorded clearance.
	 * @return int Approval ID.
	 */
	public static function insert( int $post_id, array $snapshot, int $last_editor, int $clearance_by ): int {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB
			Schema::table( 'approvals' ),
			array(
				'post_id'        => $post_id,
				'status'         => 'submitted',
				'snapshot_hash'  => $snapshot['hash'],
				'format_version' => $snapshot['format'],
				'snapshot_text'  => $snapshot['text'],
				'last_editor_id' => $last_editor,
				'clearance_by'   => $clearance_by,
				'created_at'     => gmdate( 'Y-m-d H:i:s' ),
			)
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Change the status of an approval row.
	 *
	 * @param int                 $id     Approval ID.
	 * @param string              $status New status.
	 * @param array<string,mixed> $extra  Extra columns (approver_id, decided_at, note).
	 */
	public static function set_status( int $id, string $status, array $extra = array() ): void {
		global $wpdb;
		$wpdb->update( Schema::table( 'approvals' ), array_merge( array( 'status' => $status ), $extra ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Replace the snapshot of an approval (used only by audited reference clean-up).
	 *
	 * @param int                 $id       Approval ID.
	 * @param array<string,mixed> $snapshot Snapshot.
	 */
	public static function replace_snapshot( int $id, array $snapshot ): void {
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB
			Schema::table( 'approvals' ),
			array(
				'snapshot_hash'  => $snapshot['hash'],
				'snapshot_text'  => $snapshot['text'],
				'format_version' => $snapshot['format'],
			),
			array( 'id' => $id )
		);
	}

	/**
	 * Mark the live approval of a record Stale.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $reason  Reason code.
	 */
	public static function mark_stale( int $post_id, string $reason = 'edit' ): void {
		$a = self::latest( $post_id );
		if ( ! $a || ! in_array( $a['status'], array( 'submitted', 'approved' ), true ) ) {
			return;
		}
		self::set_status( (int) $a['id'], 'stale' );
		AuditLog::append(
			'approval_stale',
			array(
				'post_id'       => $post_id,
				'approval_id'   => (int) $a['id'],
				'snapshot_hash' => (string) $a['snapshot_hash'],
				'detail'        => array( 'reason' => $reason ),
			)
		);
		ReadCheck::flush();
	}
}

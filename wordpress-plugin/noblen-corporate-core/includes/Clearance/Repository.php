<?php
namespace Noblen\Core\Clearance;

use Noblen\Core\Audit\AuditLog;
use Noblen\Core\Content\Types;
use Noblen\Core\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Private clearance records. Every read and write is capability-checked here.
 *
 * Clearance data lives in a dedicated table, never in post meta, so the usual
 * WordPress read paths (queries, REST, feeds, exports, attachments) cannot reach
 * it. Evidence is a short reference to where the owner holds a document. No
 * evidence file is stored on the website.
 */
final class Repository {

	/**
	 * Checklist items required for a type. Value: whether "not applicable" is allowed.
	 *
	 * @param string $type Post type.
	 * @return array<string,bool>
	 */
	public static function items_for_type( string $type ): array {
		if ( in_array( $type, array( Types::PROJECT, Types::PRODUCT ), true ) ) {
			return array(
				'client_consent'        => false,
				'logo_permission'       => true,
				'image_release'         => true,
				'confidentiality_check' => false,
			);
		}
		return array( 'content_reviewed' => false );
	}

	/**
	 * Whether evidence is required for the type.
	 *
	 * @param string $type Post type.
	 */
	public static function evidence_required( string $type ): bool {
		return in_array( $type, array( Types::PROJECT, Types::PRODUCT ), true );
	}

	/**
	 * Raw record (internal use; no capability check).
	 *
	 * @param int $post_id Post ID.
	 * @return array{status:string,items:array<string,string>,evidence_ref:string,recorded_by:int,updated_at:string}
	 */
	public static function raw( int $post_id ): array {
		global $wpdb;
		$empty = array(
			'status'       => 'incomplete',
			'items'        => array(),
			'evidence_ref' => '',
			'recorded_by'  => 0,
			'updated_at'   => '',
		);
		if ( ! Schema::exists( 'clearance' ) ) {
			return $empty;
		}
		$table = Schema::table( 'clearance' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE post_id = %d", $post_id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		if ( ! $row ) {
			return $empty;
		}
		$items = json_decode( (string) $row['items'], true );
		return array(
			'status'       => (string) $row['status'],
			'items'        => is_array( $items ) ? $items : array(),
			'evidence_ref' => (string) $row['evidence_ref'],
			'recorded_by'  => (int) $row['recorded_by'],
			'updated_at'   => (string) $row['updated_at'],
		);
	}

	/**
	 * Whether clearance is complete. Anyone may ask; the detail stays private.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function is_complete( int $post_id ): bool {
		$type = (string) get_post_type( $post_id );
		$rec  = self::raw( $post_id );
		return self::evaluate( $type, $rec['items'], $rec['evidence_ref'] );
	}

	/**
	 * Evaluate completeness of items and evidence for a type.
	 *
	 * @param string               $type     Post type.
	 * @param array<string,string> $items    Items.
	 * @param string               $evidence Evidence reference.
	 */
	private static function evaluate( string $type, array $items, string $evidence ): bool {
		foreach ( self::items_for_type( $type ) as $item => $na_allowed ) {
			$v = $items[ $item ] ?? '';
			if ( 'yes' !== $v && ! ( $na_allowed && 'na' === $v ) ) {
				return false;
			}
		}
		return ! self::evidence_required( $type ) || '' !== trim( $evidence );
	}

	/**
	 * Full record for an authorised user.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function get( int $post_id ) {
		if ( ! current_user_can( 'noblen_manage_clearance' ) && ! current_user_can( 'noblen_approve_publication' ) ) {
			return new \WP_Error( 'noblen_forbidden', 'You are not allowed to read clearance records.' );
		}
		return self::raw( $post_id );
	}

	/**
	 * Complete or incomplete, for users who may not see the detail.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function indicator( int $post_id ): string {
		return self::is_complete( $post_id ) ? 'complete' : 'incomplete';
	}

	/**
	 * Save clearance for a record.
	 *
	 * @param int                  $post_id      Post ID.
	 * @param array<string,string> $items        Checklist values: yes, na or empty.
	 * @param string               $evidence_ref Reference to where evidence is held.
	 * @return true|\WP_Error
	 */
	public static function save( int $post_id, array $items, string $evidence_ref ) {
		global $wpdb;
		if ( ! current_user_can( 'noblen_manage_clearance' ) ) {
			return new \WP_Error( 'noblen_forbidden', 'You are not allowed to record clearance.' );
		}
		$type = (string) get_post_type( $post_id );
		if ( ! Types::is_ours( $type ) ) {
			return new \WP_Error( 'noblen_type', 'Not a Noblen content record.' );
		}
		$clean = array();
		foreach ( array_keys( self::items_for_type( $type ) ) as $item ) {
			$v              = isset( $items[ $item ] ) ? (string) $items[ $item ] : '';
			$clean[ $item ] = in_array( $v, array( 'yes', 'na' ), true ) ? $v : '';
		}
		$evidence = sanitize_text_field( $evidence_ref );
		$status   = self::evaluate( $type, $clean, $evidence ) ? 'complete' : 'incomplete';

		$wpdb->replace( // phpcs:ignore WordPress.DB
			Schema::table( 'clearance' ),
			array(
				'post_id'      => $post_id,
				'status'       => $status,
				'items'        => (string) wp_json_encode( $clean ),
				'evidence_ref' => $evidence,
				'recorded_by'  => get_current_user_id(),
				'updated_at'   => gmdate( 'Y-m-d H:i:s' ),
			)
		);
		AuditLog::append(
			'clearance_recorded',
			array(
				'post_id' => $post_id,
				'detail'  => array( 'status' => $status ),
			)
		);
		return true;
	}
}

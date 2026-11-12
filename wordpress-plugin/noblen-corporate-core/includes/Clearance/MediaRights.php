<?php
namespace Noblen\Core\Clearance;

use Noblen\Core\Audit\AuditLog;
use Noblen\Core\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Private per-image rights confirmations, bound to the file checksum.
 *
 * Replacing a file changes its checksum, which voids the confirmation, so the
 * rights must be confirmed again. Confirmations are never post meta or
 * attachment data.
 */
final class MediaRights {

	/**
	 * Confirm publication rights for an image as it currently exists.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $basis_ref     Reference to where the rights evidence is held.
	 * @return true|\WP_Error
	 */
	public static function confirm( int $attachment_id, string $basis_ref ) {
		global $wpdb;
		if ( ! current_user_can( 'noblen_manage_clearance' ) ) {
			return new \WP_Error( 'noblen_forbidden', 'You are not allowed to confirm media rights.' );
		}
		if ( '' === trim( $basis_ref ) ) {
			return new \WP_Error( 'noblen_basis', 'A reference to the rights evidence is required.' );
		}
		$checksum = Snapshot::checksum( $attachment_id );
		if ( '' === $checksum || ! wp_attachment_is_image( $attachment_id ) ) {
			return new \WP_Error( 'noblen_media', 'The attachment is not a readable image.' );
		}
		$wpdb->replace( // phpcs:ignore WordPress.DB
			Schema::table( 'media_rights' ),
			array(
				'attachment_id' => $attachment_id,
				'file_checksum' => $checksum,
				'basis_ref'     => sanitize_text_field( $basis_ref ),
				'confirmed_by'  => get_current_user_id(),
				'confirmed_at'  => gmdate( 'Y-m-d H:i:s' ),
			)
		);
		AuditLog::append( 'media_rights_confirmed', array( 'detail' => array( 'attachment_id' => $attachment_id ) ) );
		return true;
	}

	/**
	 * Whether the image, in its current state, has a rights confirmation.
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	public static function is_confirmed( int $attachment_id ): bool {
		global $wpdb;
		$checksum = Snapshot::checksum( $attachment_id );
		if ( '' === $checksum || ! Schema::exists( 'media_rights' ) ) {
			return false;
		}
		$table = Schema::table( 'media_rights' );
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM `{$table}` WHERE attachment_id = %d AND file_checksum = %s", $attachment_id, $checksum ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Images of a record that lack a current confirmation.
	 *
	 * @param int $post_id Post ID.
	 * @return int[]
	 */
	public static function unconfirmed( int $post_id ): array {
		return array_values( array_filter( Snapshot::attachments( $post_id ), static fn( $id ) => ! self::is_confirmed( (int) $id ) ) );
	}
}

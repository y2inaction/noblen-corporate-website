<?php
namespace Noblen\Core\Seed;

use Noblen\Core\Content\Types;

defined( 'ABSPATH' ) || exit;

/**
 * Optional starter drafts: names only.
 *
 * Every record is created as a draft with no content, no clearance and no approval.
 * Names come from the owner's approved naming. Nothing here can become public without
 * the normal clearance and approval workflow.
 */
final class DraftSeeder {

	/** Business units named in the owner's brief. */
	public const COMPANIES = array( 'Noblen AI Solutions', 'Noblen Media Services', 'Noblen Property Limited' );

	/** Products and platforms named in the owner's brief. Their status is unverified, so they stay drafts. */
	public const PRODUCTS = array( 'CIDU', 'OnlineMart', 'ArewaGo', 'Noblen AI Platform' );

	/**
	 * Create any missing starter drafts. Idempotent.
	 *
	 * @return int Number of drafts created.
	 */
	public static function seed(): int {
		if ( ! current_user_can( 'noblen_manage_settings' ) ) {
			return 0;
		}
		$created = 0;
		foreach ( array(
			Types::COMPANY => self::COMPANIES,
			Types::PRODUCT => self::PRODUCTS,
		) as $type => $names ) {
			foreach ( $names as $name ) {
				if ( self::exists( $type, $name ) ) {
					continue;
				}
				$id = wp_insert_post(
					array(
						'post_type'   => $type,
						'post_title'  => $name,
						'post_status' => 'draft',
					)
				);
				if ( $id && ! is_wp_error( $id ) ) {
					++$created;
				}
			}
		}
		return $created;
	}

	/**
	 * Whether a record with the title exists in any non-trashed status.
	 *
	 * @param string $type  Post type.
	 * @param string $title Title.
	 */
	private static function exists( string $type, string $title ): bool {
		$found = get_posts(
			array(
				'post_type'      => $type,
				'post_status'    => array( 'publish', 'pending', 'draft', 'future', 'private' ),
				'title'          => $title,
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);
		return ! empty( $found );
	}
}

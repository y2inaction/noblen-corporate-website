<?php
namespace Noblen\Core\Clearance;

use Noblen\Core\Audit\Key;
use Noblen\Core\Content\Types;
use Noblen\Core\Fields\Registry;
use Noblen\Core\Fields\Store;
use Noblen\Core\Relationships\Dependencies;
use Noblen\Core\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Requirements the Publisher checks before a record may be published.
 *
 * Failure codes are plain and static. They never echo private data.
 */
final class Requirements {

	/**
	 * Content-only failures (used when submitting for approval).
	 *
	 * @param int $post_id Post ID.
	 * @return string[]
	 */
	public static function content_failures( int $post_id ): array {
		$post     = get_post( $post_id );
		$failures = array();
		if ( ! $post || '' === trim( (string) $post->post_title ) ) {
			$failures[] = 'title_missing';
		}
		$type   = $post ? $post->post_type : '';
		$values = Store::get_all( $post_id );
		foreach ( Registry::fields( $type ) as $key => $def ) {
			if ( ! empty( $def['required'] ) ) {
				$v = $values[ $key ] ?? '';
				if ( '' === trim( (string) ( is_array( $v ) ? implode( '', $v ) : $v ) ) ) {
					$failures[] = 'field_required:' . $key;
				}
			}
		}
		if ( $post && '' !== (string) $post->post_password ) {
			$failures[] = 'password_protected';
		}
		return $failures;
	}

	/**
	 * Full set of requirements for publication.
	 *
	 * @param int      $post_id     Post ID.
	 * @param int|null $approver_id Approving user (defaults to the current user).
	 * @return array{failures:string[],warnings:string[]}
	 */
	public static function check( int $post_id, ?int $approver_id = null ): array {
		$approver_id = $approver_id ?? get_current_user_id();
		$type        = (string) get_post_type( $post_id );
		$failures    = self::content_failures( $post_id );

		$deps     = Dependencies::check_for_publish( $post_id );
		$failures = array_merge( $failures, $deps['failures'] );

		if ( ! Repository::is_complete( $post_id ) ) {
			$failures[] = 'clearance_incomplete';
		}
		if ( MediaRights::unconfirmed( $post_id ) ) {
			$failures[] = 'media_rights_unconfirmed';
		}
		if ( Types::PRODUCT === $type ) {
			$values = Store::get_all( $post_id );
			if ( 'live' === ( $values['stage'] ?? '' ) && '' === trim( Repository::raw( $post_id )['evidence_ref'] ) ) {
				$failures[] = 'live_requires_evidence';
			}
		}

		$a = Approvals::latest( $post_id );
		if ( ! $a || 'approved' !== $a['status'] ) {
			$failures[] = 'approval_missing';
		} elseif ( ! hash_equals( (string) $a['snapshot_hash'], Snapshot::hash( $post_id ) ) ) {
			$failures[] = 'approval_stale';
		} elseif ( Settings::separation_of_duties() ) {
			if ( (int) $a['last_editor_id'] && (int) $a['last_editor_id'] === $approver_id ) {
				$failures[] = 'separation_last_editor';
			}
			if ( (int) $a['clearance_by'] && (int) $a['clearance_by'] === $approver_id ) {
				$failures[] = 'separation_clearance';
			}
		}

		return array(
			'failures' => array_values( array_unique( $failures ) ),
			'warnings' => $deps['warnings'],
		);
	}

	/**
	 * Plain message for a failure code.
	 *
	 * @param string $code Code, possibly with a ":detail" suffix.
	 */
	public static function message( string $code ): string {
		$base = explode( ':', $code )[0];
		$map  = array(
			'title_missing'                     => 'A title is required.',
			'field_required'                    => 'A required field is empty.',
			'password_protected'                => 'Password-protected records cannot be published.',
			'required_relationship_missing'     => 'A required relationship is missing.',
			'required_relationship_unavailable' => 'A required related record is not published and approved.',
			'clearance_incomplete'              => 'Clearance is incomplete.',
			'media_rights_unconfirmed'          => 'An image has no current rights confirmation.',
			'live_requires_evidence'            => 'A Live product needs an evidence reference.',
			'approval_missing'                  => 'There is no approval.',
			'approval_stale'                    => 'The content changed after approval.',
			'separation_last_editor'            => 'The approver last edited this content.',
			'separation_clearance'              => 'The approver recorded this clearance.',
		);
		return $map[ $base ] ?? 'The requirements are not met.';
	}
}

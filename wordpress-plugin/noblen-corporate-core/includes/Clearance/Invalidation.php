<?php
namespace Noblen\Core\Clearance;

use Noblen\Core\Audit\AuditLog;
use Noblen\Core\Content\Types;
use Noblen\Core\Exposure\Withdrawal;
use Noblen\Core\Fields\Registry;
use Noblen\Core\Relationships\Relations;

defined( 'ABSPATH' ) || exit;

/**
 * Invalidation hooks: rules R3 (editing a live record), R4 (changes outside the
 * normal save path), R6 (transition safety net), and trash, delete and
 * unpublish handling.
 *
 * None of these handlers calls a post update function. Reverting uses the
 * Withdrawal's minimal status correction under a re-entrancy lock.
 */
final class Invalidation {

	/** Attachment meta keys that change how an image looks or what it is. */
	private const ATTACHMENT_META = array( '_wp_attachment_image_alt', '_wp_attached_file', '_wp_attachment_metadata' );

	private static bool $suppress = false;

	/**
	 * Attach all hooks.
	 */
	public static function register(): void {
		add_filter( 'wp_insert_post_data', array( Gate::class, 'filter_post_data' ), 99, 2 );
		add_action( 'transition_post_status', array( self::class, 'on_transition' ), 1, 3 );
		add_action( 'save_post', array( self::class, 'on_save_post' ), 99, 2 );
		add_action( 'before_delete_post', array( self::class, 'on_before_delete' ), 10, 2 );
		add_action( 'deleted_post', array( self::class, 'on_deleted' ), 10, 1 );

		add_action( 'added_post_meta', array( self::class, 'on_meta' ), 10, 4 );
		add_action( 'updated_post_meta', array( self::class, 'on_meta' ), 10, 4 );
		add_action( 'deleted_post_meta', array( self::class, 'on_meta' ), 10, 4 );
		add_action( 'set_object_terms', array( self::class, 'on_terms' ), 10, 6 );
		add_action( 'attachment_updated', array( self::class, 'on_attachment_updated' ), 10, 3 );
		add_filter( 'update_attached_file', array( self::class, 'on_attached_file' ), 10, 2 );
		add_filter( 'wp_update_attachment_metadata', array( self::class, 'on_attachment_metadata' ), 10, 2 );
		add_action( 'clean_post_cache', array( ReadCheck::class, 'flush' ) );
	}

	/**
	 * Suppress invalidation for audited system changes (reference clean-up).
	 *
	 * @param bool $on Suppress or not.
	 */
	public static function suppress( bool $on ): void {
		self::$suppress = $on;
	}

	/**
	 * Whether a record is a live gated record.
	 *
	 * @param int $post_id Post ID.
	 */
	private static function is_live( int $post_id ): bool {
		$p = get_post( $post_id );
		return $p && Types::is_ours( $p->post_type ) && 'publish' === $p->post_status;
	}

	/**
	 * Rule R6 and the unpublish, trash and demotion handling.
	 *
	 * @param string   $new  New status.
	 * @param string   $old  Old status.
	 * @param \WP_Post $post Post.
	 */
	public static function on_transition( $new, $old, $post ): void {
		if ( ! $post instanceof \WP_Post || ! Types::is_ours( $post->post_type ) || self::$suppress ) {
			return;
		}
		$id = (int) $post->ID;

		if ( in_array( $new, array( 'publish', 'future' ), true ) ) {
			if ( ! Grant::was_consumed( $id ) && ! Withdrawal::in_progress( $id ) ) {
				// R6: reached a public status without a grant (a core function bypassed the save filter).
				Withdrawal::run( $id, 'safety_net', false, 'T06' );
			}
			return;
		}

		if ( 'publish' === $old && 'publish' !== $new ) {
			if ( Withdrawal::in_progress( $id ) ) {
				return;
			}
			if ( Gate::was_demoted( $id ) ) {
				Gate::clear_demoted( $id );
				Withdrawal::run( $id, 'stale', false, 'T04' );
				return;
			}
			if ( 'trash' === $new ) {
				Withdrawal::run( $id, 'trashed', true, 'T08' );
				return;
			}
			// A manual change to draft or pending: recorded as a revocation.
			$a = Approvals::latest( $id );
			if ( $a && 'approved' === $a['status'] ) {
				Approvals::set_status( (int) $a['id'], 'revoked', array( 'decided_at' => gmdate( 'Y-m-d H:i:s' ), 'note' => 'unpublished' ) );
				AuditLog::append( 'approval_revoked', array( 'post_id' => $id, 'approval_id' => (int) $a['id'], 'snapshot_hash' => (string) $a['snapshot_hash'], 'detail' => array( 'reason' => 'unpublished' ) ) );
			}
			Withdrawal::run( $id, 'unpublished', true, 'T02' );
		}
	}

	/**
	 * Remember who last changed the public content of a record.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 */
	public static function on_save_post( $post_id, $post ): void {
		if ( ! $post instanceof \WP_Post || ! Types::is_ours( $post->post_type ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		self::touch_editor( (int) $post_id );
	}

	/**
	 * Record the current user as last editor when the public content changed.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function touch_editor( int $post_id ): void {
		$hash = Snapshot::hash( $post_id );
		if ( $hash !== (string) get_post_meta( $post_id, '_noblen_last_hash', true ) ) {
			update_post_meta( $post_id, '_noblen_last_hash', $hash );
			update_post_meta( $post_id, '_noblen_last_editor', get_current_user_id() );
		}
	}

	/**
	 * Permanent delete of a live record (trigger T09).
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 */
	public static function on_before_delete( $post_id, $post = null ): void {
		$post = $post instanceof \WP_Post ? $post : get_post( $post_id );
		if ( $post && Types::is_ours( $post->post_type ) && 'publish' === $post->post_status ) {
			Withdrawal::run( (int) $post_id, 'deleted', true, 'T09' );
		}
	}

	/**
	 * Clean up references to a deleted record.
	 *
	 * @param int $post_id Deleted post ID.
	 */
	public static function on_deleted( $post_id ): void {
		\Noblen\Core\Relationships\Cleanup::references( (int) $post_id );
	}

	/**
	 * Rule R4: a snapshot meta value changed outside the normal save path.
	 *
	 * @param mixed  $meta_id    Meta ID(s).
	 * @param int    $object_id  Object ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Value.
	 */
	public static function on_meta( $meta_id, $object_id, $meta_key, $meta_value = null ): void {
		if ( self::$suppress || ! is_string( $meta_key ) ) {
			return;
		}
		$object_id = (int) $object_id;
		if ( 'attachment' === get_post_type( $object_id ) ) {
			if ( in_array( $meta_key, self::ATTACHMENT_META, true ) ) {
				self::attachment_changed( $object_id );
			}
			return;
		}
		if ( Registry::is_snapshot_meta( $meta_key ) && self::is_live( $object_id ) ) {
			self::stale_external( $object_id );
		}
	}

	/**
	 * Rule R4 for classification changes.
	 *
	 * @param int    $object_id Object ID.
	 * @param array  $terms     Terms.
	 * @param array  $tt_ids    New term-taxonomy IDs.
	 * @param string $taxonomy  Taxonomy.
	 * @param bool   $append    Append.
	 * @param array  $old_tt_ids Old term-taxonomy IDs.
	 */
	public static function on_terms( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ): void {
		if ( self::$suppress || ! in_array( $taxonomy, array( Types::TAX_AREA, Types::TAX_PRODUCT_TYPE ), true ) ) {
			return;
		}
		$new = array_map( 'intval', (array) $tt_ids );
		$old = array_map( 'intval', (array) $old_tt_ids );
		sort( $new );
		sort( $old );
		if ( $new !== $old && self::is_live( (int) $object_id ) ) {
			self::stale_external( (int) $object_id );
		}
	}

	/**
	 * Attachment title or caption changed.
	 *
	 * @param int      $post_id     Attachment ID.
	 * @param \WP_Post $post_after  After.
	 * @param \WP_Post $post_before Before.
	 */
	public static function on_attachment_updated( $post_id, $post_after, $post_before ): void {
		if ( $post_after->post_title !== $post_before->post_title || $post_after->post_excerpt !== $post_before->post_excerpt ) {
			self::attachment_changed( (int) $post_id );
		}
	}

	/**
	 * Attachment file replaced.
	 *
	 * @param string $file          New file path.
	 * @param int    $attachment_id Attachment ID.
	 * @return string
	 */
	public static function on_attached_file( $file, $attachment_id ) {
		$old = get_attached_file( (int) $attachment_id );
		if ( $old && $old !== $file ) {
			self::attachment_changed( (int) $attachment_id );
		}
		return $file;
	}

	/**
	 * Attachment metadata (for example an edited image) changed.
	 *
	 * @param array $data          Metadata.
	 * @param int   $attachment_id Attachment ID.
	 * @return array
	 */
	public static function on_attachment_metadata( $data, $attachment_id ) {
		self::attachment_changed( (int) $attachment_id );
		return $data;
	}

	/**
	 * Stale every live record that uses a changed image.
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	private static function attachment_changed( int $attachment_id ): void {
		foreach ( Relations::posts_using_attachment( $attachment_id ) as $post_id ) {
			if ( self::is_live( $post_id ) ) {
				self::stale_external( $post_id );
			}
		}
	}

	/**
	 * Mark a live record Stale and withdraw it (trigger T05).
	 *
	 * @param int $post_id Post ID.
	 */
	private static function stale_external( int $post_id ): void {
		Approvals::mark_stale( $post_id, 'external_change' );
		Withdrawal::run( $post_id, 'stale', false, 'T05' );
	}
}

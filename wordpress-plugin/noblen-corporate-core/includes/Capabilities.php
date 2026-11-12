<?php
namespace Noblen\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Capabilities and roles.
 *
 * Content Editor: creates and edits drafts, submits for approval. Cannot publish
 * or edit published records, and cannot read clearance detail.
 * Clearance Officer: records clearance.
 * Publication Approver: approves, revokes and publishes, and reads the audit log.
 * Administrators receive the editing capabilities but NOT the clearance,
 * approval or audit capabilities. The owner assigns those deliberately.
 */
final class Capabilities {

	public const ROLE_EDITOR   = 'noblen_content_editor';
	public const ROLE_CLEARANCE = 'noblen_clearance_officer';
	public const ROLE_APPROVER = 'noblen_publication_approver';

	/** Post type => array( singular, plural ). */
	public const TYPES = array(
		'noblen_project'  => array( 'noblen_project', 'noblen_projects' ),
		'noblen_product'  => array( 'noblen_product', 'noblen_products' ),
		'noblen_company'  => array( 'noblen_company', 'noblen_companies' ),
		'noblen_industry' => array( 'noblen_industry', 'noblen_industries' ),
		'noblen_service'  => array( 'noblen_service', 'noblen_services' ),
	);

	/**
	 * Capability map for register_post_type().
	 *
	 * @param string $singular Singular cap base.
	 * @param string $plural   Plural cap base.
	 * @return array<string,string>
	 */
	public static function map( string $singular, string $plural ): array {
		return array(
			'edit_post'              => "edit_{$singular}",
			'read_post'              => "read_{$singular}",
			'delete_post'            => "delete_{$singular}",
			'edit_posts'             => "edit_{$plural}",
			'edit_others_posts'      => "edit_others_{$plural}",
			'delete_posts'           => "delete_{$plural}",
			'publish_posts'          => "publish_{$plural}",
			'read_private_posts'     => "read_private_{$plural}",
			'delete_private_posts'   => "delete_private_{$plural}",
			'delete_published_posts' => "delete_published_{$plural}",
			'delete_others_posts'    => "delete_others_{$plural}",
			'edit_private_posts'     => "edit_private_{$plural}",
			'edit_published_posts'   => "edit_published_{$plural}",
			'create_posts'           => "create_{$plural}",
		);
	}

	/**
	 * Capability names for a role.
	 *
	 * @param string[] $verbs   Verbs such as edit_, publish_.
	 * @return string[]
	 */
	private static function caps_for( array $verbs ): array {
		$out = array();
		foreach ( self::TYPES as $names ) {
			foreach ( $verbs as $verb ) {
				$out[] = $verb . $names[1];
			}
		}
		return $out;
	}

	/**
	 * Create or update the roles and grant administrator capabilities. Idempotent.
	 */
	public static function install(): void {
		$editor_caps    = array_merge( array( 'read', 'upload_files' ), self::caps_for( array( 'edit_', 'create_', 'edit_others_', 'delete_' ) ) );
		$clearance_caps = array_merge( array( 'read', 'noblen_manage_clearance' ), self::caps_for( array( 'edit_', 'create_', 'edit_others_' ) ) );
		$approver_caps  = array_merge(
			array( 'read', 'upload_files', 'noblen_approve_publication', 'noblen_view_audit' ),
			self::caps_for( array( 'edit_', 'create_', 'edit_others_', 'edit_published_', 'delete_', 'delete_published_', 'delete_others_', 'publish_', 'read_private_' ) )
		);

		foreach ( array(
			self::ROLE_EDITOR    => array( 'Noblen Content Editor', $editor_caps ),
			self::ROLE_CLEARANCE => array( 'Noblen Clearance Officer', $clearance_caps ),
			self::ROLE_APPROVER  => array( 'Noblen Publication Approver', $approver_caps ),
		) as $slug => $def ) {
			$role = get_role( $slug );
			if ( ! $role ) {
				$role = add_role( $slug, $def[0] );
			}
			if ( $role ) {
				foreach ( $def[1] as $cap ) {
					$role->add_cap( $cap );
				}
			}
		}

		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin_caps = array_merge(
				self::caps_for( array( 'edit_', 'create_', 'edit_others_', 'edit_published_', 'edit_private_', 'delete_', 'delete_published_', 'delete_others_', 'delete_private_', 'read_private_' ) ),
				array( 'noblen_manage_settings', 'manage_noblen_taxonomies' )
			);
			foreach ( $admin_caps as $cap ) {
				$admin->add_cap( $cap );
			}
		}
	}

	/**
	 * Whether a user (or the current user) may publish gated content.
	 *
	 * @param int $user_id User ID, 0 for the current user.
	 */
	public static function user_can_approve( int $user_id = 0 ): bool {
		$user_id = $user_id ? $user_id : get_current_user_id();
		return $user_id && user_can( $user_id, 'noblen_approve_publication' );
	}
}

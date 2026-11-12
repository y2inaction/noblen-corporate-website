<?php
namespace Noblen\Core\Admin;

use Noblen\Core\Content\Types;
use Noblen\Core\Fields\Registry;
use Noblen\Core\Fields\Store;
use Noblen\Core\Clearance\Repository;
use Noblen\Core\Clearance\Approvals;
use Noblen\Core\Clearance\Workflow;
use Noblen\Core\Clearance\MediaRights;
use Noblen\Core\Audit\Verifier;
use Noblen\Core\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Administration interface: structured fields, the publication box, the clearance
 * box, business settings, list columns and notices.
 *
 * Every state-changing handler checks a nonce and a capability server-side. The
 * interface only reflects what the server will allow.
 */
final class Admin {

	/** Register administration hooks. */
	public static function register(): void {
		if ( ! is_admin() ) {
			return;
		}
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post', array( __CLASS__, 'save_fields' ), 10, 2 );
		add_action( 'admin_post_noblen_workflow', array( __CLASS__, 'handle_workflow' ) );
		add_action( 'save_post', array( __CLASS__, 'save_clearance' ), 20, 2 );
		add_action( 'admin_post_noblen_seed', array( __CLASS__, 'handle_seed' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		foreach ( Types::all() as $type ) {
			add_filter( "manage_{$type}_posts_columns", array( __CLASS__, 'columns' ) );
			add_action( "manage_{$type}_posts_custom_column", array( __CLASS__, 'column' ), 10, 2 );
		}
	}

	/** Meta boxes for each content type. */
	public static function meta_boxes(): void {
		foreach ( Types::all() as $type ) {
			add_meta_box( 'noblen_fields', 'Structured content', array( __CLASS__, 'fields_box' ), $type, 'normal', 'high' );
			add_meta_box( 'noblen_publication', 'Publication', array( __CLASS__, 'publication_box' ), $type, 'side', 'high' );
			if ( current_user_can( 'noblen_manage_clearance' ) || current_user_can( 'noblen_approve_publication' ) ) {
				add_meta_box( 'noblen_clearance', 'Clearance (private)', array( __CLASS__, 'clearance_box' ), $type, 'normal', 'default' );
			}
		}
	}

	/**
	 * Structured fields box.
	 *
	 * @param \WP_Post $post Post.
	 */
	public static function fields_box( \WP_Post $post ): void {
		wp_nonce_field( 'noblen_fields_' . $post->ID, 'noblen_fields_nonce' );
		$values = Store::get_all( $post->ID );
		echo '<p class="description">Sections marked "approved for display" appear publicly only when filled in. Nothing here is published until the record is approved.</p>';
		foreach ( Registry::fields( $post->post_type ) as $key => $def ) {
			$name  = 'noblen_f[' . $key . ']';
			$id    = 'noblen_f_' . $key;
			$label = (string) $def['label'] . ( ! empty( $def['required'] ) ? ' *' : '' );
			$val   = $values[ $key ] ?? '';
			echo '<div style="margin:14px 0"><label for="' . esc_attr( $id ) . '"><strong>' . esc_html( $label ) . '</strong></label><br>';
			switch ( $def['kind'] ) {
				case 'richtext':
					echo '<textarea class="large-text" rows="6" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">' . esc_textarea( (string) $val ) . '</textarea>';
					break;
				case 'enum':
					echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"><option value="">Not set</option>';
					foreach ( $def['options'] as $v => $l ) {
						echo '<option value="' . esc_attr( $v ) . '"' . selected( $val, $v, false ) . '>' . esc_html( $l ) . '</option>';
					}
					echo '</select>';
					break;
				case 'bool':
					echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="0"><label><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . checked( (bool) $val, true, false ) . '> Yes</label>';
					break;
				case 'rel':
					self::relationship_select( $def, $name, $id, (array) $val );
					echo '<br><span class="description">' . ( 'required' === $def['class'] ? 'Required for publication.' : 'Optional. A missing or unavailable item never blocks publication.' ) . '</span>';
					break;
				case 'gallery':
					echo '<input type="text" class="regular-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( implode( ',', (array) $val ) ) . '"><br><span class="description">Comma-separated media library IDs. Each image needs a private rights confirmation before approval.</span>';
					break;
				default:
					echo '<input type="text" class="large-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $val ) . '">';
			}
			if ( ! empty( $def['switch'] ) ) {
				$sname = 'noblen_f[' . $key . '_approved]';
				echo '<br><input type="hidden" name="' . esc_attr( $sname ) . '" value="0"><label><input type="checkbox" name="' . esc_attr( $sname ) . '" value="1"' . checked( ! empty( $values[ $key . '_approved' ] ), true, false ) . '> Verified and approved for display</label>';
			}
			echo '</div>';
		}
	}

	/**
	 * Relationship selector.
	 *
	 * @param array<string,mixed> $def      Field definition.
	 * @param string              $name     Input name.
	 * @param string              $id       Input ID.
	 * @param int[]               $selected Selected IDs.
	 */
	private static function relationship_select( array $def, string $name, string $id, array $selected ): void {
		$multiple = ! empty( $def['multiple'] );
		$posts    = get_posts(
			array(
				'post_type'   => $def['target'],
				'post_status' => array( 'publish', 'pending', 'draft', 'future', 'private' ),
				'numberposts' => 100,
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);
		echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . ( $multiple ? '[]' : '' ) . '"' . ( $multiple ? ' multiple size="5"' : '' ) . '>';
		if ( ! $multiple ) {
			echo '<option value="">None</option>';
		}
		foreach ( $posts as $p ) {
			echo '<option value="' . esc_attr( (string) $p->ID ) . '"' . selected( in_array( $p->ID, $selected, true ), true, false ) . '>' . esc_html( get_the_title( $p ) . ' (' . $p->post_status . ')' ) . '</option>';
		}
		echo '</select>';
	}

	/**
	 * Save structured fields.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 */
	public static function save_fields( int $post_id, \WP_Post $post ): void {
		if ( ! Types::is_ours( $post->post_type ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['noblen_fields_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['noblen_fields_nonce'] ) ), 'noblen_fields_' . $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$input = isset( $_POST['noblen_f'] ) ? (array) wp_unslash( $_POST['noblen_f'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitised per field in Store::save.
		if ( isset( $input['gallery'] ) && is_string( $input['gallery'] ) ) {
			$input['gallery'] = array_filter( array_map( 'intval', explode( ',', $input['gallery'] ) ) );
		}
		update_post_meta( $post_id, '_noblen_last_editor', get_current_user_id() );
		$errors = Store::save( $post_id, $input );
		if ( $errors ) {
			set_transient( 'noblen_field_errors_' . get_current_user_id(), $errors, 60 );
		}
	}

	/**
	 * Publication box. The standard Publish button cannot publish these records.
	 *
	 * @param \WP_Post $post Post.
	 */
	public static function publication_box( \WP_Post $post ): void {
		$a = Approvals::latest( $post->ID );
		echo '<p><strong>WordPress status:</strong> ' . esc_html( $post->post_status ) . '</p>';
		echo '<p><strong>Approval:</strong> ' . esc_html( $a ? (string) $a['status'] : 'none' ) . '</p>';
		echo '<p><strong>Clearance:</strong> ' . esc_html( Repository::indicator( $post->ID ) ) . '</p>';
		$state = Verifier::state();
		if ( 'verified' !== $state ) {
			echo '<p style="color:#b32d2e"><strong>Approvals are blocked: audit integrity is "' . esc_html( $state ) . '".</strong></p>';
		}
		if ( 'auto-draft' === $post->post_status ) {
			echo '<p>Save a draft first.</p>';
			return;
		}
		$actions = array();
		if ( current_user_can( 'edit_post', $post->ID ) && ! ( $a && 'submitted' === $a['status'] ) ) {
			$actions['submit'] = 'Submit for approval';
		}
		if ( current_user_can( 'noblen_approve_publication' ) ) {
			if ( $a && 'submitted' === $a['status'] ) {
				$actions['approve_publish'] = 'Approve and publish';
				$actions['reject']          = 'Reject';
			}
			if ( 'publish' === $post->post_status ) {
				$actions['revoke'] = 'Revoke approval';
			}
		}
		if ( 'publish' === $post->post_status && current_user_can( 'edit_post', $post->ID ) ) {
			$actions['unpublish'] = 'Unpublish';
		}
		foreach ( $actions as $act => $label ) {
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=noblen_workflow&step=' . $act . '&post=' . $post->ID ), 'noblen_workflow_' . $act . '_' . $post->ID );
			echo '<p><a class="button" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a></p>';
		}
		echo '<p class="description">Save your changes before using these buttons.</p>';
	}

	/** Workflow handler. */
	public static function handle_workflow(): void {
		$step = isset( $_GET['step'] ) ? sanitize_key( wp_unslash( $_GET['step'] ) ) : '';
		$id   = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		check_admin_referer( 'noblen_workflow_' . $step . '_' . $id );
		if ( ! $id || ! Types::is_ours( (string) get_post_type( $id ) ) ) {
			wp_die( 'Invalid record.', 400 );
		}
		switch ( $step ) {
			case 'submit':
				$r = current_user_can( 'edit_post', $id ) ? Workflow::submit( $id ) : new \WP_Error( 'noblen_forbidden', 'Not allowed.' );
				break;
			case 'approve_publish':
				$r = Workflow::approve_and_publish( $id );
				break;
			case 'reject':
				$r = Workflow::reject( $id );
				break;
			case 'revoke':
				$r = Workflow::revoke( $id );
				break;
			case 'unpublish':
				$r = current_user_can( 'edit_post', $id ) ? Workflow::unpublish( $id ) : new \WP_Error( 'noblen_forbidden', 'Not allowed.' );
				break;
			default:
				wp_die( 'Unknown action.', 400 );
		}
		self::flash( $r, 'Done.' );
		wp_safe_redirect( (string) get_edit_post_link( $id, 'raw' ) );
		exit;
	}

	/**
	 * Store a result message for the next page load.
	 *
	 * @param mixed  $result  Result or WP_Error.
	 * @param string $success Success text.
	 */
	private static function flash( $result, string $success ): void {
		set_transient(
			'noblen_flash_' . get_current_user_id(),
			array(
				'type' => is_wp_error( $result ) ? 'error' : 'success',
				'text' => is_wp_error( $result ) ? $result->get_error_message() : $success,
			),
			60
		);
	}

	/**
	 * Clearance box (private). Visible only to those with a clearance capability.
	 *
	 * @param \WP_Post $post Post.
	 */
	public static function clearance_box( \WP_Post $post ): void {
		$rec = Repository::get( $post->ID );
		if ( is_wp_error( $rec ) ) {
			echo '<p>' . esc_html( Repository::indicator( $post->ID ) ) . '</p>';
			return;
		}
		if ( ! current_user_can( 'noblen_manage_clearance' ) ) {
			echo '<p>Clearance: ' . esc_html( (string) $rec['status'] ) . '</p>';
			return;
		}
		echo '<p class="description">Private. Stored in plugin-owned tables, never in post meta, and never output publicly. Record where evidence is held, not the evidence itself.</p>';
		// These fields live inside the main post form (forms cannot be nested) and are saved by save_clearance().
		wp_nonce_field( 'noblen_clearance_' . $post->ID, 'noblen_clr_nonce' );
		foreach ( Repository::items_for_type( $post->post_type ) as $item => $na ) {
			$cur = $rec['items'][ $item ] ?? '';
			echo '<p><label>' . esc_html( ucwords( str_replace( '_', ' ', $item ) ) ) . ' <select name="noblen_clr[items][' . esc_attr( $item ) . ']"><option value="">Not confirmed</option><option value="yes"' . selected( $cur, 'yes', false ) . '>Yes</option>';
			if ( $na ) {
				echo '<option value="na"' . selected( $cur, 'na', false ) . '>Not applicable</option>';
			}
			echo '</select></label></p>';
		}
		if ( Repository::evidence_required( $post->post_type ) ) {
			echo '<p><label>Evidence reference<br><input type="text" class="large-text" name="noblen_clr[evidence_ref]" value="' . esc_attr( (string) $rec['evidence_ref'] ) . '"></label></p>';
		}
		echo '<p><em>Clearance status: ' . esc_html( (string) $rec['status'] ) . '. Saved when you save the record.</em></p>';
		$gallery = (array) ( Store::get_all( $post->ID )['gallery'] ?? array() );
		$thumb   = (int) get_post_thumbnail_id( $post->ID );
		$images  = array_filter( array_unique( array_merge( $thumb ? array( $thumb ) : array(), $gallery ) ) );
		foreach ( $images as $img ) {
			echo '<p>Image #' . esc_html( (string) $img ) . ': ' . ( MediaRights::is_confirmed( (int) $img ) ? 'rights confirmed' : 'rights not confirmed' ) . '<br><label>Rights evidence reference <input type="text" name="noblen_media[' . esc_attr( (string) $img ) . ']" value=""></label> (enter a reference and save to confirm)</p>';
		}
	}

	/**
	 * Save clearance and media-rights confirmations submitted with the record.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 */
	public static function save_clearance( int $post_id, \WP_Post $post ): void {
		if ( ! Types::is_ours( $post->post_type ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['noblen_clr_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['noblen_clr_nonce'] ) ), 'noblen_clearance_' . $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'noblen_manage_clearance' ) ) {
			return;
		}
		$clr   = isset( $_POST['noblen_clr'] ) ? (array) wp_unslash( $_POST['noblen_clr'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitised below.
		$items = isset( $clr['items'] ) ? array_map( 'sanitize_key', (array) $clr['items'] ) : array();
		$ref   = isset( $clr['evidence_ref'] ) ? sanitize_text_field( (string) $clr['evidence_ref'] ) : '';
		$res   = Repository::save( $post_id, $items, $ref );
		$media = isset( $_POST['noblen_media'] ) ? (array) wp_unslash( $_POST['noblen_media'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitised below.
		foreach ( $media as $img => $basis ) {
			$basis = sanitize_text_field( (string) $basis );
			if ( '' !== $basis && ! is_wp_error( $res ) ) {
				$res = MediaRights::confirm( absint( $img ), $basis );
			}
		}
		self::flash( $res, 'Clearance saved.' );
	}

	/** Create the optional names-only starter drafts. */
	public static function handle_seed(): void {
		check_admin_referer( 'noblen_seed' );
		if ( ! current_user_can( 'noblen_manage_settings' ) ) {
			wp_die( 'You are not allowed to do this.', 403 );
		}
		$n = \Noblen\Core\Seed\DraftSeeder::seed();
		self::flash( true, sprintf( '%d starter draft(s) created. They are names only and stay unpublished.', $n ) );
		wp_safe_redirect( admin_url( 'admin.php?page=noblen-settings' ) );
		exit;
	}

	/** Settings menu. */
	public static function menu(): void {
		add_menu_page( 'Noblen', 'Noblen', 'noblen_manage_settings', 'noblen-settings', array( __CLASS__, 'settings_page' ), 'dashicons-building', 58 );
	}

	/** Register the settings option. */
	public static function register_settings(): void {
		register_setting(
			'noblen_settings',
			'noblen_core_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Settings::class, 'sanitize' ),
				'default'           => array(),
			)
		);
	}

	/** Settings page. */
	public static function settings_page(): void {
		if ( ! current_user_can( 'noblen_manage_settings' ) ) {
			wp_die( 'You are not allowed to manage these settings.', 403 );
		}
		$vals = Settings::all();
		echo '<div class="wrap"><h1>Noblen business settings</h1><p>Every value starts empty. Nothing appears on the website until you enter it. Do not enter anything you have not verified.</p><form method="post" action="options.php">';
		settings_fields( 'noblen_settings' );
		echo '<table class="form-table">';
		foreach ( Settings::fields() as $key => $def ) {
			$n = 'noblen_core_settings[' . $key . ']';
			echo '<tr><th><label for="s_' . esc_attr( $key ) . '">' . esc_html( (string) $def['label'] ) . '</label></th><td>';
			if ( 'textarea' === $def['kind'] ) {
				echo '<textarea class="large-text" rows="3" id="s_' . esc_attr( $key ) . '" name="' . esc_attr( $n ) . '">' . esc_textarea( $vals[ $key ] ?? '' ) . '</textarea>';
			} else {
				echo '<input class="regular-text" id="s_' . esc_attr( $key ) . '" name="' . esc_attr( $n ) . '" value="' . esc_attr( $vals[ $key ] ?? '' ) . '">';
			}
			echo '</td></tr>';
		}
		echo '</table>';
		submit_button();
		echo '</form>';
		echo '<h2>Starter drafts</h2><p>Creates unpublished, names-only drafts for the three business units and four products named in the brief. No content or clearance is added.</p>';
		echo '<p><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=noblen_seed' ), 'noblen_seed' ) ) . '">Create starter drafts</a></p></div>';
	}

	/** List columns. @param array<string,string> $cols Columns. @return array<string,string> */
	public static function columns( array $cols ): array {
		$cols['noblen_approval']  = 'Approval';
		$cols['noblen_clearance'] = 'Clearance';
		return $cols;
	}

	/** Column output. @param string $col Column. @param int $post_id Post ID. */
	public static function column( string $col, int $post_id ): void {
		if ( 'noblen_approval' === $col ) {
			$a = Approvals::latest( $post_id );
			echo esc_html( $a ? (string) $a['status'] : 'none' );
		} elseif ( 'noblen_clearance' === $col ) {
			echo esc_html( Repository::indicator( $post_id ) );
		}
	}

	/** Admin notices. */
	public static function notices(): void {
		$uid   = get_current_user_id();
		$flash = get_transient( 'noblen_flash_' . $uid );
		if ( $flash ) {
			delete_transient( 'noblen_flash_' . $uid );
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', 'error' === $flash['type'] ? 'error' : 'success', esc_html( (string) $flash['text'] ) );
		}
		$errors = get_transient( 'noblen_field_errors_' . $uid );
		if ( $errors ) {
			delete_transient( 'noblen_field_errors_' . $uid );
			foreach ( (array) $errors as $k => $msg ) {
				printf( '<div class="notice notice-warning"><p>%s: %s</p></div>', esc_html( (string) $k ), esc_html( (string) $msg ) );
			}
		}
		if ( current_user_can( 'noblen_approve_publication' ) || current_user_can( 'noblen_manage_settings' ) ) {
			foreach ( (array) get_option( 'noblen_core_notices', array() ) as $n ) {
				if ( empty( $n['urls'] ) ) {
					continue;
				}
				printf( '<div class="notice notice-info"><p>A record was withdrawn (%s). Purge these cached addresses if you use a page cache: %s</p></div>', esc_html( (string) ( $n['reason'] ?? '' ) ), esc_html( implode( ', ', array_map( 'strval', (array) $n['urls'] ) ) ) );
			}
		}
	}
}

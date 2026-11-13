<?php
/**
 * Administration endpoints for the Customizer brand tools. Each one checks a nonce and the edit_theme_options capability.
 *
 * @package noblen-corporate
 */

defined( 'ABSPATH' ) || exit;

require_once get_template_directory() . '/inc/palette.php';

/** Capability gate for the endpoints (each endpoint verifies its own nonce first). */
function noblen_brand_ajax_require_capability(): void {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_send_json_error( array( 'message' => 'You are not allowed to change the theme settings.' ), 403 );
	}
}

// Suggest a palette from the logo. Read-only: nothing is saved.
add_action(
	'wp_ajax_noblen_logo_palette',
	static function () {
		check_ajax_referer( 'noblen_brand', 'nonce' );
		noblen_brand_ajax_require_capability();
		$id = isset( $_POST['attachment'] ) ? absint( wp_unslash( $_POST['attachment'] ) ) : 0;
		if ( $id <= 0 || ! current_user_can( 'read_post', $id ) ) {
			wp_send_json_error( array( 'message' => 'Choose a logo first (Site Identity).' ), 400 );
		}
		$dominant = noblen_palette_from_attachment( $id );
		if ( is_wp_error( $dominant ) ) {
			wp_send_json_error( array( 'message' => $dominant->get_error_message() ), 422 );
		}
		$suggestion = noblen_suggest_palette( $dominant );
		if ( null === $suggestion ) {
			wp_send_json_error( array( 'message' => 'No usable colours were found in this logo (it may be white, transparent or only gold tones). Choose the colours manually.' ), 422 );
		}
		wp_send_json_success(
			array(
				'dominant' => $dominant,
				'palette'  => $suggestion['palette'],
				'notes'    => $suggestion['notes'],
			)
		);
	}
);

// Save the current colours as a named preset.
add_action(
	'wp_ajax_noblen_preset_save',
	static function () {
		check_ajax_referer( 'noblen_brand', 'nonce' );
		noblen_brand_ajax_require_capability();
		$name   = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$raw    = isset( $_POST['colors'] ) && is_array( $_POST['colors'] ) ? wp_unslash( $_POST['colors'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each value is sanitised below and then validated as hex.
		$colors = array();
		foreach ( array_keys( noblen_brand_defaults() ) as $key ) {
			$colors[ $key ] = is_scalar( $raw[ $key ] ?? null ) ? sanitize_text_field( (string) $raw[ $key ] ) : null;
		}
		$name = trim( mb_substr( $name, 0, 40 ) );
		if ( '' === $name ) {
			wp_send_json_error( array( 'message' => 'Give the preset a name.' ), 400 );
		}
		$saved = noblen_brand_saved_presets();
		$id    = sanitize_title( $name );
		$id    = '' === $id ? 'preset' : mb_substr( $id, 0, 36 );
		if ( ! isset( $saved[ $id ] ) && count( $saved ) >= 12 ) {
			wp_send_json_error( array( 'message' => 'You can keep up to 12 saved presets. Delete one first.' ), 400 );
		}
		$saved[ $id ] = array(
			'name'   => $name,
			'colors' => noblen_brand_complete( $colors ),
		);
		set_theme_mod( 'noblen_brand_presets', $saved );
		wp_send_json_success(
			array(
				'saved' => noblen_brand_saved_presets(),
				'id'    => $id,
			)
		);
	}
);

// Delete a saved preset.
add_action(
	'wp_ajax_noblen_preset_delete',
	static function () {
		check_ajax_referer( 'noblen_brand', 'nonce' );
		noblen_brand_ajax_require_capability();
		$id    = isset( $_POST['id'] ) ? sanitize_key( wp_unslash( $_POST['id'] ) ) : '';
		$saved = noblen_brand_saved_presets();
		if ( ! isset( $saved[ $id ] ) ) {
			wp_send_json_error( array( 'message' => 'That preset no longer exists.' ), 404 );
		}
		unset( $saved[ $id ] );
		set_theme_mod( 'noblen_brand_presets', $saved );
		wp_send_json_success( array( 'saved' => noblen_brand_saved_presets() ) );
	}
);

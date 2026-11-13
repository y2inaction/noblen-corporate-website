<?php
/**
 * Customizer: Brand Identity (colours, appearance, presets, logo palette, contrast check) and logo options in Site Identity.
 *
 * Every setting is stored as a theme modification, validated server-side, previewed live, and only saved when the
 * administrator presses Publish.
 *
 * @package noblen-corporate
 */

defined( 'ABSPATH' ) || exit;

/**
 * Saved palette presets: id => array( name, colors ).
 *
 * @return array<string,array{name:string,colors:array<string,string>}>
 */
function noblen_brand_saved_presets(): array {
	$saved = get_theme_mod( 'noblen_brand_presets', array() );
	$out   = array();
	foreach ( is_array( $saved ) ? $saved : array() as $id => $preset ) {
		if ( is_string( $id ) && preg_match( '/^[a-z0-9-]{1,40}$/', $id ) && is_array( $preset ) && ! empty( $preset['name'] ) && is_array( $preset['colors'] ?? null ) ) {
			$out[ $id ] = array(
				'name'   => sanitize_text_field( (string) $preset['name'] ),
				'colors' => noblen_brand_complete( $preset['colors'] ),
			);
		}
	}
	return $out;
}

add_action(
	'customize_register',
	static function ( WP_Customize_Manager $wp_customize ) {
		require_once get_template_directory() . '/inc/class-noblen-brand-tools-control.php';

		// ---- Site Identity: logo options (the logo itself stays WordPress's own control and the Media Library).
		$logo = $wp_customize->get_setting( 'custom_logo' );
		if ( $logo ) {
			$logo->transport = 'refresh';
		}
		if ( isset( $wp_customize->selective_refresh ) ) {
			$wp_customize->selective_refresh->remove_partial( 'custom_logo' );
		}
		$wp_customize->add_setting(
			'noblen_logo_alt',
			array(
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
				'transport'         => 'refresh',
			)
		);
		$wp_customize->add_control(
			'noblen_logo_alt',
			array(
				'section'     => 'title_tagline',
				'label'       => 'Logo alternative text',
				'description' => 'Read aloud by screen readers. Leave empty to use the Media Library text, or the company name.',
				'type'        => 'text',
				'priority'    => 9,
			)
		);
		foreach ( array(
			'noblen_logo_height'        => array( 'Logo height on large screens (pixels)', 'desktop', 9 ),
			'noblen_logo_height_mobile' => array( 'Logo height on small screens (pixels)', 'mobile', 9 ),
		) as $id => $def ) {
			$lim = noblen_logo_height_limits()[ $def[1] ];
			$wp_customize->add_setting(
				$id,
				array(
					'default'           => $lim[2],
					'sanitize_callback' => static fn( $v ) => (int) max( $lim[0], min( $lim[1], (int) $v ) ),
					'transport'         => 'postMessage',
				)
			);
			$wp_customize->add_control(
				$id,
				array(
					'section'     => 'title_tagline',
					'label'       => $def[0],
					'type'        => 'number',
					'input_attrs' => array(
						'min'  => $lim[0],
						'max'  => $lim[1],
						'step' => 1,
					),
					'priority'    => $def[2],
				)
			);
		}
		$wp_customize->add_setting(
			'noblen_logo_dark',
			array(
				'default'           => 0,
				'sanitize_callback' => 'absint',
				'transport'         => 'refresh',
			)
		);
		$wp_customize->add_control(
			new WP_Customize_Media_Control(
				$wp_customize,
				'noblen_logo_dark',
				array(
					'section'     => 'title_tagline',
					'label'       => 'Logo for dark appearance (optional)',
					'description' => 'Shown instead of the main logo when the site is in dark appearance. Use SVG or a PNG or WebP with a transparent background.',
					'mime_type'   => 'image',
					'priority'    => 9,
				)
			)
		);

		// ---- Brand Identity panel.
		$wp_customize->add_panel(
			'noblen_brand',
			array(
				'title'       => 'Brand Identity',
				'description' => 'Change the colours of the whole website. Changes preview live and are saved only when you press Publish.',
				'priority'    => 25,
			)
		);
		$sections = array(
			'core'   => array( 'noblen_brand_core', 'Brand and text colours', 'The main identity colours, text and backgrounds.' ),
			'chrome' => array( 'noblen_brand_chrome', 'Header and footer', 'Header and footer backgrounds and text.' ),
			'links'  => array( 'noblen_brand_links', 'Links and buttons', 'Link and button colours, including hover and pressed states.' ),
		);
		foreach ( $sections as $s ) {
			$wp_customize->add_section(
				$s[0],
				array(
					'title'       => $s[1],
					'description' => $s[2],
					'panel'       => 'noblen_brand',
				)
			);
		}
		foreach ( noblen_brand_definition()['tokens'] as $token ) {
			$id = 'noblen_brand_' . $token['key'];
			$wp_customize->add_setting(
				$id,
				array(
					'default'           => $token['default'],
					'sanitize_callback' => 'noblen_sanitize_brand_color',
					'transport'         => 'postMessage',
				)
			);
			$wp_customize->add_control(
				new WP_Customize_Color_Control(
					$wp_customize,
					$id,
					array(
						'section'     => $sections[ $token['group'] ][0],
						'label'       => $token['label'],
						'description' => $token['help'],
					)
				)
			);
		}
		$wp_customize->add_section(
			'noblen_brand_tools_section',
			array(
				'title'       => 'Appearance, presets and logo colours',
				'description' => 'Light or dark appearance, saved palettes, suggestions from your logo, and a contrast check.',
				'panel'       => 'noblen_brand',
			)
		);
		$wp_customize->add_setting(
			'noblen_brand_appearance',
			array(
				'default'           => 'light',
				'sanitize_callback' => static fn( $v ) => in_array( $v, array( 'light', 'dark', 'auto' ), true ) ? $v : 'light',
				'transport'         => 'postMessage',
			)
		);
		$wp_customize->add_control(
			'noblen_brand_appearance',
			array(
				'section'     => 'noblen_brand_tools_section',
				'label'       => 'Appearance',
				'description' => 'Dark appearance is derived from your brand colours and checked for contrast.',
				'type'        => 'radio',
				'choices'     => noblen_brand_definition()['appearance_modes'],
			)
		);
		$wp_customize->add_setting(
			'noblen_brand_tools',
			array(
				'default'           => '',
				'sanitize_callback' => '__return_empty_string',
				'transport'         => 'postMessage',
			)
		);
		$wp_customize->add_control(
			new Noblen_Brand_Tools_Control(
				$wp_customize,
				'noblen_brand_tools',
				array( 'section' => 'noblen_brand_tools_section' )
			)
		);
	}
);

// Controls (left pane): the colour library, the tools interface and its data.
add_action(
	'customize_controls_enqueue_scripts',
	static function () {
		$dir = get_template_directory_uri() . '/assets/js';
		wp_enqueue_script( 'noblen-brand-lib', $dir . '/brand-lib.js', array(), NOBLEN_THEME_VERSION, true );
		wp_enqueue_script( 'noblen-customizer-controls', $dir . '/customizer-controls.js', array( 'customize-controls', 'noblen-brand-lib' ), NOBLEN_THEME_VERSION, true );
		wp_enqueue_style( 'noblen-customizer-controls', get_template_directory_uri() . '/assets/css/customizer-controls.css', array(), NOBLEN_THEME_VERSION );
		wp_localize_script(
			'noblen-customizer-controls',
			'NoblenBrandConfig',
			array(
				'definition' => noblen_brand_definition(),
				'saved'      => noblen_brand_saved_presets(),
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'noblen_brand' ),
				'maxPresets' => 12,
			)
		);
	}
);

// Preview (right pane): live updates for every colour, the appearance and the logo sizes.
add_action(
	'customize_preview_init',
	static function () {
		$dir = get_template_directory_uri() . '/assets/js';
		wp_enqueue_script( 'noblen-brand-lib', $dir . '/brand-lib.js', array(), NOBLEN_THEME_VERSION, true );
		wp_enqueue_script( 'noblen-customizer-preview', $dir . '/customizer-preview.js', array( 'customize-preview', 'noblen-brand-lib' ), NOBLEN_THEME_VERSION, true );
		wp_localize_script( 'noblen-customizer-preview', 'NoblenBrandPreview', array( 'definition' => noblen_brand_definition() ) );
	}
);

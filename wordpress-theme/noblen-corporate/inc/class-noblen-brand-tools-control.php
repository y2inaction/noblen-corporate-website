<?php
/**
 * Customizer control that hosts the brand tools: presets, restore defaults, logo palette suggestions and the contrast check.
 * The interface is built by assets/js/customizer-controls.js; this class only prints the container.
 *
 * @package noblen-corporate
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WP_Customize_Control' ) ) {
	/** Container control for the brand tools. */
	class Noblen_Brand_Tools_Control extends WP_Customize_Control {

		/** @var string */
		public $type = 'noblen_brand_tools';

		/** Print the container. The script fills it. */
		protected function render_content(): void {
			echo '<div class="noblen-tools" data-noblen-tools><p class="description">' . esc_html__( 'Loading the brand tools…', 'noblen-corporate' ) . '</p></div>';
		}
	}
}

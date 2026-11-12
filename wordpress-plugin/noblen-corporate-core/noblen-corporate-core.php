<?php
/**
 * Plugin Name: Noblen Corporate Core
 * Description: Content architecture, CMS and publication controls for the Noblen corporate website. Independent of any theme.
 * Version: 0.1.0
 * Requires PHP: 8.3
 * Requires at least: 6.4
 * Author: Noblen Technologies Limited
 * License: Proprietary
 * Text Domain: noblen-core
 *
 * @package Noblen\Core
 */

defined( 'ABSPATH' ) || exit;

define( 'NOBLEN_CORE_VERSION', '0.1.0' );
define( 'NOBLEN_CORE_API_VERSION', 1 );
define( 'NOBLEN_CORE_FILE', __FILE__ );
define( 'NOBLEN_CORE_DIR', plugin_dir_path( __FILE__ ) );

/**
 * API version of this plugin, so a theme can check compatibility safely.
 *
 * @return int
 */
function noblen_core_api_version() {
	return (int) NOBLEN_CORE_API_VERSION;
}

/**
 * Whether this site meets the minimum PHP and WordPress versions.
 *
 * @return string Empty string when compatible, otherwise a plain message.
 */
function noblen_core_compat_problem() {
	global $wp_version;
	if ( version_compare( PHP_VERSION, '8.3', '<' ) ) {
		return 'Noblen Corporate Core requires PHP 8.3 or newer. This site runs PHP ' . PHP_VERSION . '.';
	}
	if ( isset( $wp_version ) && version_compare( $wp_version, '6.4', '<' ) ) {
		return 'Noblen Corporate Core requires WordPress 6.4 or newer. This site runs WordPress ' . $wp_version . '.';
	}
	return '';
}

if ( '' !== noblen_core_compat_problem() ) {
	// Friendly message instead of a fatal error. Nothing else is loaded.
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>' . esc_html( noblen_core_compat_problem() ) . '</p></div>';
		}
	);
	return;
}

require_once NOBLEN_CORE_DIR . 'includes/Autoloader.php';
Noblen\Core\Autoloader::register( NOBLEN_CORE_DIR . 'includes/' );

register_activation_hook( __FILE__, array( Noblen\Core\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Noblen\Core\Activator::class, 'deactivate' ) );

add_action( 'plugins_loaded', array( Noblen\Core\Plugin::class, 'boot' ) );

<?php
namespace Noblen\Core;

use Noblen\Core\Content\Types;
use Noblen\Core\Database\Migrator;

defined( 'ABSPATH' ) || exit;

/**
 * Activation and deactivation. Neither deletes any data.
 */
final class Activator {

	/**
	 * Activate: check requirements, then install roles, content types and tables.
	 */
	public static function activate( $network_wide = false ): void {
		$problem = function_exists( 'noblen_core_compat_problem' ) ? noblen_core_compat_problem() : '';
		if ( '' === $problem && ( $network_wide || is_multisite() ) ) {
			$problem = 'Noblen Corporate Core does not support multisite in this version.';
		}
		if ( '' !== $problem ) {
			deactivate_plugins( plugin_basename( NOBLEN_CORE_FILE ) );
			wp_die( esc_html( $problem ), 'Plugin activation stopped', array( 'back_link' => true ) );
		}

		Capabilities::install();
		Types::register();
		Types::seed_terms();
		$result = Migrator::run();
		if ( ! $result['ok'] && 'backup_required' !== $result['code'] ) {
			deactivate_plugins( plugin_basename( NOBLEN_CORE_FILE ) );
			wp_die( esc_html( 'Database setup failed: ' . $result['error'] ), 'Plugin activation stopped', array( 'back_link' => true ) );
		}
		update_option( 'noblen_core_version', NOBLEN_CORE_VERSION, false );
		flush_rewrite_rules();
	}

	/**
	 * Deactivate: removes nothing. Clears the migration lock and flushes rewrites.
	 */
	public static function deactivate(): void {
		delete_option( Migrator::OPT_LOCK );
		flush_rewrite_rules();
	}
}

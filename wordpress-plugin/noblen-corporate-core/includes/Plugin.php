<?php
namespace Noblen\Core;

use Noblen\Core\Clearance\Invalidation;
use Noblen\Core\Content\Types;
use Noblen\Core\Database\Migrator;
use Noblen\Core\Exposure\PublicInterfaces;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin bootstrap. The plugin works under any theme.
 */
final class Plugin {

	/**
	 * Wire everything. Runs on plugins_loaded.
	 */
	public static function boot(): void {
		add_action( 'init', array( Types::class, 'register' ), 5 );
		add_filter( 'use_block_editor_for_post_type', array( Types::class, 'classic_editor' ), 100, 2 );
		Invalidation::register();
		PublicInterfaces::register();

		require_once NOBLEN_CORE_DIR . 'includes/functions.php';

		if ( is_admin() ) {
			add_action( 'admin_init', array( self::class, 'admin_init' ) );
			Admin\Admin::register();
		}
	}

	/**
	 * Administration-only housekeeping. Upgrades never run on the front end.
	 */
	public static function admin_init(): void {
		if ( Migrator::installed_version() < 1 ) {
			Migrator::run(); // Fresh install: nothing to protect yet.
		}
		if ( NOBLEN_CORE_VERSION !== get_option( 'noblen_core_version' ) ) {
			Capabilities::install();
			Types::seed_terms();
			update_option( 'noblen_core_version', NOBLEN_CORE_VERSION, false );
		}
	}
}

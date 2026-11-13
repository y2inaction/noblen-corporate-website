<?php
namespace Noblen\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Minimal PSR-4 style autoloader for the Noblen\Core namespace.
 */
final class Autoloader {

	/**
	 * Register the autoloader.
	 *
	 * @param string $base_dir Directory that maps to the Noblen\Core namespace.
	 */
	public static function register( string $base_dir ): void {
		spl_autoload_register(
			static function ( string $class_name ) use ( $base_dir ): void {
				$prefix = 'Noblen\\Core\\';
				if ( 0 !== strpos( $class_name, $prefix ) ) {
					return;
				}
				$relative = substr( $class_name, strlen( $prefix ) );
				$file     = $base_dir . str_replace( '\\', '/', $relative ) . '.php';
				if ( is_readable( $file ) ) {
					require_once $file;
				}
			}
		);
	}
}

<?php
/**
 * PHPUnit bootstrap: loads a real WordPress (and therefore the active plugin)
 * against a disposable test database. No mocks.
 *
 * Set NOBLEN_WP_PATH to the WordPress directory.
 */

$wp_path = getenv( 'NOBLEN_WP_PATH' ) ?: '/home/user/wp-env/wordpress';
if ( ! is_readable( $wp_path . '/wp-load.php' ) ) {
	fwrite( STDERR, "WordPress not found at {$wp_path}. Set NOBLEN_WP_PATH.\n" );
	exit( 1 );
}

$_SERVER['HTTP_HOST']      = 'localhost';
$_SERVER['SERVER_NAME']    = 'localhost';
$_SERVER['REQUEST_URI']    = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';

require $wp_path . '/wp-load.php';

if ( ! function_exists( 'noblen_core_api_version' ) ) {
	fwrite( STDERR, "The Noblen Corporate Core plugin is not active in the test site.\n" );
	exit( 1 );
}

// One-time schema, roles and starter terms for the test site.
Noblen\Core\Capabilities::install();
Noblen\Core\Content\Types::seed_terms();
if ( Noblen\Core\Database\Migrator::installed_version() < Noblen\Core\Database\Schema::VERSION ) {
	Noblen\Core\Database\Migrator::confirm_backup( Noblen\Core\Database\Schema::VERSION, 0 );
	$r = Noblen\Core\Database\Migrator::run();
	if ( ! $r['ok'] ) {
		fwrite( STDERR, 'Migration failed: ' . $r['error'] . "\n" );
		exit( 1 );
	}
}

require_once __DIR__ . '/NoblenTestCase.php';

<?php
/**
 * The administration notice for a pending or failed database upgrade. It changes plugin options temporarily and restores them.
 * Usage: NOBLEN_WP_PATH=/path/to/wordpress php tests-cli/migration-notice-test.php (Noblen core plugin active; disposable site).
 */
$noblen_wp_dir = getenv( 'NOBLEN_WP_PATH' );
if ( ! $noblen_wp_dir || ! is_file( $noblen_wp_dir . '/wp-load.php' ) ) {
	fwrite( STDERR, "Set NOBLEN_WP_PATH to a WordPress directory.\n" );
	exit( 2 );
}
$_SERVER['HTTP_HOST'] = 'localhost';
require $noblen_wp_dir . '/wp-load.php';
if ( ! class_exists( 'Noblen\Core\Admin\Admin' ) ) {
	fwrite( STDERR, "The Noblen core plugin is not active.\n" );
	exit( 2 );
}
require_once ABSPATH . 'wp-admin/includes/user.php';
use Noblen\Core\Admin\Admin;
use Noblen\Core\Database\Migrator;

$pass = 0;
$fail = 0;
function t( bool $ok, string $name ): void {
	global $pass, $fail;
	$ok ? ++$pass : ++$fail;
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $name . "\n";
}
function notice_text(): string {
	ob_start();
	Admin::notices();
	return (string) ob_get_clean();
}
$version = get_option( Migrator::OPT_VERSION );
$state   = get_option( Migrator::OPT_STATE );

$admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
$editor = wp_insert_user( array( 'user_login' => 'notice_editor_' . wp_rand(), 'user_pass' => wp_generate_password(), 'user_email' => 'ne' . wp_rand() . '@example.invalid', 'role' => 'noblen_content_editor' ) );
if ( ! $admins || is_wp_error( $editor ) ) {
	fwrite( STDERR, "Could not prepare users.\n" );
	exit( 2 );
}

wp_set_current_user( $admins[0]->ID );
t( '' === trim( str_replace( array( "\n", "\t" ), '', preg_replace( '/<div class="notice[^>]*>.*?<\/div>/s', '', notice_text() ) ) ) && false === strpos( notice_text(), 'database upgrade' ), 'a current database shows no upgrade notice' );

update_option( Migrator::OPT_VERSION, 1, false );
$out = notice_text();
t( false !== strpos( $out, 'database upgrade is waiting' ) && false !== strpos( $out, 'wp noblen migrate confirm-backup' ) && false !== strpos( $out, 'wp noblen migrate run' ), 'a pending upgrade shows a warning that names both commands' );
t( false !== strpos( $out, 'notice-warning' ), 'a pending upgrade is a warning, not an error' );
t( false === strpos( $out, '<script' ), 'the notice contains no script' );

wp_set_current_user( $editor );
t( false === strpos( notice_text(), 'database upgrade' ), 'a user who may not manage settings does not see the upgrade notice' );
wp_set_current_user( 0 );
t( false === strpos( notice_text(), 'database upgrade' ), 'a visitor does not see it either' );

wp_set_current_user( $admins[0]->ID );
update_option( Migrator::OPT_STATE, array( 'state' => 'failed', 'step' => 2, 'error' => 'x' ), false );
$out = notice_text();
t( false !== strpos( $out, 'upgrade failed' ) && false !== strpos( $out, 'notice-error' ), 'a failed upgrade shows an error notice' );
t( false === strpos( $out, 'database upgrade is waiting' ), 'a failed upgrade does not also show the pending notice' );

update_option( Migrator::OPT_VERSION, (int) $version, false );
update_option( Migrator::OPT_STATE, array( 'state' => 'idle', 'step' => 0, 'error' => '' ), false );
t( false === strpos( notice_text(), 'database upgrade' ), 'after the upgrade and a clear state, the notice is gone' );

// Restore.
wp_delete_user( $editor );
update_option( Migrator::OPT_VERSION, $version, false );
if ( false === $state ) {
	delete_option( Migrator::OPT_STATE );
} else {
	update_option( Migrator::OPT_STATE, $state, false );
}
echo "\n{$pass} passed, {$fail} failed\n";
exit( $fail ? 1 : 0 );

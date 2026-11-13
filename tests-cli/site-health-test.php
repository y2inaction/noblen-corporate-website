<?php
/**
 * Site Health configuration checks: every rule and the roll-up, with synthetic facts, plus a read of the live site.
 * Usage: NOBLEN_WP_PATH=/path/to/wordpress php tests-cli/site-health-test.php (Noblen core plugin active). Read-only.
 */
$noblen_wp_dir = getenv( 'NOBLEN_WP_PATH' );
if ( ! $noblen_wp_dir || ! is_file( $noblen_wp_dir . '/wp-load.php' ) ) {
	fwrite( STDERR, "Set NOBLEN_WP_PATH to a WordPress directory.\n" );
	exit( 2 );
}
$_SERVER['HTTP_HOST'] = 'localhost';
require $noblen_wp_dir . '/wp-load.php';
if ( ! class_exists( 'Noblen\Core\Admin\SiteHealth' ) ) {
	fwrite( STDERR, "The Noblen core plugin is not active.\n" );
	exit( 2 );
}
use Noblen\Core\Admin\SiteHealth;

$pass = 0;
$fail = 0;
function t( bool $ok, string $name ): void {
	global $pass, $fail;
	$ok ? ++$pass : ++$fail;
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $name . "\n";
}
function status_of( array $rows, string $id ): string {
	foreach ( $rows as $r ) {
		if ( $id === $r['id'] ) {
			return $r['status'];
		}
	}
	return 'missing';
}

$good = array(
	'audit_state'         => 'verified',
	'migration_healthy'   => true,
	'debug_display'       => false,
	'https'               => true,
	'pretty_permalinks'   => true,
	'theme_active'        => true,
	'separation_duties'   => true,
	'remove_on_uninstall' => false,
	'search_visible'      => true,
);
$rows = SiteHealth::evaluate( $good );
t( 9 === count( $rows ), 'nine rules are evaluated' );
t( 'good' === SiteHealth::overall( $rows ), 'a healthy configuration is good overall' );

$cases = array(
	array( 'audit', array( 'audit_state' => 'key_missing' ), 'critical' ),
	array( 'audit', array( 'audit_state' => 'key_invalid' ), 'critical' ),
	array( 'audit', array( 'audit_state' => 'chain_broken' ), 'critical' ),
	array( 'audit', array( 'audit_state' => 'approvals_suspended' ), 'critical' ),
	array( 'audit', array( 'audit_state' => 'recovery_incomplete' ), 'critical' ),
	array( 'audit', array( 'audit_state' => 'rotation_incomplete' ), 'critical' ),
	array( 'audit', array( 'audit_state' => 'unknown' ), 'recommended' ),
	array( 'migration', array( 'migration_healthy' => false ), 'critical' ),
	array( 'debug', array( 'debug_display' => true ), 'recommended' ),
	array( 'https', array( 'https' => false ), 'recommended' ),
	array( 'permalinks', array( 'pretty_permalinks' => false ), 'recommended' ),
	array( 'theme', array( 'theme_active' => false ), 'recommended' ),
	array( 'duties', array( 'separation_duties' => false ), 'recommended' ),
	array( 'uninstall', array( 'remove_on_uninstall' => true ), 'recommended' ),
	array( 'search', array( 'search_visible' => false ), 'recommended' ),
);
foreach ( $cases as $c ) {
	$r = SiteHealth::evaluate( array_merge( $good, $c[1] ) );
	t( $c[2] === status_of( $r, $c[0] ), 'rule ' . $c[0] . ' with ' . wp_json_encode( $c[1] ) . ' is ' . $c[2] );
	$others_ok = true;
	foreach ( $r as $row ) {
		if ( $row['id'] !== $c[0] && 'good' !== $row['status'] ) {
			$others_ok = false;
		}
	}
	t( $others_ok, 'rule ' . $c[0] . ' changes no other rule' );
}
t( 'critical' === SiteHealth::overall( SiteHealth::evaluate( array_merge( $good, array( 'migration_healthy' => false, 'https' => false ) ) ) ), 'critical outranks recommended in the roll-up' );
t( 'recommended' === SiteHealth::overall( SiteHealth::evaluate( array_merge( $good, array( 'https' => false ) ) ) ), 'recommended outranks good in the roll-up' );
t( 'critical' === status_of( SiteHealth::evaluate( array() ), 'migration' ), 'missing facts fail closed (migration critical)' );

$res = SiteHealth::result();
t( isset( $res['label'], $res['status'], $res['badge']['color'], $res['description'], $res['actions'], $res['test'] ) && 'noblen_configuration' === $res['test'], 'result has the shape Site Health expects' );
t( ! str_contains( SiteHealth::result()['description'], '<script' ), 'result text is escaped' );
$env = SiteHealth::environment();
t( array_keys( $good ) === array_keys( $env ), 'environment() reports exactly the facts the rules need' );
$tests = SiteHealth::add_tests( array( 'direct' => array() ) );
t( isset( $tests['direct']['noblen_configuration']['test'] ) && is_callable( $tests['direct']['noblen_configuration']['test'] ), 'the test is added to Site Health and is callable' );

echo "\n{$pass} passed, {$fail} failed\n";
exit( $fail ? 1 : 0 );

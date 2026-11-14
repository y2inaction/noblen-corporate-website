<?php
/**
 * Home page section controls: order, visibility, empty-section behaviour and the partnerships extension point.
 *
 * Usage: NOBLEN_WP_PATH=/path/to/wordpress php tests-cli/home-sections-test.php
 * Needs a WordPress site with the Noblen theme active. It changes only theme settings and restores them.
 */
$noblen_wp_dir = getenv( 'NOBLEN_WP_PATH' );
if ( ! $noblen_wp_dir || ! is_file( $noblen_wp_dir . '/wp-load.php' ) ) {
	fwrite( STDERR, "Set NOBLEN_WP_PATH to a WordPress directory.\n" );
	exit( 2 );
}
$_SERVER['HTTP_HOST'] = 'localhost';
require $noblen_wp_dir . '/wp-load.php';
if ( ! function_exists( 'noblen_home_active_sections' ) ) {
	fwrite( STDERR, "The Noblen theme is not active on this site.\n" );
	exit( 2 );
}

$pass = 0;
$fail = 0;
function t( bool $ok, string $name, string $extra = '' ): void {
	global $pass, $fail;
	if ( $ok ) {
		++$pass;
		echo "ok   $name\n";
	} else {
		++$fail;
		echo "FAIL $name $extra\n";
	}
}
function render( string $id ): string {
	ob_start();
	noblen_render_home_section( $id );
	return (string) ob_get_clean();
}
function reset_mods(): void {
	foreach ( array_keys( noblen_home_registry() ) as $id ) {
		remove_theme_mod( 'noblen_home_order_' . $id );
		remove_theme_mod( 'noblen_home_show_' . $id );
	}
}

reset_mods();
$default = array_keys( noblen_home_registry() );
t( noblen_home_active_sections() === $default, 'default order equals the registry order' );
t( count( $default ) === 9 && in_array( 'partnerships', $default, true ), 'registry has nine sections including partnerships' );

set_theme_mod( 'noblen_home_show_industries', false );
t( ! in_array( 'industries', noblen_home_active_sections(), true ), 'a hidden section is not active' );
t( count( noblen_home_active_sections() ) === 8, 'hiding one section leaves eight' );
reset_mods();

set_theme_mod( 'noblen_home_order_cta', 1 );
t( noblen_home_active_sections()[0] === 'cta', 'a lower order number moves a section first' );
reset_mods();

set_theme_mod( 'noblen_home_order_group', 50 );
set_theme_mod( 'noblen_home_order_companies', 50 );
$a = noblen_home_active_sections();
t( array_search( 'group', $a, true ) < array_search( 'companies', $a, true ), 'ties keep the default order' );
reset_mods();

set_theme_mod( 'noblen_home_order_group', -5 );
set_theme_mod( 'noblen_home_order_cta', 100000 );
$a = noblen_home_active_sections();
t( 'group' === $a[0] && 'cta' === end( $a ), 'out-of-range order values are clamped' );
reset_mods();

set_theme_mod( 'noblen_home_order_group', 'abc' );
t( count( noblen_home_active_sections() ) === 9, 'a non-numeric order value does not lose or duplicate sections' );
reset_mods();

// Empty sections render nothing.
t( '' === render( 'partnerships' ), 'partnerships renders nothing by default' );
t( '' === render( 'nonexistent' ), 'an unknown section renders nothing' );

// Partnerships extension point.
add_filter(
	'noblen_partnership_items',
	static fn() => array(
		array(
			'title' => '<script>alert(1)</script>Partner',
			'url'   => 'https://example.com/p',
		),
		array( 'title' => 'No link' ),
		array( 'url' => 'https://example.com/q' ),
		'not an array',
	)
);
$out = render( 'partnerships' );
t( str_contains( $out, 'id="partnerships-h"' ), 'partnerships renders when verified items are supplied' );
t( ! str_contains( $out, '<script>' ), 'partner titles are escaped' );
t( ! str_contains( $out, 'No link' ), 'a partner without a URL is ignored' );
t( 1 === substr_count( $out, 'class="card ' ), 'only the one valid partner is rendered' );

// Call to action: the contact email when set, else the published Contact page, else nothing. No built-in fallback address.
$cta_out = render( 'cta' );
t( ! str_contains( $cta_out, 'noblenai' ), 'the call to action never contains a built-in fallback address' );
$cta_contact = get_page_by_path( 'contact', OBJECT, 'page' );
if ( '' === noblen_setting( 'contact_email' ) && ! ( $cta_contact && 'publish' === $cta_contact->post_status ) ) {
	t( '' === $cta_out, 'the call to action renders nothing without a contact email or a published Contact page' );
} else {
	t( str_contains( $cta_out, 'class="btn" href="' ), 'the call to action links to the contact email or the Contact page' );
}

reset_mods();
echo "\n$pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );

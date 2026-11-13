<?php
/**
 * SEO helpers: snippet trimming and the Organization data (owner-entered settings only).
 * Usage: NOBLEN_WP_PATH=/path/to/wordpress php tests-cli/seo-test.php (Noblen theme and core plugin active).
 * Temporarily changes the settings option and restores it.
 */
$noblen_wp_dir = getenv( 'NOBLEN_WP_PATH' );
if ( ! $noblen_wp_dir || ! is_file( $noblen_wp_dir . '/wp-load.php' ) ) {
	fwrite( STDERR, "Set NOBLEN_WP_PATH to a WordPress directory.\n" );
	exit( 2 );
}
$_SERVER['HTTP_HOST'] = 'localhost';
require $noblen_wp_dir . '/wp-load.php';
if ( ! function_exists( 'noblen_organization_data' ) || ! function_exists( 'noblen_has_core' ) || ! noblen_has_core() ) {
	fwrite( STDERR, "The Noblen theme and core plugin must be active.\n" );
	exit( 2 );
}
$pass = 0;
$fail = 0;
function t( bool $ok, string $name ): void {
	global $pass, $fail;
	$ok ? ++$pass : ++$fail;
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $name . "\n";
}

t( 'Short text.' === noblen_snippet( "  Short\n\n text. " ), 'whitespace is collapsed' );
t( 'Bold text' === noblen_snippet( '<p><b>Bold</b> text</p>' ), 'tags are stripped' );
$long = str_repeat( 'word ', 80 );
$cut  = noblen_snippet( $long );
t( mb_strlen( $cut ) <= 160 && str_ends_with( $cut, "\u{2026}" ) && ! str_contains( $cut, 'wor' . "\u{2026}" ), 'long text is cut at a word boundary with an ellipsis' );
t( '' === noblen_snippet( '   ' ), 'blank text gives an empty snippet' );
t( mb_strlen( noblen_snippet( str_repeat( "\u{00e9}", 400 ) ) ) <= 160, 'multibyte text is cut by characters, not bytes' );

$old = get_option( 'noblen_core_settings', false );
update_option( 'noblen_core_settings', array(), false );
$org = noblen_organization_data();
t( isset( $org['name'], $org['url'] ) && ! isset( $org['email'], $org['telephone'], $org['address'], $org['sameAs'] ), 'with no settings the Organization carries only name and URL' );

update_option(
	'noblen_core_settings',
	array(
		'company_name'    => 'Example Co',
		'contact_email'   => 'info@example.com',
		'contact_address' => "1 Example Road\nExample City",
		'social_linkedin' => 'https://www.linkedin.com/company/example',
	),
	false
);
$org = noblen_organization_data();
t( 'Example Co' === $org['name'] && 'info@example.com' === $org['email'], 'entered name and email are used' );
t( '1 Example Road, Example City' === $org['address'], 'a multi-line address is flattened to one line' );
t( array( 'https://www.linkedin.com/company/example' ) === $org['sameAs'], 'only entered social links appear in sameAs' );
t( ! isset( $org['telephone'] ), 'an empty phone is omitted' );
$json = wp_json_encode( array( 'x' => '</script><b>' ), JSON_HEX_TAG | JSON_HEX_AMP );
t( ! str_contains( $json, '</script>' ), 'JSON-LD encoding escapes a closing script tag' );

add_filter( 'noblen_seo_disabled', '__return_true' );
t( noblen_seo_plugin_active(), 'the noblen_seo_disabled filter switches the tags off' );

if ( false === $old ) {
	delete_option( 'noblen_core_settings' );
} else {
	update_option( 'noblen_core_settings', $old, false );
}
echo "\n{$pass} passed, {$fail} failed\n";
exit( $fail ? 1 : 0 );

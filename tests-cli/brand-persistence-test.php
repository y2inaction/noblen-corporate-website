<?php
/**
 * Brand settings: persistence across a theme switch, graceful behaviour with nothing configured, and resistance to bad stored
 * values (CSS injection). Changes theme settings temporarily and restores them and the active theme.
 * Usage: NOBLEN_WP_PATH=/path/to/wordpress php tests-cli/brand-persistence-test.php (Noblen theme active; another theme installed).
 */
$noblen_wp_dir = getenv( 'NOBLEN_WP_PATH' );
if ( ! $noblen_wp_dir || ! is_file( $noblen_wp_dir . '/wp-load.php' ) ) {
	fwrite( STDERR, "Set NOBLEN_WP_PATH to a WordPress directory.\n" );
	exit( 2 );
}
$_SERVER['HTTP_HOST'] = 'localhost';
require $noblen_wp_dir . '/wp-load.php';
if ( ! function_exists( 'noblen_brand_css' ) ) {
	fwrite( STDERR, "The Noblen theme must be active.\n" );
	exit( 2 );
}
$pass = 0;
$fail = 0;
function t( bool $ok, string $name, string $extra = '' ): void {
	global $pass, $fail;
	$ok ? ++$pass : ++$fail;
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $name . ( $ok || '' === $extra ? '' : ' :: ' . $extra ) . "\n";
}
$slug     = get_stylesheet();
$original = get_option( 'theme_mods_' . $slug, false );
$defaults = noblen_brand_defaults();

// ---- Nothing configured: defaults, no logo, no failure.
delete_option( 'theme_mods_' . $slug );
t( noblen_brand_palette() === $defaults, 'with no settings the palette is exactly the defaults' );
t( 'light' === noblen_brand_mode(), 'with no settings the appearance is light' );
t( null === noblen_logo_data(), 'with no settings there is no logo data' );
$html = noblen_logo_html();
t( str_contains( $html, 'brand__text' ) && ! str_contains( $html, '<img' ), 'with no logo the brand link is the text wordmark' );
t( 48 === noblen_logo_height( 'desktop' ) && 36 === noblen_logo_height( 'mobile' ), 'default logo heights are 48 and 36 pixels' );
t( array() === noblen_brand_saved_presets(), 'there are no saved presets' );
t( noblen_brand_css( $defaults ) === noblen_brand_css( noblen_brand_palette() ), 'the stylesheet text for the defaults is stable' );

// ---- A logo that no longer exists (deleted attachment) falls back gracefully.
set_theme_mod( 'custom_logo', 999999999 );
t( null === noblen_logo_data() && str_contains( noblen_logo_html(), 'brand__text' ), 'a missing logo attachment falls back to the wordmark' );
set_theme_mod( 'custom_logo', 'not-a-number' );
t( null === noblen_logo_data(), 'a non-numeric logo setting is ignored' );
remove_theme_mod( 'custom_logo' );

// ---- Bad stored values can never reach the stylesheet.
$evil = array(
	'primary'     => 'red;}body{display:none}',
	'secondary'   => '#fff;} *{background:url(//evil.example/x)} .a{',
	'accent'      => array( '#123456' ),
	'heading'     => '</style><script>alert(1)</script>',
	'text'        => "#abc\n}",
	'bg'          => 'javascript:alert(1)',
	'surface'     => 42,
	'link'        => null,
	'button_bg'   => '#12345',
	'footer_bg'   => 'url(http://evil.example)',
	'header_text' => '#FFF',
);
foreach ( $evil as $k => $v ) {
	set_theme_mod( 'noblen_brand_' . $k, $v );
}
$css = noblen_brand_css( noblen_brand_palette() );
t( ! preg_match( '/evil|script|javascript|display:none|url\(|<|>|\n}/i', str_replace( "}\n", '}', $css ) ), 'hostile stored values never appear in the stylesheet', $css );
t( preg_match( '/^[^<>]*$/s', $css ) === 1, 'the stylesheet text contains no markup characters' );
$p = noblen_brand_palette();
t( $p['primary'] === $defaults['primary'] && $p['bg'] === $defaults['bg'] && $p['accent'] === $defaults['accent'], 'invalid values fall back to the defaults' );
t( '#ffffff' === $p['header_text'], 'a valid short hex value is accepted and normalised' );
t( noblen_hex( "#abc\n}" ) === null && noblen_hex( '#12345' ) === null && noblen_hex( array() ) === null, 'the hex validator rejects malformed input' );
t( '#000000' === noblen_sanitize_brand_color( 'nonsense' ) && '#0a1b3d' === noblen_sanitize_brand_color( 'bad', (object) array( 'default' => '#0A1B3D' ) ), 'the Customizer sanitiser falls back to the setting default' );
foreach ( array_keys( $evil ) as $k ) {
	remove_theme_mod( 'noblen_brand_' . $k );
}
set_theme_mod( 'noblen_brand_appearance', '<b>dark</b>' );
t( 'light' === noblen_brand_mode(), 'an unknown appearance value falls back to light' );
remove_theme_mod( 'noblen_brand_appearance' );
set_theme_mod( 'noblen_logo_height', 'abc' );
set_theme_mod( 'noblen_logo_height_mobile', 100000 );
t( 24 === noblen_logo_height( 'desktop' ) && 80 === noblen_logo_height( 'mobile' ), 'logo heights are clamped to their limits' );
remove_theme_mod( 'noblen_logo_height' );
remove_theme_mod( 'noblen_logo_height_mobile' );
set_theme_mod( 'noblen_brand_presets', array( 'x' => 'not an array', '../bad' => array( 'name' => 'a', 'colors' => array() ), 'ok' => array( 'name' => '<b>Mine</b>', 'colors' => array( 'primary' => '#112233', 'accent' => 'bad' ) ) ) );
$presets = noblen_brand_saved_presets();
t( array( 'ok' ) === array_keys( $presets ) && 'Mine' === $presets['ok']['name'] && '#112233' === $presets['ok']['colors']['primary'] && $defaults['accent'] === $presets['ok']['colors']['accent'], 'malformed saved presets are dropped and the rest are sanitised' );
remove_theme_mod( 'noblen_brand_presets' );

// ---- Persistence: settings are theme modifications, so they survive a theme switch and a theme file update.
set_theme_mod( 'noblen_brand_primary', '#112233' );
set_theme_mod( 'noblen_brand_appearance', 'auto' );
set_theme_mod( 'noblen_logo_alt', 'Persistent alt' );
set_theme_mod( 'noblen_brand_presets', array( 'mine' => array( 'name' => 'Mine', 'colors' => array( 'primary' => '#445566' ) ) ) );
$stored = get_option( 'theme_mods_' . $slug );
t( is_array( $stored ) && '#112233' === ( $stored['noblen_brand_primary'] ?? '' ), 'settings are stored in the theme modifications option (not in theme files)' );
$others = array_diff( array_keys( wp_get_themes() ), array( $slug ) );
if ( $others ) {
	$other = reset( $others );
	switch_theme( $other );
	t( get_stylesheet() === $other, "switched to another theme ($other)" );
	t( is_array( get_option( 'theme_mods_' . $slug ) ) && '#112233' === get_option( 'theme_mods_' . $slug )['noblen_brand_primary'], 'the stored settings are untouched while another theme is active' );
	switch_theme( $slug );
	t( get_stylesheet() === $slug, 'switched back to the Noblen theme' );
	t( '#112233' === noblen_brand_palette()['primary'] && 'auto' === noblen_brand_mode() && 'Persistent alt' === get_theme_mod( 'noblen_logo_alt' ) && isset( noblen_brand_saved_presets()['mine'] ), 'colours, appearance, logo text and saved presets are all intact after switching back' );
} else {
	t( true, 'no second theme is installed, so the switch test is skipped' );
}

// ---- Restore.
if ( false === $original ) {
	delete_option( 'theme_mods_' . $slug );
} else {
	update_option( 'theme_mods_' . $slug, $original );
}
echo "\n{$pass} passed, {$fail} failed\n";
exit( $fail ? 1 : 0 );

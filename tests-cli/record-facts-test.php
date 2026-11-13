<?php
/**
 * Record facts panel: shows only supplied public values, escapes everything, and prints nothing when empty.
 *
 * Usage: NOBLEN_WP_PATH=/path/to/wordpress php tests-cli/record-facts-test.php (Noblen theme must be active).
 */
$noblen_wp_dir = getenv( 'NOBLEN_WP_PATH' );
if ( ! $noblen_wp_dir || ! is_file( $noblen_wp_dir . '/wp-load.php' ) ) {
	fwrite( STDERR, "Set NOBLEN_WP_PATH to a WordPress directory.\n" );
	exit( 2 );
}
$_SERVER['HTTP_HOST'] = 'localhost';
require $noblen_wp_dir . '/wp-load.php';
if ( ! function_exists( 'noblen_record_facts' ) ) {
	fwrite( STDERR, "The Noblen theme is not active on this site.\n" );
	exit( 2 );
}
$pass = 0;
$fail = 0;
function t( bool $ok, string $name ): void {
	global $pass, $fail;
	$ok ? ++$pass : ++$fail;
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $name . "\n";
}
function facts_html( array $item ): string {
	ob_start();
	noblen_record_facts( $item );
	return (string) ob_get_clean();
}

t( '' === facts_html( array() ), 'no facts prints nothing' );
t( '' === facts_html( array( 'client' => '', 'business_unit' => null, 'terms' => array() ) ), 'empty values print nothing' );

$html = facts_html(
	array(
		'client'        => '<b onmouseover=x>Client</b>',
		'business_unit' => array(
			'title' => 'Unit <i>one</i>',
			'url'   => 'javascript:alert(1)',
		),
		'terms'         => array(
			array(
				'taxonomy' => 'noblen_area',
				'name'     => 'Area <script>',
				'url'      => 'https://example.com/a',
			),
			array(
				'taxonomy' => 'noblen_area',
				'name'     => 'Second',
				'url'      => '',
			),
			array( 'taxonomy' => 'noblen_area' ),
		),
	)
);
t( str_contains( $html, '<dl class="facts">' ), 'panel renders when facts exist' );
t( ! str_contains( $html, '<b ' ) && ! str_contains( $html, '<script>' ) && ! str_contains( $html, '<i>' ), 'values are escaped' );
t( ! str_contains( $html, 'javascript:' ) && ! str_contains( $html, 'href=""' ), 'a javascript: URL is dropped without leaving an empty link' );
t( 3 === substr_count( $html, 'class="fact"' ), 'one fact each for business unit, client and the term group' );
t( str_contains( $html, 'Second' ) && str_contains( $html, 'Area &lt;script&gt;' ), 'terms are joined, with and without links' );
t( 1 === substr_count( $html, '<a href="https://example.com/a">' ), 'a term link is kept when a URL is given' );

echo "\n$pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );

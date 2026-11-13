<?php
// Prints the WordPress debug log, or says clearly that it holds no entries, so a diagnostics artifact is never empty.
$file = WP_CONTENT_DIR . '/debug.log';
if ( is_readable( $file ) && filesize( $file ) > 0 ) {
	readfile( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
} else {
	echo "(debug.log has no entries: WordPress logged no PHP errors, warnings or notices)\n";
}

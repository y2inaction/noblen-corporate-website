<?php
// Prints the number of PHP errors, warnings, notices and deprecations recorded in the WordPress debug log.
$file = WP_CONTENT_DIR . '/debug.log';
$n    = 0;
if ( is_readable( $file ) ) {
	foreach ( file( $file ) as $line ) {
		if ( preg_match( '/PHP (Fatal error|Warning|Notice|Deprecated|Parse error)/', $line ) ) {
			++$n;
		}
	}
}
echo $n . "\n";

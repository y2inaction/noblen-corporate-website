<?php
$file = WP_CONTENT_DIR . '/debug.log';
if ( is_file( $file ) ) {
	file_put_contents( $file, '' );
}
echo "log reset\n";

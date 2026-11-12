<?php
// Attempts to publish the record whose ID is held in the option noblen_probe_id, as WP-CLI code would, and prints the resulting status.
$id = (int) get_option( 'noblen_probe_id' );
wp_publish_post( $id );
clean_post_cache( $id );
echo get_post_status( $id ) . "\n";

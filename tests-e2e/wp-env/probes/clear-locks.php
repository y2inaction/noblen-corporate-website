<?php
// WordPress keeps a post lock for about 150 seconds. The browser test switches between simulated users, so clear it.
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_edit_lock'" ); // phpcs:ignore WordPress.DB
echo "locks cleared\n";

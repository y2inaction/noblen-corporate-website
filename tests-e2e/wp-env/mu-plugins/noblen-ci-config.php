<?php
/**
 * Must-use plugin for the DISPOSABLE wp-env test site only. Never install on a real site.
 * Supplies a throwaway audit key (a real site configures its own outside the repository)
 * and keeps the test site free of outbound requests.
 */
define( 'NOBLEN_AUDIT_KEYS', array( 'wpenv1' => 'wp-env-throwaway-test-key-0123456789abcdef' ) );
define( 'NOBLEN_AUDIT_KEY_ID', 'wpenv1' );
// Marks this as the disposable test site: the browser-test seed script refuses to run anywhere else.
define( 'NOBLEN_E2E_SITE', true );
if ( ! defined( 'WP_HTTP_BLOCK_EXTERNAL' ) ) {
	define( 'WP_HTTP_BLOCK_EXTERNAL', true );
}

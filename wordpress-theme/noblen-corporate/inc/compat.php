<?php
/**
 * Activation guard: refuse unsupported PHP or WordPress with a plain message.
 *
 * @package noblen-corporate
 */

defined( 'ABSPATH' ) || exit;

/**
 * Describe an unsupported environment, or return an empty string when supported.
 *
 * @param string|null $php PHP version (defaults to the running one).
 * @param string|null $wp  WordPress version (defaults to the running one).
 */
function noblen_theme_compat_problem( ?string $php = null, ?string $wp = null ): string {
	$php = $php ?? PHP_VERSION;
	$wp  = $wp ?? ( $GLOBALS['wp_version'] ?? '' );
	if ( version_compare( $php, '8.3', '<' ) ) {
		return 'The Noblen Corporate theme requires PHP 8.3 or newer. This site runs PHP ' . $php . '.';
	}
	if ( '' !== $wp && version_compare( $wp, '6.4', '<' ) ) {
		return 'The Noblen Corporate theme requires WordPress 6.4 or newer. This site runs WordPress ' . $wp . '.';
	}
	return '';
}

add_action(
	'after_switch_theme',
	static function () {
		$problem = noblen_theme_compat_problem();
		if ( '' === $problem ) {
			return;
		}
		switch_theme( WP_DEFAULT_THEME );
		set_transient( 'noblen_theme_compat_notice', $problem, 120 );
	}
);

add_action(
	'admin_notices',
	static function () {
		$msg = get_transient( 'noblen_theme_compat_notice' );
		if ( $msg ) {
			delete_transient( 'noblen_theme_compat_notice' );
			echo '<div class="notice notice-error"><p>' . esc_html( (string) $msg ) . '</p></div>';
		}
	}
);

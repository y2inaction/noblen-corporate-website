<?php
/**
 * C01-004: the theme refuses unsupported PHP or WordPress with a message.
 */
class ThemeCompatTest extends NoblenTestCase {

	public function test_C01_004_theme_guard_messages(): void {
		require_once dirname( __DIR__, 4 ) . '/wordpress-theme/noblen-corporate/inc/compat.php';
		$this->assertSame( '', noblen_theme_compat_problem( '8.3.0', '6.4' ) );
		$this->assertStringContainsString( 'PHP 8.3', noblen_theme_compat_problem( '8.2.9', '6.4.3' ) );
		$this->assertStringContainsString( 'WordPress 6.4', noblen_theme_compat_problem( '8.4.1', '6.3.9' ) );
	}
}

<?php
class SmokeTest extends NoblenTestCase {
	public function test_plugin_active_and_tables_exist(): void {
		$this->assertSame( 1, noblen_core_api_version() );
		foreach ( Noblen\Core\Database\Schema::table_names() as $t ) {
			$this->assertTrue( Noblen\Core\Database\Schema::exists( $t ), $t );
		}
		foreach ( Noblen\Core\Content\Types::all() as $type ) {
			$this->assertTrue( post_type_exists( $type ), $type );
		}
	}
}

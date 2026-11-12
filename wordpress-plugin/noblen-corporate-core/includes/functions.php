<?php
/**
 * Public API for themes. Everything here returns allowlisted public data only.
 *
 * @package Noblen\Core
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'noblen_core_item' ) ) {
	/**
	 * Public data for one record, or null.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string,mixed>|null
	 */
	function noblen_core_item( $post_id ) {
		return Noblen\Core\PublicData\Accessor::get( (int) $post_id );
	}
}

if ( ! function_exists( 'noblen_core_items' ) ) {
	/**
	 * Public data for eligible records of a type.
	 *
	 * @param string              $type Post type.
	 * @param array<string,mixed> $args Query arguments.
	 * @return array<int,array<string,mixed>>
	 */
	function noblen_core_items( $type, $args = array() ) {
		return Noblen\Core\PublicData\Accessor::query( (string) $type, (array) $args );
	}
}

if ( ! function_exists( 'noblen_core_setting' ) ) {
	/**
	 * One business setting.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	function noblen_core_setting( $key ) {
		return Noblen\Core\Settings\Settings::get( (string) $key );
	}
}

if ( ! function_exists( 'noblen_core_settings' ) ) {
	/**
	 * All business settings.
	 *
	 * @return array<string,string>
	 */
	function noblen_core_settings() {
		return Noblen\Core\Settings\Settings::all();
	}
}

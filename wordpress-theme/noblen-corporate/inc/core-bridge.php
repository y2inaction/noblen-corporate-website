<?php
/**
 * The only place the theme talks to the core plugin.
 * Without the plugin, every helper returns an empty value and sections hide.
 *
 * @package noblen-corporate
 */

defined( 'ABSPATH' ) || exit;

/** Whether a compatible core plugin is active. */
function noblen_has_core(): bool {
	return function_exists( 'noblen_core_api_version' ) && 1 === (int) noblen_core_api_version();
}

/**
 * Public records of a type, already filtered to what may be shown.
 *
 * @param string              $type Post type.
 * @param array<string,mixed> $args Query arguments.
 * @return array<int,array<string,mixed>>
 */
function noblen_items( string $type, array $args = array() ): array {
	return noblen_has_core() ? (array) noblen_core_items( $type, $args ) : array();
}

/** One public record, or null. @param int $id Post ID. */
function noblen_item( int $id ): ?array {
	return noblen_has_core() ? noblen_core_item( $id ) : null;
}

/** A business setting; empty when unset. @param string $key Key. */
function noblen_setting( string $key ): string {
	return noblen_has_core() ? (string) noblen_core_setting( $key ) : '';
}

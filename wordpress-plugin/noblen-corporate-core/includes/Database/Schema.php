<?php
namespace Noblen\Core\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Table definitions for the private clearance, approval and audit storage.
 *
 * These tables are owned by the plugin. They are not posts, post meta, options
 * or attachments, so WordPress's query, search, REST, feed and export systems
 * have no path to them.
 */
final class Schema {

	/** Latest schema version. */
	public const VERSION = 2;

	/**
	 * Full table name.
	 *
	 * @param string $name Short name.
	 */
	public static function table( string $name ): string {
		global $wpdb;
		return $wpdb->prefix . 'noblen_' . $name;
	}

	/**
	 * Short names of all plugin tables.
	 *
	 * @return string[]
	 */
	public static function table_names(): array {
		return array( 'clearance', 'media_rights', 'approvals', 'epochs', 'audit' );
	}

	/**
	 * Table definitions as dbDelta SQL for a schema version.
	 *
	 * @param int $version Schema version (1 or 2).
	 */
	public static function sql( int $version = self::VERSION ): string {
		global $wpdb;
		$c = $wpdb->get_charset_collate();
		$p = $wpdb->prefix . 'noblen_';

		$approvals_key = ( $version >= 2 ) ? ",\n  KEY post_status (post_id,status)" : '';

		return "CREATE TABLE {$p}clearance (
  post_id bigint(20) unsigned NOT NULL,
  status varchar(20) NOT NULL DEFAULT 'incomplete',
  items longtext NOT NULL,
  evidence_ref text NULL,
  recorded_by bigint(20) unsigned NOT NULL DEFAULT 0,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (post_id)
) {$c};
CREATE TABLE {$p}media_rights (
  attachment_id bigint(20) unsigned NOT NULL,
  file_checksum char(64) NOT NULL,
  basis_ref text NULL,
  confirmed_by bigint(20) unsigned NOT NULL DEFAULT 0,
  confirmed_at datetime NOT NULL,
  PRIMARY KEY  (attachment_id,file_checksum)
) {$c};
CREATE TABLE {$p}approvals (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  post_id bigint(20) unsigned NOT NULL,
  status varchar(20) NOT NULL DEFAULT 'submitted',
  snapshot_hash char(64) NOT NULL,
  format_version smallint(5) unsigned NOT NULL DEFAULT 1,
  snapshot_text longtext NOT NULL,
  approver_id bigint(20) unsigned NOT NULL DEFAULT 0,
  last_editor_id bigint(20) unsigned NOT NULL DEFAULT 0,
  clearance_by bigint(20) unsigned NOT NULL DEFAULT 0,
  decided_at datetime NULL,
  created_at datetime NOT NULL,
  note text NULL,
  PRIMARY KEY  (id),
  KEY post_id (post_id){$approvals_key}
) {$c};
CREATE TABLE {$p}epochs (
  post_id bigint(20) unsigned NOT NULL,
  epoch bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (post_id)
) {$c};
CREATE TABLE {$p}audit (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  post_id bigint(20) unsigned NOT NULL DEFAULT 0,
  event_type varchar(40) NOT NULL,
  actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
  actor_login varchar(60) NOT NULL DEFAULT '',
  event_time datetime NOT NULL,
  approval_id bigint(20) unsigned NOT NULL DEFAULT 0,
  snapshot_hash char(64) NOT NULL DEFAULT '',
  detail longtext NOT NULL,
  key_id varchar(40) NOT NULL DEFAULT '',
  key_fingerprint varchar(16) NOT NULL DEFAULT '',
  prev_hash char(64) NOT NULL,
  record_hash char(64) NOT NULL,
  PRIMARY KEY  (id),
  KEY post_id (post_id),
  KEY event_type (event_type)
) {$c};";
	}

	/**
	 * Whether a table exists.
	 *
	 * @param string $name Short table name.
	 */
	public static function exists( string $name ): bool {
		global $wpdb;
		$table = self::table( $name );
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Whether an index exists on a table.
	 *
	 * @param string $name  Short table name.
	 * @param string $index Index name.
	 */
	public static function has_index( string $name, string $index ): bool {
		global $wpdb;
		$table = self::table( $name );
		$rows  = $wpdb->get_results( "SHOW INDEX FROM `{$table}`", ARRAY_A ); // phpcs:ignore WordPress.DB
		foreach ( (array) $rows as $row ) {
			if ( isset( $row['Key_name'] ) && $row['Key_name'] === $index ) {
				return true;
			}
		}
		return false;
	}
}

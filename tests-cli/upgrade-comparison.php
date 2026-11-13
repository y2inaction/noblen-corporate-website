<?php
/**
 * C26-003 to C26-005 tooling: upgrade the plugin database from a schema-version-1 FIXTURE to the current version and
 * compare a normalised logical dataset before and after against a declared manifest. Also C26-004 (audit chain verifies
 * with the key before and after).
 *
 * THIS TESTS THE TOOLING AND THE MIGRATION ON A CODE-BUILT FIXTURE. Schema version 1 was never released. The run is not
 * the registered source/target comparison of criterion 26, and it records no acceptance.
 *
 * DESTRUCTIVE: it deletes every Noblen record and empties the plugin tables of the site it runs on.
 * It refuses to run unless NOBLEN_UPGRADE_FIXTURE_RESET=1 is set and the site is a local test site.
 *
 * Usage: NOBLEN_UPGRADE_FIXTURE_RESET=1 NOBLEN_WP_PATH=/path/to/wordpress php tests-cli/upgrade-comparison.php [manifest.json]
 */
$noblen_wp_dir = getenv( 'NOBLEN_WP_PATH' );
if ( '1' !== getenv( 'NOBLEN_UPGRADE_FIXTURE_RESET' ) || ! $noblen_wp_dir || ! is_file( $noblen_wp_dir . '/wp-load.php' ) ) {
	fwrite( STDERR, "Refusing to run: set NOBLEN_UPGRADE_FIXTURE_RESET=1 and NOBLEN_WP_PATH. This deletes all Noblen data on that site.\n" );
	exit( 2 );
}
$_SERVER['HTTP_HOST'] = 'localhost';
require $noblen_wp_dir . '/wp-load.php';
if ( ! class_exists( 'Noblen\Core\Database\Migrator' ) ) {
	fwrite( STDERR, "The Noblen core plugin is not active.\n" );
	exit( 2 );
}
$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
if ( ! in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true ) ) {
	fwrite( STDERR, "Refusing to run on a non-local site ({$host}).\n" );
	exit( 2 );
}

use Noblen\Core\Audit\AuditLog;
use Noblen\Core\Audit\Verifier;
use Noblen\Core\Database\Migrator;
use Noblen\Core\Database\Schema;

$pass = 0;
$fail = 0;
function t( bool $ok, string $name, string $extra = '' ): void {
	global $pass, $fail;
	$ok ? ++$pass : ++$fail;
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $name . ( $ok || '' === $extra ? '' : ' :: ' . $extra ) . "\n";
}

$manifest_path = $argv[1] ?? __DIR__ . '/upgrade/manifest-db-1-to-2.json';
$manifest_raw  = (string) file_get_contents( $manifest_path );
$manifest      = json_decode( $manifest_raw, true );
echo 'manifest sha256 ' . hash( 'sha256', $manifest_raw ) . "\n";

// ---- Manifest validity: no wildcards, every required part present.
function has_wildcard( $node ): bool {
	if ( is_array( $node ) ) {
		foreach ( $node as $k => $v ) {
			if ( ( is_string( $k ) && false !== strpbrk( $k, '*?' ) ) || has_wildcard( $v ) ) {
				return true;
			}
		}
		return false;
	}
	return is_string( $node ) && false !== strpbrk( $node, '*?' );
}
t( is_array( $manifest ) && 1 === ( $manifest['schema_version'] ?? 0 ), 'manifest parses and has schema_version 1' );
t( is_array( $manifest ) && ! has_wildcard( $manifest ), 'manifest contains no wildcards' );
if ( ! is_array( $manifest ) ) {
	exit( 1 );
}

// ---- Test key (kept out of the site configuration).
$keys = array( 'k1' => 'upgrade-fixture-secret-000000000000' );
add_filter( 'noblen_core_audit_keys', static fn() => $keys );
add_filter( 'noblen_core_audit_key_id', static fn() => 'k1' );

// ---- Logical export: the same probe the theme-switch tests use, captured as data.
function export_logical( int $audit_upto = 0 ): array {
	update_option( 'noblen_probe_audit_upto', $audit_upto, false );
	ob_start();
	include dirname( __DIR__ ) . '/tests-e2e/wp-env/probes/logical-export.php';
	$text = (string) ob_get_clean();
	delete_option( 'noblen_probe_audit_upto' );
	$out = array();
	foreach ( explode( "\n", trim( $text ) ) as $line ) {
		list( $k, $v ) = array_pad( explode( ' ', $line, 2 ), 2, '' );
		$out[ $k ]     = $v;
	}
	return $out;
}
function noblen_options(): array {
	global $wpdb;
	$rows = $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'noblen\_core\_%'", ARRAY_A ); // phpcs:ignore WordPress.DB
	$out  = array();
	foreach ( $rows as $r ) {
		$out[ $r['option_name'] ] = $r['option_value'];
	}
	ksort( $out );
	return $out;
}

// ---- Build the schema-version-1 fixture.
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_type LIKE 'noblen\_%'" ); // phpcs:ignore WordPress.DB
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '\_noblen\_%'" ); // phpcs:ignore WordPress.DB
$wpdb->query( "DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL" ); // phpcs:ignore WordPress.DB
foreach ( Schema::table_names() as $name ) {
	$wpdb->query( 'DROP TABLE IF EXISTS `' . Schema::table( $name ) . '`' ); // phpcs:ignore WordPress.DB
}
foreach ( noblen_options() as $name => $unused ) {
	delete_option( $name );
}
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
dbDelta( Schema::sql( 1 ) );
update_option( Migrator::OPT_VERSION, 1, false );
update_option( 'noblen_core_settings', array( 'company_name' => 'Fixture Company', 'tagline' => 'Fixture tagline' ), false );
t( 1 === Migrator::installed_version() && ! Schema::has_index( 'approvals', 'post_status' ), 'fixture is at schema version 1 without the version-2 index' );

$company  = wp_insert_post( array( 'post_type' => 'noblen_company', 'post_status' => 'publish', 'post_title' => 'Fixture Unit', 'post_content' => 'Body of the fixture unit.' ) );
$project  = wp_insert_post( array( 'post_type' => 'noblen_project', 'post_status' => 'publish', 'post_title' => 'Fixture Project', 'post_content' => 'Body of the fixture project.' ) );
$draft    = wp_insert_post( array( 'post_type' => 'noblen_project', 'post_status' => 'draft', 'post_title' => 'Fixture Draft', 'post_content' => '' ) );
$service  = wp_insert_post( array( 'post_type' => 'noblen_service', 'post_status' => 'draft', 'post_title' => 'Fixture Service', 'post_content' => '' ) );
update_post_meta( $project, '_noblen_f_tagline', 'Project tagline' );
update_post_meta( $project, '_noblen_f_overview', 'Project overview text' );
update_post_meta( $draft, '_noblen_f_tagline', 'Draft tagline' );
add_post_meta( $project, '_noblen_rel_business_unit', $company );
add_post_meta( $project, '_noblen_rel_services', $service );
add_post_meta( $project, '_noblen_rel_services', $draft );
$now = gmdate( 'Y-m-d H:i:s', 1700000000 );
$wpdb->insert( Schema::table( 'clearance' ), array( 'post_id' => $project, 'status' => 'complete', 'items' => '{"rights":"confirmed"}', 'evidence_ref' => 'fixture-evidence', 'recorded_by' => 1, 'updated_at' => $now ) ); // phpcs:ignore WordPress.DB
$wpdb->insert( Schema::table( 'clearance' ), array( 'post_id' => $draft, 'status' => 'incomplete', 'items' => '{}', 'recorded_by' => 1, 'updated_at' => $now ) ); // phpcs:ignore WordPress.DB
$wpdb->insert( Schema::table( 'approvals' ), array( 'post_id' => $project, 'status' => 'approved', 'snapshot_hash' => str_repeat( 'a', 64 ), 'format_version' => 1, 'snapshot_text' => '{"fixture":true}', 'approver_id' => 2, 'last_editor_id' => 1, 'clearance_by' => 3, 'decided_at' => $now, 'created_at' => $now, 'note' => 'fixture' ) ); // phpcs:ignore WordPress.DB
$wpdb->insert( Schema::table( 'approvals' ), array( 'post_id' => $draft, 'status' => 'submitted', 'snapshot_hash' => str_repeat( 'b', 64 ), 'format_version' => 1, 'snapshot_text' => '{"fixture":"draft"}', 'approver_id' => 0, 'last_editor_id' => 1, 'clearance_by' => 0, 'created_at' => $now ) ); // phpcs:ignore WordPress.DB
$wpdb->insert( Schema::table( 'media_rights' ), array( 'attachment_id' => 9001, 'file_checksum' => str_repeat( 'c', 64 ), 'basis_ref' => 'fixture-basis', 'confirmed_by' => 3, 'confirmed_at' => $now ) ); // phpcs:ignore WordPress.DB
$wpdb->insert( Schema::table( 'epochs' ), array( 'post_id' => $project, 'epoch' => 2 ) ); // phpcs:ignore WordPress.DB
AuditLog::append( 'record_submitted', array( 'post_id' => $project, 'actor_id' => 0, 'detail' => array( 'fixture' => 1 ) ) );
AuditLog::append( 'record_approved', array( 'post_id' => $project, 'actor_id' => 0, 'detail' => array( 'fixture' => 2 ) ) );
AuditLog::append( 'record_submitted', array( 'post_id' => $draft, 'actor_id' => 0, 'detail' => array( 'fixture' => 3 ) ) );
update_option( Migrator::OPT_STATE, array( 'state' => 'idle', 'step' => 0, 'error' => '' ), false );

// ---- Before.
$before_audit  = Verifier::verify();
$before_export = export_logical();
$audit_max     = (int) $before_export['audit_max'];
$before_opts   = noblen_options();
$before_rows   = array();
foreach ( AuditLog::all() as $r ) {
	$before_rows[] = $r;
}
t( true === $before_audit['ok'] && 3 === $before_audit['entries'] && 3 === $before_audit['verified'], 'C26-004 before: audit chain verifies with the key (3 entries, all signed)' );
t( $audit_max >= 3, 'fixture audit history exists' );

// ---- Upgrade.
$unconfirmed = Migrator::run();
t( false === $unconfirmed['ok'] && 'backup_required' === $unconfirmed['code'], 'the upgrade refuses to run without a confirmed backup' );
Migrator::confirm_backup( 2, 0 );
$result = Migrator::run();
t( true === $result['ok'] && 2 === Migrator::installed_version(), 'the upgrade succeeds and reaches schema version 2', wp_json_encode( $result ) );
t( Schema::has_index( 'approvals', 'post_status' ), 'physical change observed: approvals index post_status exists' );

// ---- After.
Verifier::flush_cache();
$after_audit  = Verifier::verify();
$after_export = export_logical();
$after_hist   = export_logical( $audit_max );
$after_opts   = noblen_options();
$after_rows   = AuditLog::all();
t( true === $after_audit['ok'] && $after_audit['entries'] === $after_audit['verified'], 'C26-004 after: audit chain verifies with the key (every entry signed)' );

// ---- C26-003/005: every category against the manifest rules.
$seen = array();
foreach ( $manifest['logical_categories'] as $cat => $rule ) {
	if ( 'identical' === $rule['rule'] ) {
		t( ( $before_export[ $cat ] ?? 'x' ) === ( $after_export[ $cat ] ?? 'y' ), "C26-003/005 {$cat}: identical before and after (declared identical)" );
		$seen[ $cat ] = true;
	}
}
t( ( $before_export['audit'] ?? 'x' ) === ( $after_hist['audit'] ?? 'y' ), 'C26-003/005 audit: the existing history is unchanged' );
$appended = array_slice( $after_rows, count( $before_rows ) );
$declared = $manifest['logical_categories']['audit']['appended'];
$match    = count( $appended ) === count( $declared );
foreach ( $declared as $i => $d ) {
	$a      = $appended[ $i ] ?? array();
	$detail = json_decode( (string) ( $a['detail'] ?? '{}' ), true );
	$match  = $match && ( $a['event_type'] ?? '' ) === $d['event_type'] && ( $detail == $d['detail'] ); // phpcs:ignore WordPress.PHP.StrictComparisons
}
t( $match, 'C26-005 audit: exactly the declared entries were appended, in order (' . count( $appended ) . ' appended)' );

// ---- Undeclared option changes fail; declared ones must have happened (exercised).
$changed = array();
foreach ( array_unique( array_merge( array_keys( $before_opts ), array_keys( $after_opts ) ) ) as $name ) {
	if ( ( $before_opts[ $name ] ?? null ) !== ( $after_opts[ $name ] ?? null ) ) {
		$changed[] = $name;
	}
}
$declared_opts = array_column( $manifest['option_changes'], 'option' );
$undeclared    = array_diff( $changed, $declared_opts );
$unexercised   = array_diff( $declared_opts, $changed );
t( ! $undeclared, 'C26-005 options: no undeclared change under noblen_core_*', implode( ', ', $undeclared ) );
t( ! $unexercised, 'C26-005 options: every declared option change was exercised', implode( ', ', $unexercised ) );
foreach ( $manifest['option_changes'] as $oc ) {
	$from = $before_opts[ $oc['option'] ] ?? null;
	$to   = $after_opts[ $oc['option'] ] ?? null;
	$ok   = ( $oc['from'] === $from || ( null === $oc['from'] && null === $from ) ) && ( 'present' === $oc['to'] ? null !== $to : $oc['to'] === $to );
	t( $ok, 'C26-005 option ' . $oc['option'] . ' changed as declared' );
}
t( 0 === count( $manifest['preservation_mappings'] ), 'no value is moved or rewritten (no preservation mappings needed)' );

// ---- Mutation guard: the comparison must notice a logical change.
$wpdb->update( Schema::table( 'approvals' ), array( 'note' => 'tampered' ), array( 'post_id' => $project ) ); // phpcs:ignore WordPress.DB
$tampered = export_logical();
t( $tampered['approvals'] !== $after_export['approvals'], 'the comparison detects a changed approval value (tooling self-check)' );
$wpdb->update( Schema::table( 'approvals' ), array( 'note' => 'fixture' ), array( 'post_id' => $project ) ); // phpcs:ignore WordPress.DB
t( export_logical()['approvals'] === $after_export['approvals'], 'the self-check change was reverted' );

echo "\n{$pass} passed, {$fail} failed\n";
echo "Fixture run only: not the registered source/target comparison and not an acceptance.\n";
exit( $fail ? 1 : 0 );

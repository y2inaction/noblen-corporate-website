<?php
namespace Noblen\Core\Cli;

use Noblen\Core\Database\Migrator;
use Noblen\Core\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Operator commands for the plugin's database upgrades. An upgrade needs a confirmed backup first.
 * Run with --user=<login> for the commands that change anything.
 */
final class MigrateCommand {

	/**
	 * Show the database schema status: installed and target versions, migration state, and whether a backup is confirmed.
	 *
	 * ## EXAMPLES
	 *
	 *     wp noblen migrate status
	 *
	 * @param array<int,string>   $args       Positional arguments.
	 * @param array<string,mixed> $assoc_args Named arguments.
	 */
	public function status( $args, $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$state  = Migrator::state();
		$backup = get_option( Migrator::OPT_BACKUP, array() );
		$rows   = array(
			array(
				'item'  => 'installed_version',
				'value' => (string) Migrator::installed_version(),
			),
			array(
				'item'  => 'target_version',
				'value' => (string) Schema::VERSION,
			),
			array(
				'item'  => 'upgrade_pending',
				'value' => Migrator::pending() ? 'yes' : 'no',
			),
			array(
				'item'  => 'migration_state',
				'value' => (string) $state['state'],
			),
			array(
				'item'  => 'migration_error',
				'value' => (string) $state['error'],
			),
			array(
				'item'  => 'backup_confirmed_for_version',
				'value' => isset( $backup['target'] ) ? (string) (int) $backup['target'] : 'none',
			),
			array(
				'item'  => 'backup_confirmed_at',
				'value' => isset( $backup['time'] ) ? (string) $backup['time'] : '',
			),
			array(
				'item'  => 'healthy',
				'value' => Migrator::is_healthy() ? 'yes' : 'no',
			),
		);
		\WP_CLI\Utils\format_items( 'table', $rows, array( 'item', 'value' ) );
	}

	/**
	 * Record that a current backup of the files and database exists, for the target schema version. Writes an audit entry.
	 * Needs --user=<login> for a user who may manage Noblen settings.
	 *
	 * ## EXAMPLES
	 *
	 *     wp noblen migrate confirm-backup --user=admin
	 *
	 * @param array<int,string>   $args       Positional arguments.
	 * @param array<string,mixed> $assoc_args Named arguments.
	 * @subcommand confirm-backup
	 */
	public function confirm_backup( $args, $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if ( ! current_user_can( 'noblen_manage_settings' ) ) {
			\WP_CLI::error( 'You are not allowed to confirm a backup. Use --user=<login> with a user who may manage Noblen settings.' );
		}
		Migrator::confirm_backup( Schema::VERSION, get_current_user_id() );
		\WP_CLI::success( 'Backup confirmed for schema version ' . Schema::VERSION . '. Only confirm this after you have taken and checked a backup.' );
	}

	/**
	 * Run the pending database upgrade. It refuses to run until a backup has been confirmed. Needs --user=<login>.
	 *
	 * ## EXAMPLES
	 *
	 *     wp noblen migrate run --user=admin
	 *
	 * @param array<int,string>   $args       Positional arguments.
	 * @param array<string,mixed> $assoc_args Named arguments.
	 */
	public function run( $args, $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if ( ! current_user_can( 'noblen_manage_settings' ) ) {
			\WP_CLI::error( 'You are not allowed to run an upgrade. Use --user=<login> with a user who may manage Noblen settings.' );
		}
		$result = Migrator::run();
		if ( $result['ok'] ) {
			\WP_CLI::success( 'current' === $result['code'] ? 'The database is already current.' : 'The database was upgraded to version ' . (int) $result['version'] . '.' );
			return;
		}
		\WP_CLI::error( (string) $result['error'] . ' (' . (string) $result['code'] . ')' );
	}
}

<?php
namespace Noblen\Core\Database;

use Noblen\Core\Audit\AuditLog;

defined( 'ABSPATH' ) || exit;

/**
 * Numbered, idempotent, additive-first schema migrations.
 *
 * Upgrades run only from an explicit call (administration area or activation),
 * under a lock. A schema-changing upgrade cannot start until an administrator
 * confirms a current database backup. A failed step stops, records its state,
 * and never retries automatically. While a migration is pending or failed the
 * publication gate refuses new approvals and publications.
 */
final class Migrator {

	public const OPT_VERSION = 'noblen_core_db_version';
	public const OPT_STATE   = 'noblen_core_migration_state';
	public const OPT_LOCK    = 'noblen_core_migration_lock';
	public const OPT_BACKUP  = 'noblen_core_backup_confirmed';

	/** Seconds after which an abandoned lock may be taken over. */
	private const LOCK_TTL = 600;

	/**
	 * Installed schema version (0 when nothing is installed).
	 */
	public static function installed_version(): int {
		return (int) get_option( self::OPT_VERSION, 0 );
	}

	/**
	 * Whether a newer schema is available than the installed one.
	 */
	public static function pending(): bool {
		return self::installed_version() < Schema::VERSION;
	}

	/**
	 * Migration state record.
	 *
	 * @return array{state:string,step:int,error:string}
	 */
	public static function state(): array {
		$state = get_option( self::OPT_STATE, array() );
		return array(
			'state' => isset( $state['state'] ) ? (string) $state['state'] : 'idle',
			'step'  => isset( $state['step'] ) ? (int) $state['step'] : 0,
			'error' => isset( $state['error'] ) ? (string) $state['error'] : '',
		);
	}

	/**
	 * Whether the schema is current and the last migration did not fail.
	 */
	public static function is_healthy(): bool {
		$state = self::state();
		return 'failed' !== $state['state'] && ! self::pending();
	}

	/**
	 * Record that an administrator confirmed a current backup before upgrading.
	 *
	 * @param int $target_version Schema version the confirmation covers.
	 * @param int $user_id        Confirming user.
	 */
	public static function confirm_backup( int $target_version, int $user_id ): void {
		update_option(
			self::OPT_BACKUP,
			array(
				'target' => $target_version,
				'user'   => $user_id,
				'time'   => gmdate( 'c' ),
			),
			false
		);
		if ( Schema::exists( 'audit' ) ) {
			AuditLog::append(
				'backup_confirmed',
				array(
					'actor_id' => $user_id,
					'detail'   => array( 'target_version' => $target_version ),
				)
			);
		}
	}

	/**
	 * Whether a backup confirmation covers the given target version.
	 *
	 * @param int $target_version Target schema version.
	 */
	private static function backup_confirmed( int $target_version ): bool {
		$b = get_option( self::OPT_BACKUP, array() );
		return isset( $b['target'] ) && (int) $b['target'] >= $target_version;
	}

	/**
	 * Take the migration lock.
	 */
	private static function lock(): bool {
		if ( add_option( self::OPT_LOCK, time(), '', 'no' ) ) {
			return true;
		}
		$held = (int) get_option( self::OPT_LOCK, 0 );
		if ( $held && ( time() - $held ) > self::LOCK_TTL ) {
			delete_option( self::OPT_LOCK );
			return add_option( self::OPT_LOCK, time(), '', 'no' );
		}
		return false;
	}

	/**
	 * Release the migration lock.
	 */
	private static function unlock(): void {
		delete_option( self::OPT_LOCK );
	}

	/**
	 * Run all pending migrations.
	 *
	 * @return array{ok:bool,version:int,error:string,code:string}
	 */
	public static function run(): array {
		$from = self::installed_version();
		if ( $from >= Schema::VERSION ) {
			return self::result( true, '', 'current' );
		}
		if ( $from > 0 && ! self::backup_confirmed( Schema::VERSION ) ) {
			return self::result( false, 'A current database backup must be confirmed before this upgrade.', 'backup_required' );
		}
		if ( ! self::lock() ) {
			return self::result( false, 'Another migration is already running.', 'locked' );
		}

		try {
			for ( $n = $from + 1; $n <= Schema::VERSION; $n++ ) {
				update_option(
					self::OPT_STATE,
					array(
						'state' => 'running',
						'step'  => $n,
						'error' => '',
					),
					false
				);
				// Test seam: a callback may throw to simulate a failing step.
				apply_filters( 'noblen_core_migration_step', null, $n );
				self::apply( $n );
				update_option( self::OPT_VERSION, $n, false );
				if ( Schema::exists( 'audit' ) ) {
					AuditLog::append( 'migration_applied', array( 'detail' => array( 'version' => $n ) ) );
				}
			}
			update_option(
				self::OPT_STATE,
				array(
					'state' => 'idle',
					'step'  => 0,
					'error' => '',
				),
				false
			);
			self::unlock();
			return self::result( true, '', 'migrated' );
		} catch ( \Throwable $e ) {
			$step = (int) ( self::state()['step'] );
			update_option(
				self::OPT_STATE,
				array(
					'state' => 'failed',
					'step'  => $step,
					'error' => $e->getMessage(),
				),
				false
			);
			self::unlock();
			error_log( 'Noblen Core migration failed at step ' . $step . ': ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
			return self::result( false, 'Migration failed at step ' . $step . '.', 'failed' );
		}
	}

	/**
	 * Clear a recorded failure so the migration can be run again explicitly.
	 */
	public static function clear_failure(): void {
		update_option(
			self::OPT_STATE,
			array(
				'state' => 'idle',
				'step'  => 0,
				'error' => '',
			),
			false
		);
	}

	/**
	 * Apply one migration step.
	 *
	 * @param int $n Step number.
	 * @throws \RuntimeException When a post-condition is not met.
	 */
	private static function apply( int $n ): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( Schema::sql( $n ) );
		if ( 1 === $n ) {
			foreach ( Schema::table_names() as $name ) {
				if ( ! Schema::exists( $name ) ) {
					throw new \RuntimeException( 'Table ' . esc_html( $name ) . ' was not created.' );
				}
			}
		}
		if ( 2 === $n && ! Schema::has_index( 'approvals', 'post_status' ) ) {
			throw new \RuntimeException( 'Index on approvals was not created.' );
		}
	}

	/**
	 * Build a result record.
	 *
	 * @param bool   $ok      Success flag.
	 * @param string $error   Plain message.
	 * @param string $code    Machine code.
	 * @return array{ok:bool,version:int,error:string,code:string}
	 */
	private static function result( bool $ok, string $error, string $code ): array {
		return array(
			'ok'      => $ok,
			'version' => self::installed_version(),
			'error'   => $error,
			'code'    => $code,
		);
	}
}

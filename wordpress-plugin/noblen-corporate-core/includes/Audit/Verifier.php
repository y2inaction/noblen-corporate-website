<?php
namespace Noblen\Core\Audit;

use Noblen\Core\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Audit chain verification and the audit integrity state.
 *
 * New approvals and publications are allowed only in the state "verified".
 * Every other state blocks them. Already-published approved content is not
 * unpublished by an audit failure.
 */
final class Verifier {

	public const OPT_SUSPENDED = 'noblen_core_approvals_suspended';
	public const OPT_RECOVERY  = 'noblen_core_recovery_state';

	/** @var array{sig:string,state:string}|null */
	private static ?array $cache = null;

	/**
	 * Forget the cached state.
	 */
	public static function flush_cache(): void {
		self::$cache = null;
	}

	/**
	 * Verify the chain.
	 *
	 * @param int|null $from_id When set, only entries from this ID onward are checked.
	 * @return array{ok:bool,entries:int,verified:int,unsigned:int,unverifiable:int[],break_id:?int,break_reason:string}
	 */
	public static function verify( ?int $from_id = null ): array {
		$report = array(
			'ok'           => true,
			'entries'      => 0,
			'verified'     => 0,
			'unsigned'     => 0,
			'unverifiable' => array(),
			'break_id'     => null,
			'break_reason' => '',
		);
		if ( ! Schema::exists( 'audit' ) ) {
			return $report;
		}

		$prev = Chain::GENESIS;
		foreach ( AuditLog::all() as $e ) {
			$id = (int) $e['id'];
			if ( null !== $from_id && $id < $from_id ) {
				$prev = (string) $e['record_hash'];
				continue;
			}
			++$report['entries'];

			if ( $e['prev_hash'] !== $prev ) {
				return self::broken( $report, $id, 'prev_hash_mismatch' );
			}

			$secret = '';
			if ( '' !== (string) $e['key_id'] ) {
				$secret = Key::get( (string) $e['key_id'] );
				if ( '' === $secret ) {
					$report['unverifiable'][] = $id;
					$prev                     = (string) $e['record_hash'];
					continue;
				}
				if ( ! hash_equals( Key::fingerprint( $secret ), (string) $e['key_fingerprint'] ) ) {
					return self::broken( $report, $id, 'key_fingerprint_mismatch' );
				}
			} else {
				++$report['unsigned'];
			}

			if ( ! hash_equals( Chain::hash( $secret, $prev, $e ), (string) $e['record_hash'] ) ) {
				return self::broken( $report, $id, 'hash_mismatch' );
			}
			++$report['verified'];
			$prev = (string) $e['record_hash'];
		}
		return $report;
	}

	/**
	 * Mark a report as broken.
	 *
	 * @param array<string,mixed> $report Report.
	 * @param int                 $id     Entry ID.
	 * @param string              $reason Reason code.
	 * @return array<string,mixed>
	 */
	private static function broken( array $report, int $id, string $reason ): array {
		$report['ok']           = false;
		$report['break_id']     = $id;
		$report['break_reason'] = $reason;
		return $report;
	}

	/**
	 * Current audit integrity state.
	 *
	 * @return string One of: verified, key_missing, key_invalid, chain_broken,
	 *                rotation_incomplete, recovery_incomplete, approvals_suspended, unknown.
	 */
	public static function state(): string {
		if ( ! Schema::exists( 'audit' ) ) {
			return 'unknown';
		}
		$last = AuditLog::last();
		$sig  = implode(
			'|',
			array(
				$last ? $last['id'] : 0,
				Key::current_id(),
				Key::has_current() ? Key::fingerprint( Key::current_secret() ) : '-',
				get_option( self::OPT_SUSPENDED ) ? 's' : '-',
				get_option( self::OPT_RECOVERY, '' ),
				md5( (string) wp_json_encode( array_map( array( Key::class, 'fingerprint' ), Key::all() ) ) ),
			)
		);
		if ( null !== self::$cache && self::$cache['sig'] === $sig ) {
			return self::$cache['state'];
		}
		$state       = self::compute();
		self::$cache = array(
			'sig'   => $sig,
			'state' => $state,
		);
		return $state;
	}

	/**
	 * Compute the state without the cache.
	 */
	private static function compute(): string {
		if ( get_option( self::OPT_SUSPENDED ) ) {
			return 'approvals_suspended';
		}
		if ( ! Key::has_current() ) {
			return 'key_missing';
		}
		if ( 'incomplete' === get_option( self::OPT_RECOVERY, '' ) ) {
			return 'recovery_incomplete';
		}

		$started = AuditLog::by_event( 'key_rotation_started' );
		if ( $started ) {
			$last_start = (int) end( $started )['id'];
			$done       = array_merge( AuditLog::by_event( 'key_rotation_confirmed' ), AuditLog::by_event( 'key_rotation_abandoned' ) );
			$last_done  = $done ? (int) max( array_map( static fn( $r ) => (int) $r['id'], $done ) ) : 0;
			if ( $last_start > $last_done ) {
				return 'rotation_incomplete';
			}
		}

		$last_signed = AuditLog::last_signed();
		if ( $last_signed ) {
			$configured = Key::get( (string) $last_signed['key_id'] );
			if ( '' === $configured
				|| ! hash_equals( Key::fingerprint( $configured ), (string) $last_signed['key_fingerprint'] )
				|| Key::current_id() !== (string) $last_signed['key_id'] ) {
				return 'key_invalid';
			}
		}

		$report = self::verify();
		return $report['ok'] ? 'verified' : 'chain_broken';
	}

	/**
	 * Classify every entry as verified, corroborated or unverifiable.
	 *
	 * "Corroborated" means the signing key is unavailable but the entry matches an
	 * independent external export. It is NEVER reported as verified.
	 *
	 * @param array<string,mixed>|null $export Earlier external export, if any.
	 * @return array<int,string> Map of entry ID to class.
	 */
	public static function classify( ?array $export = null ): array {
		$exported = array();
		if ( is_array( $export ) && isset( $export['entries'] ) ) {
			foreach ( $export['entries'] as $row ) {
				$exported[ (int) $row['id'] ] = (string) $row['record_hash'];
			}
		}

		$out  = array();
		$prev = Chain::GENESIS;
		foreach ( AuditLog::all() as $e ) {
			$id     = (int) $e['id'];
			$secret = '' === (string) $e['key_id'] ? '' : Key::get( (string) $e['key_id'] );
			$linked = hash_equals( $prev, (string) $e['prev_hash'] );

			if ( '' !== (string) $e['key_id'] && '' === $secret ) {
				if ( isset( $exported[ $id ] ) && hash_equals( $exported[ $id ], (string) $e['record_hash'] ) ) {
					$out[ $id ] = 'corroborated';
				} else {
					$out[ $id ] = 'unverifiable';
				}
			} elseif ( $linked && hash_equals( Chain::hash( $secret, $prev, $e ), (string) $e['record_hash'] ) ) {
				$out[ $id ] = 'verified';
			} else {
				$out[ $id ] = 'unverifiable';
			}
			$prev = (string) $e['record_hash'];
		}
		return $out;
	}
}

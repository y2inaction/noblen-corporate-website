<?php
namespace Noblen\Core\Admin;

use Noblen\Core\Audit\Verifier;
use Noblen\Core\Database\Migrator;
use Noblen\Core\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Configuration validation shown in WordPress Site Health (Tools, Site Health).
 *
 * Read-only: it reports and recommends, and changes nothing. `evaluate()` is pure so that every rule can be tested
 * with synthetic inputs; `environment()` reads the real site.
 */
final class SiteHealth {

	/** Hook the test into Site Health. */
	public static function register(): void {
		add_filter( 'site_status_tests', array( self::class, 'add_tests' ) );
	}

	/**
	 * Add the direct test.
	 *
	 * @param array<string,mixed> $tests Existing tests.
	 * @return array<string,mixed>
	 */
	public static function add_tests( array $tests ): array {
		$tests['direct']['noblen_configuration'] = array(
			'label' => 'Noblen configuration',
			'test'  => array( self::class, 'result' ),
		);
		return $tests;
	}

	/**
	 * Facts read from the live site.
	 *
	 * @return array<string,mixed>
	 */
	public static function environment(): array {
		$theme = wp_get_theme();
		return array(
			'audit_state'         => Verifier::state(),
			'migration_healthy'   => Migrator::is_healthy(),
			'debug_display'       => defined( 'WP_DEBUG' ) && WP_DEBUG && ( ! defined( 'WP_DEBUG_DISPLAY' ) || WP_DEBUG_DISPLAY ),
			'https'               => 0 === strpos( (string) home_url(), 'https://' ),
			'pretty_permalinks'   => '' !== (string) get_option( 'permalink_structure', '' ),
			'theme_active'        => 'noblen-corporate' === $theme->get_template(),
			'separation_duties'   => Settings::separation_of_duties(),
			'remove_on_uninstall' => Settings::remove_data_on_uninstall(),
			'search_visible'      => '0' !== (string) get_option( 'blog_public', '1' ),
		);
	}

	/**
	 * Apply the rules to a set of facts.
	 *
	 * @param array<string,mixed> $env Facts, as returned by environment().
	 * @return array<int,array{id:string,status:string,text:string}> Status is good, recommended or critical.
	 */
	public static function evaluate( array $env ): array {
		$out   = array();
		$state = (string) ( $env['audit_state'] ?? 'unknown' );
		if ( 'verified' === $state ) {
			$out[] = self::row( 'audit', 'good', 'The audit chain verifies with the configured key.' );
		} elseif ( 'unknown' === $state ) {
			$out[] = self::row( 'audit', 'recommended', 'The audit tables are not available yet. Activate the plugin and open the dashboard once.' );
		} else {
			$out[] = self::row( 'audit', 'critical', 'Approvals are blocked: audit integrity is "' . $state . '". See the recovery guide before changing anything.' );
		}
		$out[] = ! empty( $env['migration_healthy'] )
			? self::row( 'migration', 'good', 'The database schema is current and no migration has failed.' )
			: self::row( 'migration', 'critical', 'A database migration is pending or has failed. Confirm a backup, then re-run it, or restore the backup.' );
		$out[] = ! empty( $env['debug_display'] )
			? self::row( 'debug', 'recommended', 'WP_DEBUG is on and shows errors on the page. Turn WP_DEBUG_DISPLAY off on a live site.' )
			: self::row( 'debug', 'good', 'Errors are not displayed on the page.' );
		$out[] = ! empty( $env['https'] )
			? self::row( 'https', 'good', 'The site address uses HTTPS.' )
			: self::row( 'https', 'recommended', 'The site address does not use HTTPS. A live site should.' );
		$out[] = ! empty( $env['pretty_permalinks'] )
			? self::row( 'permalinks', 'good', 'Pretty permalinks are on, so section addresses are readable.' )
			: self::row( 'permalinks', 'recommended', 'Pretty permalinks are off. Choose a post-name structure under Settings, Permalinks.' );
		$out[] = ! empty( $env['theme_active'] )
			? self::row( 'theme', 'good', 'The Noblen Corporate theme is active.' )
			: self::row( 'theme', 'recommended', 'The Noblen Corporate theme is not active. Content stays safe, but the public site will not use the intended design.' );
		$out[] = ! empty( $env['separation_duties'] )
			? self::row( 'duties', 'good', 'Separation of duties is enforced for approvals.' )
			: self::row( 'duties', 'recommended', 'Separation of duties is switched off. Approvals can be made by the person who edited or cleared a record.' );
		$out[] = empty( $env['remove_on_uninstall'] )
			? self::row( 'uninstall', 'good', 'Content is kept if the plugin is uninstalled.' )
			: self::row( 'uninstall', 'recommended', 'The plugin is set to DELETE all its data on uninstall. Leave this off unless that is intended.' );
		$out[] = ! empty( $env['search_visible'] )
			? self::row( 'search', 'good', 'Search engines are allowed to index the site.' )
			: self::row( 'search', 'recommended', 'Search engines are discouraged (Settings, Reading). That is right before launch and wrong after it.' );
		return $out;
	}

	/**
	 * One rule outcome.
	 *
	 * @param string $id     Rule ID.
	 * @param string $status good, recommended or critical.
	 * @param string $text   Plain text.
	 * @return array{id:string,status:string,text:string}
	 */
	private static function row( string $id, string $status, string $text ): array {
		return array(
			'id'     => $id,
			'status' => $status,
			'text'   => $text,
		);
	}

	/**
	 * The worst status among the rows.
	 *
	 * @param array<int,array{id:string,status:string,text:string}> $rows Rows.
	 */
	public static function overall( array $rows ): string {
		$statuses = array_column( $rows, 'status' );
		if ( in_array( 'critical', $statuses, true ) ) {
			return 'critical';
		}
		return in_array( 'recommended', $statuses, true ) ? 'recommended' : 'good';
	}

	/**
	 * Result in the shape Site Health expects.
	 *
	 * @return array<string,mixed>
	 */
	public static function result(): array {
		$rows    = self::evaluate( self::environment() );
		$overall = self::overall( $rows );
		$colour  = array(
			'good'        => 'green',
			'recommended' => 'orange',
			'critical'    => 'red',
		);
		$html    = '<ul>';
		foreach ( $rows as $row ) {
			$html .= '<li><strong>' . esc_html( strtoupper( $row['status'] ) ) . '</strong>: ' . esc_html( $row['text'] ) . '</li>';
		}
		$html .= '</ul>';
		return array(
			'label'       => 'good' === $overall ? 'Noblen configuration is in good order' : 'Noblen configuration needs attention',
			'status'      => $overall,
			'badge'       => array(
				'label' => 'Noblen',
				'color' => $colour[ $overall ],
			),
			'description' => $html,
			'actions'     => '',
			'test'        => 'noblen_configuration',
		);
	}
}

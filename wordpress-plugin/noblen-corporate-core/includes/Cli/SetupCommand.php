<?php
namespace Noblen\Core\Cli;

use Noblen\Core\Setup\Blueprint;
use Noblen\Core\Setup\Setup;

defined( 'ABSPATH' ) || exit;

/**
 * Operator commands for the Website Setup. Run with --user=<administrator login> so the same capability check as the screen applies.
 * Nothing here deletes or overwrites content; see the Website Setup screen and ADMINISTRATOR-GUIDE.md.
 */
final class SetupCommand {

	/**
	 * Show what is created, existing, in conflict and ready. Read-only.
	 *
	 * ## EXAMPLES
	 *
	 *     wp noblen setup status
	 *
	 * @param array<int,string>   $args       Positional arguments.
	 * @param array<string,mixed> $assoc_args Named arguments.
	 */
	public function status( $args, $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$st   = Setup::status();
		$rows = array();
		foreach ( array(
			'pages'    => 'page',
			'menus'    => 'menu',
			'records'  => 'record',
			'archives' => 'archive',
		) as $group => $kind ) {
			foreach ( $st[ $group ] as $r ) {
				$rows[] = array(
					'kind'   => $kind,
					'item'   => (string) $r['label'],
					'status' => (string) $r['class'],
				);
			}
		}
		\WP_CLI\Utils\format_items( 'table', $rows, array( 'kind', 'item', 'status' ) );
	}

	/**
	 * Stage 1: create the structure as drafts (pages, empty unassigned menus, name-only draft records). Changes nothing public. Safe to repeat.
	 *
	 * ## EXAMPLES
	 *
	 *     wp noblen setup prepare --user=admin
	 *
	 * @param array<int,string>   $args       Positional arguments.
	 * @param array<string,mixed> $assoc_args Named arguments.
	 */
	public function prepare( $args, $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$res = Setup::prepare();
		if ( is_wp_error( $res ) ) {
			\WP_CLI::error( $res->get_error_message() . ' Use --user=<administrator login>.' );
		}
		\WP_CLI\Utils\format_items( 'table', $res['items'], array( 'kind', 'label', 'outcome', 'note' ) );
		\WP_CLI::success( sprintf( 'Prepared: %d created, %d existing, %d conflict, %d error. Nothing is public yet.', $res['counts']['created'], $res['counts']['existing'], $res['counts']['conflict'], $res['counts']['error'] ) );
	}

	/**
	 * Stage 2: activate reviewed items. Nothing is changed unless you pass the matching option.
	 *
	 * ## OPTIONS
	 *
	 * [--publish=<keys>]
	 * : Comma-separated page keys to publish after review (home, about, insights, partnerships, contact, privacy, terms). Placeholders block publication.
	 *
	 * [--assign=<keys>]
	 * : Comma-separated menu keys to assign (primary, footer, legal). Entries are added for public destinations only.
	 *
	 * [--replace-location=<keys>]
	 * : Menu keys whose location may replace a different assigned menu.
	 *
	 * [--front-page]
	 * : Use the published Home page as the front page.
	 *
	 * [--replace-front-page]
	 * : Allow replacing a different static front page.
	 *
	 * [--posts-page]
	 * : Use the published Insights page as the posts page.
	 *
	 * [--replace-posts-page]
	 * : Allow replacing a different posts page.
	 *
	 * [--hero-defaults]
	 * : Fill the empty homepage headline and introduction (existing text is kept).
	 *
	 * ## EXAMPLES
	 *
	 *     wp noblen setup activate --user=admin --publish=about,contact --assign=primary
	 *
	 * @param array<int,string>   $args       Positional arguments.
	 * @param array<string,mixed> $assoc_args Named arguments.
	 */
	public function activate( $args, $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$list = static function ( string $name ) use ( $assoc_args ): array {
			$out = array();
			foreach ( array_filter( array_map( 'trim', explode( ',', (string) ( $assoc_args[ $name ] ?? '' ) ) ) ) as $k ) {
				$out[ $k ] = true;
			}
			return $out;
		};
		$opts = array_merge(
			Setup::default_options(),
			array(
				'publish'            => array_intersect_key( $list( 'publish' ), Blueprint::pages() ),
				'assign'             => array_intersect_key( $list( 'assign' ), Blueprint::menus() ),
				'replace_location'   => array_intersect_key( $list( 'replace-location' ), Blueprint::menus() ),
				'front_page'         => isset( $assoc_args['front-page'] ),
				'replace_front_page' => isset( $assoc_args['replace-front-page'] ),
				'posts_page'         => isset( $assoc_args['posts-page'] ),
				'replace_posts_page' => isset( $assoc_args['replace-posts-page'] ),
				'hero_defaults'      => isset( $assoc_args['hero-defaults'] ),
			)
		);
		$res  = Setup::activate( $opts );
		if ( is_wp_error( $res ) ) {
			\WP_CLI::error( $res->get_error_message() . ' Use --user=<administrator login>.' );
		}
		if ( $res['items'] ) {
			\WP_CLI\Utils\format_items( 'table', $res['items'], array( 'kind', 'label', 'outcome', 'note' ) );
		}
		\WP_CLI::success( 'Activation finished. Review the table: "blocked" and "conflict" lines were not applied.' );
	}
}

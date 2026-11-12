<?php
namespace Noblen\Core\Exposure;

use Noblen\Core\Clearance\ReadCheck;
use Noblen\Core\Content\Types;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps ineligible records out of every public interface the plugin supports:
 * single address, search, archives, feeds, oEmbed and core sitemaps.
 *
 * REST routes do not exist for these types (REST is off). The defensive filter
 * below only guards against private keys ever appearing in a response.
 */
final class PublicInterfaces {

	/**
	 * Attach hooks.
	 */
	public static function register(): void {
		add_filter( 'the_posts', array( ReadCheck::class, 'filter_posts' ), 20 );
		add_action( 'pre_get_posts', array( self::class, 'exclude_from_feeds' ) );
		add_filter( 'oembed_request_post_id', array( self::class, 'oembed' ), 10, 2 );
		add_filter( 'wp_sitemaps_posts_query_args', array( self::class, 'sitemap' ), 10, 2 );
		add_filter( 'rest_post_dispatch', array( self::class, 'scrub_rest' ), 10, 3 );
		add_filter( 'comments_open', array( self::class, 'no_comments' ), 10, 2 );
	}

	/**
	 * Gated types never appear in feeds.
	 *
	 * @param \WP_Query $query Query.
	 */
	public static function exclude_from_feeds( $query ): void {
		if ( ! $query instanceof \WP_Query || ! $query->is_feed() ) {
			return;
		}
		$types = (array) $query->get( 'post_type' );
		if ( array_intersect( $types, Types::all() ) ) {
			$query->set( 'post__in', array( 0 ) );
		}
	}

	/**
	 * Ineligible records return not found from the oEmbed endpoint.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $url     URL.
	 * @return int
	 */
	public static function oembed( $post_id, $url = '' ) {
		$post = $post_id ? get_post( (int) $post_id ) : null;
		if ( $post && Types::is_ours( $post->post_type ) && ! ReadCheck::eligible( $post ) ) {
			return 0;
		}
		return $post_id;
	}

	/**
	 * Exclude ineligible published records from core sitemap queries.
	 *
	 * @param array<string,mixed> $args      Query args.
	 * @param string              $post_type Post type.
	 * @return array<string,mixed>
	 */
	public static function sitemap( $args, $post_type ) {
		if ( ! Types::is_ours( (string) $post_type ) ) {
			return $args;
		}
		$ids = get_posts(
			array(
				'post_type'        => $post_type,
				'post_status'      => 'publish',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
				'no_found_rows'    => true,
			)
		);
		$bad = array();
		foreach ( $ids as $id ) {
			if ( ! ReadCheck::eligible( (int) $id ) ) {
				$bad[] = (int) $id;
			}
		}
		if ( $bad ) {
			$args['post__not_in'] = array_merge( (array) ( $args['post__not_in'] ?? array() ), $bad );
		}
		return $args;
	}

	/**
	 * Defensive: remove private-looking keys from any REST response.
	 *
	 * @param \WP_REST_Response $response Response.
	 * @param mixed             $server   Server.
	 * @param mixed             $request  Request.
	 * @return \WP_REST_Response
	 */
	public static function scrub_rest( $response, $server = null, $request = null ) {
		if ( $response instanceof \WP_REST_Response ) {
			$response->set_data( self::scrub( $response->get_data() ) );
		}
		return $response;
	}

	/**
	 * Recursively drop private keys.
	 *
	 * @param mixed $data Data.
	 * @return mixed
	 */
	private static function scrub( $data ) {
		if ( is_array( $data ) ) {
			$out = array();
			foreach ( $data as $k => $v ) {
				if ( is_string( $k ) && preg_match( '/^(noblen_)?(clearance|approval|audit)(_|$)/i', $k ) ) {
					continue;
				}
				$out[ $k ] = self::scrub( $v );
			}
			return $out;
		}
		return $data;
	}

	/**
	 * No comments on our types.
	 *
	 * @param bool $open    Open.
	 * @param int  $post_id Post ID.
	 */
	public static function no_comments( $open, $post_id ) {
		return Types::is_ours( (string) get_post_type( (int) $post_id ) ) ? false : $open;
	}
}

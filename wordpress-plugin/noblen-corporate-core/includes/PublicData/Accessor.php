<?php
namespace Noblen\Core\PublicData;

use Noblen\Core\Clearance\ReadCheck;
use Noblen\Core\Content\Types;
use Noblen\Core\Fields\Epochs;
use Noblen\Core\Fields\Registry;
use Noblen\Core\Fields\Store;
use Noblen\Core\Relationships\Relations;

defined( 'ABSPATH' ) || exit;

/**
 * The single allowlisted source of public data.
 *
 * Themes, structured data and any other public output must read through here and
 * never read raw metadata. A record is returned only when it is eligible
 * (published with a valid, current approval). Related records and images appear
 * only when valid. Narrative sections appear only when they have content and
 * their "verified and approved for display" switch is on.
 */
final class Accessor {

	private const SECTIONS = array( 'overview', 'scope', 'contribution', 'approach', 'outcomes' );

	/**
	 * Public data for one record, or null when it must not be shown.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string,mixed>|null
	 */
	public static function get( int $post_id ): ?array {
		$post = get_post( $post_id );
		if ( ! $post || ! Types::is_ours( $post->post_type ) || ! ReadCheck::eligible( $post ) ) {
			return null;
		}
		$key    = Epochs::cache_key( $post_id, 'public' );
		$cached = wp_cache_get( $key, 'noblen' );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$data = self::build( $post );
		wp_cache_set( $key, $data, 'noblen', 600 );
		return $data;
	}

	/**
	 * Build the public data.
	 *
	 * @param \WP_Post $post Post.
	 * @return array<string,mixed>
	 */
	private static function build( \WP_Post $post ): array {
		$type   = $post->post_type;
		$values = Store::get_all( $post->ID );
		$defs   = Registry::public_fields( $type );

		$data = array(
			'id'             => (int) $post->ID,
			'type'           => $type,
			'title'          => (string) $post->post_title,
			'slug'           => (string) $post->post_name,
			'url'            => (string) get_permalink( $post ),
			'excerpt'        => (string) $post->post_excerpt,
			'content_html'   => '' !== trim( (string) $post->post_content ) ? wpautop( wp_kses( (string) $post->post_content, Store::allowed_html() ) ) : '',
			'status'         => null,
			'client'         => '',
			'sections'       => array(),
			'tagline'        => '',
			'featured_home'  => ! empty( $values['featured_home'] ),
			'video_url'      => (string) ( $values['video_url'] ?? '' ),
			'featured_image' => self::image( (int) get_post_thumbnail_id( $post->ID ) ),
			'gallery'        => array(),
			'business_unit'  => null,
			'related'        => array(),
			'terms'          => array(),
		);

		$status_key = Registry::status_key( $type );
		if ( $status_key && '' !== ( $values[ $status_key ] ?? '' ) ) {
			$options        = $defs[ $status_key ]['options'] ?? array();
			$data['status'] = array(
				'key'   => (string) $values[ $status_key ],
				'label' => (string) ( $options[ $values[ $status_key ] ] ?? '' ),
			);
		}
		if ( isset( $values['tagline'] ) ) {
			$data['tagline'] = (string) $values['tagline'];
		}
		if ( '' !== ( $values['client'] ?? '' ) && ! empty( $values['client_approved'] ) ) {
			$data['client'] = (string) $values['client'];
		}

		foreach ( self::SECTIONS as $key ) {
			if ( isset( $defs[ $key ] ) && ! empty( $values[ $key . '_approved' ] ) && '' !== trim( (string) ( $values[ $key ] ?? '' ) ) ) {
				$data['sections'][ $key ] = array(
					'label' => (string) $defs[ $key ]['label'],
					'html'  => wpautop( wp_kses( (string) $values[ $key ], Store::allowed_html() ) ),
				);
			}
		}
		// Industry fields have no switch: they are part of the approved record.
		foreach ( array( 'opportunities', 'capabilities' ) as $key ) {
			if ( isset( $defs[ $key ] ) && '' !== trim( (string) ( $values[ $key ] ?? '' ) ) ) {
				$data['sections'][ $key ] = array(
					'label' => (string) $defs[ $key ]['label'],
					'html'  => wpautop( wp_kses( (string) $values[ $key ], Store::allowed_html() ) ),
				);
			}
		}

		foreach ( (array) ( $values['gallery'] ?? array() ) as $att ) {
			$img = self::image( (int) $att );
			if ( $img ) {
				$data['gallery'][] = $img;
			}
		}

		foreach ( Registry::relationships( $type ) as $field => $def ) {
			$items = array();
			foreach ( Relations::valid_targets( $post->ID, (string) $field ) as $rid ) {
				$items[] = array(
					'id'    => $rid,
					'title' => (string) get_the_title( $rid ),
					'url'   => (string) get_permalink( $rid ),
					'type'  => (string) get_post_type( $rid ),
				);
			}
			if ( 'business_unit' === $field ) {
				$data['business_unit'] = $items ? $items[0] : null; // Hidden while unavailable (grace state).
			} elseif ( $items ) {
				$data['related'][ $field ] = $items;
			}
		}

		foreach ( get_object_taxonomies( $type ) as $tax ) {
			foreach ( (array) wp_get_object_terms( $post->ID, $tax ) as $term ) {
				$data['terms'][] = array(
					'taxonomy' => $tax,
					'id'       => (int) $term->term_id,
					'name'     => (string) $term->name,
					'url'      => (string) get_term_link( $term ),
				);
			}
		}
		return $data;
	}

	/**
	 * Public descriptor of an image.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<string,mixed>|null
	 */
	private static function image( int $attachment_id ): ?array {
		if ( ! $attachment_id ) {
			return null;
		}
		$url = wp_get_attachment_image_url( $attachment_id, 'large' );
		if ( ! $url ) {
			return null;
		}
		return array(
			'id'      => $attachment_id,
			'url'     => $url,
			'alt'     => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
			'caption' => (string) wp_get_attachment_caption( $attachment_id ),
			'width'   => (int) ( wp_get_attachment_metadata( $attachment_id )['width'] ?? 0 ),
			'height'  => (int) ( wp_get_attachment_metadata( $attachment_id )['height'] ?? 0 ),
		);
	}

	/**
	 * Eligible records of a type.
	 *
	 * @param string              $type Post type.
	 * @param array<string,mixed> $args limit, business_unit, industry, area, status, featured_home, orderby.
	 * @return array<int,array<string,mixed>>
	 */
	public static function query( string $type, array $args = array() ): array {
		if ( ! Types::is_ours( $type ) ) {
			return array();
		}
		$args  = wp_parse_args(
			$args,
			array(
				'limit'         => 12,
				'business_unit' => 0,
				'industry'      => 0,
				'area'          => '',
				'status'        => '',
				'featured_home' => false,
				'orderby'       => 'date',
			)
		);
		$query = array(
			'post_type'      => $type,
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, (int) $args['limit'] ) * 3,
			'no_found_rows'  => true,
			'orderby'        => 'title' === $args['orderby'] ? 'title' : 'date',
			'order'          => 'title' === $args['orderby'] ? 'ASC' : 'DESC',
		);
		$meta  = array();
		if ( $args['business_unit'] ) {
			$meta[] = array(
				'key'   => Registry::rel_key( 'business_unit' ),
				'value' => (string) (int) $args['business_unit'],
			);
		}
		if ( $args['industry'] ) {
			$meta[] = array(
				'key'   => Registry::rel_key( 'industries' ),
				'value' => (string) (int) $args['industry'],
			);
		}
		if ( '' !== $args['status'] ) {
			$meta[] = array(
				'key'   => Registry::meta_key( Registry::status_key( $type ) ),
				'value' => (string) $args['status'],
			);
		}
		if ( $args['featured_home'] ) {
			$meta[] = array(
				'key'   => Registry::meta_key( 'featured_home' ),
				'value' => '1',
			);
		}
		if ( $meta ) {
			$query['meta_query'] = $meta; // phpcs:ignore WordPress.DB.SlowDBQuery
		}
		if ( '' !== $args['area'] ) {
			$query['tax_query'] = array(
				array(
					'taxonomy' => Types::TAX_AREA,
					'field'    => is_numeric( $args['area'] ) ? 'term_id' : 'slug',
					'terms'    => $args['area'],
				),
			); // phpcs:ignore WordPress.DB.SlowDBQuery
		}
		$out = array();
		foreach ( ( new \WP_Query( $query ) )->posts as $p ) {
			$item = self::get( (int) $p->ID );
			if ( $item ) {
				$out[] = $item;
			}
			if ( count( $out ) >= (int) $args['limit'] ) {
				break;
			}
		}
		return $out;
	}
}

<?php
namespace Noblen\Core\Fields;

use Noblen\Core\Content\Types;

defined( 'ABSPATH' ) || exit;

/**
 * Single registry of every field. It drives storage, validation, the public
 * allowlist and the approval snapshot, so a field cannot be public without also
 * being part of the snapshot.
 *
 * Relationship fields are declared REQUIRED or OPTIONAL here. A relationship
 * field cannot be registered without a class.
 */
final class Registry {

	public const PROJECT_STATUS = array(
		'completed_assignment' => 'Completed Assignment',
		'ongoing_engagement'   => 'Ongoing Engagement',
		'strategic_initiative' => 'Strategic Initiative',
	);

	public const PRODUCT_STAGE = array(
		'concept'        => 'Concept',
		'in_development' => 'In Development',
		'pilot'          => 'Pilot',
		'live'           => 'Live',
	);

	public const INDUSTRY_STATUS = array(
		'active_operation'      => 'Active operation',
		'available_service'     => 'Available service',
		'strategic_growth_area' => 'Strategic growth area',
	);

	/**
	 * Field definitions for a content type.
	 *
	 * @param string $type Post type.
	 * @return array<string,array<string,mixed>>
	 */
	public static function fields( string $type ): array {
		$rich = static fn( string $label, bool $required = false ): array => array(
			'label' => $label, 'kind' => 'richtext', 'public' => true, 'required' => $required, 'switch' => true,
		);
		$rel = static fn( string $label, string $target, string $class_name, bool $multiple = true ): array => array(
			'label' => $label, 'kind' => 'rel', 'public' => true, 'target' => $target, 'class' => $class_name, 'multiple' => $multiple,
		);

		switch ( $type ) {
			case Types::PROJECT:
				$fields = array(
					'client'           => array( 'label' => 'Client or commissioning organisation', 'kind' => 'text', 'public' => true, 'switch' => true ),
					'overview'         => $rich( 'Project overview', true ),
					'scope'            => $rich( 'Project scope' ),
					'contribution'     => $rich( "Noblen's contribution" ),
					'approach'         => $rich( 'Project approach' ),
					'outcomes'         => $rich( 'Outcomes and impact (verified only)' ),
					'status'           => array( 'label' => 'Project status', 'kind' => 'enum', 'public' => true, 'required' => true, 'options' => self::PROJECT_STATUS ),
					'business_unit'    => $rel( 'Responsible Noblen business unit', Types::COMPANY, 'required', false ),
					'industries'       => $rel( 'Industry', Types::INDUSTRY, 'optional' ),
					'services'         => $rel( 'Services delivered', Types::SERVICE, 'optional' ),
					'related_products' => $rel( 'Related products', Types::PRODUCT, 'optional' ),
					'gallery'          => array( 'label' => 'Gallery', 'kind' => 'gallery', 'public' => true ),
					'video_url'        => array( 'label' => 'Video URL', 'kind' => 'url', 'public' => true ),
					'featured_home'    => array( 'label' => 'Feature on the homepage', 'kind' => 'bool', 'public' => true ),
				);
				break;
			case Types::PRODUCT:
				$fields = array(
					'overview'         => $rich( 'Product overview', true ),
					'scope'            => $rich( 'Capabilities and scope' ),
					'approach'         => $rich( 'Approach' ),
					'outcomes'         => $rich( 'Outcomes (verified only)' ),
					'stage'            => array( 'label' => 'Product stage', 'kind' => 'enum', 'public' => true, 'required' => true, 'options' => self::PRODUCT_STAGE ),
					'business_unit'    => $rel( 'Responsible Noblen business unit', Types::COMPANY, 'required', false ),
					'industries'       => $rel( 'Industry', Types::INDUSTRY, 'optional' ),
					'related_projects' => $rel( 'Related projects', Types::PROJECT, 'optional' ),
					'gallery'          => array( 'label' => 'Gallery', 'kind' => 'gallery', 'public' => true ),
					'video_url'        => array( 'label' => 'Video URL', 'kind' => 'url', 'public' => true ),
					'featured_home'    => array( 'label' => 'Feature on the homepage', 'kind' => 'bool', 'public' => true ),
				);
				break;
			case Types::COMPANY:
				$fields = array(
					'tagline'    => array( 'label' => 'Tagline', 'kind' => 'text', 'public' => true ),
					'industries' => $rel( 'Industries', Types::INDUSTRY, 'optional' ),
					'services'   => $rel( 'Services', Types::SERVICE, 'optional' ),
				);
				break;
			case Types::INDUSTRY:
				$fields = array(
					'status'        => array( 'label' => 'Industry status', 'kind' => 'enum', 'public' => true, 'options' => self::INDUSTRY_STATUS ),
					'opportunities' => array( 'label' => 'Business opportunities', 'kind' => 'richtext', 'public' => true ),
					'capabilities'  => array( 'label' => "Noblen's capabilities", 'kind' => 'richtext', 'public' => true ),
					'companies'     => $rel( 'Related business units', Types::COMPANY, 'optional' ),
					'services'      => $rel( 'Related services', Types::SERVICE, 'optional' ),
				);
				break;
			case Types::SERVICE:
				$fields = array(
					'industries' => $rel( 'Industries', Types::INDUSTRY, 'optional' ),
					'companies'  => $rel( 'Business units', Types::COMPANY, 'optional' ),
				);
				break;
			default:
				return array();
		}

		$fields = (array) apply_filters( 'noblen_core_fields', $fields, $type );
		foreach ( $fields as $key => $def ) {
			if ( 'rel' === ( $def['kind'] ?? '' ) && ! in_array( $def['class'] ?? '', array( 'required', 'optional' ), true ) ) {
				// A relationship field cannot exist without a class.
				_doing_it_wrong( __METHOD__, esc_html( "Relationship field {$key} has no class." ), '0.1.0' );
			}
		}
		return $fields;
	}

	/**
	 * Fields that appear publicly.
	 *
	 * @param string $type Post type.
	 * @return array<string,array<string,mixed>>
	 */
	public static function public_fields( string $type ): array {
		return array_filter( self::fields( $type ), static fn( $d ) => ! empty( $d['public'] ) );
	}

	/**
	 * Relationship fields.
	 *
	 * @param string $type  Post type.
	 * @param string $class Optional class filter: required or optional.
	 * @return array<string,array<string,mixed>>
	 */
	public static function relationships( string $type, string $class = '' ): array {
		return array_filter(
			self::fields( $type ),
			static fn( $d ) => 'rel' === ( $d['kind'] ?? '' ) && ( '' === $class || ( $d['class'] ?? '' ) === $class )
		);
	}

	/**
	 * Key of the field that holds the business status or stage for a type, if any.
	 *
	 * @param string $type Post type.
	 */
	public static function status_key( string $type ): string {
		foreach ( self::fields( $type ) as $key => $def ) {
			if ( 'enum' === ( $def['kind'] ?? '' ) ) {
				return (string) $key;
			}
		}
		return '';
	}

	/** Meta key for a scalar field. @param string $key Field key. */
	public static function meta_key( string $key ): string {
		return '_noblen_f_' . $key;
	}

	/** Meta key for a relationship field. @param string $key Field key. */
	public static function rel_key( string $key ): string {
		return '_noblen_rel_' . $key;
	}

	/** Meta key for a "verified and approved for display" switch. @param string $key Field key. */
	public static function switch_key( string $key ): string {
		return '_noblen_f_' . $key . '_approved';
	}

	/**
	 * Whether a meta key belongs to the snapshot (a public field's storage).
	 *
	 * @param string $meta_key Meta key.
	 */
	public static function is_snapshot_meta( string $meta_key ): bool {
		return 0 === strpos( $meta_key, '_noblen_f_' ) || 0 === strpos( $meta_key, '_noblen_rel_' ) || '_thumbnail_id' === $meta_key;
	}
}

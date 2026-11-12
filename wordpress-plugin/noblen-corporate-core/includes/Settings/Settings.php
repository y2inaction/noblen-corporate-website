<?php
namespace Noblen\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Business settings owned by the plugin so they survive a theme change.
 *
 * Nothing is pre-filled with business content. Every value starts empty and
 * is entered and approved by the owner in the dashboard.
 */
final class Settings {

	public const OPTION = 'noblen_core_settings';

	/**
	 * Field definitions: key => array( label, kind, public ).
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function fields(): array {
		return array(
			'company_name'     => array(
				'label'  => 'Company name',
				'kind'   => 'text',
				'public' => true,
			),
			'tagline'          => array(
				'label'  => 'Tagline',
				'kind'   => 'text',
				'public' => true,
			),
			'contact_email'    => array(
				'label'  => 'Contact email',
				'kind'   => 'email',
				'public' => true,
			),
			'contact_phone'    => array(
				'label'  => 'Contact phone',
				'kind'   => 'text',
				'public' => true,
			),
			'contact_address'  => array(
				'label'  => 'Address',
				'kind'   => 'textarea',
				'public' => true,
			),
			'whatsapp_number'  => array(
				'label'  => 'WhatsApp number',
				'kind'   => 'text',
				'public' => true,
			),
			'social_linkedin'  => array(
				'label'  => 'LinkedIn URL',
				'kind'   => 'url',
				'public' => true,
			),
			'social_x'         => array(
				'label'  => 'X (Twitter) URL',
				'kind'   => 'url',
				'public' => true,
			),
			'social_facebook'  => array(
				'label'  => 'Facebook URL',
				'kind'   => 'url',
				'public' => true,
			),
			'social_instagram' => array(
				'label'  => 'Instagram URL',
				'kind'   => 'url',
				'public' => true,
			),
			'social_youtube'   => array(
				'label'  => 'YouTube URL',
				'kind'   => 'url',
				'public' => true,
			),
			'hero_headline'    => array(
				'label'  => 'Homepage headline',
				'kind'   => 'text',
				'public' => true,
			),
			'hero_intro'       => array(
				'label'  => 'Homepage introduction',
				'kind'   => 'textarea',
				'public' => true,
			),
			'group_intro'      => array(
				'label'  => 'Group introduction',
				'kind'   => 'textarea',
				'public' => true,
			),
			'cta_headline'     => array(
				'label'  => 'Closing call-to-action headline',
				'kind'   => 'text',
				'public' => true,
			),
			'analytics_id'     => array(
				'label'  => 'Analytics measurement ID (empty = no analytics)',
				'kind'   => 'analytics',
				'public' => true,
			),
		);
	}

	/**
	 * Sanitise a value for a field kind.
	 *
	 * @param string $kind  Kind.
	 * @param mixed  $value Raw value.
	 */
	private static function clean( string $kind, $value ): string {
		$value = is_scalar( $value ) ? (string) $value : '';
		switch ( $kind ) {
			case 'email':
				return sanitize_email( $value );
			case 'url':
				$url = esc_url_raw( $value, array( 'https', 'http' ) );
				return $url;
			case 'textarea':
				return sanitize_textarea_field( $value );
			case 'analytics':
				return preg_match( '/^[A-Za-z0-9\-]{4,24}$/', trim( $value ) ) ? trim( $value ) : '';
			default:
				return sanitize_text_field( $value );
		}
	}

	/**
	 * Sanitise a full settings array.
	 *
	 * @param array<string,mixed> $input Raw input.
	 * @return array<string,string>
	 */
	public static function sanitize( array $input ): array {
		$out = array();
		foreach ( self::fields() as $key => $def ) {
			$out[ $key ] = isset( $input[ $key ] ) ? self::clean( (string) $def['kind'], $input[ $key ] ) : '';
		}
		return $out;
	}

	/**
	 * All stored settings.
	 *
	 * @return array<string,string>
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION, array() );
		return self::sanitize( is_array( $stored ) ? $stored : array() );
	}

	/**
	 * One setting.
	 *
	 * @param string $key Key.
	 */
	public static function get( string $key ): string {
		$all = self::all();
		return $all[ $key ] ?? '';
	}

	/**
	 * Save settings (already authorised by the caller).
	 *
	 * @param array<string,mixed> $input Raw input.
	 */
	public static function save( array $input ): void {
		update_option( self::OPTION, self::sanitize( $input ), false );
	}

	/**
	 * Whether separation of duties is enforced (on by default).
	 */
	public static function separation_of_duties(): bool {
		$v = get_option( 'noblen_core_separation_of_duties', '1' );
		return '0' !== (string) $v && false !== $v && '' !== $v;
	}

	/**
	 * Whether data removal on uninstall was explicitly enabled (off by default).
	 */
	public static function remove_data_on_uninstall(): bool {
		return '1' === (string) get_option( 'noblen_core_remove_data_on_uninstall', '0' );
	}
}

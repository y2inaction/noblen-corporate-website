<?php
namespace Noblen\Core\Setup;

use Noblen\Core\Content\Types;
use Noblen\Core\Seed\DraftSeeder;

defined( 'ABSPATH' ) || exit;

/**
 * What the Website Setup creates. Data only.
 *
 * Content rules:
 *  - Only information the owner has established is written as fact: the parent company, the positioning, the brand promise,
 *    Noblen AI's role and the five business areas. Everything else is a clearly labelled, editable placeholder.
 *  - A placeholder reads "[Owner to complete: ...]". A page that still contains one is never treated as ready for the public.
 *  - Business records are names only, always drafts, with no content, clearance or approval.
 *  - Nothing here invents a client, partner, testimonial, statistic, award, outcome, date or capability.
 */
final class Blueprint {

	/** Placeholder pattern. A page containing a match is not ready for the public. */
	public const PLACEHOLDER = '/\[Owner to complete[^\]]*\]/i';

	/** Established positioning, used where the owner has given it. */
	public const COMPANY     = 'Noblen Technologies Limited';
	public const POSITIONING = 'Autonomous Operating Systems Provider';
	public const PROMISE     = 'AI That Gets Things Done';

	/**
	 * A placeholder paragraph.
	 *
	 * @param string $what What the owner must supply.
	 */
	private static function todo( string $what ): string {
		return '<p class="noblen-setup-todo">[Owner to complete: ' . $what . ']</p>';
	}

	/**
	 * Pages. Slug, title, purpose, role and editable content. All are created as drafts.
	 *
	 * Role "front_page" is the home page (its sections come from the plugin, so no body content is needed),
	 * "posts_page" is the Insights page (it lists posts, so no body content is needed), "content" needs owner-completed content.
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function pages(): array {
		return array(
			'home'         => array(
				'title'   => 'Home',
				'slug'    => 'home',
				'role'    => 'front_page',
				'purpose' => 'The front page. The theme builds it from the hero settings and from approved records, and hides every section that has nothing to show.',
				'content' => '',
			),
			'about'        => array(
				'title'   => 'About Noblen',
				'slug'    => 'about',
				'role'    => 'content',
				'purpose' => 'Who Noblen is: the parent company, its positioning and its business areas.',
				'content' => '<h2>Who we are</h2>'
					. '<p>' . self::COMPANY . ' is the parent company of the Noblen group. We are an ' . self::POSITIONING . '. ' . self::PROMISE . '.</p>'
					. '<h2>Our business areas</h2>'
					. '<p>The group\'s business areas include AI, Property, Media, Energy and Cloud. Noblen AI is the group\'s central intelligence and execution business.</p>'
					. '<h2>Our story</h2>' . self::todo( 'the company history, from verified sources' )
					. '<h2>Vision and mission</h2>' . self::todo( 'the group vision and mission, approved by the owner' )
					. '<h2>Leadership</h2>' . self::todo( 'leadership profiles, only with each person\'s consent' ),
			),
			'insights'     => array(
				'title'   => 'Insights',
				'slug'    => 'insights',
				'role'    => 'posts_page',
				'purpose' => 'The news and articles page. It lists published posts once the administrator makes it the posts page.',
				'content' => '',
			),
			'partnerships' => array(
				'title'   => 'Partnerships',
				'slug'    => 'partnerships',
				'role'    => 'content',
				'purpose' => 'Verified partnerships. Nothing may be listed without the partner\'s written permission.',
				'content' => '<h2>Partnerships</h2>'
					. self::todo( 'verified partnerships only, each with the partner\'s written permission; remove this page from navigation if there is nothing to show' ),
			),
			'contact'      => array(
				'title'   => 'Contact',
				'slug'    => 'contact',
				'role'    => 'content',
				'purpose' => 'How to reach Noblen. Contact details come from Noblen, Settings, and appear in the footer.',
				'content' => '<h2>Get in touch</h2>'
					. self::todo( 'a short welcome and how enquiries are handled; enter the verified email, phone and address under Noblen, Settings' ),
			),
			'privacy'      => array(
				'title'   => 'Privacy Policy',
				'slug'    => 'privacy-policy',
				'role'    => 'content',
				'purpose' => 'Legal page for the Legal navigation.',
				'content' => '<h2>Privacy Policy</h2>' . self::todo( 'the privacy policy, reviewed by a qualified adviser for the business' ),
			),
			'terms'        => array(
				'title'   => 'Terms and Conditions',
				'slug'    => 'terms-and-conditions',
				'role'    => 'content',
				'purpose' => 'Legal page for the Legal navigation.',
				'content' => '<h2>Terms and Conditions</h2>' . self::todo( 'the terms, reviewed by a qualified adviser for the business' ),
			),
		);
	}

	/**
	 * Sections that the plugin serves as archives, not as pages.
	 *
	 * The five record types already own the public addresses /group/, /industries/, /solutions/, /projects/ and /products/.
	 * A WordPress page with the same address would never be reached, so no duplicate page is created: the navigation links to
	 * the archive instead, and only once that archive has at least one published record.
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function archives(): array {
		return array(
			'group'      => array(
				'title'   => 'Our Group',
				'type'    => Types::COMPANY,
				'purpose' => 'The group\'s business units.',
			),
			'industries' => array(
				'title'   => 'Industries',
				'type'    => Types::INDUSTRY,
				'purpose' => 'The industries Noblen serves.',
			),
			'solutions'  => array(
				'title'   => 'Solutions & Services',
				'type'    => Types::SERVICE,
				'purpose' => 'Solutions and services.',
			),
			'portfolio'  => array(
				'title'   => 'Portfolio',
				'type'    => Types::PROJECT,
				'purpose' => 'Projects and engagements.',
			),
			'products'   => array(
				'title'   => 'Products & Platforms',
				'type'    => Types::PRODUCT,
				'purpose' => 'Products and platforms. Kept separate from projects.',
			),
		);
	}

	/**
	 * Menus: name, theme location and the destinations in order. Destination form: "page:<key>", "archive:<key>" or "home".
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function menus(): array {
		return array(
			'primary' => array(
				'name'     => 'Primary Navigation',
				'location' => 'primary',
				'items'    => array( 'home', 'page:about', 'archive:group', 'archive:industries', 'archive:solutions', 'archive:portfolio', 'archive:products', 'page:insights', 'page:partnerships', 'page:contact' ),
			),
			'footer'  => array(
				'name'     => 'Footer Navigation',
				'location' => 'footer',
				'items'    => array( 'page:about', 'archive:group', 'archive:portfolio', 'page:insights', 'page:partnerships', 'page:contact' ),
			),
			'legal'   => array(
				'name'     => 'Legal Navigation',
				'location' => 'legal',
				'items'    => array( 'page:privacy', 'page:terms' ),
			),
		);
	}

	/**
	 * Initial draft records, names only.
	 *
	 * Business units and products reuse the existing starter-draft names. Industries are the five business areas the owner stated.
	 * Services are the eight services documented in the media brochure. Projects are six institutional project titles documented in
	 * the content inventory (no client names, no profile films of private individuals, no broadcaster productions whose wording rule
	 * is still open). Titles only: scope, status, dates and clients are entered and cleared through the normal workflow.
	 *
	 * @return array<string,string[]> Post type => titles.
	 */
	public static function records(): array {
		return array(
			Types::COMPANY  => DraftSeeder::COMPANIES,
			Types::INDUSTRY => array( 'AI', 'Property', 'Media', 'Energy', 'Cloud' ),
			Types::SERVICE  => array(
				'Documentary filmmaking',
				'Radio and TV production',
				'Online content',
				'Business branding',
				'Web development',
				'Data management',
				'Events coverage',
				'Live streaming',
			),
			Types::PRODUCT  => DraftSeeder::PRODUCTS,
			Types::PROJECT  => array(
				'The Story Never Told',
				'Call Masking and Refiling',
				'Adding Value to Livelihood',
				'Automated Weather Stations (AWS) and Simple Weather Recording (SWR)',
				'Matrix 360% Media Engagement Campaign',
				'The Ground Breaking Of Mega Project',
			),
		);
	}

	/**
	 * Homepage text applied only when the administrator chooses it and only to empty settings.
	 *
	 * @return array<string,string>
	 */
	public static function hero_defaults(): array {
		return array(
			'hero_headline' => self::PROMISE,
			'hero_intro'    => self::COMPANY . ', an ' . self::POSITIONING . '.',
		);
	}
}

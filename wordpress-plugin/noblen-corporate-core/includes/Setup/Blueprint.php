<?php
namespace Noblen\Core\Setup;

use Noblen\Core\Content\Types;
use Noblen\Core\Seed\DraftSeeder;

defined( 'ABSPATH' ) || exit;

/**
 * What the Website Setup creates: complete, editable starter pages for the Noblen corporate website. Data and markup only.
 *
 * Content rules:
 *  - Facts are limited to what the owner has established: the parent company, the positioning, the mission, the brand promise, the five
 *    business areas, the Noblen AI positioning, and the services and capabilities recorded in the project's content inventory.
 *  - Nothing invents a client, partnership, award, testimonial, registration, financial figure or project achievement.
 *  - Illustrative material (sample projects, sample contact details, generic area descriptions) is labelled and listed in the content
 *    review checklist so the owner can replace it.
 *  - A page that still contains "[Owner to complete: ...]" is never published by the setup. The two legal pages carry that marker on
 *    purpose: they need legal review and are never published automatically.
 *  - Markup uses only the theme's component classes and plain elements, so it survives content filtering on any site.
 */
final class Blueprint {

	/** Placeholder pattern. A page containing a match is not ready for the public. */
	public const PLACEHOLDER = '/\[Owner to complete[^\]]*\]/i';

	/** Established positioning. */
	public const COMPANY     = 'Noblen Technologies Limited';
	public const POSITIONING = 'Autonomous Operating Systems Provider';
	public const PROMISE     = 'AI That Gets Things Done';
	public const MISSION     = 'helping individuals and companies improve operations and scale their businesses through collective solutions';
	public const MISSION_TO  = 'help individuals and companies improve operations and scale their businesses through collective solutions';

	/* ------------------------------------------------------------ markup helpers */

	/**
	 * A placeholder paragraph.
	 *
	 * @param string $what What the owner must supply.
	 */
	private static function todo( string $what ): string {
		return '<p class="noblen-setup-todo">[Owner to complete: ' . $what . ']</p>';
	}

	/**
	 * A tile (card with an icon). A link, when given, makes the whole tile clickable.
	 *
	 * @param string $icon  Icon name.
	 * @param string $title Heading.
	 * @param string $text  Text.
	 * @param string $link  Optional address.
	 * @param string $label Optional link text.
	 * @param string $id    Optional anchor ID.
	 */
	private static function tile( string $icon, string $title, string $text, string $link = '', string $label = 'Learn more', string $id = '' ): string {
		$more = '' !== $link ? '<a class="more" href="' . $link . '">' . $label . '</a>' : '';
		$attr = '' !== $id ? ' id="' . $id . '"' : '';
		return '<div class="tile"' . $attr . '><span class="tile__icon"><span class="icon icon--' . $icon . '" aria-hidden="true"></span></span><h3>' . $title . '</h3><p>' . $text . '</p>' . $more . '</div>';
	}

	/**
	 * A grid of tiles.
	 *
	 * @param string[] $tiles Tile markup.
	 */
	private static function tiles( array $tiles, string $class = '' ): string {
		return '<div class="tiles' . ( $class ? ' ' . $class : '' ) . '">' . implode( '', $tiles ) . '</div>';
	}

	/**
	 * A two-column block: text beside decorative artwork.
	 *
	 * @param string $text Text markup.
	 * @param string $art  Art class suffix (an icon name).
	 * @param bool   $flip Put the artwork first.
	 * @param bool   $green Use the green gradient.
	 */
	private static function split( string $text, string $art, bool $flip = false, bool $green = false ): string {
		return '<div class="split' . ( $flip ? ' split--flip' : '' ) . '"><div class="split__text">' . $text . '</div><div class="split__art"><div class="art art--' . $art . ( $green ? ' art--green' : '' ) . '" aria-hidden="true"></div></div></div>';
	}

	/**
	 * A numbered process.
	 *
	 * @param array<int,array<int,string>> $steps Pairs of title and text.
	 */
	private static function steps( array $steps ): string {
		$out = '<ol class="steps">';
		foreach ( $steps as $s ) {
			$out .= '<li><strong>' . $s[0] . '</strong>' . $s[1] . '</li>';
		}
		return $out . '</ol>';
	}

	/**
	 * A contact card.
	 *
	 * @param string $icon  Icon.
	 * @param string $title Heading.
	 * @param string $text  Text.
	 * @param string $link  Link markup.
	 */
	private static function contact_card( string $icon, string $title, string $text, string $link ): string {
		return '<div class="contact-card"><span class="tile__icon"><span class="icon icon--' . $icon . '" aria-hidden="true"></span></span><div><h3>' . $title . '</h3><p>' . $text . '</p><p>' . $link . '</p></div></div>';
	}

	/**
	 * A closing call to action.
	 *
	 * @param string $title Heading.
	 * @param string $text  Text.
	 */
	private static function cta( string $title, string $text ): string {
		return '<div class="cta-band"><h2>' . $title . '</h2><p>' . $text . '</p><p><a class="btn" href="/contact/">Talk to Noblen</a></p></div>';
	}

	/**
	 * A sample (illustrative) project card.
	 *
	 * @param string $art   Art icon.
	 * @param string $title Title.
	 * @param string $text  Text.
	 * @param string $area  Area label.
	 * @param bool   $green Green gradient.
	 */
	private static function sample( string $art, string $title, string $text, string $area, bool $green = false ): string {
		return '<div class="sample"><div class="art art--' . $art . ( $green ? ' art--green' : '' ) . '" aria-hidden="true"></div><div class="sample__body"><span class="badge badge--sample">Illustrative sample</span><h3>' . $title . '</h3><p>' . $text . '</p><p><strong>' . $area . '</strong></p></div></div>';
	}

	/**
	 * Content of the home page: designed sections shown below the hero.
	 */
	private static function home_content(): string {
		$areas = self::tiles(
			array(
				self::tile( 'ai', 'Noblen AI', 'The group’s central intelligence and execution business: an ' . self::POSITIONING . '.', '/our-businesses/#ai', 'Explore Noblen AI' ),
				self::tile( 'property', 'Noblen Property', 'Property-related capabilities, from development and investment to management and advisory.', '/our-businesses/#property', 'Explore Noblen Property' ),
				self::tile( 'media', 'Noblen Media', 'Storytelling, production and communications services for organisations and programmes.', '/our-businesses/#media', 'Explore Noblen Media' ),
				self::tile( 'energy', 'Noblen Energy', 'A business area for energy-related solutions and sustainability-minded ambitions.', '/our-businesses/#energy', 'Explore Noblen Energy' ),
				self::tile( 'cloud', 'Noblen Cloud', 'Cloud and digital infrastructure capabilities that keep operations reliable and scalable.', '/our-businesses/#cloud', 'Explore Noblen Cloud' ),
			)
		);
		$how   = self::steps(
			array(
				array( 'Understand', 'We start with the work that needs to be done and the people it affects.' ),
				array( 'Design', 'We shape practical solutions around real operating needs and constraints.' ),
				array( 'Deliver', 'We build, integrate and support so ideas become working systems.' ),
				array( 'Improve', 'We learn from use and keep improving, so progress compounds over time.' ),
			)
		);
		return '<div class="band"><div class="wrap"><div class="band__head"><span class="eyebrow">One group. Connected capabilities.</span><h2>Technology, innovation and impact</h2><p class="lead">' . self::COMPANY . ' brings together complementary business areas to ' . self::MISSION_TO . '.</p></div>' . $areas . '</div></div>'
			. '<div class="band band--tint"><div class="wrap">' . self::split(
				'<span class="eyebrow">Noblen AI</span><h2>' . self::PROMISE . '</h2><p>Noblen AI is the group’s central intelligence and execution business. As an ' . self::POSITIONING . ', its direction is to connect intelligent software with the day-to-day work of organisations, so insight turns into action.</p><p><a class="btn" href="/our-businesses/#ai">Discover Noblen AI</a></p>',
				'ai'
			) . '</div></div>'
			. '<div class="band"><div class="wrap"><div class="band__head"><span class="eyebrow">How we work</span><h2>From plans to practical execution</h2><p class="lead">A simple, repeatable way of working that keeps technology tied to outcomes people care about.</p></div>' . $how . '</div></div>'
			. '<div class="band band--tint"><div class="wrap">' . self::split(
				'<span class="eyebrow">Solutions and services</span><h2>Capabilities that scale with you</h2><p>From intelligent systems and digital platforms to media production, property services and cloud infrastructure, the group offers connected capabilities under one roof.</p><ul class="chips"><li>Intelligent systems</li><li>Digital transformation</li><li>Web and platforms</li><li>Media production</li><li>Property services</li><li>Cloud infrastructure</li></ul><p><a class="btn" href="/solutions-and-services/">See solutions and services</a></p>',
				'layers',
				true,
				true
			) . '</div></div>'
			. '<div class="band"><div class="wrap">' . self::cta( 'Let’s turn your next idea into meaningful progress.', 'Tell us what you want to build, improve or make possible.' ) . '</div></div>';
	}

	/**
	 * Pages. All are created as drafts for the owner to review.
	 *
	 * Fields: title, slug, role, purpose, excerpt (the lead line under the page title), content, auto (published by the quick start when
	 * true), review (what the owner should replace later; empty when nothing).
	 * Role "front_page" is the home page, "posts_page" is the Insights page, "content" pages carry their own text.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function pages(): array {
		$privacy_intro = '<div class="legal-note"><p><strong>Draft for legal review.</strong></p>' . self::todo( 'legal review of this privacy policy by a qualified adviser, then remove this notice' ) . '</div>';
		$terms_intro   = '<div class="legal-note"><p><strong>Draft for legal review.</strong></p>' . self::todo( 'legal review of these terms by a qualified adviser, then remove this notice' ) . '</div>';

		$contact = self::tiles(
			array(
				self::tile( 'mail', 'Email', 'For general enquiries, partnerships and project conversations.', 'mailto:info@noblen.org', 'info@noblen.org' ),
				self::tile( 'phone', 'Phone', 'Call during normal business hours.', 'tel:+2340000000000', '+234 000 000 0000' ),
				self::tile( 'whatsapp', 'WhatsApp', 'A quick way to start a conversation.', 'https://wa.me/2340000000000', 'Message us' ),
				self::tile( 'pin', 'Office', 'Business address to be confirmed.', '', '' ),
			)
		);

		return array(
			'home'         => array(
				'title'   => 'Home',
				'slug'    => 'home',
				'role'    => 'front_page',
				'purpose' => 'The corporate homepage: hero, business areas, Noblen AI, ways of working and a call to action. Sections that fill from approved records appear below it as records are published.',
				'excerpt' => '',
				'content' => self::home_content(),
				'auto'    => true,
				'review'  => '',
			),
			'about'        => array(
				'title'   => 'About Noblen',
				'slug'    => 'about',
				'role'    => 'content',
				'purpose' => 'Corporate overview: who Noblen is, its purpose, its business areas and how it works.',
				'excerpt' => 'Technology, innovation and impact, delivered by a group of connected businesses.',
				'content' => '<p class="lead">' . self::COMPANY . ' is the parent company of the Noblen group. We bring technology, innovation and practical execution together to help people and organisations do more with what they have.</p>'
					. self::split(
						'<h2>Who we are</h2><p>The group exists to ' . self::MISSION_TO . '. Our businesses work across AI, Property, Media, Energy and Cloud, sharing one standard of delivery and one commitment: <strong>' . self::PROMISE . '</strong>.</p><p>Noblen AI is the group’s central intelligence and execution business, positioned as an ' . self::POSITIONING . '.</p>',
						'network'
					)
					. '<h2>Our purpose</h2><div class="callout"><p>' . ucfirst( self::MISSION ) . '.</p></div>'
					. '<h2>Business areas</h2>' . self::tiles(
						array(
							self::tile( 'ai', 'Noblen AI', 'Central intelligence and execution business.' ),
							self::tile( 'property', 'Noblen Property', 'Property development, investment and management.' ),
							self::tile( 'media', 'Noblen Media', 'Media production and communications.' ),
							self::tile( 'energy', 'Noblen Energy', 'Energy-related solutions.' ),
							self::tile( 'cloud', 'Noblen Cloud', 'Cloud and digital infrastructure.' ),
						)
					)
					. '<h2>How we work</h2>' . self::steps(
						array(
							array( 'Purpose before technology', 'We begin with the problem and the people affected.' ),
							array( 'Practical innovation', 'We build what works in the real operating environment.' ),
							array( 'Shared standards', 'Every business in the group follows the same discipline of delivery.' ),
							array( 'Long-term value', 'We aim for progress that lasts, not just launches.' ),
						)
					)
					. '<h2>Our story and leadership</h2><p>This website introduces the group and its areas of work. Our full company history and leadership profiles will be added here once they have been approved for publication.</p>'
					. self::cta( 'Work with Noblen', 'Share what you are trying to achieve and we will explore how the group can help.' ),
				'auto'    => true,
				'review'  => 'Add the company history and approved leadership profiles when available.',
			),
			'businesses'   => array(
				'title'   => 'Our Businesses',
				'slug'    => 'our-businesses',
				'role'    => 'content',
				'purpose' => 'The Noblen group’s business areas: AI, Property, Media, Energy and Cloud.',
				'excerpt' => 'Five connected business areas under one parent company.',
				'content' => '<p class="lead">' . self::COMPANY . ' brings together five business areas. Each has its own focus, and together they let us serve clients end to end.</p>'
					. self::split( '<h2 id="ai">Noblen AI</h2><p>Noblen AI is the group’s central intelligence and execution business. Positioned as an <strong>' . self::POSITIONING . '</strong>, it focuses on intelligent systems that help organisations plan, decide and act.</p><p class="small">' . self::PROMISE . '</p>', 'ai' )
					. self::split( '<h2 id="property">Noblen Property</h2><p>Property-related capabilities: real estate development, property investment, joint-venture development, residential and commercial property, property management, land acquisition and development, and property marketing and consultancy.</p>', 'property', true )
					. self::split( '<h2 id="media">Noblen Media</h2><p>Media and communications services including documentary filmmaking, radio and TV production, online content, business branding, web development, data management, events coverage and live streaming.</p>', 'media' )
					. self::split( '<h2 id="energy">Noblen Energy</h2><p>The group’s business area for energy-related solutions and sustainability-minded ambitions, connecting technology with practical energy and resilience needs.</p>', 'energy', true, true )
					. self::split( '<h2 id="cloud">Noblen Cloud</h2><p>Cloud and digital infrastructure capabilities that help organisations run reliable, secure and scalable digital operations.</p>', 'cloud' )
					. '<div class="callout"><p>Individual business-unit pages with full detail are published from the Noblen dashboard as each unit’s information is approved.</p></div>'
					. self::cta( 'Find the right Noblen business for your needs', 'Tell us about your goals and we will connect you with the right team.' ),
				'auto'    => true,
				'review'  => 'Descriptions for Noblen Energy and Noblen Cloud are generic; replace them with verified details.',
			),
			'industries'   => array(
				'title'   => 'Industries',
				'slug'    => 'industries-we-serve',
				'role'    => 'content',
				'purpose' => 'The sectors the group works in.',
				'excerpt' => 'Practical solutions for the sectors where technology can make the most difference.',
				'content' => '<p class="lead">Our work spans several sectors. The areas below reflect where the group’s businesses operate and where we are building capability.</p>'
					. self::tiles(
						array(
							self::tile( 'media', 'Media and communications', 'Documentary, broadcast, online content, branding and event coverage.' ),
							self::tile( 'ai', 'ICT and digital transformation', 'Intelligent systems, software, digital platforms and automation.' ),
							self::tile( 'leaf', 'Climate, agriculture and development', 'Digital solutions that support climate information, agriculture and development programmes.' ),
							self::tile( 'property', 'Real estate and property', 'Development, investment, management and advisory.' ),
							self::tile( 'energy', 'Energy', 'Energy-related solutions and sustainability-minded initiatives.' ),
							self::tile( 'people', 'Corporate and enterprise', 'Operational improvement for businesses and institutions of different sizes.' ),
						),
						'tiles--3'
					)
					. '<div class="callout"><p>Detailed industry pages are published from the Noblen dashboard when each sector’s information has been verified and approved.</p></div>'
					. self::cta( 'Is your sector not listed?', 'Many of our solutions apply across industries. Tell us about your context.' ),
				'auto'    => true,
				'review'  => 'Confirm the final list of industries and add verified detail for each.',
			),
			'solutions'    => array(
				'title'   => 'Solutions & Services',
				'slug'    => 'solutions-and-services',
				'role'    => 'content',
				'purpose' => 'What the group offers: intelligent systems, digital services, media, property and cloud.',
				'excerpt' => 'Connected capabilities that help organisations improve operations and scale.',
				'content' => '<p class="lead">From intelligent systems to media production and property services, the group offers capabilities that work well alone and better together.</p>'
					. '<h2>Technology and AI</h2>' . self::tiles(
						array(
							self::tile( 'ai', 'AI and intelligent systems', 'Systems that learn from operations and help teams act.' ),
							self::tile( 'gear', 'Business process automation', 'Reduce repetitive work and keep processes consistent.' ),
							self::tile( 'compass', 'ICT consultancy and digital transformation', 'Plan and carry out practical digital change.' ),
							self::tile( 'layers', 'Enterprise software and platforms', 'Software and digital platforms built around your workflow.' ),
						)
					)
					. '<h2>Media and communications</h2>' . self::tiles(
						array(
							self::tile( 'media', 'Film, radio and TV production', 'Documentary filmmaking and radio and TV production.' ),
							self::tile( 'globe', 'Online content and web', 'Online content and web development.' ),
							self::tile( 'spark', 'Business branding', 'Identity and branding for businesses.' ),
							self::tile( 'chat', 'Events and live streaming', 'Events coverage and live streaming.' ),
						)
					)
					. '<h2>Data, cloud, property and climate</h2>' . self::tiles(
						array(
							self::tile( 'chart', 'Data management', 'Collect, organise and use information with confidence.' ),
							self::tile( 'cloud', 'Cloud and digital infrastructure', 'Reliable foundations for digital operations.' ),
							self::tile( 'property', 'Property services', 'Development, investment, management and advisory.' ),
							self::tile( 'leaf', 'Climate and agriculture technology', 'Climate information services and digital agriculture support.' ),
						)
					)
					. self::cta( 'Tell us what you need', 'We will help you choose the right combination of capabilities.' ),
				'auto'    => true,
				'review'  => 'Confirm the service list and add detail for each service.',
			),
			'portfolio'    => array(
				'title'   => 'Portfolio',
				'slug'    => 'portfolio',
				'role'    => 'content',
				'purpose' => 'Projects and engagements. Illustrative samples until real, cleared projects are published.',
				'excerpt' => 'The kind of work the group undertakes across its areas.',
				'content' => '<div class="callout"><p><strong>Illustrative samples.</strong> The entries below illustrate the kinds of engagements Noblen undertakes. They are examples, not completed client projects.</p></div>'
					. '<div class="tiles tiles--3">'
					. self::sample( 'ai', 'Operations intelligence dashboard', 'A concept for turning scattered operational data into a single view that supports faster decisions.', 'Noblen AI', false )
					. self::sample( 'media', 'Institutional documentary', 'A concept for a documentary that explains an organisation’s work to its stakeholders.', 'Noblen Media', true )
					. self::sample( 'property', 'Mixed-use development advisory', 'A concept for advising on the planning and marketing of a residential and commercial development.', 'Noblen Property', false )
					. self::sample( 'leaf', 'Climate information service', 'A concept for communicating seasonal forecasts to farming communities through digital channels.', 'Noblen AI and Media', true )
					. self::sample( 'cloud', 'Secure cloud migration', 'A concept for moving an organisation’s core systems to a reliable cloud environment.', 'Noblen Cloud', false )
					. self::sample( 'energy', 'Energy awareness campaign', 'A concept for a communication programme that raises awareness of energy efficiency.', 'Noblen Energy and Media', true )
					. '</div>'
					. '<p class="small">Real projects are published from the Noblen dashboard, with client consent, after review and approval.</p>'
					. self::cta( 'Have a project in mind?', 'Let us explore what we could build together.' ),
				'auto'    => true,
				'review'  => 'These six entries are illustrative samples. Replace them with real, cleared projects.',
			),
			'products'     => array(
				'title'   => 'Products & Platforms',
				'slug'    => 'products-and-platforms',
				'role'    => 'content',
				'purpose' => 'Products and platforms in the Noblen ecosystem.',
				'excerpt' => 'Platforms and operating systems from the Noblen ecosystem.',
				'content' => '<p class="lead">The Noblen ecosystem includes platforms designed to make operations clearer and easier to run. Product availability and stage are confirmed on each product page as it is published.</p>'
					. self::tiles(
						array(
							self::tile( 'ai', 'Noblen AI Platform', 'The group’s intelligence and execution platform, an ' . self::POSITIONING . '.' ),
							self::tile( 'leaf', 'CIDU', 'Climate Information and Digital Support Platform.' ),
							self::tile( 'globe', 'OnlineMart', 'A Hausa-first digital marketplace.' ),
							self::tile( 'box', 'ArewaGo', 'A logistics and fulfilment platform.' ),
						)
					)
					. '<h2>Operating systems</h2><p>Noblen is developing a family of operating systems for specific domains such as business, government, agriculture and climate. Each will be presented with its stage of development when approved details are available.</p>'
					. self::cta( 'Interested in a platform?', 'Contact us to discuss availability, pilots and partnerships.' ),
				'auto'    => true,
				'review'  => 'Confirm the status of each product and add approved descriptions and screenshots.',
			),
			'partnerships' => array(
				'title'   => 'Partnerships',
				'slug'    => 'partnerships',
				'role'    => 'content',
				'purpose' => 'An invitation to collaborate, without implying any existing endorsement.',
				'excerpt' => 'Progress is built through collaboration.',
				'content' => '<p class="lead">Noblen welcomes conversations with organisations whose expertise, networks and ambitions complement our work.</p>'
					. self::tiles(
						array(
							self::tile( 'ai', 'Technology and innovation', 'Solution design, digital transformation, automation and intelligent systems.' ),
							self::tile( 'people', 'Enterprise and development', 'Programmes that help businesses and communities grow.' ),
							self::tile( 'property', 'Property and infrastructure', 'Development, investment and digital infrastructure.' ),
							self::tile( 'leaf', 'Climate and agriculture', 'Information, technology and communication that support resilience.' ),
						)
					)
					. '<h2>Start a conversation</h2><p>Share the challenge, the outcomes you want and the contribution you have in mind. Our team will review it and respond.</p><p><a class="btn" href="/contact/">Contact us</a></p>'
					. '<p class="small">This page describes areas of collaboration interest. It does not represent or imply an existing partnership, endorsement or contractual relationship.</p>',
				'auto'    => true,
				'review'  => 'Add verified partnerships only, each with the partner’s written permission.',
			),
			'insights'     => array(
				'title'   => 'Insights',
				'slug'    => 'insights',
				'role'    => 'posts_page',
				'purpose' => 'News, perspectives and updates. It lists published posts once made the posts page.',
				'excerpt' => '',
				'content' => '',
				'auto'    => true,
				'review'  => 'Publish your first articles under Posts.',
			),
			'contact'      => array(
				'title'   => 'Contact',
				'slug'    => 'contact',
				'role'    => 'content',
				'purpose' => 'How to reach Noblen, with working email, phone and WhatsApp links.',
				'excerpt' => 'Tell us what you want to build, improve or make possible.',
				'content' => '<p class="lead">We would like to hear from you. Choose the way that suits you best.</p>' . $contact
					. '<h2>What to include</h2><ul><li>What you are trying to achieve</li><li>Who is involved and the timeline you have in mind</li><li>Any background that would help us prepare</li></ul>'
					. '<p class="small">Contact details shown here are sample details and will be replaced with Noblen’s official details.</p>',
				'auto'    => true,
				'review'  => 'Sample contact details: replace the email, phone, WhatsApp and office address with verified details (Noblen, Settings, and this page).',
			),
			'privacy'      => array(
				'title'   => 'Privacy Policy',
				'slug'    => 'privacy',
				'role'    => 'content',
				'purpose' => 'Privacy policy draft for legal review. Never published automatically.',
				'excerpt' => 'How we handle personal information.',
				'content' => $privacy_intro
					. '<h2>Who we are</h2><p>' . self::COMPANY . ' operates this website.</p>'
					. '<h2>Information we collect</h2><p>We collect information you choose to give us, such as your name, email address and message when you contact us, and limited technical information about how the website is used.</p>'
					. '<h2>How we use information</h2><p>We use information to respond to enquiries, operate and improve the website, and meet our legal obligations.</p>'
					. '<h2>Sharing</h2><p>We do not sell personal information. We may share it with service providers who help us run the website, under appropriate safeguards.</p>'
					. '<h2>Retention and security</h2><p>We keep information only as long as needed for the purposes above and take reasonable steps to protect it.</p>'
					. '<h2>Your choices</h2><p>You can ask us to access, correct or delete your personal information by contacting us.</p>'
					. '<h2>Changes</h2><p>We may update this policy and will post the current version here.</p>',
				'auto'    => false,
				'review'  => 'Legal review required before publication.',
			),
			'terms'        => array(
				'title'   => 'Terms and Conditions',
				'slug'    => 'terms',
				'role'    => 'content',
				'purpose' => 'Website terms draft for legal review. Never published automatically.',
				'excerpt' => 'The terms for using this website.',
				'content' => $terms_intro
					. '<h2>Using this website</h2><p>By using this website you agree to these terms. If you do not agree, please do not use it.</p>'
					. '<h2>Information on this website</h2><p>The information here is provided for general purposes. We try to keep it accurate, but it may not always be complete or current.</p>'
					. '<h2>Intellectual property</h2><p>The content, design and marks on this website belong to ' . self::COMPANY . ' or its licensors and may not be used without permission.</p>'
					. '<h2>Links</h2><p>This website may link to other sites. We are not responsible for their content.</p>'
					. '<h2>Limitation of liability</h2><p>To the extent permitted by law, we are not liable for loss arising from the use of this website.</p>'
					. '<h2>Changes and governing law</h2><p>We may change these terms at any time. Governing law and jurisdiction will be confirmed in the final reviewed version.</p>',
				'auto'    => false,
				'review'  => 'Legal review required before publication.',
			),
		);
	}

	/**
	 * Short navigation labels, so the main menu fits on one line. Pages without an entry use their title.
	 *
	 * @return array<string,string>
	 */
	public static function nav_labels(): array {
		return array(
			'about'        => 'About',
			'businesses'   => 'Our Businesses',
			'industries'   => 'Industries',
			'solutions'    => 'Solutions',
			'portfolio'    => 'Portfolio',
			'products'     => 'Products',
			'insights'     => 'Insights',
			'partnerships' => 'Partnerships',
			'contact'      => 'Contact',
			'privacy'      => 'Privacy Policy',
			'terms'        => 'Terms',
		);
	}

	/**
	 * Record archives. They keep their own addresses (/group/, /industries/, /solutions/, /projects/, /products/) and fill as records are
	 * approved and published. They are informational here; the pages above are the navigation destinations.
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function archives(): array {
		return array(
			'group'      => array(
				'title'   => 'Business unit records',
				'type'    => Types::COMPANY,
				'purpose' => 'Detailed business-unit records.',
			),
			'industries' => array(
				'title'   => 'Industry records',
				'type'    => Types::INDUSTRY,
				'purpose' => 'Detailed industry records.',
			),
			'solutions'  => array(
				'title'   => 'Service records',
				'type'    => Types::SERVICE,
				'purpose' => 'Detailed service records.',
			),
			'portfolio'  => array(
				'title'   => 'Project records',
				'type'    => Types::PROJECT,
				'purpose' => 'Detailed project records.',
			),
			'products'   => array(
				'title'   => 'Product records',
				'type'    => Types::PRODUCT,
				'purpose' => 'Detailed product records.',
			),
		);
	}

	/**
	 * Menus: name, theme location and the destinations in order ("page:<key>", "archive:<key>" or "home").
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function menus(): array {
		return array(
			'primary' => array(
				'name'     => 'Primary Navigation',
				'location' => 'primary',
				'items'    => array( 'page:about', 'page:businesses', 'page:industries', 'page:solutions', 'page:portfolio', 'page:products', 'page:insights', 'page:contact' ),
			),
			'footer'  => array(
				'name'     => 'Footer Navigation',
				'location' => 'footer',
				'items'    => array( 'page:about', 'page:businesses', 'page:portfolio', 'page:partnerships', 'page:insights', 'page:contact' ),
			),
			'legal'   => array(
				'name'     => 'Legal Navigation',
				'location' => 'legal',
				'items'    => array( 'page:privacy', 'page:terms' ),
			),
		);
	}

	/**
	 * Initial draft records, names only. They stay drafts and go through the normal clearance and approval workflow.
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
	 * Homepage text, applied only on request and only to empty settings.
	 *
	 * @return array<string,string>
	 */
	public static function hero_defaults(): array {
		return array(
			'hero_headline' => self::PROMISE,
			'hero_intro'    => self::COMPANY . ' brings connected businesses together to ' . self::MISSION_TO . '.',
		);
	}

	/**
	 * Sample business details, applied only on request, only to empty settings, and listed in the content review checklist.
	 *
	 * @return array<string,string>
	 */
	public static function sample_settings(): array {
		return array(
			'company_name'    => self::COMPANY,
			'tagline'         => 'Technology, innovation and impact.',
			'contact_email'   => 'info@noblen.org',
			'contact_phone'   => '+234 000 000 0000',
			'contact_address' => 'Business address to be confirmed',
			'whatsapp_number' => '+234 000 000 0000',
		);
	}
}

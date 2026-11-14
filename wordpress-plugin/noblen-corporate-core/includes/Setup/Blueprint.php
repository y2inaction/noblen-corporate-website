<?php
namespace Noblen\\Core\\Setup;

use Noblen\\Core\\Content\\Types;
use Noblen\\Core\\Seed\\DraftSeeder;

defined( 'ABSPATH' ) || exit;

/**
 * Complete, editable starter content for the Noblen corporate website.
 *
 * Facts are limited to owner-established positioning and the supplied content inventory.
 * Sample editorial copy is deliberately generic; no clients, outcomes, metrics or partnerships are invented.
 */
final class Blueprint {

	public const PLACEHOLDER = '/\\[Owner to complete[^\\]]*\\]/i';
	public const COMPANY = 'Noblen Technologies Limited';
	public const POSITIONING = 'Autonomous Operating Systems Provider';
	public const PROMISE = 'AI That Gets Things Done';

	private static function todo( string $what ): string {
		return '<p class="noblen-setup-todo">[Owner to complete: ' . $what . ']</p>';
	}

	/** Pages are prepared as drafts for owner review. */
	public static function pages(): array {
		return array(
			'home' => array(
				'title' => 'Home', 'slug' => 'home', 'role' => 'front_page',
				'purpose' => 'The corporate homepage, assembled from the group introduction, business units and approved content.',
				'content' => '',
			),
			'about' => array(
				'title' => 'About Noblen', 'slug' => 'about', 'role' => 'content',
				'purpose' => 'Corporate overview, purpose, operating model and business areas.',
				'content' =>
					'<p class="lead">Noblen brings technology, innovation and practical execution together to help organisations improve how they operate and scale their work.</p>'
					. '<h2>Who we are</h2><p>' . self::COMPANY . ' is the parent company of the Noblen group. The group is positioned as an ' . self::POSITIONING . ', with a commitment captured in the promise: <strong>' . self::PROMISE . '</strong>.</p>'
					. '<p>Our approach connects people, technology and delivery. We focus on understanding the work that needs to be done, designing solutions around real operating needs, and making those solutions useful in day-to-day practice.</p>'
					. '<h2>Our purpose</h2><p>We help individuals and organisations improve their operations and scale their businesses through our collective solutions. We believe technology should make work clearer, services more accessible and organisations more capable.</p>'
					. '<h2>How the group is organised</h2><p>Noblen brings together complementary business areas: AI, Property, Media, Energy and Cloud. These areas provide a connected platform for developing and delivering solutions across sectors.</p>'
					. '<h2>Our intelligence and execution engine</h2><p>Noblen AI is the group\'s central intelligence and execution business. Its strategic direction brings together AI workforce capabilities, AI operating systems, transformation services and platform development. The wider vision is to make intelligent systems useful across business, public services, agriculture, climate and other essential areas.</p>'
					. '<h2>Our working principles</h2><ul><li><strong>Purpose before technology:</strong> start with the problem and the people affected.</li><li><strong>Practical innovation:</strong> build solutions that can be understood, adopted and improved.</li><li><strong>Connected thinking:</strong> bring relevant expertise together across the group.</li><li><strong>Responsible execution:</strong> communicate clearly about capabilities, evidence and delivery.</li></ul>'
					. '<h2>Our story and leadership</h2><p>This website introduces the group and its areas of work. Detailed corporate history and leadership biographies will be added when the approved information is ready.</p>',
			),
			'insights' => array(
				'title' => 'Insights', 'slug' => 'insights', 'role' => 'posts_page',
				'purpose' => 'News, perspectives and updates from across the Noblen group.',
				'content' => '',
			),
			'partnerships' => array(
				'title' => 'Partnerships', 'slug' => 'partnerships', 'role' => 'content',
				'purpose' => 'A complete partnership overview and invitation without implying existing endorsements.',
				'content' =>
					'<p class="lead">Progress on complex challenges is built through collaboration. Noblen welcomes conversations with organisations whose expertise, networks and ambitions complement our work.</p>'
					. '<h2>Working together</h2><p>We are interested in purposeful collaboration across technology, enterprise development, property, media, energy, cloud infrastructure, agriculture and climate-related solutions.</p>'
					. '<h2>Areas for collaboration</h2><ul><li><strong>Technology and innovation:</strong> solution design, digital transformation, automation and intelligent systems.</li><li><strong>Enterprise and investment:</strong> opportunities that strengthen business operations and support sustainable growth.</li><li><strong>Knowledge and capacity:</strong> practical learning, research, communication and knowledge-sharing initiatives.</li><li><strong>Sector programmes:</strong> initiatives that connect technology with real needs in communities, institutions and industries.</li></ul>'
					. '<h2>Start a conversation</h2><p>If your organisation is exploring a collaboration, share the challenge, intended outcomes and the kind of contribution you have in mind. Our team can review the opportunity and identify an appropriate next step.</p><p><a href="mailto:sales@noblenai.com">Contact Noblen AI about a technology collaboration</a></p>'
					. '<p class="small">This page describes areas of collaboration interest. It does not represent or imply an existing partnership, endorsement or contractual relationship.</p>',
			),
			'contact' => array(
				'title' => 'Contact', 'slug' => 'contact', 'role' => 'content',
				'purpose' => 'A usable contact page with the verified Noblen AI enquiry address currently available.',
				'content' =>
					'<p class="lead">Tell us what you are working on, what you want to improve, or where you need a capable technology and delivery partner.</p>'
					. '<h2>Business and technology enquiries</h2><p>For enquiries relating to Noblen AI, intelligent systems, automation and digital transformation, contact:</p><p><strong>Email:</strong> <a href="mailto:sales@noblenai.com">sales@noblenai.com</a><br><strong>Website:</strong> <a href="https://noblenai.com">noblenai.com</a></p>'
					. '<h2>What to include</h2><p>To help us direct your enquiry, include your organisation, the challenge or opportunity, the outcome you are seeking and the best way to reach you. Please do not send passwords, financial credentials or confidential personal information by email.</p>'
					. '<h2>Other group enquiries</h2><p>For enquiries relating to Property, Media, Energy or Cloud, indicate the relevant business area in your message so it can be directed appropriately.</p>'
					. '<p>We look forward to hearing from you.</p>',
			),
			'privacy' => array(
				'title' => 'Privacy Policy', 'slug' => 'privacy-policy', 'role' => 'content',
				'purpose' => 'Legal page; requires the actual data practices and legal review before publication.',
				'content' => '<h2>Privacy Policy</h2>' . self::todo( 'confirm the website operator, data collected, purposes, lawful bases, retention, processors, user rights and contact for privacy requests; obtain qualified legal review before publication' ),
			),
			'terms' => array(
				'title' => 'Terms and Conditions', 'slug' => 'terms-and-conditions', 'role' => 'content',
				'purpose' => 'Legal page; requires approved terms and legal review before publication.',
				'content' => '<h2>Terms and Conditions</h2>' . self::todo( 'the approved website terms, governing law, limitation of liability and intellectual-property provisions; obtain qualified legal review before publication' ),
			),
		);
	}

	/** Record archives already own these routes; do not create colliding pages. */
	public static function archives(): array {
		return array(
			'group' => array( 'title' => 'Our Group', 'type' => Types::COMPANY, 'purpose' => 'The group\'s business units.' ),
			'industries' => array( 'title' => 'Industries', 'type' => Types::INDUSTRY, 'purpose' => 'The industries Noblen serves.' ),
			'solutions' => array( 'title' => 'Solutions & Services', 'type' => Types::SERVICE, 'purpose' => 'Solutions and services.' ),
			'portfolio' => array( 'title' => 'Portfolio', 'type' => Types::PROJECT, 'purpose' => 'Selected projects and engagements.' ),
			'products' => array( 'title' => 'Products & Platforms', 'type' => Types::PRODUCT, 'purpose' => 'Products and platforms, distinct from client projects.' ),
		);
	}

	public static function menus(): array {
		return array(
			'primary' => array( 'name' => 'Primary Navigation', 'location' => 'primary', 'items' => array( 'home', 'page:about', 'archive:group', 'archive:industries', 'archive:solutions', 'archive:portfolio', 'archive:products', 'page:insights', 'page:partnerships', 'page:contact' ) ),
			'footer' => array( 'name' => 'Footer Navigation', 'location' => 'footer', 'items' => array( 'page:about', 'archive:group', 'archive:portfolio', 'page:insights', 'page:partnerships', 'page:contact' ) ),
			'legal' => array( 'name' => 'Legal Navigation', 'location' => 'legal', 'items' => array( 'page:privacy', 'page:terms' ) ),
		);
	}

	/** Draft titles only: each item still requires accurate detail, evidence and approval. */
	public static function records(): array {
		return array(
			Types::COMPANY => DraftSeeder::COMPANIES,
			Types::INDUSTRY => array( 'AI & Intelligent Systems', 'Property & Real Estate', 'Media & Communications', 'Energy', 'Cloud & Digital Infrastructure' ),
			Types::SERVICE => array( 'Documentary filmmaking', 'Radio and TV production', 'Online content', 'Business branding', 'Web development', 'Data management', 'Events coverage', 'Live streaming' ),
			Types::PRODUCT => DraftSeeder::PRODUCTS,
			Types::PROJECT => array( 'The Story Never Told', 'Call Masking and Refiling', 'Adding Value to Livelihood', 'Automated Weather Stations (AWS) and Simple Weather Recording (SWR)', 'Matrix 360% Media Engagement Campaign', 'The Ground Breaking Of Mega Project' ),
		);
	}

	public static function hero_defaults(): array {
		return array(
			'hero_headline' => self::PROMISE,
			'hero_intro' => 'We connect intelligent technology, practical solutions and coordinated execution to help organisations work better and grow with confidence.',
		);
	}
}

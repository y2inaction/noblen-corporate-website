<?php
/**
 * Home page sections: registry, ordering and visibility (theme settings, per M2 Rev 5.1 section 8).
 *
 * Order and visibility change layout only. A visible section with no data still renders nothing.
 *
 * @package noblen-corporate
 */

defined( 'ABSPATH' ) || exit;

/**
 * The orderable home sections in their default order.
 *
 * @return array<string,string> Section ID => label.
 */
function noblen_home_registry(): array {
	return array(
		'group'        => 'Group introduction',
		'companies'    => 'Business units',
		'industries'   => 'Industries',
		'solutions'    => 'Solutions and services',
		'projects'     => 'Featured projects',
		'products'     => 'Products and platforms',
		'insights'     => 'Insights',
		'partnerships' => 'Partnerships',
		'cta'          => 'Closing call to action',
	);
}

/**
 * Section IDs to render, in order, with hidden sections removed.
 *
 * @return string[]
 */
function noblen_home_active_sections(): array {
	$ids  = array_keys( noblen_home_registry() );
	$rank = array();
	foreach ( $ids as $pos => $id ) {
		$set         = (int) get_theme_mod( 'noblen_home_order_' . $id, ( $pos + 1 ) * 10 );
		$rank[ $id ] = array( min( 999, max( 1, $set ) ), $pos );
	}
	uasort(
		$rank,
		static fn( $a, $b ) => $a <=> $b
	);
	$out = array();
	foreach ( array_keys( $rank ) as $id ) {
		if ( (bool) get_theme_mod( 'noblen_home_show_' . $id, true ) ) {
			$out[] = $id;
		}
	}
	return $out;
}

/**
 * Verified, approved partner entries. The core plugin has no partner record type in this release, so the
 * default is empty and the section renders nothing. A later release supplies data through this filter.
 *
 * @return array<int,array<string,mixed>> Items with at least a title and a URL.
 */
function noblen_partnership_items(): array {
	$items = apply_filters( 'noblen_partnership_items', array() );
	$out   = array();
	foreach ( is_array( $items ) ? $items : array() as $item ) {
		if ( is_array( $item ) && ! empty( $item['title'] ) && ! empty( $item['url'] ) ) {
			$item['type'] = 'partner';
			$out[]        = $item;
		}
	}
	return $out;
}

/**
 * A "Discover Noblen" link, shown only when the About page is published (never a link to a page that does not exist).
 */
function noblen_discover_link(): string {
	$page = get_page_by_path( 'about', OBJECT, 'page' );
	if ( ! $page || 'publish' !== $page->post_status ) {
		return '';
	}
	return '<p><a class="link-more" href="' . esc_url( get_permalink( $page ) ) . '">Discover Noblen</a></p>';
}

/**
 * Render one home section by ID.
 *
 * @param string $id Section ID.
 */
function noblen_render_home_section( string $id ): void {
	$by_type = array(
		'companies'  => array( 'noblen_company', 'Business units', array( 'orderby' => 'title' ), 6 ),
		'industries' => array( 'noblen_industry', 'Industries', array( 'orderby' => 'title' ), 10 ),
		'solutions'  => array( 'noblen_service', 'Solutions and services', array( 'orderby' => 'title' ), 6 ),
		'projects'   => array( 'noblen_project', 'Featured projects', array( 'featured_home' => true ), 6 ),
		'products'   => array( 'noblen_product', 'Products and platforms', array( 'featured_home' => true ), 6 ),
	);
	if ( isset( $by_type[ $id ] ) ) {
		list( $type, $title, $args, $limit ) = $by_type[ $id ];
		noblen_section( $id, $title, noblen_items( $type, $args + array( 'limit' => $limit ) ), (string) get_post_type_archive_link( $type ) );
		return;
	}
	if ( 'group' === $id ) {
		$group = noblen_setting( 'group_intro' );
		if ( '' === $group ) {
			$group = 'Noblen Technologies Limited brings together complementary capabilities across AI, Property, Media, Energy and Cloud. We connect ideas, people and technology to help organisations improve operations and move from plans to practical execution.';
		}
		echo '<section class="section section--tint" id="group"><div class="wrap prose"><span class="eyebrow">One group. Connected capabilities.</span><h2>Technology, innovation and impact</h2><p>' . esc_html( $group ) . '</p>' . noblen_discover_link() . '</div></section>';
	} elseif ( 'insights' === $id ) {
		$posts = get_posts(
			array(
				'numberposts' => 3,
				'post_status' => 'publish',
			)
		);
		if ( $posts ) {
			echo '<section class="section" id="insights" aria-labelledby="insights-h"><div class="wrap"><div class="section__head"><h2 id="insights-h">Insights</h2></div><div class="grid">';
			foreach ( $posts as $p ) {
				echo '<article class="card"><div class="card__body"><p class="card__meta">' . esc_html( get_the_date( '', $p ) ) . '</p><h3 class="card__title"><a href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html( get_the_title( $p ) ) . '</a></h3></div></article>';
			}
			echo '</div></div></section>';
		}
	} elseif ( 'partnerships' === $id ) {
		noblen_section( 'partnerships', 'Partnerships', noblen_partnership_items() );
	} elseif ( 'cta' === $id ) {
		$cta   = noblen_setting( 'cta_headline' );
		$email = noblen_setting( 'contact_email' );
		if ( '' === $cta ) {
			$cta = 'Let’s turn your next idea into meaningful progress.';
		}
		// The button goes to the contact email when one is set, otherwise to the published Contact page; with neither, nothing is shown.
		$href = '' !== $email ? 'mailto:' . $email : '';
		if ( '' === $href ) {
			$contact = get_page_by_path( 'contact', OBJECT, 'page' );
			if ( $contact && 'publish' === $contact->post_status ) {
				$href = (string) get_permalink( $contact );
			}
		}
		if ( '' === $href ) {
			return;
		}
		echo '<section class="section section--cta" id="contact"><div class="wrap"><span class="eyebrow">Start a conversation</span><h2>' . esc_html( $cta ) . '</h2><p>Tell us what you want to build, improve or make possible.</p><p><a class="btn" href="' . esc_url( $href, array( 'http', 'https', 'mailto' ) ) . '">Talk to Noblen</a></p></div></section>';
	}
}

add_action(
	'customize_register',
	static function ( WP_Customize_Manager $wp_customize ) {
		$wp_customize->add_section(
			'noblen_home',
			array(
				'title'       => 'Home page sections',
				'description' => 'Choose which sections appear and in what order (lower numbers first). A section with no published content stays hidden whatever you choose here.',
				'priority'    => 30,
			)
		);
		$pos = 0;
		foreach ( noblen_home_registry() as $id => $label ) {
			++$pos;
			$wp_customize->add_setting(
				'noblen_home_show_' . $id,
				array(
					'default'           => true,
					'sanitize_callback' => 'rest_sanitize_boolean',
				)
			);
			$wp_customize->add_control(
				'noblen_home_show_' . $id,
				array(
					'section' => 'noblen_home',
					'type'    => 'checkbox',
					/* translators: %s: section name. */
					'label'   => sprintf( 'Show: %s', $label ),
				)
			);
			$wp_customize->add_setting(
				'noblen_home_order_' . $id,
				array(
					'default'           => $pos * 10,
					'sanitize_callback' => static fn( $v ) => min( 999, max( 1, (int) $v ) ),
				)
			);
			$wp_customize->add_control(
				'noblen_home_order_' . $id,
				array(
					'section'     => 'noblen_home',
					'type'        => 'number',
					'label'       => sprintf( 'Order: %s', $label ),
					'input_attrs' => array(
						'min'  => 1,
						'max'  => 999,
						'step' => 1,
					),
				)
			);
		}
	}
);

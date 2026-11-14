<?php
/**
 * Home page: hero, then the Home page's own content (designed blocks), then the sections that fill from approved records.
 * Every record section hides when it has nothing to show.
 */
defined( 'ABSPATH' ) || exit;
get_header();
$noblen_headline = noblen_setting( 'hero_headline' );
$noblen_intro    = noblen_setting( 'hero_intro' );
$noblen_about    = noblen_page_url( 'our-businesses', noblen_archive_url( 'noblen_company' ) );
$noblen_contact  = noblen_page_url( 'contact', '#contact' );
$noblen_areas    = array(
	'AI'       => 'ai',
	'Property' => 'property',
	'Media'    => 'media',
	'Energy'   => 'energy',
	'Cloud'    => 'cloud',
);
?>
<section class="hero hero--home">
	<div class="wrap hero__grid">
		<div class="hero__text">
			<p class="hero__eyebrow">Technology. Innovation. Impact.</p>
			<h1 class="hero__title"><?php echo esc_html( '' !== $noblen_headline ? $noblen_headline : get_bloginfo( 'name' ) ); ?></h1>
			<?php
			if ( '' !== $noblen_intro ) :
				?>
				<p class="hero__intro"><?php echo esc_html( $noblen_intro ); ?></p><?php endif; ?>
			<p class="hero__actions">
				<a class="btn" href="<?php echo esc_url( $noblen_about ); ?>">Explore our businesses</a>
				<a class="btn btn--ghost" href="<?php echo esc_url( $noblen_contact ); ?>">Talk to Noblen</a>
			</p>
			<ul class="hero__chips" aria-label="Business areas">
				<?php foreach ( $noblen_areas as $noblen_label => $noblen_icon ) : ?>
					<li><span class="icon icon--<?php echo esc_attr( $noblen_icon ); ?>" aria-hidden="true"></span><?php echo esc_html( $noblen_label ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<div class="hero__art"><?php get_template_part( 'template-parts/hero-art' ); ?></div>
	</div>
</section>
<?php
$noblen_front       = (int) get_option( 'page_on_front' );
$noblen_home        = $noblen_front ? get_post( $noblen_front ) : get_page_by_path( 'home', OBJECT, 'page' );
$noblen_has_content = $noblen_home && 'page' === $noblen_home->post_type && 'publish' === $noblen_home->post_status && '' !== trim( wp_strip_all_tags( (string) $noblen_home->post_content ) );
if ( $noblen_has_content ) {
	echo '<div class="home-content">' . apply_filters( 'the_content', $noblen_home->post_content ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- post content filtered by the_content.
}
foreach ( noblen_home_active_sections() as $noblen_section_id ) {
	// The Home page content already carries the group introduction and the closing call to action.
	if ( $noblen_has_content && in_array( $noblen_section_id, array( 'group', 'cta' ), true ) ) {
		continue;
	}
	noblen_render_home_section( $noblen_section_id );
}
get_footer();

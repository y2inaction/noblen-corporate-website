<?php
/**
 * Home page. Every section renders only from the core plugin and hides when empty.
 */
defined( 'ABSPATH' ) || exit;
get_header();
$headline = noblen_setting( 'hero_headline' );
$intro    = noblen_setting( 'hero_intro' );
?>
<section class="hero">
	<div class="wrap hero__inner">
		<h1 class="hero__title"><?php echo esc_html( '' !== $headline ? $headline : get_bloginfo( 'name' ) ); ?></h1>
		<?php
		if ( '' !== $intro ) :
			?>
			<p class="hero__intro"><?php echo esc_html( $intro ); ?></p><?php endif; ?>
		<?php if ( noblen_has_core() ) : ?>
		<p class="hero__actions">
			<a class="btn" href="<?php echo esc_url( noblen_archive_url( 'noblen_company' ) ); ?>">Explore the group</a>
			<a class="btn btn--ghost" href="<?php echo esc_url( noblen_archive_url( 'noblen_project' ) ); ?>">View the portfolio</a>
		</p>
		<?php endif; ?>
	</div>
</section>
<?php
foreach ( noblen_home_active_sections() as $noblen_section_id ) {
	noblen_render_home_section( $noblen_section_id );
}
get_footer();

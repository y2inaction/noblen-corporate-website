<?php
/**
 * Home page. Every section renders only from the core plugin and hides when empty.
 */
defined( 'ABSPATH' ) || exit;
get_header();
$headline = noblen_setting( 'hero_headline' );
$intro    = noblen_setting( 'hero_intro' );
$group    = noblen_setting( 'group_intro' );
$cta      = noblen_setting( 'cta_headline' );
?>
<section class="hero">
	<div class="wrap hero__inner">
		<h1 class="hero__title"><?php echo esc_html( '' !== $headline ? $headline : get_bloginfo( 'name' ) ); ?></h1>
		<?php if ( '' !== $intro ) : ?><p class="hero__intro"><?php echo esc_html( $intro ); ?></p><?php endif; ?>
		<?php if ( noblen_has_core() ) : ?>
		<p class="hero__actions">
			<a class="btn" href="<?php echo esc_url( get_post_type_archive_link( 'noblen_company' ) ?: home_url( '/' ) ); ?>">Explore the group</a>
			<a class="btn btn--ghost" href="<?php echo esc_url( get_post_type_archive_link( 'noblen_project' ) ?: home_url( '/' ) ); ?>">View the portfolio</a>
		</p>
		<?php endif; ?>
	</div>
</section>
<?php if ( '' !== $group ) : ?>
<section class="section section--tint"><div class="wrap prose"><h2>The group</h2><p><?php echo esc_html( $group ); ?></p></div></section>
<?php endif; ?>
<?php
noblen_section( 'companies', 'Business units', noblen_items( 'noblen_company', array( 'limit' => 6, 'orderby' => 'title' ) ), (string) get_post_type_archive_link( 'noblen_company' ) );
noblen_section( 'industries', 'Industries', noblen_items( 'noblen_industry', array( 'limit' => 10, 'orderby' => 'title' ) ), (string) get_post_type_archive_link( 'noblen_industry' ) );
noblen_section( 'solutions', 'Solutions and services', noblen_items( 'noblen_service', array( 'limit' => 6, 'orderby' => 'title' ) ), (string) get_post_type_archive_link( 'noblen_service' ) );
noblen_section( 'projects', 'Featured projects', noblen_items( 'noblen_project', array( 'limit' => 6, 'featured_home' => true ) ), (string) get_post_type_archive_link( 'noblen_project' ) );
noblen_section( 'products', 'Products and platforms', noblen_items( 'noblen_product', array( 'limit' => 6, 'featured_home' => true ) ), (string) get_post_type_archive_link( 'noblen_product' ) );

$posts = get_posts( array( 'numberposts' => 3, 'post_status' => 'publish' ) );
if ( $posts ) :
	?>
<section class="section" id="insights"><div class="wrap"><div class="section__head"><h2>Insights</h2></div><div class="grid">
	<?php foreach ( $posts as $p ) : ?>
	<article class="card"><div class="card__body"><p class="card__meta"><?php echo esc_html( get_the_date( '', $p ) ); ?></p><h3 class="card__title"><a href="<?php echo esc_url( get_permalink( $p ) ); ?>"><?php echo esc_html( get_the_title( $p ) ); ?></a></h3></div></article>
	<?php endforeach; ?>
</div></div></section>
<?php endif; ?>
<?php if ( '' !== $cta && noblen_setting( 'contact_email' ) ) : ?>
<section class="section section--cta"><div class="wrap"><h2><?php echo esc_html( $cta ); ?></h2><p><a class="btn" href="mailto:<?php echo esc_attr( noblen_setting( 'contact_email' ) ); ?>">Contact us</a></p></div></section>
<?php endif; ?>
<?php get_footer(); ?>

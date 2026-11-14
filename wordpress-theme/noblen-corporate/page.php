<?php
defined( 'ABSPATH' ) || exit;
get_header();
while ( have_posts() ) :
	the_post();
	$noblen_lead = has_excerpt() ? get_the_excerpt() : '';
	?>
<header class="page-head page-head--banner page-head--<?php echo esc_attr( sanitize_html_class( (string) get_post_field( 'post_name' ) ) ); ?>"><div class="wrap">
	<?php noblen_breadcrumbs( array( array( get_the_title(), '' ) ) ); ?>
	<h1><?php the_title(); ?></h1>
	<?php
	if ( '' !== $noblen_lead ) :
		?>
		<p class="lead"><?php echo esc_html( $noblen_lead ); ?></p><?php endif; ?>
</div></header>
<article class="section"><div class="wrap prose">
	<?php the_content(); ?>
</div></article>
<?php endwhile;
get_footer(); ?>

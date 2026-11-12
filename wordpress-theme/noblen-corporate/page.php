<?php
defined( 'ABSPATH' ) || exit;
get_header();
while ( have_posts() ) :
	the_post();
	?>
<article class="section"><div class="wrap prose">
	<h1><?php the_title(); ?></h1>
	<?php the_content(); ?>
</div></article>
<?php endwhile; get_footer(); ?>

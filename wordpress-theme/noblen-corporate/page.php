<?php
defined( 'ABSPATH' ) || exit;
get_header();
while ( have_posts() ) :
	the_post();
	?>
<header class="page-head"><div class="wrap">
	<?php noblen_breadcrumbs( array( array( get_the_title(), '' ) ) ); ?>
	<h1><?php the_title(); ?></h1>
</div></header>
<article class="section"><div class="wrap prose">
	<?php the_content(); ?>
</div></article>
<?php endwhile;
get_footer(); ?>

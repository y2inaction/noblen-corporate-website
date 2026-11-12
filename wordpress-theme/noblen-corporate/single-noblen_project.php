<?php
defined( 'ABSPATH' ) || exit;
get_header();
while ( have_posts() ) {
	the_post();
	get_template_part( 'template-parts/record' );
}
get_footer();

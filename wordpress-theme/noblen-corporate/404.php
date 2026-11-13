<?php
defined( 'ABSPATH' ) || exit;
get_header();
?>
<header class="page-head"><div class="wrap">
	<?php noblen_breadcrumbs( array( array( 'Page not found', '' ) ) ); ?>
	<h1>Page not found</h1>
	<p class="lead">The page you asked for is not available. It may have moved, or it may not be published.</p>
</div></header>
<section class="section"><div class="wrap prose">
	<?php get_search_form(); ?>
	<p><a class="btn" href="<?php echo esc_url( home_url( '/' ) ); ?>">Back to the home page</a></p>
</div></section>
<?php get_footer(); ?>

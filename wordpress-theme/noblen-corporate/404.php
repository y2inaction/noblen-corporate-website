<?php
defined( 'ABSPATH' ) || exit;
get_header();
?>
<section class="section"><div class="wrap prose">
	<h1>Page not found</h1>
	<p>The page you asked for is not available.</p>
	<p><a class="btn" href="<?php echo esc_url( home_url( '/' ) ); ?>">Back to the home page</a></p>
</div></section>
<?php get_footer(); ?>

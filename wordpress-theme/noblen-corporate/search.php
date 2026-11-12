<?php
defined( 'ABSPATH' ) || exit;
get_header();
?>
<section class="section"><div class="wrap">
	<h1>Search results for &ldquo;<?php echo esc_html( get_search_query() ); ?>&rdquo;</h1>
	<?php get_search_form(); ?>
	<?php if ( have_posts() ) : ?>
		<ul class="results">
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<li><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></li>
		<?php endwhile; ?>
		</ul>
		<?php the_posts_pagination(); ?>
	<?php else : ?>
		<p>No results were found.</p>
	<?php endif; ?>
</div></section>
<?php get_footer(); ?>

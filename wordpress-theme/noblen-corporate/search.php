<?php
defined( 'ABSPATH' ) || exit;
get_header();
?>
<header class="page-head"><div class="wrap">
	<?php noblen_breadcrumbs( array( array( 'Search', '' ) ) ); ?>
	<h1>Search results for &ldquo;<?php echo esc_html( get_search_query() ); ?>&rdquo;</h1>
	<?php get_search_form(); ?>
</div></header>
<section class="section"><div class="wrap">
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
		<p class="empty">No results were found. Try a different word, or browse the sections from the menu.</p>
	<?php endif; ?>
</div></section>
<?php get_footer(); ?>

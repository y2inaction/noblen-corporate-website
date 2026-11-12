<?php
defined( 'ABSPATH' ) || exit;
get_header();
?>
<section class="section"><div class="wrap">
	<?php
	if ( is_home() && ! is_front_page() ) :
		?>
		<h1><?php single_post_title(); ?></h1><?php endif; ?>
	<?php if ( have_posts() ) : ?>
		<div class="grid">
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<article <?php post_class( 'card' ); ?>>
				<h2 class="card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
				<p class="card__meta"><?php echo esc_html( get_the_date() ); ?></p>
				<div class="card__text"><?php the_excerpt(); ?></div>
			</article>
		<?php endwhile; ?>
		</div>
		<?php the_posts_pagination(); ?>
	<?php else : ?>
		<p>There is nothing to show here yet.</p>
	<?php endif; ?>
</div></section>
<?php get_footer(); ?>

<?php
defined( 'ABSPATH' ) || exit;
get_header();
$items = noblen_items(
	get_post_type(),
	array(
		'limit'   => 60,
		'orderby' => 'title',
	)
);
?>
<section class="section"><div class="wrap">
	<h1><?php post_type_archive_title(); ?></h1>
	<?php
	if ( $items ) :
		?>
		<div class="grid">
		<?php
		foreach ( $items as $i ) {
				noblen_card( $i ); }
		?>
</div>
		<?php
	else :
		?>
		<p>There is nothing to show here yet.</p><?php endif; ?>
</div></section>
<?php get_footer(); ?>

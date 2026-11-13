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
<header class="page-head"><div class="wrap">
	<?php noblen_breadcrumbs( array( array( post_type_archive_title( '', false ), '' ) ) ); ?>
	<h1><?php post_type_archive_title(); ?></h1>
</div></header>
<section class="section"><div class="wrap">
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
		<p class="empty">Nothing is published in this section yet.</p><?php endif; ?>
</div></section>
<?php get_footer(); ?>

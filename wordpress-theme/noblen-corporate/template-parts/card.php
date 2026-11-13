<?php
defined( 'ABSPATH' ) || exit;
$item  = $args['item'] ?? array();
$level = in_array( (int) ( $args['level'] ?? 3 ), array( 2, 3 ), true ) ? (int) $args['level'] : 3;
if ( ! $item ) {
	return;
}
?>
<article class="card card--<?php echo esc_attr( str_replace( 'noblen_', '', (string) $item['type'] ) ); ?>">
	<?php if ( ! empty( $item['featured_image'] ) ) : ?>
		<img class="card__img" src="<?php echo esc_url( $item['featured_image']['url'] ); ?>" alt="<?php echo esc_attr( $item['featured_image']['alt'] ); ?>" loading="lazy" width="<?php echo esc_attr( (string) $item['featured_image']['width'] ); ?>" height="<?php echo esc_attr( (string) $item['featured_image']['height'] ); ?>">
	<?php endif; ?>
	<div class="card__body">
		<?php
		if ( ! empty( $item['status']['label'] ) ) :
			?>
			<span class="badge"><?php echo esc_html( $item['status']['label'] ); ?></span><?php endif; ?>
		<h<?php echo (int) $level; ?> class="card__title"><a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['title'] ); ?></a></h<?php echo (int) $level; ?>>
		<?php
		if ( ! empty( $item['tagline'] ) ) :
			?>
			<p class="card__text"><?php echo esc_html( $item['tagline'] ); ?></p><?php endif; ?>
		<?php
		if ( ! empty( $item['business_unit']['title'] ) ) :
			?>
			<p class="card__meta"><?php echo esc_html( $item['business_unit']['title'] ); ?></p><?php endif; ?>
	</div>
</article>

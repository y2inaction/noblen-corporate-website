<?php
/**
 * Detail view for a record from the core plugin. Renders only what the plugin
 * returns as public. If nothing is returned, the page is a 404.
 */
defined( 'ABSPATH' ) || exit;
$item = noblen_item( (int) get_the_ID() );
if ( ! $item ) {
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
	get_template_part( '404' );
	return;
}
?>
<article class="section"><div class="wrap prose record">
	<?php if ( ! empty( $item['status']['label'] ) ) : ?><span class="badge"><?php echo esc_html( $item['status']['label'] ); ?></span><?php endif; ?>
	<h1><?php echo esc_html( $item['title'] ); ?></h1>
	<?php if ( ! empty( $item['tagline'] ) ) : ?><p class="lead"><?php echo esc_html( $item['tagline'] ); ?></p><?php endif; ?>
	<?php if ( ! empty( $item['business_unit']['title'] ) ) : ?><p class="card__meta">Business unit: <a href="<?php echo esc_url( $item['business_unit']['url'] ); ?>"><?php echo esc_html( $item['business_unit']['title'] ); ?></a></p><?php endif; ?>
	<?php if ( ! empty( $item['client'] ) ) : ?><p class="card__meta">Client: <?php echo esc_html( $item['client'] ); ?></p><?php endif; ?>
	<?php if ( ! empty( $item['featured_image'] ) ) : ?><figure><img src="<?php echo esc_url( $item['featured_image']['url'] ); ?>" alt="<?php echo esc_attr( $item['featured_image']['alt'] ); ?>" width="<?php echo esc_attr( (string) $item['featured_image']['width'] ); ?>" height="<?php echo esc_attr( (string) $item['featured_image']['height'] ); ?>"><?php if ( $item['featured_image']['caption'] ) : ?><figcaption><?php echo esc_html( $item['featured_image']['caption'] ); ?></figcaption><?php endif; ?></figure><?php endif; ?>
	<?php echo wp_kses_post( $item['content_html'] ); ?>
	<?php foreach ( (array) $item['sections'] as $sec ) : ?>
		<section><h2><?php echo esc_html( $sec['label'] ); ?></h2><?php echo wp_kses_post( $sec['html'] ); ?></section>
	<?php endforeach; ?>
	<?php if ( ! empty( $item['gallery'] ) ) : ?>
		<section><h2>Gallery</h2><div class="grid">
		<?php foreach ( $item['gallery'] as $img ) : ?><figure><img src="<?php echo esc_url( $img['url'] ); ?>" alt="<?php echo esc_attr( $img['alt'] ); ?>" loading="lazy" width="<?php echo esc_attr( (string) $img['width'] ); ?>" height="<?php echo esc_attr( (string) $img['height'] ); ?>"></figure><?php endforeach; ?>
		</div></section>
	<?php endif; ?>
	<?php if ( ! empty( $item['video_url'] ) ) : ?><p><a class="btn" href="<?php echo esc_url( $item['video_url'] ); ?>" rel="noopener">Watch the video</a></p><?php endif; ?>
	<?php foreach ( (array) $item['related'] as $field => $list ) : ?>
		<section><h2><?php echo esc_html( ucfirst( str_replace( '_', ' ', (string) $field ) ) ); ?></h2><ul>
		<?php foreach ( $list as $r ) : ?><li><a href="<?php echo esc_url( $r['url'] ); ?>"><?php echo esc_html( $r['title'] ); ?></a></li><?php endforeach; ?>
		</ul></section>
	<?php endforeach; ?>
</div></article>

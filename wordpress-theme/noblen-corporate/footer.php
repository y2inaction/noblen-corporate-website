<?php
defined( 'ABSPATH' ) || exit;
$s = array(
	'LinkedIn'  => noblen_setting( 'social_linkedin' ),
	'X'         => noblen_setting( 'social_x' ),
	'Facebook'  => noblen_setting( 'social_facebook' ),
	'Instagram' => noblen_setting( 'social_instagram' ),
	'YouTube'   => noblen_setting( 'social_youtube' ),
);
$s = array_filter( $s );
?>
</main>
<footer class="site-footer">
	<div class="wrap site-footer__grid">
		<div>
			<p class="site-footer__name"><?php echo esc_html( noblen_setting( 'company_name' ) ?: get_bloginfo( 'name' ) ); ?></p>
			<?php if ( noblen_setting( 'tagline' ) ) : ?><p><?php echo esc_html( noblen_setting( 'tagline' ) ); ?></p><?php endif; ?>
			<?php if ( noblen_setting( 'contact_address' ) ) : ?><p><?php echo nl2br( esc_html( noblen_setting( 'contact_address' ) ) ); ?></p><?php endif; ?>
			<?php if ( noblen_setting( 'contact_email' ) ) : ?><p><a href="mailto:<?php echo esc_attr( noblen_setting( 'contact_email' ) ); ?>"><?php echo esc_html( noblen_setting( 'contact_email' ) ); ?></a></p><?php endif; ?>
			<?php if ( noblen_setting( 'contact_phone' ) ) : ?><p><?php echo esc_html( noblen_setting( 'contact_phone' ) ); ?></p><?php endif; ?>
		</div>
		<nav aria-label="Footer"><?php wp_nav_menu( array( 'theme_location' => 'footer', 'container' => false, 'fallback_cb' => false, 'depth' => 1 ) ); ?></nav>
		<?php if ( $s ) : ?>
		<nav aria-label="Social"><ul class="menu">
			<?php foreach ( $s as $label => $url ) : ?><li><a href="<?php echo esc_url( $url ); ?>" rel="noopener"><?php echo esc_html( $label ); ?></a></li><?php endforeach; ?>
		</ul></nav>
		<?php endif; ?>
	</div>
	<div class="wrap site-footer__legal">
		<p>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo esc_html( noblen_setting( 'company_name' ) ?: get_bloginfo( 'name' ) ); ?></p>
		<?php wp_nav_menu( array( 'theme_location' => 'legal', 'container' => false, 'fallback_cb' => false, 'depth' => 1 ) ); ?>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>

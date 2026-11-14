<?php
defined( 'ABSPATH' ) || exit;
$noblen_social = array(
	'LinkedIn'  => noblen_setting( 'social_linkedin' ),
	'X'         => noblen_setting( 'social_x' ),
	'Facebook'  => noblen_setting( 'social_facebook' ),
	'Instagram' => noblen_setting( 'social_instagram' ),
	'YouTube'   => noblen_setting( 'social_youtube' ),
);
$noblen_social = array_filter( $noblen_social );
?>
</main>
<footer class="site-footer">
	<div class="wrap site-footer__grid">
		<div class="site-footer__about">
			<p class="site-footer__name"><?php echo esc_html( noblen_company_name() ); ?></p>
			<p><?php echo esc_html( '' !== noblen_setting( 'tagline' ) ? noblen_setting( 'tagline' ) : 'Technology, innovation and impact.' ); ?></p>
			<ul class="site-footer__contact">
				<?php if ( noblen_setting( 'contact_email' ) ) : ?>
					<li><span class="icon icon--mail" aria-hidden="true"></span><a href="mailto:<?php echo esc_attr( noblen_setting( 'contact_email' ) ); ?>"><?php echo esc_html( noblen_setting( 'contact_email' ) ); ?></a></li>
				<?php endif; ?>
				<?php if ( noblen_setting( 'contact_phone' ) ) : ?>
					<li><span class="icon icon--phone" aria-hidden="true"></span><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', noblen_setting( 'contact_phone' ) ) ); ?>"><?php echo esc_html( noblen_setting( 'contact_phone' ) ); ?></a></li>
				<?php endif; ?>
				<?php if ( noblen_setting( 'contact_address' ) ) : ?>
					<li><span class="icon icon--pin" aria-hidden="true"></span><span><?php echo nl2br( esc_html( noblen_setting( 'contact_address' ) ) ); ?></span></li>
				<?php endif; ?>
			</ul>
		</div>
		<?php if ( has_nav_menu( 'footer' ) ) : ?>
		<nav aria-label="Footer"><p class="site-footer__head">Explore</p>
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'footer',
					'container'      => false,
					'fallback_cb'    => false,
					'depth'          => 1,
				)
			);
			?>
		</nav>
		<?php endif; ?>
		<nav aria-label="Business areas"><p class="site-footer__head">Business areas</p><ul class="menu">
			<?php foreach ( array( 'AI', 'Property', 'Media', 'Energy', 'Cloud' ) as $noblen_area ) : ?>
				<li><a href="<?php echo esc_url( noblen_page_url( 'our-businesses', home_url( '/' ) ) ); ?>#<?php echo esc_attr( strtolower( $noblen_area ) ); ?>">Noblen <?php echo esc_html( $noblen_area ); ?></a></li>
			<?php endforeach; ?>
		</ul></nav>
		<?php if ( $noblen_social ) : ?>
		<nav aria-label="Social"><p class="site-footer__head">Follow</p><ul class="menu">
			<?php
			foreach ( $noblen_social as $label => $url ) :
				?>
				<li><a href="<?php echo esc_url( $url ); ?>" rel="noopener"><?php echo esc_html( $label ); ?></a></li><?php endforeach; ?>
		</ul></nav>
		<?php endif; ?>
	</div>
	<div class="wrap site-footer__legal">
		<p>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo esc_html( noblen_company_name() ); ?></p>
		<?php
		wp_nav_menu(
			array(
				'theme_location' => 'legal',
				'container'      => false,
				'fallback_cb'    => false,
				'depth'          => 1,
			)
		);
		?>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>

<?php
defined( 'ABSPATH' ) || exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header">
	<div class="wrap site-header__inner">
		<?php echo noblen_logo_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside the function. ?>
		<button class="nav-toggle" type="button" aria-expanded="false" aria-controls="primary-nav"><span class="sr-only">Menu</span><span aria-hidden="true" class="nav-toggle__bars"></span></button>
		<nav id="primary-nav" class="site-nav" aria-label="Primary">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'fallback_cb'    => static function () {
						echo '<ul class="menu">';
						wp_list_pages(
							array(
								'title_li' => '',
								'depth'    => 1,
							)
						);
						echo '</ul>';
					},
				)
			);
			?>
		</nav>
	</div>
</header>
<main id="main" tabindex="-1">

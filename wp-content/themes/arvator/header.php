<?php
/**
 * En-tête du site.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="theme-color" content="#0B0B0C">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#contenu"><?php esc_html_e( 'Aller au contenu', 'arvator' ); ?></a>

<header class="site-header">
	<div class="container site-header__inner">
		<?php arvator_brand(); ?>

		<button class="nav-toggle" type="button" aria-expanded="false" aria-controls="menu-principal">
			<span class="nav-toggle__bars" aria-hidden="true"></span>
			<span class="screen-reader-text"><?php esc_html_e( 'Menu', 'arvator' ); ?></span>
		</button>

		<nav id="menu-principal" class="site-nav" aria-label="<?php esc_attr_e( 'Navigation principale', 'arvator' ); ?>">
			<?php arvator_nav( 'primary', 'site-nav__list' ); ?>
			<div class="site-nav__actions">
				<?php if ( '' !== arvator_page_url( 'espace-client' ) ) : ?>
					<a class="link-quiet" href="<?php echo esc_url( arvator_page_url( 'espace-client' ) ); ?>"><?php esc_html_e( 'Espace client', 'arvator' ); ?></a>
				<?php endif; ?>
				<?php arvator_button( 'essai-gratuit', 'Essai gratuit', 'btn btn--primary btn--sm', 'Essai gratuit · bientôt' ); ?>
			</div>
		</nav>
	</div>
</header>

<main id="contenu">

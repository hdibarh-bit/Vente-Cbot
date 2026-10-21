<?php
/**
 * Page standard (textes légaux, FAQ, etc.), éditée dans le tableau de bord WordPress.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div class="container section">
	<?php
	while ( have_posts() ) :
		the_post();
		$arv_wide = has_shortcode( get_the_content(), 'arvator_membre' );
		?>
		<article <?php post_class( $arv_wide ? 'member-page' : 'prose' ); ?>>
			<h1><?php the_title(); ?></h1>
			<?php the_content(); ?>
		</article>
	<?php endwhile; ?>
	<?php
	// Appel à l'action discret en bas des pages d'information (jamais sur les pages légales, l'achat, l'essai ou l'espace membre).
	if ( is_page( array( 'faq', 'a-propos', 'resultats' ) ) ) {
		arvator_cta_band( 'Prêt à essayer ?', 'Testez le robot pendant 14 jours sur un compte démo, ou passez directement à l’achat.' );
	}
	?>
</div>
<?php
get_footer();

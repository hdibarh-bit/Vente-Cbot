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
</div>
<?php
get_footer();

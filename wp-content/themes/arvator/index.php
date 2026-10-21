<?php
/**
 * Modèle par défaut (articles et pages sans modèle dédié).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div class="container section">
	<?php if ( have_posts() ) : ?>
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<article <?php post_class( 'prose' ); ?>>
				<h1><?php the_title(); ?></h1>
				<?php the_content(); ?>
			</article>
		<?php endwhile; ?>
	<?php else : ?>
		<p><?php esc_html_e( 'Aucun contenu à afficher.', 'arvator' ); ?></p>
	<?php endif; ?>
</div>
<?php
get_footer();

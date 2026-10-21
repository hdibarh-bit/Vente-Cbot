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
		?>
		<article <?php post_class( 'prose' ); ?>>
			<h1><?php the_title(); ?></h1>
			<?php the_content(); ?>
		</article>
	<?php endwhile; ?>
</div>
<?php
get_footer();

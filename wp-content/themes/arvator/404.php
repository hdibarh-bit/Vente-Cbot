<?php
/**
 * Page introuvable.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div class="container section prose">
	<h1><?php esc_html_e( 'Page introuvable', 'arvator' ); ?></h1>
	<p><?php esc_html_e( 'Cette page n’existe pas ou a été déplacée.', 'arvator' ); ?></p>
	<p><a class="btn btn--primary" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Retour à l’accueil', 'arvator' ); ?></a></p>
</div>
<?php
get_footer();

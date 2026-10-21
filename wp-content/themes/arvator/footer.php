<?php
/**
 * Pied de page : avertissement sur les risques visible sur chaque page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
</main>

<footer class="site-footer">
	<div class="container">
		<div class="site-footer__grid">
			<div class="site-footer__brand">
				<?php arvator_brand(); ?>
				<p class="muted"><?php echo esc_html( arvator_text( 'footer_blurb' ) ); ?></p>
				<p class="site-footer__cta">
					<?php arvator_button( 'essai-gratuit', 'Essai gratuit', 'btn btn--primary btn--sm' ); ?>
					<?php arvator_buy_button( 'Acheter', 'btn btn--ghost btn--sm' ); ?>
				</p>
			</div>
			<nav aria-label="<?php esc_attr_e( 'Pied de page', 'arvator' ); ?>">
				<h2 class="footer-title"><?php esc_html_e( 'Découvrir', 'arvator' ); ?></h2>
				<?php arvator_nav( 'footer', 'footer-list' ); ?>
			</nav>
			<nav aria-label="<?php esc_attr_e( 'Informations légales', 'arvator' ); ?>">
				<h2 class="footer-title"><?php esc_html_e( 'Informations', 'arvator' ); ?></h2>
				<?php arvator_nav( 'legal', 'footer-list' ); ?>
			</nav>
		</div>

		<p class="risk-footer" role="note"><?php echo esc_html( arvator_risk_notice() ); ?></p>
		<p class="copyright muted">© <?php echo esc_html( gmdate( 'Y' ) ); ?> Arvator. Tous droits réservés.</p>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>

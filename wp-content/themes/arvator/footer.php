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
				<?php
				// Réseaux sociaux de l'éditeur (réglés dans Apparence > Personnaliser, fournis par le plugin Arvator Core).
				if ( class_exists( 'Arvator_Author' ) ) {
					echo Arvator_Author::social_links( 'social-links social-links--footer' ); // phpcs:ignore WordPress.Security.EscapeOutput -- échappé dans le plugin
				}
				?>
				<p class="site-footer__cta">
					<?php arvator_button( 'essai-gratuit', __( 'Essai gratuit', 'arvator' ), 'btn btn--primary btn--sm' ); ?>
					<?php arvator_buy_button( __( 'Acheter', 'arvator' ), 'btn btn--ghost btn--sm' ); ?>
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
		<div class="site-footer__bottom">
			<p class="copyright muted">© <?php echo esc_html( gmdate( 'Y' ) ); ?> Arvator. <?php esc_html_e( 'Tous droits réservés.', 'arvator' ); ?></p>
			<p class="site-footer__meta">
				<button type="button" class="link-button" data-consent-open><?php esc_html_e( 'Gérer les cookies', 'arvator' ); ?></button>
				<span aria-hidden="true">·</span>
				<span><?php esc_html_e( 'Développé par', 'arvator' ); ?> <a href="https://cosmartis.com" target="_blank" rel="noopener">cosmartis.com</a></span>
			</p>
		</div>
	</div>
</footer>

<?php
/*
 * Bandeau de consentement aux cookies (RGPD / recommandations de la CNIL) :
 * - « Tout refuser » aussi visible et aussi simple que « Tout accepter » ; aucune case précochée ;
 * - rien n'est déposé sans accord (le plugin ne dépose le cookie de recommandation qu'après « accepter ») ;
 * - choix retenu 6 mois, modifiable à tout moment par « Gérer les cookies » en bas de page.
 * Masqué par défaut : le script l'affiche seulement si aucun choix n'a encore été fait.
 */
?>
<section id="consent" class="consent" role="dialog" aria-modal="false" aria-labelledby="consent-title" aria-describedby="consent-text" hidden>
	<div class="consent__inner">
		<div class="consent__body">
			<h2 id="consent-title" class="consent__title"><?php esc_html_e( 'Vos choix sur les cookies', 'arvator' ); ?></h2>
			<p id="consent-text"><?php echo wp_kses_post( __( 'Ce site utilise des cookies nécessaires à son fonctionnement (connexion, sécurité, mémorisation de vos choix). Avec votre accord, il utilise aussi un <strong>cookie de recommandation</strong> : si un parrain vous a envoyé son lien, son code est retenu 90&nbsp;jours pour lui attribuer sa récompense si vous achetez. Aucune publicité, aucune mesure d’audience. Vous pouvez changer d’avis à tout moment («&nbsp;Gérer les cookies&nbsp;» en bas de page).', 'arvator' ) ); ?> <a href="<?php echo esc_url( '' !== arvator_page_url( 'cookies' ) ? arvator_page_url( 'cookies' ) : home_url( '/cookies/' ) ); ?>"><?php esc_html_e( 'En savoir plus', 'arvator' ); ?></a></p>
			<div id="consent-prefs" class="consent__prefs" hidden>
				<label class="consent__opt"><input type="checkbox" checked disabled> <span><?php echo wp_kses_post( __( '<strong>Nécessaires</strong> · toujours actifs : connexion à l’espace membre, sécurité, mémorisation de vos choix.', 'arvator' ) ); ?></span></label>
				<label class="consent__opt"><input type="checkbox" id="consent-ref"> <span><?php echo wp_kses_post( __( '<strong>Recommandation</strong> · retient 90&nbsp;jours le code du parrain qui vous a envoyé son lien.', 'arvator' ) ); ?></span></label>
			</div>
		</div>
		<div class="consent__actions">
			<button type="button" class="btn btn--primary btn--sm" data-consent="refuse"><?php esc_html_e( 'Tout refuser', 'arvator' ); ?></button>
			<button type="button" class="btn btn--ghost btn--sm" data-consent="custom" aria-expanded="false" aria-controls="consent-prefs"><?php esc_html_e( 'Personnaliser', 'arvator' ); ?></button>
			<button type="button" class="btn btn--ghost btn--sm" data-consent="save" hidden><?php esc_html_e( 'Enregistrer mes choix', 'arvator' ); ?></button>
			<button type="button" class="btn btn--primary btn--sm" data-consent="accept"><?php esc_html_e( 'Tout accepter', 'arvator' ); ?></button>
		</div>
	</div>
</section>

<?php wp_footer(); ?>
</body>
</html>

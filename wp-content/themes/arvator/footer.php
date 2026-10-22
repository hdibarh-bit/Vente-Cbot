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
		<div class="site-footer__bottom">
			<p class="copyright muted">© <?php echo esc_html( gmdate( 'Y' ) ); ?> Arvator. Tous droits réservés.</p>
			<p class="site-footer__meta">
				<button type="button" class="link-button" data-consent-open><?php esc_html_e( 'Gérer les cookies', 'arvator' ); ?></button>
				<span aria-hidden="true">·</span>
				<span>Développé par <a href="https://cosmartis.com" target="_blank" rel="noopener">cosmartis.com</a></span>
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
			<h2 id="consent-title" class="consent__title">Vos choix sur les cookies</h2>
			<p id="consent-text">Ce site utilise des cookies nécessaires à son fonctionnement (connexion, sécurité, mémorisation de vos choix). Avec votre accord, il utilise aussi un <strong>cookie de recommandation</strong> : si un parrain vous a envoyé son lien, son code est retenu 90&nbsp;jours pour lui attribuer sa récompense si vous achetez. Aucune publicité, aucune mesure d’audience. Vous pouvez changer d’avis à tout moment («&nbsp;Gérer les cookies&nbsp;» en bas de page). <a href="<?php echo esc_url( '' !== arvator_page_url( 'cookies' ) ? arvator_page_url( 'cookies' ) : home_url( '/cookies/' ) ); ?>">En savoir plus</a></p>
			<div id="consent-prefs" class="consent__prefs" hidden>
				<label class="consent__opt"><input type="checkbox" checked disabled> <span><strong>Nécessaires</strong> · toujours actifs : connexion à l’espace membre, sécurité, mémorisation de vos choix.</span></label>
				<label class="consent__opt"><input type="checkbox" id="consent-ref"> <span><strong>Recommandation</strong> · retient 90&nbsp;jours le code du parrain qui vous a envoyé son lien.</span></label>
			</div>
		</div>
		<div class="consent__actions">
			<button type="button" class="btn btn--primary btn--sm" data-consent="refuse">Tout refuser</button>
			<button type="button" class="btn btn--ghost btn--sm" data-consent="custom" aria-expanded="false" aria-controls="consent-prefs">Personnaliser</button>
			<button type="button" class="btn btn--ghost btn--sm" data-consent="save" hidden>Enregistrer mes choix</button>
			<button type="button" class="btn btn--primary btn--sm" data-consent="accept">Tout accepter</button>
		</div>
	</div>
</section>

<?php wp_footer(); ?>
</body>
</html>

<?php
/**
 * Page d'accueil Arvator.
 *
 * Règle de communication : on présente le robot et ses performances simulées en euros et en pourcentage.
 * On ne décrit jamais la stratégie ni aucun détail technique, et on ne promet aucun gain.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$data  = arvator_results();
$price = arvator_pricing();
$cfg   = arvator_settings();
$grace = (int) ( $cfg['graceJours'] ?? 3 );
$trial = (int) ( $cfg['essaiJours'] ?? 14 );
$cap   = (int) ( $cfg['capitalConseille'] ?? 2000 );

// Compte de référence (5 000 € au risque 1 %) pour la carte du haut de page.
$ref = is_array( $data['reference'] ?? null ) ? $data['reference'] : array();

get_header();
?>

<?php // Bandeau « Recommandez Arvator ! » tout en haut, juste sous l'en-tête (demande de Hamid, 23/10/2026). ?>
<?php if ( '' !== arvator_page_url( 'parrainage' ) ) : ?>
	<section class="section section--tight section--top" aria-label="<?php esc_attr_e( 'Recommandation', 'arvator' ); ?>">
		<div class="container"><?php arvator_referral_banner(); ?></div>
	</section>
<?php endif; ?>

<section class="hero">
	<div class="container hero__inner">
		<div class="hero__copy">
			<p class="eyebrow"><?php echo esc_html( arvator_text( 'hero_eyebrow' ) ); ?></p>
			<h1><?php echo esc_html( arvator_text( 'hero_title' ) ); ?></h1>
			<p class="lead"><?php echo esc_html( arvator_text( 'hero_lead' ) ); ?></p>
			<p class="lead lead--sub">
				<?php echo wp_kses_post( __( 'Les résultats présentés sont des <strong>performances simulées</strong> (backtest), pas des résultats réels : nous les montrons toujours avec les baisses qui vont avec.', 'arvator' ) ); ?>
			</p>
			<div class="hero__cta">
				<?php if ( '' !== arvator_page_url( 'essai-gratuit' ) ) : ?>
					<?php /* translators: %d: durée de l'essai en jours. */ arvator_button( 'essai-gratuit', sprintf( __( 'Essayer %d jours sur compte démo', 'arvator' ), $trial ), 'btn btn--primary' ); ?>
					<a class="btn btn--ghost" href="#tarifs"><?php esc_html_e( 'Voir les tarifs', 'arvator' ); ?></a>
				<?php else : ?>
					<a class="btn btn--primary" href="#tarifs"><?php esc_html_e( 'Voir les tarifs', 'arvator' ); ?></a>
				<?php endif; ?>
			</div>
			<p class="hero__note muted"><a href="#resultats"><?php esc_html_e( 'Voir les résultats simulés', 'arvator' ); ?></a></p>
		</div>

		<?php if ( $ref ) : ?>
			<aside class="hero__card" aria-label="<?php esc_attr_e( 'Exemple de performances simulées', 'arvator' ); ?>">
				<p class="hero__card-kicker"><?php esc_html_e( 'Performances simulées · risque 1 %', 'arvator' ); ?></p>
				<p class="hero__card-title"><?php /* translators: 1: capital de départ, 2: solde final. */ echo esc_html( sprintf( __( '%1$s → %2$s', 'arvator' ), arvator_fmt_capital( $ref['capital'] ), arvator_fmt_eur( $ref['soldeFinal'] ) ) ); ?></p>
				<dl class="stat-list">
					<div class="stat-list__row">
						<dt><?php esc_html_e( 'Évolution simulée', 'arvator' ); ?></dt>
						<dd><?php echo esc_html( arvator_fmt_pct1( $ref['gainPct'] ) ); ?> <span class="stat-list__pct"><?php echo esc_html( arvator_fmt_eur_signed( $ref['gainEur'] ) ); ?></span></dd>
					</div>
					<div class="stat-list__row stat-list__row--neg">
						<dt><?php esc_html_e( 'Pire baisse', 'arvator' ); ?></dt>
						<dd><?php echo esc_html( arvator_fmt_pct1( $ref['pireBaissePct'], true, true ) ); ?> <span class="stat-list__pct"><?php echo esc_html( arvator_fmt_eur_signed( $ref['pireBaisseEur'] ) ); ?></span></dd>
					</div>
				</dl>
				<p class="hero__card-note muted"><?php echo wp_kses_post( __( 'Compte simulé du 1<sup>er</sup> janvier 2024 au 25 octobre 2026, frais d’un courtier inclus. Baisse en cours au 25/10/2026. Ne garantit pas les résultats futurs.', 'arvator' ) ); ?></p>
			</aside>
		<?php endif; ?>
	</div>
</section>

<section class="section" id="comment-ca-marche" aria-labelledby="titre-etapes">
	<div class="container">
		<p class="eyebrow"><?php esc_html_e( 'Comment ça marche', 'arvator' ); ?></p>
		<h2 id="titre-etapes"><?php esc_html_e( 'Trois étapes, sans rien de compliqué', 'arvator' ); ?></h2>
		<ol class="steps">
			<li class="steps__item">
				<span class="steps__num" aria-hidden="true">1</span>
				<h3><?php esc_html_e( 'Vous choisissez votre formule', 'arvator' ); ?></h3>
				<p><?php esc_html_e( 'Un achat du robot, puis un abonnement mensuel ou annuel pour garder votre licence active. Vous pouvez d’abord l’essayer 14&nbsp;jours sur un compte démo.', 'arvator' ); ?></p>
			</li>
			<li class="steps__item">
				<span class="steps__num" aria-hidden="true">2</span>
				<h3><?php esc_html_e( 'Vous l’installez sur votre compte cTrader', 'arvator' ); ?></h3>
				<p><?php esc_html_e( 'Vous recevez votre licence et un guide pas à pas. Le robot tourne dans le cloud cTrader, sans que votre ordinateur reste allumé.', 'arvator' ); ?></p>
			</li>
			<li class="steps__item">
				<span class="steps__num" aria-hidden="true">3</span>
				<h3><?php esc_html_e( 'Vous gardez le contrôle du risque', 'arvator' ); ?></h3>
				<p><?php esc_html_e( 'Le robot est livré réglé à 1&nbsp;% de risque par trade. Vous pouvez le monter à 2&nbsp;% ou 3&nbsp;%, jamais au-delà. Vous restez seul responsable de votre compte.', 'arvator' ); ?></p>
			</li>
		</ol>
		<p class="trust-line"><?php esc_html_e( 'Nous ne touchons jamais à votre argent ni à votre compte : nous vendons un logiciel.', 'arvator' ); ?></p>
		<p class="section__more"><?php arvator_button( 'essai-gratuit', __( 'Commencer par l’essai gratuit', 'arvator' ), 'btn btn--ghost' ); ?></p>
	</div>
</section>

<section class="section section--surface" id="resultats" aria-labelledby="titre-resultats">
	<div class="container">
		<p class="eyebrow"><?php esc_html_e( 'Résultats simulés', 'arvator' ); ?></p>
		<h2 id="titre-resultats"><?php esc_html_e( 'Backtest avec les frais réels d’un courtier', 'arvator' ); ?></h2>
		<p class="section__intro">
			<?php esc_html_e( 'Un backtest rejoue le robot sur des prix passés. Ce sont des performances simulées : elles ne garantissent pas les résultats futurs. Regardez toujours la pire baisse à côté de chaque gain.', 'arvator' ); ?>
		</p>

		<?php get_template_part( 'template-parts/results', 'reference', array( 'data' => $data ) ); ?>

		<h3 class="results__title results__title--spaced"><?php esc_html_e( 'Tous les capitaux de départ', 'arvator' ); ?></h3>
		<?php get_template_part( 'template-parts/results', 'tabs', array( 'data' => $data ) ); ?>

		<div class="callout" role="note">
			<h3><?php esc_html_e( 'Comment ce backtest est calculé', 'arvator' ); ?></h3>
			<ul class="callout__list">
				<li><?php esc_html_e( 'Un seul compte, ouvert le 1er janvier 2024 et suivi jusqu’au 25 octobre 2026, sans retrait. Les gains s’accumulent d’une année sur l’autre, sans remise à zéro chaque année.', 'arvator' ); ?></li>
				<li><?php esc_html_e( 'Réglage livré : 1 perte maximum par jour, 3 positions maximum en même temps.', 'arvator' ); ?></li>
				<li><?php esc_html_e( 'Tous les frais d’un vrai courtier sont inclus, avec IC Markets (compte Raw Spread, cTrader) cité comme exemple de courtier : les écarts de prix (spreads), la commission, les frais pour garder une position ouverte la nuit (swaps) et la taille minimale de position.', 'arvator' ); ?></li>
				<li><?php esc_html_e( 'Quand le capital est trop petit pour la taille minimale du courtier, le robot ne prend pas le trade : c’est compté dans les résultats (colonne « Trades refusés »).', 'arvator' ); ?></li>
			</ul>
			<p class="muted"><?php esc_html_e( 'Objectif de cette section : vous donner une idée réaliste de ce que le robot coûte en frais et de ce qu’il pourrait rapporter, sans promesse.', 'arvator' ); ?></p>
		</div>

		<div class="callout" role="note">
			<h3><?php esc_html_e( 'Pourquoi nous conseillons au moins 2&nbsp;000&nbsp;€', 'arvator' ); ?></h3>
			<p>
				<?php esc_html_e( 'Ce n’est pas une obligation. Avec un petit capital, la taille minimale de position imposée par le courtier ferait risquer plus que prévu : le robot refuse alors le trade. Il en prend donc moins, et les résultats deviennent plus irréguliers. À partir de 2&nbsp;000&nbsp;€, presque plus aucun trade n’est refusé (voir la colonne « Trades refusés ») ; en dessous, une partie des trades est refusée.', 'arvator' ); ?>
			</p>
		</div>

		<p class="section__more">
			<?php arvator_button( 'resultats', __( 'Voir tous les résultats détaillés', 'arvator' ), 'btn btn--ghost' ); ?>
			<?php arvator_button( 'essai-gratuit', __( 'Essayer sur compte démo', 'arvator' ), 'btn btn--primary' ); ?>
		</p>
	</div>
</section>

<section class="section" id="tarifs" aria-labelledby="titre-tarifs">
	<div class="container">
		<p class="eyebrow"><?php esc_html_e( 'Tarifs', 'arvator' ); ?></p>
		<h2 id="titre-tarifs"><?php esc_html_e( 'Ce que ça coûte, sans surprise', 'arvator' ); ?></h2>
		<p class="section__intro">
			<?php echo wp_kses_post( __( 'Deux éléments : un <strong>achat unique</strong> pour le robot, puis un <strong>abonnement</strong> qui garde votre licence active. Au moment du paiement, vous réglez l’achat et le premier abonnement en une seule fois.', 'arvator' ) ); ?>
		</p>

		<div class="pricing">
			<article class="plan" aria-labelledby="plan-mensuel">
				<h3 id="plan-mensuel"><?php esc_html_e( 'Abonnement mensuel', 'arvator' ); ?></h3>
				<p class="plan__price"><span class="plan__amount"><?php echo esc_html( arvator_fmt_price( $price['mensuel'] ) ); ?></span> <span class="plan__unit"><?php esc_html_e( 'par mois', 'arvator' ); ?></span></p>
				<dl class="plan__rows">
					<div><dt><?php esc_html_e( 'Achat du robot (une seule fois)', 'arvator' ); ?></dt><dd><?php echo esc_html( arvator_fmt_price( $price['achat'] ) ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Premier paiement', 'arvator' ); ?></dt><dd><?php echo esc_html( arvator_fmt_price( $price['premier_paiement_m'] ) ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Ensuite, chaque mois', 'arvator' ); ?></dt><dd><?php echo esc_html( arvator_fmt_price( $price['mensuel'] ) ); ?></dd></div>
					<div class="plan__total"><dt><?php esc_html_e( 'Coût de la première année', 'arvator' ); ?></dt><dd><?php echo esc_html( arvator_fmt_price( $price['annee1_mensuel'] ) ); ?></dd></div>
				</dl>
				<p class="plan__cta"><?php arvator_buy_button( __( 'Choisir le mensuel', 'arvator' ), 'btn btn--ghost', 'monthly' ); ?></p>
			</article>

			<article class="plan plan--featured" aria-labelledby="plan-annuel">
				<p class="plan__flag"><?php esc_html_e( 'Le plus avantageux', 'arvator' ); ?><?php /* translators: %s: montant économisé par an. */ echo $price['economie'] > 0 ? ' · ' . esc_html( sprintf( __( '%s d’économie par an', 'arvator' ), arvator_fmt_price( $price['economie'] ) ) ) : ''; ?></p>
				<h3 id="plan-annuel"><?php esc_html_e( 'Abonnement annuel', 'arvator' ); ?></h3>
				<p class="plan__price"><span class="plan__amount"><?php echo esc_html( arvator_fmt_price( $price['annuel_par_mois'] ) ); ?></span> <span class="plan__unit"><?php esc_html_e( 'par mois, payés d’avance pour l’année', 'arvator' ); ?></span></p>
				<dl class="plan__rows">
					<div><dt><?php esc_html_e( 'Achat du robot (une seule fois)', 'arvator' ); ?></dt><dd><?php echo esc_html( arvator_fmt_price( $price['achat'] ) ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Premier paiement', 'arvator' ); ?></dt><dd><?php echo esc_html( arvator_fmt_price( $price['premier_paiement_a'] ) ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Ensuite, chaque année', 'arvator' ); ?></dt><dd><?php echo esc_html( arvator_fmt_price( $price['annuel_total'] ) ); ?></dd></div>
					<div class="plan__total"><dt><?php esc_html_e( 'Coût de la première année', 'arvator' ); ?></dt><dd><?php echo esc_html( arvator_fmt_price( $price['annee1_annuel'] ) ); ?></dd></div>
				</dl>
				<p class="plan__cta"><?php arvator_buy_button( __( 'Choisir l’annuel', 'arvator' ), 'btn btn--primary', 'annual' ); ?></p>
			</article>
		</div>

		<div class="pricing-info">
			<div>
				<h3><?php esc_html_e( 'Ce que vous recevez', 'arvator' ); ?></h3>
				<ul class="checklist">
					<li><?php esc_html_e( 'Le robot Arvator, prêt à installer sur votre compte cTrader', 'arvator' ); ?></li>
					<li><?php esc_html_e( 'Votre licence, renouvelée tant que votre abonnement est à jour', 'arvator' ); ?></li>
					<li><?php esc_html_e( 'Un guide d’installation pas à pas', 'arvator' ); ?></li>
					<li><?php esc_html_e( 'Votre espace client : téléchargement, licence, abonnement et factures', 'arvator' ); ?></li>
				</ul>
			</div>
			<div>
				<h3><?php esc_html_e( 'Bon à savoir avant d’acheter', 'arvator' ); ?></h3>
				<ul class="checklist">
					<li><?php /* translators: %d: jours de grâce. */ echo esc_html( sprintf( __( 'L’abonnement est obligatoire : sans abonnement à jour, la licence expire. Après %d jours de grâce, le robot ne prend plus de nouvelles positions.', 'arvator' ), $grace ) ); ?></li>
					<li><?php /* translators: %s: capital conseillé. */ echo esc_html( sprintf( __( 'Le capital que vous placez sur votre compte cTrader est à part du prix du robot, et reste chez votre courtier. Nous conseillons au moins %s (voir plus haut pourquoi).', 'arvator' ), arvator_fmt_capital( $cap ) ) ); ?></li>
					<li><?php esc_html_e( 'Paiement sécurisé par Stripe : vos données de carte ne passent jamais par ce site.', 'arvator' ); ?></li>
				</ul>
			</div>
		</div>

		<div class="hero__cta">
			<?php /* translators: %d: durée de l'essai en jours. */
			arvator_button( 'essai-gratuit', sprintf( __( 'Essayer d’abord %d jours sur compte démo', 'arvator' ), $trial ), 'btn btn--ghost' ); ?>
		</div>
	</div>
</section>

<section class="section section--surface" aria-labelledby="titre-essai">
	<div class="container">
		<div class="cta-band">
			<div>
				<h2 id="titre-essai"><?php esc_html_e( 'Essayez avant d’acheter', 'arvator' ); ?></h2>
				<p><?php /* translators: %d: durée de l'essai en jours. */ echo esc_html( sprintf( __( '%d jours sur un compte démo, sans risquer votre argent. Il suffit d’une adresse email et du numéro de votre compte démo cTrader.', 'arvator' ), $trial ) ); ?></p>
			</div>
			<div class="cta-band__actions">
				<?php arvator_button( 'essai-gratuit', __( 'Demander mon essai gratuit', 'arvator' ), 'btn btn--primary' ); ?>
				<?php arvator_buy_button( __( 'Acheter Arvator', 'arvator' ), 'btn btn--ghost' ); ?>
			</div>
		</div>
	</div>
</section>

<?php
get_footer();

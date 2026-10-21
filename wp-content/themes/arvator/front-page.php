<?php
/**
 * Page d'accueil Arvator.
 *
 * Règle de communication : on présente le robot et ses performances simulées en pourcentage.
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
$cap   = (int) ( $cfg['capitalConseille'] ?? 5000 );

// Ligne « capital conseillé » du réglage livré (1 %), pour la carte du haut de page.
$hero_row = null;
foreach ( $data['risques'] ?? array() as $risk ) {
	if ( 1 === (int) $risk['risquePct'] ) {
		foreach ( $risk['lignes'] as $row ) {
			if ( 5000 === (int) $row['capital'] ) {
				$hero_row = $row;
			}
		}
	}
}

get_header();
?>

<section class="hero">
	<div class="container hero__inner">
		<div class="hero__copy">
			<p class="eyebrow"><?php echo esc_html( arvator_text( 'hero_eyebrow' ) ); ?></p>
			<h1><?php echo esc_html( arvator_text( 'hero_title' ) ); ?></h1>
			<p class="lead"><?php echo esc_html( arvator_text( 'hero_lead' ) ); ?></p>
			<p class="lead lead--sub">
				Les résultats présentés sont des <strong>performances simulées</strong> (backtest), pas des résultats réels :
				nous les montrons toujours avec les baisses qui vont avec.
			</p>
			<div class="hero__cta">
				<?php if ( '' !== arvator_page_url( 'essai-gratuit' ) ) : ?>
					<?php arvator_button( 'essai-gratuit', 'Essayer ' . $trial . ' jours sur compte démo', 'btn btn--primary' ); ?>
					<a class="btn btn--ghost" href="#tarifs"><?php esc_html_e( 'Voir les tarifs', 'arvator' ); ?></a>
				<?php else : ?>
					<a class="btn btn--primary" href="#tarifs"><?php esc_html_e( 'Voir les tarifs', 'arvator' ); ?></a>
				<?php endif; ?>
			</div>
			<p class="hero__note muted"><a href="#resultats"><?php esc_html_e( 'Voir les résultats simulés', 'arvator' ); ?></a></p>
		</div>

		<?php if ( $hero_row ) : ?>
			<aside class="hero__card" aria-label="Exemple de performances simulées">
				<p class="hero__card-kicker">Performances simulées · risque 1 %</p>
				<p class="hero__card-title">Capital de départ de <?php echo esc_html( arvator_fmt_capital( $hero_row['capital'] ) ); ?></p>
				<dl class="stat-list">
					<?php
					$labels = array(
						'2024'          => '2024',
						'2025'          => '2025',
						'2026_au_7_oct' => '2026 (au 7 oct.)',
					);
					foreach ( $labels as $key => $label ) :
						?>
						<div class="stat-list__row">
							<dt><?php echo esc_html( $label ); ?></dt>
							<dd><?php echo esc_html( arvator_fmt_pct( $hero_row['parAnnee'][ $key ]['gainPct'] ) ); ?></dd>
						</div>
					<?php endforeach; ?>
					<div class="stat-list__row stat-list__row--neg">
						<dt>Pire baisse sur la période</dt>
						<dd><?php echo esc_html( arvator_fmt_pct( $hero_row['troisAns']['pireBaissePct'] ) ); ?></dd>
					</div>
					<div class="stat-list__row stat-list__row--neg">
						<dt>Solde le plus bas</dt>
						<dd><?php echo esc_html( arvator_fmt_eur( $hero_row['troisAns']['plusBasDuCompte'] ) ); ?></dd>
					</div>
				</dl>
				<p class="hero__card-note muted">Backtest du 1<sup>er</sup> janvier 2024 au 7 octobre 2026. Ne préjuge pas des résultats futurs.</p>
			</aside>
		<?php endif; ?>
	</div>
</section>

<section class="section" id="comment-ca-marche" aria-labelledby="titre-etapes">
	<div class="container">
		<p class="eyebrow">Comment ça marche</p>
		<h2 id="titre-etapes">Trois étapes, sans rien de compliqué</h2>
		<ol class="steps">
			<li class="steps__item">
				<span class="steps__num" aria-hidden="true">1</span>
				<h3>Vous choisissez votre formule</h3>
				<p>Un achat du robot, puis un abonnement mensuel ou annuel pour garder votre licence active. Vous pouvez d’abord l’essayer 14&nbsp;jours sur un compte démo.</p>
			</li>
			<li class="steps__item">
				<span class="steps__num" aria-hidden="true">2</span>
				<h3>Vous l’installez sur votre compte cTrader</h3>
				<p>Vous recevez votre licence et un guide pas à pas. Le robot tourne dans le cloud cTrader, sans que votre ordinateur reste allumé.</p>
			</li>
			<li class="steps__item">
				<span class="steps__num" aria-hidden="true">3</span>
				<h3>Vous gardez le contrôle du risque</h3>
				<p>Le robot est livré réglé à 1&nbsp;% de risque par trade. Vous pouvez le monter à 2&nbsp;% ou 3&nbsp;%, jamais au-delà. Vous restez seul responsable de votre compte.</p>
			</li>
		</ol>
		<p class="trust-line">Nous ne touchons jamais à votre argent ni à votre compte : nous vendons un logiciel.</p>
		<p class="section__more"><?php arvator_button( 'essai-gratuit', 'Commencer par l’essai gratuit', 'btn btn--ghost' ); ?></p>
	</div>
</section>

<section class="section section--surface" id="resultats" aria-labelledby="titre-resultats">
	<div class="container">
		<p class="eyebrow">Résultats simulés</p>
		<h2 id="titre-resultats">Ce que le robot aurait fait entre 2024 et 2026, avec ses baisses</h2>
		<p class="section__intro">
			Un backtest rejoue le robot sur des prix passés. C’est utile pour comprendre son comportement, mais ce n’est pas une promesse :
			regardez toujours la pire baisse à côté de chaque gain.
		</p>

		<?php get_template_part( 'template-parts/results', 'tabs', array( 'data' => $data ) ); ?>

		<div class="callout" role="note">
			<h3>Pourquoi nous conseillons au moins 5&nbsp;000&nbsp;€</h3>
			<p>
				Ce n’est pas une obligation. Avec un petit capital, la taille minimale de position imposée par le courtier ferait risquer plus que prévu :
				le robot refuse alors le trade. Il en prend donc moins, et les résultats deviennent plus irréguliers.
				Plus le capital est élevé, moins il y a de trades refusés.
			</p>
		</div>

		<p class="section__more">
			<?php arvator_button( 'resultats', 'Voir tous les résultats détaillés', 'btn btn--ghost' ); ?>
			<?php arvator_button( 'essai-gratuit', 'Essayer sur compte démo', 'btn btn--primary' ); ?>
		</p>
	</div>
</section>

<section class="section" id="tarifs" aria-labelledby="titre-tarifs">
	<div class="container">
		<p class="eyebrow">Tarifs</p>
		<h2 id="titre-tarifs">Ce que ça coûte, sans surprise</h2>
		<p class="section__intro">
			Deux éléments : un <strong>achat unique</strong> pour le robot, puis un <strong>abonnement</strong> qui garde votre licence active.
			Au moment du paiement, vous réglez l’achat et le premier abonnement en une seule fois.
		</p>

		<div class="pricing">
			<article class="plan" aria-labelledby="plan-mensuel">
				<h3 id="plan-mensuel">Abonnement mensuel</h3>
				<p class="plan__price"><span class="plan__amount"><?php echo esc_html( arvator_fmt_price( $price['mensuel'] ) ); ?></span> <span class="plan__unit">par mois</span></p>
				<dl class="plan__rows">
					<div><dt>Achat du robot (une seule fois)</dt><dd><?php echo esc_html( arvator_fmt_price( $price['achat'] ) ); ?></dd></div>
					<div><dt>Premier paiement</dt><dd><?php echo esc_html( arvator_fmt_price( $price['premier_paiement_m'] ) ); ?></dd></div>
					<div><dt>Ensuite, chaque mois</dt><dd><?php echo esc_html( arvator_fmt_price( $price['mensuel'] ) ); ?></dd></div>
					<div class="plan__total"><dt>Coût de la première année</dt><dd><?php echo esc_html( arvator_fmt_price( $price['annee1_mensuel'] ) ); ?></dd></div>
				</dl>
				<p class="plan__cta"><?php arvator_buy_button( 'Choisir le mensuel', 'btn btn--ghost', 'monthly' ); ?></p>
			</article>

			<article class="plan plan--featured" aria-labelledby="plan-annuel">
				<p class="plan__flag">Le plus avantageux<?php echo $price['economie'] > 0 ? ' · ' . esc_html( arvator_fmt_price( $price['economie'] ) ) . ' d’économie par an' : ''; ?></p>
				<h3 id="plan-annuel">Abonnement annuel</h3>
				<p class="plan__price"><span class="plan__amount"><?php echo esc_html( arvator_fmt_price( $price['annuel_par_mois'] ) ); ?></span> <span class="plan__unit">par mois, payés d’avance pour l’année</span></p>
				<dl class="plan__rows">
					<div><dt>Achat du robot (une seule fois)</dt><dd><?php echo esc_html( arvator_fmt_price( $price['achat'] ) ); ?></dd></div>
					<div><dt>Premier paiement</dt><dd><?php echo esc_html( arvator_fmt_price( $price['premier_paiement_a'] ) ); ?></dd></div>
					<div><dt>Ensuite, chaque année</dt><dd><?php echo esc_html( arvator_fmt_price( $price['annuel_total'] ) ); ?></dd></div>
					<div class="plan__total"><dt>Coût de la première année</dt><dd><?php echo esc_html( arvator_fmt_price( $price['annee1_annuel'] ) ); ?></dd></div>
				</dl>
				<p class="plan__cta"><?php arvator_buy_button( 'Choisir l’annuel', 'btn btn--primary', 'annual' ); ?></p>
			</article>
		</div>

		<div class="pricing-info">
			<div>
				<h3>Ce que vous recevez</h3>
				<ul class="checklist">
					<li>Le robot Arvator, prêt à installer sur votre compte cTrader</li>
					<li>Votre licence, renouvelée tant que votre abonnement est à jour</li>
					<li>Un guide d’installation pas à pas</li>
					<li>Votre espace client : téléchargement, licence, abonnement et factures</li>
				</ul>
			</div>
			<div>
				<h3>Bon à savoir avant d’acheter</h3>
				<ul class="checklist">
					<li>L’abonnement est obligatoire : sans abonnement à jour, la licence expire. Après <?php echo esc_html( (string) $grace ); ?> jours de grâce, le robot ne prend plus de nouvelles positions.</li>
					<li>Le capital que vous placez sur votre compte cTrader est à part du prix du robot, et reste chez votre courtier. Nous conseillons au moins <?php echo esc_html( arvator_fmt_capital( $cap ) ); ?> (voir plus haut pourquoi).</li>
					<li>Paiement sécurisé par Stripe : vos données de carte ne passent jamais par ce site.</li>
				</ul>
			</div>
		</div>

		<div class="hero__cta">
			<?php arvator_button( 'essai-gratuit', 'Essayer d’abord ' . $trial . ' jours sur compte démo', 'btn btn--ghost' ); ?>
		</div>
	</div>
</section>

<?php if ( '' !== arvator_page_url( 'parrainage' ) ) : ?>
	<section class="section section--tight" aria-label="Recommandation">
		<div class="container"><?php arvator_referral_banner(); ?></div>
	</section>
<?php endif; ?>

<section class="section section--surface" aria-labelledby="titre-essai">
	<div class="container">
		<div class="cta-band">
			<div>
				<h2 id="titre-essai">Essayez avant d’acheter</h2>
				<p><?php echo esc_html( (string) $trial ); ?> jours sur un compte démo, sans risquer votre argent. Il suffit d’une adresse email et du numéro de votre compte démo cTrader.</p>
			</div>
			<div class="cta-band__actions">
				<?php arvator_button( 'essai-gratuit', 'Demander mon essai gratuit', 'btn btn--primary' ); ?>
				<?php arvator_buy_button( 'Acheter Arvator', 'btn btn--ghost' ); ?>
			</div>
		</div>
	</div>
</section>

<?php
get_footer();

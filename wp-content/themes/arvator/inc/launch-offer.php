<?php
/**
 * Offre de lancement : prix actuel du robot, puis prix annoncé à partir d'une date (demande de Hamid, 28/10/2026).
 *
 * Règle légale (art. L112-1-1 du Code de la consommation) : pas de prix barré, puisque le prix futur n'a jamais
 * été pratiqué. On annonce seulement le prix qui s'appliquera à la date indiquée. Le bandeau et les mentions
 * disparaissent seuls à cette date (heure de Paris) ; le prix réel (réglage et Stripe) doit alors être changé.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Offre en cours, ou null si aucune (pas configurée ou date passée).
 *
 * @return array{prix:float, apres:float, date:DateTimeImmutable}|null
 */
function arvator_launch_offer(): ?array {
	$p     = arvator_settings()['prix'] ?? array();
	$offer = is_array( $p['offreLancement'] ?? null ) ? $p['offreLancement'] : array();
	$prix  = (float) ( $p['achatCbot'] ?? 0 );
	$apres = (float) ( $offer['prixApres'] ?? 0 );
	$tz    = new DateTimeZone( 'Europe/Paris' );
	$date  = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) ( $offer['dateChangement'] ?? '' ), $tz );

	if ( ! $date || $prix <= 0 || $apres <= $prix ) {
		return null;
	}
	$now = new DateTimeImmutable( 'now', $tz );
	if ( $now >= $date ) {
		return null;
	}
	return array( 'prix' => $prix, 'apres' => $apres, 'date' => $date );
}

/** Date de changement en toutes lettres : « 1er janvier 2027 ». */
function arvator_launch_offer_date( DateTimeImmutable $date ): string {
	$day = (int) $date->format( 'j' );
	/* translators: jour du mois 1 (« 1er » en français). */
	$day_label = 1 === $day ? __( '1er', 'arvator' ) : (string) $day;
	return $day_label . "\u{00A0}" . wp_date( 'F Y', $date->getTimestamp(), $date->getTimezone() );
}

/** Mention courte à côté du prix : « Prix de lancement · 2 500 € à partir du 1er janvier 2027 ». '' sans offre. */
function arvator_launch_offer_note(): string {
	$o = arvator_launch_offer();
	if ( null === $o ) {
		return '';
	}
	/* translators: 1: prix futur, 2: date du changement. */
	return sprintf( __( 'Prix de lancement · %1$s à partir du %2$s', 'arvator' ), arvator_fmt_price( $o['apres'] ), arvator_launch_offer_date( $o['date'] ) );
}

/** Bandeau « Offre de lancement » de l'accueil (rien sans offre en cours). */
function arvator_launch_offer_banner(): void {
	$o = arvator_launch_offer();
	if ( null === $o ) {
		return;
	}
	$buy   = arvator_buy_url();
	$trial = arvator_page_url( 'essai-gratuit' );
	?>
	<div class="launch">
		<div class="launch__body">
			<p class="launch__pill"><?php esc_html_e( 'Offre de lancement', 'arvator' ); ?></p>
			<h2 id="titre-lancement" class="launch__title">
				<span class="launch__amount"><?php echo esc_html( arvator_fmt_price( $o['prix'] ) ); ?></span>
				<span class="launch__unit"><?php esc_html_e( 'le robot, payé une seule fois', 'arvator' ); ?></span>
			</h2>
			<p class="launch__text">
				<?php
				/* translators: 1: prix de lancement, 2: prix futur, 3: date du changement. */
				echo esc_html( sprintf( __( 'Prix de lancement : %1$s pour les premiers utilisateurs. Il passera à %2$s le %3$s.', 'arvator' ), arvator_fmt_price( $o['prix'] ), arvator_fmt_price( $o['apres'] ), arvator_launch_offer_date( $o['date'] ) ) );
				?>
			</p>
			<p class="launch__note"><?php esc_html_e( 'Abonnement en plus, pour garder la licence active (voir les tarifs).', 'arvator' ); ?></p>
		</div>
		<?php if ( '' !== $buy || '' !== $trial ) : ?>
			<div class="launch__cta">
				<?php if ( '' !== $buy ) : ?>
					<a class="btn btn--primary" href="<?php echo esc_url( $buy ); ?>"><?php esc_html_e( 'En profiter', 'arvator' ); ?></a>
				<?php endif; ?>
				<?php if ( '' !== $trial ) : ?>
					<a class="btn btn--ghost" href="<?php echo esc_url( $trial ); ?>"><?php esc_html_e( 'Essayer Arvator', 'arvator' ); ?></a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

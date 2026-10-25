<?php
/**
 * Backtest de référence : un seul compte, frais d'un vrai courtier inclus, 5 000 € au risque 1 %.
 * Titre = la méthode (pas de montant en titre). Pire baisse toujours à côté du gain, en % et en €, avec les dates.
 *
 * @var array<string, mixed> $args  « data » : résultats.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$data = $args['data'] ?? array();
$ref  = is_array( $data['reference'] ?? null ) ? $data['reference'] : array();
if ( ! $ref ) {
	return;
}
$years = is_array( $ref['annees'] ?? null ) ? $ref['annees'] : array();
$start = (int) $ref['capital'];
?>
<div class="reference">
	<div class="reference__main">
		<p class="reference__kicker"><?php /* translators: %d: niveau de risque. */ echo esc_html( sprintf( __( 'Performances simulées · compte de référence · risque %d %%', 'arvator' ), (int) $ref['risquePct'] ) ); ?></p>
		<p class="reference__line">
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: capital de départ, 2: solde final. */
					__( '%1$s au 1er janvier 2024 → %2$s au 25 octobre 2026', 'arvator' ),
					arvator_fmt_eur( $start ),
					arvator_fmt_eur( $ref['soldeFinal'] )
				)
			);
			?>
		</p>
		<dl class="stat-list">
			<div class="stat-list__row">
				<dt><?php esc_html_e( 'Évolution simulée', 'arvator' ); ?></dt>
				<dd><?php echo esc_html( arvator_fmt_pct1( $ref['gainPct'] ) . ' (' . arvator_fmt_eur_signed( $ref['gainEur'] ) . ')' ); ?></dd>
			</div>
			<div class="stat-list__row stat-list__row--neg">
				<dt><?php esc_html_e( 'Pire baisse', 'arvator' ); ?></dt>
				<dd>
					<?php echo esc_html( arvator_fmt_pct1( $ref['pireBaissePct'], true, true ) . ' (' . arvator_fmt_eur_signed( $ref['pireBaisseEur'] ) . ')' ); ?>
					<span class="stat-list__pct"><?php echo esc_html( arvator_fmt_span( $ref['debutBaisse'], $ref['finBaisse'] ) ); ?></span>
				</dd>
			</div>
			<div class="stat-list__row">
				<dt><?php esc_html_e( 'Trades pris', 'arvator' ); ?></dt>
				<dd><?php echo esc_html( arvator_fmt_int( $ref['tradesPris'] ) . ' · ' . arvator_fmt_pct1( $ref['partGagnantsPct'], false ) ); ?> <span class="stat-list__pct"><?php esc_html_e( 'gagnants', 'arvator' ); ?></span></dd>
			</div>
			<div class="stat-list__row">
				<dt><?php esc_html_e( 'Trades refusés', 'arvator' ); ?></dt>
				<dd><?php echo esc_html( arvator_fmt_int( $ref['tradesRefuses'] ) ); ?></dd>
			</div>
		</dl>
		<?php if ( ! empty( $ref['baisseEnCours'] ) ) : ?>
			<p class="reference__alert"><?php esc_html_e( 'Baisse en cours au 25/10/2026 : le compte n’est pas encore revenu à son plus haut.', 'arvator' ); ?></p>
		<?php endif; ?>
	</div>

	<?php if ( $years ) : ?>
		<h3 class="results__title"><?php esc_html_e( 'Le même compte, année par année', 'arvator' ); ?></h3>
		<div class="table-wrap" role="region" aria-label="<?php esc_attr_e( 'Détail par année du compte de référence', 'arvator' ); ?>" tabindex="0">
			<table class="results__table results__table--years">
				<caption class="screen-reader-text"><?php esc_html_e( 'Compte de référence : solde, gain et pire baisse pour chaque année', 'arvator' ); ?></caption>
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Année', 'arvator' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Solde début → fin', 'arvator' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Gain', 'arvator' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Pire baisse', 'arvator' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Période de la baisse', 'arvator' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Retour au plus haut', 'arvator' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $years as $y ) : ?>
						<tr>
							<th scope="row">
								<?php echo esc_html( $y['annee'] ); ?>
								<?php if ( ! empty( $y['enCours'] ) ) : ?>
									<span class="results__badge results__badge--soft"><?php esc_html_e( 'au 25 octobre', 'arvator' ); ?></span>
								<?php endif; ?>
							</th>
							<td><?php echo esc_html( arvator_fmt_eur( $y['soldeDebut'] ) . ' → ' . arvator_fmt_eur( $y['soldeFin'] ) ); ?></td>
							<td><span class="results__amount"><?php echo esc_html( arvator_fmt_pct1( $y['gainPct'], true, true ) ); ?></span><span class="results__pct"><?php echo esc_html( arvator_fmt_eur_signed( $y['gainEur'] ) ); ?></span></td>
							<td class="is-neg"><span class="results__amount"><?php echo esc_html( arvator_fmt_pct1( $y['pireBaissePct'], true, true ) ); ?></span><span class="results__pct"><?php echo esc_html( arvator_fmt_eur_signed( $y['pireBaisseEur'] ) ); ?></span></td>
							<td><?php echo esc_html( arvator_fmt_span( $y['debutBaisse'], $y['finBaisse'] ) ); ?><?php echo ! empty( $y['enCours'] ) ? ' ' . esc_html__( '(baisse en cours au 25/10/2026)', 'arvator' ) : ''; ?></td>
							<td><?php echo ! empty( $y['retourPlusHaut'] ) ? esc_html( arvator_fmt_date( $y['retourPlusHaut'] ) ) : esc_html__( 'pas encore', 'arvator' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<p class="results__note muted"><?php esc_html_e( 'Les quelques euros d’écart entre la fin d’une année et le début de la suivante viennent des positions encore ouvertes le 31 décembre.', 'arvator' ); ?></p>
	<?php endif; ?>
</div>

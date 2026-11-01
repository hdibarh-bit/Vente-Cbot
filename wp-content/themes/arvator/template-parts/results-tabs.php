<?php
/**
 * Tableau de performance : un onglet par niveau de risque (1 %, 2 %, 3 %).
 * Colonnes : capital de départ, solde en fin de chaque année (avec le gain de l'année), total 3 ans, pire baisse.
 * Chiffres : config/resultats-backtest.json (source unique). Sans JavaScript, les trois tableaux restent visibles les uns sous les autres.
 *
 * @var array<string, mixed> $args  « data » : résultats.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$data   = $args['data'] ?? array();
$tables = is_array( $data['tableaux'] ?? null ) ? $data['tableaux'] : array();
if ( ! $tables ) {
	return;
}
$years    = is_array( $data['colonnesAnnees'] ?? null ) ? $data['colonnesAnnees'] : array();
$advised  = (int) ( $data['capitalConseille']['montant'] ?? 5000 );
$ref_cap  = (int) ( $data['reference']['capital'] ?? 5000 );
$risks    = array(
	1 => $tables['r1'] ?? array(),
	2 => $tables['r2'] ?? array(),
	3 => $tables['r3'] ?? array(),
);
$risks    = array_filter( $risks );
$first    = (int) array_key_first( $risks );
?>
<div class="results" data-tabs>
	<div class="results__tabs" role="tablist" aria-label="<?php esc_attr_e( 'Niveau de risque par trade', 'arvator' ); ?>">
		<?php foreach ( $risks as $pct => $rows ) : ?>
			<?php $is_on = $pct === $first; ?>
			<button class="results__tab" type="button" role="tab" id="tab-risque-<?php echo esc_attr( (string) $pct ); ?>"
				aria-controls="panneau-risque-<?php echo esc_attr( (string) $pct ); ?>"
				aria-selected="<?php echo $is_on ? 'true' : 'false'; ?>" tabindex="<?php echo $is_on ? '0' : '-1'; ?>">
				<?php echo esc_html( /* translators: %d: niveau de risque en pourcentage. */ sprintf( __( 'Risque %d %%', 'arvator' ), $pct ) ); ?>
				<?php if ( 1 === $pct ) : ?>
					<span class="results__badge"><?php esc_html_e( 'Livré par défaut', 'arvator' ); ?></span>
				<?php endif; ?>
			</button>
		<?php endforeach; ?>
	</div>

	<?php foreach ( $risks as $pct => $rows ) : ?>
		<div class="results__panel" role="tabpanel" id="panneau-risque-<?php echo esc_attr( (string) $pct ); ?>"
			aria-labelledby="tab-risque-<?php echo esc_attr( (string) $pct ); ?>" tabindex="0">
			<h3 class="results__title">
				<?php echo esc_html( /* translators: %d: niveau de risque en pourcentage. */ sprintf( __( 'Risque %d %% par trade', 'arvator' ), $pct ) ); ?>
				<?php if ( 1 === $pct ) : ?>
					<span class="muted"> — <?php esc_html_e( 'réglage livré avec le robot', 'arvator' ); ?></span>
				<?php endif; ?>
			</h3>
			<div class="table-wrap" role="region" aria-label="<?php echo esc_attr( /* translators: %d: niveau de risque en pourcentage. */ sprintf( __( 'Résultats backtestés au risque %d %%', 'arvator' ), $pct ) ); ?>" tabindex="0">
				<table class="results__table">
					<caption class="screen-reader-text"><?php echo esc_html( /* translators: %d: niveau de risque en pourcentage. */ sprintf( __( 'Solde en fin de chaque année, total sur 3 ans et pire baisse par capital de départ, risque %d %%', 'arvator' ), $pct ) ); ?></caption>
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Capital de départ', 'arvator' ); ?></th>
							<?php foreach ( $years as $label ) : ?>
								<th scope="col"><?php echo esc_html( (string) $label ); ?></th>
							<?php endforeach; ?>
							<th scope="col"><?php esc_html_e( 'Total 3 ans', 'arvator' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Pire baisse', 'arvator' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<?php $cap = (int) $row['capital']; ?>
							<tr<?php echo $ref_cap === $cap ? ' class="is-advised"' : ''; ?>>
								<th scope="row">
									<?php echo esc_html( arvator_fmt_capital( $cap ) ); ?>
									<?php if ( $ref_cap === $cap || $advised === $cap ) : ?>
										<span class="results__badges">
											<?php if ( $ref_cap === $cap ) : ?>
												<span class="results__badge results__badge--soft"><?php esc_html_e( 'Référence', 'arvator' ); ?></span>
											<?php endif; ?>
											<?php if ( $advised === $cap ) : ?>
												<span class="results__badge results__badge--soft"><?php esc_html_e( 'Minimum conseillé', 'arvator' ); ?></span>
											<?php endif; ?>
										</span>
									<?php endif; ?>
								</th>
								<?php foreach ( (array) $row['annees'] as $y ) : ?>
									<td><span class="results__amount"><?php echo esc_html( arvator_fmt_eur( $y['solde'] ) ); ?></span><span class="results__pct"><?php echo esc_html( '(' . arvator_fmt_pct( $y['pct'] ) . ')' ); ?></span></td>
								<?php endforeach; ?>
								<td><span class="results__amount"><?php echo esc_html( arvator_fmt_pct( $row['totalPct'] ) ); ?></span></td>
								<td class="is-neg">
									<span class="results__amount"><?php echo esc_html( arvator_fmt_pct1( $row['pireBaissePct'], true, true ) . ' (' . arvator_fmt_eur_signed( $row['pireBaisseEur'] ) . ')' ); ?></span>
									<span class="results__pct"><?php echo esc_html( arvator_fmt_span( (string) $row['debutBaisse'], (string) $row['finBaisse'] ) ); ?></span>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	<?php endforeach; ?>

	<p class="results__note muted"><?php echo esc_html( arvator_results_source() ); ?></p>
	<p class="results__note muted"><?php esc_html_e( 'Le pourcentage entre parenthèses est le gain de l’année. Plus le risque est élevé, plus les gains et les baisses sont grands. Le robot démarre à 1 % : c’est le réglage conseillé.', 'arvator' ); ?></p>
	<p class="results__note muted"><?php echo esc_html( arvator_results_disclaimer() ); ?></p>
</div>

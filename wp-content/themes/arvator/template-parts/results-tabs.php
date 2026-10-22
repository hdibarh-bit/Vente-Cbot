<?php
/**
 * Résultats simulés : un onglet par niveau de risque (1 %, 2 %, 3 %).
 * Sans JavaScript, les trois tableaux restent visibles les uns sous les autres.
 *
 * @var array<string, mixed> $args  « data » : résultats ; « compact » : bool
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$data  = $args['data'] ?? array();
$risks = is_array( $data['risques'] ?? null ) ? $data['risques'] : array();
if ( ! $risks ) {
	return;
}
$max_risk = (int) ( $data['livraison']['risqueMaxPct'] ?? 3 );
?>
<div class="results" data-tabs>
	<div class="results__tabs" role="tablist" aria-label="<?php esc_attr_e( 'Niveau de risque par trade', 'arvator' ); ?>">
		<?php foreach ( $risks as $i => $risk ) : ?>
			<?php
			$pct   = (int) $risk['risquePct'];
			$is_on = 0 === $i;
			?>
			<button class="results__tab" type="button" role="tab" id="tab-risque-<?php echo esc_attr( (string) $pct ); ?>"
				aria-controls="panneau-risque-<?php echo esc_attr( (string) $pct ); ?>"
				aria-selected="<?php echo $is_on ? 'true' : 'false'; ?>" tabindex="<?php echo $is_on ? '0' : '-1'; ?>">
				<?php echo esc_html( sprintf( 'Risque %d %%', $pct ) ); ?>
				<?php if ( ! empty( $risk['livreParDefaut'] ) ) : ?>
					<span class="results__badge"><?php esc_html_e( 'livré par défaut', 'arvator' ); ?></span>
				<?php endif; ?>
			</button>
		<?php endforeach; ?>
	</div>

	<?php foreach ( $risks as $i => $risk ) : ?>
		<?php $pct = (int) $risk['risquePct']; ?>
		<div class="results__panel" role="tabpanel" id="panneau-risque-<?php echo esc_attr( (string) $pct ); ?>"
			aria-labelledby="tab-risque-<?php echo esc_attr( (string) $pct ); ?>" tabindex="0">
			<h3 class="results__title">
				<?php echo esc_html( sprintf( 'Performances simulées au risque %d %% par trade', $pct ) ); ?>
				<?php if ( 1 === $pct ) : ?>
					<span class="muted"> — <?php esc_html_e( 'réglage livré avec le robot', 'arvator' ); ?></span>
				<?php endif; ?>
			</h3>
			<div class="table-wrap" role="region" aria-label="<?php echo esc_attr( sprintf( 'Résultats simulés au risque %d %%', $pct ) ); ?>" tabindex="0">
				<table class="results__table">
					<caption class="screen-reader-text"><?php echo esc_html( sprintf( 'Solde du compte en fin de période (en euros et en pourcentage) et baisses, risque %d %%', $pct ) ); ?></caption>
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Capital de départ', 'arvator' ); ?></th>
							<th scope="col">2024</th>
							<th scope="col">2025</th>
							<th scope="col"><?php esc_html_e( '2026 (au 7 oct.)', 'arvator' ); ?></th>
							<th scope="col"><?php esc_html_e( '2024 → 2026 enchaînés', 'arvator' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Pire baisse', 'arvator' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Solde le plus bas', 'arvator' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $risk['lignes'] as $row ) : ?>
							<tr<?php echo 5000 === (int) $row['capital'] ? ' class="is-advised"' : ''; ?>>
								<th scope="row">
									<?php echo esc_html( arvator_fmt_capital( $row['capital'] ) ); ?>
									<?php if ( 5000 === (int) $row['capital'] ) : ?>
										<span class="results__badge results__badge--soft"><?php esc_html_e( 'conseillé', 'arvator' ); ?></span>
									<?php endif; ?>
								</th>
								<?php foreach ( array( '2024', '2025', '2026_au_7_oct' ) as $year ) : ?>
									<?php arvator_result_cell( $row['parAnnee'][ $year ] ); ?>
								<?php endforeach; ?>
								<?php arvator_result_cell( $row['troisAns'] ); ?>
								<td class="is-neg"><?php echo esc_html( arvator_fmt_pct( $row['troisAns']['pireBaissePct'] ) ); ?></td>
								<td><?php echo esc_html( arvator_fmt_eur( $row['troisAns']['plusBasDuCompte'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	<?php endforeach; ?>

	<p class="results__note muted">
		Performances simulées (backtest du 1<sup>er</sup> janvier 2024 au 7 octobre 2026), sans retrait. Chaque case donne le solde simulé du compte en fin de période, en euros, et l’évolution en pourcentage par rapport au capital de départ.
		Chaque année est simulée seule, en repartant du capital de départ ; la colonne « 2024 → 2026 enchaînés » montre le solde si l’on avait laissé tourner le robot sur toute la période, sans retrait.
		« Pire baisse » et « solde le plus bas » portent sur toute la période 2024 → 2026 (soldes de fin de mois).
		Le robot est livré réglé à 1 % de risque par trade ; vous pouvez le monter jusqu’à <?php echo esc_html( (string) $max_risk ); ?> %, jamais au-delà.
		Ces chiffres sont des simulations : ils ne préjugent pas des résultats futurs, qui peuvent être très positifs, nuls ou négatifs.
	</p>
</div>

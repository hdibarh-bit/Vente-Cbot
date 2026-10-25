<?php
/**
 * Résultats simulés par capital de départ : un onglet par niveau de risque (1 %, 2 %, 3 %).
 * Un seul compte suivi du 01/01/2024 au 25/10/2026, frais d'un courtier inclus (pas de colonnes par année qui repartent à zéro).
 * Sans JavaScript, les trois tableaux restent visibles les uns sous les autres.
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
$max_risk = (int) ( $data['livraison']['risqueMaxPct'] ?? 3 );
$advised  = (int) ( $data['capitalConseille']['montant'] ?? 2000 );
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
					<span class="results__badge"><?php esc_html_e( 'livré par défaut', 'arvator' ); ?></span>
				<?php endif; ?>
			</button>
		<?php endforeach; ?>
	</div>

	<?php foreach ( $risks as $pct => $rows ) : ?>
		<div class="results__panel" role="tabpanel" id="panneau-risque-<?php echo esc_attr( (string) $pct ); ?>"
			aria-labelledby="tab-risque-<?php echo esc_attr( (string) $pct ); ?>" tabindex="0">
			<h3 class="results__title">
				<?php echo esc_html( /* translators: %d: niveau de risque en pourcentage. */ sprintf( __( 'Performances simulées au risque %d %% par trade', 'arvator' ), $pct ) ); ?>
				<?php if ( 1 === $pct ) : ?>
					<span class="muted"> — <?php esc_html_e( 'réglage livré avec le robot', 'arvator' ); ?></span>
				<?php endif; ?>
			</h3>
			<div class="table-wrap" role="region" aria-label="<?php echo esc_attr( /* translators: %d: niveau de risque en pourcentage. */ sprintf( __( 'Résultats simulés au risque %d %%', 'arvator' ), $pct ) ); ?>" tabindex="0">
				<table class="results__table">
					<caption class="screen-reader-text"><?php echo esc_html( /* translators: %d: niveau de risque en pourcentage. */ sprintf( __( 'Solde final, gain, pire baisse et trades refusés par capital de départ, risque %d %%', 'arvator' ), $pct ) ); ?></caption>
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Capital de départ', 'arvator' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Solde final', 'arvator' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Gain', 'arvator' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Pire baisse', 'arvator' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Trades refusés', 'arvator' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<?php $cap = (int) $row['capital']; ?>
							<tr<?php echo $ref_cap === $cap ? ' class="is-advised"' : ''; ?>>
								<th scope="row">
									<?php echo esc_html( arvator_fmt_capital( $cap ) ); ?>
									<?php if ( $advised === $cap ) : ?>
										<span class="results__badge results__badge--soft"><?php esc_html_e( 'minimum conseillé', 'arvator' ); ?></span>
									<?php elseif ( $ref_cap === $cap ) : ?>
										<span class="results__badge results__badge--soft"><?php esc_html_e( 'référence', 'arvator' ); ?></span>
									<?php endif; ?>
								</th>
								<td><span class="results__amount"><?php echo esc_html( arvator_fmt_eur( $row['soldeFinal'] ) ); ?></span></td>
								<td><span class="results__amount"><?php echo esc_html( arvator_fmt_pct1( $row['gainPct'] ) ); ?></span></td>
								<td class="is-neg">
									<span class="results__amount"><?php echo esc_html( arvator_fmt_pct1( $row['pireBaissePct'], true, true ) . ' (' . arvator_fmt_eur_signed( $row['pireBaisseEur'] ) . ')' ); ?></span>
									<span class="results__pct"><?php echo esc_html( arvator_fmt_span( (string) $row['debutBaisse'], (string) $row['finBaisse'] ) ); ?></span>
								</td>
								<td><?php echo esc_html( arvator_fmt_int( $row['tradesRefuses'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php if ( 1 === $pct ) : ?>
				<p class="results__note muted"><?php esc_html_e( 'À partir de 2 000 €, la pire baisse va du 2 septembre au 21 octobre 2026.', 'arvator' ); ?></p>
			<?php else : ?>
				<p class="results__note muted"><?php /* translators: %d: niveau de risque. */ echo esc_html( sprintf( __( 'Au risque %d %%, la pire baisse a lieu du 2 septembre au 21 octobre 2026.', 'arvator' ), $pct ) ); ?></p>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>

	<p class="results__note muted"><strong><?php echo esc_html( (string) ( $data['noteSmallCapital'] ?? '' ) ); ?></strong></p>

	<p class="results__note muted">
		<?php
		/* translators: %d: risque maximum autorisé, en pourcentage. */
		echo esc_html( sprintf( __( 'Le robot est livré réglé à 1 %% de risque par trade ; vous pouvez le monter jusqu’à %d %%, jamais au-delà.', 'arvator' ), $max_risk ) );
		?>

		<?php esc_html_e( 'Ces chiffres sont des performances simulées : ils ne garantissent pas les résultats futurs, qui peuvent être très positifs, nuls ou négatifs.', 'arvator' ); ?>
	</p>
</div>

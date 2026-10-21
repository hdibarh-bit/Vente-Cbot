<?php
/**
 * Tarifs et réglages publics (prix, essai, risque livré).
 *
 * Valeurs de data/tarifs.php, généré par bin/sync-donnees.php à partir de config/reglages.json.
 * Le plugin « Arvator Core » pourra les remplacer par des valeurs modifiables dans le tableau de bord,
 * via le filtre « arvator_settings ».
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @return array<string, mixed>
 */
function arvator_settings(): array {
	static $data = null;

	if ( null === $data ) {
		$file = get_theme_file_path( 'data/tarifs.php' );
		$data = is_readable( $file ) ? include $file : array();
		$data = is_array( $data ) ? $data : array();
		$data = apply_filters( 'arvator_settings', $data );
	}

	return is_array( $data ) ? $data : array();
}

/**
 * Chiffres de tarification dérivés des prix, pour l'affichage.
 *
 * @return array<string, float>
 */
function arvator_pricing(): array {
	$p = arvator_settings()['prix'] ?? array();

	$achat   = (float) ( $p['achatCbot'] ?? 0 );
	$mensuel = (float) ( $p['abonnementMensuel'] ?? 0 );
	$annuel  = (float) ( $p['abonnementAnnuelTotal'] ?? 0 );

	return array(
		'achat'              => $achat,
		'mensuel'            => $mensuel,
		'annuel_total'       => $annuel,
		'annuel_par_mois'    => (float) ( $p['abonnementAnnuelParMois'] ?? 0 ),
		'economie'           => (float) ( $p['economieAnnuelle'] ?? 0 ),
		'premier_paiement_m' => $achat + $mensuel,
		'premier_paiement_a' => $achat + $annuel,
		'annee1_mensuel'     => $achat + 12 * $mensuel,
		'annee1_annuel'      => $achat + $annuel,
	);
}

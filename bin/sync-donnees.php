<?php
/**
 * Génère les fichiers de données publiques du thème à partir de config/*.json.
 *
 * Seuls les champs destinés à être affichés sur le site sont copiés : aucune note interne,
 * aucun réglage de licence ou de parrainage. Les fichiers générés sont du PHP (protégé par
 * « ABSPATH ») : un visiteur qui en connaît l'adresse ne voit rien.
 *
 * Usage : php bin/sync-donnees.php
 */

declare(strict_types=1);

$root  = dirname( __DIR__ );
$theme = $root . '/wp-content/themes/arvator/data';

/** @return array<string,mixed> */
function lire( string $file ): array {
	$json = file_get_contents( $file );
	$data = json_decode( (string) $json, true, 512, JSON_THROW_ON_ERROR );
	return is_array( $data ) ? $data : array();
}

function ecrire( string $file, string $label, array $data ): void {
	$code  = "<?php\n";
	$code .= "// $label — fichier généré par bin/sync-donnees.php. Ne pas modifier à la main.\n";
	$code .= "if ( ! defined( 'ABSPATH' ) ) {\n\texit;\n}\n\n";
	$code .= 'return ' . var_export( $data, true ) . ";\n";
	file_put_contents( $file, $code );
	echo "Écrit : $file\n";
}

// Résultats de backtest.
$r      = lire( $root . '/config/resultats-backtest.json' );
$risques = array();
foreach ( $r['risques'] as $risque ) {
	$lignes = array();
	foreach ( $risque['lignes'] as $l ) {
		$lignes[] = array(
			'capital'       => $l['capital'],
			'troisAns'      => $l['troisAns'],
			'parAnnee'      => $l['parAnnee'],
			'tradesRefuses' => $l['tradesRefuses'],
			'tradesPris'    => $l['tradesPris'],
			'pireMois'      => $l['pireMois'],
		);
	}
	$risques[] = array(
		'risquePct'      => $risque['risquePct'],
		'livreParDefaut' => $risque['livreParDefaut'],
		'lignes'         => $lignes,
	);
}
ecrire(
	$theme . '/resultats.php',
	'Résultats de backtest (performances simulées)',
	array(
		'periode'        => $r['periode'],
		'devise'         => $r['devise'],
		'livraison'      => $r['livraison'],
		'noteMethode'    => $r['noteMethode'],
		'capitaux'       => $r['capitaux'],
		'risques'        => $risques,
		'limitesCommunes' => $r['limitesCommunes'],
		'capitalConseille' => array(
			'montant'     => $r['capitalConseille']['montant'],
			'obligatoire' => $r['capitalConseille']['obligatoire'],
		),
	)
);

// Tarifs et réglages publics.
$s = lire( $root . '/config/reglages.json' );
ecrire(
	$theme . '/tarifs.php',
	'Tarifs et réglages publics',
	array(
		'produit'         => array( 'nom' => $s['produit']['nom'] ),
		'prix'            => $s['prix'],
		'capitalConseille' => $s['capitalConseille'],
		'risque'          => $s['risque'],
		'graceJours'      => $s['licence']['graceJours'],
		'essaiJours'      => $s['licence']['essaiJours'],
	)
);

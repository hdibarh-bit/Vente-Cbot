<?php
/**
 * Résultats de backtest (performances simulées).
 *
 * Les chiffres viennent de data/resultats.php, généré par bin/sync-donnees.php à partir de config/resultats-backtest.json
 * (seuls les champs publics sont copiés).
 * Le plugin « Arvator Core » pourra les remplacer par des valeurs modifiables dans le tableau de bord,
 * via le filtre « arvator_results ».
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @return array<string, mixed>
 */
function arvator_results(): array {
	static $data = null;

	if ( null === $data ) {
		$file = get_theme_file_path( 'data/resultats.php' );
		$data = is_readable( $file ) ? include $file : array();
		$data = is_array( $data ) ? $data : array();
		$data = apply_filters( 'arvator_results', $data );
	}

	return is_array( $data ) ? $data : array();
}

/** Nombre entier à la française : « 5 300 ». */
function arvator_fmt_int( int|float $n ): string {
	return number_format( (float) $n, 0, ',', "\u{202F}" );
}

/** Montant en euros : « 5 300 € ». */
function arvator_fmt_eur( int|float $n ): string {
	return arvator_fmt_int( $n ) . "\u{00A0}€";
}

/** Pourcentage signé : « +80 % », « −14 % », « 0 % ». */
function arvator_fmt_pct( int|float $n, bool $signed = true ): string {
	$abs = arvator_fmt_int( abs( $n ) );
	if ( 0.0 === (float) $n ) {
		return "0\u{00A0}%";
	}
	if ( $n < 0 ) {
		return "\u{2212}" . $abs . "\u{00A0}%";
	}
	return ( $signed ? '+' : '' ) . $abs . "\u{00A0}%";
}

/** Prix : décimales seulement si nécessaire (« 1 500 € », « 39,90 € »). */
function arvator_fmt_price( int|float $n ): string {
	$decimals = ( abs( $n - round( $n ) ) < 0.005 ) ? 0 : 2;
	return number_format( (float) $n, $decimals, ',', "\u{202F}" ) . "\u{00A0}€";
}

/** Libellé de capital : « 5 000 € ». */
function arvator_fmt_capital( int|float $n ): string {
	return arvator_fmt_eur( $n );
}

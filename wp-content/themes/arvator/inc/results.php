<?php
/**
 * Résultats backtestés (cTrader, 2024-2026, frais du courtier inclus).
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

/** Pourcentage avec une décimale si besoin, signé : « +5,5 % », « −16,7 % », « +81 % ». */
function arvator_fmt_pct1( int|float $n, bool $signed = true, bool $force = false ): string {
	$decimals = ( ! $force && abs( $n - round( $n ) ) < 0.05 ) ? 0 : 1;
	$abs      = number_format( abs( (float) $n ), $decimals, ',', "\u{202F}" );
	if ( 0.0 === (float) $n ) {
		return "0\u{00A0}%";
	}
	if ( $n < 0 ) {
		return "\u{2212}" . $abs . "\u{00A0}%";
	}
	return ( $signed ? '+' : '' ) . $abs . "\u{00A0}%";
}

/** Montant signé en euros : « +3 036 € », « −1 022 € ». */
function arvator_fmt_eur_signed( int|float $n ): string {
	$abs = arvator_fmt_eur( abs( $n ) );
	if ( $n < 0 ) {
		return "\u{2212}" . $abs;
	}
	return ( $n > 0 ? '+' : '' ) . $abs;
}

/** Date « 2026-09-02 » → « 2 septembre 2026 » (« 1er » le premier du mois). */
function arvator_fmt_date( string $iso, bool $with_year = true ): string {
	$months = array( '', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre' );
	$parts  = explode( '-', $iso );
	if ( 3 !== count( $parts ) ) {
		return '';
	}
	$day = (int) $parts[2];
	$out = ( 1 === $day ? '1er' : (string) $day ) . ' ' . ( $months[ (int) $parts[1] ] ?? '' );
	return $with_year ? $out . ' ' . $parts[0] : $out;
}

/** Date courte « 2026-02-03 » → « 3 fév. 2026 » (« 1er » le premier du mois). */
function arvator_fmt_date_short( string $iso ): string {
	$months = array( '', 'janv.', 'fév.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.' );
	$parts  = explode( '-', $iso );
	if ( 3 !== count( $parts ) ) {
		return '';
	}
	$day = (int) $parts[2];
	return ( 1 === $day ? '1er' : (string) $day ) . "\u{00A0}" . ( $months[ (int) $parts[1] ] ?? '' ) . "\u{00A0}" . $parts[0];
}

/** Période de baisse : « 3 fév. 2026 → 13 mars 2026 ». */
function arvator_fmt_span( string $from, string $to ): string {
	if ( '' === $from || '' === $to ) {
		return '';
	}
	return arvator_fmt_date_short( $from ) . ' → ' . arvator_fmt_date_short( $to );
}

/** Mention de risque unique, courte, sous les tableaux de résultats et dans le pied de page (demande de Hamid, 01/11/2026). */
function arvator_results_disclaimer(): string {
	return __( 'Résultats backtestés sur des données passées, frais du courtier inclus. Les performances passées ne préjugent pas des performances futures. Le trading comporte un risque de perte en capital.', 'arvator' );
}

/** Source des chiffres, sous chaque tableau. */
function arvator_results_source(): string {
	return __( 'Backtests réalisés sur cTrader, du 1er janvier 2024 au 31 octobre 2026, frais du courtier inclus.', 'arvator' );
}

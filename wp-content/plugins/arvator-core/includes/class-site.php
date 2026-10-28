<?php
/**
 * Identité du site : adresse email officielle, domaine officiel (décisions de Hamid du 22/10/2026) et adresse postale
 * de l'éditeur (donnée par Hamid le 22/10/2026).
 *
 * Source unique : tout le plugin (pages, formulaire de contact, emails, alertes) lit ces valeurs ici.
 * Pour en changer une, modifier seulement sa ligne, puis monter le « rev » des pages
 * qui les affichent (includes/content/pages.php).
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Arvator_Site {

	public const EMAIL  = 'support@arvator.com';
	public const DOMAIN = 'arvator.com';
	public const POSTAL = '21 rue Taffin, 59970 Fresnes-sur-Escaut, France';

	public static function url(): string {
		return 'https://' . self::DOMAIN;
	}

	public static function init(): void {
		add_shortcode( 'arvator_offre_lancement', array( self::class, 'launch_offer_note' ) );
	}

	/**
	 * [arvator_offre_lancement] : mention de l'offre de lancement à côté du prix du robot dans la FAQ et les CGV,
	 * par exemple « (prix de lancement ; il passera à 2 500 € le 1er janvier 2027) ». Vide une fois la date passée,
	 * ou si le thème Arvator (qui porte le réglage de l'offre) n'est pas actif.
	 */
	public static function launch_offer_note(): string {
		if ( ! function_exists( 'arvator_launch_offer' ) || ! function_exists( 'arvator_launch_offer_date' ) || ! function_exists( 'arvator_fmt_price' ) ) {
			return '';
		}
		$o = arvator_launch_offer();
		if ( null === $o ) {
			return '';
		}
		/* translators: 1: prix futur du robot, 2: date du changement. */
		return esc_html( sprintf( __( ' (prix de lancement ; il passera à %1$s le %2$s)', 'arvator-core' ), arvator_fmt_price( $o['apres'] ), arvator_launch_offer_date( $o['date'] ) ) );
	}

	/** Signature placée à la fin de chaque email envoyé par le site. */
	public static function signature(): string {
		return 'Arvator · ' . self::url() . ' · ' . self::EMAIL;
	}
}

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

	/** Signature placée à la fin de chaque email envoyé par le site. */
	public static function signature(): string {
		return 'Arvator · ' . self::url() . ' · ' . self::EMAIL;
	}
}

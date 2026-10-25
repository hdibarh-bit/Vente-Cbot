<?php
/**
 * Plugin Name:       Arvator Core
 * Plugin URI:        https://arvator.com
 * Description:       Cœur du site Arvator : sécurité renforcée, pages du site (FAQ, À propos, Contact, Risques, textes légaux), formulaire de contact, espace membre sécurisé (compte client, achats, abonnement, licence, téléchargement du robot, données personnelles). Paiement Stripe (achat + abonnement) et webhooks. Parrainage (lien de recommandation, récompenses, virements). Licence : liaison avec le workflow n8n (paiement, résiliation, essai). Codes promo (robot, abonnement, mois offerts). Accueillera ensuite l'administration complète.
 * Version:           0.11.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Hamid
 * License:           Proprietary
 * Text Domain:       arvator-core
 * Domain Path:       /languages
 *
 * Règle : ce plugin porte la logique du site, le thème ne porte que l'affichage.
 * Aucun secret ici : les clés vont dans wp-config.php.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ARVATOR_CORE_VERSION', '0.11.0' );
define( 'ARVATOR_CORE_DIR', plugin_dir_path( __FILE__ ) );

foreach (
	array(
		'class-site',
		'class-hardening',
		'class-login-limiter',
		'class-support',
		'class-repo',
		'class-roles',
		'class-pages',
		'class-install',
		'class-mailer',
		'class-stripe',
		'class-qr',
		'class-bank',
		'class-trial',
		'class-referral',
		'class-license',
		'class-promo',
		'class-checkout',
		'class-webhook',
		'class-contact',
		'class-member',
		'class-downloads',
		'class-privacy',
		'class-admin',
		'class-referral-admin',
		'class-license-admin',
		'class-promo-admin',
		'class-bank-admin',
		'class-brokers',
		'class-author',
	) as $arvator_file
) {
	require_once ARVATOR_CORE_DIR . 'includes/' . $arvator_file . '.php';
}
unset( $arvator_file );

Arvator_Hardening::init();
Arvator_Login_Limiter::init();
Arvator_Install::init();
Arvator_Roles::init();
Arvator_Mailer::init();
Arvator_Contact::init();
Arvator_Trial::init();
Arvator_Referral::init();
Arvator_License::init();
Arvator_Brokers::init();
Arvator_Author::init();
Arvator_Bank::init();
Arvator_Checkout::init();
Arvator_Webhook::init();
Arvator_Member::init();
Arvator_Downloads::init();
Arvator_Privacy::init();

if ( is_admin() ) {
	Arvator_Admin::init();
	Arvator_Referral_Admin::init();
	Arvator_License_Admin::init();
	Arvator_Promo_Admin::init();
	Arvator_Bank_Admin::init();
}

// Traductions (dossier languages/, ex. arvator-core-en_US.mo) : le français reste la langue d'origine du site.
add_action(
	'init',
	static function (): void {
		load_plugin_textdomain( 'arvator-core', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	},
	1
);

register_activation_hook( __FILE__, array( 'Arvator_Install', 'activate' ) );

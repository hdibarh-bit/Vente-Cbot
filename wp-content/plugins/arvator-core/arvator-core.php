<?php
/**
 * Plugin Name:       Arvator Core
 * Plugin URI:        https://bot.cosmartis.com
 * Description:       Cœur du site Arvator : sécurité renforcée, pages du site (FAQ, À propos, Contact, Risques, textes légaux), formulaire de contact, espace membre sécurisé (compte client, achats, abonnement, licence, téléchargement du robot, données personnelles). Paiement Stripe (achat + abonnement) et webhooks ; accueillera ensuite la licence, le parrainage et l'administration.
 * Version:           0.4.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Hamid
 * License:           Proprietary
 * Text Domain:       arvator-core
 *
 * Règle : ce plugin porte la logique du site, le thème ne porte que l'affichage.
 * Aucun secret ici : les clés vont dans wp-config.php.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ARVATOR_CORE_VERSION', '0.4.0' );
define( 'ARVATOR_CORE_DIR', plugin_dir_path( __FILE__ ) );

foreach (
	array(
		'class-hardening',
		'class-login-limiter',
		'class-support',
		'class-repo',
		'class-roles',
		'class-pages',
		'class-install',
		'class-mailer',
		'class-stripe',
		'class-trial',
		'class-checkout',
		'class-webhook',
		'class-contact',
		'class-member',
		'class-downloads',
		'class-privacy',
		'class-admin',
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
Arvator_Checkout::init();
Arvator_Webhook::init();
Arvator_Member::init();
Arvator_Downloads::init();
Arvator_Privacy::init();

if ( is_admin() ) {
	Arvator_Admin::init();
}

register_activation_hook( __FILE__, array( 'Arvator_Install', 'activate' ) );

<?php
/**
 * Plugin Name:       Arvator Core
 * Plugin URI:        https://bot.cosmartis.com
 * Description:       Cœur du site Arvator : sécurité renforcée dès l'activation (connexions limitées, XML-RPC et éditeur de fichiers désactivés, énumération des comptes bloquée, en-têtes de sécurité). Accueillera plus tard les licences, l'espace client, le parrainage et l'administration.
 * Version:           0.1.0
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

define( 'ARVATOR_CORE_VERSION', '0.1.0' );
define( 'ARVATOR_CORE_DIR', plugin_dir_path( __FILE__ ) );

require_once ARVATOR_CORE_DIR . 'includes/class-hardening.php';
require_once ARVATOR_CORE_DIR . 'includes/class-login-limiter.php';

Arvator_Hardening::init();
Arvator_Login_Limiter::init();

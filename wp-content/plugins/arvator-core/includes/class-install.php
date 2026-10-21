<?php
/**
 * Installation et mises à jour : tables, rôle « client », pages du site.
 *
 * Se déclenche à l'activation ET quand le numéro de version du plugin change
 * (réinstaller le zip par-dessus ne déclenche pas l'« activation » de WordPress).
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Arvator_Install {

	public static function init(): void {
		add_action( 'init', array( self::class, 'maybe_upgrade' ), 5 );
	}

	public static function activate(): void {
		self::upgrade();
	}

	public static function maybe_upgrade(): void {
		if ( get_option( 'arvator_core_version' ) === ARVATOR_CORE_VERSION ) {
			return;
		}
		self::upgrade();
	}

	private static function upgrade(): void {
		self::create_tables();
		Arvator_Roles::register_role();
		Arvator_Downloads::ensure_private_dir();
		Arvator_Pages::create_missing();
		update_option( 'arvator_core_version', ARVATOR_CORE_VERSION, false );
	}

	private static function create_tables(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$p       = $wpdb->prefix . 'arvator_';

		// Achats (un enregistrement par paiement validé).
		dbDelta(
			"CREATE TABLE {$p}orders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  stripe_session_id VARCHAR(190) NULL,
  stripe_payment_intent VARCHAR(190) NULL,
  stripe_invoice_id VARCHAR(190) NULL,
  stripe_customer_id VARCHAR(190) NULL,
  product VARCHAR(40) NOT NULL,
  amount_cents INT UNSIGNED NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'eur',
  status VARCHAR(20) NOT NULL DEFAULT 'paid',
  is_test TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY stripe_session_id (stripe_session_id),
  KEY user_id (user_id),
  KEY stripe_invoice_id (stripe_invoice_id),
  KEY stripe_customer_id (stripe_customer_id)
) $charset;"
		);

		// Abonnements.
		dbDelta(
			"CREATE TABLE {$p}subscriptions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  stripe_subscription_id VARCHAR(190) NULL,
  stripe_customer_id VARCHAR(190) NULL,
  plan VARCHAR(10) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  current_period_end DATETIME NULL,
  cancel_at_period_end TINYINT(1) NOT NULL DEFAULT 0,
  is_test TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY stripe_subscription_id (stripe_subscription_id),
  KEY user_id (user_id)
) $charset;"
		);

		// Licences (la clé est signée ailleurs : ici on ne fait que la stocker et l'afficher).
		dbDelta(
			"CREATE TABLE {$p}licenses (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  subscription_id BIGINT UNSIGNED NULL,
  license_key TEXT NOT NULL,
  plan VARCHAR(10) NOT NULL,
  account_number VARCHAR(40) NULL,
  demo_only TINYINT(1) NOT NULL DEFAULT 0,
  expires_at DATETIME NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  is_test TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY  (id),
  KEY user_id (user_id)
) $charset;"
		);

		// Journal des événements (téléchargements, webhooks, etc.). external_id unique = traitement idempotent.
		dbDelta(
			"CREATE TABLE {$p}events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  type VARCHAR(40) NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  external_id VARCHAR(190) NULL,
  payload LONGTEXT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY external_id (external_id),
  KEY type (type),
  KEY user_id (user_id)
) $charset;"
		);
	}
}

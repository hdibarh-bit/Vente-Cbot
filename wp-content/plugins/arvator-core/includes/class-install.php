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
  card_fingerprint VARCHAR(64) NULL,
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

		// Demandes d'essai gratuit (une par email et par compte démo).
		dbDelta(
			"CREATE TABLE {$p}trials (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(190) NOT NULL,
  account_number VARCHAR(40) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'requested',
  waiver_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY email (email),
  UNIQUE KEY account_number (account_number)
) $charset;"
		);

		// Parrains : code de recommandation, coordonnées de virement (IBAN chiffré).
		dbDelta(
			"CREATE TABLE {$p}referrers (
  user_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(16) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  holder_name VARCHAR(120) NULL,
  iban_enc TEXT NULL,
  iban_last4 VARCHAR(8) NULL,
  entity_type VARCHAR(20) NULL,
  siret VARCHAR(14) NULL,
  terms_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY  (user_id),
  UNIQUE KEY code (code)
) $charset;"
		);

		// Filleuls : un filleul n'a qu'un parrain, fixé à son premier achat.
		dbDelta(
			"CREATE TABLE {$p}referrals (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  referee_user_id BIGINT UNSIGNED NOT NULL,
  referrer_user_id BIGINT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'ok',
  note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY referee_user_id (referee_user_id),
  KEY referrer_user_id (referrer_user_id)
) $charset;"
		);

		// Récompenses de recommandation (montants signés : une déduction est négative). source_id unique = idempotent.
		dbDelta(
			"CREATE TABLE {$p}commissions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  referrer_user_id BIGINT UNSIGNED NOT NULL,
  referee_user_id BIGINT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NULL,
  kind VARCHAR(20) NOT NULL,
  source_id VARCHAR(190) NOT NULL,
  amount_cents INT NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  available_at DATETIME NOT NULL,
  payout_batch VARCHAR(40) NULL,
  paid_at DATETIME NULL,
  note VARCHAR(255) NULL,
  is_test TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY source_id (source_id),
  KEY referrer_user_id (referrer_user_id),
  KEY order_id (order_id),
  KEY status (status)
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

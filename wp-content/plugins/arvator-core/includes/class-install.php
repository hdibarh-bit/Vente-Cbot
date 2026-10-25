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
		Arvator_Promo::migrate();
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
  method VARCHAR(10) NOT NULL DEFAULT 'card',
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

		// Envois au workflow n8n de licence (paiement, résiliation). source_id unique = jamais deux prolongations pour un paiement.
		dbDelta(
			"CREATE TABLE {$p}license_sync (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  action VARCHAR(20) NOT NULL,
  plan VARCHAR(10) NOT NULL,
  account VARCHAR(40) NULL,
  source_id VARCHAR(190) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  last_error VARCHAR(255) NULL,
  response TEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY source_id (source_id),
  KEY user_id (user_id),
  KEY status (status)
) $charset;"
		);

		// Codes promo créés par Hamid (la réduction elle-même est un coupon Stripe). Un même code peut exister en test et en réel.
		dbDelta(
			"CREATE TABLE {$p}promo_codes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(30) NOT NULL,
  target VARCHAR(10) NOT NULL DEFAULT '',
  percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
  bot_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
  sub_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
  duration VARCHAR(10) NOT NULL,
  months SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  plans VARCHAR(10) NOT NULL DEFAULT 'both',
  max_uses INT UNSIGNED NOT NULL DEFAULT 0,
  expires_at DATETIME NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  stripe_coupon VARCHAR(190) NOT NULL DEFAULT '',
  is_test TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY code_mode (code,is_test)
) $charset;"
		);

		// Utilisations des codes promo : une par paiement Stripe (stripe_session_id unique = idempotent).
		dbDelta(
			"CREATE TABLE {$p}promo_uses (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  promo_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NULL,
  stripe_session_id VARCHAR(190) NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY stripe_session_id (stripe_session_id),
  KEY promo_id (promo_id),
  KEY user_id (user_id)
) $charset;"
		);

		// Commandes payées par virement (achat initial et renouvellements annuels). reference = « ARV-123456 » ; token = accès à la
		// page des coordonnées bancaires ; tx_id = identifiant de la transaction bancaire (unique = idempotent).
		dbDelta(
			"CREATE TABLE {$p}bank_orders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  reference VARCHAR(16) NOT NULL,
  token VARCHAR(40) NOT NULL,
  kind VARCHAR(10) NOT NULL DEFAULT 'initial',
  user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  email VARCHAR(190) NOT NULL,
  name VARCHAR(120) NOT NULL DEFAULT '',
  account VARCHAR(40) NOT NULL DEFAULT '',
  plan VARCHAR(10) NOT NULL DEFAULT 'annual',
  amount_cents INT UNSIGNED NOT NULL,
  bot_cents INT UNSIGNED NOT NULL DEFAULT 0,
  sub_cents INT UNSIGNED NOT NULL DEFAULT 0,
  ref_code VARCHAR(16) NOT NULL DEFAULT '',
  promo_code VARCHAR(30) NOT NULL DEFAULT '',
  waiver TINYINT(1) NOT NULL DEFAULT 0,
  locale VARCHAR(10) NOT NULL DEFAULT '',
  status VARCHAR(20) NOT NULL DEFAULT 'en_attente',
  tx_id VARCHAR(190) NULL,
  received_cents INT UNSIGNED NULL,
  paid_at DATETIME NULL,
  order_id BIGINT UNSIGNED NULL,
  invoice_no VARCHAR(20) NULL,
  due_at DATETIME NULL,
  reminder_stage SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  applied TINYINT(1) NOT NULL DEFAULT 0,
  is_test TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY reference (reference),
  UNIQUE KEY token (token),
  UNIQUE KEY tx_id (tx_id),
  UNIQUE KEY invoice_no (invoice_no),
  KEY user_id (user_id),
  KEY email (email),
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

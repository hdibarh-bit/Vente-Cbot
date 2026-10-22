<?php
/**
 * Parrainage (un seul niveau) : lien de recommandation, filleuls, récompenses de recommandation.
 *
 * Règles (docs/03-parrainage-affiliation.md, décisions de Hamid du 21/10/2026) :
 * - inscription gratuite, sans obligation d'acheter ; un parrain non client est payé comme les autres ;
 * - lien ?ref=CODE mémorisé 90 jours, seulement si le visiteur l'accepte (bandeau cookies) ; le parrain est fixé au premier achat du filleul et ne change plus ;
 * - prime fixe par achat du robot (225 €), « à payer » 30 jours après le paiement ;
 * - 10 % de chaque abonnement payé par le filleul, tant qu'il reste abonné ; seul le parrain direct est payé ;
 * - remboursement ou contestation bancaire : la récompense est annulée, ou déduite des suivantes si elle a déjà été versée ;
 * - auto-parrainage (même compte, même email, même client Stripe, même carte) : récompense bloquée + alerte ;
 * - virement : la prime dès qu'elle est « à payer », l'affiliation à partir de 50 € cumulés.
 *
 * Statuts d'une récompense : pending (en attente) → payable (à payer) → paid (payée) ; canceled (annulée) ; blocked (bloquée).
 * Montants en centimes, signés (une déduction est négative). Chaque récompense a une origine unique (source_id) :
 * un même événement Stripe reçu deux fois ne crée jamais deux récompenses.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Arvator_Referral {

	public const COOKIE = 'arv_ref';

	/**
	 * Choix du visiteur sur les cookies, enregistré par le bandeau du thème (6 mois) : « v1-r1 » = cookie de
	 * recommandation accepté, « v1-r0 » = refusé. Sans accord, le cookie de recommandation n'est jamais déposé.
	 */
	public const CONSENT = 'arv_consent';
	private const OPT   = 'arvator_referral_settings';
	private const CRON  = 'arvator_referral_daily';
	private const CHARS = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // sans 0/O ni 1/I : codes faciles à recopier.

	public static function init(): void {
		add_action( 'template_redirect', array( self::class, 'capture_link' ), 1 );
		add_action( 'init', array( self::class, 'enforce_consent' ) );
		add_action( 'init', array( self::class, 'schedule' ) );
		add_action( self::CRON, array( self::class, 'promote_due' ) );
		add_filter( 'arvator_settings', array( self::class, 'public_settings' ) );
		add_shortcode( 'arvator_parrainage', array( self::class, 'render_page' ) );
		add_action( 'admin_post_nopriv_arvator_referral_signup', array( self::class, 'handle_signup' ) );
		add_action( 'admin_post_arvator_referral_signup', array( self::class, 'handle_signup' ) );
		add_action( 'admin_post_arvator_referral_join', array( self::class, 'handle_join' ) );
		add_action( 'admin_post_arvator_referral_bank', array( self::class, 'handle_bank' ) );
		Arvator_Support::no_cache_for( array( 'arvator_parrainage' ) );
	}

	/* --------------------------------------------------------------------- Réglages */

	/** @return array{prime_cents:int, rate:float, delay_days:int, payout_min_cents:int, cookie_days:int} */
	public static function settings(): array {
		$defaults = array(
			'prime_cents'      => 22500,
			'rate'             => 0.10,
			'delay_days'       => 30,
			'payout_min_cents' => 5000,
			'cookie_days'      => 90,
		);
		$saved = get_option( self::OPT, array() );
		$saved = is_array( $saved ) ? $saved : array();
		return array(
			'prime_cents'      => max( 0, (int) ( $saved['prime_cents'] ?? $defaults['prime_cents'] ) ),
			'rate'             => min( 0.5, max( 0.0, (float) ( $saved['rate'] ?? $defaults['rate'] ) ) ),
			'delay_days'       => max( 14, (int) ( $saved['delay_days'] ?? $defaults['delay_days'] ) ), // jamais avant la fin du délai de rétractation.
			'payout_min_cents' => max( 0, (int) ( $saved['payout_min_cents'] ?? $defaults['payout_min_cents'] ) ),
			'cookie_days'      => min( 365, max( 1, (int) ( $saved['cookie_days'] ?? $defaults['cookie_days'] ) ) ),
		);
	}

	/** @param array<string, mixed> $values */
	public static function save_settings( array $values ): void {
		update_option( self::OPT, $values, false );
	}

	/**
	 * Le thème affiche la prime et son délai (bandeau « Recommandez Arvator ») : on lui donne les valeurs réellement appliquées.
	 *
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	public static function public_settings( $data ): array {
		$data = is_array( $data ) ? $data : array();
		$s    = self::settings();
		$data['parrainage'] = array(
			'primeParAchat'      => $s['prime_cents'] / 100,
			'delaiPaiementJours' => $s['delay_days'],
		);
		return $data;
	}

	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
		}
	}

	/* --------------------------------------------------------------------- Parrains */

	/** @return array<string, mixed>|null */
	public static function referrer( int $user_id ): ?array {
		if ( $user_id <= 0 ) {
			return null;
		}
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Arvator_Repo::table( 'referrers' ) . ' WHERE user_id = %d', $user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return is_array( $row ) ? $row : null;
	}

	/** Parrain actif correspondant à un code, sinon null. */
	public static function referrer_by_code( string $code ): ?array {
		$code = self::normalize_code( $code );
		if ( '' === $code ) {
			return null;
		}
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Arvator_Repo::table( 'referrers' ) . " WHERE code = %s AND status = 'active'", $code ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return is_array( $row ) ? $row : null;
	}

	public static function normalize_code( string $code ): string {
		$code = strtoupper( preg_replace( '/[\s\-]+/', '', $code ) ?? '' );
		return 1 === preg_match( '/^[A-Z0-9]{8}$/', $code ) ? $code : '';
	}

	/** Inscrit un utilisateur comme parrain (idempotent). Retourne son code. */
	public static function enroll( int $user_id ): string {
		$existing = self::referrer( $user_id );
		if ( $existing ) {
			if ( 'active' !== $existing['status'] && 'blocked' !== $existing['status'] ) {
				global $wpdb;
				$wpdb->update( Arvator_Repo::table( 'referrers' ), array( 'status' => 'active', 'terms_at' => gmdate( 'Y-m-d H:i:s' ) ), array( 'user_id' => $user_id ) );
			}
			return (string) $existing['code'];
		}
		global $wpdb;
		for ( $i = 0; $i < 10; $i++ ) {
			$code = '';
			for ( $j = 0; $j < 8; $j++ ) {
				$code .= self::CHARS[ random_int( 0, strlen( self::CHARS ) - 1 ) ];
			}
			$now = gmdate( 'Y-m-d H:i:s' );
			$ok  = $wpdb->query( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$wpdb->prepare(
					'INSERT IGNORE INTO ' . Arvator_Repo::table( 'referrers' ) . ' (user_id, code, status, terms_at, created_at, updated_at) VALUES (%d, %s, %s, %s, %s, %s)',
					$user_id,
					$code,
					'active',
					$now,
					$now,
					$now
				)
			);
			if ( 1 === $ok ) {
				Arvator_Repo::log_event( 'referrer_enrolled', $user_id );
				return $code;
			}
			$again = self::referrer( $user_id ); // inscription simultanée : déjà fait.
			if ( $again ) {
				return (string) $again['code'];
			}
		}
		throw new RuntimeException( 'Code de parrainage impossible à générer.' );
	}

	public static function link( string $code ): string {
		return add_query_arg( 'ref', $code, home_url( '/' ) );
	}

	/** Lien direct vers la page d'achat : le code y est prérempli même si le visiteur refuse les cookies. */
	public static function buy_link( string $code ): string {
		return add_query_arg( 'ref', $code, Arvator_Checkout::url() );
	}

	/* --------------------------------------------------------------------- Lien de recommandation */

	/** Le visiteur a-t-il accepté le cookie de recommandation ? */
	public static function has_consent(): bool {
		return isset( $_COOKIE[ self::CONSENT ] ) && 'v1-r1' === $_COOKIE[ self::CONSENT ];
	}

	private static function cookie_args( int $expires ): array {
		return array(
			'expires'  => $expires,
			'path'     => COOKIEPATH ? COOKIEPATH : '/',
			'domain'   => (string) COOKIE_DOMAIN,
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		);
	}

	/** Accord retiré : le cookie de recommandation déjà déposé est supprimé à la visite suivante. */
	public static function enforce_consent(): void {
		if ( isset( $_COOKIE[ self::COOKIE ] ) && ! self::has_consent() && ! headers_sent() ) {
			setcookie( self::COOKIE, '', self::cookie_args( time() - YEAR_IN_SECONDS ) );
			unset( $_COOKIE[ self::COOKIE ] );
		}
	}

	/**
	 * ?ref=CODE :
	 * - accord donné : le code est mémorisé (cookie), puis la page se recharge sans le code (adresse propre) ;
	 * - choix pas encore fait : rien n'est déposé ; la page s'affiche avec le code dans l'adresse et le bandeau
	 *   demande l'accord (si le visiteur accepte, la page se recharge et on repasse ici) ;
	 * - refus : rien n'est déposé ; le code reste seulement dans l'adresse (prérempli si c'est la page d'achat,
	 *   sinon le visiteur peut le saisir à la main).
	 */
	public static function capture_link(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lien public, aucune action sensible
		if ( ! isset( $_GET['ref'] ) || is_admin() ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$code = self::normalize_code( sanitize_text_field( wp_unslash( (string) $_GET['ref'] ) ) );
		nocache_headers();
		if ( ! self::has_consent() ) {
			return;
		}
		if ( '' !== $code && self::referrer_by_code( $code ) ) {
			setcookie( self::COOKIE, $code, self::cookie_args( time() + self::settings()['cookie_days'] * DAY_IN_SECONDS ) );
		}
		wp_safe_redirect( remove_query_arg( 'ref' ), 302 );
		exit;
	}

	/** Code mémorisé dans le navigateur du visiteur (valide et actif), sinon ''. */
	public static function cookie_code(): string {
		if ( ! self::has_consent() ) {
			return '';
		}
		$raw  = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_text_field( wp_unslash( (string) $_COOKIE[ self::COOKIE ] ) ) : '';
		$code = self::normalize_code( $raw );
		return ( '' !== $code && self::referrer_by_code( $code ) ) ? $code : '';
	}

	/**
	 * Code utilisable pour cet achat ? Refusé s'il est inconnu, inactif, ou s'il appartient à l'acheteur lui-même.
	 */
	public static function code_usable_for( string $code, string $buyer_email, int $buyer_id ): bool {
		$ref = self::referrer_by_code( $code );
		if ( ! $ref ) {
			return false;
		}
		if ( $buyer_id > 0 && (int) $ref['user_id'] === $buyer_id ) {
			return false;
		}
		$owner = get_user_by( 'id', (int) $ref['user_id'] );
		return ! ( $owner instanceof WP_User && 0 === strcasecmp( $owner->user_email, $buyer_email ) );
	}

	/* --------------------------------------------------------------------- Achat (appelé par le webhook) */

	/**
	 * Achat du robot confirmé par Stripe : rattache le filleul à son parrain (une seule fois) et crée la prime
	 * et la récompense sur le premier abonnement.
	 *
	 * @param array<string, mixed> $session  Session Checkout complète, relue chez Stripe.
	 */
	public static function on_purchase( int $user_id, int $order_id, array $session, bool $live ): void {
		global $wpdb;
		$s        = self::settings();
		$referral = self::referral_for( $user_id );

		if ( ! $referral ) {
			$code = self::normalize_code( (string) ( $session['metadata']['ref'] ?? '' ) );
			if ( '' === $code ) {
				return;
			}
			$ref = self::referrer_by_code( $code );
			if ( ! $ref ) {
				Arvator_Repo::log_event( 'referral_unknown_code', $user_id, array( 'code' => $code ) );
				return;
			}
			$reason = self::fraud_reason( (int) $ref['user_id'], $user_id, (string) ( $session['customer'] ?? '' ), $order_id );
			$wpdb->query( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$wpdb->prepare(
					'INSERT IGNORE INTO ' . Arvator_Repo::table( 'referrals' ) . ' (referee_user_id, referrer_user_id, order_id, status, note, created_at) VALUES (%d, %d, %d, %s, %s, %s)',
					$user_id,
					(int) $ref['user_id'],
					$order_id,
					'' === $reason ? 'ok' : 'blocked',
					$reason,
					gmdate( 'Y-m-d H:i:s' )
				)
			);
			$referral = self::referral_for( $user_id );
			if ( ! $referral ) {
				throw new RuntimeException( 'Parrainage non enregistré.' );
			}
			if ( '' !== $reason ) {
				self::alert_fraud( (int) $ref['user_id'], $user_id, $reason );
			}
		}

		$blocked  = 'blocked' === $referral['status'];
		$referrer = (int) $referral['referrer_user_id'];

		// Prime d'achat (une par achat du robot).
		self::add_commission( $referrer, $user_id, $order_id, 'prime', 'prime:order:' . $order_id, $s['prime_cents'], $blocked, $live, (string) $referral['note'] );

		// 10 % du premier abonnement, payé dans le même passage que le robot.
		$sub_cents = 0;
		foreach ( (array) ( $session['line_items']['data'] ?? array() ) as $line ) {
			if ( '' !== Arvator_Stripe::plan_for_price( (string) ( $line['price']['id'] ?? '' ) ) ) {
				$sub_cents += (int) ( $line['amount_total'] ?? 0 );
			}
		}
		if ( $sub_cents > 0 ) {
			$source = '' !== (string) ( $session['invoice'] ?? '' ) ? 'aff:inv:' . $session['invoice'] : 'aff:first:' . (string) ( $session['id'] ?? $order_id );
			self::add_commission( $referrer, $user_id, $order_id, 'affiliation', $source, self::affiliation_cents( $sub_cents ), $blocked, $live, (string) $referral['note'] );
		}
	}

	/** 10 % arrondi au centime. */
	public static function affiliation_cents( int $paid_cents ): int {
		return (int) round( $paid_cents * self::settings()['rate'] );
	}

	/**
	 * Facture d'abonnement payée (renouvellement) : 10 % au parrain du client. Le premier abonnement est traité à l'achat.
	 *
	 * @param array<string, mixed> $invoice
	 */
	public static function on_invoice_paid( array $invoice, bool $live ): void {
		if ( 'subscription_create' === ( $invoice['billing_reason'] ?? '' ) ) {
			return;
		}
		$invoice_id = (string) ( $invoice['id'] ?? '' );
		$sub_id     = Arvator_Stripe::invoice_subscription( $invoice );
		$paid       = (int) ( $invoice['amount_paid'] ?? 0 );
		if ( '' === $invoice_id || '' === $sub_id || $paid <= 0 ) {
			return;
		}
		global $wpdb;
		$user_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT user_id FROM ' . Arvator_Repo::table( 'subscriptions' ) . ' WHERE stripe_subscription_id = %s', $sub_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( $user_id <= 0 ) {
			return;
		}
		$referral = self::referral_for( $user_id );
		if ( ! $referral || ! Arvator_Repo::has_paid_order( $user_id ) ) {
			return;
		}
		self::add_commission( (int) $referral['referrer_user_id'], $user_id, null, 'affiliation', 'aff:inv:' . $invoice_id, self::affiliation_cents( $paid ), 'blocked' === $referral['status'], $live, (string) $referral['note'] );
	}

	/**
	 * Achat remboursé (prix du robot) ou contesté : la prime est retirée. Pour une contestation, qui reprend tout le
	 * paiement, la récompense sur le premier abonnement l'est aussi. Ce qui n'est pas encore versé est annulé ; ce qui
	 * a déjà été versé est déduit des récompenses suivantes (ligne négative).
	 */
	public static function on_order_reversed( int $order_id, string $reason ): void {
		global $wpdb;
		$t     = Arvator_Repo::table( 'commissions' );
		$kinds = 'dispute' === $reason ? "'prime','affiliation'" : "'prime'";
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE order_id = %d AND kind IN ($kinds)", $order_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$now   = gmdate( 'Y-m-d H:i:s' );
		$label = 'dispute' === $reason ? 'contestation bancaire' : 'remboursement';

		foreach ( (array) $rows as $c ) {
			if ( in_array( $c['status'], array( 'pending', 'payable', 'blocked' ), true ) ) {
				$wpdb->update( $t, array( 'status' => 'canceled', 'payout_batch' => null, 'note' => 'Annulée : ' . $label . ' de l’achat', 'updated_at' => $now ), array( 'id' => (int) $c['id'] ) );
			} elseif ( 'paid' === $c['status'] ) {
				self::insert_commission(
					array(
						'referrer_user_id' => (int) $c['referrer_user_id'],
						'referee_user_id'  => (int) $c['referee_user_id'],
						'order_id'         => $order_id,
						'kind'             => 'adjustment',
						'source_id'        => 'adj:' . (int) $c['id'],
						'amount_cents'     => -1 * (int) $c['amount_cents'],
						'status'           => 'payable',
						'available_at'     => $now,
						'note'             => 'Déduction : ' . $label . ' après versement',
						'is_test'          => (int) $c['is_test'],
					)
				);
			}
		}
		if ( $rows ) {
			Arvator_Repo::log_event( 'referral_reversed', null, array( 'order' => $order_id, 'reason' => $reason ) );
		}
	}

	/* --------------------------------------------------------------------- Anti-fraude */

	/** Raison d'un auto-parrainage, '' si aucun soupçon. */
	public static function fraud_reason( int $referrer_id, int $referee_id, string $customer, int $order_id ): string {
		if ( $referrer_id === $referee_id ) {
			return 'Le parrain et l’acheteur sont le même compte.';
		}
		$a = get_user_by( 'id', $referrer_id );
		$b = get_user_by( 'id', $referee_id );
		if ( $a instanceof WP_User && $b instanceof WP_User && 0 === strcasecmp( $a->user_email, $b->user_email ) ) {
			return 'Même adresse email que le parrain.';
		}
		global $wpdb;
		if ( '' !== $customer ) {
			$same = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$wpdb->prepare(
					'SELECT (SELECT COUNT(*) FROM ' . Arvator_Repo::table( 'orders' ) . ' WHERE user_id = %d AND stripe_customer_id = %s) + (SELECT COUNT(*) FROM ' . Arvator_Repo::table( 'subscriptions' ) . ' WHERE user_id = %d AND stripe_customer_id = %s)',
					$referrer_id,
					$customer,
					$referrer_id,
					$customer
				)
			);
			if ( $same > 0 ) {
				return 'Même client Stripe que le parrain.';
			}
		}
		$fp = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT card_fingerprint FROM ' . Arvator_Repo::table( 'orders' ) . ' WHERE id = %d', $order_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( '' !== $fp ) {
			$same = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Arvator_Repo::table( 'orders' ) . ' WHERE user_id = %d AND card_fingerprint = %s', $referrer_id, $fp ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( $same > 0 ) {
				return 'Même carte bancaire que le parrain.';
			}
		}
		return '';
	}

	private static function alert_fraud( int $referrer_id, int $referee_id, string $reason ): void {
		Arvator_Repo::log_event( 'referral_blocked', $referrer_id, array( 'referee' => $referee_id, 'reason' => $reason ) );
		$a = get_user_by( 'id', $referrer_id );
		$b = get_user_by( 'id', $referee_id );
		wp_mail(
			Arvator_Pages::EMAIL,
			'Arvator : récompense de recommandation bloquée (auto-parrainage possible)',
			"Une récompense de recommandation a été bloquée automatiquement.\n\nRaison : $reason\nParrain : " . ( $a instanceof WP_User ? $a->user_email : '#' . $referrer_id ) . "\nAcheteur : " . ( $b instanceof WP_User ? $b->user_email : '#' . $referee_id ) . "\n\nÀ vérifier dans Arvator > Parrainage (débloquer ou annuler).\n"
		);
	}

	/* --------------------------------------------------------------------- Récompenses */

	/** @return array<string, mixed>|null */
	public static function referral_for( int $referee_id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Arvator_Repo::table( 'referrals' ) . ' WHERE referee_user_id = %d', $referee_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return is_array( $row ) ? $row : null;
	}

	private static function add_commission( int $referrer, int $referee, ?int $order_id, string $kind, string $source, int $cents, bool $blocked, bool $live, string $note ): void {
		if ( $cents <= 0 ) {
			return;
		}
		self::insert_commission(
			array(
				'referrer_user_id' => $referrer,
				'referee_user_id'  => $referee,
				'order_id'         => $order_id,
				'kind'             => $kind,
				'source_id'        => $source,
				'amount_cents'     => $cents,
				'status'           => $blocked ? 'blocked' : 'pending',
				'available_at'     => gmdate( 'Y-m-d H:i:s', time() + self::settings()['delay_days'] * DAY_IN_SECONDS ),
				'note'             => $blocked ? $note : '',
				'is_test'          => $live ? 0 : 1,
			)
		);
	}

	/** Insertion idempotente : une origine (source_id) ne donne jamais deux récompenses. */
	private static function insert_commission( array $c ): void {
		global $wpdb;
		$now    = gmdate( 'Y-m-d H:i:s' );
		$order  = null === $c['order_id'] ? 'NULL' : (string) (int) $c['order_id'];
		$wpdb->query( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare(
				'INSERT IGNORE INTO ' . Arvator_Repo::table( 'commissions' ) . " (referrer_user_id, referee_user_id, order_id, kind, source_id, amount_cents, status, available_at, note, is_test, created_at, updated_at) VALUES (%d, %d, $order, %s, %s, %d, %s, %s, %s, %d, %s, %s)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $order est un entier ou NULL
				(int) $c['referrer_user_id'],
				(int) $c['referee_user_id'],
				substr( (string) $c['kind'], 0, 20 ),
				substr( (string) $c['source_id'], 0, 190 ),
				(int) $c['amount_cents'],
				(string) $c['status'],
				(string) $c['available_at'],
				substr( (string) $c['note'], 0, 255 ),
				(int) $c['is_test'],
				$now,
				$now
			)
		);
	}

	/** Les récompenses dont le délai est passé deviennent « à payer » (tâche quotidienne, et à chaque consultation). */
	public static function promote_due(): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . Arvator_Repo::table( 'commissions' ) . " SET status = 'payable', updated_at = %s WHERE status = 'pending' AND available_at <= %s", gmdate( 'Y-m-d H:i:s' ), gmdate( 'Y-m-d H:i:s' ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/** @return array<int, array<string, mixed>> */
	public static function commissions_for( int $referrer_id ): array {
		if ( $referrer_id <= 0 ) {
			return array();
		}
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Arvator_Repo::table( 'commissions' ) . ' WHERE referrer_user_id = %d ORDER BY created_at DESC, id DESC', $referrer_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return is_array( $rows ) ? $rows : array();
	}

	/** @return array<int, array<string, mixed>> */
	public static function referrals_of( int $referrer_id ): array {
		if ( $referrer_id <= 0 ) {
			return array();
		}
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Arvator_Repo::table( 'referrals' ) . ' WHERE referrer_user_id = %d ORDER BY created_at DESC, id DESC', $referrer_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Ce qui peut être viré à chaque parrain maintenant : primes et déductions « à payer », plus l'affiliation si son
	 * cumul atteint le seuil. Rien si le total n'est pas positif.
	 *
	 * @return array<int, array{user_id:int, total:int, ids:int[]}>
	 */
	public static function payouts_due(): array {
		self::promote_due();
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT id, referrer_user_id, kind, amount_cents FROM ' . Arvator_Repo::table( 'commissions' ) . " WHERE status = 'payable' AND (payout_batch IS NULL OR payout_batch = '') ORDER BY id", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$min  = self::settings()['payout_min_cents'];
		$by   = array();
		foreach ( (array) $rows as $r ) {
			$by[ (int) $r['referrer_user_id'] ][] = $r;
		}
		$out = array();
		foreach ( $by as $uid => $list ) {
			$aff = array_sum( array_map( static fn( $r ) => 'affiliation' === $r['kind'] ? (int) $r['amount_cents'] : 0, $list ) );
			$ids = array();
			$tot = 0;
			foreach ( $list as $r ) {
				if ( 'affiliation' === $r['kind'] && $aff < $min ) {
					continue;
				}
				$ids[] = (int) $r['id'];
				$tot  += (int) $r['amount_cents'];
			}
			if ( $tot > 0 && $ids ) {
				$out[] = array( 'user_id' => (int) $uid, 'total' => $tot, 'ids' => $ids );
			}
		}
		return $out;
	}

	/* --------------------------------------------------------------------- Coordonnées bancaires (chiffrées) */

	public static function valid_iban( string $iban ): bool {
		$iban = strtoupper( preg_replace( '/\s+/', '', $iban ) ?? '' );
		if ( 1 !== preg_match( '/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/', $iban ) ) {
			return false;
		}
		$moved  = substr( $iban, 4 ) . substr( $iban, 0, 4 );
		$digits = '';
		foreach ( str_split( $moved ) as $ch ) {
			$digits .= ctype_alpha( $ch ) ? (string) ( ord( $ch ) - 55 ) : $ch;
		}
		$rest = 0;
		foreach ( str_split( $digits, 7 ) as $chunk ) {
			$rest = (int) ( $rest . $chunk ) % 97;
		}
		return 1 === $rest;
	}

	public static function mask_iban( string $iban ): string {
		return '' === $iban ? '' : substr( $iban, 0, 4 ) . ' •••• •••• ' . substr( $iban, -4 );
	}

	/** Clé de chiffrement : ARVATOR_DATA_KEY (wp-config.php) si elle existe, sinon dérivée des clés secrètes de WordPress. */
	private static function key(): string {
		$base = defined( 'ARVATOR_DATA_KEY' ) && '' !== (string) ARVATOR_DATA_KEY ? (string) ARVATOR_DATA_KEY : wp_salt( 'secure_auth' );
		return sodium_crypto_generichash( 'arvator-iban|' . $base, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
	}

	public static function encrypt( string $plain ): string {
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		return 'v1:' . base64_encode( $nonce . sodium_crypto_secretbox( $plain, $nonce, self::key() ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/** Déchiffre ; '' si la donnée est absente ou illisible (par exemple après un changement des clés secrètes). */
	public static function decrypt( string $stored ): string {
		if ( ! str_starts_with( $stored, 'v1:' ) ) {
			return '';
		}
		$raw = base64_decode( substr( $stored, 3 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return '';
		}
		$plain = sodium_crypto_secretbox_open( substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), self::key() );
		return false === $plain ? '' : $plain;
	}

	/* --------------------------------------------------------------------- Page publique [arvator_parrainage] */

	public static function render_page(): string {
		$s = self::settings();
		ob_start();
		echo wp_kses_post( Arvator_Support::render_notice() );
		?>
		<div class="arv-ref-summary">
			<div class="arv-ref-summary__item"><strong><?php echo esc_html( self::money( $s['prime_cents'] ) ); ?></strong><span>par achat du robot fait avec votre lien, versés <?php echo esc_html( (string) $s['delay_days'] ); ?>&nbsp;jours après l’achat</span></div>
			<div class="arv-ref-summary__item"><strong><?php echo esc_html( self::pct( $s['rate'] ) ); ?></strong><span>de chaque abonnement payé par la personne recommandée, tant qu’elle reste abonnée</span></div>
		</div>
		<?php
		$user_id = get_current_user_id();
		if ( $user_id && self::referrer( $user_id ) ) {
			echo '<p><a class="btn btn--primary" href="' . esc_url( Arvator_Member::url( 'parrainage' ) ) . '">Voir mon lien et mes récompenses</a></p>';
		} elseif ( $user_id ) {
			self::join_form();
		} else {
			self::signup_form();
		}
		return (string) ob_get_clean();
	}

	private static function terms_checkbox(): void {
		?>
		<p class="arv-check"><label><input type="checkbox" name="terms" value="1" required> J’ai lu et j’accepte les <a href="<?php echo esc_url( Arvator_Support::page_url( 'conditions-parrainage' ) ); ?>" target="_blank" rel="noopener">conditions du programme de parrainage</a>, notamment : une récompense est retirée si l’achat est remboursé ou contesté, et déduite de mes récompenses suivantes si elle m’a déjà été versée.</label></p>
		<?php
	}

	public static function join_form(): void {
		?>
		<form class="arv-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="arvator_referral_join">
			<?php wp_nonce_field( 'arvator_referral_join', '_arv_nonce' ); ?>
			<?php self::terms_checkbox(); ?>
			<p><button class="btn btn--primary" type="submit">Obtenir mon lien de recommandation</button></p>
			<p class="muted">Gratuit et sans engagement. Aucun achat n’est nécessaire.</p>
		</form>
		<?php
	}

	private static function signup_form(): void {
		?>
		<form class="arv-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="arvator_referral_signup">
			<?php wp_nonce_field( 'arvator_referral_signup', '_arv_nonce' ); ?>
			<div class="arv-hp" aria-hidden="true">
				<label>Ne pas remplir ce champ <input type="text" name="website" value="" tabindex="-1" autocomplete="off"></label>
			</div>
			<p>
				<label for="arv-ref-email">Votre adresse email</label>
				<input id="arv-ref-email" name="email" type="email" required maxlength="190" autocomplete="email">
			</p>
			<?php self::terms_checkbox(); ?>
			<p><button class="btn btn--primary" type="submit">Devenir parrain gratuitement</button></p>
			<p class="muted">Gratuit et sans obligation d’acheter. Vous recevez un email pour choisir votre mot de passe, puis vous retrouvez votre lien dans votre espace membre. Déjà client&nbsp;? <a href="<?php echo esc_url( Arvator_Member::url() ); ?>">Connectez-vous</a>.</p>
		</form>
		<?php
	}

	/* --------------------------------------------------------------------- Actions */

	/** Inscription d'un visiteur sans compte. Réponse identique que l'adresse existe ou non (pas de fuite d'information). */
	public static function handle_signup(): void {
		$back  = Arvator_Support::page_url( 'parrainage' );
		$nonce = isset( $_POST['_arv_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_arv_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'arvator_referral_signup' ) ) {
			Arvator_Support::redirect( $back, 'generic_invalid' );
		}
		if ( is_user_logged_in() ) {
			wp_safe_redirect( Arvator_Member::url( 'parrainage' ) );
			exit;
		}
		if ( ! empty( $_POST['website'] ) ) {
			Arvator_Support::redirect( $back, 'parrain_mail' );
		}
		$ip_key = 'arv_refsig_' . md5( Arvator_Support::client_ip() );
		if ( (int) get_transient( $ip_key ) >= 5 ) {
			Arvator_Support::redirect( $back, 'achat_rate' );
		}
		set_transient( $ip_key, (int) get_transient( $ip_key ) + 1, HOUR_IN_SECONDS );

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( (string) $_POST['email'] ) ) : '';
		if ( ! is_email( $email ) || empty( $_POST['terms'] ) ) {
			Arvator_Support::redirect( $back, 'parrain_invalid' );
		}

		$user = get_user_by( 'email', $email );
		if ( $user instanceof WP_User ) {
			// Compte existant : on ne l'inscrit pas à sa place, on lui explique comment faire (email à son adresse uniquement).
			wp_mail(
				$user->user_email,
				'Arvator : activer votre lien de recommandation',
				"Bonjour,\n\nVous avez demandé à devenir parrain Arvator. Vous avez déjà un compte : connectez-vous à votre espace membre, rubrique « Parrainage », pour obtenir votre lien :\n" . Arvator_Member::url( 'parrainage' ) . "\n\nSi vous n'êtes pas à l'origine de cette demande, ignorez ce message.\n\nArvator · " . Arvator_Pages::EMAIL
			);
			Arvator_Support::redirect( $back, 'parrain_mail' );
		}

		$id = wp_insert_user(
			array(
				'user_login' => sanitize_user( $email, true ),
				'user_email' => $email,
				'user_pass'  => wp_generate_password( 32, true, true ),
				'role'       => Arvator_Roles::CLIENT,
			)
		);
		if ( is_wp_error( $id ) ) {
			Arvator_Support::redirect( $back, 'parrain_invalid' );
		}
		$code = self::enroll( (int) $id );
		$user = get_user_by( 'id', (int) $id );
		$key  = $user instanceof WP_User ? get_password_reset_key( $user ) : '';
		$lines = array(
			'Bonjour,',
			'',
			'Bienvenue dans le programme de parrainage Arvator.',
			'',
			'Votre lien de recommandation : ' . self::link( $code ),
			'Pour une personne prête à acheter, envoyez plutôt ce lien vers la page d\'achat (votre code y est déjà rempli) : ' . self::buy_link( $code ),
			'',
		);
		if ( is_string( $key ) && $user instanceof WP_User ) {
			$lines[] = 'Choisissez votre mot de passe ici (lien valable 24 heures) pour accéder à votre espace membre :';
			$lines[] = network_site_url( 'wp-login.php?action=rp&key=' . rawurlencode( $key ) . '&login=' . rawurlencode( $user->user_login ), 'login' );
			$lines[] = '';
		}
		$lines[] = 'Pour recevoir vos récompenses de recommandation, indiquez votre IBAN dans votre espace membre, rubrique « Parrainage ».';
		$lines[] = '';
		$lines[] = 'Rappel : recommandez Arvator de façon honnête. Les résultats présentés sur le site sont des performances simulées et le trading comporte un risque élevé de perte en capital.';
		$lines[] = '';
		$lines[] = 'Arvator · ' . Arvator_Pages::EMAIL;
		wp_mail( $email, 'Arvator : votre lien de recommandation', implode( "\n", $lines ) );

		Arvator_Support::redirect( $back, 'parrain_mail' );
	}

	/** Inscription d'un client déjà connecté. */
	public static function handle_join(): void {
		$back = Arvator_Member::url( 'parrainage' );
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( Arvator_Member::url() );
			exit;
		}
		$nonce = isset( $_POST['_arv_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_arv_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'arvator_referral_join' ) ) {
			Arvator_Support::redirect( $back, 'generic_invalid' );
		}
		if ( empty( $_POST['terms'] ) ) {
			Arvator_Support::redirect( $back, 'parrain_invalid' );
		}
		self::enroll( get_current_user_id() );
		Arvator_Support::redirect( $back, 'parrain_ok' );
	}

	/** Coordonnées de virement du parrain connecté (jamais celles d'un autre : l'identifiant vient de la session). */
	public static function handle_bank(): void {
		$back = Arvator_Member::url( 'parrainage' );
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( Arvator_Member::url() );
			exit;
		}
		$nonce = isset( $_POST['_arv_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_arv_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'arvator_referral_bank' ) ) {
			Arvator_Support::redirect( $back, 'generic_invalid' );
		}
		$user_id = get_current_user_id();
		if ( ! self::referrer( $user_id ) ) {
			Arvator_Support::redirect( $back, 'generic_invalid' );
		}
		$holder = isset( $_POST['holder'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['holder'] ) ) : '';
		$iban   = isset( $_POST['iban'] ) ? strtoupper( preg_replace( '/\s+/', '', sanitize_text_field( wp_unslash( (string) $_POST['iban'] ) ) ) ?? '' ) : '';
		$type   = isset( $_POST['entity'] ) ? sanitize_key( wp_unslash( (string) $_POST['entity'] ) ) : '';
		$siret  = isset( $_POST['siret'] ) ? preg_replace( '/\D+/', '', sanitize_text_field( wp_unslash( (string) $_POST['siret'] ) ) ) : '';
		$holder = mb_substr( trim( $holder ), 0, 120 );

		if ( mb_strlen( $holder ) < 2 || ! self::valid_iban( $iban ) || ! in_array( $type, array( 'particulier', 'entreprise' ), true ) || ( 'entreprise' === $type && 14 !== strlen( (string) $siret ) ) ) {
			Arvator_Support::redirect( $back, 'iban_invalid' );
		}
		global $wpdb;
		$wpdb->update(
			Arvator_Repo::table( 'referrers' ),
			array(
				'holder_name' => $holder,
				'iban_enc'    => self::encrypt( $iban ),
				'iban_last4'  => substr( $iban, 0, 4 ) . substr( $iban, -4 ),
				'entity_type' => $type,
				'siret'       => 'entreprise' === $type ? (string) $siret : '',
				'updated_at'  => gmdate( 'Y-m-d H:i:s' ),
			),
			array( 'user_id' => $user_id )
		);
		Arvator_Repo::log_event( 'referrer_bank_updated', $user_id );
		Arvator_Support::redirect( $back, 'iban_ok' );
	}

	/* --------------------------------------------------------------------- Mise en forme */

	public static function money( int $cents ): string {
		$neg = $cents < 0;
		$abs = abs( $cents );
		return ( $neg ? "\u{2212}" : '' ) . number_format( $abs / 100, 0 === $abs % 100 ? 0 : 2, ',', "\u{202F}" ) . "\u{00A0}€";
	}

	public static function pct( float $rate ): string {
		$v = round( $rate * 100, 1 );
		return ( floor( $v ) === $v ? (string) (int) $v : str_replace( '.', ',', (string) $v ) ) . "\u{00A0}%";
	}

	public static function status_label( string $status ): string {
		return array(
			'pending'  => 'En attente',
			'payable'  => 'À payer',
			'paid'     => 'Payée',
			'canceled' => 'Annulée',
			'blocked'  => 'Bloquée (vérification)',
		)[ $status ] ?? $status;
	}

	public static function kind_label( string $kind ): string {
		return array(
			'prime'       => 'Prime d’achat',
			'affiliation' => 'Abonnement',
			'adjustment'  => 'Déduction',
		)[ $kind ] ?? $kind;
	}
}

<?php
/**
 * Réception des messages de Stripe (webhook) : /wp-json/arvator/v1/stripe
 *
 * - Signature vérifiée (secret du webhook) et message de plus de 5 minutes refusé.
 * - Idempotent : chaque événement Stripe n'est traité qu'une fois (clé unique dans le journal). En cas d'erreur
 *   pendant le traitement, l'événement est « relâché » et Stripe le renvoie.
 * - Événements gérés : checkout.session.completed, customer.subscription.updated/deleted,
 *   invoice.payment_failed, charge.refunded. Tout autre type est ignoré.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Arvator_Webhook {

	private const HANDLED = array(
		'checkout.session.completed',
		'customer.subscription.updated',
		'customer.subscription.deleted',
		'invoice.payment_failed',
		'charge.refunded',
	);

	public static function init(): void {
		add_action( 'rest_api_init', array( self::class, 'register_route' ) );
	}

	public static function register_route(): void {
		register_rest_route(
			'arvator/v1',
			'/stripe',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'handle' ),
				'permission_callback' => '__return_true', // la sécurité est la signature Stripe, vérifiée dans handle().
			)
		);
	}

	public static function handle( WP_REST_Request $request ): WP_REST_Response {
		$secret = Arvator_Stripe::webhook_secret();
		if ( '' === $secret ) {
			return new WP_REST_Response( array( 'error' => 'not_configured' ), 503 );
		}
		$payload = (string) $request->get_body();
		$header  = (string) $request->get_header( 'stripe-signature' );
		if ( ! Arvator_Stripe::verify_signature( $payload, $header, $secret ) ) {
			return new WP_REST_Response( array( 'error' => 'bad_signature' ), 400 );
		}

		$event = json_decode( $payload, true );
		$id    = is_array( $event ) ? (string) ( $event['id'] ?? '' ) : '';
		$type  = is_array( $event ) ? (string) ( $event['type'] ?? '' ) : '';
		if ( '' === $id || '' === $type ) {
			return new WP_REST_Response( array( 'error' => 'bad_payload' ), 400 );
		}
		if ( ! in_array( $type, self::HANDLED, true ) ) {
			return new WP_REST_Response( array( 'status' => 'ignored' ), 200 );
		}

		// On « réserve » l'événement : s'il est déjà connu, on ne refait rien.
		if ( ! Arvator_Repo::log_event( 'stripe_' . $type, null, array( 'livemode' => ! empty( $event['livemode'] ) ), 'stripe:' . $id ) ) {
			return new WP_REST_Response( array( 'status' => 'duplicate' ), 200 );
		}

		try {
			$object = is_array( $event['data']['object'] ?? null ) ? $event['data']['object'] : array();
			$live   = ! empty( $event['livemode'] );
			match ( $type ) {
				'checkout.session.completed'    => self::on_checkout_completed( $object, $live ),
				'customer.subscription.updated',
				'customer.subscription.deleted' => self::on_subscription_changed( $object, 'customer.subscription.deleted' === $type ),
				'invoice.payment_failed'        => self::on_payment_failed( $object ),
				'charge.refunded'               => self::on_refund( $object ),
			};
		} catch ( Throwable $e ) {
			Arvator_Repo::release_event( 'stripe:' . $id );
			Arvator_Repo::log_event( 'stripe_error', null, array( 'where' => $type, 'error' => $e->getMessage() ) );
			return new WP_REST_Response( array( 'error' => 'processing_failed' ), 500 );
		}

		return new WP_REST_Response( array( 'status' => 'ok' ), 200 );
	}

	/* --------------------------------------------------------------------- Achat */

	/** @param array<string, mixed> $session */
	private static function on_checkout_completed( array $session, bool $live ): void {
		if ( 'paid' !== ( $session['payment_status'] ?? '' ) ) {
			return; // paiement pas encore encaissé : on attend l'événement suivant.
		}
		$session_id = (string) ( $session['id'] ?? '' );
		if ( '' === $session_id ) {
			throw new RuntimeException( 'Session sans identifiant.' );
		}

		// Données complètes (lignes d'achat, abonnement) : lues chez Stripe, jamais crues sur parole.
		$full = Arvator_Stripe::request( 'GET', 'checkout/sessions/' . rawurlencode( $session_id ), array( 'expand' => array( 'line_items', 'subscription' ) ) );
		if ( is_wp_error( $full ) ) {
			throw new RuntimeException( $full->get_error_message() );
		}

		$email = (string) ( $full['customer_details']['email'] ?? $full['customer_email'] ?? '' );
		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			throw new RuntimeException( 'Email du client introuvable.' );
		}

		[ $user_id, $created ] = self::find_or_create_user( $email, (int) ( $full['client_reference_id'] ?? 0 ) );

		// Montant du robot = la ligne « robot » de la commande.
		$bot_price = Arvator_Stripe::price( 'bot' );
		$amount    = 0;
		foreach ( (array) ( $full['line_items']['data'] ?? array() ) as $line ) {
			if ( ( $line['price']['id'] ?? '' ) === $bot_price ) {
				$amount = (int) ( $line['amount_total'] ?? 0 );
			}
		}
		if ( $amount <= 0 ) {
			throw new RuntimeException( 'Ligne du robot introuvable dans la commande.' );
		}

		global $wpdb;
		$now      = gmdate( 'Y-m-d H:i:s' );
		$customer = (string) ( $full['customer'] ?? '' );
		$wpdb->query( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare(
				'INSERT IGNORE INTO ' . Arvator_Repo::table( 'orders' ) . ' (user_id, stripe_session_id, stripe_payment_intent, stripe_invoice_id, stripe_customer_id, product, amount_cents, currency, status, is_test, created_at) VALUES (%d, %s, %s, %s, %s, %s, %d, %s, %s, %d, %s)',
				$user_id,
				$session_id,
				(string) ( $full['payment_intent'] ?? '' ),
				(string) ( $full['invoice'] ?? '' ),
				$customer,
				'cbot',
				$amount,
				strtolower( (string) ( $full['currency'] ?? 'eur' ) ),
				'paid',
				$live ? 0 : 1,
				$now
			)
		);

		if ( is_array( $full['subscription'] ?? null ) ) {
			self::upsert_subscription( $user_id, $full['subscription'], $customer, ! $live );
		}

		Arvator_Repo::log_event( 'purchase', $user_id, array( 'session' => $session_id, 'amount_cents' => $amount ) );
		self::send_confirmation( $user_id, $created );
	}

	/**
	 * Retrouve le compte du client : identifiant transmis au paiement (client connecté), sinon adresse email,
	 * sinon création du compte (rôle client, mot de passe aléatoire : le client le choisit par email).
	 *
	 * @return array{0:int, 1:bool}  [identifiant, compte créé ?]
	 */
	private static function find_or_create_user( string $email, int $reference ): array {
		if ( $reference > 0 ) {
			$user = get_user_by( 'id', $reference );
			if ( $user instanceof WP_User && 0 === strcasecmp( $user->user_email, $email ) ) {
				return array( (int) $user->ID, false );
			}
		}
		$user = get_user_by( 'email', $email );
		if ( $user instanceof WP_User ) {
			return array( (int) $user->ID, false );
		}

		$login = sanitize_user( $email, true );
		$id    = wp_insert_user(
			array(
				'user_login' => $login,
				'user_email' => $email,
				'user_pass'  => wp_generate_password( 32, true, true ),
				'role'       => Arvator_Roles::CLIENT,
			)
		);
		if ( is_wp_error( $id ) ) {
			throw new RuntimeException( 'Création du compte impossible : ' . $id->get_error_message() );
		}
		return array( (int) $id, true );
	}

	/** @param array<string, mixed> $sub */
	private static function upsert_subscription( int $user_id, array $sub, string $customer, bool $is_test ): void {
		$sub_id = (string) ( $sub['id'] ?? '' );
		if ( '' === $sub_id ) {
			return;
		}
		$price = (string) ( $sub['items']['data'][0]['price']['id'] ?? '' );
		$plan  = Arvator_Stripe::plan_for_price( $price );
		if ( '' === $plan ) {
			$plan = (string) ( $sub['metadata']['plan'] ?? '' );
		}
		$plan = in_array( $plan, array( 'annual', 'monthly' ), true ) ? $plan : 'monthly';

		global $wpdb;
		$now = gmdate( 'Y-m-d H:i:s' );
		$wpdb->query( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare(
				'INSERT INTO ' . Arvator_Repo::table( 'subscriptions' ) . ' (user_id, stripe_subscription_id, stripe_customer_id, plan, status, current_period_end, cancel_at_period_end, is_test, created_at, updated_at) VALUES (%d, %s, %s, %s, %s, %s, %d, %d, %s, %s) ON DUPLICATE KEY UPDATE status = VALUES(status), current_period_end = VALUES(current_period_end), cancel_at_period_end = VALUES(cancel_at_period_end), updated_at = VALUES(updated_at)',
				$user_id,
				$sub_id,
				$customer,
				$plan,
				substr( (string) ( $sub['status'] ?? 'active' ), 0, 20 ),
				self::period_end( $sub ),
				! empty( $sub['cancel_at_period_end'] ) ? 1 : 0,
				$is_test ? 1 : 0,
				$now,
				$now
			)
		);
	}

	/** Fin de la période payée (UTC). L'emplacement du champ dépend de la version de l'API Stripe. */
	private static function period_end( array $sub ): string {
		$ts = (int) ( $sub['current_period_end'] ?? $sub['items']['data'][0]['current_period_end'] ?? 0 );
		return $ts > 0 ? gmdate( 'Y-m-d H:i:s', $ts ) : gmdate( 'Y-m-d H:i:s' );
	}

	/* --------------------------------------------------------------------- Abonnement */

	/** @param array<string, mixed> $sub */
	private static function on_subscription_changed( array $sub, bool $deleted ): void {
		$sub_id = (string) ( $sub['id'] ?? '' );
		if ( '' === $sub_id ) {
			return;
		}
		global $wpdb;
		// Si l'achat n'est pas encore enregistré (messages dans le désordre), on ne fait rien :
		// l'achat relira l'état complet chez Stripe.
		$wpdb->query( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare(
				'UPDATE ' . Arvator_Repo::table( 'subscriptions' ) . ' SET status = %s, current_period_end = %s, cancel_at_period_end = %d, updated_at = %s WHERE stripe_subscription_id = %s',
				$deleted ? 'canceled' : substr( (string) ( $sub['status'] ?? 'active' ), 0, 20 ),
				self::period_end( $sub ),
				! empty( $sub['cancel_at_period_end'] ) ? 1 : 0,
				gmdate( 'Y-m-d H:i:s' ),
				$sub_id
			)
		);
	}

	/** @param array<string, mixed> $invoice */
	private static function on_payment_failed( array $invoice ): void {
		// L'état de l'abonnement (en retard de paiement) arrive par customer.subscription.updated.
		// Ici on garde seulement une trace pour l'administration et pour n8n (relances).
		Arvator_Repo::log_event( 'payment_failed', null, array( 'invoice' => (string) ( $invoice['id'] ?? '' ), 'customer' => (string) ( $invoice['customer'] ?? '' ) ) );
	}

	/* --------------------------------------------------------------------- Remboursement */

	/**
	 * Règle de Hamid : le remboursement porte sur le prix du robot ; l'abonnement de la première période n'est pas
	 * remboursé ; le remboursement met fin à la licence. Dès que le montant remboursé couvre le prix du robot,
	 * l'achat est marqué « remboursé », les licences sont révoquées et l'abonnement est arrêté.
	 *
	 * @param array<string, mixed> $charge
	 */
	private static function on_refund( array $charge ): void {
		global $wpdb;
		$orders   = Arvator_Repo::table( 'orders' );
		$invoice  = (string) ( $charge['invoice'] ?? '' );
		$customer = (string) ( $charge['customer'] ?? '' );
		$refunded = (int) ( $charge['amount_refunded'] ?? 0 );

		$order = null;
		if ( '' !== $invoice ) {
			$order = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $orders WHERE stripe_invoice_id = %s AND status = 'paid' LIMIT 1", $invoice ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		if ( ! $order && '' !== $customer ) {
			$order = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $orders WHERE stripe_customer_id = %s AND status = 'paid' ORDER BY id DESC LIMIT 1", $customer ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		if ( ! is_array( $order ) ) {
			Arvator_Repo::log_event( 'refund_unmatched', null, array( 'charge' => (string) ( $charge['id'] ?? '' ) ) );
			return;
		}
		if ( $refunded < (int) $order['amount_cents'] ) {
			// Remboursement inférieur au prix du robot : on garde la trace, l'achat reste valide.
			Arvator_Repo::log_event( 'refund_partial', (int) $order['user_id'], array( 'refunded_cents' => $refunded ) );
			return;
		}

		$user_id = (int) $order['user_id'];
		$now     = gmdate( 'Y-m-d H:i:s' );
		$wpdb->update( $orders, array( 'status' => 'refunded' ), array( 'id' => (int) $order['id'] ), array( '%s' ), array( '%d' ) );
		$wpdb->query( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare( 'UPDATE ' . Arvator_Repo::table( 'licenses' ) . " SET status = 'revoked', updated_at = %s WHERE user_id = %d AND status = 'active'", $now, $user_id )
		);

		// Arrêt de l'abonnement chez Stripe pour qu'il ne soit plus facturé.
		$subs = $wpdb->get_col( $wpdb->prepare( 'SELECT stripe_subscription_id FROM ' . Arvator_Repo::table( 'subscriptions' ) . " WHERE user_id = %d AND status IN ('active','trialing','past_due') AND stripe_subscription_id IS NOT NULL", $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		foreach ( (array) $subs as $sub_id ) {
			$res = Arvator_Stripe::request( 'DELETE', 'subscriptions/' . rawurlencode( (string) $sub_id ) );
			if ( is_wp_error( $res ) ) {
				Arvator_Repo::log_event( 'refund_cancel_failed', $user_id, array( 'subscription' => (string) $sub_id, 'error' => $res->get_error_message() ) );
				continue;
			}
			$wpdb->update( Arvator_Repo::table( 'subscriptions' ), array( 'status' => 'canceled', 'updated_at' => $now ), array( 'stripe_subscription_id' => (string) $sub_id ), array( '%s', '%s' ), array( '%s' ) );
		}
		Arvator_Repo::log_event( 'refund', $user_id, array( 'refunded_cents' => $refunded ) );
	}

	/* --------------------------------------------------------------------- Email */

	private static function send_confirmation( int $user_id, bool $created ): void {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user instanceof WP_User ) {
			return;
		}
		$lines = array( 'Bonjour,', '', 'Merci pour votre commande : votre paiement a bien été reçu.', '' );
		if ( $created ) {
			$key = get_password_reset_key( $user );
			if ( is_string( $key ) ) {
				$link    = network_site_url( 'wp-login.php?action=rp&key=' . rawurlencode( $key ) . '&login=' . rawurlencode( $user->user_login ), 'login' );
				$lines[] = 'Votre espace membre est créé. Choisissez votre mot de passe ici (lien valable 24 heures) :';
				$lines[] = $link;
				$lines[] = '';
			}
		}
		$lines[] = 'Retrouvez votre achat, votre abonnement et le téléchargement du robot dans votre espace membre :';
		$lines[] = Arvator_Member::url();
		$lines[] = '';
		$lines[] = 'Rappel : les résultats présentés sur le site sont des performances simulées. Le trading comporte un risque élevé de perte en capital.';
		$lines[] = '';
		$lines[] = 'Arvator · ' . Arvator_Pages::EMAIL;

		wp_mail( $user->user_email, 'Arvator : votre commande est confirmée', implode( "\n", $lines ) );
	}
}

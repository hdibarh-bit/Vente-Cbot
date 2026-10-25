<?php
/**
 * Réception des messages de Stripe (webhook) : /wp-json/arvator/v1/stripe
 *
 * - Signature vérifiée (secret du webhook) et message de plus de 5 minutes refusé.
 * - Idempotent : chaque événement Stripe n'est traité qu'une fois (clé unique dans le journal). En cas d'erreur
 *   pendant le traitement, l'événement est « relâché » et Stripe le renvoie.
 * - Événements gérés : checkout.session.completed, customer.subscription.updated/deleted,
 *   invoice.paid (récompense de parrainage sur les renouvellements), invoice.payment_failed, charge.refunded,
 *   charge.dispute.created (contestation bancaire). Tout autre type est ignoré.
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
		'invoice.paid',
		'invoice.payment_failed',
		'charge.refunded',
		'charge.dispute.created',
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
				'invoice.paid'                  => self::on_invoice_paid( $object, $live ),
				'invoice.payment_failed'        => self::on_payment_failed( $object ),
				'charge.refunded'               => self::on_refund( $object ),
				'charge.dispute.created'        => self::on_dispute( $object ),
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
		// « no_payment_required » : premier paiement à 0 € (code promo robot et 1re période offerts).
		if ( ! in_array( $session['payment_status'] ?? '', array( 'paid', 'no_payment_required' ), true ) ) {
			return; // paiement pas encore encaissé : on attend l'événement suivant.
		}
		$session_id = (string) ( $session['id'] ?? '' );
		if ( '' === $session_id ) {
			throw new RuntimeException( 'Session sans identifiant.' );
		}

		// Données complètes (lignes d'achat, abonnement) : lues chez Stripe, jamais crues sur parole.
		$full = Arvator_Stripe::request( 'GET', 'checkout/sessions/' . rawurlencode( $session_id ), array( 'expand' => array( 'line_items', 'subscription.default_payment_method' ) ) );
		if ( is_wp_error( $full ) ) {
			throw new RuntimeException( $full->get_error_message() );
		}

		$email = (string) ( $full['customer_details']['email'] ?? $full['customer_email'] ?? '' );
		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			throw new RuntimeException( 'Email du client introuvable.' );
		}

		[ $user_id, $created ] = self::find_or_create_user( $email, (int) ( $full['client_reference_id'] ?? 0 ) );

		// Montant du robot = la ligne « robot » de la commande, réduction comprise (0 si un code promo l'offre). Avec un
		// code promo, le robot a un prix calculé pour ce paiement : c'est alors la ligne qui n'est pas un abonnement.
		$amount = -1;
		foreach ( (array) ( $full['line_items']['data'] ?? array() ) as $line ) {
			if ( '' === Arvator_Stripe::plan_for_price( (string) ( $line['price']['id'] ?? '' ) ) ) {
				$amount = max( 0, (int) ( $line['amount_total'] ?? 0 ) );
			}
		}
		if ( $amount < 0 ) {
			throw new RuntimeException( 'Ligne du robot introuvable dans la commande.' );
		}

		global $wpdb;
		$now      = gmdate( 'Y-m-d H:i:s' );
		$customer = (string) ( $full['customer'] ?? '' );
		// Empreinte de la carte (identique pour une même carte, sans en révéler le numéro) : sert à repérer l'auto-parrainage.
		$card_fp = (string) ( $full['subscription']['default_payment_method']['card']['fingerprint'] ?? '' );
		$wpdb->query( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare(
				'INSERT IGNORE INTO ' . Arvator_Repo::table( 'orders' ) . ' (user_id, stripe_session_id, stripe_payment_intent, stripe_invoice_id, stripe_customer_id, product, amount_cents, currency, status, is_test, card_fingerprint, created_at) VALUES (%d, %s, %s, %s, %s, %s, %d, %s, %s, %d, %s, %s)',
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
				substr( $card_fp, 0, 64 ),
				$now
			)
		);

		if ( is_array( $full['subscription'] ?? null ) ) {
			self::upsert_subscription( $user_id, $full['subscription'], $customer, ! $live );
		}

		$order_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . Arvator_Repo::table( 'orders' ) . ' WHERE stripe_session_id = %s', $session_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( $order_id > 0 ) {
			Arvator_Referral::on_purchase( $user_id, $order_id, $full, $live );
			Arvator_Promo::on_purchase( $user_id, $order_id, $full, $live );
		}

		// Licence : numéro de compte cTrader saisi à l'achat (s'il n'y en a pas déjà un), puis activation via n8n.
		$account = Arvator_License::clean_account( (string) ( $full['metadata']['compte'] ?? '' ) );
		if ( '' === Arvator_License::account_for( $user_id ) && Arvator_License::valid_account( $account ) && ! Arvator_License::activated( $user_id ) ) {
			update_user_meta( $user_id, 'arv_ctrader_account', $account );
		}
		$plan = is_array( $full['subscription'] ?? null ) ? Arvator_Stripe::plan_for_price( (string) ( $full['subscription']['items']['data'][0]['price']['id'] ?? '' ) ) : '';
		$plan = '' !== $plan ? $plan : (string) ( $full['metadata']['plan'] ?? 'monthly' );
		Arvator_License::on_paid( $user_id, $plan, '' !== (string) ( $full['invoice'] ?? '' ) ? 'pay:inv:' . $full['invoice'] : 'pay:sess:' . $session_id );

		// Langue de la page d'achat : retenue pour écrire au client dans sa langue (seulement si ce n'est pas celle du site).
		$locale = (string) ( $full['metadata']['langue'] ?? '' );
		if ( preg_match( '/^[a-z]{2,3}(_[A-Z]{2})?$/', $locale ) && get_locale() !== $locale ) {
			update_user_meta( $user_id, 'locale', $locale );
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
	public static function find_or_create_user( string $email, int $reference ): array {
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
		if ( $deleted ) {
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT user_id, plan FROM ' . Arvator_Repo::table( 'subscriptions' ) . ' WHERE stripe_subscription_id = %s', $sub_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( is_array( $row ) ) {
				Arvator_License::on_cancel( (int) $row['user_id'], (string) $row['plan'], 'res:sub:' . $sub_id );
			}
		}
	}

	/**
	 * Facture payée : renouvellement de la licence (le premier paiement est traité à l'achat) et récompense du parrain.
	 *
	 * @param array<string, mixed> $invoice
	 */
	private static function on_invoice_paid( array $invoice, bool $live ): void {
		Arvator_Referral::on_invoice_paid( $invoice, $live );
		$reason = (string) ( $invoice['billing_reason'] ?? '' );
		// Un renouvellement à 0 € (mois offert par un code promo) prolonge aussi la licence.
		if ( 'subscription_create' === $reason || ( (int) ( $invoice['amount_paid'] ?? 0 ) <= 0 && 'subscription_cycle' !== $reason ) ) {
			return;
		}
		$sub_id = Arvator_Stripe::invoice_subscription( $invoice );
		$inv_id = (string) ( $invoice['id'] ?? '' );
		if ( '' === $sub_id || '' === $inv_id ) {
			return;
		}
		global $wpdb;
		$sub = $wpdb->get_row( $wpdb->prepare( 'SELECT user_id, plan FROM ' . Arvator_Repo::table( 'subscriptions' ) . ' WHERE stripe_subscription_id = %s', $sub_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! is_array( $sub ) || ! Arvator_Repo::has_paid_order( (int) $sub['user_id'] ) ) {
			return;
		}
		Arvator_License::on_paid( (int) $sub['user_id'], (string) $sub['plan'], 'pay:inv:' . $inv_id );
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
		// Remboursement inférieur au prix payé pour le robot, robot offert (code promo) ou remboursement d'une autre
		// facture (un renouvellement) : on garde la trace, l'achat reste valide.
		$other_invoice = '' !== $invoice && '' !== (string) $order['stripe_invoice_id'] && $invoice !== (string) $order['stripe_invoice_id'];
		if ( (int) $order['amount_cents'] <= 0 || $other_invoice || $refunded < (int) $order['amount_cents'] ) {
			Arvator_Repo::log_event( 'refund_partial', (int) $order['user_id'], array( 'refunded_cents' => $refunded ) );
			return;
		}

		$user_id = (int) $order['user_id'];
		$now     = gmdate( 'Y-m-d H:i:s' );
		$wpdb->update( $orders, array( 'status' => 'refunded' ), array( 'id' => (int) $order['id'] ), array( '%s' ), array( '%d' ) );
		Arvator_Referral::on_order_reversed( (int) $order['id'], 'refund' );
		$wpdb->query( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare( 'UPDATE ' . Arvator_Repo::table( 'licenses' ) . " SET status = 'revoked', updated_at = %s WHERE user_id = %d AND status = 'active'", $now, $user_id )
		);

		// Arrêt de l'abonnement chez Stripe pour qu'il ne soit plus facturé, et coupure de la licence (via n8n).
		$subs = $wpdb->get_col( $wpdb->prepare( 'SELECT stripe_subscription_id FROM ' . Arvator_Repo::table( 'subscriptions' ) . " WHERE user_id = %d AND status IN ('active','trialing','past_due') AND stripe_subscription_id IS NOT NULL", $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$plan = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT plan FROM ' . Arvator_Repo::table( 'subscriptions' ) . ' WHERE user_id = %d ORDER BY id DESC LIMIT 1', $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		// Même origine que la résiliation que Stripe enverra ensuite : la licence n'est coupée qu'une fois.
		Arvator_License::on_cancel( $user_id, $plan, $subs ? 'res:sub:' . $subs[0] : 'res:order:' . (int) $order['id'] );
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

	/**
	 * Contestation bancaire : la récompense de parrainage liée à l'achat est retirée, et Hamid est prévenu.
	 * L'achat lui-même n'est pas modifié ici (Stripe tranche la contestation).
	 *
	 * @param array<string, mixed> $dispute
	 */
	private static function on_dispute( array $dispute ): void {
		$charge_id = (string) ( is_array( $dispute['charge'] ?? null ) ? ( $dispute['charge']['id'] ?? '' ) : ( $dispute['charge'] ?? '' ) );
		if ( '' === $charge_id ) {
			return;
		}
		$charge = Arvator_Stripe::request( 'GET', 'charges/' . rawurlencode( $charge_id ) );
		if ( is_wp_error( $charge ) ) {
			throw new RuntimeException( $charge->get_error_message() );
		}
		global $wpdb;
		$orders   = Arvator_Repo::table( 'orders' );
		$invoice  = (string) ( $charge['invoice'] ?? '' );
		$customer = (string) ( $charge['customer'] ?? '' );
		$order    = null;
		if ( '' !== $invoice ) {
			$order = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $orders WHERE stripe_invoice_id = %s LIMIT 1", $invoice ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		if ( ! $order && '' !== $customer ) {
			$order = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $orders WHERE stripe_customer_id = %s ORDER BY id DESC LIMIT 1", $customer ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		if ( ! is_array( $order ) ) {
			Arvator_Repo::log_event( 'dispute_unmatched', null, array( 'charge' => $charge_id ) );
			return;
		}
		Arvator_Referral::on_order_reversed( (int) $order['id'], 'dispute' );
		Arvator_Repo::log_event( 'dispute', (int) $order['user_id'], array( 'charge' => $charge_id ) );
		wp_mail( Arvator_Site::EMAIL, 'Arvator : contestation bancaire reçue', "Un client a contesté un paiement auprès de sa banque (paiement $charge_id).\nLa récompense de parrainage liée a été retirée. Répondez à la contestation depuis votre tableau de bord Stripe.\n" . "\n" . Arvator_Site::signature() );
	}

	/* --------------------------------------------------------------------- Email */

	public static function send_confirmation( int $user_id, bool $created ): void {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user instanceof WP_User ) {
			return;
		}
		$switched = switch_to_user_locale( $user_id ); // email dans la langue du client (celle du site par défaut)
		$lines    = array( __( 'Bonjour,', 'arvator-core' ), '', __( 'Merci pour votre commande : votre paiement a bien été reçu.', 'arvator-core' ), '' );
		if ( $created ) {
			$key = get_password_reset_key( $user );
			if ( is_string( $key ) ) {
				$link    = network_site_url( 'wp-login.php?action=rp&key=' . rawurlencode( $key ) . '&login=' . rawurlencode( $user->user_login ), 'login' );
				$lines[] = __( 'Votre espace membre est créé. Choisissez votre mot de passe ici (lien valable 24 heures) :', 'arvator-core' );
				$lines[] = $link;
				$lines[] = '';
			}
		}
		$lines[] = __( 'Retrouvez votre achat, votre abonnement et le téléchargement du robot dans votre espace membre :', 'arvator-core' );
		$lines[] = Arvator_Member::url();
		$lines[] = '';
		$lines[] = __( 'Rappel : les résultats présentés sur le site sont des performances simulées. Le trading comporte un risque élevé de perte en capital.', 'arvator-core' );
		$lines[] = '';
		$lines[] = Arvator_Site::signature();

		wp_mail( $user->user_email, __( 'Arvator : votre commande est confirmée', 'arvator-core' ), implode( "\n", $lines ) );
		if ( $switched ) {
			restore_previous_locale();
		}
	}
}

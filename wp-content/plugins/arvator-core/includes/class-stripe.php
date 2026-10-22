<?php
/**
 * Dialogue avec Stripe (API REST) et vérification des messages (webhooks).
 *
 * Aucun secret ici : tout vient de wp-config.php.
 *   define( 'ARVATOR_STRIPE_SECRET', 'rk_test_...' );          // clé (restreinte de préférence)
 *   define( 'ARVATOR_STRIPE_WEBHOOK_SECRET', 'whsec_...' );    // secret du webhook
 *   define( 'ARVATOR_STRIPE_PRICE_BOT', 'price_...' );         // robot, paiement unique
 *   define( 'ARVATOR_STRIPE_PRICE_ANNUAL', 'price_...' );      // abonnement annuel
 *   define( 'ARVATOR_STRIPE_PRICE_MONTHLY', 'price_...' );     // abonnement mensuel
 * Les clés « test » (bac à sable) et « live » (réel) ont chacune leurs propres prix : on change les 5 lignes ensemble.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Arvator_Stripe {

	private const API = 'https://api.stripe.com/v1/';

	public static function secret(): string {
		return defined( 'ARVATOR_STRIPE_SECRET' ) ? trim( (string) ARVATOR_STRIPE_SECRET ) : '';
	}

	public static function webhook_secret(): string {
		return defined( 'ARVATOR_STRIPE_WEBHOOK_SECRET' ) ? trim( (string) ARVATOR_STRIPE_WEBHOOK_SECRET ) : '';
	}

	/** La clé configurée est-elle une clé de test (bac à sable) ? */
	public static function is_test_key(): bool {
		return str_starts_with( self::secret(), 'sk_test_' ) || str_starts_with( self::secret(), 'rk_test_' );
	}

	/** @param string $kind 'bot' | 'annual' | 'monthly' */
	public static function price( string $kind ): string {
		$const = array(
			'bot'     => 'ARVATOR_STRIPE_PRICE_BOT',
			'annual'  => 'ARVATOR_STRIPE_PRICE_ANNUAL',
			'monthly' => 'ARVATOR_STRIPE_PRICE_MONTHLY',
		)[ $kind ] ?? '';
		return ( '' !== $const && defined( $const ) ) ? trim( (string) constant( $const ) ) : '';
	}

	/** Plan (annual/monthly) correspondant à un identifiant de prix Stripe, sinon ''. */
	public static function plan_for_price( string $price_id ): string {
		if ( '' === $price_id ) {
			return '';
		}
		foreach ( array( 'annual', 'monthly' ) as $plan ) {
			if ( self::price( $plan ) === $price_id ) {
				return $plan;
			}
		}
		return '';
	}

	/** Le paiement peut-il être proposé ? (clé + 3 prix renseignés) */
	public static function configured(): bool {
		return '' !== self::secret()
			&& '' !== self::price( 'bot' )
			&& '' !== self::price( 'annual' )
			&& '' !== self::price( 'monthly' );
	}

	/**
	 * Appel à l'API Stripe.
	 *
	 * @param array<string, mixed> $params
	 * @return array<string, mixed>|WP_Error
	 */
	public static function request( string $method, string $path, array $params = array(), string $idempotency_key = '' ) {
		$secret = self::secret();
		if ( '' === $secret ) {
			return new WP_Error( 'arvator_stripe_config', 'Clé Stripe absente.' );
		}

		$url     = self::API . ltrim( $path, '/' );
		$headers = array(
			'Authorization' => 'Bearer ' . $secret,
			'Content-Type'  => 'application/x-www-form-urlencoded',
		);
		if ( '' !== $idempotency_key && 'POST' === $method ) {
			$headers['Idempotency-Key'] = substr( $idempotency_key, 0, 200 );
		}
		$args = array(
			'method'  => $method,
			'headers' => $headers,
			'timeout' => 25,
		);
		$body = self::encode( $params );
		if ( 'GET' === $method || 'DELETE' === $method ) {
			if ( '' !== $body ) {
				$url .= '?' . $body;
			}
		} else {
			$args['body'] = $body;
		}

		$res = wp_remote_request( $url, $args );
		if ( is_wp_error( $res ) ) {
			return new WP_Error( 'arvator_stripe_http', 'Stripe injoignable.' );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$data = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'arvator_stripe_bad', 'Réponse Stripe illisible (HTTP ' . $code . ').' );
		}
		if ( $code >= 400 ) {
			$msg = isset( $data['error']['message'] ) ? (string) $data['error']['message'] : 'Erreur Stripe';
			return new WP_Error( 'arvator_stripe_api', 'HTTP ' . $code . ' : ' . $msg );
		}
		return $data;
	}

	/** Encodage « formulaire » des paramètres imbriqués (a[b][0]=c), booléens en true/false. */
	public static function encode( array $params ): string {
		$flat = array();
		$walk = static function ( $value, string $key ) use ( &$walk, &$flat ): void {
			if ( is_array( $value ) ) {
				foreach ( $value as $k => $v ) {
					$walk( $v, $key . '[' . $k . ']' );
				}
				return;
			}
			if ( is_bool( $value ) ) {
				$value = $value ? 'true' : 'false';
			}
			$flat[] = rawurlencode( $key ) . '=' . rawurlencode( (string) $value );
		};
		foreach ( $params as $k => $v ) {
			$walk( $v, (string) $k );
		}
		return implode( '&', $flat );
	}

	/** Identifiant de l'abonnement d'une facture (son emplacement dépend de la version de l'API Stripe). */
	public static function invoice_subscription( array $invoice ): string {
		$candidates = array(
			$invoice['subscription'] ?? null,
			$invoice['parent']['subscription_details']['subscription'] ?? null,
			$invoice['lines']['data'][0]['subscription'] ?? null,
		);
		foreach ( $candidates as $c ) {
			if ( is_array( $c ) ) {
				$c = $c['id'] ?? null;
			}
			if ( is_string( $c ) && '' !== $c ) {
				return $c;
			}
		}
		return '';
	}

	/**
	 * Vérifie la signature d'un webhook : en-tête « t=...,v1=... », HMAC-SHA256 de « t.corps ».
	 * Refuse les messages trop anciens (5 minutes) pour empêcher le rejeu.
	 */
	public static function verify_signature( string $payload, string $header, string $secret, int $tolerance = 300, ?int $now = null ): bool {
		if ( '' === $secret || '' === $header ) {
			return false;
		}
		$timestamp = 0;
		$sigs      = array();
		foreach ( explode( ',', $header ) as $part ) {
			$kv = explode( '=', trim( $part ), 2 );
			if ( 2 !== count( $kv ) ) {
				continue;
			}
			if ( 't' === $kv[0] ) {
				$timestamp = (int) $kv[1];
			} elseif ( 'v1' === $kv[0] ) {
				$sigs[] = $kv[1];
			}
		}
		if ( $timestamp <= 0 || ! $sigs ) {
			return false;
		}
		if ( abs( ( $now ?? time() ) - $timestamp ) > $tolerance ) {
			return false;
		}
		$expected = hash_hmac( 'sha256', $timestamp . '.' . $payload, $secret );
		foreach ( $sigs as $sig ) {
			if ( hash_equals( $expected, $sig ) ) {
				return true;
			}
		}
		return false;
	}
}

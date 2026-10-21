<?php
/**
 * Limite les tentatives de connexion : 5 échecs pour un même identifiant depuis une même adresse,
 * ou 20 échecs depuis une même adresse, bloquent la connexion pendant 15 minutes.
 *
 * L'adresse utilisée est REMOTE_ADDR (jamais un en-tête que le visiteur peut falsifier).
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Arvator_Login_Limiter {

	public const LOCK_MESSAGE = 'Trop de tentatives. Réessayez dans 15 minutes.';

	private const MAX_PER_USER = 5;
	private const MAX_PER_IP   = 20;
	private const WINDOW       = 15 * MINUTE_IN_SECONDS;

	public static function init(): void {
		add_action( 'wp_login_failed', array( self::class, 'record_failure' ), 10, 1 );
		add_action( 'wp_login', array( self::class, 'reset_user' ), 10, 1 );
		// Priorité 100 : s'applique après toutes les vérifications, même avec le bon mot de passe.
		add_filter( 'authenticate', array( self::class, 'maybe_block' ), 100, 2 );
	}

	/**
	 * @param \WP_User|\WP_Error|null $user
	 * @return \WP_User|\WP_Error|null
	 */
	public static function maybe_block( $user, string $username ) {
		if ( '' === $username ) {
			return $user;
		}
		if ( self::count( self::user_key( $username ) ) >= self::MAX_PER_USER || self::count( self::ip_key() ) >= self::MAX_PER_IP ) {
			return new \WP_Error( 'arvator_locked', self::LOCK_MESSAGE );
		}
		return $user;
	}

	public static function record_failure( string $username ): void {
		self::bump( self::user_key( $username ) );
		self::bump( self::ip_key() );
	}

	public static function reset_user( string $username ): void {
		delete_transient( self::user_key( $username ) );
	}

	private static function ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '';
		return false !== filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
	}

	private static function user_key( string $username ): string {
		return 'arv_ll_u_' . substr( hash( 'sha256', self::ip() . '|' . strtolower( trim( $username ) ) ), 0, 32 );
	}

	private static function ip_key(): string {
		return 'arv_ll_i_' . substr( hash( 'sha256', self::ip() ), 0, 32 );
	}

	private static function count( string $key ): int {
		return (int) get_transient( $key );
	}

	private static function bump( string $key ): void {
		set_transient( $key, self::count( $key ) + 1, self::WINDOW );
	}
}

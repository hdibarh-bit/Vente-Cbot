<?php
/**
 * Durcissement de WordPress : on retire ce qui n'est pas utile et qui sert aux attaques.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Arvator_Hardening {

	public static function init(): void {
		// Plus d'éditeur de thème/extensions dans le tableau de bord : un compte volé ne peut plus injecter de code.
		if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
			define( 'DISALLOW_FILE_EDIT', true );
		}

		// XML-RPC : porte d'entrée classique des attaques par force brute (même désactivé, il laissait
		// passer « system.multicall »). Le site n'en a pas besoin : on coupe la requête net.
		add_filter( 'xmlrpc_enabled', '__return_false' );
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			status_header( 403 );
			header( 'Content-Type: text/plain; charset=utf-8' );
			exit( 'XML-RPC est désactivé sur ce site.' );
		}

		// Discrétion : ne pas annoncer la version de WordPress.
		add_filter( 'the_generator', '__return_empty_string' );

		// Liste des comptes : bloquée pour les visiteurs (API, adresse ?author=1, plan du site).
		add_filter( 'rest_endpoints', array( self::class, 'hide_users_endpoints' ) );
		add_filter( 'wp_sitemaps_add_provider', array( self::class, 'drop_users_sitemap' ), 10, 2 );
		add_action( 'template_redirect', array( self::class, 'block_author_pages' ), 1 );

		// Connexion : un seul message d'erreur, qui ne dit pas si l'identifiant existe
		// (sauf le message de blocage temporaire, qui doit rester lisible).
		add_filter(
			'login_errors',
			static function ( string $errors ): string {
				return str_contains( $errors, Arvator_Login_Limiter::LOCK_MESSAGE ) ? $errors : 'Identifiant ou mot de passe incorrect.';
			}
		);

		// En-têtes de sécurité sur le site public.
		add_filter( 'wp_headers', array( self::class, 'headers' ) );
		add_action( 'send_headers', array( self::class, 'send_extra_headers' ) );
	}

	/**
	 * @param array<string, mixed> $endpoints
	 * @return array<string, mixed>
	 */
	public static function hide_users_endpoints( array $endpoints ): array {
		if ( ! is_user_logged_in() ) {
			unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
		}
		return $endpoints;
	}

	/**
	 * @param object|false $provider
	 * @return object|false
	 */
	public static function drop_users_sitemap( $provider, string $name ) {
		return 'users' === $name ? false : $provider;
	}

	public static function block_author_pages(): void {
		if ( is_author() ) {
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	}

	/**
	 * @param array<string, string> $headers
	 * @return array<string, string>
	 */
	public static function headers( array $headers ): array {
		unset( $headers['X-Pingback'] );
		return $headers;
	}

	public static function send_extra_headers(): void {
		if ( headers_sent() ) {
			return;
		}
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		header( 'Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(self)' );
	}
}

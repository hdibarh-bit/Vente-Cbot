<?php
/**
 * Rôle « Client Arvator » : un client n'a AUCUN accès au tableau de bord WordPress.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Arvator_Roles {

	public const CLIENT = 'arvator_client';

	public static function init(): void {
		add_filter( 'show_admin_bar', array( self::class, 'admin_bar' ) );
		// « admin_menu » arrive avant le contrôle d'accès des écrans (qui afficherait une page d'erreur) ; « admin_init » couvre le reste.
		add_action( 'admin_menu', array( self::class, 'block_admin' ), 0 );
		add_action( 'admin_init', array( self::class, 'block_admin' ), 1 );
		add_filter( 'login_redirect', array( self::class, 'login_redirect' ), 10, 3 );
		add_filter( 'wp_is_application_passwords_available_for_user', array( self::class, 'no_app_passwords' ), 10, 2 );
	}

	/** Un seul droit : « read ». Aucun droit de publier, modifier ou téléverser. */
	public static function register_role(): void {
		if ( null === get_role( self::CLIENT ) ) {
			add_role( self::CLIENT, 'Client Arvator', array( 'read' => true ) );
		}
	}

	/** Vrai si l'utilisateur est un client « pur » (et pas aussi un éditeur ou un administrateur). */
	public static function is_client( ?int $user_id = null ): bool {
		$user = null === $user_id ? wp_get_current_user() : get_userdata( $user_id );
		if ( ! $user instanceof WP_User || 0 === $user->ID ) {
			return false;
		}
		return in_array( self::CLIENT, (array) $user->roles, true ) && ! user_can( $user, 'edit_posts' );
	}

	public static function admin_bar( bool $show ): bool {
		return self::is_client() ? false : $show;
	}

	public static function block_admin(): void {
		if ( ! is_user_logged_in() || ! self::is_client() ) {
			return;
		}
		if ( wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		global $pagenow;
		if ( 'admin-post.php' === $pagenow ) {
			return; // les actions de l'espace membre passent par ce fichier.
		}
		wp_safe_redirect( Arvator_Member::url() );
		exit;
	}

	/**
	 * @param string|\WP_User|\WP_Error $user
	 */
	public static function login_redirect( string $redirect_to, string $requested, $user ): string {
		if ( $user instanceof WP_User && self::is_client( $user->ID ) ) {
			return Arvator_Member::url();
		}
		return $redirect_to;
	}

	public static function no_app_passwords( bool $available, WP_User $user ): bool {
		return in_array( self::CLIENT, (array) $user->roles, true ) ? false : $available;
	}
}

<?php
/**
 * Accès aux données des clients.
 *
 * RÈGLE D'OR : toute lecture est limitée à UN identifiant d'utilisateur passé explicitement.
 * Cet identifiant vient toujours de la session (get_current_user_id()), jamais d'un champ de formulaire
 * ni d'une adresse. Toutes les requêtes sont préparées.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Arvator_Repo {

	public static function table( string $name ): string {
		global $wpdb;
		return $wpdb->prefix . 'arvator_' . $name;
	}

	/** @return array<int, array<string, mixed>> */
	public static function orders_for_user( int $user_id ): array {
		return self::rows( 'orders', $user_id, 'created_at DESC, id DESC' );
	}

	/** @return array<int, array<string, mixed>> */
	public static function subscriptions_for_user( int $user_id ): array {
		return self::rows( 'subscriptions', $user_id, 'created_at DESC, id DESC' );
	}

	/** @return array<int, array<string, mixed>> */
	public static function licenses_for_user( int $user_id ): array {
		return self::rows( 'licenses', $user_id, 'expires_at DESC, id DESC' );
	}

	/**
	 * Le client a-t-il le droit de télécharger le robot ? Oui s'il a au moins un achat payé,
	 * ou un abonnement actif.
	 */
	public static function is_entitled( int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}
		global $wpdb;

		$orders = (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table( 'orders' ) . " WHERE user_id = %d AND status = 'paid'", $user_id ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
		if ( $orders > 0 ) {
			return true;
		}

		$subs = (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table( 'subscriptions' ) . " WHERE user_id = %d AND status IN ('active','trialing','past_due')", $user_id ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
		return $subs > 0;
	}

	/**
	 * Journalise un événement. Avec $external_id, un même événement n'est enregistré qu'une fois
	 * (retourne false s'il existait déjà).
	 *
	 * @param array<string, mixed> $payload
	 */
	public static function log_event( string $type, ?int $user_id, array $payload = array(), ?string $external_id = null ): bool {
		global $wpdb;

		// NULL (et non une chaîne vide) quand il n'y a pas d'identifiant externe : plusieurs lignes sans identifiant
		// doivent pouvoir coexister malgré l'unicité de la colonne.
		$external_sql = null === $external_id ? 'NULL' : $wpdb->prepare( '%s', substr( $external_id, 0, 190 ) );

		$res = $wpdb->query( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare(
				'INSERT IGNORE INTO ' . self::table( 'events' ) . ' (type, user_id, external_id, payload, created_at) VALUES (%s, %d, ' . str_replace( '%', '%%', (string) $external_sql ) . ', %s, %s)', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				substr( $type, 0, 40 ),
				(int) $user_id,
				wp_json_encode( $payload ),
				gmdate( 'Y-m-d H:i:s' )
			)
		);
		return 1 === $res;
	}

	/** Supprime les lignes de démonstration (et seulement elles). */
	public static function delete_test_rows_for_user( int $user_id ): void {
		global $wpdb;
		foreach ( array( 'orders', 'subscriptions', 'licenses' ) as $t ) {
			$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table( $t ) . ' WHERE user_id = %d AND is_test = 1', $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table( 'events' ) . ' WHERE user_id = %d', $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function rows( string $table, int $user_id, string $order_by ): array {
		if ( $user_id <= 0 ) {
			return array();
		}
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM ' . self::table( $table ) . ' WHERE user_id = %d ORDER BY ' . $order_by, $user_id ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}
}

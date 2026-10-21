<?php
/**
 * Export et effacement des données personnelles (outils RGPD de WordPress : Outils > Exporter / Effacer).
 *
 * Effacement : les pièces comptables (achats, abonnements) sont conservées 10 ans comme la loi l'exige, mais
 * détachées du compte (aucun nom, aucun email) ; les licences et le journal du client sont supprimés.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Arvator_Privacy {

	public static function init(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( self::class, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( self::class, 'register_eraser' ) );
	}

	/**
	 * @param array<string, mixed> $exporters
	 * @return array<string, mixed>
	 */
	public static function register_exporter( array $exporters ): array {
		$exporters['arvator'] = array(
			'exporter_friendly_name' => 'Arvator : achats, abonnements, licences',
			'callback'               => array( self::class, 'export' ),
		);
		return $exporters;
	}

	/**
	 * @param array<string, mixed> $erasers
	 * @return array<string, mixed>
	 */
	public static function register_eraser( array $erasers ): array {
		$erasers['arvator'] = array(
			'eraser_friendly_name' => 'Arvator : achats, abonnements, licences',
			'callback'             => array( self::class, 'erase' ),
		);
		return $erasers;
	}

	/** @return array{data: array<int, array<string, mixed>>, done: bool} */
	public static function export( string $email ): array {
		$user = get_user_by( 'email', $email );
		if ( ! $user instanceof WP_User ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}
		$uid  = (int) $user->ID;
		$data = array();

		foreach ( Arvator_Repo::orders_for_user( $uid ) as $o ) {
			$data[] = self::item( 'arvator-achats', 'Achats Arvator', 'order-' . $o['id'], array(
				'Date'    => (string) $o['created_at'],
				'Produit' => (string) $o['product'],
				'Montant' => number_format( (int) $o['amount_cents'] / 100, 2, ',', ' ' ) . ' ' . strtoupper( (string) $o['currency'] ),
				'État'    => (string) $o['status'],
			) );
		}
		foreach ( Arvator_Repo::subscriptions_for_user( $uid ) as $s ) {
			$data[] = self::item( 'arvator-abonnements', 'Abonnements Arvator', 'sub-' . $s['id'], array(
				'Formule'   => (string) $s['plan'],
				'État'      => (string) $s['status'],
				'Échéance'  => (string) $s['current_period_end'],
				'Créé le'   => (string) $s['created_at'],
			) );
		}
		foreach ( Arvator_Repo::licenses_for_user( $uid ) as $l ) {
			$data[] = self::item( 'arvator-licences', 'Licences Arvator', 'lic-' . $l['id'], array(
				'Formule'           => (string) $l['plan'],
				'Compte cTrader'    => (string) $l['account_number'],
				'Valable jusqu’au'  => (string) $l['expires_at'],
				'État'              => (string) $l['status'],
				'Clé'               => (string) $l['license_key'],
			) );
		}

		$ref = Arvator_Referral::referrer( $uid );
		if ( $ref ) {
			$data[] = self::item( 'arvator-parrainage', 'Parrainage Arvator', 'referrer-' . $uid, array(
				'Code'                => (string) $ref['code'],
				'Inscrit le'          => (string) $ref['created_at'],
				'Titulaire du compte' => (string) $ref['holder_name'],
				'IBAN'                => Arvator_Referral::mask_iban( Arvator_Referral::decrypt( (string) $ref['iban_enc'] ) ),
				'Statut'              => (string) $ref['entity_type'],
				'SIRET'               => (string) $ref['siret'],
			) );
			foreach ( Arvator_Referral::commissions_for( $uid ) as $c ) {
				$data[] = self::item( 'arvator-recompenses', 'Récompenses de recommandation Arvator', 'com-' . $c['id'], array(
					'Date'    => (string) $c['created_at'],
					'Type'    => Arvator_Referral::kind_label( (string) $c['kind'] ),
					'Montant' => number_format( (int) $c['amount_cents'] / 100, 2, ',', ' ' ) . ' EUR',
					'État'    => Arvator_Referral::status_label( (string) $c['status'] ),
				) );
			}
		}

		return array(
			'data' => $data,
			'done' => true,
		);
	}

	/** @return array{items_removed: bool, items_retained: bool, messages: string[], done: bool} */
	public static function erase( string $email ): array {
		$user = get_user_by( 'email', $email );
		if ( ! $user instanceof WP_User ) {
			return array(
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			);
		}
		global $wpdb;
		$uid      = (int) $user->ID;
		$retained = false;

		foreach ( array( 'orders', 'subscriptions' ) as $t ) {
			$count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Arvator_Repo::table( $t ) . ' WHERE user_id = %d', $uid ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( $count > 0 ) {
				$retained = true;
				$wpdb->query( $wpdb->prepare( 'UPDATE ' . Arvator_Repo::table( $t ) . ' SET user_id = 0 WHERE user_id = %d', $uid ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}
		}
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . Arvator_Repo::table( 'licenses' ) . ' WHERE user_id = %d', $uid ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Parrainage : fiche de parrain (IBAN compris) et rattachements supprimés ; les récompenses (pièces comptables)
		// sont gardées sans lien avec l'identité.
		$wpdb->delete( Arvator_Repo::table( 'referrers' ), array( 'user_id' => $uid ), array( '%d' ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . Arvator_Repo::table( 'referrals' ) . ' WHERE referrer_user_id = %d OR referee_user_id = %d', $uid, $uid ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		foreach ( array( 'referrer_user_id', 'referee_user_id' ) as $col ) {
			$n = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Arvator_Repo::table( 'commissions' ) . " WHERE $col = %d", $uid ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $n > 0 ) {
				$retained = true;
				$wpdb->query( $wpdb->prepare( 'UPDATE ' . Arvator_Repo::table( 'commissions' ) . " SET $col = 0 WHERE $col = %d", $uid ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
		}
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . Arvator_Repo::table( 'events' ) . ' WHERE user_id = %d', $uid ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array(
			'items_removed'  => true,
			'items_retained' => $retained,
			'messages'       => $retained ? array( 'Les pièces comptables (achats, abonnements, récompenses de recommandation) sont conservées 10 ans, sans lien avec votre identité.' ) : array(),
			'done'           => true,
		);
	}

	/**
	 * @param array<string, string> $fields
	 * @return array<string, mixed>
	 */
	private static function item( string $group_id, string $label, string $item_id, array $fields ): array {
		$out = array();
		foreach ( $fields as $name => $value ) {
			$out[] = array(
				'name'  => $name,
				'value' => $value,
			);
		}
		return array(
			'group_id'    => $group_id,
			'group_label' => $label,
			'item_id'     => $item_id,
			'data'        => $out,
		);
	}
}

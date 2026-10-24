<?php
/**
 * Codes promo, créés par Hamid dans Arvator > Codes promo et saisis par le client sur la page d'achat.
 *
 * Un code donne UN avantage (Stripe n'accepte qu'une réduction par paiement) :
 * - « robot » : x % sur le prix du robot (paiement unique) ;
 * - « abonnement » : x % sur l'abonnement (100 % = offert), pour la 1re période, pendant N mois (formule mensuelle)
 *   ou à chaque paiement. Le code peut être réservé à la formule mensuelle ou annuelle.
 *
 * À la création, le site crée le « coupon » correspondant chez Stripe (réduction appliquée par Stripe, jamais calculée
 * ici). Le code lui-même n'existe que sur ce site : il est vérifié au moment de l'achat (actif, date, nombre
 * d'utilisations, formule), puis le coupon est transmis à Stripe. Chaque client n'achète le robot qu'une fois : un code
 * ne sert donc qu'une fois par client. Un coupon Stripe ne se modifie pas : pour changer un code, on le désactive et on
 * en crée un nouveau. Les codes du bac à sable (test) ne marchent pas avec la clé réelle, et inversement.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Arvator_Promo {

	public const TARGETS   = array( 'bot', 'sub' );
	public const DURATIONS = array( 'once', 'repeating', 'forever' );
	public const PLANS     = array( 'both', 'monthly', 'annual' );

	public static function normalize( string $code ): string {
		$code = strtoupper( trim( $code ) );
		return preg_match( '/^[A-Z0-9_-]{3,30}$/', $code ) ? $code : '';
	}

	private static function mode_is_test(): int {
		return Arvator_Stripe::is_test_key() ? 1 : 0;
	}

	/** Y a-t-il au moins un code utilisable (pour afficher le champ sur la page d'achat) ? */
	public static function any_active(): bool {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Arvator_Repo::table( 'promo_codes' ) . ' WHERE active = 1 AND is_test = %d AND (expires_at IS NULL OR expires_at > %s)', self::mode_is_test(), gmdate( 'Y-m-d H:i:s' ) ) ) > 0; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/** @return array<string, mixed>|null */
	public static function get( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Arvator_Repo::table( 'promo_codes' ) . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return is_array( $row ) ? $row : null;
	}

	/** @return array<int, array<string, mixed>> */
	public static function all(): array {
		global $wpdb;
		return (array) $wpdb->get_results( 'SELECT * FROM ' . Arvator_Repo::table( 'promo_codes' ) . ' ORDER BY active DESC, id DESC', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public static function uses( int $id ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Arvator_Repo::table( 'promo_uses' ) . ' WHERE promo_id = %d', $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Vérifie un code saisi à l'achat pour la formule choisie.
	 *
	 * @return array<string, mixed>|string  le code (ligne) s'il est utilisable, sinon la raison : 'promo' | 'promo_formule'
	 */
	public static function check( string $typed, string $plan ) {
		$code = self::normalize( $typed );
		if ( '' === $code ) {
			return 'promo';
		}
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Arvator_Repo::table( 'promo_codes' ) . ' WHERE code = %s AND is_test = %d', $code, self::mode_is_test() ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! is_array( $row ) || 1 !== (int) $row['active'] || '' === (string) $row['stripe_coupon'] ) {
			return 'promo';
		}
		if ( ! empty( $row['expires_at'] ) && (string) $row['expires_at'] <= gmdate( 'Y-m-d H:i:s' ) ) {
			return 'promo';
		}
		if ( (int) $row['max_uses'] > 0 && self::uses( (int) $row['id'] ) >= (int) $row['max_uses'] ) {
			return 'promo';
		}
		if ( 'both' !== $row['plans'] && $plan !== $row['plans'] ) {
			return 'promo_formule';
		}
		return $row;
	}

	/** Ce que donne le code, en clair (page d'achat, administration). */
	public static function describe( array $row ): string {
		$pct = (int) $row['percent'];
		if ( 'bot' === $row['target'] ) {
			/* translators: %d: pourcentage de réduction. */
			return 100 === $pct ? __( 'robot offert', 'arvator-core' ) : sprintf( __( '-%d %% sur le prix du robot', 'arvator-core' ), $pct );
		}
		$months = (int) $row['months'];
		if ( 'repeating' === $row['duration'] ) {
			return 100 === $pct
				/* translators: %d: nombre de mois. */
				? sprintf( _n( '%d mois d’abonnement offert', '%d mois d’abonnement offerts', $months, 'arvator-core' ), $months )
				/* translators: 1: pourcentage, 2: nombre de mois. */
				: sprintf( _n( '-%1$d %% sur l’abonnement pendant %2$d mois', '-%1$d %% sur l’abonnement pendant %2$d mois', $months, 'arvator-core' ), $pct, $months );
		}
		if ( 'forever' === $row['duration'] ) {
			/* translators: %d: pourcentage de réduction. */
			return 100 === $pct ? __( 'abonnement offert tant qu’il n’est pas résilié', 'arvator-core' ) : sprintf( __( '-%d %% sur l’abonnement, à chaque paiement', 'arvator-core' ), $pct );
		}
		$first = array(
			'monthly' => array( __( '1er mois d’abonnement offert', 'arvator-core' ), /* translators: %d: pourcentage. */ __( '-%d %% sur le 1er mois d’abonnement', 'arvator-core' ) ),
			'annual'  => array( __( '1re année d’abonnement offerte', 'arvator-core' ), /* translators: %d: pourcentage. */ __( '-%d %% sur la 1re année d’abonnement', 'arvator-core' ) ),
			'both'    => array( __( '1re période d’abonnement offerte (1er mois ou 1re année)', 'arvator-core' ), /* translators: %d: pourcentage. */ __( '-%d %% sur la 1re période d’abonnement (1er mois ou 1re année)', 'arvator-core' ) ),
		)[ (string) $row['plans'] ] ?? array( '', '' );
		return 100 === $pct ? $first[0] : sprintf( $first[1], $pct );
	}

	/**
	 * Valide les réglages d'un nouveau code (formulaire de l'administration).
	 *
	 * @param array<string, mixed> $in
	 * @return array<string, mixed>|WP_Error
	 */
	public static function validate( array $in ) {
		$code     = self::normalize( (string) ( $in['code'] ?? '' ) );
		$target   = (string) ( $in['target'] ?? '' );
		$percent  = (int) ( $in['percent'] ?? 0 );
		$duration = 'bot' === $target ? 'once' : (string) ( $in['duration'] ?? '' );
		$months   = 'repeating' === $duration ? (int) ( $in['months'] ?? 0 ) : 0;
		$plans    = 'bot' === $target ? 'both' : (string) ( $in['plans'] ?? 'both' );
		$max_uses = max( 0, (int) ( $in['max_uses'] ?? 0 ) );
		$until    = trim( (string) ( $in['expires'] ?? '' ) );

		if ( '' === $code ) {
			return new WP_Error( 'promo', 'Code non valable : 3 à 30 caractères, lettres, chiffres, tiret ou tiret bas (par exemple NOEL30).' );
		}
		if ( ! in_array( $target, self::TARGETS, true ) || ! in_array( $duration, self::DURATIONS, true ) || ! in_array( $plans, self::PLANS, true ) ) {
			return new WP_Error( 'promo', 'Choisissez ce que le code réduit (robot ou abonnement) et pour combien de temps.' );
		}
		if ( $percent < 1 || $percent > 100 ) {
			return new WP_Error( 'promo', 'Le pourcentage doit être compris entre 1 et 100 (100 = offert).' );
		}
		if ( 'repeating' === $duration && ( $months < 1 || $months > 36 ) ) {
			return new WP_Error( 'promo', 'Indiquez un nombre de mois entre 1 et 36.' );
		}
		if ( 'repeating' === $duration && 'monthly' !== $plans ) {
			return new WP_Error( 'promo', 'Une durée en mois ne s’applique qu’à la formule mensuelle. Pour l’annuel, choisissez « la 1re période » (= la 1re année) ou « à chaque paiement ».' );
		}
		$expires = null;
		if ( '' !== $until ) {
			$day = DateTimeImmutable::createFromFormat( '!Y-m-d', $until, wp_timezone() );
			if ( ! $day ) {
				return new WP_Error( 'promo', 'Date de fin non valable.' );
			}
			$end = $day->setTime( 23, 59, 59 );
			if ( $end->getTimestamp() <= time() ) {
				return new WP_Error( 'promo', 'La date de fin est déjà passée.' );
			}
			$expires = gmdate( 'Y-m-d H:i:s', $end->getTimestamp() );
		}
		global $wpdb;
		$exists = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Arvator_Repo::table( 'promo_codes' ) . ' WHERE code = %s AND is_test = %d', $code, self::mode_is_test() ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( $exists ) {
			return new WP_Error( 'promo', 'Ce code existe déjà. Choisissez un autre nom.' );
		}
		return compact( 'code', 'target', 'percent', 'duration', 'months', 'plans', 'max_uses', 'expires' );
	}

	/**
	 * Paramètres du coupon Stripe correspondant (la réduction n'est appliquée qu'aux produits visés).
	 *
	 * @param array<string, mixed> $c         code validé
	 * @param array<int, string>   $products  produits Stripe visés
	 * @return array<string, mixed>
	 */
	public static function coupon_params( array $c, array $products ): array {
		$params = array(
			'name'        => substr( 'Arvator ' . $c['code'], 0, 40 ),
			'percent_off' => (int) $c['percent'],
			'duration'    => (string) $c['duration'],
			'applies_to'  => array( 'products' => array_values( $products ) ),
			'metadata'    => array(
				'source' => 'arvator',
				'code'   => (string) $c['code'],
			),
		);
		if ( 'repeating' === $c['duration'] ) {
			$params['duration_in_months'] = (int) $c['months'];
		}
		if ( (int) $c['max_uses'] > 0 ) {
			$params['max_redemptions'] = (int) $c['max_uses'];
		}
		if ( ! empty( $c['expires'] ) ) {
			$params['redeem_by'] = strtotime( $c['expires'] . ' UTC' );
		}
		return $params;
	}

	/**
	 * Crée le code : coupon chez Stripe, puis enregistrement ici.
	 *
	 * @param array<string, mixed> $in
	 * @return int|WP_Error  identifiant du code
	 */
	public static function create( array $in ) {
		$c = self::validate( $in );
		if ( is_wp_error( $c ) ) {
			return $c;
		}
		if ( '' === Arvator_Stripe::secret() ) {
			return new WP_Error( 'promo', 'Stripe n’est pas configuré (clé absente du wp-config.php).' );
		}

		// Produits Stripe visés : celui du robot, ou ceux des abonnements de la formule choisie.
		$kinds    = 'bot' === $c['target'] ? array( 'bot' ) : ( 'both' === $c['plans'] ? array( 'monthly', 'annual' ) : array( (string) $c['plans'] ) );
		$products = array();
		foreach ( $kinds as $kind ) {
			$price_id = Arvator_Stripe::price( $kind );
			$price    = '' !== $price_id ? Arvator_Stripe::request( 'GET', 'prices/' . rawurlencode( $price_id ) ) : new WP_Error( 'promo', 'Prix absent.' );
			$product  = is_array( $price ) ? ( is_array( $price['product'] ?? null ) ? (string) ( $price['product']['id'] ?? '' ) : (string) ( $price['product'] ?? '' ) ) : '';
			if ( '' === $product ) {
				return new WP_Error( 'promo', 'Stripe n’a pas pu lire le prix « ' . $kind . ' » (' . ( is_wp_error( $price ) ? $price->get_error_message() : 'produit introuvable' ) . '). Vérifiez que la clé restreinte a le droit de lecture sur « Prices ».' );
			}
			$products[ $product ] = $product;
		}

		$coupon = Arvator_Stripe::request( 'POST', 'coupons', self::coupon_params( $c, $products ), 'arv-promo-' . md5( wp_json_encode( $c ) ) );
		$id     = is_array( $coupon ) ? (string) ( $coupon['id'] ?? '' ) : '';
		if ( '' === $id ) {
			return new WP_Error( 'promo', 'Stripe a refusé de créer la réduction (' . ( is_wp_error( $coupon ) ? $coupon->get_error_message() : 'réponse illisible' ) . '). Si le message parle de droits (permission), ajoutez le droit d’écriture sur « Coupons » à la clé restreinte.' );
		}

		global $wpdb;
		$now = gmdate( 'Y-m-d H:i:s' );
		$ok  = $wpdb->insert(
			Arvator_Repo::table( 'promo_codes' ),
			array(
				'code'          => $c['code'],
				'target'        => $c['target'],
				'percent'       => $c['percent'],
				'duration'      => $c['duration'],
				'months'        => $c['months'],
				'plans'         => $c['plans'],
				'max_uses'      => $c['max_uses'],
				'expires_at'    => $c['expires'],
				'active'        => 1,
				'stripe_coupon' => $id,
				'is_test'       => self::mode_is_test(),
				'created_at'    => $now,
				'updated_at'    => $now,
			),
			array( '%s', '%s', '%d', '%s', '%d', '%s', '%d', '%s', '%d', '%s', '%d', '%s', '%s' )
		);
		if ( ! $ok ) {
			return new WP_Error( 'promo', 'Le code n’a pas pu être enregistré.' );
		}
		Arvator_Repo::log_event( 'promo_created', get_current_user_id(), array( 'code' => $c['code'], 'coupon' => $id ) );
		return (int) $wpdb->insert_id;
	}

	public static function set_active( int $id, bool $active ): void {
		global $wpdb;
		$wpdb->update( Arvator_Repo::table( 'promo_codes' ), array( 'active' => $active ? 1 : 0, 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ), array( 'id' => $id ), array( '%d', '%s' ), array( '%d' ) );
	}

	/**
	 * Achat confirmé par Stripe : note l'utilisation du code (une seule fois par paiement).
	 *
	 * @param array<string, mixed> $session  Session Checkout complète, relue chez Stripe.
	 */
	public static function on_purchase( int $user_id, int $order_id, array $session, bool $live ): void {
		$code = self::normalize( (string) ( $session['metadata']['promo'] ?? '' ) );
		if ( '' === $code ) {
			return;
		}
		global $wpdb;
		$promo_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . Arvator_Repo::table( 'promo_codes' ) . ' WHERE code = %s AND is_test = %d', $code, $live ? 0 : 1 ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( $promo_id <= 0 ) {
			Arvator_Repo::log_event( 'promo_unknown', $user_id, array( 'code' => $code ) );
			return;
		}
		$wpdb->query( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare(
				'INSERT IGNORE INTO ' . Arvator_Repo::table( 'promo_uses' ) . ' (promo_id, user_id, order_id, stripe_session_id, created_at) VALUES (%d, %d, %d, %s, %s)',
				$promo_id,
				$user_id,
				$order_id,
				(string) ( $session['id'] ?? '' ),
				gmdate( 'Y-m-d H:i:s' )
			)
		);
	}

	/** @return array<int, array<string, mixed>> dernières utilisations d'un code */
	public static function uses_of( int $id, int $limit = 50 ): array {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Arvator_Repo::table( 'promo_uses' ) . ' WHERE promo_id = %d ORDER BY id DESC LIMIT %d', $id, $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
}

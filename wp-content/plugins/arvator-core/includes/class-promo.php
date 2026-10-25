<?php
/**
 * Codes promo, créés par Hamid dans Arvator > Codes promo et saisis par le client sur la page d'achat.
 *
 * Un code réduit le robot, l'abonnement, ou les deux à la fois (chacun avec son propre pourcentage) :
 * - robot : x % sur le prix du robot (paiement unique, 100 % = offert) ;
 * - abonnement : y % (100 % = offert), pour la 1re période, pendant N mois (formule mensuelle) ou à chaque paiement.
 *   Le code peut être réservé à la formule mensuelle ou annuelle.
 *
 * Stripe n'accepte qu'un coupon par paiement : la réduction de l'abonnement est un « coupon » Stripe (créé avec le
 * code), celle du robot un prix du robot recalculé pour ce paiement (même produit Stripe, montant réduit). Le code
 * lui-même n'existe que sur ce site : il est vérifié au moment de l'achat (actif, date, nombre d'utilisations,
 * formule), puis transmis à Stripe. Chaque client n'achète le robot qu'une fois : un code
 * ne sert donc qu'une fois par client. Un coupon Stripe ne se modifie pas : pour changer un code, on le désactive et on
 * en crée un nouveau. Les codes du bac à sable (test) ne marchent pas avec la clé réelle, et inversement.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Arvator_Promo {

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
		if ( ! is_array( $row ) || 1 !== (int) $row['active'] || ( (int) $row['sub_percent'] > 0 && '' === (string) $row['stripe_coupon'] ) || ( (int) $row['bot_percent'] + (int) $row['sub_percent'] ) <= 0 ) {
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
		$parts = array();
		$bot   = (int) ( $row['bot_percent'] ?? 0 );
		if ( $bot > 0 ) {
			/* translators: %d: pourcentage de réduction. */
			$parts[] = 100 === $bot ? __( 'robot offert', 'arvator-core' ) : sprintf( __( '-%d %% sur le prix du robot', 'arvator-core' ), $bot );
		}
		if ( (int) ( $row['sub_percent'] ?? 0 ) > 0 ) {
			$parts[] = self::describe_sub( $row );
		}
		/* translators: sépare deux avantages d'un même code promo (« robot offert + 6 mois d'abonnement offerts »). */
		return implode( __( ' + ', 'arvator-core' ), $parts );
	}

	private static function describe_sub( array $row ): string {
		$pct    = (int) $row['sub_percent'];
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
		$bot_pct  = (int) ( $in['bot_percent'] ?? 0 );
		$sub_pct  = (int) ( $in['sub_percent'] ?? 0 );
		$duration = $sub_pct > 0 ? (string) ( $in['duration'] ?? '' ) : 'once';
		$months   = 'repeating' === $duration ? (int) ( $in['months'] ?? 0 ) : 0;
		$plans    = $sub_pct > 0 ? (string) ( $in['plans'] ?? 'both' ) : 'both';
		$max_uses = max( 0, (int) ( $in['max_uses'] ?? 0 ) );
		$until    = trim( (string) ( $in['expires'] ?? '' ) );

		if ( '' === $code ) {
			return new WP_Error( 'promo', 'Code non valable : 3 à 30 caractères, lettres, chiffres, tiret ou tiret bas (par exemple NOEL30).' );
		}
		if ( $bot_pct < 0 || $bot_pct > 100 || $sub_pct < 0 || $sub_pct > 100 ) {
			return new WP_Error( 'promo', 'Chaque réduction doit être comprise entre 0 et 100 % (0 = rien, 100 = offert).' );
		}
		if ( 0 === $bot_pct + $sub_pct ) {
			return new WP_Error( 'promo', 'Indiquez une réduction sur le robot, sur l’abonnement, ou les deux.' );
		}
		if ( ! in_array( $duration, self::DURATIONS, true ) || ! in_array( $plans, self::PLANS, true ) ) {
			return new WP_Error( 'promo', 'Choisissez la formule concernée et la durée de la réduction sur l’abonnement.' );
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
		return array(
			'code'        => $code,
			'bot_percent' => $bot_pct,
			'sub_percent' => $sub_pct,
			'duration'    => $duration,
			'months'      => $months,
			'plans'       => $plans,
			'max_uses'    => $max_uses,
			'expires'     => $expires,
		);
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
			'percent_off' => (int) $c['sub_percent'],
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

		// Réduction de l'abonnement : coupon Stripe limité aux produits des abonnements de la formule choisie.
		// (Celle du robot est appliquée au moment du paiement, voir bot_line().)
		$id = '';
		if ( (int) $c['sub_percent'] > 0 ) {
			$id = self::create_coupon( $c );
			if ( is_wp_error( $id ) ) {
				return $id;
			}
		}

		global $wpdb;
		$now = gmdate( 'Y-m-d H:i:s' );
		$ok  = $wpdb->insert(
			Arvator_Repo::table( 'promo_codes' ),
			array(
				'code'          => $c['code'],
				'target'        => $c['bot_percent'] > 0 && $c['sub_percent'] > 0 ? 'both' : ( $c['bot_percent'] > 0 ? 'bot' : 'sub' ),
				'percent'       => max( $c['bot_percent'], $c['sub_percent'] ),
				'bot_percent'   => $c['bot_percent'],
				'sub_percent'   => $c['sub_percent'],
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
			array( '%s', '%s', '%d', '%d', '%d', '%s', '%d', '%s', '%d', '%s', '%d', '%s', '%d', '%s', '%s' )
		);
		if ( ! $ok ) {
			return new WP_Error( 'promo', 'Le code n’a pas pu être enregistré.' );
		}
		Arvator_Repo::log_event( 'promo_created', get_current_user_id(), array( 'code' => $c['code'], 'coupon' => $id ) );
		return (int) $wpdb->insert_id;
	}

	/**
	 * @param array<string, mixed> $c  code validé
	 * @return string|WP_Error  identifiant du coupon Stripe
	 */
	private static function create_coupon( array $c ) {
		$kinds    = 'both' === $c['plans'] ? array( 'monthly', 'annual' ) : array( (string) $c['plans'] );
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
		return $id;
	}

	/**
	 * Ligne « robot » du paiement Stripe : le prix normal, ou, si le code réduit le robot, le même produit Stripe à un
	 * montant réduit (Stripe n'acceptant qu'un coupon par paiement, celui de l'abonnement).
	 *
	 * @param array<string, mixed>|null $promo
	 * @return array<string, mixed>|WP_Error
	 */
	public static function bot_line( ?array $promo ) {
		$pct = $promo ? (int) $promo['bot_percent'] : 0;
		if ( $pct <= 0 ) {
			return array(
				'price'    => Arvator_Stripe::price( 'bot' ),
				'quantity' => 1,
			);
		}
		$price   = Arvator_Stripe::request( 'GET', 'prices/' . rawurlencode( Arvator_Stripe::price( 'bot' ) ) );
		$product = is_array( $price ) ? ( is_array( $price['product'] ?? null ) ? (string) ( $price['product']['id'] ?? '' ) : (string) ( $price['product'] ?? '' ) ) : '';
		$amount  = is_array( $price ) ? (int) ( $price['unit_amount'] ?? 0 ) : 0;
		if ( '' === $product || $amount <= 0 ) {
			return new WP_Error( 'promo', is_wp_error( $price ) ? $price->get_error_message() : 'prix du robot illisible' );
		}
		return array(
			'price_data' => array(
				'currency'    => strtolower( (string) ( $price['currency'] ?? 'eur' ) ),
				'product'     => $product,
				'unit_amount' => self::reduced( $amount, $pct ),
			),
			'quantity'   => 1,
		);
	}

	/** Montant après réduction, arrondi au centime (le client ne paie jamais plus que le calcul exact). */
	public static function reduced( int $cents, int $pct ): int {
		return max( 0, (int) floor( $cents * ( 100 - $pct ) / 100 ) );
	}

	/** Codes créés avec le plugin 0.9.0 (un seul avantage, colonnes target/percent) : repris dans bot_percent/sub_percent. */
	public static function migrate(): void {
		global $wpdb;
		$t = Arvator_Repo::table( 'promo_codes' );
		$wpdb->query( "UPDATE $t SET bot_percent = percent WHERE target = 'bot' AND bot_percent = 0 AND sub_percent = 0" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "UPDATE $t SET sub_percent = percent WHERE target = 'sub' AND bot_percent = 0 AND sub_percent = 0" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// Un ancien code « robot » portait un coupon Stripe sur le produit du robot : il n'est plus envoyé (la réduction du
		// robot passe par le prix recalculé) ; on l'efface pour ne jamais l'appliquer à l'abonnement.
		$wpdb->query( "UPDATE $t SET stripe_coupon = '' WHERE target = 'bot' AND sub_percent = 0" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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

<?php
/**
 * Paiement par virement bancaire (SEPA) — formule annuelle (robot + 1 an d'abonnement), puis renouvellement annuel.
 *
 * Principe (demande de Hamid, 25/10/2026) : le site reste la seule source de vérité des commandes. Le client choisit
 * « Virement » sur la page d'achat ; le site crée une commande « en attente » avec une référence unique (ARV-123456) et
 * affiche/envoie les coordonnées bancaires. n8n lit les virements reçus sur le compte (une fois par jour) et appelle
 * POST /wp-json/arvator/v1/virement-recu ; le site déroule alors EXACTEMENT le même traitement qu'après un paiement
 * Stripe réussi : compte client, email, achat et abonnement, parrainage, code promo, licence (circuit n8n existant),
 * facture. Aucune donnée bancaire du client n'est lue ni conservée par le site.
 *
 * Sécurité : le point de réception exige une signature HMAC-SHA256 (constante ARVATOR_VIREMENT_SECRET) et un horodatage de
 * moins de 5 minutes ; chaque identifiant de transaction n'est traité qu'une fois ; les coordonnées bancaires viennent de
 * constantes de wp-config.php (jamais du code ni de la base).
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Arvator_Bank {

	public const CRON      = 'arvator_bank_daily';
	public const PENDING   = 'en_attente';
	public const PAID      = 'payee';
	public const CANCELED  = 'annulee';
	public const REFUNDED  = 'remboursee';
	private const TOLERANCE = 300; // secondes : âge maximum du message de n8n.

	/** Rappels avant/après l'échéance d'un renouvellement (en jours). */
	private const OFFSETS = array( -30, -14, -7, -3, -1, 0, 1, 2 );

	public static function init(): void {
		add_action( 'rest_api_init', array( self::class, 'register_route' ) );
		add_action( 'init', array( self::class, 'schedule' ) );
		add_action( self::CRON, array( self::class, 'daily' ) );
		add_action( 'admin_post_arvator_bank_renew', array( self::class, 'handle_renew' ) );
		add_action( 'admin_post_arvator_bank_invoice', array( self::class, 'handle_invoice' ) );
		add_action( 'admin_post_nopriv_arvator_bank_invoice', array( self::class, 'handle_invoice' ) );
	}

	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
		}
	}

	/* --------------------------------------------------------------------- Réglages */

	private static function constant( string $name ): string {
		return defined( $name ) ? trim( (string) constant( $name ) ) : '';
	}

	public static function holder(): string {
		return self::constant( 'ARVATOR_VIREMENT_TITULAIRE' );
	}

	public static function iban(): string {
		return strtoupper( (string) preg_replace( '/\s+/', '', self::constant( 'ARVATOR_VIREMENT_IBAN' ) ) );
	}

	public static function bic(): string {
		return strtoupper( (string) preg_replace( '/\s+/', '', self::constant( 'ARVATOR_VIREMENT_BIC' ) ) );
	}

	public static function secret(): string {
		return self::constant( 'ARVATOR_VIREMENT_SECRET' );
	}

	/** Coordonnées et secret posés dans wp-config.php ? */
	public static function configured(): bool {
		return '' !== self::holder() && Arvator_Referral::valid_iban( self::iban() ) && '' !== self::bic() && strlen( self::secret() ) >= 16;
	}

	/** Le virement est proposé sur la page d'achat (réglé + activé dans Arvator > Virements). */
	public static function enabled(): bool {
		return self::configured() && '1' === (string) get_option( 'arvator_bank_enabled', '1' );
	}

	/** Jours avant l'annulation automatique d'une commande non payée. */
	public static function cancel_days(): int {
		return max( 1, min( 365, (int) get_option( 'arvator_bank_cancel_days', 30 ) ) );
	}

	/** Les achats sont marqués « test » comme ceux de Stripe (même mode que la clé Stripe). */
	public static function is_test(): bool {
		return Arvator_Stripe::is_test_key();
	}

	public static function vat_note(): string {
		return (string) get_option( 'arvator_bank_vat_note', 'TVA non applicable, article 293 B du Code général des impôts.' );
	}

	/* --------------------------------------------------------------------- Montants */

	public static function eur( int $cents ): string {
		return number_format( $cents / 100, 0 === $cents % 100 ? 0 : 2, ',', "\u{202F}" ) . "\u{00A0}€";
	}

	/** Date UTC de la base → date lisible. */
	public static function date( string $utc ): string {
		$ts = strtotime( $utc . ' UTC' );
		return false === $ts ? '—' : wp_date( 'j F Y', $ts );
	}

	/**
	 * Montants d'une commande : robot et abonnement annuel, avec la réduction du code promo éventuel.
	 *
	 * @param array<string, mixed>|null $promo  Ligne du code promo (déjà vérifiée).
	 * @return array{bot:int, sub:int, total:int}
	 */
	public static function amounts_for( ?array $promo, bool $renewal ): array {
		$a   = Arvator_Checkout::amounts();
		$bot = (int) round( $a['achat'] * 100 );
		$sub = (int) round( $a['annuel'] * 100 );
		if ( $promo ) {
			$sub_pct = (int) $promo['sub_percent'];
			if ( ! $renewal ) {
				$bot = Arvator_Promo::reduced( $bot, (int) $promo['bot_percent'] );
				$sub = $sub_pct > 0 ? Arvator_Promo::reduced( $sub, $sub_pct ) : $sub;
			} elseif ( 'forever' === (string) $promo['duration'] && $sub_pct > 0 ) {
				$sub = Arvator_Promo::reduced( $sub, $sub_pct );
			}
		}
		if ( $renewal ) {
			$bot = 0;
		}
		return array( 'bot' => $bot, 'sub' => $sub, 'total' => $bot + $sub );
	}

	/* --------------------------------------------------------------------- Lecture / écriture des commandes */

	private static function table(): string {
		return Arvator_Repo::table( 'bank_orders' );
	}

	/** @return array<string, mixed>|null */
	public static function by_reference( string $ref ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE reference = %s', $ref ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return is_array( $row ) ? $row : null;
	}

	/** @return array<string, mixed>|null */
	public static function by_token( string $token ): ?array {
		if ( 1 !== preg_match( '/^[a-f0-9]{32}$/', $token ) ) {
			return null;
		}
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE token = %s', $token ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return is_array( $row ) ? $row : null;
	}

	/** @return array<string, mixed>|null */
	public static function by_id( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return is_array( $row ) ? $row : null;
	}

	/** @return array<int, array<string, mixed>> */
	public static function for_user( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return array();
		}
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE user_id = %d ORDER BY id DESC', $user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return is_array( $rows ) ? $rows : array();
	}

	/** Référence « ARV-482913 » : 6 chiffres tirés au hasard, jamais réutilisés. */
	private static function new_reference(): string {
		for ( $i = 0; $i < 30; $i++ ) {
			$ref = 'ARV-' . str_pad( (string) random_int( 0, 999999 ), 6, '0', STR_PAD_LEFT );
			if ( null === self::by_reference( $ref ) ) {
				return $ref;
			}
		}
		throw new RuntimeException( 'Référence de virement introuvable.' );
	}

	/** Extrait « ARV-123456 » d'un texte libre (libellé du virement), quelle que soit la casse ou la ponctuation. */
	public static function extract_reference( string $text ): string {
		return 1 === preg_match( '/ARV[\s_.-]*([0-9]{6})(?![0-9])/i', $text, $m ) ? 'ARV-' . $m[1] : '';
	}

	/**
	 * Crée la commande « en attente » de l'achat initial (ou met à jour celle déjà ouverte pour cette adresse email : une
	 * seule commande en attente par client, la même référence est conservée).
	 *
	 * @param array<string, mixed>      $in     email, name, account, ref_code, waiver, locale, user_id
	 * @param array<string, mixed>|null $promo  Code promo vérifié.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function create_initial( array $in, ?array $promo ) {
		global $wpdb;
		$amounts = self::amounts_for( $promo, false );
		if ( $amounts['total'] <= 0 ) {
			return new WP_Error( 'zero', 'Total nul.' );
		}
		$now  = gmdate( 'Y-m-d H:i:s' );
		$data = array(
			'user_id'      => (int) $in['user_id'],
			'email'        => (string) $in['email'],
			'name'         => (string) $in['name'],
			'account'      => (string) $in['account'],
			'plan'         => 'annual',
			'amount_cents' => $amounts['total'],
			'bot_cents'    => $amounts['bot'],
			'sub_cents'    => $amounts['sub'],
			'ref_code'     => (string) $in['ref_code'],
			'promo_code'   => $promo ? (string) $promo['code'] : '',
			'waiver'       => ! empty( $in['waiver'] ) ? 1 : 0,
			'locale'       => (string) $in['locale'],
			'is_test'      => self::is_test() ? 1 : 0,
			'updated_at'   => $now,
		);
		$open = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . " WHERE email = %s AND kind = 'initial' AND status = %s ORDER BY id DESC LIMIT 1", $data['email'], self::PENDING ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( is_array( $open ) ) {
			$wpdb->update( self::table(), $data, array( 'id' => (int) $open['id'] ) );
			return (array) self::by_id( (int) $open['id'] );
		}
		$data += array(
			'reference'  => self::new_reference(),
			'token'      => bin2hex( random_bytes( 16 ) ),
			'kind'       => 'initial',
			'status'     => self::PENDING,
			'created_at' => $now,
		);
		if ( false === $wpdb->insert( self::table(), $data ) ) {
			return new WP_Error( 'db', 'Enregistrement impossible.' );
		}
		$row = self::by_id( (int) $wpdb->insert_id );
		return $row ?? new WP_Error( 'db', 'Commande introuvable.' );
	}

	/**
	 * Commande de renouvellement annuel (358,80 €). Une seule par échéance : la tâche quotidienne n'en recrée jamais une
	 * (même annulée) ; le bouton du client ($manual) peut en créer une nouvelle si la précédente a été annulée.
	 *
	 * @return array<string, mixed>|null  null si elle existe déjà ou si le client n'a pas d'abonnement par virement.
	 */
	public static function create_renewal( int $user_id, bool $notify = true, bool $manual = false ): ?array {
		global $wpdb;
		$sub = self::subscription( $user_id );
		$user = get_user_by( 'id', $user_id );
		if ( ! $sub || ! $user instanceof WP_User || empty( $sub['current_period_end'] ) ) {
			return null;
		}
		$due  = (string) $sub['current_period_end'];
		$have = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . " WHERE user_id = %d AND kind = 'renewal' AND due_at = %s" . ( $manual ? ' AND status IN (%s, %s)' : ' AND %s <> %s' ), $user_id, $due, self::PENDING, self::PAID ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( $have > 0 ) {
			return null;
		}
		$first   = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . " WHERE user_id = %d AND kind = 'initial' AND status = %s ORDER BY id ASC LIMIT 1", $user_id, self::PAID ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$promo   = is_array( $first ) && '' !== (string) $first['promo_code'] ? self::promo_row( (string) $first['promo_code'] ) : null;
		$amounts = self::amounts_for( $promo, true );
		$now     = gmdate( 'Y-m-d H:i:s' );
		$ok      = $wpdb->insert(
			self::table(),
			array(
				'reference'    => self::new_reference(),
				'token'        => bin2hex( random_bytes( 16 ) ),
				'kind'         => 'renewal',
				'user_id'      => $user_id,
				'email'        => $user->user_email,
				'name'         => is_array( $first ) ? (string) $first['name'] : $user->display_name,
				'account'      => Arvator_License::account_for( $user_id ),
				'plan'         => 'annual',
				'amount_cents' => $amounts['total'],
				'bot_cents'    => 0,
				'sub_cents'    => $amounts['sub'],
				'status'       => self::PENDING,
				'due_at'       => $due,
				'is_test'      => self::is_test() ? 1 : 0,
				'created_at'   => $now,
				'updated_at'   => $now,
			)
		);
		if ( false === $ok ) {
			return null;
		}
		$row = self::by_id( (int) $wpdb->insert_id );
		if ( $row && $notify ) {
			self::send_reminder( $row );
		}
		return $row;
	}

	/** @return array<string, mixed>|null */
	private static function promo_row( string $code ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Arvator_Repo::table( 'promo_codes' ) . ' WHERE code = %s AND is_test = %d', $code, self::is_test() ? 1 : 0 ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return is_array( $row ) ? $row : null;
	}

	/** Abonnement payé par virement du client (le plus récent), ou null. @return array<string, mixed>|null */
	public static function subscription( int $user_id ): ?array {
		if ( $user_id <= 0 ) {
			return null;
		}
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Arvator_Repo::table( 'subscriptions' ) . " WHERE user_id = %d AND method = 'bank' ORDER BY id DESC LIMIT 1", $user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return is_array( $row ) ? $row : null;
	}

	/* --------------------------------------------------------------------- Page de coordonnées et QR code */

	public static function url( array $order ): string {
		return add_query_arg( 'arv_virement', (string) $order['token'], Arvator_Checkout::url() );
	}

	/** Contenu du QR code de virement SEPA (norme EPC, version 001, UTF-8). */
	public static function epc_payload( array $order ): string {
		$name = mb_substr( self::holder(), 0, 70 );
		return implode(
			"\n",
			array(
				'BCD',
				'001',
				'1',
				'SCT',
				self::bic(),
				$name,
				self::iban(),
				'EUR' . number_format( (int) $order['amount_cents'] / 100, 2, '.', '' ),
				'',
				'',
				(string) $order['reference'],
			)
		);
	}

	public static function format_iban( string $iban ): string {
		return trim( chunk_split( $iban, 4, ' ' ) );
	}

	/** Bloc HTML avec les coordonnées, le montant exact, la référence et le QR code. */
	public static function instructions_html( array $order ): string {
		$ref    = (string) $order['reference'];
		$amount = self::eur( (int) $order['amount_cents'] );
		$status = (string) $order['status'];
		ob_start();
		?>
		<div class="arv-bank">
			<?php if ( self::PAID === $status ) : ?>
				<p class="arv-notice arv-notice--ok" role="status"><?php /* translators: %s: référence du virement. */ echo esc_html( sprintf( __( 'Virement reçu pour la commande %s. Merci !', 'arvator-core' ), $ref ) ); ?></p>
			<?php elseif ( self::CANCELED === $status ) : ?>
				<p class="arv-notice arv-notice--err" role="alert"><?php /* translators: %s: référence du virement. */ echo esc_html( sprintf( __( 'La commande %s a été annulée faute de paiement. Vous pouvez passer une nouvelle commande.', 'arvator-core' ), $ref ) ); ?></p>
			<?php elseif ( self::REFUNDED === $status ) : ?>
				<p class="arv-notice arv-notice--err" role="alert"><?php /* translators: %s: référence du virement. */ echo esc_html( sprintf( __( 'La commande %s a été remboursée.', 'arvator-core' ), $ref ) ); ?></p>
			<?php else : ?>
				<dl class="arv-bank__list">
					<div><dt><?php esc_html_e( 'Bénéficiaire', 'arvator-core' ); ?></dt><dd><?php echo esc_html( self::holder() ); ?></dd></div>
					<div><dt>IBAN</dt><dd><code><?php echo esc_html( self::format_iban( self::iban() ) ); ?></code></dd></div>
					<div><dt>BIC</dt><dd><code><?php echo esc_html( self::bic() ); ?></code></dd></div>
					<div><dt><?php esc_html_e( 'Montant exact', 'arvator-core' ); ?></dt><dd><strong><?php echo esc_html( $amount ); ?></strong></dd></div>
					<div class="arv-bank__ref"><dt><?php esc_html_e( 'Référence à recopier dans le libellé du virement (obligatoire)', 'arvator-core' ); ?></dt>
						<dd><code id="arv-bank-ref"><?php echo esc_html( $ref ); ?></code>
						<button type="button" class="btn btn--ghost btn--sm" data-copy="<?php echo esc_attr( $ref ); ?>"><?php esc_html_e( 'Copier la référence', 'arvator-core' ); ?></button></dd></div>
				</dl>
				<p><strong><?php esc_html_e( 'Sans cette référence, nous ne pouvons pas rattacher votre virement à votre commande.', 'arvator-core' ); ?></strong></p>
				<p><?php esc_html_e( 'Conseil : si votre banque le propose, faites un virement instantané.', 'arvator-core' ); ?></p>
				<p><?php echo esc_html( self::delay_sentence() ); ?></p>
				<?php $qr = self::qr_svg( $order ); ?>
				<?php if ( '' !== $qr ) : ?>
					<figure class="arv-bank__qr">
						<?php echo $qr; // phpcs:ignore WordPress.Security.EscapeOutput -- SVG construit par Arvator_QR, sans donnée non maîtrisée ?>
						<figcaption class="muted"><?php esc_html_e( 'Ou scannez ce code avec l’application de votre banque : il remplit le virement pour vous.', 'arvator-core' ); ?></figcaption>
					</figure>
				<?php endif; ?>
				<script>
				document.querySelectorAll('[data-copy]').forEach(function(b){b.addEventListener('click',function(){var t=b.getAttribute('data-copy');if(navigator.clipboard){navigator.clipboard.writeText(t);}b.textContent='<?php echo esc_js( __( 'Copié', 'arvator-core' ) ); ?>';});});
				</script>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	public static function delay_sentence(): string {
		return __( 'Votre licence est activée dans les 24 heures suivant la réception de votre virement.', 'arvator-core' );
	}

	public static function qr_svg( array $order ): string {
		return Arvator_QR::svg( self::epc_payload( $order ) );
	}

	/* --------------------------------------------------------------------- Emails */

	/** @param string[] $lines */
	private static function send( string $to, string $subject, array $lines, string $locale = '' ): void {
		$switched = false;
		if ( preg_match( '/^[a-z]{2,3}(_[A-Z]{2})?$/', $locale ) && get_locale() !== $locale ) {
			$switched = switch_to_locale( $locale );
		}
		$lines[] = '';
		$lines[] = Arvator_Site::signature();
		wp_mail( $to, $subject, implode( "\n", $lines ) );
		if ( $switched ) {
			restore_previous_locale();
		}
	}

	/** @return string[] */
	private static function instruction_lines( array $order ): array {
		return array(
			sprintf( /* translators: %s: nom du bénéficiaire. */ __( 'Bénéficiaire : %s', 'arvator-core' ), self::holder() ),
			'IBAN : ' . self::format_iban( self::iban() ),
			'BIC : ' . self::bic(),
			sprintf( /* translators: %s: montant. */ __( 'Montant exact : %s', 'arvator-core' ), self::eur( (int) $order['amount_cents'] ) ),
			sprintf( /* translators: %s: référence. */ __( 'Référence à recopier dans le libellé du virement (obligatoire) : %s', 'arvator-core' ), (string) $order['reference'] ),
			'',
			__( 'Conseil : faites un virement instantané si votre banque le propose.', 'arvator-core' ),
			self::delay_sentence(),
			'',
			__( 'Vous pouvez aussi retrouver ces informations, avec un QR code de virement, ici :', 'arvator-core' ),
			self::url( $order ),
		);
	}

	/** Email de l'achat initial : « il reste une étape ». */
	public static function send_initial( array $order ): void {
		$lines = array_merge(
			array( __( 'Bonjour,', 'arvator-core' ), '', __( 'Merci pour votre commande Arvator (robot + abonnement annuel). Il reste une étape : votre virement.', 'arvator-core' ), '' ),
			self::instruction_lines( $order ),
			array( '', sprintf( /* translators: %d: nombre de jours. */ __( 'Sans paiement, la commande est annulée automatiquement après %d jours.', 'arvator-core' ), self::cancel_days() ) )
		);
		self::send( (string) $order['email'], __( 'Arvator : votre virement à effectuer', 'arvator-core' ), $lines, (string) $order['locale'] );
	}

	/**
	 * Rappel d'une commande en attente : J+3 et J+7 pour un achat, de J-30 à J+2 pour un renouvellement.
	 *
	 * @param array<string, mixed> $order
	 */
	public static function send_reminder( array $order, int $day = -30 ): void {
		$renewal = 'renewal' === (string) $order['kind'];
		$user    = (int) $order['user_id'] > 0 ? get_user_by( 'id', (int) $order['user_id'] ) : false;
		$locale  = $user instanceof WP_User ? (string) get_user_meta( $user->ID, 'locale', true ) : (string) $order['locale'];
		$lines   = array( __( 'Bonjour,', 'arvator-core' ), '' );
		if ( $renewal ) {
			$end = self::date( (string) $order['due_at'] );
			if ( $day < 0 ) {
				/* translators: %s: date de fin de la licence. */
				$lines[] = sprintf( __( 'Votre abonnement annuel Arvator se termine le %s. Il ne se renouvelle pas tout seul : pour le prolonger d’un an, faites le virement ci-dessous.', 'arvator-core' ), $end );
				$subject = __( 'Arvator : renouvelez votre abonnement par virement', 'arvator-core' );
			} elseif ( 0 === $day ) {
				$lines[]  = __( 'Votre licence est arrivée à échéance aujourd’hui. Vous disposez de 3 jours de grâce : le robot continue de fonctionner pendant ce délai.', 'arvator-core' );
				$subject  = __( 'Arvator : votre licence arrive à échéance aujourd’hui', 'arvator-core' );
			} else {
				$lines[]  = __( 'Votre licence est arrivée à échéance. Sans virement, le robot n’ouvre plus de nouveaux trades à la fin du délai de grâce.', 'arvator-core' );
				$subject  = __( 'Arvator : votre licence est échue, virement à effectuer', 'arvator-core' );
			}
			$lines[] = sprintf( /* translators: %s: date limite. */ __( 'Date limite : %s', 'arvator-core' ), $end );
		} else {
			$lines[] = __( 'Votre commande Arvator attend encore votre virement.', 'arvator-core' );
			$subject = __( 'Arvator : rappel, votre virement n’est pas encore arrivé', 'arvator-core' );
		}
		$lines[] = '';
		$lines   = array_merge( $lines, self::instruction_lines( $order ) );
		self::send( (string) $order['email'], $subject, $lines, $locale );
	}

	/* --------------------------------------------------------------------- Réception du virement (appelée par n8n) */

	public static function register_route(): void {
		register_rest_route(
			'arvator/v1',
			'/virement-recu',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'handle_received' ),
				'permission_callback' => '__return_true', // la sécurité est la signature HMAC, vérifiée dans handle_received().
			)
		);
	}

	/** Signature attendue : HMAC-SHA256 en hexadécimal de « horodatage.corps ». */
	public static function sign( string $timestamp, string $body, string $secret ): string {
		return hash_hmac( 'sha256', $timestamp . '.' . $body, $secret );
	}

	public static function handle_received( WP_REST_Request $request ): WP_REST_Response {
		$secret = self::secret();
		if ( strlen( $secret ) < 16 ) {
			return new WP_REST_Response( array( 'status' => 'non_configure' ), 503 );
		}
		$body = (string) $request->get_body();
		$ts   = trim( (string) $request->get_header( 'x-arvator-timestamp' ) );
		$sig  = strtolower( trim( (string) $request->get_header( 'x-arvator-signature' ) ) );
		$sig  = str_starts_with( $sig, 'sha256=' ) ? substr( $sig, 7 ) : $sig;
		if ( 1 !== preg_match( '/^[0-9]{9,12}$/', $ts ) || abs( time() - (int) $ts ) > self::TOLERANCE || ! hash_equals( self::sign( $ts, $body, $secret ), $sig ) ) {
			return new WP_REST_Response( array( 'status' => 'signature_invalide' ), 403 );
		}
		$in = json_decode( $body, true );
		if ( ! is_array( $in ) ) {
			return new WP_REST_Response( array( 'status' => 'corps_invalide' ), 400 );
		}
		$tx     = trim( (string) ( $in['transaction_id'] ?? '' ) );
		$amount = str_replace( array( ' ', ',' ), array( '', '.' ), (string) ( $in['montant'] ?? '' ) );
		if ( '' === $tx || strlen( $tx ) > 190 || ! is_numeric( $amount ) || (float) $amount <= 0 ) {
			return new WP_REST_Response( array( 'status' => 'corps_invalide' ), 400 );
		}
		$cents = (int) round( (float) $amount * 100 );
		$ref   = self::extract_reference( (string) ( $in['reference'] ?? '' ) );
		if ( '' === $ref ) {
			$ref = self::extract_reference( (string) ( $in['libelle'] ?? '' ) );
		}
		$ts_paid = strtotime( (string) ( $in['date'] ?? '' ) );
		$paid_at = false !== $ts_paid && $ts_paid > 0 && $ts_paid <= time() + DAY_IN_SECONDS ? gmdate( 'Y-m-d H:i:s', $ts_paid ) : gmdate( 'Y-m-d H:i:s' );

		// Verrou d'idempotence : un même identifiant de transaction n'est traité qu'une fois.
		$lock = 'bank:' . $tx;
		if ( ! Arvator_Repo::log_event( 'bank_received', null, array( 'reference' => $ref, 'cents' => $cents, 'from' => mb_substr( (string) ( $in['emetteur'] ?? '' ), 0, 80 ) ), $lock ) ) {
			return new WP_REST_Response( array( 'status' => 'deja_traite', 'reference' => $ref ), 200 );
		}
		try {
			[ $status, $extra ] = self::apply_payment( $ref, $tx, $cents, $paid_at );
		} catch ( Throwable $e ) {
			Arvator_Repo::release_event( $lock );
			Arvator_Repo::log_event( 'bank_error', null, array( 'tx' => $tx, 'error' => $e->getMessage() ) );
			return new WP_REST_Response( array( 'status' => 'erreur_traitement' ), 500 );
		}
		return new WP_REST_Response( array_merge( array( 'status' => $status, 'reference' => $ref ), $extra ), 200 );
	}

	/**
	 * Rapproche un virement d'une commande et, s'il est valable, la valide.
	 *
	 * @return array{0:string, 1:array<string,mixed>}  [statut, informations complémentaires]
	 */
	public static function apply_payment( string $ref, string $tx, int $cents, string $paid_at ): array {
		if ( '' === $ref ) {
			Arvator_Repo::log_event( 'bank_unmatched', null, array( 'tx' => $tx, 'cents' => $cents, 'why' => 'sans_reference' ) );
			return array( 'sans_reference', array() );
		}
		$order = self::by_reference( $ref );
		if ( ! $order ) {
			Arvator_Repo::log_event( 'bank_unmatched', null, array( 'tx' => $tx, 'cents' => $cents, 'ref' => $ref, 'why' => 'commande_inconnue' ) );
			return array( 'commande_inconnue', array() );
		}
		if ( in_array( (string) $order['status'], array( self::PAID, self::REFUNDED ), true ) ) {
			return array( 'deja_traite', array() );
		}
		$expected = (int) $order['amount_cents'];
		if ( $cents < $expected ) {
			Arvator_Repo::log_event( 'bank_unmatched', (int) $order['user_id'] ?: null, array( 'tx' => $tx, 'cents' => $cents, 'ref' => $ref, 'why' => 'montant_insuffisant', 'attendu' => $expected ) );
			return array( 'montant_insuffisant', array( 'attendu' => $expected / 100, 'recu' => $cents / 100 ) );
		}
		self::fulfill( $order, $tx, $cents, $paid_at );
		if ( $cents > $expected ) {
			Arvator_Repo::log_event( 'bank_overpaid', (int) $order['user_id'] ?: null, array( 'tx' => $tx, 'ref' => $ref, 'recu' => $cents, 'attendu' => $expected ) );
			return array( 'trop_percu', array( 'attendu' => $expected / 100, 'recu' => $cents / 100 ) );
		}
		return array( 'ok', array() );
	}

	/**
	 * Commande payée : le même traitement qu'après un paiement Stripe réussi. Chaque étape est idempotente : en cas
	 * d'erreur, n8n peut renvoyer le virement sans rien dédoubler.
	 *
	 * @param array<string, mixed> $order
	 */
	public static function fulfill( array $order, string $tx, int $received_cents, string $paid_at ): void {
		global $wpdb;
		$renewal = 'renewal' === (string) $order['kind'];
		$live    = ! (bool) (int) $order['is_test'];
		$ref     = (string) $order['reference'];
		$order_id = (int) $order['id'];

		// 1. Compte client (créé si besoin, avec lien pour choisir son mot de passe).
		$created = false;
		if ( $renewal ) {
			$user_id = (int) $order['user_id'];
		} else {
			[ $user_id, $created ] = Arvator_Webhook::find_or_create_user( (string) $order['email'], (int) $order['user_id'] );
			if ( '' !== (string) $order['name'] && '' === (string) get_user_meta( $user_id, 'first_name', true ) ) {
				wp_update_user( array( 'ID' => $user_id, 'display_name' => (string) $order['name'], 'first_name' => (string) $order['name'] ) );
			}
		}

		// 2. Achat du robot (initial) : une ligne dans la table des achats, comme un paiement Stripe.
		$shop_order_id = (int) $order['order_id'];
		if ( ! $renewal ) {
			$wpdb->query( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$wpdb->prepare(
					'INSERT IGNORE INTO ' . Arvator_Repo::table( 'orders' ) . ' (user_id, stripe_session_id, product, amount_cents, currency, status, is_test, created_at) VALUES (%d, %s, %s, %d, %s, %s, %d, %s)',
					$user_id,
					'bank:' . $ref,
					'cbot',
					(int) $order['bot_cents'],
					'eur',
					'paid',
					(int) $order['is_test'],
					$paid_at
				)
			);
			$shop_order_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . Arvator_Repo::table( 'orders' ) . ' WHERE stripe_session_id = %s', 'bank:' . $ref ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		// 3. Abonnement : une période d'un an (ajoutée une seule fois par commande).
		$claimed = (int) $wpdb->query( $wpdb->prepare( 'UPDATE ' . self::table() . ' SET applied = 1 WHERE id = %d AND applied = 0', $order_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( $claimed > 0 ) {
			self::extend_subscription( $user_id, $paid_at, (int) $order['is_test'] );
		}

		// 4. Parrainage et code promo (comme à l'achat par carte).
		if ( ! $renewal && $shop_order_id > 0 ) {
			Arvator_Referral::attach_purchase( $user_id, $shop_order_id, (string) $order['ref_code'], '', (int) $order['sub_cents'], 'aff:bank:' . $ref, $live );
			if ( '' !== (string) $order['promo_code'] ) {
				Arvator_Promo::on_purchase( $user_id, $shop_order_id, array( 'id' => 'bank:' . $ref, 'metadata' => array( 'promo' => (string) $order['promo_code'] ) ), $live );
			}
		} elseif ( $renewal ) {
			Arvator_Referral::on_bank_renewal( $user_id, (int) $order['sub_cents'], 'aff:bank:' . $ref, $live );
		}

		// 5. Licence : numéro de compte cTrader saisi à l'achat, puis envoi au workflow n8n (circuit existant).
		if ( ! $renewal ) {
			$account = Arvator_License::clean_account( (string) $order['account'] );
			if ( '' === Arvator_License::account_for( $user_id ) && Arvator_License::valid_account( $account ) && ! Arvator_License::activated( $user_id ) ) {
				update_user_meta( $user_id, 'arv_ctrader_account', $account );
			}
			$locale = (string) $order['locale'];
			if ( preg_match( '/^[a-z]{2,3}(_[A-Z]{2})?$/', $locale ) && get_locale() !== $locale ) {
				update_user_meta( $user_id, 'locale', $locale );
			}
		}
		Arvator_License::on_paid( $user_id, 'annual', 'pay:bank:' . $ref );

		// 6. Facture numérotée, puis la commande est marquée payée (en dernier : un échec avant ce point se rejoue).
		self::assign_invoice_number( $order_id, $paid_at );
		$wpdb->update(
			self::table(),
			array(
				'status'         => self::PAID,
				'user_id'        => $user_id,
				'tx_id'          => $tx,
				'received_cents' => $received_cents,
				'paid_at'        => $paid_at,
				'order_id'       => $shop_order_id > 0 ? $shop_order_id : null,
				'updated_at'     => gmdate( 'Y-m-d H:i:s' ),
			),
			array( 'id' => $order_id )
		);

		Arvator_Repo::log_event( $renewal ? 'bank_renewal' : 'purchase', $user_id, array( 'reference' => $ref, 'amount_cents' => (int) $order['amount_cents'], 'method' => 'bank' ) );
		if ( $renewal ) {
			$user = get_user_by( 'id', $user_id );
			if ( $user instanceof WP_User ) {
				self::send(
					$user->user_email,
					__( 'Arvator : renouvellement reçu', 'arvator-core' ),
					array( __( 'Bonjour,', 'arvator-core' ), '', __( 'Nous avons bien reçu votre virement : votre abonnement annuel est prolongé d’un an et votre licence est mise à jour.', 'arvator-core' ), '', __( 'Votre facture est disponible dans votre espace membre :', 'arvator-core' ), Arvator_Member::url( 'achats' ) ),
					(string) get_user_meta( $user_id, 'locale', true )
				);
			}
		} else {
			Arvator_Webhook::send_confirmation( $user_id, $created );
		}
	}

	/** Ajoute un an à l'abonnement par virement (crée la ligne la première fois). */
	private static function extend_subscription( int $user_id, string $paid_at, int $is_test ): void {
		global $wpdb;
		$t   = Arvator_Repo::table( 'subscriptions' );
		$now = gmdate( 'Y-m-d H:i:s' );
		$sub = self::subscription( $user_id );
		if ( ! $sub ) {
			$end = gmdate( 'Y-m-d H:i:s', (int) strtotime( $paid_at . ' UTC +1 year' ) );
			$wpdb->insert(
				$t,
				array(
					'user_id'              => $user_id,
					'plan'                 => 'annual',
					'status'               => 'active',
					'current_period_end'   => $end,
					'cancel_at_period_end' => 0,
					'is_test'              => $is_test,
					'method'               => 'bank',
					'created_at'           => $now,
					'updated_at'           => $now,
				)
			);
			return;
		}
		// On repart de l'ancienne échéance si elle n'est pas dépassée, sinon de la date de réception.
		$old  = (int) strtotime( (string) $sub['current_period_end'] . ' UTC' );
		$base = max( $old, (int) strtotime( $paid_at . ' UTC' ) );
		$wpdb->update(
			$t,
			array( 'status' => 'active', 'current_period_end' => gmdate( 'Y-m-d H:i:s', (int) strtotime( '+1 year', $base ) ), 'cancel_at_period_end' => 0, 'updated_at' => $now ),
			array( 'id' => (int) $sub['id'] )
		);
	}

	private static function assign_invoice_number( int $order_id, string $paid_at ): void {
		global $wpdb;
		$row = self::by_id( $order_id );
		if ( ! $row || '' !== (string) $row['invoice_no'] ) {
			return;
		}
		$year = gmdate( 'Y', (int) strtotime( $paid_at . ' UTC' ) );
		$base = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE invoice_no LIKE %s', 'FA-' . $year . '-%' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		for ( $i = 1; $i <= 20; $i++ ) {
			$no  = sprintf( 'FA-%s-%04d', $year, $base + $i );
			$res = $wpdb->query( $wpdb->prepare( 'UPDATE ' . self::table() . ' SET invoice_no = %s WHERE id = %d AND invoice_no IS NULL', $no, $order_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( 1 === $res ) {
				return;
			}
			$check = self::by_id( $order_id );
			if ( $check && '' !== (string) $check['invoice_no'] ) {
				return;
			}
		}
	}

	/* --------------------------------------------------------------------- Actions de l'administrateur */

	/** « Marquer payée » à la main : même traitement que la réception de n8n. */
	public static function mark_paid_manually( int $order_id, string $tx, string $paid_date ): string {
		$order = self::by_id( $order_id );
		if ( ! $order ) {
			return 'commande_inconnue';
		}
		if ( in_array( (string) $order['status'], array( self::PAID, self::REFUNDED ), true ) ) {
			return 'deja_traite';
		}
		$ts   = strtotime( $paid_date . ' 12:00:00 UTC' );
		$paid = false !== $ts && $ts > 0 ? gmdate( 'Y-m-d H:i:s', min( $ts, time() ) ) : gmdate( 'Y-m-d H:i:s' );
		if ( ! Arvator_Repo::log_event( 'bank_received', (int) $order['user_id'] ?: null, array( 'reference' => $order['reference'], 'cents' => (int) $order['amount_cents'], 'manual' => true ), 'bank:' . $tx ) ) {
			return 'transaction_deja_utilisee';
		}
		try {
			self::fulfill( $order, $tx, (int) $order['amount_cents'], $paid );
		} catch ( Throwable $e ) {
			Arvator_Repo::release_event( 'bank:' . $tx );
			Arvator_Repo::log_event( 'bank_error', null, array( 'tx' => $tx, 'error' => $e->getMessage() ) );
			return 'erreur';
		}
		return 'ok';
	}

	public static function cancel( int $order_id, bool $notify = false ): bool {
		global $wpdb;
		$n = (int) $wpdb->query( $wpdb->prepare( 'UPDATE ' . self::table() . ' SET status = %s, updated_at = %s WHERE id = %d AND status = %s', self::CANCELED, gmdate( 'Y-m-d H:i:s' ), $order_id, self::PENDING ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( $n > 0 && $notify ) {
			$order = self::by_id( $order_id );
			if ( $order ) {
				self::send( (string) $order['email'], __( 'Arvator : commande annulée', 'arvator-core' ), array( __( 'Bonjour,', 'arvator-core' ), '', sprintf( /* translators: %s: référence. */ __( 'Votre commande %s a été annulée faute de virement reçu. Si vous avez payé entre-temps, écrivez-nous en indiquant la référence : nous retrouvons votre paiement.', 'arvator-core' ), (string) $order['reference'] ), '', sprintf( /* translators: %s: adresse. */ __( 'Pour commander à nouveau : %s', 'arvator-core' ), Arvator_Checkout::url() ) ), (string) $order['locale'] );
			}
		}
		return $n > 0;
	}

	/**
	 * Remboursement effectué par Hamid (virement depuis la banque) : mêmes conséquences qu'un remboursement Stripe.
	 * Achat initial : achat marqué remboursé, prime du parrain annulée ou déduite, licence révoquée, abonnement arrêté.
	 * Renouvellement : licence coupée et abonnement arrêté.
	 */
	public static function refund( int $order_id ): bool {
		global $wpdb;
		$order = self::by_id( $order_id );
		if ( ! $order || self::PAID !== (string) $order['status'] ) {
			return false;
		}
		$user_id = (int) $order['user_id'];
		$now     = gmdate( 'Y-m-d H:i:s' );
		$wpdb->update( self::table(), array( 'status' => self::REFUNDED, 'updated_at' => $now ), array( 'id' => $order_id ) );
		if ( 'initial' === (string) $order['kind'] && (int) $order['order_id'] > 0 ) {
			$wpdb->update( Arvator_Repo::table( 'orders' ), array( 'status' => 'refunded' ), array( 'id' => (int) $order['order_id'] ), array( '%s' ), array( '%d' ) );
			Arvator_Referral::on_order_reversed( (int) $order['order_id'], 'refund' );
		}
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . Arvator_Repo::table( 'licenses' ) . " SET status = 'revoked', updated_at = %s WHERE user_id = %d AND status = 'active'", $now, $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . Arvator_Repo::table( 'subscriptions' ) . " SET status = 'canceled', updated_at = %s WHERE user_id = %d AND method = 'bank'", $now, $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		Arvator_License::on_cancel( $user_id, 'annual', 'res:bank:' . $order['reference'] );
		Arvator_Repo::log_event( 'bank_refund', $user_id, array( 'reference' => $order['reference'] ) );
		return true;
	}

	/* --------------------------------------------------------------------- Tâche quotidienne : rappels, annulations, renouvellements */

	public static function daily(): void {
		global $wpdb;
		$now = time();

		// Achats non payés : rappels à J+3 et J+7, annulation automatique après le délai réglé (30 jours par défaut).
		$pending = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . " WHERE kind = 'initial' AND status = %s", self::PENDING ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		foreach ( $pending as $o ) {
			$age = (int) floor( ( $now - (int) strtotime( (string) $o['created_at'] . ' UTC' ) ) / DAY_IN_SECONDS );
			if ( $age >= self::cancel_days() ) {
				self::cancel( (int) $o['id'], true );
				continue;
			}
			$stage = (int) $o['reminder_stage'];
			$want  = $age >= 7 ? 2 : ( $age >= 3 ? 1 : 0 );
			if ( $want > $stage ) {
				self::send_reminder( $o, $age );
				$wpdb->update( self::table(), array( 'reminder_stage' => $want ), array( 'id' => (int) $o['id'] ) );
			}
		}

		// Renouvellements : commande créée 30 jours avant l'échéance, puis rappels J-30, J-14, J-7, J-3, J-1, J0, J+1, J+2.
		$subs = (array) $wpdb->get_results( 'SELECT * FROM ' . Arvator_Repo::table( 'subscriptions' ) . " WHERE method = 'bank' AND status IN ('active','past_due') AND cancel_at_period_end = 0", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		foreach ( $subs as $s ) {
			$due = (int) strtotime( (string) $s['current_period_end'] . ' UTC' );
			if ( $due > 0 && $due - $now <= 30 * DAY_IN_SECONDS ) {
				self::create_renewal( (int) $s['user_id'], false );
			}
		}
		$renewals = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . " WHERE kind = 'renewal' AND status = %s", self::PENDING ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		foreach ( $renewals as $o ) {
			$d = (int) floor( ( $now - (int) strtotime( (string) $o['due_at'] . ' UTC' ) ) / DAY_IN_SECONDS );
			if ( $d >= self::cancel_days() ) {
				self::cancel( (int) $o['id'], false );
				continue;
			}
			$stage = (int) $o['reminder_stage'];
			$idx   = -1;
			foreach ( self::OFFSETS as $i => $offset ) {
				if ( $offset <= $d ) {
					$idx = $i;
				}
			}
			if ( $idx >= 0 && $idx + 1 > $stage ) {
				self::send_reminder( $o, self::OFFSETS[ $idx ] );
				$wpdb->update( self::table(), array( 'reminder_stage' => $idx + 1 ), array( 'id' => (int) $o['id'] ) );
			}
			if ( $d >= 1 ) {
				$wpdb->query( $wpdb->prepare( 'UPDATE ' . Arvator_Repo::table( 'subscriptions' ) . " SET status = 'past_due', updated_at = %s WHERE user_id = %d AND method = 'bank' AND status = 'active'", gmdate( 'Y-m-d H:i:s' ), (int) $o['user_id'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}
		}
	}

	/* --------------------------------------------------------------------- Espace membre : renouvellement et facture */

	public static function handle_renew(): void {
		$back = Arvator_Member::url( 'achats' );
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( Arvator_Member::url() );
			exit;
		}
		$nonce = isset( $_POST['_arv_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_arv_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'arvator_bank_renew' ) ) {
			Arvator_Support::redirect( $back, 'generic_invalid' );
		}
		if ( ! self::enabled() ) {
			Arvator_Support::redirect( $back, 'virement_indispo' );
		}
		$user_id = get_current_user_id();
		if ( ! self::subscription( $user_id ) ) {
			Arvator_Support::redirect( $back, 'virement_indispo' );
		}
		$order = self::create_renewal( $user_id, true, true );
		Arvator_Support::redirect( $back, $order ? 'virement_renouv_ok' : 'virement_renouv_deja' );
	}

	/** Commande de renouvellement en attente d'un client (pour afficher les instructions), ou null. @return array<string, mixed>|null */
	public static function open_renewal( int $user_id ): ?array {
		foreach ( self::for_user( $user_id ) as $o ) {
			if ( 'renewal' === $o['kind'] && self::PENDING === $o['status'] ) {
				return $o;
			}
		}
		return null;
	}

	public static function invoice_url( array $order ): string {
		return add_query_arg(
			array(
				'action'     => 'arvator_bank_invoice',
				'id'         => (int) $order['id'],
				'_arv_nonce' => wp_create_nonce( 'arvator_bank_invoice_' . (int) $order['id'] ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/** Facture imprimable : seulement pour le client concerné (ou un administrateur). */
	public static function handle_invoice(): void {
		$id    = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- vérifié ci-dessous
		$nonce = isset( $_GET['_arv_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['_arv_nonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( Arvator_Member::url() );
			exit;
		}
		$order = $id > 0 && wp_verify_nonce( $nonce, 'arvator_bank_invoice_' . $id ) ? self::by_id( $id ) : null;
		$mine  = $order && ( (int) $order['user_id'] === get_current_user_id() || current_user_can( 'manage_options' ) );
		if ( ! $order || ! $mine || '' === (string) $order['invoice_no'] || ! in_array( (string) $order['status'], array( self::PAID, self::REFUNDED ), true ) ) {
			wp_die( esc_html__( 'Facture introuvable.', 'arvator-core' ), '', array( 'response' => 404 ) );
		}
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		echo self::invoice_html( $order ); // phpcs:ignore WordPress.Security.EscapeOutput -- page échappée dans invoice_html()
		exit;
	}

	/** @param array<string, mixed> $o */
	public static function invoice_html( array $o ): string {
		$lines = array();
		if ( (int) $o['bot_cents'] > 0 || 'initial' === (string) $o['kind'] ) {
			$lines[] = array( __( 'Robot Arvator (licence d’utilisation)', 'arvator-core' ), (int) $o['bot_cents'] );
		}
		$lines[] = array( __( 'Abonnement annuel Arvator (1 an)', 'arvator-core' ), (int) $o['sub_cents'] );
		$total   = (int) $o['bot_cents'] + (int) $o['sub_cents'];
		$title   = sprintf( /* translators: %s: numéro de facture. */ __( 'Facture %s', 'arvator-core' ), (string) $o['invoice_no'] );
		ob_start();
		?>
<!doctype html>
<html lang="<?php echo esc_attr( str_replace( '_', '-', determine_locale() ) ); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html( $title ); ?></title>
<style>body{font:15px/1.5 system-ui,sans-serif;max-width:760px;margin:2rem auto;padding:0 1rem;color:#111}h1{font-size:1.5rem}table{width:100%;border-collapse:collapse;margin:1.5rem 0}th,td{padding:.5rem;border-bottom:1px solid #ccc;text-align:left}td:last-child,th:last-child{text-align:right}.cols{display:flex;gap:2rem;flex-wrap:wrap}.cols>div{flex:1 1 280px}.muted{color:#555;font-size:.9rem}@media print{button{display:none}}</style>
</head>
<body>
<button type="button" onclick="window.print()"><?php esc_html_e( 'Imprimer / enregistrer en PDF', 'arvator-core' ); ?></button>
<h1><?php echo esc_html( $title ); ?></h1>
<p class="muted"><?php /* translators: 1: date, 2: référence. */ echo esc_html( sprintf( __( 'Date : %1$s · Référence de paiement : %2$s', 'arvator-core' ), self::date( (string) $o['paid_at'] ), (string) $o['reference'] ) ); ?></p>
<div class="cols">
	<div><strong><?php esc_html_e( 'Vendeur', 'arvator-core' ); ?></strong><br>Hamid DIBARH, entrepreneur individuel<br>COSMARTIS (Arvator)<br><?php echo esc_html( Arvator_Site::POSTAL ); ?><br>SIRET 802 301 317 00029<br><?php echo esc_html( Arvator_Site::EMAIL ); ?></div>
	<div><strong><?php esc_html_e( 'Client', 'arvator-core' ); ?></strong><br><?php echo esc_html( (string) $o['name'] ); ?><br><?php echo esc_html( (string) $o['email'] ); ?></div>
</div>
<table>
<thead><tr><th><?php esc_html_e( 'Désignation', 'arvator-core' ); ?></th><th><?php esc_html_e( 'Montant', 'arvator-core' ); ?></th></tr></thead>
<tbody>
		<?php foreach ( $lines as [ $label, $cents ] ) : ?>
<tr><td><?php echo esc_html( $label ); ?></td><td><?php echo esc_html( self::eur( $cents ) ); ?></td></tr>
		<?php endforeach; ?>
<tr><th><?php esc_html_e( 'Total', 'arvator-core' ); ?></th><th><?php echo esc_html( self::eur( $total ) ); ?></th></tr>
</tbody>
</table>
<p><?php esc_html_e( 'Payé par virement bancaire.', 'arvator-core' ); ?></p>
<p class="muted"><?php echo esc_html( self::vat_note() ); ?></p>
</body>
</html>
		<?php
		return (string) ob_get_clean();
	}
}

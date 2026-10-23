<?php
/**
 * Licence : le site prévient n8n (workflow « Licences cBot – Paiements ») de chaque paiement, résiliation et essai.
 *
 * Circuit A (décidé avec Hamid le 22/10/2026) : Stripe → WordPress (webhook signé) → n8n → serveur de licences.
 * Le robot récupère ensuite sa licence tout seul auprès du serveur (aucune clé à copier pour le client).
 *
 * Réglages dans wp-config.php (jamais dans le code) :
 *   define( 'ARVATOR_N8N_TOKEN', '...' );                                   // jeton « x-admin-token » du workflow
 *   define( 'ARVATOR_N8N_URL', 'https://n8n.cosmartis.com/webhook' );      // facultatif (valeur par défaut)
 *
 * Fiabilité : chaque envoi est une ligne de la table `license_sync`, avec une origine unique (source_id) :
 * un même paiement Stripe reçu deux fois ne prolonge jamais deux fois la licence. Un envoi qui échoue est retenté
 * toutes les heures (10 fois au plus), puis Hamid est prévenu. Un paiement sans numéro de compte cTrader attend
 * que le client l'indique dans son espace membre.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Arvator_License {

	private const CRON         = 'arvator_license_retry';
	private const MAX_ATTEMPTS = 10;

	public static function init(): void {
		add_action( 'init', array( self::class, 'schedule' ) );
		add_action( self::CRON, array( self::class, 'retry_pending' ) );
		add_action( 'admin_post_arvator_account', array( self::class, 'handle_account' ) );
	}

	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::CRON );
		}
	}

	/* --------------------------------------------------------------------- Réglages */

	public static function token(): string {
		return defined( 'ARVATOR_N8N_TOKEN' ) ? trim( (string) ARVATOR_N8N_TOKEN ) : '';
	}

	private static function base_url(): string {
		$url = defined( 'ARVATOR_N8N_URL' ) ? trim( (string) ARVATOR_N8N_URL ) : 'https://n8n.cosmartis.com/webhook';
		return rtrim( $url, '/' );
	}

	public static function configured(): bool {
		return '' !== self::token();
	}

	/* --------------------------------------------------------------------- Numéro de compte cTrader */

	public static function valid_account( string $account ): bool {
		return 1 === preg_match( '/^[0-9]{4,12}$/', $account );
	}

	public static function clean_account( string $raw ): string {
		return preg_replace( '/\s+/', '', $raw ) ?? '';
	}

	public static function account_for( int $user_id ): string {
		$a = (string) get_user_meta( $user_id, 'arv_ctrader_account', true );
		return self::valid_account( $a ) ? $a : '';
	}

	/** Une licence a-t-elle déjà été activée sur un compte ? (le numéro ne se change plus seul ensuite) */
	public static function activated( int $user_id ): bool {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Arvator_Repo::table( 'license_sync' ) . " WHERE user_id = %d AND action = 'paiement' AND status = 'done'", $user_id ) ) > 0; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/** Enregistre le numéro de compte d'un client (tant qu'aucune licence n'est activée) et débloque les envois en attente. */
	public static function set_account( int $user_id, string $account ): bool {
		if ( ! self::valid_account( $account ) || self::activated( $user_id ) ) {
			return false;
		}
		update_user_meta( $user_id, 'arv_ctrader_account', $account );
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . Arvator_Repo::table( 'license_sync' ) . " SET account = %s, status = 'pending', updated_at = %s WHERE user_id = %d AND status = 'waiting_account'", $account, gmdate( 'Y-m-d H:i:s' ), $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		self::process_user( $user_id );
		return true;
	}

	/* --------------------------------------------------------------------- Événements (appelés par le webhook Stripe) */

	/** Paiement validé (achat ou renouvellement) : prolonger la licence d'une période. */
	public static function on_paid( int $user_id, string $plan, string $source ): void {
		$account = self::account_for( $user_id );
		self::queue( $user_id, 'paiement', self::plan_name( $plan ), $account, $source, '' === $account ? 'waiting_account' : 'pending' );
		self::process_user( $user_id );
	}

	/** Abonnement résilié ou achat remboursé : couper la licence (seulement si elle a été activée). */
	public static function on_cancel( int $user_id, string $plan, string $source ): void {
		global $wpdb;
		// Rien n'a jamais été envoyé : on annule simplement les envois en attente.
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . Arvator_Repo::table( 'license_sync' ) . " SET status = 'canceled', updated_at = %s WHERE user_id = %d AND action = 'paiement' AND status IN ('waiting_account','pending')", gmdate( 'Y-m-d H:i:s' ), $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! self::activated( $user_id ) ) {
			return;
		}
		self::queue( $user_id, 'resilier', self::plan_name( $plan ), self::account_for( $user_id ), $source, 'pending' );
		self::process_user( $user_id );
	}

	private static function plan_name( string $plan ): string {
		return 'annual' === $plan ? 'annuel' : 'mensuel';
	}

	/** Ajoute un envoi (idempotent : une origine donnée n'est enregistrée qu'une fois). */
	private static function queue( int $user_id, string $action, string $plan, string $account, string $source, string $status ): void {
		global $wpdb;
		$now = gmdate( 'Y-m-d H:i:s' );
		$wpdb->query( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare(
				'INSERT IGNORE INTO ' . Arvator_Repo::table( 'license_sync' ) . ' (user_id, action, plan, account, source_id, status, attempts, created_at, updated_at) VALUES (%d, %s, %s, %s, %s, %s, 0, %s, %s)',
				$user_id,
				$action,
				$plan,
				$account,
				substr( $source, 0, 190 ),
				$status,
				$now,
				$now
			)
		);
	}

	/* --------------------------------------------------------------------- Envoi à n8n */

	/** Envoie les envois en attente d'un client, dans l'ordre. */
	public static function process_user( int $user_id ): void {
		global $wpdb;
		$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . Arvator_Repo::table( 'license_sync' ) . " WHERE user_id = %d AND status = 'pending' ORDER BY id", $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		foreach ( (array) $ids as $id ) {
			if ( ! self::process( (int) $id ) ) {
				break; // n8n injoignable : on n'envoie pas la suite dans le désordre, tout sera retenté.
			}
		}
	}

	/** Tâche horaire : retente les envois en attente. */
	public static function retry_pending(): void {
		global $wpdb;
		$users = $wpdb->get_col( 'SELECT DISTINCT user_id FROM ' . Arvator_Repo::table( 'license_sync' ) . " WHERE status = 'pending'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		foreach ( (array) $users as $uid ) {
			self::process_user( (int) $uid );
		}
	}

	/**
	 * Envoie une ligne. Retourne false si n8n n'a pas pu être joint (à retenter), true sinon
	 * (succès, ou refus définitif du serveur de licences, signalé à Hamid).
	 */
	public static function process( int $id ): bool {
		global $wpdb;
		$t   = Arvator_Repo::table( 'license_sync' );
		$job = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d AND status = 'pending'", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! is_array( $job ) ) {
			return true;
		}
		if ( ! self::configured() ) {
			$wpdb->update( $t, array( 'last_error' => 'Jeton n8n absent (ARVATOR_N8N_TOKEN)', 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ), array( 'id' => $id ) );
			return false;
		}
		$user = get_user_by( 'id', (int) $job['user_id'] );
		$res  = self::send(
			'licence-paiement',
			array(
				'compte' => (string) $job['account'],
				'plan'   => (string) $job['plan'],
				'action' => (string) $job['action'],
				'email'  => $user instanceof WP_User ? $user->user_email : '',
				'nom'    => $user instanceof WP_User ? $user->display_name : '',
			)
		);
		$now      = gmdate( 'Y-m-d H:i:s' );
		$attempts = (int) $job['attempts'] + 1;

		if ( is_wp_error( $res ) ) {
			$failed = $attempts >= self::MAX_ATTEMPTS;
			$wpdb->update( $t, array( 'attempts' => $attempts, 'status' => $failed ? 'error' : 'pending', 'last_error' => substr( $res->get_error_message(), 0, 255 ), 'updated_at' => $now ), array( 'id' => $id ) );
			if ( $failed ) {
				self::alert( $job, 'n8n injoignable après ' . self::MAX_ATTEMPTS . ' essais : ' . $res->get_error_message() );
			}
			return false;
		}

		$ok = true === ( $res['ok'] ?? null );
		$wpdb->update(
			$t,
			array(
				'attempts'   => $attempts,
				'status'     => $ok ? 'done' : 'error',
				'last_error' => $ok ? '' : substr( (string) ( $res['raison'] ?? 'refusé' ), 0, 255 ),
				'response'   => substr( (string) wp_json_encode( $res ), 0, 2000 ),
				'updated_at' => $now,
			),
			array( 'id' => $id )
		);
		Arvator_Repo::log_event( 'license_' . $job['action'], (int) $job['user_id'], array( 'ok' => $ok, 'source' => $job['source_id'] ) );
		if ( ! $ok ) {
			self::alert( $job, 'Refus du serveur de licences : ' . (string) ( $res['raison'] ?? 'raison inconnue' ) );
			return true;
		}
		self::record( $job, (string) ( $res['expiration'] ?? '' ) );
		return true;
	}

	/** Met à jour la licence affichée dans l'espace membre. */
	private static function record( array $job, string $expiration ): void {
		global $wpdb;
		$t       = Arvator_Repo::table( 'licenses' );
		$uid     = (int) $job['user_id'];
		$now     = gmdate( 'Y-m-d H:i:s' );
		$plan    = 'annuel' === $job['plan'] ? 'annual' : 'monthly';
		$ts      = '' !== $expiration ? strtotime( $expiration ) : false;
		$expires = false !== $ts ? gmdate( 'Y-m-d H:i:s', $ts ) : null;
		// Licences de ce circuit : sans clé (le robot la reçoit du serveur). Les fausses licences de démonstration ont une clé.
		$current = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE user_id = %d AND license_key = '' ORDER BY id DESC LIMIT 1", $uid ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( 'resilier' === $job['action'] ) {
			if ( $current ) {
				$wpdb->update( $t, array( 'status' => 'canceled', 'updated_at' => $now ), array( 'id' => (int) $current['id'] ) );
			}
			return;
		}
		$row = array(
			'plan'           => $plan,
			'account_number' => (string) $job['account'],
			'expires_at'     => $expires ?? ( $current['expires_at'] ?? $now ),
			'status'         => 'active',
			'updated_at'     => $now,
		);
		if ( $current ) {
			$wpdb->update( $t, $row, array( 'id' => (int) $current['id'] ) );
		} else {
			$wpdb->insert( $t, $row + array( 'user_id' => $uid, 'license_key' => '', 'demo_only' => 0, 'is_test' => 0, 'created_at' => $now ) );
		}
	}

	/**
	 * Appel au workflow n8n.
	 *
	 * @param array<string, string> $body
	 * @return array<string, mixed>|WP_Error
	 */
	public static function send( string $endpoint, array $body ) {
		$res = wp_remote_post(
			self::base_url() . '/' . $endpoint,
			array(
				'timeout' => 20,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'x-admin-token' => self::token(),
				),
				'body'    => (string) wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $res ) ) {
			return new WP_Error( 'arvator_n8n_http', 'n8n injoignable.' );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$data = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( $code >= 400 || ! is_array( $data ) ) {
			return new WP_Error( 'arvator_n8n_bad', 'Réponse n8n inattendue (HTTP ' . $code . ').' );
		}
		return $data;
	}

	/** Essai gratuit : demande directe au serveur de licences (pas de file : la réponse est donnée au visiteur). */
	public static function request_trial( string $email, string $account ) {
		if ( ! self::configured() ) {
			return new WP_Error( 'arvator_n8n_config', 'Jeton n8n absent.' );
		}
		return self::send( 'licence-essai', array( 'compte' => $account, 'email' => $email, 'nom' => '' ) );
	}

	private static function alert( array $job, string $reason ): void {
		$user = get_user_by( 'id', (int) $job['user_id'] );
		wp_mail(
			Arvator_Site::EMAIL,
			'Arvator : licence non mise à jour (action nécessaire)',
			"La licence d'un client n'a pas pu être mise à jour automatiquement.\n\nClient : " . ( $user instanceof WP_User ? $user->user_email : '#' . $job['user_id'] ) . "\nCompte cTrader : " . $job['account'] . "\nAction : " . $job['action'] . ' (' . $job['plan'] . ")\nRaison : $reason\n\nÀ vérifier dans Arvator > Licences (bouton « Renvoyer »), ou dans n8n.\n" . "\n" . Arvator_Site::signature()
		);
	}

	/* --------------------------------------------------------------------- Espace membre */

	/** Formulaire du numéro de compte cTrader (tant qu'aucune licence n'est activée). */
	public static function render_account_form( int $user_id ): void {
		$account = self::account_for( $user_id );
		if ( self::activated( $user_id ) ) {
			?>
			<section class="arv-card">
				<h3><?php esc_html_e( 'Mon compte cTrader', 'arvator-core' ); ?></h3>
				<p><?php /* translators: %s: numéro de compte cTrader. */ echo wp_kses_post( sprintf( __( 'Licence liée au compte cTrader n° <strong>%s</strong>.', 'arvator-core' ), esc_html( $account ) ) ); ?></p>
				<p class="muted"><?php /* translators: %s: adresse de la page Contact. */ echo wp_kses_post( sprintf( __( 'Pour utiliser le robot sur un autre compte, écrivez-nous depuis la page <a href="%s">Contact</a> en indiquant le nouveau numéro : le changement est limité à deux fois par an.', 'arvator-core' ), esc_url( Arvator_Support::page_url( 'contact' ) ) ) ); ?></p>
			</section>
			<?php
			return;
		}
		$waiting = self::waiting( $user_id );
		?>
		<section class="arv-card">
			<h3><?php esc_html_e( 'Mon compte cTrader', 'arvator-core' ); ?></h3>
			<?php if ( $waiting ) : ?>
				<p class="arv-notice arv-notice--err"><?php esc_html_e( 'Votre licence attend votre numéro de compte cTrader : indiquez-le ci-dessous pour l’activer.', 'arvator-core' ); ?></p>
			<?php endif; ?>
			<p><?php esc_html_e( 'La licence est liée au numéro du compte cTrader sur lequel le robot va tourner. Vous le trouvez dans cTrader, en haut de la liste de vos comptes. Vérifiez-le bien : il ne se change plus seul une fois la licence activée.', 'arvator-core' ); ?></p>
			<form class="arv-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="arvator_account">
				<?php wp_nonce_field( 'arvator_account', '_arv_nonce' ); ?>
				<p><label for="arv-account"><?php esc_html_e( 'Numéro de compte cTrader', 'arvator-core' ); ?></label>
					<input id="arv-account" name="account" type="text" inputmode="numeric" pattern="[0-9]{4,12}" maxlength="12" required value="<?php echo esc_attr( $account ); ?>"></p>
				<p><button class="btn btn--primary" type="submit"><?php echo esc_html( $waiting ? __( 'Activer ma licence', 'arvator-core' ) : __( 'Enregistrer', 'arvator-core' ) ); ?></button></p>
			</form>
		</section>
		<?php
	}

	/** Un paiement attend-il le numéro de compte ? */
	public static function waiting( int $user_id ): bool {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Arvator_Repo::table( 'license_sync' ) . " WHERE user_id = %d AND status = 'waiting_account'", $user_id ) ) > 0; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public static function handle_account(): void {
		$back = Arvator_Member::url( 'licence' );
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( Arvator_Member::url() );
			exit;
		}
		$nonce = isset( $_POST['_arv_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_arv_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'arvator_account' ) ) {
			Arvator_Support::redirect( $back, 'generic_invalid' );
		}
		$user_id = get_current_user_id();
		$account = isset( $_POST['account'] ) ? self::clean_account( sanitize_text_field( wp_unslash( (string) $_POST['account'] ) ) ) : '';
		if ( self::activated( $user_id ) ) {
			Arvator_Support::redirect( $back, 'compte_verrouille' );
		}
		if ( ! self::set_account( $user_id, $account ) ) {
			Arvator_Support::redirect( $back, 'compte_invalide' );
		}
		Arvator_Repo::log_event( 'account_set', $user_id );
		Arvator_Support::redirect( $back, self::activated( $user_id ) ? 'compte_active' : 'compte_ok' );
	}
}

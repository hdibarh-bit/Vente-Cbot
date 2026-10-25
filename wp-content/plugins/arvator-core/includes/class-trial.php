<?php
/**
 * Essai gratuit de 14 jours sur compte démo : [arvator_essai].
 *
 * La demande est enregistrée (une seule par adresse email et par numéro de compte) et envoyée à contact@.
 * La renonciation au droit de rétractation est annoncée AVANT la demande (case obligatoire) : celui qui fait
 * l'essai renonce à son droit s'il achète ensuite (décision de Hamid, 21/10/2026). Le rappel est fait au paiement
 * (voir Arvator_Checkout). La licence d'essai est créée tout de suite par le serveur de licences (workflow n8n
 * « licence-essai ») ; si n8n ne répond pas, la demande reste « à traiter » et Hamid l'active à la main.
 * Essai activé : le robot et le guide d'installation (marqués dans Arvator > Fichiers privés) sont joints à l'email ;
 * trop lourds pour l'email, ils sont remplacés par des liens privés valables 14 jours, liés à cet essai.
 * S'il en manque un, Hamid reçoit un email « À faire » qui dit lequel.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Arvator_Trial {

	/** Durée de validité des liens de téléchargement d'essai. */
	private const LINK_DAYS = 14;

	public static function init(): void {
		add_action( 'admin_post_nopriv_arvator_trial_dl', array( self::class, 'serve_trial_file' ) );
		add_action( 'admin_post_arvator_trial_dl', array( self::class, 'serve_trial_file' ) );
		add_shortcode( 'arvator_essai', array( self::class, 'render' ) );
		add_action( 'admin_post_nopriv_arvator_trial', array( self::class, 'handle' ) );
		add_action( 'admin_post_arvator_trial', array( self::class, 'handle' ) );
		Arvator_Support::no_cache_for( array( 'arvator_essai' ) );
	}

	public static function url(): string {
		return Arvator_Support::page_url( 'essai-gratuit' );
	}

	/** Cette adresse email a-t-elle demandé un essai ? */
	public static function has_trial( string $email ): bool {
		$email = strtolower( trim( $email ) );
		if ( '' === $email ) {
			return false;
		}
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM ' . Arvator_Repo::table( 'trials' ) . ' WHERE email = %s', $email ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		) > 0;
	}

	public static function render(): string {
		ob_start();
		echo wp_kses_post( Arvator_Support::render_notice() );
		?>
		<form class="arv-form arv-trial" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate>
			<input type="hidden" name="action" value="arvator_trial">
			<?php wp_nonce_field( 'arvator_trial', '_arv_nonce' ); ?>
			<div class="arv-hp" aria-hidden="true">
				<label><?php esc_html_e( 'Ne pas remplir ce champ', 'arvator-core' ); ?> <input type="text" name="website" value="" tabindex="-1" autocomplete="off"></label>
			</div>
			<p>
				<label for="arv-trial-email"><?php esc_html_e( 'Votre adresse email', 'arvator-core' ); ?></label>
				<input id="arv-trial-email" name="email" type="email" required maxlength="190" autocomplete="email">
			</p>
			<p>
				<label for="arv-trial-account"><?php echo wp_kses_post( __( 'Numéro de votre compte <strong>démo</strong> cTrader', 'arvator-core' ) ); ?></label>
				<input id="arv-trial-account" name="account" type="text" inputmode="numeric" pattern="[0-9]{4,12}" required maxlength="12" autocomplete="off">
				<span class="muted"><?php esc_html_e( 'Uniquement des chiffres. L’essai ne fonctionne que sur un compte démo : aucun argent n’est engagé.', 'arvator-core' ); ?></span>
			</p>
			<p class="arv-check"><label><input type="checkbox" name="waiver" value="1" required> <?php echo wp_kses_post( __( '<strong>Je comprends que, si j’achète le robot après cet essai, je renonce à mon droit de rétractation :</strong> l’achat ne sera alors plus remboursable.', 'arvator-core' ) ); ?></label></p>
			<p class="arv-check"><label><input type="checkbox" name="consent" value="1" required> <?php /* translators: %s: adresse de la politique de confidentialité. */ echo wp_kses_post( sprintf( __( 'J’accepte que mon adresse email soit utilisée pour m’envoyer ma licence d’essai (voir la <a href="%s" target="_blank" rel="noopener">politique de confidentialité</a>).', 'arvator-core' ), esc_url( Arvator_Support::page_url( 'confidentialite' ) ) ) ); ?></label></p>
			<p><button class="btn btn--primary" type="submit"><?php esc_html_e( 'Demander mon essai gratuit', 'arvator-core' ); ?></button></p>
			<p class="muted"><?php esc_html_e( 'Les résultats présentés sur le site sont des performances simulées. Le trading comporte un risque élevé de perte en capital.', 'arvator-core' ); ?></p>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	public static function handle(): void {
		$back  = self::url();
		$nonce = isset( $_POST['_arv_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_arv_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'arvator_trial' ) ) {
			Arvator_Support::redirect( $back, 'generic_invalid' );
		}
		// Champ piège : un robot le remplit. On répond comme si tout allait bien, sans rien enregistrer.
		if ( ! empty( $_POST['website'] ) ) {
			Arvator_Support::redirect( $back, 'essai_ok' );
		}

		$ip_key = 'arv_trial_' . md5( Arvator_Support::client_ip() );
		if ( (int) get_transient( $ip_key ) >= 10 ) {
			Arvator_Support::redirect( $back, 'achat_rate' );
		}
		set_transient( $ip_key, (int) get_transient( $ip_key ) + 1, HOUR_IN_SECONDS );

		$email   = isset( $_POST['email'] ) ? strtolower( sanitize_email( wp_unslash( (string) $_POST['email'] ) ) ) : '';
		$account = isset( $_POST['account'] ) ? preg_replace( '/\s+/', '', sanitize_text_field( wp_unslash( (string) $_POST['account'] ) ) ) : '';
		if ( ! is_email( $email ) || ! preg_match( '/^[0-9]{4,12}$/', (string) $account ) || empty( $_POST['waiver'] ) || empty( $_POST['consent'] ) ) {
			Arvator_Support::redirect( $back, 'essai_invalid' );
		}

		global $wpdb;
		$inserted = $wpdb->query( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare(
				'INSERT IGNORE INTO ' . Arvator_Repo::table( 'trials' ) . ' (email, account_number, status, waiver_at, created_at) VALUES (%s, %s, %s, %s, %s)',
				$email,
				$account,
				'requested',
				gmdate( 'Y-m-d H:i:s' ),
				gmdate( 'Y-m-d H:i:s' )
			)
		);
		if ( 1 !== $inserted ) {
			Arvator_Support::redirect( $back, 'essai_deja' );
		}
		Arvator_Repo::log_event( 'trial_request', null, array( 'account' => $account ) );

		// Activation automatique de la licence d'essai par le serveur de licences.
		$res    = Arvator_License::request_trial( $email, $account );
		$status = is_wp_error( $res ) ? 'requested' : ( true === ( $res['ok'] ?? null ) ? 'active' : 'refused' );
		$wpdb->update( Arvator_Repo::table( 'trials' ), array( 'status' => $status ), array( 'email' => $email ) );
		$reminder = __( "Rappel : si vous achetez le robot après l'essai, vous renoncez à votre droit de rétractation (l'achat n'est alors plus remboursable), comme vous l'avez accepté en demandant l'essai.", 'arvator-core' ) . "\n\n" . __( 'Les résultats présentés sur le site sont des performances simulées. Le trading comporte un risque élevé de perte en capital.', 'arvator-core' ) . "\n\n" . Arvator_Site::signature();

		if ( 'refused' === $status ) {
			$reason = (string) ( $res['raison'] ?? '' );
			wp_mail( Arvator_Site::EMAIL, 'Arvator : essai gratuit refusé par le serveur de licences', "Demande d'essai refusée automatiquement.\n\nEmail : $email\nCompte démo cTrader : $account\nRaison : $reason\n" . "\n" . Arvator_Site::signature() );
			Arvator_Support::redirect( $back, 'essai_refuse' );
		}
		if ( 'active' === $status ) {
			self::send_activated( $email, $account, (string) ( $res['expiration'] ?? '' ), $reminder );
		}

		// n8n injoignable : demande enregistrée, activée à la main par Hamid.
		wp_mail(
			Arvator_Site::EMAIL,
			'Arvator : nouvelle demande d’essai gratuit (à activer à la main)',
			"Nouvelle demande d'essai (14 jours, compte démo). L'activation automatique n'a pas fonctionné : à activer dans n8n.\n\nEmail : $email\nCompte démo cTrader : $account\nRenonciation au droit de rétractation cochée : oui\n" . "\n" . Arvator_Site::signature()
		);
		wp_mail(
			$email,
			__( 'Arvator : votre demande d’essai est bien reçue', 'arvator-core' ),
			/* translators: %s: numéro du compte démo cTrader. */
			sprintf( __( "Bonjour,\n\nNous avons bien reçu votre demande d'essai gratuit de 14 jours sur le compte démo cTrader n° %s.\nVous recevrez votre licence d'essai par email, à cette adresse.", 'arvator-core' ), $account ) . "\n\n" . $reminder
		);

		Arvator_Support::redirect( $back, 'essai_ok' );
	}

	/**
	 * Essai activé : envoie le robot et le guide au prospect (pièces jointes, ou liens privés s'ils sont trop lourds),
	 * informe Hamid, puis affiche le message de confirmation. Sans robot ou sans guide : envoi à la main par Hamid.
	 */
	private static function send_activated( string $email, string $account, string $until, string $reminder ): never {
		$robot   = Arvator_Downloads::role_file( 'robot' );
		$guide   = Arvator_Downloads::role_file( 'guide' );
		$missing = array();
		if ( null === $robot ) {
			$missing[] = 'le fichier du robot';
		}
		if ( null === $guide ) {
			$missing[] = 'le guide d’installation (PDF)';
		}
		$video = Arvator_Downloads::video_url();
		/* translators: %s: numéro du compte démo cTrader. */
		$intro       = sprintf( __( "Bonjour,\n\nVotre essai gratuit de 14 jours est activé sur le compte démo cTrader n° %s.", 'arvator-core' ), $account );
		$after       = __( 'Une fois installé sur ce compte démo, le robot récupère sa licence d’essai tout seul.', 'arvator-core' );
		$video_text  = '' !== $video ? "\n\n" . __( 'Vidéo de démonstration de l’installation :', 'arvator-core' ) . "\n" . $video : '';
		$facts       = "Email : $email\nCompte démo cTrader : $account\nFin de l'essai : $until\nRenonciation au droit de rétractation cochée : oui\n";
		$attachments = array();

		if ( $missing ) {
			$body = $intro . "\n" . __( 'Nous vous envoyons le robot et son guide d’installation par email.', 'arvator-core' ) . ' ' . $after;
			wp_mail(
				Arvator_Site::EMAIL,
				'Arvator : essai gratuit activé (robot à envoyer)',
				"Une licence d'essai a été activée automatiquement.\n\n$facts\nÀ faire : envoyer le fichier du robot et le guide d'installation à cette adresse.\nEnvoi automatique impossible : il manque " . implode( ' et ', $missing ) . ". Marquez-les dans Arvator > Fichiers privés pour les prochains essais.\n\n" . Arvator_Site::signature()
			);
			$notice = 'essai_actif_manuel';
		} elseif ( $robot['size'] + $guide['size'] <= Arvator_Downloads::max_attach_bytes() ) {
			$attachments = Arvator_Downloads::attachments( array( $robot, $guide ) );
			$body        = $intro . "\n" . __( 'Le robot et son guide d’installation sont joints à cet email.', 'arvator-core' ) . ' ' . $after;
			wp_mail( Arvator_Site::EMAIL, 'Arvator : essai gratuit activé (robot et guide envoyés)', "Une licence d'essai a été activée automatiquement.\n\n$facts\nRobot et guide envoyés automatiquement (joints à l'email).\n\n" . Arvator_Site::signature() );
			$notice = 'essai_actif';
		} else {
			$token = bin2hex( random_bytes( 32 ) );
			global $wpdb;
			$wpdb->update(
				Arvator_Repo::table( 'trials' ),
				array(
					'dl_token_hash' => hash( 'sha256', $token ),
					'dl_expires'    => gmdate( 'Y-m-d H:i:s', time() + self::LINK_DAYS * DAY_IN_SECONDS ),
				),
				array( 'email' => $email )
			);
			/* translators: %d: nombre de jours. */
			$body  = $intro . "\n" . sprintf( __( 'Le robot et son guide d’installation sont à télécharger avec ces liens personnels (valables %d jours) :', 'arvator-core' ), self::LINK_DAYS );
			$body .= "\n\n" . __( 'Robot :', 'arvator-core' ) . ' ' . self::trial_link( $token, 'robot' ) . "\n" . __( 'Guide d’installation (PDF) :', 'arvator-core' ) . ' ' . self::trial_link( $token, 'guide' ) . "\n\n" . $after;
			wp_mail( Arvator_Site::EMAIL, 'Arvator : essai gratuit activé (robot et guide envoyés)', "Une licence d'essai a été activée automatiquement.\n\n$facts\nRobot et guide envoyés automatiquement (liens de téléchargement privés valables " . self::LINK_DAYS . " jours : fichiers trop lourds pour être joints).\n\n" . Arvator_Site::signature() );
			$notice = 'essai_actif';
		}

		wp_mail( $email, __( 'Arvator : votre essai gratuit est activé', 'arvator-core' ), $body . $video_text . "\n\n" . $reminder, '', $attachments );
		Arvator_Support::redirect( self::url(), $notice );
	}

	private static function trial_link( string $token, string $role ): string {
		return add_query_arg(
			array(
				'action' => 'arvator_trial_dl',
				't'      => $token,
				'f'      => $role,
			),
			admin_url( 'admin-post.php' )
		);
	}

	/** Lien privé d'essai : le jeton (jamais stocké en clair) doit correspondre à un essai actif non expiré. */
	public static function serve_trial_file(): void {
		$token = isset( $_GET['t'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['t'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- le jeton secret fait office de clé
		$role  = isset( $_GET['f'] ) ? sanitize_key( wp_unslash( (string) $_GET['f'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$deny  = static function (): never {
			wp_die( esc_html__( 'Ce lien n’est pas valable ou a expiré. Écrivez-nous depuis la page Contact.', 'arvator-core' ), esc_html__( 'Lien expiré', 'arvator-core' ), array( 'response' => 403 ) );
		};
		if ( 1 !== preg_match( '/^[a-f0-9]{64}$/', $token ) || ! isset( Arvator_Downloads::ROLES[ $role ] ) ) {
			$deny();
		}
		global $wpdb;
		$trial = $wpdb->get_row(
			$wpdb->prepare( 'SELECT id, status, dl_expires FROM ' . Arvator_Repo::table( 'trials' ) . ' WHERE dl_token_hash = %s', hash( 'sha256', $token ) ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);
		if ( ! is_array( $trial ) || 'active' !== $trial['status'] || strtotime( (string) $trial['dl_expires'] . ' UTC' ) < time() ) {
			$deny();
		}
		$file = Arvator_Downloads::role_file( $role );
		if ( null === $file ) {
			wp_die( esc_html__( 'Fichier introuvable.', 'arvator-core' ), esc_html__( 'Erreur', 'arvator-core' ), array( 'response' => 404 ) );
		}
		Arvator_Repo::log_event( 'trial_download', null, array( 'trial' => (int) $trial['id'], 'file' => $role ) );
		Arvator_Downloads::send_file( $file['id'] );
	}
}

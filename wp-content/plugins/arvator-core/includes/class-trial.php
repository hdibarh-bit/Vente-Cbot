<?php
/**
 * Essai gratuit de 14 jours sur compte démo : [arvator_essai].
 *
 * La demande est enregistrée (une seule par adresse email et par numéro de compte) et envoyée à contact@.
 * La renonciation au droit de rétractation est annoncée AVANT la demande (case obligatoire) : celui qui fait
 * l'essai renonce à son droit s'il achète ensuite (décision de Hamid, 21/10/2026). Le rappel est fait au paiement
 * (voir Arvator_Checkout). La licence d'essai est créée tout de suite par le serveur de licences (workflow n8n
 * « licence-essai ») ; si n8n ne répond pas, la demande reste « à traiter » et Hamid l'active à la main.
 * Le fichier du robot est envoyé par Hamid (l'email qu'il reçoit le lui rappelle).
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Arvator_Trial {

	public static function init(): void {
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
				<label>Ne pas remplir ce champ <input type="text" name="website" value="" tabindex="-1" autocomplete="off"></label>
			</div>
			<p>
				<label for="arv-trial-email">Votre adresse email</label>
				<input id="arv-trial-email" name="email" type="email" required maxlength="190" autocomplete="email">
			</p>
			<p>
				<label for="arv-trial-account">Numéro de votre compte <strong>démo</strong> cTrader</label>
				<input id="arv-trial-account" name="account" type="text" inputmode="numeric" pattern="[0-9]{4,12}" required maxlength="12" autocomplete="off">
				<span class="muted">Uniquement des chiffres. L’essai ne fonctionne que sur un compte démo : aucun argent n’est engagé.</span>
			</p>
			<p class="arv-check"><label><input type="checkbox" name="waiver" value="1" required> <strong>Je comprends que, si j’achète le robot après cet essai, je renonce à mon droit de rétractation :</strong> l’achat ne sera alors plus remboursable.</label></p>
			<p class="arv-check"><label><input type="checkbox" name="consent" value="1" required> J’accepte que mon adresse email soit utilisée pour m’envoyer ma licence d’essai (voir la <a href="<?php echo esc_url( Arvator_Support::page_url( 'confidentialite' ) ); ?>" target="_blank" rel="noopener">politique de confidentialité</a>).</label></p>
			<p><button class="btn btn--primary" type="submit">Demander mon essai gratuit</button></p>
			<p class="muted">Les résultats présentés sur le site sont des performances simulées. Le trading comporte un risque élevé de perte en capital.</p>
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
		$reminder = "Rappel : si vous achetez le robot après l'essai, vous renoncez à votre droit de rétractation (l'achat n'est alors plus remboursable), comme vous l'avez accepté en demandant l'essai.\n\nLes résultats présentés sur le site sont des performances simulées. Le trading comporte un risque élevé de perte en capital.\n\n" . Arvator_Site::signature();

		if ( 'refused' === $status ) {
			$reason = (string) ( $res['raison'] ?? '' );
			wp_mail( Arvator_Site::EMAIL, 'Arvator : essai gratuit refusé par le serveur de licences', "Demande d'essai refusée automatiquement.\n\nEmail : $email\nCompte démo cTrader : $account\nRaison : $reason\n" . "\n" . Arvator_Site::signature() );
			Arvator_Support::redirect( $back, 'essai_refuse' );
		}
		if ( 'active' === $status ) {
			$until = (string) ( $res['expiration'] ?? '' );
			wp_mail(
				Arvator_Site::EMAIL,
				'Arvator : essai gratuit activé (robot à envoyer)',
				"Une licence d'essai a été activée automatiquement.\n\nEmail : $email\nCompte démo cTrader : $account\nFin de l'essai : $until\nRenonciation au droit de rétractation cochée : oui\n\nÀ faire : envoyer le fichier du robot et le guide d'installation à cette adresse.\n" . "\n" . Arvator_Site::signature()
			);
			wp_mail(
				$email,
				'Arvator : votre essai gratuit est activé',
				"Bonjour,\n\nVotre essai gratuit de 14 jours est activé sur le compte démo cTrader n° $account.\nNous vous envoyons le robot et son guide d'installation par email. Une fois installé sur ce compte démo, le robot récupère sa licence d'essai tout seul.\n\n" . $reminder
			);
			Arvator_Support::redirect( $back, 'essai_actif' );
		}

		// n8n injoignable : demande enregistrée, activée à la main par Hamid.
		wp_mail(
			Arvator_Site::EMAIL,
			'Arvator : nouvelle demande d’essai gratuit (à activer à la main)',
			"Nouvelle demande d'essai (14 jours, compte démo). L'activation automatique n'a pas fonctionné : à activer dans n8n.\n\nEmail : $email\nCompte démo cTrader : $account\nRenonciation au droit de rétractation cochée : oui\n" . "\n" . Arvator_Site::signature()
		);
		wp_mail(
			$email,
			'Arvator : votre demande d’essai est bien reçue',
			"Bonjour,\n\nNous avons bien reçu votre demande d'essai gratuit de 14 jours sur le compte démo cTrader n° $account.\nVous recevrez votre licence d'essai par email, à cette adresse.\n\n" . $reminder
		);

		Arvator_Support::redirect( $back, 'essai_ok' );
	}
}

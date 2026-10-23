<?php
/**
 * Formulaire de contact : [arvator_contact].
 *
 * Protections : clé de sécurité (nonce), champ piège invisible, délai minimum et maximum (signé),
 * limite par adresse IP et par email, validation stricte, aucune adresse de redirection venant du visiteur,
 * en-têtes d'email construits sans aucune saisie brute. Aucun message n'est conservé sur le site.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Arvator_Contact {

	private const SUBJECTS = array(
		'avant-achat' => 'Question avant achat',
		'support'     => 'Aide pour mon compte ou ma licence',
		'autre'       => 'Autre demande',
	);

	/** Sujet affiché au visiteur (traduit) ; l'email envoyé à Hamid garde le libellé français de SUBJECTS. */
	private static function subject_label( string $key ): string {
		return array(
			'avant-achat' => __( 'Question avant achat', 'arvator-core' ),
			'support'     => __( 'Aide pour mon compte ou ma licence', 'arvator-core' ),
			'autre'       => __( 'Autre demande', 'arvator-core' ),
		)[ $key ] ?? (string) ( self::SUBJECTS[ $key ] ?? '' );
	}

	public static function init(): void {
		add_shortcode( 'arvator_contact', array( self::class, 'render' ) );
		add_action( 'admin_post_nopriv_arvator_contact', array( self::class, 'handle' ) );
		add_action( 'admin_post_arvator_contact', array( self::class, 'handle' ) );
		Arvator_Support::no_cache_for( array( 'arvator_contact' ) );
	}

	/** Délai signé : empêche de forger l'heure d'affichage du formulaire. */
	private static function stamp(): string {
		$now = (string) time();
		return $now . '.' . hash_hmac( 'sha256', 'arv-contact|' . $now, wp_salt( 'nonce' ) );
	}

	private static function stamp_age( string $stamp ): ?int {
		$parts = explode( '.', $stamp, 2 );
		if ( 2 !== count( $parts ) || ! ctype_digit( $parts[0] ) ) {
			return null;
		}
		$expected = hash_hmac( 'sha256', 'arv-contact|' . $parts[0], wp_salt( 'nonce' ) );
		if ( ! hash_equals( $expected, $parts[1] ) ) {
			return null;
		}
		return time() - (int) $parts[0];
	}

	public static function render(): string {
		ob_start();
		echo wp_kses_post( Arvator_Support::render_notice() );
		?>
		<form class="arv-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate>
			<input type="hidden" name="action" value="arvator_contact">
			<?php wp_nonce_field( 'arvator_contact', '_arv_nonce' ); ?>
			<input type="hidden" name="arv_ts" value="<?php echo esc_attr( self::stamp() ); ?>">
			<div class="arv-hp" aria-hidden="true">
				<label><?php esc_html_e( 'Ne pas remplir ce champ', 'arvator-core' ); ?> <input type="text" name="website" value="" tabindex="-1" autocomplete="off"></label>
			</div>

			<p>
				<label for="arv-name"><?php esc_html_e( 'Votre nom', 'arvator-core' ); ?></label>
				<input id="arv-name" name="arv_name" type="text" required maxlength="100" autocomplete="name">
			</p>
			<p>
				<label for="arv-email"><?php esc_html_e( 'Votre adresse email', 'arvator-core' ); ?></label>
				<input id="arv-email" name="arv_email" type="email" required maxlength="190" autocomplete="email">
			</p>
			<p>
				<label for="arv-subject"><?php esc_html_e( 'Sujet', 'arvator-core' ); ?></label>
				<select id="arv-subject" name="arv_subject">
					<?php foreach ( array_keys( self::SUBJECTS ) as $key ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( self::subject_label( $key ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p>
				<label for="arv-message"><?php esc_html_e( 'Votre message', 'arvator-core' ); ?></label>
				<textarea id="arv-message" name="arv_message" rows="7" required minlength="20" maxlength="5000"></textarea>
			</p>
			<p class="arv-check">
				<label>
					<input type="checkbox" name="arv_consent" value="1" required>
					<?php /* translators: %s: adresse de la politique de confidentialité. */ echo wp_kses_post( sprintf( __( 'J’accepte que mes données servent à répondre à ma demande (voir la <a href="%s">politique de confidentialité</a>).', 'arvator-core' ), esc_url( Arvator_Support::page_url( 'confidentialite' ) ) ) ); ?>
				</label>
			</p>
			<p><button class="btn btn--primary" type="submit"><?php esc_html_e( 'Envoyer le message', 'arvator-core' ); ?></button></p>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	public static function handle(): void {
		$back = Arvator_Support::page_url( 'contact' );

		// 1. Clé de sécurité.
		$nonce = isset( $_POST['_arv_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_arv_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'arvator_contact' ) ) {
			Arvator_Support::redirect( $back, 'contact_expired' );
		}

		// 2. Champ piège : un robot le remplit. On fait semblant que tout va bien.
		if ( ! empty( $_POST['website'] ) ) {
			Arvator_Support::redirect( $back, 'contact_ok' );
		}

		// 3. Délai : ni trop rapide (robot), ni trop ancien.
		$stamp = isset( $_POST['arv_ts'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['arv_ts'] ) ) : '';
		$age   = self::stamp_age( $stamp );
		if ( null === $age || $age < 3 || $age > 2 * HOUR_IN_SECONDS ) {
			Arvator_Support::redirect( $back, 'contact_expired' );
		}

		// 4. Saisie.
		$name    = self::one_line( isset( $_POST['arv_name'] ) ? wp_unslash( (string) $_POST['arv_name'] ) : '' );
		$email   = isset( $_POST['arv_email'] ) ? sanitize_email( wp_unslash( (string) $_POST['arv_email'] ) ) : '';
		$subject = isset( $_POST['arv_subject'] ) ? sanitize_key( wp_unslash( (string) $_POST['arv_subject'] ) ) : '';
		$message = isset( $_POST['arv_message'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['arv_message'] ) ) : '';
		$consent = ! empty( $_POST['arv_consent'] );

		$len = function_exists( 'mb_strlen' ) ? mb_strlen( $message ) : strlen( $message );
		if (
			'' === $name || mb_strlen( $name ) > 100
			|| ! is_email( $email ) || strlen( $email ) > 190
			|| ! isset( self::SUBJECTS[ $subject ] )
			|| $len < 20 || $len > 5000
			|| ! $consent
		) {
			Arvator_Support::redirect( $back, 'contact_invalid' );
		}

		// 5. Limites : 3 messages par heure et par adresse IP, 1 message toutes les 5 minutes par email.
		$ip_key    = 'arv_ct_i_' . substr( hash( 'sha256', Arvator_Support::client_ip() ), 0, 32 );
		$email_key = 'arv_ct_e_' . substr( hash( 'sha256', strtolower( $email ) ), 0, 32 );
		if ( (int) get_transient( $ip_key ) >= 3 || false !== get_transient( $email_key ) ) {
			Arvator_Support::redirect( $back, 'contact_rate' );
		}
		set_transient( $ip_key, (int) get_transient( $ip_key ) + 1, HOUR_IN_SECONDS );
		set_transient( $email_key, 1, 5 * MINUTE_IN_SECONDS );

		// 6. Envoi. Les en-têtes ne contiennent que des valeurs déjà nettoyées (une seule ligne, email validé).
		$to      = defined( 'ARVATOR_CONTACT_TO' ) && is_email( (string) ARVATOR_CONTACT_TO ) ? (string) ARVATOR_CONTACT_TO : Arvator_Site::EMAIL;
		$title   = '[Arvator] ' . self::SUBJECTS[ $subject ];
		$body    = "Nom : {$name}\nEmail : {$email}\nSujet : " . self::SUBJECTS[ $subject ] . "\n\n" . $message . "\n\n" . Arvator_Site::signature();
		$headers = array(
			'Content-Type: text/plain; charset=UTF-8',
			'Reply-To: ' . $name . ' <' . $email . '>',
		);
		$sent    = wp_mail( $to, $title, $body, $headers );

		Arvator_Support::redirect( $back, $sent ? 'contact_ok' : 'contact_fail' );
	}

	/** Une seule ligne, sans retour à la ligne ni caractère de contrôle, et sans chevrons (utilisé dans un en-tête). */
	private static function one_line( string $s ): string {
		$s = (string) preg_replace( '/[\x00-\x1F\x7F<>"]+/u', ' ', $s );
		return trim( sanitize_text_field( $s ) );
	}
}

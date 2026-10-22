<?php
/**
 * Page d'achat [arvator_achat], départ vers le paiement Stripe (Checkout hébergé) et portail client Stripe.
 *
 * Sécurité : aucune donnée de carte sur ce site ; clé de sécurité (nonce) ; limitation par adresse IP ;
 * adresses de retour fixes (jamais fournies par le visiteur) ; l'adresse de paiement renvoyée par Stripe
 * n'est suivie que si elle appartient à Stripe.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Arvator_Checkout {

	public static function init(): void {
		add_shortcode( 'arvator_achat', array( self::class, 'render' ) );
		add_action( 'admin_post_nopriv_arvator_checkout', array( self::class, 'handle_checkout' ) );
		add_action( 'admin_post_arvator_checkout', array( self::class, 'handle_checkout' ) );
		add_action( 'admin_post_arvator_portal', array( self::class, 'handle_portal' ) );
		Arvator_Support::no_cache_for( array( 'arvator_achat' ) );
	}

	public static function url(): string {
		return Arvator_Support::page_url( 'acheter' );
	}

	/** @return array{achat:float, mensuel:float, annuel:float, annuel_mois:float} */
	private static function amounts(): array {
		$p = function_exists( 'arvator_pricing' ) ? arvator_pricing() : array();
		return array(
			'achat'       => (float) ( $p['achat'] ?? 1500 ),
			'mensuel'     => (float) ( $p['mensuel'] ?? 39.9 ),
			'annuel'      => (float) ( $p['annuel_total'] ?? 358.8 ),
			'annuel_mois' => (float) ( $p['annuel_par_mois'] ?? 29.9 ),
		);
	}

	private static function eur( float $v ): string {
		$cents = (int) round( $v * 100 );
		return number_format( $cents / 100, 0 === $cents % 100 ? 0 : 2, ',', "\u{202F}" ) . "\u{00A0}€";
	}

	/* --------------------------------------------------------------------- Affichage */

	public static function render(): string {
		ob_start();
		echo wp_kses_post( Arvator_Support::render_notice() );

		$user_id = get_current_user_id();
		if ( $user_id && Arvator_Repo::has_paid_order( $user_id ) ) {
			echo '<p>Vous avez déjà acheté Arvator. Retrouvez votre licence et le téléchargement dans votre <a href="' . esc_url( Arvator_Member::url() ) . '">espace membre</a>.</p>';
			return (string) ob_get_clean();
		}
		if ( ! Arvator_Stripe::configured() ) {
			echo '<p>Le paiement en ligne est en cours de configuration. Vous pouvez nous écrire depuis la page <a href="' . esc_url( Arvator_Support::page_url( 'contact' ) ) . '">Contact</a>.</p>';
			return (string) ob_get_clean();
		}

		$a    = self::amounts();
		$user = wp_get_current_user();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- simple présélection d'affichage
		$pref    = isset( $_GET['plan'] ) ? sanitize_key( wp_unslash( (string) $_GET['plan'] ) ) : 'annual';
		$pref    = 'monthly' === $pref ? 'monthly' : 'annual';
		$had_trial = $user_id && Arvator_Trial::has_trial( (string) $user->user_email );
		?>
		<form class="arv-form arv-buy" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="arvator_checkout">
			<?php wp_nonce_field( 'arvator_checkout', '_arv_nonce' ); ?>

			<fieldset class="arv-plans">
				<legend>Choisissez votre abonnement</legend>
				<label class="arv-plan">
					<input type="radio" name="plan" value="annual"<?php checked( 'annual', $pref ); ?>>
					<span><strong>Annuel</strong> · <?php echo esc_html( self::eur( $a['annuel_mois'] ) ); ?> par mois, payés d’avance (<?php echo esc_html( self::eur( $a['annuel'] ) ); ?> par an)<br>
					<span class="muted">Premier paiement : <?php echo esc_html( self::eur( $a['achat'] + $a['annuel'] ) ); ?> (robot + 1 an d’abonnement)</span></span>
				</label>
				<label class="arv-plan">
					<input type="radio" name="plan" value="monthly"<?php checked( 'monthly', $pref ); ?>>
					<span><strong>Mensuel</strong> · <?php echo esc_html( self::eur( $a['mensuel'] ) ); ?> par mois<br>
					<span class="muted">Premier paiement : <?php echo esc_html( self::eur( $a['achat'] + $a['mensuel'] ) ); ?> (robot + 1er mois)</span></span>
				</label>
			</fieldset>

			<p class="muted">Le robot (<?php echo esc_html( self::eur( $a['achat'] ) ); ?>) est payé une seule fois. L’abonnement est obligatoire : il se renouvelle jusqu’à votre résiliation, que vous pouvez faire à tout moment depuis votre espace membre.</p>

			<?php if ( $user_id ) : ?>
				<p>Compte : <strong><?php echo esc_html( $user->user_email ); ?></strong></p>
			<?php else : ?>
				<p>
					<label for="arv-buy-email">Votre adresse email</label>
					<input id="arv-buy-email" name="email" type="email" required autocomplete="email" maxlength="190">
				</p>
			<?php endif; ?>

			<p>
				<label for="arv-buy-ref">Code de recommandation <span class="muted">(facultatif)</span></label>
				<input id="arv-buy-ref" name="ref" type="text" maxlength="12" autocomplete="off" value="<?php echo esc_attr( self::prefill_code() ); ?>">
				<span class="muted">Si quelqu’un vous a recommandé Arvator, son code est déjà rempli ou figure dans le lien qu’il vous a envoyé.</span>
			</p>

			<p class="arv-check"><label><input type="checkbox" name="cgv" value="1" required> J’ai lu et j’accepte les <a href="<?php echo esc_url( Arvator_Support::page_url( 'cgv' ) ); ?>" target="_blank" rel="noopener">conditions générales de vente</a>.</label></p>
			<p class="arv-check"><label><input type="checkbox" name="risks" value="1" required> J’ai lu l’<a href="<?php echo esc_url( Arvator_Support::page_url( 'risques' ) ); ?>" target="_blank" rel="noopener">avertissement sur les risques</a> et je comprends que les résultats présentés sont des performances simulées et que je peux perdre tout ou partie du capital engagé.</label></p>

			<p class="arv-check"><label><input type="checkbox" name="waiver" value="1"<?php echo $had_trial ? ' required' : ''; ?>> <?php echo $had_trial ? '' : 'Si vous avez fait l’essai gratuit : '; ?>je confirme renoncer à mon droit de rétractation, comme annoncé avant l’essai (l’achat n’est alors plus remboursable).</label></p>

			<p><button class="btn btn--primary" type="submit">Payer en toute sécurité avec Stripe</button></p>
			<p class="muted">Paiement sécurisé par Stripe : vos données de carte ne passent jamais par ce site. Vous recevez ensuite un email pour choisir votre mot de passe.</p>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/** Code prérempli : celui mémorisé (avec accord), sinon celui de l'adresse (/acheter/?ref=CODE), sans rien déposer. */
	private static function prefill_code(): string {
		$code = Arvator_Referral::cookie_code();
		if ( '' === $code && isset( $_GET['ref'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- simple préremplissage
			$code = Arvator_Referral::normalize_code( sanitize_text_field( wp_unslash( (string) $_GET['ref'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$code = ( '' !== $code && Arvator_Referral::referrer_by_code( $code ) ) ? $code : '';
		}
		return $code;
	}

	/* --------------------------------------------------------------------- Actions */

	public static function handle_checkout(): void {
		$back  = self::url();
		$nonce = isset( $_POST['_arv_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_arv_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'arvator_checkout' ) ) {
			Arvator_Support::redirect( $back, 'generic_invalid' );
		}
		if ( ! Arvator_Stripe::configured() ) {
			Arvator_Support::redirect( $back, 'achat_erreur' );
		}

		$ip_key = 'arv_buy_' . md5( Arvator_Support::client_ip() );
		if ( (int) get_transient( $ip_key ) >= 10 ) {
			Arvator_Support::redirect( $back, 'achat_rate' );
		}
		set_transient( $ip_key, (int) get_transient( $ip_key ) + 1, HOUR_IN_SECONDS );

		$plan = isset( $_POST['plan'] ) ? sanitize_key( wp_unslash( (string) $_POST['plan'] ) ) : '';
		if ( ! in_array( $plan, array( 'annual', 'monthly' ), true ) || empty( $_POST['cgv'] ) || empty( $_POST['risks'] ) ) {
			Arvator_Support::redirect( $back, 'achat_invalid' );
		}

		$user_id = get_current_user_id();
		if ( $user_id ) {
			if ( Arvator_Repo::has_paid_order( $user_id ) ) {
				Arvator_Support::redirect( $back, 'achat_deja' );
			}
			$email = (string) wp_get_current_user()->user_email;
		} else {
			$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( (string) $_POST['email'] ) ) : '';
		}
		if ( ! is_email( $email ) ) {
			Arvator_Support::redirect( $back, 'achat_invalid' );
		}

		// Code de recommandation : vérifié ici (message clair en cas de faute de frappe), puis transmis à Stripe.
		// Le parrain n'est enregistré qu'une fois le paiement confirmé, avec un nouveau contrôle anti-fraude.
		$typed = isset( $_POST['ref'] ) ? trim( sanitize_text_field( wp_unslash( (string) $_POST['ref'] ) ) ) : '';
		$ref   = Arvator_Referral::normalize_code( $typed );
		if ( '' !== $typed && ( '' === $ref || ! Arvator_Referral::code_usable_for( $ref, $email, $user_id ) ) ) {
			Arvator_Support::redirect( $back, 'achat_code' );
		}

		$waiver = ! empty( $_POST['waiver'] );
		if ( Arvator_Trial::has_trial( $email ) && ! $waiver ) {
			Arvator_Support::redirect( $back, 'achat_renonciation' );
		}

		$params = array(
			'mode'                  => 'subscription',
			'line_items'            => array(
				array(
					'price'    => Arvator_Stripe::price( $plan ),
					'quantity' => 1,
				),
				array(
					'price'    => Arvator_Stripe::price( 'bot' ),
					'quantity' => 1,
				),
			),
			'customer_email'        => $email,
			'success_url'           => add_query_arg( 'arv_notice', 'achat_ok', Arvator_Member::url() ),
			'cancel_url'            => add_query_arg( 'arv_notice', 'achat_annule', $back ),
			'locale'                => 'fr',
			'metadata'              => array(
				'plan'          => $plan,
				'source'        => 'arvator',
				'trial_waiver'  => $waiver ? '1' : '0',
				'ref'           => $ref,
			),
			'subscription_data'     => array(
				'metadata' => array(
					'plan'   => $plan,
					'source' => 'arvator',
				),
			),
		);
		if ( $user_id ) {
			$params['client_reference_id'] = (string) $user_id;
		}

		$session = Arvator_Stripe::request( 'POST', 'checkout/sessions', $params, 'arv-co-' . md5( $email . $plan . gmdate( 'YmdHi' ) ) );
		$url     = is_array( $session ) ? (string) ( $session['url'] ?? '' ) : '';
		if ( is_wp_error( $session ) || ! self::is_stripe_url( $url, 'checkout.stripe.com' ) ) {
			Arvator_Repo::log_event( 'stripe_error', $user_id ?: null, array( 'where' => 'checkout', 'error' => is_wp_error( $session ) ? $session->get_error_message() : 'url invalide' ) );
			Arvator_Support::redirect( $back, 'achat_erreur' );
		}

		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- adresse Stripe vérifiée ci-dessus
		wp_redirect( $url, 303 );
		exit;
	}

	public static function handle_portal(): void {
		$back = Arvator_Member::url( 'achats' );
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( Arvator_Member::url() );
			exit;
		}
		$nonce = isset( $_POST['_arv_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_arv_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'arvator_portal' ) ) {
			Arvator_Support::redirect( $back, 'generic_invalid' );
		}

		$user_id  = get_current_user_id();
		$customer = Arvator_Repo::stripe_customer_for_user( $user_id );
		if ( '' === $customer || '' === Arvator_Stripe::secret() ) {
			Arvator_Support::redirect( $back, 'portal_erreur' );
		}

		$session = Arvator_Stripe::request(
			'POST',
			'billing_portal/sessions',
			array(
				'customer'   => $customer,
				'return_url' => $back,
				'locale'     => 'fr',
			)
		);
		$url = is_array( $session ) ? (string) ( $session['url'] ?? '' ) : '';
		if ( is_wp_error( $session ) || ! self::is_stripe_url( $url, 'billing.stripe.com' ) ) {
			Arvator_Repo::log_event( 'stripe_error', $user_id, array( 'where' => 'portal', 'error' => is_wp_error( $session ) ? $session->get_error_message() : 'url invalide' ) );
			Arvator_Support::redirect( $back, 'portal_erreur' );
		}

		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- adresse Stripe vérifiée ci-dessus
		wp_redirect( $url, 303 );
		exit;
	}

	private static function is_stripe_url( string $url, string $host ): bool {
		$parts = wp_parse_url( $url );
		return is_array( $parts ) && 'https' === ( $parts['scheme'] ?? '' ) && $host === ( $parts['host'] ?? '' );
	}
}

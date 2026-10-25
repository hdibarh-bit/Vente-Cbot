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

	/** Langue des pages Stripe : anglais si la page du site est en anglais, sinon français. */
	private static function stripe_locale(): string {
		return str_starts_with( determine_locale(), 'en' ) ? 'en' : 'fr';
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
			/* translators: %s: adresse de l'espace membre. */
			echo '<p>' . wp_kses_post( sprintf( __( 'Vous avez déjà acheté Arvator. Retrouvez votre licence et le téléchargement dans votre <a href="%s">espace membre</a>.', 'arvator-core' ), esc_url( Arvator_Member::url() ) ) ) . '</p>';
			return (string) ob_get_clean();
		}
		if ( ! Arvator_Stripe::configured() ) {
			/* translators: %s: adresse de la page Contact. */
			echo '<p>' . wp_kses_post( sprintf( __( 'Le paiement en ligne est en cours de configuration. Vous pouvez nous écrire depuis la page <a href="%s">Contact</a>.', 'arvator-core' ), esc_url( Arvator_Support::page_url( 'contact' ) ) ) ) . '</p>';
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
				<legend><?php esc_html_e( 'Choisissez votre abonnement', 'arvator-core' ); ?></legend>
				<label class="arv-plan">
					<input type="radio" name="plan" value="annual"<?php checked( 'annual', $pref ); ?>>
					<span><?php /* translators: 1: prix mensuel, 2: prix annuel. */ echo wp_kses_post( sprintf( __( '<strong>Annuel</strong> · %1$s par mois, payés d’avance (%2$s par an)', 'arvator-core' ), esc_html( self::eur( $a['annuel_mois'] ) ), esc_html( self::eur( $a['annuel'] ) ) ) ); ?><br>
					<span class="muted"><?php /* translators: %s: montant du premier paiement. */ echo esc_html( sprintf( __( 'Premier paiement : %s (robot + 1 an d’abonnement)', 'arvator-core' ), self::eur( $a['achat'] + $a['annuel'] ) ) ); ?></span></span>
				</label>
				<label class="arv-plan">
					<input type="radio" name="plan" value="monthly"<?php checked( 'monthly', $pref ); ?>>
					<span><?php /* translators: %s: prix mensuel. */ echo wp_kses_post( sprintf( __( '<strong>Mensuel</strong> · %s par mois', 'arvator-core' ), esc_html( self::eur( $a['mensuel'] ) ) ) ); ?><br>
					<span class="muted"><?php /* translators: %s: montant du premier paiement. */ echo esc_html( sprintf( __( 'Premier paiement : %s (robot + 1er mois)', 'arvator-core' ), self::eur( $a['achat'] + $a['mensuel'] ) ) ); ?></span></span>
				</label>
			</fieldset>

			<p class="muted"><?php /* translators: %s: prix du robot. */ echo esc_html( sprintf( __( 'Le robot (%s) est payé une seule fois. L’abonnement est obligatoire : il se renouvelle jusqu’à votre résiliation, que vous pouvez faire à tout moment depuis votre espace membre.', 'arvator-core' ), self::eur( $a['achat'] ) ) ); ?></p>

			<?php if ( $user_id ) : ?>
				<p><?php /* translators: %s: adresse email du compte. */ echo wp_kses_post( sprintf( __( 'Compte : <strong>%s</strong>', 'arvator-core' ), esc_html( $user->user_email ) ) ); ?></p>
			<?php else : ?>
				<p>
					<label for="arv-buy-email"><?php esc_html_e( 'Votre adresse email', 'arvator-core' ); ?></label>
					<input id="arv-buy-email" name="email" type="email" required autocomplete="email" maxlength="190">
				</p>
			<?php endif; ?>

			<p>
				<label for="arv-buy-account"><?php esc_html_e( 'Numéro de votre compte cTrader', 'arvator-core' ); ?> <span class="muted"><?php esc_html_e( '(facultatif maintenant)', 'arvator-core' ); ?></span></label>
				<input id="arv-buy-account" name="account" type="text" inputmode="numeric" pattern="[0-9]{4,12}" maxlength="12" autocomplete="off" value="<?php echo esc_attr( $user_id ? Arvator_License::account_for( $user_id ) : '' ); ?>">
				<span class="muted"><?php esc_html_e( 'Le compte sur lequel le robot va tourner : la licence y est liée. Vous pouvez aussi l’indiquer plus tard dans votre espace membre.', 'arvator-core' ); ?></span>
			</p>

			<p>
				<label for="arv-buy-ref"><?php esc_html_e( 'Code de recommandation', 'arvator-core' ); ?> <span class="muted"><?php esc_html_e( '(facultatif)', 'arvator-core' ); ?></span></label>
				<input id="arv-buy-ref" name="ref" type="text" maxlength="12" autocomplete="off" value="<?php echo esc_attr( self::prefill_code() ); ?>">
				<span class="muted"><?php esc_html_e( 'Si quelqu’un vous a recommandé Arvator, son code est déjà rempli ou figure dans le lien qu’il vous a envoyé.', 'arvator-core' ); ?></span>
			</p>

			<?php if ( Arvator_Promo::any_active() ) : ?>
				<p>
					<label for="arv-buy-promo"><?php esc_html_e( 'Code promo', 'arvator-core' ); ?> <span class="muted"><?php esc_html_e( '(facultatif)', 'arvator-core' ); ?></span></label>
					<input id="arv-buy-promo" name="promo" type="text" maxlength="30" autocomplete="off" value="<?php echo esc_attr( self::prefill_promo() ); ?>">
					<span class="muted"><?php esc_html_e( 'La réduction s’affiche sur la page de paiement Stripe, avant de payer.', 'arvator-core' ); ?></span>
				</p>
			<?php endif; ?>

			<p class="arv-check"><label><input type="checkbox" name="cgv" value="1" required> <?php /* translators: %s: adresse des CGV. */ echo wp_kses_post( sprintf( __( 'J’ai lu et j’accepte les <a href="%s" target="_blank" rel="noopener">conditions générales de vente</a>.', 'arvator-core' ), esc_url( Arvator_Support::page_url( 'cgv' ) ) ) ); ?></label></p>
			<p class="arv-check"><label><input type="checkbox" name="risks" value="1" required> <?php /* translators: %s: adresse de la page Risques. */ echo wp_kses_post( sprintf( __( 'J’ai lu l’<a href="%s" target="_blank" rel="noopener">avertissement sur les risques</a> et je comprends que les résultats présentés sont des performances simulées et que je peux perdre tout ou partie du capital engagé.', 'arvator-core' ), esc_url( Arvator_Support::page_url( 'risques' ) ) ) ); ?></label></p>

			<p class="arv-check"><label><input type="checkbox" name="waiver" value="1"<?php echo $had_trial ? ' required' : ''; ?>> <?php echo esc_html( $had_trial ? __( 'je confirme renoncer à mon droit de rétractation, comme annoncé avant l’essai (l’achat n’est alors plus remboursable).', 'arvator-core' ) : __( 'Si vous avez fait l’essai gratuit : je confirme renoncer à mon droit de rétractation, comme annoncé avant l’essai (l’achat n’est alors plus remboursable).', 'arvator-core' ) ); ?></label></p>

			<p><button class="btn btn--primary" type="submit"><?php esc_html_e( 'Payer en toute sécurité avec Stripe', 'arvator-core' ); ?></button></p>
			<p class="muted"><?php esc_html_e( 'Paiement sécurisé par Stripe : vos données de carte ne passent jamais par ce site. Vous recevez ensuite un email pour choisir votre mot de passe.', 'arvator-core' ); ?></p>
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

	/** Code promo prérempli depuis l'adresse (/acheter/?promo=CODE), seulement s'il est utilisable. */
	private static function prefill_promo(): string {
		if ( ! isset( $_GET['promo'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- simple préremplissage
			return '';
		}
		$code = Arvator_Promo::normalize( sanitize_text_field( wp_unslash( (string) $_GET['promo'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$res  = '' !== $code ? Arvator_Promo::check( $code, 'both' ) : 'promo';
		return ( is_array( $res ) || 'promo_formule' === $res ) ? $code : '';
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

		$account = isset( $_POST['account'] ) ? Arvator_License::clean_account( sanitize_text_field( wp_unslash( (string) $_POST['account'] ) ) ) : '';
		if ( '' !== $account && ! Arvator_License::valid_account( $account ) ) {
			Arvator_Support::redirect( $back, 'achat_compte' );
		}

		// Code promo : vérifié ici (actif, date, nombre d'utilisations, formule) ; la réduction est appliquée par Stripe.
		$promo_typed = isset( $_POST['promo'] ) ? trim( sanitize_text_field( wp_unslash( (string) $_POST['promo'] ) ) ) : '';
		$promo       = null;
		if ( '' !== $promo_typed ) {
			$promo = Arvator_Promo::check( $promo_typed, $plan );
			if ( ! is_array( $promo ) ) {
				Arvator_Support::redirect( $back, 'promo_formule' === $promo ? 'achat_promo_formule' : 'achat_promo' );
			}
		}

		// Robot : prix normal, ou prix réduit si le code promo réduit le robot.
		$bot_line = Arvator_Promo::bot_line( is_array( $promo ) ? $promo : null );
		if ( is_wp_error( $bot_line ) ) {
			Arvator_Repo::log_event( 'stripe_error', $user_id ?: null, array( 'where' => 'promo_bot', 'error' => $bot_line->get_error_message() ) );
			Arvator_Support::redirect( $back, 'achat_erreur' );
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
				$bot_line,
			),
			'customer_email'        => $email,
			'success_url'           => add_query_arg( 'arv_notice', 'achat_ok', Arvator_Member::url() ),
			'cancel_url'            => add_query_arg( 'arv_notice', 'achat_annule', $back ),
			'locale'                => self::stripe_locale(),
			'metadata'              => array(
				'plan'          => $plan,
				'source'        => 'arvator',
				'trial_waiver'  => $waiver ? '1' : '0',
				'ref'           => $ref,
				'compte'        => $account,
				'langue'        => determine_locale(),
				'promo'         => $promo ? (string) $promo['code'] : '',
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
		if ( $promo && (int) $promo['sub_percent'] > 0 ) {
			$params['discounts'] = array( array( 'coupon' => (string) $promo['stripe_coupon'] ) );
		}

		$session = Arvator_Stripe::request( 'POST', 'checkout/sessions', $params, 'arv-co-' . md5( $email . $plan . ( $promo ? $promo['code'] : '' ) . gmdate( 'YmdHi' ) ) );
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
				'locale'     => self::stripe_locale(),
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

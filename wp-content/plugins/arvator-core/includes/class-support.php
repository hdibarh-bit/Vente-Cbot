<?php
/**
 * Petits outils partagés : messages d'information, pages jamais mises en cache, adresses.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Arvator_Support {

	/**
	 * Messages affichables. Seuls ces codes (jamais un texte venant de l'adresse) peuvent être affichés.
	 *
	 * @return array<string, array{0:string,1:string}>  code => [type, texte]
	 */
	private static function messages(): array {
		return array(
			'contact_ok'       => array( 'ok', 'Merci, votre message a bien été envoyé. Nous vous répondons par email.' ),
			'contact_invalid'  => array( 'err', 'Votre message n’a pas pu être envoyé : vérifiez les champs (nom, email valide, message d’au moins 20 caractères) et la case de consentement, puis réessayez.' ),
			'contact_rate'     => array( 'err', 'Vous avez envoyé plusieurs messages à la suite. Merci de patienter un moment avant de réessayer.' ),
			'contact_fail'     => array( 'err', 'Le message n’a pas pu être envoyé. Vous pouvez nous écrire directement à ' . Arvator_Pages::EMAIL . '.' ),
			'contact_expired'  => array( 'err', 'Le formulaire a expiré. Merci de le renvoyer.' ),
			'login_failed'     => array( 'err', 'Identifiant ou mot de passe incorrect.' ),
			'login_locked'     => array( 'err', 'Trop de tentatives. Réessayez dans 15 minutes.' ),
			'login_invalid'    => array( 'err', 'Votre session de connexion a expiré. Merci de réessayer.' ),
			'pwd_ok'           => array( 'ok', 'Votre mot de passe a été modifié.' ),
			'pwd_wrong'        => array( 'err', 'Le mot de passe actuel est incorrect.' ),
			'pwd_mismatch'     => array( 'err', 'Les deux nouveaux mots de passe ne sont pas identiques.' ),
			'pwd_weak'         => array( 'err', 'Le nouveau mot de passe doit contenir au moins 12 caractères et être différent de l’ancien.' ),
			'pwd_locked'       => array( 'err', 'Trop d’essais. Réessayez dans 15 minutes.' ),
			'privacy_export'   => array( 'ok', 'Un email de confirmation vient de vous être envoyé pour valider votre demande d’export de données.' ),
			'privacy_erase'    => array( 'ok', 'Un email de confirmation vient de vous être envoyé pour valider votre demande d’effacement de données.' ),
			'privacy_pending'  => array( 'err', 'Une demande identique est déjà en cours pour votre compte.' ),
			'privacy_limit'    => array( 'err', 'Vous avez déjà fait une demande aujourd’hui. Réessayez demain.' ),
			'privacy_fail'     => array( 'err', 'La demande n’a pas pu être enregistrée. Écrivez-nous depuis la page Contact.' ),
			'generic_invalid'  => array( 'err', 'Cette action n’a pas pu être effectuée (session expirée). Merci de réessayer.' ),
			'achat_ok'         => array( 'ok', 'Merci pour votre commande ! Dès que Stripe confirme le paiement (quelques instants), vous recevez un email de confirmation. Connectez-vous ensuite pour retrouver votre achat.' ),
			'achat_annule'     => array( 'err', 'Le paiement a été annulé : vous n’avez pas été débité. Vous pouvez recommencer quand vous le souhaitez.' ),
			'achat_invalid'    => array( 'err', 'Merci de choisir un abonnement, d’indiquer une adresse email valide et de cocher les deux cases obligatoires.' ),
			'achat_erreur'     => array( 'err', 'Le paiement n’a pas pu être lancé. Réessayez dans un instant, ou écrivez-nous depuis la page Contact.' ),
			'achat_rate'       => array( 'err', 'Trop de tentatives. Merci de patienter un peu avant de réessayer.' ),
			'achat_deja'       => array( 'err', 'Vous avez déjà acheté Arvator : retrouvez-le dans votre espace membre.' ),
			'essai_ok'         => array( 'ok', 'Votre demande d’essai est bien reçue. Vous recevrez votre licence d’essai par email, à l’adresse indiquée. Un email de confirmation vient de vous être envoyé.' ),
			'essai_actif'      => array( 'ok', 'Votre essai gratuit de 14 jours est activé sur votre compte démo. Nous vous envoyons le robot et son guide d’installation par email.' ),
			'essai_refuse'     => array( 'err', 'L’essai n’a pas pu être activé sur ce compte démo (un essai a peut-être déjà été utilisé). Écrivez-nous depuis la page Contact : nous regardons votre demande.' ),
			'essai_invalid'    => array( 'err', 'Merci d’indiquer une adresse email valide, le numéro de votre compte démo (chiffres uniquement) et de cocher les deux cases.' ),
			'essai_deja'       => array( 'err', 'Un essai a déjà été demandé avec cette adresse email ou ce numéro de compte. L’essai est limité à un par personne : vous pouvez écrire depuis la page Contact.' ),
			'achat_renonciation' => array( 'err', 'Vous avez demandé l’essai gratuit : pour acheter, merci de cocher la case de renonciation au droit de rétractation.' ),
			'portal_erreur'    => array( 'err', 'La gestion de l’abonnement n’a pas pu s’ouvrir. Réessayez dans un instant ou écrivez-nous depuis la page Contact.' ),
			'achat_code'       => array( 'err', 'Ce code de recommandation n’est pas valable. Vérifiez-le, ou laissez le champ vide.' ),
			'parrain_mail'     => array( 'ok', 'Merci ! Un email vient de vous être envoyé à cette adresse : il contient la suite des instructions.' ),
			'parrain_invalid'  => array( 'err', 'Merci d’indiquer une adresse email valide et d’accepter les conditions du programme de parrainage.' ),
			'parrain_ok'       => array( 'ok', 'C’est fait : voici votre lien de recommandation.' ),
			'iban_ok'          => array( 'ok', 'Vos coordonnées de virement sont enregistrées.' ),
			'iban_invalid'     => array( 'err', 'Coordonnées non enregistrées : vérifiez le nom du titulaire, l’IBAN et, pour une entreprise, le numéro SIRET (14 chiffres).' ),
			'compte_ok'        => array( 'ok', 'Votre numéro de compte cTrader est enregistré.' ),
			'compte_active'    => array( 'ok', 'C’est fait : votre licence est activée sur ce compte. Le robot la récupère tout seul à son prochain démarrage.' ),
			'compte_invalide'  => array( 'err', 'Numéro de compte non valable : uniquement des chiffres (4 à 12).' ),
			'compte_verrouille' => array( 'err', 'Votre licence est déjà activée sur un compte. Pour en changer, écrivez-nous depuis la page Contact.' ),
			'achat_compte'     => array( 'err', 'Numéro de compte cTrader non valable : uniquement des chiffres (4 à 12), ou laissez le champ vide pour l’indiquer plus tard.' ),
			'download_denied'  => array( 'err', 'Le téléchargement n’est pas disponible pour votre compte.' ),
		);
	}

	/** Affiche le message demandé par ?arv_notice=code (s'il est connu). */
	public static function render_notice(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- simple affichage d'un message prédéfini
		$code = isset( $_GET['arv_notice'] ) ? sanitize_key( wp_unslash( (string) $_GET['arv_notice'] ) ) : '';
		$all  = self::messages();
		if ( '' === $code || ! isset( $all[ $code ] ) ) {
			return '';
		}
		return sprintf(
			'<div class="arv-notice arv-notice--%s" role="%s">%s</div>',
			esc_attr( $all[ $code ][0] ),
			'err' === $all[ $code ][0] ? 'alert' : 'status',
			esc_html( $all[ $code ][1] )
		);
	}

	/** Redirige vers une page du site avec un message prédéfini. */
	public static function redirect( string $base_url, string $code, array $args = array() ): never {
		$args['arv_notice'] = $code;
		wp_safe_redirect( add_query_arg( $args, $base_url ) );
		exit;
	}

	/** Adresse d'une page à partir de son adresse courte, sinon l'accueil. */
	public static function page_url( string $slug ): string {
		$page = get_page_by_path( $slug, OBJECT, 'page' );
		return $page instanceof WP_Post ? (string) get_permalink( $page ) : home_url( '/' );
	}

	/**
	 * Les pages qui contiennent ces shortcodes ne sont jamais mises en cache
	 * (les formulaires portent une clé de sécurité qui expire ; l'espace membre est personnel).
	 *
	 * @param string[] $shortcodes
	 */
	public static function no_cache_for( array $shortcodes ): void {
		add_action(
			'template_redirect',
			static function () use ( $shortcodes ): void {
				if ( ! is_singular() ) {
					return;
				}
				$post = get_post();
				if ( ! $post instanceof WP_Post ) {
					return;
				}
				foreach ( $shortcodes as $tag ) {
					if ( has_shortcode( $post->post_content, $tag ) ) {
						if ( ! defined( 'DONOTCACHEPAGE' ) ) {
							define( 'DONOTCACHEPAGE', true );
						}
						nocache_headers();
						header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0' );
						return;
					}
				}
			}
		);
	}

	public static function client_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '';
		return false !== filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
	}
}

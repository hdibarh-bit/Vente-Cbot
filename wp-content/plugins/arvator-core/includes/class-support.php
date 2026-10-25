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
			'contact_ok'       => array( 'ok', __( 'Merci, votre message a bien été envoyé. Nous vous répondons par email.', 'arvator-core' ) ),
			'contact_invalid'  => array( 'err', __( 'Votre message n’a pas pu être envoyé : vérifiez les champs (nom, email valide, message d’au moins 20 caractères) et la case de consentement, puis réessayez.', 'arvator-core' ) ),
			'contact_rate'     => array( 'err', __( 'Vous avez envoyé plusieurs messages à la suite. Merci de patienter un moment avant de réessayer.', 'arvator-core' ) ),
			'contact_fail'     => array( 'err', sprintf( /* translators: %s: adresse email du support. */ __( 'Le message n’a pas pu être envoyé. Vous pouvez nous écrire directement à %s.', 'arvator-core' ), Arvator_Site::EMAIL ) ),
			'contact_expired'  => array( 'err', __( 'Le formulaire a expiré. Merci de le renvoyer.', 'arvator-core' ) ),
			'login_failed'     => array( 'err', __( 'Identifiant ou mot de passe incorrect.', 'arvator-core' ) ),
			'login_locked'     => array( 'err', __( 'Trop de tentatives. Réessayez dans 15 minutes.', 'arvator-core' ) ),
			'login_invalid'    => array( 'err', __( 'Votre session de connexion a expiré. Merci de réessayer.', 'arvator-core' ) ),
			'pwd_ok'           => array( 'ok', __( 'Votre mot de passe a été modifié.', 'arvator-core' ) ),
			'pwd_wrong'        => array( 'err', __( 'Le mot de passe actuel est incorrect.', 'arvator-core' ) ),
			'pwd_mismatch'     => array( 'err', __( 'Les deux nouveaux mots de passe ne sont pas identiques.', 'arvator-core' ) ),
			'pwd_weak'         => array( 'err', __( 'Le nouveau mot de passe doit contenir au moins 12 caractères et être différent de l’ancien.', 'arvator-core' ) ),
			'pwd_locked'       => array( 'err', __( 'Trop d’essais. Réessayez dans 15 minutes.', 'arvator-core' ) ),
			'privacy_export'   => array( 'ok', __( 'Un email de confirmation vient de vous être envoyé pour valider votre demande d’export de données.', 'arvator-core' ) ),
			'privacy_erase'    => array( 'ok', __( 'Un email de confirmation vient de vous être envoyé pour valider votre demande d’effacement de données.', 'arvator-core' ) ),
			'privacy_pending'  => array( 'err', __( 'Une demande identique est déjà en cours pour votre compte.', 'arvator-core' ) ),
			'privacy_limit'    => array( 'err', __( 'Vous avez déjà fait une demande aujourd’hui. Réessayez demain.', 'arvator-core' ) ),
			'privacy_fail'     => array( 'err', __( 'La demande n’a pas pu être enregistrée. Écrivez-nous depuis la page Contact.', 'arvator-core' ) ),
			'generic_invalid'  => array( 'err', __( 'Cette action n’a pas pu être effectuée (session expirée). Merci de réessayer.', 'arvator-core' ) ),
			'achat_ok'         => array( 'ok', __( 'Merci pour votre commande ! Dès que Stripe confirme le paiement (quelques instants), vous recevez un email de confirmation. Connectez-vous ensuite pour retrouver votre achat.', 'arvator-core' ) ),
			'achat_annule'     => array( 'err', __( 'Le paiement a été annulé : vous n’avez pas été débité. Vous pouvez recommencer quand vous le souhaitez.', 'arvator-core' ) ),
			'achat_invalid'    => array( 'err', __( 'Merci de choisir un abonnement, d’indiquer une adresse email valide et de cocher les deux cases obligatoires.', 'arvator-core' ) ),
			'achat_erreur'     => array( 'err', __( 'Le paiement n’a pas pu être lancé. Réessayez dans un instant, ou écrivez-nous depuis la page Contact.', 'arvator-core' ) ),
			'achat_rate'       => array( 'err', __( 'Trop de tentatives. Merci de patienter un peu avant de réessayer.', 'arvator-core' ) ),
			'achat_deja'       => array( 'err', __( 'Vous avez déjà acheté Arvator : retrouvez-le dans votre espace membre.', 'arvator-core' ) ),
			'essai_ok'         => array( 'ok', __( 'Votre demande d’essai est bien reçue. Vous recevrez votre licence d’essai par email, à l’adresse indiquée. Un email de confirmation vient de vous être envoyé.', 'arvator-core' ) ),
			'essai_actif'      => array( 'ok', __( 'Votre essai gratuit de 14 jours est activé sur votre compte démo. Nous vous envoyons le robot et son guide d’installation par email.', 'arvator-core' ) ),
			'essai_refuse'     => array( 'err', __( 'L’essai n’a pas pu être activé sur ce compte démo (un essai a peut-être déjà été utilisé). Écrivez-nous depuis la page Contact : nous regardons votre demande.', 'arvator-core' ) ),
			'essai_invalid'    => array( 'err', __( 'Merci d’indiquer une adresse email valide, le numéro de votre compte démo (chiffres uniquement) et de cocher les deux cases.', 'arvator-core' ) ),
			'essai_deja'       => array( 'err', __( 'Un essai a déjà été demandé avec cette adresse email ou ce numéro de compte. L’essai est limité à un par personne : vous pouvez écrire depuis la page Contact.', 'arvator-core' ) ),
			'achat_renonciation' => array( 'err', __( 'Vous avez demandé l’essai gratuit : pour acheter, merci de cocher la case de renonciation au droit de rétractation.', 'arvator-core' ) ),
			'portal_erreur'    => array( 'err', __( 'La gestion de l’abonnement n’a pas pu s’ouvrir. Réessayez dans un instant ou écrivez-nous depuis la page Contact.', 'arvator-core' ) ),
			'achat_promo'      => array( 'err', __( 'Ce code promo n’existe pas ou n’est plus valable. Vérifiez-le, ou laissez le champ vide.', 'arvator-core' ) ),
			'achat_promo_formule' => array( 'err', __( 'Ce code promo ne s’applique pas à la formule choisie. Choisissez l’autre formule, ou laissez le champ vide.', 'arvator-core' ) ),
			'achat_code'       => array( 'err', __( 'Ce code de recommandation n’est pas valable. Vérifiez-le, ou laissez le champ vide.', 'arvator-core' ) ),
			'parrain_mail'     => array( 'ok', __( 'Merci ! Un email vient de vous être envoyé à cette adresse : il contient la suite des instructions.', 'arvator-core' ) ),
			'parrain_invalid'  => array( 'err', __( 'Merci d’indiquer une adresse email valide et d’accepter les conditions du programme de parrainage.', 'arvator-core' ) ),
			'parrain_ok'       => array( 'ok', __( 'C’est fait : voici votre lien de recommandation.', 'arvator-core' ) ),
			'iban_ok'          => array( 'ok', __( 'Vos coordonnées de virement sont enregistrées.', 'arvator-core' ) ),
			'iban_invalid'     => array( 'err', __( 'Coordonnées non enregistrées : vérifiez le nom du titulaire, l’IBAN et, pour une entreprise, le numéro SIRET (14 chiffres).', 'arvator-core' ) ),
			'compte_ok'        => array( 'ok', __( 'Votre numéro de compte cTrader est enregistré.', 'arvator-core' ) ),
			'compte_active'    => array( 'ok', __( 'C’est fait : votre licence est activée sur ce compte. Le robot la récupère tout seul à son prochain démarrage.', 'arvator-core' ) ),
			'compte_invalide'  => array( 'err', __( 'Numéro de compte non valable : uniquement des chiffres (4 à 12).', 'arvator-core' ) ),
			'compte_verrouille' => array( 'err', __( 'Votre licence est déjà activée sur un compte. Pour en changer, écrivez-nous depuis la page Contact.', 'arvator-core' ) ),
			'achat_compte'     => array( 'err', __( 'Numéro de compte cTrader non valable : uniquement des chiffres (4 à 12), ou laissez le champ vide pour l’indiquer plus tard.', 'arvator-core' ) ),
			'achat_virement_annuel' => array( 'err', __( 'Le paiement par virement est disponible pour la formule annuelle. Choisissez l’abonnement annuel, ou payez par carte.', 'arvator-core' ) ),
			'achat_virement_nom' => array( 'err', __( 'Merci d’indiquer votre nom : il figure sur la facture.', 'arvator-core' ) ),
			'achat_virement_zero' => array( 'err', __( 'Avec ce code promo, le total est de 0 € : il n’y a rien à virer. Choisissez le paiement par carte.', 'arvator-core' ) ),
			'virement_indispo' => array( 'err', __( 'Le paiement par virement n’est pas disponible pour le moment.', 'arvator-core' ) ),
			'virement_renouv_ok' => array( 'ok', __( 'Votre commande de renouvellement est créée : les coordonnées du virement sont ci-dessous et vous les recevez par email.', 'arvator-core' ) ),
			'virement_renouv_deja' => array( 'ok', __( 'Une commande de renouvellement est déjà en cours pour votre abonnement : les coordonnées sont ci-dessous.', 'arvator-core' ) ),
			'download_denied'  => array( 'err', __( 'Le téléchargement n’est pas disponible pour votre compte.', 'arvator-core' ) ),
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

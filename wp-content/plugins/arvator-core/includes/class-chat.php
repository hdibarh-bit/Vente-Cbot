<?php
/**
 * Assistant de discussion : bulle en bas à droite de chaque page publique, fenêtre de discussion (demande de Hamid du 28/10/2026).
 *
 * Le navigateur du visiteur écrit directement au workflow n8n « Arvator — Assistant du site (chatbot) »
 * (POST JSON {"messages":[{"role":"user","content":"..."}]} → {"reply":"..."}). C'est n8n qui limite les abus
 * par visiteur (adresse IP) et qui n'accepte que les appels venant d'arvator.com. Le site ne conserve aucun message :
 * la conversation reste seulement dans l'onglet du visiteur, le temps de sa visite.
 *
 * Réglage facultatif dans wp-config.php (ce n'est pas un secret) :
 *   define( 'ARVATOR_CHAT_URL', 'https://n8n.cosmartis.com/webhook/arvator-chat' );
 *   define( 'ARVATOR_CHAT_URL', '' );   // masque l'assistant
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Arvator_Chat {

	private const DEFAULT_URL = 'https://n8n.cosmartis.com/webhook/arvator-chat';

	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	public static function url(): string {
		$url = defined( 'ARVATOR_CHAT_URL' ) ? trim( (string) ARVATOR_CHAT_URL ) : self::DEFAULT_URL;
		return '' !== $url && str_starts_with( $url, 'https://' ) ? $url : '';
	}

	public static function enqueue(): void {
		$url = self::url();
		if ( '' === $url ) {
			return;
		}
		$base = plugin_dir_url( ARVATOR_CORE_DIR . 'arvator-core.php' ) . 'assets/';
		wp_enqueue_style( 'arvator-chat', $base . 'chat.css', array(), ARVATOR_CORE_VERSION );
		wp_enqueue_script(
			'arvator-chat',
			$base . 'chat.js',
			array(),
			ARVATOR_CORE_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_localize_script(
			'arvator-chat',
			'ARVATOR_CHAT',
			array(
				'url'  => $url,
				'site' => wp_parse_url( home_url(), PHP_URL_HOST ),
				'i18n' => array(
					'open'        => __( 'Ouvrir l’assistant Arvator', 'arvator-core' ),
					'close'       => __( 'Fermer l’assistant', 'arvator-core' ),
					'title'       => __( 'Assistant Arvator', 'arvator-core' ),
					'status'      => __( 'Assistant virtuel · répond en quelques secondes', 'arvator-core' ),
					'greeting'    => __( 'Bonjour ! Je suis l’assistant virtuel d’Arvator. Posez-moi vos questions sur le robot, les tarifs, l’essai gratuit ou votre espace membre.', 'arvator-core' ),
					'placeholder' => __( 'Écrivez votre question…', 'arvator-core' ),
					'send'        => __( 'Envoyer', 'arvator-core' ),
					'label'       => __( 'Votre message', 'arvator-core' ),
					'typing'      => __( 'L’assistant écrit…', 'arvator-core' ),
					/* translators: %s: adresse email du support. */
					'error'       => sprintf( __( 'Désolé, l’assistant ne répond pas pour le moment. Réessayez dans un instant, ou écrivez-nous à %s.', 'arvator-core' ), Arvator_Site::EMAIL ),
					'notice'      => __( 'Assistant automatique : ses réponses peuvent contenir des erreurs et ne sont pas un conseil en investissement. N’écrivez pas de données personnelles.', 'arvator-core' ),
					'privacy'     => __( 'Confidentialité', 'arvator-core' ),
					'privacyUrl'  => home_url( '/confidentialite/' ),
					'reset'       => __( 'Nouvelle conversation', 'arvator-core' ),
				),
			)
		);
	}
}

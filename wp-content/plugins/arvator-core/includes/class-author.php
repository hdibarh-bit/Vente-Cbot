<?php
/**
 * Présentation de l'éditeur (photo de Hamid + réseaux sociaux) et liens des réseaux sociaux du pied de page.
 *
 * - Shortcode [arvator_auteur] : photo, nom, réseaux (pages « À propos » et « Mentions légales »).
 * - Arvator_Author::social_links() : liste des réseaux seule, appelée par le pied de page du thème.
 * - Réglages dans Apparence > Personnaliser > « Arvator : éditeur et réseaux sociaux » : photo, liens LinkedIn,
 *   Telegram, Facebook et X. Un réseau sans lien n'est jamais affiché (aucune icône vers une page absente).
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Arvator_Author {

	public const NAME = 'Hamid DIBARH';

	/** Réseaux : clé => [libellé, lien par défaut, domaines acceptés]. */
	private const NETWORKS = array(
		'linkedin' => array( 'LinkedIn', 'https://www.linkedin.com/in/hamid-dibarh-1936b7200', array( 'linkedin.com' ) ),
		'telegram' => array( 'Telegram', 'https://t.me/Bot_Arvator', array( 't.me', 'telegram.me' ) ),
		'facebook' => array( 'Facebook', '', array( 'facebook.com', 'fb.com' ) ),
		'x'        => array( 'X', '', array( 'x.com', 'twitter.com' ) ),
	);

	private const OPT_PHOTO = 'arvator_author_photo';

	public static function init(): void {
		add_shortcode( 'arvator_auteur', array( self::class, 'render_card' ) );
		add_action( 'customize_register', array( self::class, 'customize' ) );
	}

	/* --------------------------------------------------------------------- Données */

	/**
	 * Lien nettoyé : https, domaine officiel du réseau (ou sous-domaine), chemin seulement. '' si invalide.
	 *
	 * @param string[] $hosts
	 */
	public static function clean_url( string $url, array $hosts ): string {
		$p = wp_parse_url( trim( $url ) );
		if ( ! is_array( $p ) || 'https' !== strtolower( (string) ( $p['scheme'] ?? '' ) ) || empty( $p['host'] ) ) {
			return '';
		}
		$host = strtolower( (string) $p['host'] );
		$ok   = false;
		foreach ( $hosts as $h ) {
			$ok = $ok || $host === $h || str_ends_with( $host, '.' . $h );
		}
		if ( ! $ok ) {
			return '';
		}
		$path = isset( $p['path'] ) ? preg_replace( '#[^A-Za-z0-9/._-]#', '', (string) $p['path'] ) : '/';
		return 'https://' . $host . ( '' !== $path ? $path : '/' );
	}

	/** @return array<string, array{label:string, url:string}> Réseaux renseignés seulement. */
	public static function networks(): array {
		$out = array();
		foreach ( self::NETWORKS as $key => [ $label, $default, $hosts ] ) {
			$url = self::clean_url( (string) get_option( 'arvator_social_' . $key, $default ), $hosts );
			if ( '' !== $url ) {
				$out[ $key ] = array( 'label' => $label, 'url' => $url );
			}
		}
		return $out;
	}

	public static function photo_url(): string {
		$id  = (int) get_option( self::OPT_PHOTO, 0 );
		$url = $id > 0 ? wp_get_attachment_image_url( $id, 'large' ) : false;
		// Version dans l'adresse : une nouvelle photo livrée remplace l'ancienne dans les caches.
		return is_string( $url ) && '' !== $url ? $url : add_query_arg( 'ver', ARVATOR_CORE_VERSION, plugins_url( 'assets/hamid-dibarh.jpg', ARVATOR_CORE_DIR . 'arvator-core.php' ) );
	}

	/* --------------------------------------------------------------------- Affichage */

	private static function icon( string $key ): string {
		$paths = array(
			'linkedin' => 'M4.98 3.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5zM3 9.75h4V21H3V9.75zm6.5 0h3.83v1.54h.05c.53-1 1.84-2.06 3.79-2.06 4.05 0 4.8 2.67 4.8 6.13V21h-4v-5.02c0-1.2-.02-2.74-1.67-2.74-1.67 0-1.93 1.3-1.93 2.65V21h-4V9.75z',
			'telegram' => 'M21.9 4.3 18.6 19.8c-.2 1.1-.9 1.4-1.8.9l-5-3.7-2.4 2.3c-.3.3-.5.5-1 .5l.4-5.1 9.3-8.4c.4-.4-.1-.6-.6-.2L6 13.3l-4.9-1.5c-1.1-.3-1.1-1.1.2-1.6L20.5 2.8c.9-.3 1.7.2 1.4 1.5z',
			'facebook' => 'M13.5 21v-7.5h2.53l.38-2.94H13.5V8.7c0-.85.24-1.43 1.46-1.43h1.56V4.64A20.9 20.9 0 0 0 14.25 4.5c-2.25 0-3.79 1.37-3.79 3.9v2.16H7.92v2.94h2.54V21h3.04z',
			'x'        => 'M17.75 3h3.07l-6.7 7.66L22 21h-6.17l-4.83-6.32L5.47 21H2.4l7.17-8.2L2 3h6.33l4.37 5.78L17.75 3zm-1.08 16.17h1.7L7.4 4.74H5.58l11.09 14.43z',
		);
		return '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path fill="currentColor" d="' . esc_attr( $paths[ $key ] ?? '' ) . '"/></svg>';
	}

	/** Liste des réseaux renseignés ('' si aucun). */
	public static function social_links( string $class = 'social-links' ): string {
		$items = '';
		foreach ( self::networks() as $key => $n ) {
			$items .= sprintf(
				'<li><a class="social-links__item social-links__item--%1$s" href="%2$s" target="_blank" rel="noopener noreferrer me" aria-label="%3$s">%4$s<span>%5$s</span></a></li>',
				esc_attr( $key ),
				esc_url( $n['url'] ),
				/* translators: 1: nom de l'éditeur, 2: nom du réseau social. */ esc_attr( sprintf( __( '%1$s sur %2$s (nouvel onglet)', 'arvator-core' ), self::NAME, $n['label'] ) ),
				self::icon( $key ),
				esc_html( $n['label'] )
			);
		}
		return '' === $items ? '' : '<ul class="' . esc_attr( $class ) . '">' . $items . '</ul>';
	}

	public static function render_card(): string {
		return '<figure class="author-card">'
			. '<img class="author-card__photo" src="' . esc_url( self::photo_url() ) . '" alt="' . /* translators: %s: nom de l'éditeur. */ esc_attr( sprintf( __( 'Photo de %s, fondateur et éditeur d’Arvator', 'arvator-core' ), self::NAME ) ) . '" width="720" height="830" loading="lazy" decoding="async">'
			. '<figcaption class="author-card__caption"><strong class="author-card__name">' . esc_html( self::NAME ) . '</strong>'
			. '<span class="author-card__role">' . esc_html__( 'Fondateur et éditeur d’Arvator', 'arvator-core' ) . '</span>'
			. self::social_links()
			. '</figcaption></figure>';
	}

	/* --------------------------------------------------------------------- Personnaliser */

	/** @param WP_Customize_Manager $wp_customize */
	public static function customize( $wp_customize ): void {
		$wp_customize->add_section(
			'arvator_author',
			array(
				'title'       => 'Arvator : éditeur et réseaux sociaux',
				'description' => 'Photo affichée dans « À propos » et « Mentions légales », et liens des réseaux (aussi en pied de page). Laissez un lien vide pour masquer le réseau. Adresse complète en https.',
				'priority'    => 36,
			)
		);
		$wp_customize->add_setting(
			self::OPT_PHOTO,
			array(
				'type'              => 'option',
				'capability'        => 'manage_options',
				'default'           => 0,
				'sanitize_callback' => 'absint',
			)
		);
		$wp_customize->add_control(
			new WP_Customize_Media_Control(
				$wp_customize,
				self::OPT_PHOTO,
				array(
					'label'       => 'Photo de l’éditeur',
					'description' => 'Facultatif : sans choix, la photo livrée avec le plugin est utilisée.',
					'section'     => 'arvator_author',
					'mime_type'   => 'image',
				)
			)
		);
		foreach ( self::NETWORKS as $key => [ $label, $default, $hosts ] ) {
			$wp_customize->add_setting(
				'arvator_social_' . $key,
				array(
					'type'              => 'option',
					'capability'        => 'manage_options',
					'default'           => $default,
					'sanitize_callback' => static fn( $v ): string => self::clean_url( (string) $v, $hosts ),
				)
			);
			$wp_customize->add_control(
				'arvator_social_' . $key,
				array(
					'label'       => 'Lien ' . $label,
					'description' => 'Exemple : https://' . ( 't.me' === $hosts[0] ? '' : 'www.' ) . $hosts[0] . '/…',
					'section'     => 'arvator_author',
					'type'        => 'url',
				)
			);
		}
	}
}

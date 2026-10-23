<?php
/**
 * Arvator : configuration du thème.
 *
 * Thème d'affichage uniquement. Licences, paiements, parrainage, espace client et
 * administration vivent dans le plugin « Arvator Core ».
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ARVATOR_THEME_VERSION', '0.3.0' );

require_once get_theme_file_path( 'inc/template-tags.php' );
require_once get_theme_file_path( 'inc/results.php' );
require_once get_theme_file_path( 'inc/settings.php' );
require_once get_theme_file_path( 'inc/customizer.php' );

add_action(
	'after_setup_theme',
	static function (): void {
		// Traductions (dossier languages/, ex. arvator-en_US.mo) : le français reste la langue d'origine du site.
		load_theme_textdomain( 'arvator', get_template_directory() . '/languages' );
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
		add_theme_support(
			'custom-logo',
			array(
				'height'      => 80,
				'width'       => 320,
				'flex-width'  => true,
				'flex-height' => true,
			)
		);
		register_nav_menus(
			array(
				'primary' => __( 'Menu principal', 'arvator' ),
				'footer'  => __( 'Menu du pied de page', 'arvator' ),
				'legal'   => __( 'Liens légaux', 'arvator' ),
			)
		);
	}
);

/* Styles et script : un seul fichier de chaque, aucune ressource externe. */
add_action(
	'wp_enqueue_scripts',
	static function (): void {
		$css = get_theme_file_path( 'assets/css/main.css' );
		$js  = get_theme_file_path( 'assets/js/main.js' );

		wp_enqueue_style( 'arvator', get_theme_file_uri( 'assets/css/main.css' ), array(), (string) filemtime( $css ) );
		wp_enqueue_script(
			'arvator',
			get_theme_file_uri( 'assets/js/main.js' ),
			array(),
			(string) filemtime( $js ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		// Allègement : les styles de blocs ne servent pas sur la page d'accueil.
		if ( is_front_page() ) {
			wp_dequeue_style( 'wp-block-library' );
			wp_dequeue_style( 'wp-block-library-theme' );
			wp_dequeue_style( 'global-styles' );
			wp_dequeue_style( 'classic-theme-styles' );
		}
	}
);

/* Précharge les deux polices du haut de page. */
add_action(
	'wp_head',
	static function (): void {
		foreach ( array( 'playfair-display-latin-600-normal.woff2', 'inter-latin-400-normal.woff2' ) as $font ) {
			printf(
				'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
				esc_url( get_theme_file_uri( 'assets/fonts/' . $font ) )
			);
		}
	},
	1
);

/* Allègement et discrétion : retire ce que WordPress ajoute sans qu'on en ait besoin. */
add_action(
	'init',
	static function (): void {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_action( 'wp_head', 'wp_generator' );
		remove_action( 'wp_head', 'rsd_link' );
		remove_action( 'wp_head', 'wlwmanifest_link' );
		remove_action( 'wp_head', 'wp_shortlink_wp_head' );
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'wp_oembed_add_host_js' );
	}
);

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		wp_deregister_script( 'wp-embed' );
	},
	100
);

/*
 * Icône du site (favicon) : celle choisie dans Apparence > Personnaliser > Identité du site.
 * Tant qu'aucune n'est choisie, une icône dorée intégrée évite une requête vers une icône absente.
 */
add_action(
	'wp_head',
	static function (): void {
		if ( has_site_icon() ) {
			return;
		}
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><rect width="32" height="32" rx="7" fill="#0B0B0C"/><path d="M16 5 27 27h-5.4L16 15.6 10.4 27H5z" fill="#C9A227"/></svg>';
		printf( '<link rel="icon" href="data:image/svg+xml,%s" type="image/svg+xml">' . "\n", rawurlencode( $svg ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	},
	2
);

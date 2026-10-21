<?php
/**
 * Petites fonctions d'affichage du thème Arvator.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * URL d'une page du site à partir de son adresse courte (ex. « tarifs »).
 */
function arvator_url( string $slug ): string {
	return home_url( '/' . trim( $slug, '/' ) . '/' );
}

/**
 * Liens utilisés quand aucun menu n'est défini dans le tableau de bord WordPress.
 *
 * @return array<int, array{0:string,1:string}>
 */
function arvator_default_links( string $location ): array {
	$links = array(
		'primary' => array(
			array( 'Résultats', 'resultats' ),
			array( 'Tarifs', 'tarifs' ),
			array( 'Parrainage', 'parrainage' ),
			array( 'FAQ', 'faq' ),
		),
		'footer'  => array(
			array( 'Résultats simulés', 'resultats' ),
			array( 'Tarifs', 'tarifs' ),
			array( 'Essai gratuit', 'essai-gratuit' ),
			array( 'Rapport gratuit', 'rapport-gratuit' ),
			array( 'Parrainage', 'parrainage' ),
			array( 'FAQ', 'faq' ),
		),
		'legal'   => array(
			array( 'Risques', 'risques' ),
			array( 'Mentions légales', 'mentions-legales' ),
			array( 'CGV', 'cgv' ),
			array( 'Confidentialité', 'confidentialite' ),
			array( 'Cookies', 'cookies' ),
		),
	);

	return $links[ $location ] ?? array();
}

/**
 * Affiche un menu : celui du tableau de bord s'il existe, sinon les liens par défaut.
 */
function arvator_nav( string $location, string $class ): void {
	if ( has_nav_menu( $location ) ) {
		wp_nav_menu(
			array(
				'theme_location' => $location,
				'container'      => false,
				'menu_class'     => $class,
				'depth'          => 1,
				'fallback_cb'    => false,
			)
		);
		return;
	}

	echo '<ul class="' . esc_attr( $class ) . '">';
	foreach ( arvator_default_links( $location ) as $link ) {
		printf(
			'<li><a href="%s">%s</a></li>',
			esc_url( arvator_url( $link[1] ) ),
			esc_html( $link[0] )
		);
	}
	echo '</ul>';
}

/**
 * Logo : celui choisi dans le tableau de bord, sinon la marque en texte.
 */
function arvator_brand(): void {
	echo '<a class="brand" href="' . esc_url( home_url( '/' ) ) . '" aria-label="' . esc_attr( get_bloginfo( 'name' ) . ' — accueil' ) . '">';
	if ( has_custom_logo() ) {
		echo wp_kses_post( get_custom_logo() );
	} else {
		echo '<svg class="brand__mark" viewBox="0 0 32 32" width="30" height="30" aria-hidden="true" focusable="false"><defs><linearGradient id="arvg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#E6C766"/><stop offset="1" stop-color="#A8841B"/></linearGradient></defs><path d="M16 3 29 28h-6.4L16 14.6 9.4 28H3z" fill="url(#arvg)"/><rect x="11" y="21" width="10" height="2.2" fill="#0B0B0C"/></svg>';
		echo '<span class="brand__name">Arvator</span>';
	}
	echo '</a>';
}

/**
 * Texte d'avertissement obligatoire, identique sur toutes les pages.
 */
function arvator_risk_notice(): string {
	return 'Le trading comporte un risque élevé de perte en capital, pouvant aller jusqu’à la totalité des sommes engagées. Les résultats présentés sont des performances simulées et ne préjugent pas des résultats futurs. Ce logiciel ne constitue pas un conseil en investissement.';
}

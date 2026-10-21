<?php
/**
 * Réglages modifiables par Hamid dans Apparence > Personnaliser.
 *
 * Logo et icône du site (favicon) : section « Identité du site » de WordPress.
 * Menus : section « Menus » de WordPress.
 * Textes : section « Arvator — textes de l'accueil » ci-dessous.
 *
 * Volontairement NON modifiables ici : l'avertissement sur les risques, les chiffres des résultats
 * et les prix (ils doivent rester cohérents avec les règles légales et avec ce qui est facturé).
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Textes par défaut de l'accueil.
 *
 * @return array<string, string>
 */
function arvator_text_defaults(): array {
	return array(
		'hero_eyebrow' => 'Arvator · robot de trading automatique',
		'hero_title'   => "Un robot de trading automatique, transparent, testé sur\u{00A0}2024, 2025 et\u{00A0}2026",
		'hero_lead'    => 'Arvator s’installe sur votre propre compte cTrader et fonctionne dans le cloud, même ordinateur éteint. Vous gardez la main sur votre compte et sur votre niveau de risque.',
		'footer_blurb' => 'Un robot de trading automatique qui s’installe sur votre propre compte cTrader. Nous ne touchons jamais à votre argent ni à votre compte.',
	);
}

/** Texte modifiable (valeur du tableau de bord, sinon valeur par défaut). */
function arvator_text( string $key ): string {
	$defaults = arvator_text_defaults();
	$default  = $defaults[ $key ] ?? '';
	$value    = (string) get_theme_mod( 'arvator_' . $key, $default );

	return '' !== trim( $value ) ? $value : $default;
}

add_action(
	'customize_register',
	static function ( WP_Customize_Manager $wp_customize ): void {
		$wp_customize->add_section(
			'arvator_home',
			array(
				'title'       => 'Arvator — textes de l’accueil',
				'priority'    => 30,
				'description' => 'Ne décrivez jamais la stratégie du robot et ne promettez aucun gain. L’avertissement sur les risques, les résultats et les prix ne se modifient pas ici.',
			)
		);

		$fields = array(
			'hero_eyebrow' => array( 'Petit texte au-dessus du titre', 'text' ),
			'hero_title'   => array( 'Titre principal', 'textarea' ),
			'hero_lead'    => array( 'Texte d’introduction', 'textarea' ),
			'footer_blurb' => array( 'Texte du pied de page', 'textarea' ),
		);
		$defaults = arvator_text_defaults();

		foreach ( $fields as $key => $field ) {
			$wp_customize->add_setting(
				'arvator_' . $key,
				array(
					'default'           => $defaults[ $key ],
					'sanitize_callback' => 'sanitize_textarea_field',
					'transport'         => 'refresh',
				)
			);
			$wp_customize->add_control(
				'arvator_' . $key,
				array(
					'label'   => $field[0],
					'section' => 'arvator_home',
					'type'    => $field[1],
				)
			);
		}

		// Image du bandeau « Recommandez Arvator ! » (illustration dorée par défaut).
		$wp_customize->add_setting(
			'arvator_referral_image',
			array(
				'default'           => 0,
				'sanitize_callback' => 'absint',
				'transport'         => 'refresh',
			)
		);
		$wp_customize->add_control(
			new WP_Customize_Media_Control(
				$wp_customize,
				'arvator_referral_image',
				array(
					'label'       => 'Image du bandeau « Recommandez Arvator »',
					'description' => 'Facultatif. Une photo de deux personnes qui regardent un téléphone, format paysage. Sans image, l’illustration dorée est utilisée. Jamais de billets ni de signes de richesse.',
					'section'     => 'arvator_home',
					'mime_type'   => 'image',
				)
			)
		);
	}
);

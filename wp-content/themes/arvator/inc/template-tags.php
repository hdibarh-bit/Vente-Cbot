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
 * Adresse d'une page du site SI cette page existe et est publiée, sinon chaîne vide.
 * Évite les liens qui mènent à une page « introuvable » tant que la page n'est pas créée.
 */
function arvator_page_url( string $slug ): string {
	static $cache = array();

	$slug = trim( $slug, '/' );
	if ( ! array_key_exists( $slug, $cache ) ) {
		$page          = get_page_by_path( $slug );
		$cache[ $slug ] = ( $page instanceof WP_Post && 'publish' === $page->post_status ) ? (string) get_permalink( $page ) : '';
	}

	return $cache[ $slug ];
}

/**
 * Adresse vers laquelle mène un lien du menu par défaut : la page si elle existe,
 * sinon (pour « résultats » et « tarifs ») la section correspondante de l'accueil, sinon rien.
 */
function arvator_menu_target( string $slug ): string {
	$url = arvator_page_url( $slug );
	if ( '' !== $url ) {
		return $url;
	}
	if ( in_array( $slug, array( __( 'resultats', 'arvator' ), 'tarifs' ), true ) ) {
		return home_url( '/#' . $slug );
	}
	return '';
}

/**
 * Bouton : lien si la page existe. Sinon, aucun bouton (jamais de lien mort ni de mention « bientôt »).
 */
function arvator_button( string $slug, string $label, string $classes = 'btn btn--primary' ): void {
	$url = arvator_page_url( $slug );
	if ( '' === $url ) {
		return;
	}
	printf( '<a class="%s" href="%s">%s</a>', esc_attr( $classes ), esc_url( $url ), esc_html( $label ) );
}

/** Adresse de la page d'achat, avec la formule présélectionnée (annual ou monthly). */
function arvator_buy_url( string $plan = '' ): string {
	$url = arvator_page_url( 'acheter' );
	if ( '' === $url ) {
		return '';
	}
	return in_array( $plan, array( __( 'annual', 'arvator' ), 'monthly' ), true ) ? add_query_arg( 'plan', $plan, $url ) : $url;
}

/** Bouton « Acheter » (masqué si la page d'achat n'existe pas). */
function arvator_buy_button( string $label = '', string $classes = 'btn btn--primary', string $plan = '' ): void {
	$label = '' !== $label ? $label : __( 'Acheter Arvator', 'arvator' );
	$url   = arvator_buy_url( $plan );
	if ( '' !== $url ) {
		printf( '<a class="%s" href="%s">%s</a>', esc_attr( $classes ), esc_url( $url ), esc_html( $label ) );
	}
}

/**
 * Bandeau d'appel à l'action discret : un titre, une phrase, l'essai et l'achat.
 * Jamais de promesse de gain : on invite à essayer ou à acheter.
 */
function arvator_cta_band( string $title, string $text ): void {
	$trial = (int) ( arvator_settings()['essaiJours'] ?? 14 );
	if ( '' === arvator_page_url( 'essai-gratuit' ) && '' === arvator_page_url( 'acheter' ) ) {
		return;
	}
	?>
	<aside class="cta-band cta-band--inline" aria-label="<?php echo esc_attr( $title ); ?>">
		<div>
			<h2><?php echo esc_html( $title ); ?></h2>
			<p><?php echo esc_html( $text ); ?></p>
		</div>
		<div class="cta-band__actions">
			<?php /* translators: %d: durée de l'essai en jours. */
			arvator_button( 'essai-gratuit', sprintf( __( 'Essayer %d jours sur compte démo', 'arvator' ), $trial ), 'btn btn--primary' ); ?>
			<?php arvator_buy_button( __( 'Acheter Arvator', 'arvator' ), 'btn btn--ghost' ); ?>
		</div>
	</aside>
	<?php
}

/**
 * Bandeau « Recommandez Arvator ! » : montant de la récompense de recommandation, image à droite.
 * Affiché seulement si la page « parrainage » (conditions et inscription) est publiée.
 * Toute la carte est un seul lien vers cette page (pastille, texte et image cliquables).
 * Vocabulaire imposé : « récompense de recommandation », jamais « gagner », « revenu » ni « opportunité ».
 */
function arvator_referral_banner(): void {
	$url = arvator_page_url( 'parrainage' );
	if ( '' === $url ) {
		return;
	}

	$ref    = arvator_settings()['parrainage'] ?? array();
	$prime  = (float) ( $ref['primeParAchat'] ?? 225 );
	$delai  = (int) ( $ref['delaiPaiementJours'] ?? 30 );
	$img_id = (int) get_theme_mod( 'arvator_referral_image' );
	?>
	<a class="referral" href="<?php echo esc_url( $url ); ?>" aria-labelledby="titre-recommandation">
		<div class="referral__body">
			<p class="referral__pill"><?php esc_html_e( 'Recommandez Arvator&nbsp;!', 'arvator' ); ?></p>
			<h2 id="titre-recommandation" class="referral__title">
				<span class="referral__amount"><?php echo esc_html( arvator_fmt_price( $prime ) ); ?><sup>*</sup></span>
				<?php esc_html_e( 'par recommandation', 'arvator' ); ?>
			</h2>
			<p class="referral__sub"><?php esc_html_e( 'Pour chaque proche qui achète le robot grâce à votre lien.', 'arvator' ); ?></p>
			<p class="referral__note">
				<?php
				/* translators: %s: délai en jours avant le versement de la récompense. */
				echo wp_kses_post( sprintf( __( '*Récompense de recommandation versée %s&nbsp;jours après l’achat, s’il n’est pas remboursé.', 'arvator' ), esc_html( (string) $delai ) ) );
				?>

				<?php echo wp_kses_post( __( 'Inscription gratuite, <span class="referral__link">voir conditions</span>.', 'arvator' ) ); ?>
			</p>
		</div>
		<div class="referral__media" aria-hidden="true">
			<?php
			if ( $img_id > 0 && wp_attachment_is_image( $img_id ) ) {
				echo wp_get_attachment_image( $img_id, 'large', false, array( 'alt' => '', 'loading' => 'lazy', 'decoding' => 'async' ) );
			} else {
				printf(
					'<img src="%s" alt="" width="480" height="400" loading="lazy" decoding="async">',
					esc_url( get_theme_file_uri( 'assets/img/recommandation.svg' ) )
				);
			}
			?>
		</div>
	</a>
	<?php
}

/**
 * Bouton avec solution de repli : lien vers la page si elle existe, sinon vers une section de l'accueil.
 */
function arvator_button_or_anchor( string $slug, string $label, string $classes, string $fallback_label, string $anchor ): void {
	$url = arvator_page_url( $slug );
	if ( '' !== $url ) {
		printf( '<a class="%s" href="%s">%s</a>', esc_attr( $classes ), esc_url( $url ), esc_html( $label ) );
		return;
	}
	printf( '<a class="%s" href="%s">%s</a>', esc_attr( $classes ), esc_url( home_url( '/#' . $anchor ) ), esc_html( $fallback_label ) );
}

/**
 * Liens utilisés quand aucun menu n'est défini dans le tableau de bord WordPress.
 *
 * @return array<int, array{0:string,1:string}>
 */
function arvator_default_links( string $location ): array {
	$links = array(
		'primary' => array(
			array( __( 'Résultats', 'arvator' ), 'resultats' ),
			array( __( 'Tarifs', 'arvator' ), 'tarifs' ),
			array( __( 'FAQ', 'arvator' ), 'faq' ),
			array( __( 'À propos', 'arvator' ), 'a-propos' ),
			array( __( 'Contact', 'arvator' ), 'contact' ),
		),
		'footer'  => array(
			array( __( 'Résultats simulés', 'arvator' ), 'resultats' ),
			array( __( 'Tarifs', 'arvator' ), 'tarifs' ),
			array( __( 'Essai gratuit', 'arvator' ), 'essai-gratuit' ),
			array( __( 'Rapport gratuit', 'arvator' ), 'rapport-gratuit' ),
			array( __( 'Parrainage', 'arvator' ), 'parrainage' ),
			array( __( 'FAQ', 'arvator' ), 'faq' ),
			array( __( 'À propos', 'arvator' ), 'a-propos' ),
			array( __( 'Contact', 'arvator' ), 'contact' ),
			array( __( 'Espace membre', 'arvator' ), 'espace-membre' ),
		),
		'legal'   => array(
			array( __( 'Risques', 'arvator' ), 'risques' ),
			array( __( 'Mentions légales', 'arvator' ), 'mentions-legales' ),
			array( __( 'CGV', 'arvator' ), 'cgv' ),
			array( __( 'Confidentialité', 'arvator' ), 'confidentialite' ),
			array( __( 'Cookies', 'arvator' ), 'cookies' ),
			array( __( 'Conditions du parrainage', 'arvator' ), 'conditions-parrainage' ),
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
		$target = arvator_menu_target( $link[1] );
		if ( '' === $target ) {
			continue; // la page n'existe pas encore : pas de lien mort.
		}
		printf(
			'<li><a href="%s">%s</a></li>',
			esc_url( $target ),
			esc_html( $link[0] )
		);
	}
	echo '</ul>';
}

/**
 * Logo : celui choisi dans le tableau de bord, sinon la marque en texte.
 */
function arvator_brand(): void {
	echo '<a class="brand" href="' . esc_url( home_url( '/' ) ) . '" aria-label="' . esc_attr( get_bloginfo( 'name' ) . ' — ' . __( 'accueil', 'arvator' ) ) . '">';

	$logo_id = (int) get_theme_mod( 'custom_logo' );
	if ( $logo_id > 0 && wp_attachment_is_image( $logo_id ) ) {
		echo wp_get_attachment_image(
			$logo_id,
			'full',
			false,
			array(
				'class'    => 'custom-logo',
				'alt'      => esc_attr( get_bloginfo( 'name' ) ),
				'decoding' => 'async',
			)
		);
	} else {
		echo '<svg class="brand__mark" viewBox="0 0 32 32" width="30" height="30" aria-hidden="true" focusable="false"><defs><linearGradient id="arvg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#E6C766"/><stop offset="1" stop-color="#A8841B"/></linearGradient></defs><path d="M16 3 29 28h-6.4L16 14.6 9.4 28H3z" fill="url(#arvg)"/><rect x="11" y="21" width="10" height="2.2" fill="#0B0B0C"/></svg>';
		echo '<span class="brand__name">' . esc_html( get_bloginfo( 'name' ) ?: 'Arvator' ) . '</span>';
	}
	echo '</a>';
}

/**
 * Texte d'avertissement obligatoire, identique sur toutes les pages.
 */
function arvator_risk_notice(): string {
	return __( 'Le trading comporte un risque élevé de perte en capital, pouvant aller jusqu’à la totalité des sommes engagées. Les résultats présentés sont des performances simulées et ne préjugent pas des résultats futurs. Ce logiciel ne constitue pas un conseil en investissement.', 'arvator' );
}

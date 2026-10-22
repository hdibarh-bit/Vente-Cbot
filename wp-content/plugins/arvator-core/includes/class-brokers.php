<?php
/**
 * Courtiers compatibles : liste indicative affichée aux clients dans l'espace membre (rubrique « Courtiers »),
 * en bandeau défilant. Décision de Hamid du 22/10/2026 : jamais sur les pages publiques (risque « publicité
 * indirecte » de la loi Sapin 2), jamais de lien d'affiliation.
 *
 * Sécurité des liens : seule l'adresse du site officiel est gardée (https, domaine et chemin) ; tout paramètre
 * (?ref=, ?affid=, #…) est retiré automatiquement, ce qui rend un lien d'affiliation impossible.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Arvator_Brokers {

	private const OPT = 'arvator_brokers';

	public static function init(): void {
		if ( is_admin() ) {
			add_action( 'admin_menu', array( self::class, 'menu' ), 22 );
		}
		add_action( 'admin_post_arvator_brokers_save', array( self::class, 'handle_save' ) );
	}

	/**
	 * Liste de départ : courtiers proposant cTrader avec une licence européenne (recherche du 22/10/2026).
	 * Liste indicative, sans test préalable (décision de Hamid) ; modifiable dans Arvator > Courtiers.
	 *
	 * @return array<int, array{name:string, url:string}>
	 */
	private static function defaults(): array {
		return array(
			array( 'name' => 'Pepperstone', 'url' => 'https://pepperstone.com/' ),
			array( 'name' => 'IC Markets', 'url' => 'https://www.icmarkets.eu/' ),
			array( 'name' => 'FxPro', 'url' => 'https://www.fxpro.com/' ),
			array( 'name' => 'FP Markets', 'url' => 'https://www.fpmarkets.com/' ),
		);
	}

	/** @return array<int, array{name:string, url:string}> */
	public static function all(): array {
		$saved = get_option( self::OPT, null );
		return is_array( $saved ) ? $saved : self::defaults();
	}

	/** Adresse nettoyée : https, domaine et chemin seulement (aucun paramètre de suivi). '' si invalide. */
	public static function clean_url( string $url ): string {
		$p = wp_parse_url( trim( $url ) );
		if ( ! is_array( $p ) || 'https' !== strtolower( (string) ( $p['scheme'] ?? '' ) ) || empty( $p['host'] ) || ! preg_match( '/^[a-z0-9.-]+\.[a-z]{2,}$/i', (string) $p['host'] ) ) {
			return '';
		}
		$path = isset( $p['path'] ) ? preg_replace( '#[^A-Za-z0-9/._-]#', '', (string) $p['path'] ) : '/';
		return 'https://' . strtolower( (string) $p['host'] ) . ( '' !== $path ? $path : '/' );
	}

	/**
	 * Lit la liste saisie dans l'administration : une ligne par courtier, « Nom | https://adresse ».
	 *
	 * @return array{0: array<int, array{name:string, url:string}>, 1: string[]}  [courtiers retenus, lignes refusées]
	 */
	public static function parse( string $text ): array {
		$out = array();
		$bad = array();
		foreach ( preg_split( '/\R/', $text ) ?: array() as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$parts = array_map( 'trim', explode( '|', $line, 2 ) );
			$name  = mb_substr( sanitize_text_field( $parts[0] ), 0, 40 );
			$url   = self::clean_url( $parts[1] ?? '' );
			if ( '' === $name || '' === $url ) {
				$bad[] = $line;
				continue;
			}
			$out[] = array( 'name' => $name, 'url' => $url );
		}
		return array( array_slice( $out, 0, 20 ), $bad );
	}

	/* --------------------------------------------------------------------- Espace membre */

	public static function render_member(): void {
		$list = self::all();
		?>
		<p>Arvator fonctionne chez tout courtier qui propose <strong>cTrader avec le cloud</strong>. Voici, à titre indicatif, des courtiers qui proposent cTrader et disposent d’une licence européenne. Cliquez sur un nom pour ouvrir son site officiel.</p>
		<?php if ( ! $list ) : ?>
			<p class="muted">La liste sera bientôt complétée. En attendant, écrivez-nous depuis la page <a href="<?php echo esc_url( Arvator_Support::page_url( 'contact' ) ); ?>">Contact</a>.</p>
			<?php
			return;
		endif;
		$items = static function ( bool $copy ) use ( $list ): void {
			foreach ( $list as $b ) {
				printf(
					'<li><a class="brokers__item" href="%s" target="_blank" rel="noopener noreferrer nofollow"%s>%s</a></li>',
					esc_url( $b['url'] ),
					$copy ? ' tabindex="-1"' : '',
					esc_html( $b['name'] )
				);
			}
		};
		?>
		<div class="brokers" role="region" aria-label="Courtiers compatibles">
			<div class="brokers__track">
				<ul class="brokers__list"><?php $items( false ); ?></ul>
				<ul class="brokers__list" aria-hidden="true"><?php $items( true ); ?></ul>
			</div>
		</div>
		<div class="arv-card">
			<h3>Avant d’ouvrir un compte</h3>
			<ul class="checklist">
				<li>Ce n’est ni une recommandation ni un conseil : nous ne touchons <strong>aucune commission</strong> de ces courtiers. Comparez leurs conditions vous-même.</li>
				<li>Vérifiez que le courtier est autorisé à proposer ses services en France, sur le registre officiel <a href="https://www.regafi.fr" target="_blank" rel="noopener noreferrer">REGAFI</a>, et qu’il ne figure pas sur la liste noire de l’<a href="https://www.amf-france.org" target="_blank" rel="noopener noreferrer">AMF</a>.</li>
				<li>Vérifiez que votre compte permet de faire tourner les robots (cBots) dans le cloud cTrader : faites d’abord un essai sur un compte démo.</li>
				<li>La taille minimale de position varie d’un courtier à l’autre : avec un petit capital, le robot peut refuser plus de trades.</li>
				<li>Le trading comporte un risque élevé de perte en capital, pouvant aller jusqu’à la totalité des sommes engagées.</li>
			</ul>
		</div>
		<?php
	}

	/* --------------------------------------------------------------------- Administration */

	public static function menu(): void {
		add_submenu_page( 'arvator', 'Courtiers', 'Courtiers', 'manage_options', 'arvator-courtiers', array( self::class, 'page' ) );
	}

	public static function page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Accès refusé.', '', array( 'response' => 403 ) );
		}
		$lines = implode( "\n", array_map( static fn( $b ) => $b['name'] . ' | ' . $b['url'], self::all() ) );
		echo '<div class="wrap"><h1>Courtiers compatibles</h1>';
		$flash = get_transient( 'arvator_brokers_notice_' . get_current_user_id() );
		if ( is_string( $flash ) ) {
			delete_transient( 'arvator_brokers_notice_' . get_current_user_id() );
			echo '<div class="notice notice-info"><p>' . esc_html( $flash ) . '</p></div>';
		}
		?>
		<p>Cette liste s’affiche <strong>seulement dans l’espace membre</strong> des clients ayant acheté (rubrique « Courtiers »), jamais sur les pages publiques. Une ligne par courtier : <code>Nom | https://adresse-du-site-officiel</code>. Les paramètres d’adresse (<code>?ref=</code>, <code>?affid=</code>…) sont retirés automatiquement : aucun lien d’affiliation n’est possible.</p>
		<p>Liste indicative de courtiers proposant cTrader avec une licence européenne. Avant d’en ajouter un, vérifiez son autorisation en France (REGAFI) et qu’il n’est pas sur la liste noire de l’AMF.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="arvator_brokers_save">
			<?php wp_nonce_field( 'arvator_brokers_save', '_arv_nonce' ); ?>
			<textarea name="brokers" rows="10" class="large-text code"><?php echo esc_textarea( $lines ); ?></textarea>
			<?php submit_button( 'Enregistrer' ); ?>
		</form>
		</div>
		<?php
	}

	public static function handle_save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Accès refusé.', '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'arvator_brokers_save', '_arv_nonce' );
		$text = isset( $_POST['brokers'] ) ? (string) wp_unslash( $_POST['brokers'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- nettoyé ligne par ligne dans parse()
		[ $list, $bad ] = self::parse( $text );
		update_option( self::OPT, $list, false );
		Arvator_Repo::log_event( 'brokers_saved', get_current_user_id(), array( 'count' => count( $list ) ) );
		set_transient( 'arvator_brokers_notice_' . get_current_user_id(), count( $list ) . ' courtier(s) enregistré(s).' . ( $bad ? ' Lignes ignorées (nom vide ou adresse non https) : ' . implode( ' ; ', $bad ) : '' ), 5 * MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=arvator-courtiers' ) );
		exit;
	}
}

<?php
/**
 * Espace membre : [arvator_membre].
 *
 * Sécurité :
 * - rien n'est lu ni modifié sans session valide ; l'identifiant du client vient de la session, jamais d'un champ ;
 * - chaque action (connexion, mot de passe, demandes RGPD, téléchargement) a sa clé de sécurité (nonce) ;
 * - aucune adresse de redirection n'est acceptée du visiteur ;
 * - page jamais mise en cache ; messages limités à une liste prédéfinie.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Arvator_Member {

	private const SECTIONS = array(
		'tableau'   => 'Tableau de bord',
		'achats'    => 'Mes achats et abonnement',
		'licence'   => 'Ma licence',
		'fichiers'  => 'Téléchargements',
		'courtiers' => 'Courtiers',
		'parrainage' => 'Parrainage',
		'profil'    => 'Mes informations',
	);

	public static function init(): void {
		add_shortcode( 'arvator_membre', array( self::class, 'render' ) );
		add_action( 'admin_post_nopriv_arvator_login', array( self::class, 'handle_login' ) );
		add_action( 'admin_post_arvator_login', array( self::class, 'handle_login' ) );
		add_action( 'admin_post_arvator_password', array( self::class, 'handle_password' ) );
		add_action( 'admin_post_arvator_privacy', array( self::class, 'handle_privacy' ) );
		Arvator_Support::no_cache_for( array( 'arvator_membre' ) );
	}

	/** Libellé traduit d'une rubrique (SECTIONS garde la liste des rubriques autorisées). */
	private static function section_label( string $key ): string {
		return array(
			'tableau'    => __( 'Tableau de bord', 'arvator-core' ),
			'achats'     => __( 'Mes achats et abonnement', 'arvator-core' ),
			'licence'    => __( 'Ma licence', 'arvator-core' ),
			'fichiers'   => __( 'Téléchargements', 'arvator-core' ),
			'courtiers'  => __( 'Courtiers', 'arvator-core' ),
			'parrainage' => __( 'Parrainage', 'arvator-core' ),
			'profil'     => __( 'Mes informations', 'arvator-core' ),
		)[ $key ] ?? (string) ( self::SECTIONS[ $key ] ?? '' );
	}

	public static function url( string $section = '' ): string {
		$url = Arvator_Support::page_url( 'espace-membre' );
		return ( '' !== $section && isset( self::SECTIONS[ $section ] ) && 'tableau' !== $section ) ? add_query_arg( 'section', $section, $url ) : $url;
	}

	/* --------------------------------------------------------------------- Affichage */

	public static function render(): string {
		ob_start();
		echo wp_kses_post( Arvator_Support::render_notice() );
		if ( ! is_user_logged_in() ) {
			self::render_login();
		} else {
			self::render_account();
		}
		return (string) ob_get_clean();
	}

	private static function render_login(): void {
		?>
		<div class="arv-login">
			<h2><?php esc_html_e( 'Connexion', 'arvator-core' ); ?></h2>
			<form class="arv-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="arvator_login">
				<?php wp_nonce_field( 'arvator_login', '_arv_nonce' ); ?>
				<p>
					<label for="arv-log"><?php esc_html_e( 'Adresse email', 'arvator-core' ); ?></label>
					<input id="arv-log" name="log" type="text" required autocomplete="username" maxlength="190">
				</p>
				<p>
					<label for="arv-pwd"><?php esc_html_e( 'Mot de passe', 'arvator-core' ); ?></label>
					<input id="arv-pwd" name="pwd" type="password" required autocomplete="current-password" maxlength="200">
				</p>
				<p class="arv-check"><label><input type="checkbox" name="rememberme" value="1"> <?php esc_html_e( 'Se souvenir de moi', 'arvator-core' ); ?></label></p>
				<p><button class="btn btn--primary" type="submit"><?php esc_html_e( 'Se connecter', 'arvator-core' ); ?></button></p>
				<p class="muted"><a href="<?php echo esc_url( wp_lostpassword_url( self::url() ) ); ?>"><?php esc_html_e( 'Mot de passe oublié ?', 'arvator-core' ); ?></a></p>
			</form>
			<p class="muted"><?php esc_html_e( 'Votre compte est créé automatiquement lors de votre achat : vous recevez un email pour choisir votre mot de passe.', 'arvator-core' ); ?></p>
		</div>
		<?php
	}

	private static function render_account(): void {
		$user    = wp_get_current_user();
		$user_id = (int) $user->ID;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation, aucune action
		$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( (string) $_GET['section'] ) ) : 'tableau';
		if ( ! isset( self::SECTIONS[ $section ] ) ) {
			$section = 'tableau';
		}
		?>
		<div class="member">
			<aside class="member__side">
				<p class="member__hello"><?php /* translators: %s: nom du client. */ echo wp_kses_post( sprintf( __( 'Bonjour <strong>%s</strong>', 'arvator-core' ), esc_html( $user->display_name ) ) ); ?></p>
				<nav class="member__nav" aria-label="<?php esc_attr_e( 'Espace membre', 'arvator-core' ); ?>">
					<ul>
						<?php foreach ( array_keys( self::SECTIONS ) as $key ) : ?>
							<li><a href="<?php echo esc_url( self::url( $key ) ); ?>"<?php echo $key === $section ? ' aria-current="page"' : ''; ?>><?php echo esc_html( self::section_label( $key ) ); ?></a></li>
						<?php endforeach; ?>
						<li><a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Se déconnecter', 'arvator-core' ); ?></a></li>
					</ul>
				</nav>
			</aside>
			<div class="member__main">
				<h2><?php echo esc_html( self::section_label( $section ) ); ?></h2>
				<?php
				match ( $section ) {
					'achats'   => self::section_purchases( $user_id ),
					'licence'  => self::section_license( $user_id ),
					'fichiers' => self::section_files( $user_id ),
					'courtiers' => self::section_brokers( $user_id ),
					'parrainage' => self::section_referral( $user_id ),
					'profil'   => self::section_profile( $user ),
					default    => self::section_dashboard( $user_id ),
				};
				?>
			</div>
		</div>
		<?php
	}

	private static function section_dashboard( int $user_id ): void {
		$orders = Arvator_Repo::orders_for_user( $user_id );
		$subs   = Arvator_Repo::subscriptions_for_user( $user_id );
		$lics   = Arvator_Repo::licenses_for_user( $user_id );
		$paid   = array_values( array_filter( $orders, static fn( array $o ): bool => 'paid' === $o['status'] ) );
		if ( Arvator_License::waiting( $user_id ) ) {
			/* translators: %s: adresse de la rubrique Licence. */
			echo '<p class="arv-notice arv-notice--err">' . wp_kses_post( sprintf( __( 'Votre licence attend le numéro de votre compte cTrader. <a href="%s">L’indiquer maintenant</a>', 'arvator-core' ), esc_url( self::url( 'licence' ) ) ) ) . '</p>';
		}
		?>
		<div class="arv-cards">
			<section class="arv-card">
				<h3><?php esc_html_e( 'Mon robot', 'arvator-core' ); ?></h3>
				<?php if ( $paid ) : ?>
					<p><?php /* translators: %s: date d'achat. */ echo wp_kses_post( sprintf( __( '<strong>Acheté</strong> le %s', 'arvator-core' ), esc_html( self::date( (string) $paid[0]['created_at'] ) ) ) ); ?></p>
					<p><a href="<?php echo esc_url( self::url( 'fichiers' ) ); ?>"><?php esc_html_e( 'Télécharger le robot', 'arvator-core' ); ?></a></p>
				<?php else : ?>
					<p><?php esc_html_e( 'Aucun achat pour le moment.', 'arvator-core' ); ?></p>
				<?php endif; ?>
			</section>
			<section class="arv-card">
				<h3><?php esc_html_e( 'Mon abonnement', 'arvator-core' ); ?></h3>
				<?php if ( $subs ) : ?>
					<p><strong><?php echo esc_html( self::plan_label( (string) $subs[0]['plan'] ) ); ?></strong> · <?php echo esc_html( self::status_label( 'subscription', (string) $subs[0]['status'] ) ); ?></p>
					<?php if ( ! empty( $subs[0]['current_period_end'] ) ) : ?>
						<p class="muted"><?php /* translators: %s: date. */ echo esc_html( sprintf( $subs[0]['cancel_at_period_end'] ? __( 'Fin le %s', 'arvator-core' ) : __( 'Prochaine échéance le %s', 'arvator-core' ), self::date( (string) $subs[0]['current_period_end'] ) ) ); ?></p>
					<?php endif; ?>
					<p><a href="<?php echo esc_url( self::url( 'achats' ) ); ?>"><?php esc_html_e( 'Voir le détail', 'arvator-core' ); ?></a></p>
				<?php else : ?>
					<p><?php esc_html_e( 'Aucun abonnement actif.', 'arvator-core' ); ?></p>
				<?php endif; ?>
			</section>
			<section class="arv-card">
				<h3><?php esc_html_e( 'Ma licence', 'arvator-core' ); ?></h3>
				<?php if ( $lics ) : ?>
					<p><strong><?php echo esc_html( self::status_label( 'license', (string) $lics[0]['status'] ) ); ?></strong></p>
					<p class="muted"><?php /* translators: %s: date de fin. */ echo esc_html( sprintf( __( 'Valable jusqu’au %s', 'arvator-core' ), self::date( (string) $lics[0]['expires_at'] ) ) ); ?></p>
					<p><a href="<?php echo esc_url( self::url( 'licence' ) ); ?>"><?php esc_html_e( 'Voir le détail', 'arvator-core' ); ?></a></p>
				<?php else : ?>
					<p><?php esc_html_e( 'Aucune licence pour le moment.', 'arvator-core' ); ?></p>
				<?php endif; ?>
			</section>
		</div>
		<?php
	}

	private static function section_purchases( int $user_id ): void {
		$orders = Arvator_Repo::orders_for_user( $user_id );
		$subs   = Arvator_Repo::subscriptions_for_user( $user_id );

		echo '<h3>' . esc_html__( 'Abonnement', 'arvator-core' ) . '</h3>';
		if ( ! $subs ) {
			echo '<p>' . esc_html__( 'Aucun abonnement.', 'arvator-core' ) . '</p>';
		} else {
			echo '<div class="table-wrap"><table class="arv-table"><thead><tr><th scope="col">' . esc_html__( 'Formule', 'arvator-core' ) . '</th><th scope="col">' . esc_html__( 'État', 'arvator-core' ) . '</th><th scope="col">' . esc_html__( 'Échéance', 'arvator-core' ) . '</th><th scope="col">' . esc_html__( 'Résiliation', 'arvator-core' ) . '</th></tr></thead><tbody>';
			foreach ( $subs as $s ) {
				printf(
					'<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
					esc_html( self::plan_label( (string) $s['plan'] ) ),
					esc_html( self::status_label( 'subscription', (string) $s['status'] ) ),
					esc_html( ! empty( $s['current_period_end'] ) ? self::date( (string) $s['current_period_end'] ) : '—' ),
					esc_html( $s['cancel_at_period_end'] ? __( 'Prévue à l’échéance', 'arvator-core' ) : '—' )
				);
			}
			echo '</tbody></table></div>';
			if ( '' !== Arvator_Repo::stripe_customer_for_user( $user_id ) ) {
				?>
				<form class="arv-form arv-form--inline" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="arvator_portal">
					<?php wp_nonce_field( 'arvator_portal', '_arv_nonce' ); ?>
					<button class="btn btn--primary btn--sm" type="submit"><?php esc_html_e( 'Gérer mon abonnement, mes factures et ma carte', 'arvator-core' ); ?></button>
				</form>
				<p class="muted"><?php esc_html_e( 'Vous pouvez résilier à tout moment : la résiliation prend effet à la fin de la période déjà payée, qui n’est pas remboursée.', 'arvator-core' ); ?></p>
				<?php
			}
		}

		echo '<h3>' . esc_html__( 'Achats', 'arvator-core' ) . '</h3>';
		if ( ! $orders ) {
			echo '<p>' . esc_html__( 'Aucun achat pour le moment.', 'arvator-core' ) . ' <a href="' . esc_url( Arvator_Checkout::url() ) . '">' . esc_html__( 'Acheter Arvator', 'arvator-core' ) . '</a></p>';
			return;
		}
		echo '<div class="table-wrap"><table class="arv-table"><thead><tr><th scope="col">' . esc_html__( 'Date', 'arvator-core' ) . '</th><th scope="col">' . esc_html__( 'Produit', 'arvator-core' ) . '</th><th scope="col">' . esc_html__( 'Montant', 'arvator-core' ) . '</th><th scope="col">' . esc_html__( 'État', 'arvator-core' ) . '</th></tr></thead><tbody>';
		foreach ( $orders as $o ) {
			printf(
				'<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
				esc_html( self::date( (string) $o['created_at'] ) ),
				esc_html( self::product_label( (string) $o['product'] ) ),
				esc_html( self::money( (int) $o['amount_cents'] ) ),
				esc_html( self::status_label( 'order', (string) $o['status'] ) )
			);
		}
		echo '</tbody></table></div>';
	}

	private static function section_license( int $user_id ): void {
		$lics = Arvator_Repo::licenses_for_user( $user_id );
		if ( Arvator_Repo::has_paid_order( $user_id ) ) {
			Arvator_License::render_account_form( $user_id );
		}
		if ( ! $lics ) {
			echo '<p>' . esc_html__( 'Aucune licence pour le moment. Elle apparaît ici après votre achat, dès que votre numéro de compte cTrader est connu.', 'arvator-core' ) . '</p>';
			return;
		}
		foreach ( $lics as $i => $l ) {
			$field = 'arv-key-' . (int) $i;
			?>
			<section class="arv-card">
				<h3>
					<?php echo esc_html( self::plan_label( (string) $l['plan'] ) ); ?>
					<span class="arv-badge"><?php echo esc_html( self::status_label( 'license', (string) $l['status'] ) ); ?></span>
					<?php if ( ! empty( $l['demo_only'] ) ) : ?><span class="arv-badge arv-badge--soft"><?php esc_html_e( 'Compte démo uniquement', 'arvator-core' ); ?></span><?php endif; ?>
				</h3>
				<p class="muted"><?php /* translators: %s: date de fin. */ echo esc_html( sprintf( __( 'Valable jusqu’au %s', 'arvator-core' ), self::date( (string) $l['expires_at'] ) ) ); ?>
					<?php /* translators: %s: numéro de compte cTrader. */ echo ! empty( $l['account_number'] ) ? ' · ' . esc_html( sprintf( __( 'Compte cTrader n° %s', 'arvator-core' ), (string) $l['account_number'] ) ) : ''; ?></p>
				<?php if ( '' === (string) $l['license_key'] ) : ?>
					<p><?php esc_html_e( 'Rien à copier : le robot récupère sa licence tout seul à son démarrage, puis la vérifie régulièrement. Elle est renouvelée automatiquement à chaque paiement de votre abonnement.', 'arvator-core' ); ?></p>
				<?php else : ?>
					<label for="<?php echo esc_attr( $field ); ?>"><?php esc_html_e( 'Votre clé de licence', 'arvator-core' ); ?></label>
					<textarea id="<?php echo esc_attr( $field ); ?>" class="arv-key" readonly rows="4"><?php echo esc_textarea( (string) $l['license_key'] ); ?></textarea>
					<p><button class="btn btn--ghost btn--sm" type="button" data-copy="#<?php echo esc_attr( $field ); ?>" data-copied="<?php esc_attr_e( 'Copié ✓', 'arvator-core' ); ?>"><?php esc_html_e( 'Copier la clé', 'arvator-core' ); ?></button></p>
				<?php endif; ?>
			</section>
			<?php
		}
	}

	private static function section_files( int $user_id ): void {
		if ( ! Arvator_Repo::is_entitled( $user_id ) && ! current_user_can( 'manage_options' ) ) {
			echo '<p>' . esc_html__( 'Le téléchargement du robot est disponible après votre achat.', 'arvator-core' ) . '</p>';
			return;
		}
		$files = Arvator_Downloads::files();
		if ( ! $files ) {
			echo '<p>' . esc_html__( 'Aucun fichier à télécharger pour le moment.', 'arvator-core' ) . '</p>';
			return;
		}
		echo '<ul class="arv-files">';
		foreach ( $files as $id => $f ) {
			printf(
				'<li><span><strong>%s</strong> <span class="muted">(%s)</span></span> <a class="btn btn--primary btn--sm" href="%s">' . esc_html__( 'Télécharger', 'arvator-core' ) . '</a></li>',
				esc_html( (string) $f['label'] ),
				esc_html( size_format( (int) $f['size'] ) ),
				esc_url( Arvator_Downloads::link( (string) $id ) )
			);
		}
		echo '</ul>';
	}

	/** Courtiers compatibles : réservé aux clients ayant acheté (jamais public, décision de Hamid). */
	private static function section_brokers( int $user_id ): void {
		if ( ! Arvator_Repo::is_entitled( $user_id ) && ! current_user_can( 'manage_options' ) ) {
			echo '<p>' . esc_html__( 'La liste des courtiers compatibles est disponible après votre achat.', 'arvator-core' ) . '</p>';
			return;
		}
		Arvator_Brokers::render_member();
	}

	private static function section_referral( int $user_id ): void {
		$ref = Arvator_Referral::referrer( $user_id );
		if ( ! $ref ) {
			$s = Arvator_Referral::settings();
			/* translators: 1: montant de la récompense, 2: pourcentage sur les abonnements. */
			echo '<p>' . wp_kses_post( sprintf( __( 'Recommandez Arvator à vos proches : vous recevez une <strong>récompense de recommandation de %1$s</strong> pour chaque achat du robot fait avec votre lien, puis %2$s de chaque abonnement qu’ils paient. Gratuit, sans engagement.', 'arvator-core' ), esc_html( Arvator_Referral::money( $s['prime_cents'] ) ), esc_html( Arvator_Referral::pct( $s['rate'] ) ) ) ) . '</p>';
			Arvator_Referral::join_form();
			return;
		}
		Arvator_Referral::promote_due();
		$link  = Arvator_Referral::link( (string) $ref['code'] );
		$comms = Arvator_Referral::commissions_for( $user_id );
		$refs  = Arvator_Referral::referrals_of( $user_id );
		$sum   = array( 'pending' => 0, 'payable' => 0, 'paid' => 0 );
		foreach ( $comms as $c ) {
			if ( isset( $sum[ $c['status'] ] ) ) {
				$sum[ $c['status'] ] += (int) $c['amount_cents'];
			}
		}
		?>
		<section class="arv-card">
			<h3><?php esc_html_e( 'Mon lien de recommandation', 'arvator-core' ); ?></h3>
			<?php if ( 'active' !== $ref['status'] ) : ?>
				<p class="arv-notice arv-notice--err"><?php /* translators: %s: adresse de la page Contact. */ echo wp_kses_post( sprintf( __( 'Votre lien est suspendu. Écrivez-nous depuis la page <a href="%s">Contact</a>.', 'arvator-core' ), esc_url( Arvator_Support::page_url( 'contact' ) ) ) ); ?></p>
			<?php endif; ?>
			<label for="arv-ref-link"><?php esc_html_e( 'Partagez ce lien', 'arvator-core' ); ?></label>
			<input id="arv-ref-link" class="arv-key" type="text" readonly value="<?php echo esc_attr( $link ); ?>">
			<p><button class="btn btn--ghost btn--sm" type="button" data-copy="#arv-ref-link" data-copied="<?php esc_attr_e( 'Copié ✓', 'arvator-core' ); ?>"><?php esc_html_e( 'Copier le lien', 'arvator-core' ); ?></button>
				<span class="muted"><?php /* translators: %s: code de recommandation. */ echo wp_kses_post( sprintf( __( 'Votre code : <strong>%s</strong>', 'arvator-core' ), esc_html( (string) $ref['code'] ) ) ); ?></span></p>
			<p class="muted"><?php esc_html_e( 'Ce lien mène à la page d’achat, où votre code est déjà rempli.', 'arvator-core' ); ?></p>
			<p class="muted"><?php esc_html_e( 'Recommandez Arvator honnêtement : jamais de promesse de gain. Les résultats du site sont des performances simulées et le trading comporte un risque élevé de perte en capital.', 'arvator-core' ); ?></p>
		</section>

		<div class="arv-cards">
			<section class="arv-card"><h3><?php esc_html_e( 'En attente', 'arvator-core' ); ?></h3><p><strong><?php echo esc_html( Arvator_Referral::money( $sum['pending'] ) ); ?></strong></p><p class="muted"><?php /* translators: %d: délai en jours. */ echo esc_html( sprintf( __( 'Disponible %d jours après chaque paiement.', 'arvator-core' ), (int) Arvator_Referral::settings()['delay_days'] ) ); ?></p></section>
			<section class="arv-card"><h3><?php esc_html_e( 'À payer', 'arvator-core' ); ?></h3><p><strong><?php echo esc_html( Arvator_Referral::money( $sum['payable'] ) ); ?></strong></p><p class="muted"><?php /* translators: %s: montant minimum de virement. */ echo esc_html( sprintf( __( 'Virement de la prime dès qu’elle est disponible ; abonnements à partir de %s cumulés.', 'arvator-core' ), Arvator_Referral::money( Arvator_Referral::settings()['payout_min_cents'] ) ) ); ?></p></section>
			<section class="arv-card"><h3><?php esc_html_e( 'Déjà versé', 'arvator-core' ); ?></h3><p><strong><?php echo esc_html( Arvator_Referral::money( $sum['paid'] ) ); ?></strong></p><p class="muted"><?php /* translators: %d: nombre de personnes recommandées ayant acheté. */ echo esc_html( sprintf( __( '%d personne(s) recommandée(s) ayant acheté.', 'arvator-core' ), count( $refs ) ) ); ?></p></section>
		</div>

		<h3><?php esc_html_e( 'Mes récompenses de recommandation', 'arvator-core' ); ?></h3>
		<?php
		if ( ! $comms ) {
			echo '<p>' . esc_html__( 'Aucune récompense pour le moment.', 'arvator-core' ) . '</p>';
		} else {
			echo '<div class="table-wrap"><table class="arv-table"><thead><tr><th scope="col">' . esc_html__( 'Date', 'arvator-core' ) . '</th><th scope="col">' . esc_html__( 'Type', 'arvator-core' ) . '</th><th scope="col">' . esc_html__( 'Montant', 'arvator-core' ) . '</th><th scope="col">' . esc_html__( 'État', 'arvator-core' ) . '</th><th scope="col">' . esc_html__( 'Disponible le', 'arvator-core' ) . '</th></tr></thead><tbody>';
			foreach ( $comms as $c ) {
				printf(
					'<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
					esc_html( self::date( (string) $c['created_at'] ) ),
					esc_html( Arvator_Referral::kind_label( (string) $c['kind'] ) ),
					esc_html( Arvator_Referral::money( (int) $c['amount_cents'] ) ),
					esc_html( Arvator_Referral::status_label( (string) $c['status'] ) ),
					esc_html( /* translators: %s: date du virement. */ 'paid' === $c['status'] && ! empty( $c['paid_at'] ) ? sprintf( __( 'Versée le %s', 'arvator-core' ), self::date( (string) $c['paid_at'] ) ) : self::date( (string) $c['available_at'] ) )
				);
			}
			echo '</tbody></table></div>';
		}
		$has_bank = '' !== (string) ( $ref['iban_last4'] ?? '' );
		?>
		<section class="arv-card">
			<h3><?php esc_html_e( 'Coordonnées pour le virement', 'arvator-core' ); ?></h3>
			<?php if ( $has_bank ) : ?>
				<p><?php /* translators: %s: titulaire du compte. */ echo wp_kses_post( sprintf( __( 'Virement à <strong>%s</strong>', 'arvator-core' ), esc_html( (string) $ref['holder_name'] ) ) ); ?> · IBAN <?php echo esc_html( substr( (string) $ref['iban_last4'], 0, 4 ) . ' •••• •••• ' . substr( (string) $ref['iban_last4'], -4 ) ); ?> · <?php echo esc_html( 'entreprise' === $ref['entity_type'] ? __( 'Entreprise', 'arvator-core' ) : __( 'Particulier', 'arvator-core' ) ); ?></p>
				<p class="muted"><?php esc_html_e( 'Pour changer de compte, saisissez les nouvelles coordonnées ci-dessous.', 'arvator-core' ); ?></p>
			<?php else : ?>
				<p><?php esc_html_e( 'Indiquez où verser vos récompenses. Sans IBAN, elles restent en attente de virement.', 'arvator-core' ); ?></p>
			<?php endif; ?>
			<form class="arv-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" autocomplete="off">
				<input type="hidden" name="action" value="arvator_referral_bank">
				<?php wp_nonce_field( 'arvator_referral_bank', '_arv_nonce' ); ?>
				<p><label for="arv-holder"><?php esc_html_e( 'Nom du titulaire du compte', 'arvator-core' ); ?></label><input id="arv-holder" name="holder" type="text" required maxlength="120"></p>
				<p><label for="arv-iban">IBAN</label><input id="arv-iban" name="iban" type="text" required maxlength="42" inputmode="text" spellcheck="false"></p>
				<fieldset class="arv-plans">
					<legend><?php esc_html_e( 'Vous êtes', 'arvator-core' ); ?></legend>
					<label class="arv-plan"><input type="radio" name="entity" value="particulier" checked> <span><?php esc_html_e( 'Un particulier', 'arvator-core' ); ?></span></label>
					<label class="arv-plan"><input type="radio" name="entity" value="entreprise"> <span><?php esc_html_e( 'Une entreprise (SIRET obligatoire)', 'arvator-core' ); ?></span></label>
				</fieldset>
				<p><label for="arv-siret">SIRET <span class="muted"><?php esc_html_e( '(entreprise uniquement)', 'arvator-core' ); ?></span></label><input id="arv-siret" name="siret" type="text" inputmode="numeric" maxlength="17"></p>
				<p><button class="btn btn--primary" type="submit"><?php esc_html_e( 'Enregistrer', 'arvator-core' ); ?></button></p>
				<p class="muted"><?php esc_html_e( 'Votre IBAN est enregistré chiffré et ne sert qu’au virement de vos récompenses. Selon votre situation, ces récompenses peuvent être à déclarer aux impôts.', 'arvator-core' ); ?></p>
			</form>
		</section>
		<p class="muted"><a href="<?php echo esc_url( Arvator_Support::page_url( 'conditions-parrainage' ) ); ?>"><?php esc_html_e( 'Conditions du programme de parrainage', 'arvator-core' ); ?></a></p>
		<?php
	}

	private static function section_profile( WP_User $user ): void {
		?>
		<section class="arv-card">
			<h3><?php esc_html_e( 'Mon compte', 'arvator-core' ); ?></h3>
			<p><?php /* translators: %s: adresse email du client. */ echo wp_kses_post( sprintf( __( 'Adresse email : <strong>%s</strong>', 'arvator-core' ), esc_html( $user->user_email ) ) ); ?></p>
			<p class="muted"><?php /* translators: %s: adresse de la page Contact. */ echo wp_kses_post( sprintf( __( 'Pour changer votre adresse email, écrivez-nous depuis la page <a href="%s">Contact</a>.', 'arvator-core' ), esc_url( Arvator_Support::page_url( 'contact' ) ) ) ); ?></p>
		</section>

		<section class="arv-card">
			<h3><?php esc_html_e( 'Changer mon mot de passe', 'arvator-core' ); ?></h3>
			<form class="arv-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="arvator_password">
				<?php wp_nonce_field( 'arvator_password', '_arv_nonce' ); ?>
				<p><label for="arv-cur"><?php esc_html_e( 'Mot de passe actuel', 'arvator-core' ); ?></label><input id="arv-cur" name="current" type="password" required autocomplete="current-password" maxlength="200"></p>
				<p><label for="arv-new"><?php esc_html_e( 'Nouveau mot de passe (12 caractères minimum)', 'arvator-core' ); ?></label><input id="arv-new" name="new" type="password" required minlength="12" autocomplete="new-password" maxlength="200"></p>
				<p><label for="arv-new2"><?php esc_html_e( 'Répéter le nouveau mot de passe', 'arvator-core' ); ?></label><input id="arv-new2" name="new2" type="password" required minlength="12" autocomplete="new-password" maxlength="200"></p>
				<p><button class="btn btn--primary" type="submit"><?php esc_html_e( 'Enregistrer', 'arvator-core' ); ?></button></p>
			</form>
		</section>

		<section class="arv-card">
			<h3><?php esc_html_e( 'Mes données', 'arvator-core' ); ?></h3>
			<p class="muted"><?php esc_html_e( 'Vous pouvez demander une copie de vos données ou leur effacement. Un email de confirmation vous est envoyé. Les pièces comptables sont conservées 10 ans, comme la loi l’exige.', 'arvator-core' ); ?></p>
			<form class="arv-form arv-form--inline" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="arvator_privacy">
				<?php wp_nonce_field( 'arvator_privacy', '_arv_nonce' ); ?>
				<button class="btn btn--ghost btn--sm" type="submit" name="kind" value="export"><?php esc_html_e( 'Recevoir une copie de mes données', 'arvator-core' ); ?></button>
				<button class="btn btn--ghost btn--sm" type="submit" name="kind" value="erase"><?php esc_html_e( 'Demander l’effacement de mes données', 'arvator-core' ); ?></button>
			</form>
		</section>
		<?php
	}

	/* --------------------------------------------------------------------- Actions */

	public static function handle_login(): void {
		$back = self::url();
		if ( is_user_logged_in() ) {
			wp_safe_redirect( $back );
			exit;
		}
		$nonce = isset( $_POST['_arv_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_arv_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'arvator_login' ) ) {
			Arvator_Support::redirect( $back, 'login_invalid' );
		}

		$login = isset( $_POST['log'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['log'] ) ) : '';
		$pass  = isset( $_POST['pwd'] ) ? wp_unslash( (string) $_POST['pwd'] ) : ''; // le mot de passe ne doit JAMAIS être « nettoyé ».
		if ( '' === $login || '' === $pass ) {
			Arvator_Support::redirect( $back, 'login_failed' );
		}

		$user = wp_signon(
			array(
				'user_login'    => $login,
				'user_password' => $pass,
				'remember'      => ! empty( $_POST['rememberme'] ),
			),
			is_ssl()
		);

		if ( is_wp_error( $user ) ) {
			Arvator_Support::redirect( $back, 'arvator_locked' === $user->get_error_code() ? 'login_locked' : 'login_failed' );
		}

		wp_safe_redirect( $back );
		exit;
	}

	public static function handle_password(): void {
		$back = self::url( 'profil' );
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( self::url() );
			exit;
		}
		$nonce = isset( $_POST['_arv_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_arv_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'arvator_password' ) ) {
			Arvator_Support::redirect( $back, 'generic_invalid' );
		}

		$user    = wp_get_current_user();
		$lockkey = 'arv_pw_' . (int) $user->ID;
		if ( (int) get_transient( $lockkey ) >= 5 ) {
			Arvator_Support::redirect( $back, 'pwd_locked' );
		}

		$current = isset( $_POST['current'] ) ? wp_unslash( (string) $_POST['current'] ) : '';
		$new     = isset( $_POST['new'] ) ? wp_unslash( (string) $_POST['new'] ) : '';
		$new2    = isset( $_POST['new2'] ) ? wp_unslash( (string) $_POST['new2'] ) : '';

		if ( ! wp_check_password( $current, $user->user_pass, $user->ID ) ) {
			set_transient( $lockkey, (int) get_transient( $lockkey ) + 1, 15 * MINUTE_IN_SECONDS );
			Arvator_Support::redirect( $back, 'pwd_wrong' );
		}
		if ( $new !== $new2 ) {
			Arvator_Support::redirect( $back, 'pwd_mismatch' );
		}
		if ( strlen( $new ) < 12 || $new === $current ) {
			Arvator_Support::redirect( $back, 'pwd_weak' );
		}

		wp_update_user(
			array(
				'ID'        => $user->ID,
				'user_pass' => $new,
			)
		);
		delete_transient( $lockkey );
		// Le changement de mot de passe ferme les autres sessions : on rouvre celle-ci.
		wp_set_auth_cookie( $user->ID, false, is_ssl() );
		Arvator_Repo::log_event( 'password_changed', (int) $user->ID );

		Arvator_Support::redirect( $back, 'pwd_ok' );
	}

	public static function handle_privacy(): void {
		$back = self::url( 'profil' );
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( self::url() );
			exit;
		}
		$nonce = isset( $_POST['_arv_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_arv_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'arvator_privacy' ) ) {
			Arvator_Support::redirect( $back, 'generic_invalid' );
		}

		$kind   = isset( $_POST['kind'] ) ? sanitize_key( wp_unslash( (string) $_POST['kind'] ) ) : '';
		$action = array(
			'export' => 'export_personal_data',
			'erase'  => 'remove_personal_data',
		)[ $kind ] ?? '';
		if ( '' === $action ) {
			Arvator_Support::redirect( $back, 'generic_invalid' );
		}

		$user = wp_get_current_user();
		$meta = 'arv_privacy_' . $kind . '_at';
		$last = (int) get_user_meta( $user->ID, $meta, true );
		if ( $last > time() - DAY_IN_SECONDS ) {
			Arvator_Support::redirect( $back, 'privacy_limit' );
		}

		// Toujours pour l'adresse du compte connecté, jamais pour une adresse saisie.
		$request_id = wp_create_user_request( $user->user_email, $action );
		if ( is_wp_error( $request_id ) ) {
			Arvator_Support::redirect( $back, 'duplicate_request' === $request_id->get_error_code() ? 'privacy_pending' : 'privacy_fail' );
		}
		$sent = wp_send_user_request( (int) $request_id );
		if ( is_wp_error( $sent ) ) {
			Arvator_Support::redirect( $back, 'privacy_fail' );
		}
		update_user_meta( $user->ID, $meta, time() );
		Arvator_Repo::log_event( 'privacy_request', (int) $user->ID, array( 'kind' => $kind ) );

		Arvator_Support::redirect( $back, 'export' === $kind ? 'privacy_export' : 'privacy_erase' );
	}

	/* --------------------------------------------------------------------- Mise en forme */

	/** Date UTC de la base → date locale lisible. */
	private static function date( string $utc ): string {
		$ts = strtotime( $utc . ' UTC' );
		return false === $ts ? '—' : wp_date( 'j F Y', $ts );
	}

	private static function money( int $cents ): string {
		$euros = $cents / 100;
		return number_format( $euros, ( 0 === $cents % 100 ) ? 0 : 2, ',', "\u{202F}" ) . "\u{00A0}€";
	}

	private static function plan_label( string $plan ): string {
		return array(
			'monthly' => __( 'Abonnement mensuel', 'arvator-core' ),
			'annual'  => __( 'Abonnement annuel', 'arvator-core' ),
			'trial'   => __( 'Essai gratuit', 'arvator-core' ),
		)[ $plan ] ?? __( 'Abonnement', 'arvator-core' );
	}

	private static function product_label( string $product ): string {
		return 'cbot' === $product ? __( 'Robot Arvator (achat)', 'arvator-core' ) : __( 'Produit', 'arvator-core' );
	}

	private static function status_label( string $kind, string $status ): string {
		$labels = array(
			'order'        => array(
				'paid'     => __( 'Payé', 'arvator-core' ),
				'refunded' => __( 'Remboursé', 'arvator-core' ),
			),
			'subscription' => array(
				'active'   => __( 'Actif', 'arvator-core' ),
				'trialing' => __( 'Essai', 'arvator-core' ),
				'past_due' => __( 'Paiement en retard', 'arvator-core' ),
				'canceled' => __( 'Résilié', 'arvator-core' ),
			),
			'license'      => array(
				'active'  => __( 'Active', 'arvator-core' ),
				'expired' => __( 'Expirée', 'arvator-core' ),
				'revoked'  => __( 'Révoquée', 'arvator-core' ),
				'canceled' => __( 'Résiliée', 'arvator-core' ),
			),
		);
		return $labels[ $kind ][ $status ] ?? ucfirst( $status );
	}
}

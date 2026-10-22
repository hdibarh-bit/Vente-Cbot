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
			<h2>Connexion</h2>
			<form class="arv-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="arvator_login">
				<?php wp_nonce_field( 'arvator_login', '_arv_nonce' ); ?>
				<p>
					<label for="arv-log">Adresse email</label>
					<input id="arv-log" name="log" type="text" required autocomplete="username" maxlength="190">
				</p>
				<p>
					<label for="arv-pwd">Mot de passe</label>
					<input id="arv-pwd" name="pwd" type="password" required autocomplete="current-password" maxlength="200">
				</p>
				<p class="arv-check"><label><input type="checkbox" name="rememberme" value="1"> Se souvenir de moi</label></p>
				<p><button class="btn btn--primary" type="submit">Se connecter</button></p>
				<p class="muted"><a href="<?php echo esc_url( wp_lostpassword_url( self::url() ) ); ?>">Mot de passe oublié ?</a></p>
			</form>
			<p class="muted">Votre compte est créé automatiquement lors de votre achat : vous recevez un email pour choisir votre mot de passe.</p>
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
				<p class="member__hello">Bonjour <strong><?php echo esc_html( $user->display_name ); ?></strong></p>
				<nav class="member__nav" aria-label="Espace membre">
					<ul>
						<?php foreach ( self::SECTIONS as $key => $label ) : ?>
							<li><a href="<?php echo esc_url( self::url( $key ) ); ?>"<?php echo $key === $section ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a></li>
						<?php endforeach; ?>
						<li><a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">Se déconnecter</a></li>
					</ul>
				</nav>
			</aside>
			<div class="member__main">
				<h2><?php echo esc_html( self::SECTIONS[ $section ] ); ?></h2>
				<?php
				match ( $section ) {
					'achats'   => self::section_purchases( $user_id ),
					'licence'  => self::section_license( $user_id ),
					'fichiers' => self::section_files( $user_id ),
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
			echo '<p class="arv-notice arv-notice--err">Votre licence attend le numéro de votre compte cTrader. <a href="' . esc_url( self::url( 'licence' ) ) . '">L’indiquer maintenant</a></p>';
		}
		?>
		<div class="arv-cards">
			<section class="arv-card">
				<h3>Mon robot</h3>
				<?php if ( $paid ) : ?>
					<p><strong>Acheté</strong> le <?php echo esc_html( self::date( (string) $paid[0]['created_at'] ) ); ?></p>
					<p><a href="<?php echo esc_url( self::url( 'fichiers' ) ); ?>">Télécharger le robot</a></p>
				<?php else : ?>
					<p>Aucun achat pour le moment.</p>
				<?php endif; ?>
			</section>
			<section class="arv-card">
				<h3>Mon abonnement</h3>
				<?php if ( $subs ) : ?>
					<p><strong><?php echo esc_html( self::plan_label( (string) $subs[0]['plan'] ) ); ?></strong> · <?php echo esc_html( self::status_label( 'subscription', (string) $subs[0]['status'] ) ); ?></p>
					<?php if ( ! empty( $subs[0]['current_period_end'] ) ) : ?>
						<p class="muted"><?php echo esc_html( $subs[0]['cancel_at_period_end'] ? 'Fin le ' : 'Prochaine échéance le ' ); ?><?php echo esc_html( self::date( (string) $subs[0]['current_period_end'] ) ); ?></p>
					<?php endif; ?>
					<p><a href="<?php echo esc_url( self::url( 'achats' ) ); ?>">Voir le détail</a></p>
				<?php else : ?>
					<p>Aucun abonnement actif.</p>
				<?php endif; ?>
			</section>
			<section class="arv-card">
				<h3>Ma licence</h3>
				<?php if ( $lics ) : ?>
					<p><strong><?php echo esc_html( self::status_label( 'license', (string) $lics[0]['status'] ) ); ?></strong></p>
					<p class="muted">Valable jusqu’au <?php echo esc_html( self::date( (string) $lics[0]['expires_at'] ) ); ?></p>
					<p><a href="<?php echo esc_url( self::url( 'licence' ) ); ?>">Voir le détail</a></p>
				<?php else : ?>
					<p>Aucune licence pour le moment.</p>
				<?php endif; ?>
			</section>
		</div>
		<?php
	}

	private static function section_purchases( int $user_id ): void {
		$orders = Arvator_Repo::orders_for_user( $user_id );
		$subs   = Arvator_Repo::subscriptions_for_user( $user_id );

		echo '<h3>Abonnement</h3>';
		if ( ! $subs ) {
			echo '<p>Aucun abonnement.</p>';
		} else {
			echo '<div class="table-wrap"><table class="arv-table"><thead><tr><th scope="col">Formule</th><th scope="col">État</th><th scope="col">Échéance</th><th scope="col">Résiliation</th></tr></thead><tbody>';
			foreach ( $subs as $s ) {
				printf(
					'<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
					esc_html( self::plan_label( (string) $s['plan'] ) ),
					esc_html( self::status_label( 'subscription', (string) $s['status'] ) ),
					esc_html( ! empty( $s['current_period_end'] ) ? self::date( (string) $s['current_period_end'] ) : '—' ),
					esc_html( $s['cancel_at_period_end'] ? 'Prévue à l’échéance' : '—' )
				);
			}
			echo '</tbody></table></div>';
			if ( '' !== Arvator_Repo::stripe_customer_for_user( $user_id ) ) {
				?>
				<form class="arv-form arv-form--inline" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="arvator_portal">
					<?php wp_nonce_field( 'arvator_portal', '_arv_nonce' ); ?>
					<button class="btn btn--primary btn--sm" type="submit">Gérer mon abonnement, mes factures et ma carte</button>
				</form>
				<p class="muted">Vous pouvez résilier à tout moment : la résiliation prend effet à la fin de la période déjà payée, qui n’est pas remboursée.</p>
				<?php
			}
		}

		echo '<h3>Achats</h3>';
		if ( ! $orders ) {
			echo '<p>Aucun achat pour le moment. <a href="' . esc_url( Arvator_Checkout::url() ) . '">Acheter Arvator</a></p>';
			return;
		}
		echo '<div class="table-wrap"><table class="arv-table"><thead><tr><th scope="col">Date</th><th scope="col">Produit</th><th scope="col">Montant</th><th scope="col">État</th></tr></thead><tbody>';
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
			echo '<p>Aucune licence pour le moment. Elle apparaît ici après votre achat, dès que votre numéro de compte cTrader est connu.</p>';
			return;
		}
		foreach ( $lics as $i => $l ) {
			$field = 'arv-key-' . (int) $i;
			?>
			<section class="arv-card">
				<h3>
					<?php echo esc_html( self::plan_label( (string) $l['plan'] ) ); ?>
					<span class="arv-badge"><?php echo esc_html( self::status_label( 'license', (string) $l['status'] ) ); ?></span>
					<?php if ( ! empty( $l['demo_only'] ) ) : ?><span class="arv-badge arv-badge--soft">Compte démo uniquement</span><?php endif; ?>
				</h3>
				<p class="muted">Valable jusqu’au <?php echo esc_html( self::date( (string) $l['expires_at'] ) ); ?>
					<?php echo ! empty( $l['account_number'] ) ? ' · Compte cTrader n° ' . esc_html( (string) $l['account_number'] ) : ''; ?></p>
				<?php if ( '' === (string) $l['license_key'] ) : ?>
					<p>Rien à copier : le robot récupère sa licence tout seul à son démarrage, puis la vérifie régulièrement. Elle est renouvelée automatiquement à chaque paiement de votre abonnement.</p>
				<?php else : ?>
					<label for="<?php echo esc_attr( $field ); ?>">Votre clé de licence</label>
					<textarea id="<?php echo esc_attr( $field ); ?>" class="arv-key" readonly rows="4"><?php echo esc_textarea( (string) $l['license_key'] ); ?></textarea>
					<p><button class="btn btn--ghost btn--sm" type="button" data-copy="#<?php echo esc_attr( $field ); ?>">Copier la clé</button></p>
				<?php endif; ?>
			</section>
			<?php
		}
	}

	private static function section_files( int $user_id ): void {
		if ( ! Arvator_Repo::is_entitled( $user_id ) && ! current_user_can( 'manage_options' ) ) {
			echo '<p>Le téléchargement du robot est disponible après votre achat.</p>';
			return;
		}
		$files = Arvator_Downloads::files();
		if ( ! $files ) {
			echo '<p>Aucun fichier à télécharger pour le moment.</p>';
			return;
		}
		echo '<ul class="arv-files">';
		foreach ( $files as $id => $f ) {
			printf(
				'<li><span><strong>%s</strong> <span class="muted">(%s)</span></span> <a class="btn btn--primary btn--sm" href="%s">Télécharger</a></li>',
				esc_html( (string) $f['label'] ),
				esc_html( size_format( (int) $f['size'] ) ),
				esc_url( Arvator_Downloads::link( (string) $id ) )
			);
		}
		echo '</ul>';
	}

	private static function section_referral( int $user_id ): void {
		$ref = Arvator_Referral::referrer( $user_id );
		if ( ! $ref ) {
			$s = Arvator_Referral::settings();
			echo '<p>Recommandez Arvator à vos proches : vous recevez une <strong>récompense de recommandation de ' . esc_html( Arvator_Referral::money( $s['prime_cents'] ) ) . '</strong> pour chaque achat du robot fait avec votre lien, puis ' . esc_html( Arvator_Referral::pct( $s['rate'] ) ) . ' de chaque abonnement qu’ils paient. Gratuit, sans engagement.</p>';
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
			<h3>Mon lien de recommandation</h3>
			<?php if ( 'active' !== $ref['status'] ) : ?>
				<p class="arv-notice arv-notice--err">Votre lien est suspendu. Écrivez-nous depuis la page <a href="<?php echo esc_url( Arvator_Support::page_url( 'contact' ) ); ?>">Contact</a>.</p>
			<?php endif; ?>
			<label for="arv-ref-link">Lien vers le site (pour faire découvrir Arvator)</label>
			<input id="arv-ref-link" class="arv-key" type="text" readonly value="<?php echo esc_attr( $link ); ?>">
			<p><button class="btn btn--ghost btn--sm" type="button" data-copy="#arv-ref-link">Copier le lien</button></p>
			<label for="arv-ref-buy">Lien direct vers la page d’achat (pour une personne prête à acheter)</label>
			<input id="arv-ref-buy" class="arv-key" type="text" readonly value="<?php echo esc_attr( Arvator_Referral::buy_link( (string) $ref['code'] ) ); ?>">
			<p><button class="btn btn--ghost btn--sm" type="button" data-copy="#arv-ref-buy">Copier le lien</button>
				<span class="muted">Votre code : <strong><?php echo esc_html( (string) $ref['code'] ); ?></strong></span></p>
			<p class="muted">Conseil : le lien vers la page d’achat est le plus sûr. Votre code y est toujours prérempli, même si la personne refuse les cookies. Avec le lien vers le site, il n’est retenu que si elle accepte le cookie de recommandation ; sinon, elle doit saisir votre code au moment de l’achat.</p>
			<p class="muted">Recommandez Arvator honnêtement : jamais de promesse de gain. Les résultats du site sont des performances simulées et le trading comporte un risque élevé de perte en capital.</p>
		</section>

		<div class="arv-cards">
			<section class="arv-card"><h3>En attente</h3><p><strong><?php echo esc_html( Arvator_Referral::money( $sum['pending'] ) ); ?></strong></p><p class="muted">Disponible <?php echo esc_html( (string) Arvator_Referral::settings()['delay_days'] ); ?> jours après chaque paiement.</p></section>
			<section class="arv-card"><h3>À payer</h3><p><strong><?php echo esc_html( Arvator_Referral::money( $sum['payable'] ) ); ?></strong></p><p class="muted">Virement de la prime dès qu’elle est disponible ; abonnements à partir de <?php echo esc_html( Arvator_Referral::money( Arvator_Referral::settings()['payout_min_cents'] ) ); ?> cumulés.</p></section>
			<section class="arv-card"><h3>Déjà versé</h3><p><strong><?php echo esc_html( Arvator_Referral::money( $sum['paid'] ) ); ?></strong></p><p class="muted"><?php echo esc_html( (string) count( $refs ) ); ?> personne(s) recommandée(s) ayant acheté.</p></section>
		</div>

		<h3>Mes récompenses de recommandation</h3>
		<?php
		if ( ! $comms ) {
			echo '<p>Aucune récompense pour le moment.</p>';
		} else {
			echo '<div class="table-wrap"><table class="arv-table"><thead><tr><th scope="col">Date</th><th scope="col">Type</th><th scope="col">Montant</th><th scope="col">État</th><th scope="col">Disponible le</th></tr></thead><tbody>';
			foreach ( $comms as $c ) {
				printf(
					'<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
					esc_html( self::date( (string) $c['created_at'] ) ),
					esc_html( Arvator_Referral::kind_label( (string) $c['kind'] ) ),
					esc_html( Arvator_Referral::money( (int) $c['amount_cents'] ) ),
					esc_html( Arvator_Referral::status_label( (string) $c['status'] ) ),
					esc_html( 'paid' === $c['status'] && ! empty( $c['paid_at'] ) ? 'Versée le ' . self::date( (string) $c['paid_at'] ) : self::date( (string) $c['available_at'] ) )
				);
			}
			echo '</tbody></table></div>';
		}
		$has_bank = '' !== (string) ( $ref['iban_last4'] ?? '' );
		?>
		<section class="arv-card">
			<h3>Coordonnées pour le virement</h3>
			<?php if ( $has_bank ) : ?>
				<p>Virement à <strong><?php echo esc_html( (string) $ref['holder_name'] ); ?></strong> · IBAN <?php echo esc_html( substr( (string) $ref['iban_last4'], 0, 4 ) . ' •••• •••• ' . substr( (string) $ref['iban_last4'], -4 ) ); ?> · <?php echo esc_html( 'entreprise' === $ref['entity_type'] ? 'Entreprise' : 'Particulier' ); ?></p>
				<p class="muted">Pour changer de compte, saisissez les nouvelles coordonnées ci-dessous.</p>
			<?php else : ?>
				<p>Indiquez où verser vos récompenses. Sans IBAN, elles restent en attente de virement.</p>
			<?php endif; ?>
			<form class="arv-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" autocomplete="off">
				<input type="hidden" name="action" value="arvator_referral_bank">
				<?php wp_nonce_field( 'arvator_referral_bank', '_arv_nonce' ); ?>
				<p><label for="arv-holder">Nom du titulaire du compte</label><input id="arv-holder" name="holder" type="text" required maxlength="120"></p>
				<p><label for="arv-iban">IBAN</label><input id="arv-iban" name="iban" type="text" required maxlength="42" inputmode="text" spellcheck="false"></p>
				<fieldset class="arv-plans">
					<legend>Vous êtes</legend>
					<label class="arv-plan"><input type="radio" name="entity" value="particulier" checked> <span>Un particulier</span></label>
					<label class="arv-plan"><input type="radio" name="entity" value="entreprise"> <span>Une entreprise (SIRET obligatoire)</span></label>
				</fieldset>
				<p><label for="arv-siret">SIRET <span class="muted">(entreprise uniquement)</span></label><input id="arv-siret" name="siret" type="text" inputmode="numeric" maxlength="17"></p>
				<p><button class="btn btn--primary" type="submit">Enregistrer</button></p>
				<p class="muted">Votre IBAN est enregistré chiffré et ne sert qu’au virement de vos récompenses. Selon votre situation, ces récompenses peuvent être à déclarer aux impôts.</p>
			</form>
		</section>
		<p class="muted"><a href="<?php echo esc_url( Arvator_Support::page_url( 'conditions-parrainage' ) ); ?>">Conditions du programme de parrainage</a></p>
		<?php
	}

	private static function section_profile( WP_User $user ): void {
		?>
		<section class="arv-card">
			<h3>Mon compte</h3>
			<p>Adresse email : <strong><?php echo esc_html( $user->user_email ); ?></strong></p>
			<p class="muted">Pour changer votre adresse email, écrivez-nous depuis la page <a href="<?php echo esc_url( Arvator_Support::page_url( 'contact' ) ); ?>">Contact</a>.</p>
		</section>

		<section class="arv-card">
			<h3>Changer mon mot de passe</h3>
			<form class="arv-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="arvator_password">
				<?php wp_nonce_field( 'arvator_password', '_arv_nonce' ); ?>
				<p><label for="arv-cur">Mot de passe actuel</label><input id="arv-cur" name="current" type="password" required autocomplete="current-password" maxlength="200"></p>
				<p><label for="arv-new">Nouveau mot de passe (12 caractères minimum)</label><input id="arv-new" name="new" type="password" required minlength="12" autocomplete="new-password" maxlength="200"></p>
				<p><label for="arv-new2">Répéter le nouveau mot de passe</label><input id="arv-new2" name="new2" type="password" required minlength="12" autocomplete="new-password" maxlength="200"></p>
				<p><button class="btn btn--primary" type="submit">Enregistrer</button></p>
			</form>
		</section>

		<section class="arv-card">
			<h3>Mes données</h3>
			<p class="muted">Vous pouvez demander une copie de vos données ou leur effacement. Un email de confirmation vous est envoyé. Les pièces comptables sont conservées 10 ans, comme la loi l’exige.</p>
			<form class="arv-form arv-form--inline" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="arvator_privacy">
				<?php wp_nonce_field( 'arvator_privacy', '_arv_nonce' ); ?>
				<button class="btn btn--ghost btn--sm" type="submit" name="kind" value="export">Recevoir une copie de mes données</button>
				<button class="btn btn--ghost btn--sm" type="submit" name="kind" value="erase">Demander l’effacement de mes données</button>
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
			'monthly' => 'Abonnement mensuel',
			'annual'  => 'Abonnement annuel',
			'trial'   => 'Essai gratuit',
		)[ $plan ] ?? 'Abonnement';
	}

	private static function product_label( string $product ): string {
		return 'cbot' === $product ? 'Robot Arvator (achat)' : 'Produit';
	}

	private static function status_label( string $kind, string $status ): string {
		$labels = array(
			'order'        => array(
				'paid'     => 'Payé',
				'refunded' => 'Remboursé',
			),
			'subscription' => array(
				'active'   => 'Actif',
				'trialing' => 'Essai',
				'past_due' => 'Paiement en retard',
				'canceled' => 'Résilié',
			),
			'license'      => array(
				'active'  => 'Active',
				'expired' => 'Expirée',
				'revoked'  => 'Révoquée',
				'canceled' => 'Résiliée',
			),
		);
		return $labels[ $kind ][ $status ] ?? ucfirst( $status );
	}
}

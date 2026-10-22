<?php
/**
 * Administration du parrainage (Arvator > Parrainage), réservée aux administrateurs :
 * - Virements : ce qui est à verser, préparation d'un lot, export CSV (nom, IBAN, montant), confirmation du virement ;
 * - Récompenses : liste, déblocage ou annulation des récompenses bloquées (soupçon d'auto-parrainage) ;
 * - Parrains : codes, filleuls, suspension ;
 * - Réglages : prime, taux, délai, seuil, durée du lien.
 *
 * Chaque action vérifie le droit « manage_options » et une clé de sécurité (nonce).
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Arvator_Referral_Admin {

	private const NOTICE_KEY = 'arvator_ref_notice_';

	private const TABS = array(
		'virements'   => 'Virements',
		'recompenses' => 'Récompenses',
		'parrains'    => 'Parrains',
		'reglages'    => 'Réglages',
	);

	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ), 20 );
		foreach ( array( 'batch_create', 'batch_csv', 'batch_paid', 'batch_cancel', 'commission', 'referrer', 'settings' ) as $a ) {
			add_action( 'admin_post_arvator_ref_' . $a, array( self::class, 'handle_' . $a ) );
		}
	}

	public static function menu(): void {
		add_submenu_page( 'arvator', 'Parrainage', 'Parrainage', 'manage_options', 'arvator-parrainage', array( self::class, 'page' ) );
	}

	private static function url( string $tab = 'virements' ): string {
		return admin_url( 'admin.php?page=arvator-parrainage&tab=' . $tab );
	}

	/* --------------------------------------------------------------------- Page */

	public static function page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Accès refusé.', '', array( 'response' => 403 ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : 'virements';
		$tab = isset( self::TABS[ $tab ] ) ? $tab : 'virements';
		Arvator_Referral::promote_due();

		echo '<div class="wrap"><h1>Parrainage</h1>';
		self::show_notice();
		echo '<nav class="nav-tab-wrapper">';
		foreach ( self::TABS as $key => $label ) {
			printf( '<a class="nav-tab%s" href="%s">%s</a>', $key === $tab ? ' nav-tab-active' : '', esc_url( self::url( $key ) ), esc_html( $label ) );
		}
		echo '</nav>';
		match ( $tab ) {
			'recompenses' => self::tab_commissions(),
			'parrains'    => self::tab_referrers(),
			'reglages'    => self::tab_settings(),
			default       => self::tab_payouts(),
		};
		echo '</div>';
	}

	private static function form_open( string $action, string $nonce_suffix = '' ): void {
		printf( '<form method="post" action="%s" style="display:inline">', esc_url( admin_url( 'admin-post.php' ) ) );
		printf( '<input type="hidden" name="action" value="%s">', esc_attr( 'arvator_ref_' . $action ) );
		wp_nonce_field( 'arvator_ref_' . $action . $nonce_suffix, '_arv_nonce' );
	}

	private static function email_of( int $user_id ): string {
		$u = get_user_by( 'id', $user_id );
		return $u instanceof WP_User ? $u->user_email : '#' . $user_id;
	}

	private static function tab_payouts(): void {
		global $wpdb;
		$due = Arvator_Referral::payouts_due();
		echo '<h2>À verser maintenant</h2>';
		echo '<p>Les primes deviennent « à payer » après le délai prévu ; les récompenses sur abonnement sont versées à partir du seuil cumulé. Une déduction (achat remboursé ou contesté après versement) est retirée du total.</p>';
		if ( ! $due ) {
			echo '<p>Rien à verser pour le moment.</p>';
		} else {
			$ready = 0;
			echo '<table class="widefat striped"><thead><tr><th>Parrain</th><th>Titulaire</th><th>IBAN</th><th>Montant</th></tr></thead><tbody>';
			foreach ( $due as $d ) {
				$r       = Arvator_Referral::referrer( $d['user_id'] );
				$has_iba = $r && '' !== (string) $r['iban_enc'];
				$ready  += $has_iba ? 1 : 0;
				printf(
					'<tr><td>%s</td><td>%s</td><td>%s</td><td><strong>%s</strong></td></tr>',
					esc_html( self::email_of( $d['user_id'] ) ),
					esc_html( (string) ( $r['holder_name'] ?? '' ) ),
					$has_iba ? esc_html( substr( (string) $r['iban_last4'], 0, 4 ) . ' •••• ' . substr( (string) $r['iban_last4'], -4 ) ) : '<em>IBAN manquant : pas de virement possible</em>',
					esc_html( Arvator_Referral::money( $d['total'] ) )
				);
			}
			echo '</tbody></table>';
			if ( $ready > 0 ) {
				echo '<p>';
				self::form_open( 'batch_create' );
				submit_button( 'Préparer un lot de virements (' . $ready . ')', 'primary', 'submit', false );
				echo '</form></p>';
			}
		}

		$batches = $wpdb->get_results( 'SELECT payout_batch, COUNT(DISTINCT referrer_user_id) AS n, SUM(amount_cents) AS total FROM ' . Arvator_Repo::table( 'commissions' ) . " WHERE status = 'payable' AND payout_batch IS NOT NULL AND payout_batch <> '' GROUP BY payout_batch ORDER BY payout_batch", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		echo '<h2>Lots en cours</h2>';
		if ( ! $batches ) {
			echo '<p>Aucun lot en cours.</p>';
			return;
		}
		echo '<p>1. Téléchargez le fichier CSV (nom, IBAN, montant). 2. Faites les virements depuis votre banque. 3. Confirmez ici : les parrains reçoivent un email et leurs récompenses passent à « payée ».</p>';
		echo '<table class="widefat striped"><thead><tr><th>Lot</th><th>Parrains</th><th>Total</th><th>Actions</th></tr></thead><tbody>';
		foreach ( (array) $batches as $b ) {
			$id = (string) $b['payout_batch'];
			echo '<tr><td>' . esc_html( $id ) . '</td><td>' . esc_html( (string) $b['n'] ) . '</td><td>' . esc_html( Arvator_Referral::money( (int) $b['total'] ) ) . '</td><td>';
			foreach ( array( 'batch_csv' => array( 'Télécharger le CSV', 'secondary' ), 'batch_paid' => array( 'Virements effectués', 'primary' ), 'batch_cancel' => array( 'Annuler le lot', 'delete' ) ) as $act => $btn ) {
				self::form_open( $act, $id );
				printf( '<input type="hidden" name="batch" value="%s">', esc_attr( $id ) );
				submit_button( $btn[0], $btn[1], 'submit', false, 'batch_paid' === $act ? array( 'onclick' => "return confirm('Confirmer que les virements de ce lot ont bien été faits ?');" ) : null );
				echo '</form> ';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	private static function tab_commissions(): void {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT * FROM ' . Arvator_Repo::table( 'commissions' ) . " ORDER BY (status = 'blocked') DESC, created_at DESC, id DESC LIMIT 300", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		echo '<p>Les récompenses <strong>bloquées</strong> (soupçon d’auto-parrainage) apparaissent en premier : vérifiez, puis débloquez-les ou annulez-les. Les 300 dernières sont affichées.</p>';
		if ( ! $rows ) {
			echo '<p>Aucune récompense.</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>Date</th><th>Parrain</th><th>Acheteur</th><th>Type</th><th>Montant</th><th>État</th><th>Disponible le</th><th>Note</th><th></th></tr></thead><tbody>';
		foreach ( (array) $rows as $c ) {
			echo '<tr>';
			foreach ( array(
				(string) $c['created_at'],
				self::email_of( (int) $c['referrer_user_id'] ),
				self::email_of( (int) $c['referee_user_id'] ),
				Arvator_Referral::kind_label( (string) $c['kind'] ) . ( $c['is_test'] ? ' (test)' : '' ),
				Arvator_Referral::money( (int) $c['amount_cents'] ),
				Arvator_Referral::status_label( (string) $c['status'] ) . ( '' !== (string) $c['payout_batch'] && 'payable' === $c['status'] ? ' · lot ' . $c['payout_batch'] : '' ),
				(string) $c['available_at'],
				(string) $c['note'],
			) as $cell ) {
				echo '<td>' . esc_html( $cell ) . '</td>';
			}
			echo '<td>';
			if ( 'blocked' === $c['status'] ) {
				foreach ( array( 'unblock' => 'Débloquer', 'cancel' => 'Annuler' ) as $op => $label ) {
					self::form_open( 'commission', (string) $c['id'] );
					printf( '<input type="hidden" name="id" value="%d"><input type="hidden" name="op" value="%s">', (int) $c['id'], esc_attr( $op ) );
					submit_button( $label, 'small', 'submit', false );
					echo '</form> ';
				}
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	private static function tab_referrers(): void {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT r.*, (SELECT COUNT(*) FROM ' . Arvator_Repo::table( 'referrals' ) . ' f WHERE f.referrer_user_id = r.user_id) AS n FROM ' . Arvator_Repo::table( 'referrers' ) . ' r ORDER BY r.created_at DESC LIMIT 500', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! $rows ) {
			echo '<p>Aucun parrain inscrit.</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>Email</th><th>Code</th><th>Inscrit le</th><th>Filleuls</th><th>Virement</th><th>État</th><th></th></tr></thead><tbody>';
		foreach ( (array) $rows as $r ) {
			printf(
				'<tr><td>%s</td><td><code>%s</code></td><td>%s</td><td>%d</td><td>%s</td><td>%s</td><td>',
				esc_html( self::email_of( (int) $r['user_id'] ) ),
				esc_html( (string) $r['code'] ),
				esc_html( (string) $r['created_at'] ),
				(int) $r['n'],
				esc_html( '' !== (string) $r['iban_enc'] ? (string) $r['holder_name'] . ( 'entreprise' === $r['entity_type'] ? ' (entreprise, SIRET ' . $r['siret'] . ')' : ' (particulier)' ) : 'IBAN manquant' ),
				esc_html( 'active' === $r['status'] ? 'Actif' : 'Suspendu' )
			);
			self::form_open( 'referrer', (string) $r['user_id'] );
			printf( '<input type="hidden" name="user" value="%d">', (int) $r['user_id'] );
			submit_button( 'active' === $r['status'] ? 'Suspendre le lien' : 'Réactiver', 'small', 'submit', false );
			echo '</form></td></tr>';
		}
		echo '</tbody></table>';
	}

	private static function tab_settings(): void {
		$s = Arvator_Referral::settings();
		echo '<p>Ces valeurs s’appliquent aux <strong>nouvelles</strong> récompenses. Si vous les changez, mettez aussi à jour la page « Conditions du programme de parrainage » (texte juridique) : le bandeau de l’accueil et la page « Parrainage » se mettent à jour seuls.</p>';
		self::form_open( 'settings' );
		echo '<table class="form-table" role="presentation">';
		$fields = array(
			'prime'  => array( 'Prime par achat du robot (€)', number_format( $s['prime_cents'] / 100, 2, '.', '' ), '0.01' ),
			'rate'   => array( 'Récompense sur chaque abonnement payé (%)', (string) ( $s['rate'] * 100 ), '0.1' ),
			'delay'  => array( 'Délai avant « à payer » (jours, 14 minimum)', (string) $s['delay_days'], '1' ),
			'min'    => array( 'Seuil de virement des récompenses d’abonnement (€)', number_format( $s['payout_min_cents'] / 100, 2, '.', '' ), '0.01' ),
			'cookie' => array( 'Durée de mémorisation du lien (jours)', (string) $s['cookie_days'], '1' ),
		);
		foreach ( $fields as $name => $f ) {
			printf( '<tr><th scope="row"><label for="arv-%1$s">%2$s</label></th><td><input id="arv-%1$s" name="%1$s" type="number" min="0" step="%3$s" value="%4$s" class="small-text"></td></tr>', esc_attr( $name ), esc_html( $f[0] ), esc_attr( $f[2] ), esc_attr( $f[1] ) );
		}
		echo '</table>';
		submit_button( 'Enregistrer' );
		echo '</form>';
		echo '<p class="description">Les coordonnées bancaires des parrains sont chiffrées. Pour une protection indépendante des clés de WordPress, ajoutez dans wp-config.php une constante <code>ARVATOR_DATA_KEY</code> (une longue phrase secrète) <strong>avant</strong> que les premiers parrains n’enregistrent leur IBAN : la changer ensuite rendrait les IBAN illisibles (les parrains devraient les ressaisir).</p>';
	}

	/* --------------------------------------------------------------------- Actions */

	private static function guard( string $action ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Accès refusé.', '', array( 'response' => 403 ) );
		}
		check_admin_referer( $action, '_arv_nonce' );
	}

	private static function batch_from_post(): string {
		$b = isset( $_POST['batch'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['batch'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- vérifiée par guard()
		return 1 === preg_match( '/^V[0-9]{14}$/', $b ) ? $b : '';
	}

	public static function handle_batch_create(): void {
		self::guard( 'arvator_ref_batch_create' );
		global $wpdb;
		$batch = 'V' . gmdate( 'YmdHis' );
		$n     = 0;
		foreach ( Arvator_Referral::payouts_due() as $d ) {
			$r = Arvator_Referral::referrer( $d['user_id'] );
			if ( ! $r || '' === (string) $r['iban_enc'] ) {
				continue;
			}
			$ids = implode( ',', array_map( 'intval', $d['ids'] ) );
			$wpdb->query( $wpdb->prepare( 'UPDATE ' . Arvator_Repo::table( 'commissions' ) . " SET payout_batch = %s, updated_at = %s WHERE id IN ($ids) AND status = 'payable' AND (payout_batch IS NULL OR payout_batch = '')", $batch, gmdate( 'Y-m-d H:i:s' ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- identifiants entiers
			++$n;
		}
		Arvator_Repo::log_event( 'payout_batch_created', get_current_user_id(), array( 'batch' => $batch, 'referrers' => $n ) );
		self::flash( 'ok', $n > 0 ? "Lot $batch préparé ($n parrain(s)). Téléchargez le CSV, faites les virements, puis confirmez." : 'Aucun parrain avec IBAN à inclure.' );
		wp_safe_redirect( self::url() );
		exit;
	}

	/** @return array<int, array{user_id:int, total:int}> */
	private static function batch_totals( string $batch ): array {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT referrer_user_id, SUM(amount_cents) AS total FROM ' . Arvator_Repo::table( 'commissions' ) . " WHERE payout_batch = %s AND status = 'payable' GROUP BY referrer_user_id", $batch ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return array_map( static fn( $r ) => array( 'user_id' => (int) $r['referrer_user_id'], 'total' => (int) $r['total'] ), (array) $rows );
	}

	/** Une cellule CSV ne doit jamais être interprétée comme une formule par le tableur. */
	private static function csv_cell( string $v ): string {
		if ( '' !== $v && in_array( $v[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			$v = "'" . $v;
		}
		return '"' . str_replace( '"', '""', $v ) . '"';
	}

	public static function handle_batch_csv(): void {
		$batch = self::batch_from_post();
		self::guard( 'arvator_ref_batch_csv' . $batch );
		if ( '' === $batch ) {
			wp_die( 'Lot inconnu.' );
		}
		$lines = array( implode( ';', array( 'Titulaire', 'IBAN', 'Montant (EUR)', 'Statut', 'SIRET', 'Email', 'Référence' ) ) );
		foreach ( self::batch_totals( $batch ) as $t ) {
			$r = Arvator_Referral::referrer( $t['user_id'] );
			$lines[] = implode(
				';',
				array_map(
					array( self::class, 'csv_cell' ),
					array(
						(string) ( $r['holder_name'] ?? '' ),
						Arvator_Referral::decrypt( (string) ( $r['iban_enc'] ?? '' ) ),
						number_format( $t['total'] / 100, 2, ',', '' ),
						(string) ( $r['entity_type'] ?? '' ),
						(string) ( $r['siret'] ?? '' ),
						self::email_of( $t['user_id'] ),
						'Arvator recompense ' . $batch,
					)
				)
			);
		}
		Arvator_Repo::log_event( 'payout_csv', get_current_user_id(), array( 'batch' => $batch ) );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="arvator-virements-' . $batch . '.csv"' );
		header( 'X-Content-Type-Options: nosniff' );
		echo "\xEF\xBB\xBF" . implode( "\r\n", $lines ) . "\r\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fichier CSV, cellules protégées par csv_cell()
		exit;
	}

	public static function handle_batch_paid(): void {
		$batch = self::batch_from_post();
		self::guard( 'arvator_ref_batch_paid' . $batch );
		global $wpdb;
		$totals = '' === $batch ? array() : self::batch_totals( $batch );
		$now    = gmdate( 'Y-m-d H:i:s' );
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . Arvator_Repo::table( 'commissions' ) . " SET status = 'paid', paid_at = %s, updated_at = %s WHERE payout_batch = %s AND status = 'payable'", $now, $now, $batch ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		foreach ( $totals as $t ) {
			$u = get_user_by( 'id', $t['user_id'] );
			if ( $u instanceof WP_User ) {
				wp_mail( $u->user_email, 'Arvator : votre récompense de recommandation a été virée', "Bonjour,\n\nNous venons de vous virer " . Arvator_Referral::money( $t['total'] ) . " au titre de vos récompenses de recommandation (référence $batch).\nLe détail est dans votre espace membre, rubrique « Parrainage » :\n" . Arvator_Member::url( 'parrainage' ) . "\n\nMerci de recommander Arvator.\n\n" . Arvator_Site::signature() );
			}
		}
		Arvator_Repo::log_event( 'payout_batch_paid', get_current_user_id(), array( 'batch' => $batch, 'referrers' => count( $totals ) ) );
		self::flash( 'ok', 'Lot ' . $batch . ' marqué comme versé (' . count( $totals ) . ' parrain(s) prévenu(s) par email).' );
		wp_safe_redirect( self::url() );
		exit;
	}

	public static function handle_batch_cancel(): void {
		$batch = self::batch_from_post();
		self::guard( 'arvator_ref_batch_cancel' . $batch );
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . Arvator_Repo::table( 'commissions' ) . " SET payout_batch = NULL, updated_at = %s WHERE payout_batch = %s AND status = 'payable'", gmdate( 'Y-m-d H:i:s' ), $batch ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		self::flash( 'ok', 'Lot ' . $batch . ' annulé : les récompenses sont de nouveau « à payer ».' );
		wp_safe_redirect( self::url() );
		exit;
	}

	public static function handle_commission(): void {
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- vérifiée par guard()
		self::guard( 'arvator_ref_commission' . $id );
		$op = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( (string) $_POST['op'] ) ) : '';
		global $wpdb;
		$t   = Arvator_Repo::table( 'commissions' );
		$now = gmdate( 'Y-m-d H:i:s' );
		if ( 'unblock' === $op ) {
			$wpdb->query( $wpdb->prepare( "UPDATE $t SET status = 'pending', note = %s, updated_at = %s WHERE id = %d AND status = 'blocked'", 'Débloquée par l’administrateur', $now, $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			// Le doute est levé pour ce filleul : ses prochaines récompenses ne seront plus bloquées.
			$referee = (int) $wpdb->get_var( $wpdb->prepare( "SELECT referee_user_id FROM $t WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->update( Arvator_Repo::table( 'referrals' ), array( 'status' => 'ok' ), array( 'referee_user_id' => $referee ) );
			Arvator_Referral::promote_due();
		} elseif ( 'cancel' === $op ) {
			$wpdb->query( $wpdb->prepare( "UPDATE $t SET status = 'canceled', note = %s, updated_at = %s WHERE id = %d AND status = 'blocked'", 'Annulée par l’administrateur', $now, $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		Arvator_Repo::log_event( 'commission_' . ( 'unblock' === $op ? 'unblocked' : 'canceled' ), get_current_user_id(), array( 'id' => $id ) );
		self::flash( 'ok', 'Récompense mise à jour.' );
		wp_safe_redirect( self::url( 'recompenses' ) );
		exit;
	}

	public static function handle_referrer(): void {
		$uid = isset( $_POST['user'] ) ? (int) $_POST['user'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- vérifiée par guard()
		self::guard( 'arvator_ref_referrer' . $uid );
		$r = Arvator_Referral::referrer( $uid );
		if ( $r ) {
			global $wpdb;
			$wpdb->update( Arvator_Repo::table( 'referrers' ), array( 'status' => 'active' === $r['status'] ? 'suspended' : 'active', 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ), array( 'user_id' => $uid ) );
		}
		self::flash( 'ok', 'Parrain mis à jour.' );
		wp_safe_redirect( self::url( 'parrains' ) );
		exit;
	}

	public static function handle_settings(): void {
		self::guard( 'arvator_ref_settings' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- vérifiée par guard()
		$num = static fn( string $k ): float => isset( $_POST[ $k ] ) ? (float) str_replace( ',', '.', sanitize_text_field( wp_unslash( (string) $_POST[ $k ] ) ) ) : 0.0;
		// phpcs:enable
		Arvator_Referral::save_settings(
			array(
				'prime_cents'      => (int) round( $num( 'prime' ) * 100 ),
				'rate'             => $num( 'rate' ) / 100,
				'delay_days'       => (int) $num( 'delay' ),
				'payout_min_cents' => (int) round( $num( 'min' ) * 100 ),
				'cookie_days'      => (int) $num( 'cookie' ),
			)
		);
		Arvator_Repo::log_event( 'referral_settings', get_current_user_id(), Arvator_Referral::settings() );
		self::flash( 'ok', 'Réglages enregistrés (les valeurs hors limites ont été ramenées dans les limites).' );
		wp_safe_redirect( self::url( 'reglages' ) );
		exit;
	}

	/* --------------------------------------------------------------------- Messages */

	private static function flash( string $type, string $text ): void {
		set_transient( self::NOTICE_KEY . get_current_user_id(), array( $type, $text ), 5 * MINUTE_IN_SECONDS );
	}

	private static function show_notice(): void {
		$key = self::NOTICE_KEY . get_current_user_id();
		$n   = get_transient( $key );
		if ( is_array( $n ) ) {
			delete_transient( $key );
			printf( '<div class="notice notice-%s"><p>%s</p></div>', 'ok' === $n[0] ? 'success' : 'error', esc_html( (string) $n[1] ) );
		}
	}
}

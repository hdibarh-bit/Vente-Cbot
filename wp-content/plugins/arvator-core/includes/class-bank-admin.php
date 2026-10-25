<?php
/**
 * Administration des virements (Arvator > Virements), réservée aux administrateurs : liste par statut, recherche par
 * référence ou email, « Marquer payée » à la main, « Annuler », « Remboursement effectué », réglages.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Arvator_Bank_Admin {

	private const NOTICE_KEY = 'arvator_bank_notice_';

	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ), 23 );
		add_action( 'admin_post_arvator_bank_markpaid', array( self::class, 'handle_markpaid' ) );
		add_action( 'admin_post_arvator_bank_cancel', array( self::class, 'handle_cancel' ) );
		add_action( 'admin_post_arvator_bank_refund', array( self::class, 'handle_refund' ) );
		add_action( 'admin_post_arvator_bank_settings', array( self::class, 'handle_settings' ) );
	}

	public static function menu(): void {
		add_submenu_page( 'arvator', 'Virements', 'Virements', 'manage_options', 'arvator-virements', array( self::class, 'page' ) );
	}

	private static function guard( string $action ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Accès refusé.', '', array( 'response' => 403 ) );
		}
		$nonce = isset( $_POST['_arv_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_arv_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, $action ) ) {
			wp_die( 'Session expirée : revenez en arrière et réessayez.', '', array( 'response' => 403 ) );
		}
	}

	private static function back( string $type, string $text ): void {
		set_transient( self::NOTICE_KEY . get_current_user_id(), array( $type, $text ), 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=arvator-virements' ) );
		exit;
	}

	private static function order_id(): int {
		return isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- vérifié dans guard()
	}

	public static function handle_markpaid(): void {
		self::guard( 'arvator_bank_markpaid' );
		$id   = self::order_id();
		$tx   = isset( $_POST['tx'] ) ? trim( sanitize_text_field( wp_unslash( (string) $_POST['tx'] ) ) ) : '';
		$date = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['date'] ) ) : '';
		if ( $id <= 0 || '' === $tx || strlen( $tx ) > 190 ) {
			self::back( 'err', 'Indiquez l’identifiant de la transaction bancaire (visible dans votre banque).' );
		}
		$res = Arvator_Bank::mark_paid_manually( $id, $tx, 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : gmdate( 'Y-m-d' ) );
		$msg = array(
			'ok'                        => array( 'ok', 'Commande marquée payée : compte, email, licence, parrainage et facture sont lancés.' ),
			'deja_traite'               => array( 'err', 'Cette commande est déjà payée ou remboursée.' ),
			'transaction_deja_utilisee' => array( 'err', 'Cet identifiant de transaction a déjà servi pour un autre paiement.' ),
			'commande_inconnue'         => array( 'err', 'Commande introuvable.' ),
		)[ $res ] ?? array( 'err', 'Le traitement a échoué : réessayez ; si cela continue, regardez le journal des événements.' );
		self::back( $msg[0], $msg[1] );
	}

	public static function handle_cancel(): void {
		self::guard( 'arvator_bank_cancel' );
		$ok = Arvator_Bank::cancel( self::order_id(), false );
		self::back( $ok ? 'ok' : 'err', $ok ? 'Commande annulée.' : 'Seule une commande en attente peut être annulée.' );
	}

	public static function handle_refund(): void {
		self::guard( 'arvator_bank_refund' );
		$ok = Arvator_Bank::refund( self::order_id() );
		self::back( $ok ? 'ok' : 'err', $ok ? 'Remboursement noté : licence révoquée, abonnement arrêté, prime du parrain annulée ou déduite.' : 'Seule une commande payée peut être marquée remboursée.' );
	}

	public static function handle_settings(): void {
		self::guard( 'arvator_bank_settings' );
		update_option( 'arvator_bank_enabled', ! empty( $_POST['enabled'] ) ? '1' : '0', false );
		$days = isset( $_POST['cancel_days'] ) ? absint( $_POST['cancel_days'] ) : 30;
		update_option( 'arvator_bank_cancel_days', max( 1, min( 365, $days ) ), false );
		$vat = isset( $_POST['vat_note'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['vat_note'] ) ) : '';
		update_option( 'arvator_bank_vat_note', mb_substr( $vat, 0, 200 ), false );
		self::back( 'ok', 'Réglages enregistrés.' );
	}

	private static function status_label( string $s ): string {
		return array(
			Arvator_Bank::PENDING  => 'En attente',
			Arvator_Bank::PAID     => 'Payée',
			Arvator_Bank::CANCELED => 'Annulée',
			Arvator_Bank::REFUNDED => 'Remboursée',
		)[ $s ] ?? $s;
	}

	public static function page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Accès refusé.', '', array( 'response' => 403 ) );
		}
		global $wpdb;
		echo '<div class="wrap"><h1>Virements</h1>';
		$notice = get_transient( self::NOTICE_KEY . get_current_user_id() );
		if ( is_array( $notice ) ) {
			delete_transient( self::NOTICE_KEY . get_current_user_id() );
			printf( '<div class="notice notice-%s"><p>%s</p></div>', 'ok' === $notice[0] ? 'success' : 'error', esc_html( (string) $notice[1] ) );
		}
		echo '<p>Paiement par virement SEPA, formule annuelle (robot + 1 an d’abonnement), puis renouvellement annuel. n8n lit les virements reçus sur Qonto chaque jour à 9 h et prévient le site ; le site déclenche alors tout (compte, email, licence, parrainage, facture). Si un client oublie la référence, utilisez « Marquer payée ».</p>';

		if ( ! Arvator_Bank::configured() ) {
			echo '<div class="notice notice-warning inline"><p><strong>Le virement n’est pas encore proposé</strong> : il manque des réglages dans <code>wp-config.php</code> (<code>ARVATOR_VIREMENT_TITULAIRE</code>, <code>ARVATOR_VIREMENT_IBAN</code>, <code>ARVATOR_VIREMENT_BIC</code> et <code>ARVATOR_VIREMENT_SECRET</code>, 16 caractères au moins).</p></div>';
		}

		// Réglages.
		?>
		<h2>Réglages</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="arvator_bank_settings">
			<?php wp_nonce_field( 'arvator_bank_settings', '_arv_nonce' ); ?>
			<table class="form-table" role="presentation">
				<tr><th scope="row">Virement proposé</th><td><label><input type="checkbox" name="enabled" value="1" <?php checked( '1', (string) get_option( 'arvator_bank_enabled', '1' ) ); ?>> Proposer le virement sur la page « Acheter Arvator »</label></td></tr>
				<tr><th scope="row"><label for="arv-bank-days">Annulation automatique</label></th><td><input id="arv-bank-days" name="cancel_days" type="number" min="1" max="365" value="<?php echo esc_attr( (string) Arvator_Bank::cancel_days() ); ?>" class="small-text"> jours après la commande non payée (rappels à J+3 et J+7).</td></tr>
				<tr><th scope="row"><label for="arv-bank-vat">Mention de TVA sur la facture</label></th><td><input id="arv-bank-vat" name="vat_note" type="text" maxlength="200" class="large-text" value="<?php echo esc_attr( Arvator_Bank::vat_note() ); ?>"><p class="description">À faire valider par votre comptable selon votre statut.</p></td></tr>
			</table>
			<?php submit_button( 'Enregistrer', 'secondary' ); ?>
		</form>
		<?php

		// Liste.
		$status = isset( $_GET['statut'] ) ? sanitize_key( wp_unslash( (string) $_GET['statut'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$q      = isset( $_GET['q'] ) ? trim( sanitize_text_field( wp_unslash( (string) $_GET['q'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$where  = '1=1';
		$args   = array();
		if ( in_array( $status, array( Arvator_Bank::PENDING, Arvator_Bank::PAID, Arvator_Bank::CANCELED, Arvator_Bank::REFUNDED ), true ) ) {
			$where .= ' AND status = %s';
			$args[] = $status;
		}
		if ( '' !== $q ) {
			$like   = '%' . $wpdb->esc_like( $q ) . '%';
			$where .= ' AND (reference LIKE %s OR email LIKE %s OR name LIKE %s)';
			array_push( $args, $like, $like, $like );
		}
		$sql  = 'SELECT * FROM ' . Arvator_Repo::table( 'bank_orders' ) . ' WHERE ' . $where . ' ORDER BY id DESC LIMIT 200';
		$rows = (array) $wpdb->get_results( $args ? $wpdb->prepare( $sql, $args ) : $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		echo '<h2>Commandes</h2><form method="get"><input type="hidden" name="page" value="arvator-virements">';
		echo '<select name="statut"><option value="">Tous les statuts</option>';
		foreach ( array( Arvator_Bank::PENDING, Arvator_Bank::PAID, Arvator_Bank::CANCELED, Arvator_Bank::REFUNDED ) as $s ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $s ), selected( $status, $s, false ), esc_html( self::status_label( $s ) ) );
		}
		echo '</select> <input type="search" name="q" value="' . esc_attr( $q ) . '" placeholder="Référence ou email"> <button class="button">Filtrer</button></form>';

		if ( ! $rows ) {
			echo '<p>Aucune commande par virement.</p></div>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>Référence</th><th>Type</th><th>Client</th><th>Montant</th><th>Statut</th><th>Créée</th><th>Actions</th></tr></thead><tbody>';
		foreach ( $rows as $o ) {
			$pending = Arvator_Bank::PENDING === $o['status'];
			echo '<tr>';
			printf( '<td><code>%s</code>%s</td>', esc_html( (string) $o['reference'] ), $o['is_test'] ? ' <em>(test)</em>' : '' );
			printf( '<td>%s</td>', esc_html( 'renewal' === $o['kind'] ? 'Renouvellement' : 'Achat' ) );
			printf( '<td>%s<br><span class="description">%s</span></td>', esc_html( (string) $o['name'] ), esc_html( (string) $o['email'] ) );
			printf( '<td>%s</td>', esc_html( Arvator_Bank::eur( (int) $o['amount_cents'] ) ) );
			printf( '<td>%s%s</td>', esc_html( self::status_label( (string) $o['status'] ) ), ! empty( $o['invoice_no'] ) ? '<br><span class="description">' . esc_html( (string) $o['invoice_no'] ) . '</span>' : '' );
			printf( '<td>%s</td>', esc_html( Arvator_Bank::date( (string) $o['created_at'] ) ) );
			echo '<td>';
			if ( $pending || Arvator_Bank::CANCELED === $o['status'] ) {
				self::mini_form( 'arvator_bank_markpaid', (int) $o['id'], 'Marquer payée', '<input name="tx" type="text" required maxlength="190" placeholder="Identifiant de transaction" style="width:11em"> <input name="date" type="date" value="' . esc_attr( gmdate( 'Y-m-d' ) ) . '">' );
			}
			if ( $pending ) {
				self::mini_form( 'arvator_bank_cancel', (int) $o['id'], 'Annuler', '' );
			}
			if ( Arvator_Bank::PAID === $o['status'] ) {
				self::mini_form( 'arvator_bank_refund', (int) $o['id'], 'Remboursement effectué', '', 'Confirmer : vous avez déjà remboursé ce client par virement depuis votre banque ?' );
			}
			echo '</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	private static function mini_form( string $action, int $id, string $label, string $extra, string $confirm = '' ): void {
		printf(
			'<form method="post" action="%s" style="margin-bottom:6px"%s><input type="hidden" name="action" value="%s"><input type="hidden" name="id" value="%d">%s%s <button class="button button-small">%s</button></form>',
			esc_url( admin_url( 'admin-post.php' ) ),
			'' !== $confirm ? ' onsubmit="return confirm(\'' . esc_js( $confirm ) . '\')"' : '',
			esc_attr( $action ),
			$id,
			wp_nonce_field( $action, '_arv_nonce', true, false ), // phpcs:ignore WordPress.Security.EscapeOutput
			$extra, // phpcs:ignore WordPress.Security.EscapeOutput -- champs construits ci-dessus
			esc_html( $label )
		);
	}
}

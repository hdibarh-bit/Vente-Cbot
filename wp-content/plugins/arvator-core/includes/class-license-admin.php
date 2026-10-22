<?php
/**
 * Administration des licences (Arvator > Licences), réservée aux administrateurs : suivi des envois au workflow n8n
 * (paiement, résiliation) et bouton « Renvoyer » pour un envoi en erreur.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Arvator_License_Admin {

	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ), 21 );
		add_action( 'admin_post_arvator_license_retry', array( self::class, 'handle_retry' ) );
	}

	public static function menu(): void {
		add_submenu_page( 'arvator', 'Licences', 'Licences', 'manage_options', 'arvator-licences', array( self::class, 'page' ) );
	}

	private static function status_label( string $s ): string {
		return array(
			'pending'         => 'À envoyer',
			'waiting_account' => 'Attend le n° de compte',
			'done'            => 'Fait',
			'error'           => 'Erreur',
			'canceled'        => 'Annulé',
		)[ $s ] ?? $s;
	}

	public static function page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Accès refusé.', '', array( 'response' => 403 ) );
		}
		global $wpdb;
		echo '<div class="wrap"><h1>Licences</h1>';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- simple message
		if ( isset( $_GET['renvoye'] ) ) {
			echo '<div class="notice notice-success"><p>Envoi relancé.</p></div>';
		}
		if ( Arvator_License::configured() ) {
			echo '<p>Liaison avec n8n : <strong>configurée</strong>. Chaque paiement prolonge la licence du client, chaque résiliation ou remboursement la coupe. Le robot récupère sa licence tout seul.</p>';
		} else {
			echo '<div class="notice notice-warning inline"><p><strong>Liaison avec n8n non configurée :</strong> ajoutez la ligne <code>ARVATOR_N8N_TOKEN</code> dans wp-config.php (voir le guide). En attendant, les envois restent « À envoyer » et partent tout seuls dès que la ligne est ajoutée.</p></div>';
		}
		$rows = $wpdb->get_results( 'SELECT * FROM ' . Arvator_Repo::table( 'license_sync' ) . ' ORDER BY id DESC LIMIT 200', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! $rows ) {
			echo '<p>Aucun envoi pour le moment.</p></div>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>Date (UTC)</th><th>Client</th><th>Compte cTrader</th><th>Action</th><th>Formule</th><th>État</th><th>Essais</th><th>Détail</th><th></th></tr></thead><tbody>';
		foreach ( (array) $rows as $r ) {
			$u = get_user_by( 'id', (int) $r['user_id'] );
			echo '<tr>';
			foreach ( array( (string) $r['created_at'], $u instanceof WP_User ? $u->user_email : '#' . $r['user_id'], (string) $r['account'], 'resilier' === $r['action'] ? 'Résiliation' : 'Paiement', (string) $r['plan'], self::status_label( (string) $r['status'] ), (string) $r['attempts'], (string) $r['last_error'] ) as $cell ) {
				echo '<td>' . esc_html( $cell ) . '</td>';
			}
			echo '<td>';
			if ( 'error' === $r['status'] ) {
				printf( '<form method="post" action="%s"><input type="hidden" name="action" value="arvator_license_retry"><input type="hidden" name="id" value="%d">', esc_url( admin_url( 'admin-post.php' ) ), (int) $r['id'] );
				wp_nonce_field( 'arvator_license_retry' . (int) $r['id'], '_arv_nonce' );
				submit_button( 'Renvoyer', 'small', 'submit', false );
				echo '</form>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	public static function handle_retry(): void {
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- vérifiée ci-dessous
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Accès refusé.', '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'arvator_license_retry' . $id, '_arv_nonce' );
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . Arvator_Repo::table( 'license_sync' ) . " SET status = 'pending', attempts = 0, updated_at = %s WHERE id = %d AND status = 'error'", gmdate( 'Y-m-d H:i:s' ), $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$uid = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT user_id FROM ' . Arvator_Repo::table( 'license_sync' ) . ' WHERE id = %d', $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		Arvator_License::process_user( $uid );
		Arvator_Repo::log_event( 'license_retry', get_current_user_id(), array( 'id' => $id ) );
		wp_safe_redirect( admin_url( 'admin.php?page=arvator-licences&renvoye=1' ) );
		exit;
	}
}

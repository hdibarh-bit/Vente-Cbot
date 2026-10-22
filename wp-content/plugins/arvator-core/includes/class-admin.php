<?php
/**
 * Écrans d'administration (réservés aux administrateurs) :
 * - Fichiers privés : mettre en ligne le robot que les clients téléchargent ;
 * - Clients de démonstration : voir l'espace membre avec de fausses données (supprimables en un clic).
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Arvator_Admin {

	private const NOTICE_KEY = 'arvator_admin_notice_';

	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_notices', array( self::class, 'review_notice' ) );
		add_action( 'admin_post_arvator_dismiss_review', array( self::class, 'handle_dismiss_review' ) );
		add_action( 'admin_post_arvator_upload_file', array( self::class, 'handle_upload' ) );
		add_action( 'admin_post_arvator_delete_file', array( self::class, 'handle_delete' ) );
		add_action( 'admin_post_arvator_demo_create', array( self::class, 'handle_demo_create' ) );
		add_action( 'admin_post_arvator_demo_delete', array( self::class, 'handle_demo_delete' ) );
	}

	public static function menu(): void {
		add_menu_page( 'Arvator', 'Arvator', 'manage_options', 'arvator', array( self::class, 'page_files' ), 'dashicons-lock', 58 );
		add_submenu_page( 'arvator', 'Fichiers privés', 'Fichiers privés', 'manage_options', 'arvator', array( self::class, 'page_files' ) );
		add_submenu_page( 'arvator', 'Clients de démonstration', 'Clients de démonstration', 'manage_options', 'arvator-demo', array( self::class, 'page_demo' ) );
	}

	/** Avis : un texte de page a été amélioré mais la page avait été modifiée, donc elle n'a pas été remplacée. */
	public static function review_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$pages = Arvator_Pages::pages_to_review();
		if ( ! $pages ) {
			return;
		}
		$links = array();
		foreach ( $pages as $page ) {
			$links[] = sprintf( '<a href="%s">%s</a>', esc_url( (string) get_edit_post_link( $page->ID ) ), esc_html( get_the_title( $page ) ) );
		}
		$dismiss = wp_nonce_url( admin_url( 'admin-post.php?action=arvator_dismiss_review' ), 'arvator_dismiss_review' );
		printf(
			'<div class="notice notice-warning"><p><strong>Arvator :</strong> le texte de %s a été mis à jour dans la dernière version du plugin (par exemple la nouvelle adresse de contact support@arvator.com), mais vous aviez modifié cette page : elle n\'a donc pas été remplacée. Relisez-la et mettez-la à jour à la main, ou demandez le nouveau texte à Claude. <a href="%s">Ne plus afficher ce message</a></p></div>',
			wp_kses( implode( ', ', $links ), array( 'a' => array( 'href' => array() ) ) ),
			esc_url( $dismiss )
		);
	}

	public static function handle_dismiss_review(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Accès refusé.', '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'arvator_dismiss_review' );
		Arvator_Pages::dismiss_review();
		wp_safe_redirect( admin_url( 'index.php' ) );
		exit;
	}

	/* --------------------------------------------------------------------- Pages */

	public static function page_files(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Accès refusé.', '', array( 'response' => 403 ) );
		}
		echo '<div class="wrap"><h1>Fichiers privés</h1>';
		self::show_notice();
		echo '<p>Le fichier du robot que vous mettez en ligne ici est <strong>invisible du public</strong> : seuls les clients ayant acheté peuvent le télécharger depuis leur espace membre. Formats acceptés : .algo, .zip, .pdf (100 Mo maximum).</p>';
		printf( '<p><em>Dossier de stockage :</em> <code>%s</code></p>', esc_html( Arvator_Downloads::private_dir() ) );
		?>
		<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="arvator_upload_file">
			<?php wp_nonce_field( 'arvator_upload_file', '_arv_nonce' ); ?>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="arv-label">Nom affiché aux clients</label></th><td><input id="arv-label" name="label" type="text" class="regular-text" maxlength="120" placeholder="Robot Arvator"></td></tr>
				<tr><th scope="row"><label for="arv-file">Fichier</label></th><td><input id="arv-file" name="file" type="file" required></td></tr>
			</table>
			<?php submit_button( 'Mettre en ligne' ); ?>
		</form>
		<h2>Fichiers en ligne</h2>
		<?php
		$files = Arvator_Downloads::files();
		if ( ! $files ) {
			echo '<p>Aucun fichier.</p></div>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>Nom affiché</th><th>Fichier d’origine</th><th>Taille</th><th>Ajouté le (UTC)</th><th></th></tr></thead><tbody>';
		foreach ( $files as $id => $f ) {
			$del = wp_nonce_url( admin_url( 'admin-post.php?action=arvator_delete_file&f=' . rawurlencode( (string) $id ) ), 'arvator_delete_file_' . $id );
			printf(
				'<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td><a href="%s" onclick="return confirm(\'Supprimer ce fichier ?\');">Supprimer</a></td></tr>',
				esc_html( (string) $f['label'] ),
				esc_html( (string) $f['original'] ),
				esc_html( size_format( (int) $f['size'] ) ),
				esc_html( (string) $f['added'] ),
				esc_url( $del )
			);
		}
		echo '</tbody></table></div>';
	}

	public static function page_demo(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Accès refusé.', '', array( 'response' => 403 ) );
		}
		echo '<div class="wrap"><h1>Clients de démonstration</h1>';
		self::show_notice();
		echo '<p>Créez un faux client (avec un faux achat, un faux abonnement et une fausse clé, marqués « démonstration ») pour voir l’espace membre tel que vos clients le verront. Supprimez-les d’un clic quand vous avez fini.</p>';
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:1em">
			<input type="hidden" name="action" value="arvator_demo_create">
			<?php wp_nonce_field( 'arvator_demo_create', '_arv_nonce' ); ?>
			<?php submit_button( 'Créer un client de démonstration', 'primary', 'submit', false ); ?>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block">
			<input type="hidden" name="action" value="arvator_demo_delete">
			<?php wp_nonce_field( 'arvator_demo_delete', '_arv_nonce' ); ?>
			<?php submit_button( 'Supprimer tous les clients de démonstration', 'secondary', 'submit', false ); ?>
		</form>
		<?php
		$demos = get_users(
			array(
				'meta_key'   => 'arvator_demo', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'     => array( 'ID', 'user_email' ),
			)
		);
		echo '<h2>Clients de démonstration existants</h2>';
		if ( ! $demos ) {
			echo '<p>Aucun.</p></div>';
			return;
		}
		echo '<ul>';
		foreach ( $demos as $d ) {
			printf( '<li>%s</li>', esc_html( $d->user_email ) );
		}
		echo '</ul></div>';
	}

	/* --------------------------------------------------------------------- Actions */

	private static function guard( string $action ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Accès refusé.', '', array( 'response' => 403 ) );
		}
		check_admin_referer( $action, '_arv_nonce' );
	}

	public static function handle_upload(): void {
		self::guard( 'arvator_upload_file' );
		$label = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['label'] ) ) : '';
		$file  = isset( $_FILES['file'] ) && is_array( $_FILES['file'] ) ? $_FILES['file'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$res   = Arvator_Downloads::add( $file, $label );

		$messages = array(
			'ok'        => array( 'ok', 'Fichier mis en ligne.' ),
			'extension' => array( 'err', 'Format refusé : seuls .algo, .zip et .pdf sont acceptés.' ),
			'size'      => array( 'err', 'Fichier vide ou trop lourd (100 Mo maximum).' ),
			'upload'    => array( 'err', 'Le téléversement a échoué.' ),
			'dir'       => array( 'err', 'Impossible de créer le dossier privé. Définissez ARVATOR_PRIVATE_DIR dans wp-config.php.' ),
			'move'      => array( 'err', 'Impossible d’enregistrer le fichier.' ),
			'denied'    => array( 'err', 'Accès refusé.' ),
		);
		self::flash( $messages[ $res ][0] ?? 'err', $messages[ $res ][1] ?? 'Erreur.' );
		wp_safe_redirect( admin_url( 'admin.php?page=arvator' ) );
		exit;
	}

	public static function handle_delete(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Accès refusé.', '', array( 'response' => 403 ) );
		}
		$id = isset( $_GET['f'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['f'] ) ) : '';
		check_admin_referer( 'arvator_delete_file_' . $id );
		if ( 1 === preg_match( '/^[a-f0-9]{16}$/', $id ) ) {
			Arvator_Downloads::remove( $id );
			self::flash( 'ok', 'Fichier supprimé.' );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=arvator' ) );
		exit;
	}

	public static function handle_demo_create(): void {
		self::guard( 'arvator_demo_create' );

		$tag      = strtolower( wp_generate_password( 6, false, false ) );
		$email    = 'demo-' . $tag . '@arvator-demo.invalid';
		$password = wp_generate_password( 20, true, false );
		$user_id  = wp_insert_user(
			array(
				'user_login'   => 'demo-' . $tag,
				'user_email'   => $email,
				'user_pass'    => $password,
				'display_name' => 'Client démonstration ' . strtoupper( $tag ),
				'role'         => Arvator_Roles::CLIENT,
			)
		);
		if ( is_wp_error( $user_id ) ) {
			self::flash( 'err', 'Création impossible : ' . $user_id->get_error_message() );
			wp_safe_redirect( admin_url( 'admin.php?page=arvator-demo' ) );
			exit;
		}
		update_user_meta( (int) $user_id, 'arvator_demo', '1' );

		global $wpdb;
		$now = gmdate( 'Y-m-d H:i:s' );
		$end = gmdate( 'Y-m-d H:i:s', time() + 30 * DAY_IN_SECONDS );

		$wpdb->insert( Arvator_Repo::table( 'orders' ), array( 'user_id' => $user_id, 'stripe_session_id' => 'demo_' . $tag, 'product' => 'cbot', 'amount_cents' => 150000, 'currency' => 'eur', 'status' => 'paid', 'is_test' => 1, 'created_at' => $now ) );
		$wpdb->insert( Arvator_Repo::table( 'subscriptions' ), array( 'user_id' => $user_id, 'stripe_subscription_id' => 'demo_sub_' . $tag, 'plan' => 'monthly', 'status' => 'active', 'current_period_end' => $end, 'cancel_at_period_end' => 0, 'is_test' => 1, 'created_at' => $now, 'updated_at' => $now ) );
		$wpdb->insert( Arvator_Repo::table( 'licenses' ), array( 'user_id' => $user_id, 'license_key' => 'CLÉ-DE-DÉMONSTRATION-SANS-VALEUR.' . strtoupper( bin2hex( random_bytes( 12 ) ) ), 'plan' => 'monthly', 'account_number' => '0000000', 'demo_only' => 0, 'expires_at' => $end, 'status' => 'active', 'is_test' => 1, 'created_at' => $now, 'updated_at' => $now ) );

		self::flash( 'ok', 'Client de démonstration créé. Connectez-vous (dans une fenêtre de navigation privée) avec : ' . $email . ' / ' . $password . ' — ce mot de passe ne sera plus affiché.' );
		wp_safe_redirect( admin_url( 'admin.php?page=arvator-demo' ) );
		exit;
	}

	public static function handle_demo_delete(): void {
		self::guard( 'arvator_demo_delete' );
		require_once ABSPATH . 'wp-admin/includes/user.php';

		$demos = get_users(
			array(
				'meta_key'   => 'arvator_demo', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'     => 'ID',
			)
		);
		foreach ( $demos as $uid ) {
			Arvator_Repo::delete_test_rows_for_user( (int) $uid );
			wp_delete_user( (int) $uid );
		}
		self::flash( 'ok', count( $demos ) . ' client(s) de démonstration supprimé(s).' );
		wp_safe_redirect( admin_url( 'admin.php?page=arvator-demo' ) );
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

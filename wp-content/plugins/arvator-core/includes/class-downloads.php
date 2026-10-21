<?php
/**
 * Fichiers privés (le robot) : stockés hors de portée du public et servis uniquement après contrôle.
 *
 * Emplacement : la constante ARVATOR_PRIVATE_DIR si elle est définie dans wp-config.php ; sinon un dossier
 * AU-DESSUS du dossier public du site si c'est possible ; sinon un dossier protégé dans « uploads ».
 * Les fichiers sont enregistrés sous un nom aléatoire, sans extension.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Arvator_Downloads {

	private const OPTION      = 'arvator_private_files';
	private const EXTENSIONS  = array( 'algo', 'zip', 'pdf' );
	private const MAX_BYTES   = 100 * MB_IN_BYTES;

	public static function init(): void {
		add_action( 'admin_post_arvator_download', array( self::class, 'serve' ) );
		add_action( 'admin_post_nopriv_arvator_download', array( self::class, 'deny_visitor' ) );
	}

	/* --------------------------------------------------------------------- Emplacement */

	public static function private_dir(): string {
		if ( defined( 'ARVATOR_PRIVATE_DIR' ) && '' !== (string) ARVATOR_PRIVATE_DIR ) {
			return rtrim( (string) ARVATOR_PRIVATE_DIR, '/\\' );
		}
		$above = dirname( untrailingslashit( ABSPATH ) );
		if ( '' !== $above && is_dir( $above ) && wp_is_writable( $above ) ) {
			return $above . '/arvator-private';
		}
		$uploads = wp_upload_dir( null, false );
		return rtrim( (string) $uploads['basedir'], '/\\' ) . '/arvator-private';
	}

	public static function ensure_private_dir(): bool {
		$dir = self::private_dir();
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return false;
		}
		// Si le dossier se trouve malgré tout dans une zone publique, on le verrouille.
		if ( ! file_exists( $dir . '/index.php' ) ) {
			file_put_contents( $dir . '/index.php', "<?php\n// Silence.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		if ( ! file_exists( $dir . '/.htaccess' ) ) {
			file_put_contents( $dir . '/.htaccess', "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		return true;
	}

	/* --------------------------------------------------------------------- Liste */

	/** @return array<string, array<string, mixed>> */
	public static function files(): array {
		$files = get_option( self::OPTION, array() );
		return is_array( $files ) ? $files : array();
	}

	/** Lien de téléchargement (valable pour la session de l'utilisateur connecté). */
	public static function link( string $id ): string {
		return wp_nonce_url( admin_url( 'admin-post.php?action=arvator_download&f=' . rawurlencode( $id ) ), 'arvator_dl_' . $id );
	}

	/* --------------------------------------------------------------------- Ajout / suppression (administrateur) */

	/**
	 * Enregistre un fichier téléversé. Retourne un code d'erreur ou 'ok'.
	 *
	 * @param array<string, mixed> $upload  Élément de $_FILES.
	 */
	public static function add( array $upload, string $label ): string {
		if ( ! current_user_can( 'manage_options' ) ) {
			return 'denied';
		}
		if ( ! isset( $upload['tmp_name'], $upload['name'], $upload['error'] ) || UPLOAD_ERR_OK !== (int) $upload['error'] || ! is_uploaded_file( (string) $upload['tmp_name'] ) ) {
			return 'upload';
		}
		$original = sanitize_file_name( (string) $upload['name'] );
		$ext      = strtolower( pathinfo( $original, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, self::EXTENSIONS, true ) ) {
			return 'extension';
		}
		$size = (int) filesize( (string) $upload['tmp_name'] );
		if ( $size <= 0 || $size > self::MAX_BYTES ) {
			return 'size';
		}
		if ( ! self::ensure_private_dir() ) {
			return 'dir';
		}

		$id     = bin2hex( random_bytes( 8 ) );
		$stored = bin2hex( random_bytes( 16 ) );
		if ( ! move_uploaded_file( (string) $upload['tmp_name'], self::private_dir() . '/' . $stored ) ) {
			return 'move';
		}
		$files        = self::files();
		$files[ $id ] = array(
			'label'    => '' !== $label ? $label : $original,
			'stored'   => $stored,
			'original' => $original,
			'size'     => $size,
			'added'    => gmdate( 'Y-m-d H:i:s' ),
		);
		update_option( self::OPTION, $files, false );
		return 'ok';
	}

	public static function remove( string $id ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$files = self::files();
		if ( ! isset( $files[ $id ] ) ) {
			return;
		}
		$path = self::private_dir() . '/' . $files[ $id ]['stored'];
		if ( is_file( $path ) ) {
			wp_delete_file( $path );
		}
		unset( $files[ $id ] );
		update_option( self::OPTION, $files, false );
	}

	/* --------------------------------------------------------------------- Téléchargement */

	public static function deny_visitor(): void {
		Arvator_Support::redirect( Arvator_Member::url(), 'login_invalid' );
	}

	public static function serve(): void {
		$id = isset( $_GET['f'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['f'] ) ) : '';
		if ( 1 !== preg_match( '/^[a-f0-9]{16}$/', $id ) ) {
			wp_die( 'Fichier introuvable.', 'Erreur', array( 'response' => 404 ) );
		}
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'arvator_dl_' . $id ) ) {
			wp_die( 'Lien expiré. Retournez dans votre espace membre et cliquez de nouveau.', 'Lien expiré', array( 'response' => 403 ) );
		}

		$user_id = get_current_user_id();
		if ( ! Arvator_Repo::is_entitled( $user_id ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Le téléchargement n’est pas disponible pour votre compte.', 'Accès refusé', array( 'response' => 403 ) );
		}

		$files = self::files();
		if ( ! isset( $files[ $id ] ) ) {
			wp_die( 'Fichier introuvable.', 'Erreur', array( 'response' => 404 ) );
		}
		$dir  = realpath( self::private_dir() );
		$path = realpath( self::private_dir() . '/' . $files[ $id ]['stored'] );
		if ( false === $dir || false === $path || ! str_starts_with( $path, $dir . DIRECTORY_SEPARATOR ) || ! is_file( $path ) ) {
			wp_die( 'Fichier introuvable.', 'Erreur', array( 'response' => 404 ) );
		}

		Arvator_Repo::log_event( 'download', $user_id, array( 'file' => $id ) );

		$name = (string) $files[ $id ]['original'];
		nocache_headers();
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="' . str_replace( array( '"', '\\' ), '', $name ) . '"; filename*=UTF-8\'\'' . rawurlencode( $name ) );
		header( 'Content-Length: ' . (string) filesize( $path ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Cache-Control: private, no-store' );

		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}
}

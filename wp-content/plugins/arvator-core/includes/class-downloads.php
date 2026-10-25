<?php
/**
 * Fichiers privés (le robot) : stockés hors de portée du public et servis uniquement après contrôle.
 *
 * Emplacement : la constante ARVATOR_PRIVATE_DIR si elle est définie dans wp-config.php ; sinon un dossier
 * AU-DESSUS du dossier public du site si c'est possible ; sinon un dossier protégé dans « uploads ».
 * Les fichiers sont enregistrés sous un nom aléatoire, sans extension.
 *
 * Deux fichiers peuvent porter un rôle (un seul de chaque, le nouveau remplace l'ancien) : « Robot » (.algo ou .zip)
 * et « Guide d'installation » (.pdf). Le guide est joint à l'email de confirmation d'achat ; robot et guide sont
 * joints à l'email d'essai (ou remplacés par des liens privés de 14 jours s'ils sont trop lourds, voir Arvator_Trial).
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Arvator_Downloads {

	private const OPTION      = 'arvator_private_files';
	private const ROLES_OPT   = 'arvator_file_roles';
	private const VIDEO_OPT   = 'arvator_video_url';
	private const EXTENSIONS  = array( 'algo', 'zip', 'pdf' );
	private const MAX_BYTES   = 100 * MB_IN_BYTES;
	/** Formats acceptés pour chaque rôle. */
	public const ROLES        = array(
		'robot' => array( 'algo', 'zip' ),
		'guide' => array( 'pdf' ),
	);
	/** Poids maximal des pièces jointes d'un email (réglable : ARVATOR_MAIL_MAX_ATTACH_MB dans wp-config.php). */
	private const ATTACH_MB   = 10;

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

	/* --------------------------------------------------------------------- Rôles : robot et guide d'installation */

	/** @return array<string, string>  rôle => identifiant du fichier */
	public static function roles(): array {
		$roles = get_option( self::ROLES_OPT, array() );
		return is_array( $roles ) ? $roles : array();
	}

	/** Donne un rôle à un fichier (le précédent fichier de ce rôle le perd). Retourne 'ok' ou un code d'erreur. */
	public static function set_role( string $id, string $role ): string {
		if ( ! current_user_can( 'manage_options' ) ) {
			return 'denied';
		}
		$files = self::files();
		if ( ! isset( self::ROLES[ $role ] ) || ! isset( $files[ $id ] ) ) {
			return 'role';
		}
		$ext = strtolower( pathinfo( (string) $files[ $id ]['original'], PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, self::ROLES[ $role ], true ) ) {
			return 'role_ext';
		}
		$roles = self::roles();
		foreach ( $roles as $r => $fid ) {
			if ( $fid === $id ) {
				unset( $roles[ $r ] ); // un fichier n'a qu'un rôle
			}
		}
		$roles[ $role ] = $id;
		update_option( self::ROLES_OPT, $roles, false );
		return 'ok';
	}

	/** Rôle d'un fichier ('robot', 'guide' ou ''). */
	public static function role_of( string $id ): string {
		$found = array_search( $id, self::roles(), true );
		return is_string( $found ) ? $found : '';
	}

	/**
	 * Fichier qui porte ce rôle, s'il existe vraiment sur le disque.
	 *
	 * @return array{id:string, path:string, name:string, size:int, label:string}|null
	 */
	public static function role_file( string $role ): ?array {
		$id    = self::roles()[ $role ] ?? '';
		$files = self::files();
		if ( '' === $id || ! isset( $files[ $id ] ) ) {
			return null;
		}
		$path = self::path( $id );
		if ( null === $path ) {
			return null;
		}
		return array(
			'id'    => $id,
			'path'  => $path,
			'name'  => (string) $files[ $id ]['original'],
			'size'  => (int) filesize( $path ),
			'label' => (string) $files[ $id ]['label'],
		);
	}

	/** Chemin réel d'un fichier privé, seulement s'il est bien dans le dossier privé. */
	public static function path( string $id ): ?string {
		$files = self::files();
		if ( ! isset( $files[ $id ] ) ) {
			return null;
		}
		$dir  = realpath( self::private_dir() );
		$path = realpath( self::private_dir() . '/' . $files[ $id ]['stored'] );
		if ( false === $dir || false === $path || ! str_starts_with( $path, $dir . DIRECTORY_SEPARATOR ) || ! is_file( $path ) ) {
			return null;
		}
		return $path;
	}

	/** Poids maximal (octets) des pièces jointes d'un même email. */
	public static function max_attach_bytes(): int {
		$mb = defined( 'ARVATOR_MAIL_MAX_ATTACH_MB' ) ? (float) ARVATOR_MAIL_MAX_ATTACH_MB : self::ATTACH_MB;
		return (int) ( max( 0.0, $mb ) * MB_IN_BYTES );
	}

	/**
	 * Pièces jointes pour wp_mail : nom affiché => chemin (le fichier stocké n'a pas d'extension).
	 *
	 * @param array<int, array{path:string, name:string}> $files
	 * @return array<string, string>
	 */
	public static function attachments( array $files ): array {
		$out = array();
		foreach ( $files as $f ) {
			$out[ $f['name'] ] = $f['path'];
		}
		return $out;
	}

	/* --------------------------------------------------------------------- Vidéo de démonstration */

	public static function video_url(): string {
		$url = (string) get_option( self::VIDEO_OPT, '' );
		return str_starts_with( $url, 'https://' ) ? $url : '';
	}

	/** Enregistre le lien de la vidéo (https uniquement ; vide = rien n'est affiché). */
	public static function set_video_url( string $url ): bool {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		$url = esc_url_raw( trim( $url ), array( 'https' ) );
		if ( '' !== $url && ! str_starts_with( $url, 'https://' ) ) {
			return false;
		}
		update_option( self::VIDEO_OPT, $url, false );
		return true;
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
		$roles = array_filter( self::roles(), static fn( $fid ): bool => $fid !== $id );
		update_option( self::ROLES_OPT, $roles, false );
	}

	/* --------------------------------------------------------------------- Téléchargement */

	public static function deny_visitor(): void {
		Arvator_Support::redirect( Arvator_Member::url(), 'login_invalid' );
	}

	public static function serve(): void {
		$id = isset( $_GET['f'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['f'] ) ) : '';
		if ( 1 !== preg_match( '/^[a-f0-9]{16}$/', $id ) ) {
			wp_die( esc_html__( 'Fichier introuvable.', 'arvator-core' ), esc_html__( 'Erreur', 'arvator-core' ), array( 'response' => 404 ) );
		}
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'arvator_dl_' . $id ) ) {
			wp_die( esc_html__( 'Lien expiré. Retournez dans votre espace membre et cliquez de nouveau.', 'arvator-core' ), esc_html__( 'Lien expiré', 'arvator-core' ), array( 'response' => 403 ) );
		}

		$user_id = get_current_user_id();
		if ( ! Arvator_Repo::is_entitled( $user_id ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Le téléchargement n’est pas disponible pour votre compte.', 'arvator-core' ), esc_html__( 'Accès refusé', 'arvator-core' ), array( 'response' => 403 ) );
		}

		if ( null === self::path( $id ) ) {
			wp_die( esc_html__( 'Fichier introuvable.', 'arvator-core' ), esc_html__( 'Erreur', 'arvator-core' ), array( 'response' => 404 ) );
		}
		Arvator_Repo::log_event( 'download', $user_id, array( 'file' => $id ) );
		self::send_file( $id );
	}

	/** Envoie le fichier au navigateur (après les contrôles d'accès faits par l'appelant). */
	public static function send_file( string $id ): never {
		$files = self::files();
		$path  = self::path( $id );
		if ( null === $path ) {
			wp_die( esc_html__( 'Fichier introuvable.', 'arvator-core' ), esc_html__( 'Erreur', 'arvator-core' ), array( 'response' => 404 ) );
		}
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

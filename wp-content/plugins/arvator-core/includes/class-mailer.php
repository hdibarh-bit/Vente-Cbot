<?php
/**
 * Envoi des emails du site.
 *
 * Par défaut : l'envoi standard de l'hébergeur. Pour un envoi plus fiable (moins de spam), renseigner dans
 * wp-config.php (valeurs de la boîte email créée dans hPanel) :
 *   define( 'ARVATOR_SMTP_HOST', 'smtp.hostinger.com' );
 *   define( 'ARVATOR_SMTP_PORT', 465 );
 *   define( 'ARVATOR_SMTP_SECURE', 'ssl' );   // 'ssl' (465) ou 'tls' (587)
 *   define( 'ARVATOR_SMTP_USER', 'contact@cosmartis.com' );
 *   define( 'ARVATOR_SMTP_PASS', '********' );
 *   define( 'ARVATOR_MAIL_FROM', 'contact@cosmartis.com' );
 * Le mot de passe ne va JAMAIS ailleurs que dans wp-config.php.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Arvator_Mailer {

	public static function init(): void {
		add_action( 'phpmailer_init', array( self::class, 'configure_smtp' ) );
		add_filter( 'wp_mail_from', array( self::class, 'from' ) );
		add_filter( 'wp_mail_from_name', static fn(): string => 'Arvator' );
	}

	/** @param \PHPMailer\PHPMailer\PHPMailer $phpmailer */
	public static function configure_smtp( $phpmailer ): void {
		if ( ! defined( 'ARVATOR_SMTP_HOST' ) || '' === (string) ARVATOR_SMTP_HOST ) {
			return;
		}
		$phpmailer->isSMTP();
		$phpmailer->Host       = (string) ARVATOR_SMTP_HOST;
		$phpmailer->Port       = defined( 'ARVATOR_SMTP_PORT' ) ? (int) ARVATOR_SMTP_PORT : 465;
		$phpmailer->SMTPSecure = defined( 'ARVATOR_SMTP_SECURE' ) ? (string) ARVATOR_SMTP_SECURE : 'ssl';
		$user                  = defined( 'ARVATOR_SMTP_USER' ) ? (string) ARVATOR_SMTP_USER : '';
		if ( '' !== $user ) {
			$phpmailer->SMTPAuth = true;
			$phpmailer->Username = $user;
			$phpmailer->Password = defined( 'ARVATOR_SMTP_PASS' ) ? (string) ARVATOR_SMTP_PASS : '';
		}
	}

	public static function from( string $from ): string {
		return ( defined( 'ARVATOR_MAIL_FROM' ) && is_email( (string) ARVATOR_MAIL_FROM ) ) ? (string) ARVATOR_MAIL_FROM : $from;
	}
}

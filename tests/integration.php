<?php
/**
 * Tests d'intégration du plugin Arvator Core (pages, contact, espace membre, isolation entre clients, sécurité).
 *
 * À lancer avec tests/run.sh sur un WordPress JETABLE (jamais sur le vrai site : il crée et supprime des utilisateurs).
 * Variables : ARV_WP (dossier de WordPress), ARV_URL (adresse du serveur de test), ARV_MAILLOG (fichier journal des emails).
 */

declare(strict_types=1);

$arv_wp_path = (string) getenv( 'ARV_WP' );
$arv_url_in  = rtrim( (string) getenv( 'ARV_URL' ), '/' );
$arv_log_in  = (string) getenv( 'ARV_MAILLOG' );
if ( ! $arv_wp_path || ! $arv_url_in || ! $arv_log_in ) {
	fwrite( STDERR, "Définir ARV_WP, ARV_URL et ARV_MAILLOG (voir tests/run.sh).\n" );
	exit( 2 );
}
define( 'WP_USE_THEMES', false );
require $arv_wp_path . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
// Variables définies APRÈS le chargement de WordPress (éviter les conflits avec ses variables globales).
$url = $arv_url_in;
$log = $arv_log_in;

$GLOBALS['ok']   = 0;
$GLOBALS['fail'] = 0;
function check( bool $cond, string $label, string $detail = '' ): void {
	if ( $cond ) {
		++$GLOBALS['ok'];
		echo "  ok    $label\n";
	} else {
		++$GLOBALS['fail'];
		echo "  ÉCHEC $label" . ( $detail ? " — $detail" : '' ) . "\n";
	}
}
function title( string $t ): void {
	echo "\n== $t\n";
}

/** Petit client HTTP : pas de redirection suivie, cookies par session. */
final class Browser {
	public string $jar;
	public function __construct( private string $base ) {
		$this->jar = tempnam( sys_get_temp_dir(), 'arvjar' );
	}
	/** @return array{code:int, headers:string, body:string, location:string} */
	public function req( string $method, string $path, array $post = array(), array $extra_headers = array() ): array {
		$ch = curl_init( str_starts_with( $path, 'http' ) ? $path : $this->base . $path );
		curl_setopt_array(
			$ch,
			array(
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HEADER         => true,
				CURLOPT_FOLLOWLOCATION => false,
				CURLOPT_COOKIEJAR      => $this->jar,
				CURLOPT_COOKIEFILE     => $this->jar,
				CURLOPT_TIMEOUT        => 60,
				CURLOPT_HTTPHEADER     => $extra_headers,
			)
		);
		curl_setopt( $ch, CURLOPT_COOKIE, 'wordpress_test_cookie=WP%20Cookie%20check' );
		if ( 'POST' === $method ) {
			curl_setopt( $ch, CURLOPT_POST, true );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query( $post ) );
		}
		$raw  = (string) curl_exec( $ch );
		$code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		$hs   = (int) curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
		curl_close( $ch );
		$headers = substr( $raw, 0, $hs );
		$loc     = preg_match( '/^Location:\s*(.+)$/mi', $headers, $m ) ? trim( $m[1] ) : '';
		return array( 'code' => $code, 'headers' => $headers, 'body' => substr( $raw, $hs ), 'location' => $loc );
	}
	public function get( string $path ): array {
		return $this->req( 'GET', $path );
	}
	public function post( string $path, array $data ): array {
		return $this->req( 'POST', $path, $data );
	}
}

function notice_of( array $r ): string {
	return preg_match( '/arv_notice=([a-z_]+)/', $r['location'], $m ) ? $m[1] : '';
}
function field( string $html, string $name ): string {
	return preg_match( '/name="' . preg_quote( $name, '/' ) . '"[^>]*value="([^"]*)"/', $html, $m ) ? html_entity_decode( $m[1] ) : '';
}
/** Valeur d'un champ DANS le formulaire dont l'action cachée est $action. */
function field_in_form( string $html, string $action, string $name ): string {
	foreach ( preg_split( '/<form\b/i', $html ) as $chunk ) {
		if ( str_contains( $chunk, 'value="' . $action . '"' ) ) {
			return field( $chunk, $name );
		}
	}
	return '';
}
function clear_transients(): void {
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient%arv\\_%' OR option_name LIKE '\\_transient%arvator\\_%'" );
}
function mails(): array {
	global $log;
	return is_file( $log ) ? array_filter( array_map( 'json_decode', file( $log, FILE_IGNORE_NEW_LINES ) ) ) : array();
}
function member_login( Browser $b, string $user, string $pass ): array {
	$page = $b->get( '/espace-membre/' );
	return $b->post( '/wp-admin/admin-post.php', array( 'action' => 'arvator_login', '_arv_nonce' => field( $page['body'], '_arv_nonce' ), 'log' => $user, 'pwd' => $pass ) );
}

// Adresses « propres » comme sur le vrai site (Réglages > Permaliens > Titre de la publication).
if ( '/%postname%/' !== get_option( 'permalink_structure' ) ) {
	update_option( 'permalink_structure', '/%postname%/' );
	flush_rewrite_rules();
}
// Départ propre : on supprime les pages du plugin et on les recrée comme à la première installation.
foreach ( array( 'a-propos', 'faq', 'contact', 'risques', 'mentions-legales', 'cgv', 'confidentialite', 'cookies', 'acheter', 'espace-membre' ) as $arv_slug ) {
	$arv_old = get_page_by_path( $arv_slug, OBJECT, 'page' );
	if ( $arv_old ) {
		wp_delete_post( $arv_old->ID, true );
	}
}
delete_option( 'arvator_pages_created' );
Arvator_Pages::create_missing();
@unlink( $log );
clear_transients();
$tag = substr( md5( (string) microtime( true ) ), 0, 6 );

/* ======================================================================== A. Pages */
title( 'A. Pages créées' );
$slugs = array( 'a-propos', 'faq', 'contact', 'risques', 'mentions-legales', 'cgv', 'confidentialite', 'cookies', 'acheter', 'espace-membre' );
$anon  = new Browser( $url );
foreach ( $slugs as $s ) {
	$page = get_page_by_path( $s, OBJECT, 'page' );
	check( $page instanceof WP_Post && 'publish' === $page->post_status, "page « $s » publiée" );
	$r = $anon->get( '/' . $s . '/' );
	check( 200 === $r['code'] && ! preg_match( '/Warning:|Notice:|Fatal error|Deprecated:/', $r['body'] ), "page « $s » s'affiche sans erreur PHP", (string) $r['code'] );
}
$faq = $anon->get( '/faq/' );
check( str_contains( $faq['body'], 'wp-block-details' ) && str_contains( $faq['body'], 'tout courtier proposant cTrader avec le cloud' ), 'FAQ : questions et réponse « courtier » présentes' );
$cgv = $anon->get( '/cgv/' );
check( str_contains( $cgv['body'], '14 jours' ) && str_contains( $cgv['body'], 'notice-provisional' ), 'CGV : règle des 14 jours et bandeau « provisoire »' );
$ml = $anon->get( '/mentions-legales/' );
check( str_contains( $ml['body'], '802 301 317 00029' ) && str_contains( $ml['body'], 'contact@cosmartis.com' ), 'Mentions légales : SIRET et email' );
foreach ( $slugs as $s ) {
	$page = get_page_by_path( $s );
	foreach ( array( 'ICT', 'SMC', '15 minutes', 'Nasdaq', 'US 500', 'bientôt' ) as $banned ) {
		if ( str_contains( (string) $page->post_content, $banned ) ) {
			check( false, "page « $s » : mot interdit « $banned »" );
		}
	}
}
check( true, 'aucune page ne contient de mot interdit (ICT, SMC, unités de temps, indices, « bientôt »)' );

check( str_contains( $faq['body'], 'première période n' ) && str_contains( $faq['body'], 'renonciez à votre droit de rétractation' ), 'FAQ : remboursement du prix du robot (pas de la 1re période) et renonciation avant l’essai' );
$plain = static fn( string $html ): string => str_replace( array( '’', "\u{00A0}" ), array( "'", ' ' ), html_entity_decode( strip_tags( $html ), ENT_QUOTES, 'UTF-8' ) );
check( str_contains( $plain( $cgv['body'] ), 'prix du robot (1 500 €)' ) && str_contains( $plain( $cgv['body'] ), "abonnement de la première période n'est pas remboursé" ), 'CGV : prix du robot remboursé, abonnement de la première période non remboursé' );

title( 'A3. Mise à jour des textes améliorés' );
global $wpdb;
$faq_page = get_page_by_path( 'faq' );
$cgv_page = get_page_by_path( 'cgv' );
// Ancienne version du texte, page jamais touchée (même date de création et de modification).
$wpdb->update( $wpdb->posts, array( 'post_content' => 'ANCIEN TEXTE FAQ', 'post_modified_gmt' => $faq_page->post_date_gmt, 'post_modified' => $faq_page->post_date ), array( 'ID' => $faq_page->ID ) );
clean_post_cache( $faq_page->ID );
$revs = get_option( 'arvator_pages_rev' ); $revs['faq'] = 1; $revs['cgv'] = 1; update_option( 'arvator_pages_rev', $revs, false );
// CGV : modifiée par Hamid.
wp_update_post( array( 'ID' => $cgv_page->ID, 'post_content' => 'TEXTE CGV MODIFIÉ PAR HAMID' ) );
Arvator_Pages::create_missing();
clean_post_cache( $faq_page->ID ); clean_post_cache( $cgv_page->ID );
$f2 = get_post( $faq_page->ID );
check( str_contains( $f2->post_content, 'première période' ), 'page jamais modifiée : le texte amélioré la remplace' );
check( $f2->post_modified_gmt === $f2->post_date_gmt, 'elle reste « jamais touchée » (les prochaines améliorations s’appliqueront aussi)' );
check( 'TEXTE CGV MODIFIÉ PAR HAMID' === get_post( $cgv_page->ID )->post_content, 'page modifiée par Hamid : jamais remplacée' );
check( array_keys( Arvator_Pages::pages_to_review() ) === array( 'cgv' ), 'page modifiée : signalée « à relire » dans l’administration' );
Arvator_Pages::dismiss_review();
check( array() === Arvator_Pages::pages_to_review(), 'le message « à relire » peut être masqué' );

title( 'A2. Création unique des pages' );
$before = (int) wp_count_posts( 'page' )->publish;
Arvator_Pages::create_missing();
check( (int) wp_count_posts( 'page' )->publish === $before, 'relancer la création ne crée aucun doublon' );
$faq_id = (int) get_page_by_path( 'faq' )->ID;
wp_update_post( array( 'ID' => $faq_id, 'post_content' => 'Texte modifié par Hamid' ) );
Arvator_Pages::create_missing();
check( 'Texte modifié par Hamid' === get_post( $faq_id )->post_content, 'une page modifiée n’est jamais écrasée' );
wp_delete_post( $faq_id, true );
Arvator_Pages::create_missing();
check( null === get_page_by_path( 'faq' ), 'une page supprimée volontairement n’est pas recréée' );

/* ======================================================================== B. Contact */
title( 'B. Formulaire de contact' );
$form       = $anon->get( '/contact/' );
check( str_contains( $form['headers'], 'no-store' ), 'page de contact jamais mise en cache' );
$nonce = field( $form['body'], '_arv_nonce' );
$ts    = field( $form['body'], 'arv_ts' );
$data  = array( 'action' => 'arvator_contact', '_arv_nonce' => $nonce, 'arv_ts' => $ts, 'website' => '', 'arv_name' => 'Jean Test', 'arv_email' => "jean$tag@example.com", 'arv_subject' => 'avant-achat', 'arv_message' => 'Bonjour, ceci est un message de test assez long.', 'arv_consent' => '1' );

$r = $anon->post( '/wp-admin/admin-post.php', $data );
check( 'contact_expired' === notice_of( $r ), 'envoi immédiat (robot) refusé', notice_of( $r ) );
sleep( 4 );
$bad = $data; $bad['_arv_nonce'] = 'faux';
check( 'contact_expired' === notice_of( $anon->post( '/wp-admin/admin-post.php', $bad ) ), 'mauvaise clé de sécurité refusée' );
$bad = $data; $bad['arv_ts'] = '1.abc';
check( 'contact_expired' === notice_of( $anon->post( '/wp-admin/admin-post.php', $bad ) ), 'horodatage falsifié refusé' );
$bad = $data; $bad['arv_email'] = 'pas-un-email';
check( 'contact_invalid' === notice_of( $anon->post( '/wp-admin/admin-post.php', $bad ) ), 'email invalide refusé' );
$bad = $data; unset( $bad['arv_consent'] );
check( 'contact_invalid' === notice_of( $anon->post( '/wp-admin/admin-post.php', $bad ) ), 'sans consentement refusé' );
$bad = $data; $bad['arv_message'] = 'trop court';
check( 'contact_invalid' === notice_of( $anon->post( '/wp-admin/admin-post.php', $bad ) ), 'message trop court refusé' );
$bad = $data; $bad['arv_subject'] = 'hack';
check( 'contact_invalid' === notice_of( $anon->post( '/wp-admin/admin-post.php', $bad ) ), 'sujet hors liste refusé' );
$n0  = count( mails() );
$bad = $data; $bad['website'] = 'http://spam';
check( 'contact_ok' === notice_of( $anon->post( '/wp-admin/admin-post.php', $bad ) ) && count( mails() ) === $n0, 'champ piège rempli : faux succès, aucun email envoyé' );
$r = $anon->post( '/wp-admin/admin-post.php', $data );
$m = mails();
check( 'contact_ok' === notice_of( $r ) && count( $m ) === $n0 + 1, 'message valide envoyé' );
$last = end( $m );
check( $last && 'contact@cosmartis.com' === $last->to, 'destinataire : contact@cosmartis.com' );
check( $last && str_contains( implode( "\n", (array) $last->headers ), "Reply-To: Jean Test <jean$tag@example.com>" ), 'Reply-To correct' );
check( 'contact_rate' === notice_of( $anon->post( '/wp-admin/admin-post.php', $data ) ), 'même email deux fois de suite : limité' );
$injection = $data; $injection['arv_name'] = "Pirate\r\nBcc: victime@example.com"; $injection['arv_email'] = "inj$tag@example.com";
$anon->post( '/wp-admin/admin-post.php', $injection );
$all  = mails();
$last = end( $all );
check( $last && ! preg_match( '/^Bcc:/mi', implode( "\n", (array) $last->headers ) ), 'injection d’en-tête email impossible' );
$n1 = count( mails() );
for ( $i = 0; $i < 3; $i++ ) {
	$d = $data; $d['arv_email'] = "rate$i$tag@example.com";
	$res = $anon->post( '/wp-admin/admin-post.php', $d );
}
check( 'contact_rate' === notice_of( $res ), 'plus de 3 messages par heure depuis la même adresse IP : limité' );
clear_transients();

/* ======================================================================== C. Données et isolation */
title( 'C. Données : isolation entre clients' );
$pwA = 'Client-A-motdepasse-' . $tag;
$pwB = 'Client-B-motdepasse-' . $tag;
$pwC = 'Client-C-motdepasse-' . $tag;
$uA  = wp_insert_user( array( 'user_login' => "ta$tag", 'user_email' => "a$tag@example.com", 'user_pass' => $pwA, 'role' => Arvator_Roles::CLIENT, 'display_name' => '<script>alert(1)</script>Alice' ) );
$uB  = wp_insert_user( array( 'user_login' => "tb$tag", 'user_email' => "b$tag@example.com", 'user_pass' => $pwB, 'role' => Arvator_Roles::CLIENT, 'display_name' => 'Bob' ) );
$uC  = wp_insert_user( array( 'user_login' => "tc$tag", 'user_email' => "c$tag@example.com", 'user_pass' => $pwC, 'role' => Arvator_Roles::CLIENT, 'display_name' => 'Chloé' ) );
check( is_int( $uA ) && is_int( $uB ) && is_int( $uC ), 'trois clients créés' );
$role = get_role( Arvator_Roles::CLIENT );
check( $role && array( 'read' => true ) === $role->capabilities, 'le rôle client n’a qu’un seul droit : « read »' );
check( ! user_can( $uA, 'edit_posts' ) && ! user_can( $uA, 'upload_files' ) && ! user_can( $uA, 'manage_options' ), 'un client ne peut ni écrire, ni téléverser, ni administrer' );

global $wpdb;
$now = gmdate( 'Y-m-d H:i:s' ); $end = gmdate( 'Y-m-d H:i:s', time() + 86400 * 30 );
$keyA = 'KEY-ALICE-' . $tag; $keyB = 'KEY-BOB-' . $tag;
$ins = static function ( int $u, string $key, int $cents ) use ( $wpdb, $now, $end, $tag ): void {
	$wpdb->insert( Arvator_Repo::table( 'orders' ), array( 'user_id' => $u, 'stripe_session_id' => "cs_{$u}_$tag", 'product' => 'cbot', 'amount_cents' => $cents, 'status' => 'paid', 'is_test' => 1, 'created_at' => $now ) );
	$wpdb->insert( Arvator_Repo::table( 'subscriptions' ), array( 'user_id' => $u, 'stripe_subscription_id' => "sub_{$u}_$tag", 'plan' => 'monthly', 'status' => 'active', 'current_period_end' => $end, 'is_test' => 1, 'created_at' => $now, 'updated_at' => $now ) );
	$wpdb->insert( Arvator_Repo::table( 'licenses' ), array( 'user_id' => $u, 'license_key' => $key, 'plan' => 'monthly', 'expires_at' => $end, 'status' => 'active', 'is_test' => 1, 'created_at' => $now, 'updated_at' => $now ) );
};
$ins( $uA, $keyA . '</textarea><script>alert(2)</script>', 150000 );
$ins( $uB, $keyB, 150000 );

check( 1 === count( Arvator_Repo::orders_for_user( $uA ) ) && (int) Arvator_Repo::orders_for_user( $uA )[0]['user_id'] === $uA, 'Alice ne lit que ses achats' );
check( ! str_contains( wp_json_encode( Arvator_Repo::licenses_for_user( $uA ) ), $keyB ), 'Alice ne lit pas la licence de Bob' );
check( array() === Arvator_Repo::orders_for_user( 0 ) && array() === Arvator_Repo::licenses_for_user( -5 ), 'identifiant 0 ou négatif : aucune donnée' );
check( Arvator_Repo::is_entitled( $uA ) && ! Arvator_Repo::is_entitled( $uC ) && ! Arvator_Repo::is_entitled( 0 ), 'droit au téléchargement : Alice oui, Chloé non, visiteur non' );
$ev0 = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Arvator_Repo::table( 'events' ) . " WHERE type = 'noext_$tag'" );
Arvator_Repo::log_event( "noext_$tag", $uA );
Arvator_Repo::log_event( "noext_$tag", $uA );
check( (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Arvator_Repo::table( 'events' ) . " WHERE type = 'noext_$tag'" ) === $ev0 + 2, 'deux événements sans identifiant externe sont bien tous les deux enregistrés' );
check( true === Arvator_Repo::log_event( 'test', $uA, array(), "ext_$tag" ) && false === Arvator_Repo::log_event( 'test', $uA, array(), "ext_$tag" ), 'événement identique enregistré une seule fois (idempotence)' );

title( 'C2. Espace membre : visiteur' );
$v = new Browser( $url );
$mp = '/espace-membre/';
$page = $v->get( $mp );
check( str_contains( $page['body'], 'name="pwd"' ) && ! str_contains( $page['body'], 'Mes achats' ), 'visiteur : formulaire de connexion, aucune donnée' );
check( str_contains( $page['headers'], 'no-store' ), 'espace membre jamais mis en cache' );
$page = $v->get( $mp . '?section=licence' );
check( ! str_contains( $page['body'], $keyA ) && str_contains( $page['body'], 'name="pwd"' ), 'visiteur sur ?section=licence : rien d’affiché' );
$page = $v->get( $mp . '?arv_notice=%3Cscript%3Ealert(1)%3C/script%3E' );
check( ! str_contains( $page['body'], '<script>alert(1)' ), 'message injecté dans l’adresse : ignoré' );

title( 'C3. Connexion' );
$r = $v->post( '/wp-admin/admin-post.php', array( 'action' => 'arvator_login', '_arv_nonce' => 'faux', 'log' => "ta$tag", 'pwd' => $pwA ) );
check( 'login_invalid' === notice_of( $r ), 'connexion sans clé de sécurité valide refusée' );
$r = member_login( $v, "ta$tag", 'mauvais' );
check( 'login_failed' === notice_of( $r ), 'mauvais mot de passe refusé', notice_of( $r ) );
$A = new Browser( $url ); $B = new Browser( $url ); $C = new Browser( $url );
$r = member_login( $A, "ta$tag", $pwA );
check( 302 === $r['code'] && str_contains( $r['location'], 'espace-membre' ) && ! str_contains( $r['location'], 'wp-admin' ), 'Alice connectée, renvoyée vers l’espace membre', $r['location'] );
$rb = member_login( $B, "b$tag@example.com", $pwB );
check( 302 === $rb['code'] && '' === notice_of( $rb ), 'Bob se connecte avec son adresse email', $rb['location'] );
$rc = member_login( $C, "tc$tag", $pwC );
check( 302 === $rc['code'] && '' === notice_of( $rc ), 'Chloé connectée', $rc['location'] );

title( 'C4. Isolation sur le site' );
$pa = $A->get( $mp . '?section=licence' )['body'];
$pb = $B->get( $mp . '?section=licence' )['body'];
$pc = $C->get( $mp . '?section=licence' )['body'];
check( str_contains( $pa, 'KEY-ALICE-' . $tag ) && ! str_contains( $pa, $keyB ), 'Alice voit sa clé, pas celle de Bob' );
check( str_contains( $pb, $keyB ) && ! str_contains( $pb, 'KEY-ALICE' ), 'Bob voit sa clé, pas celle d’Alice' );
check( ! str_contains( $pc, 'KEY-' ) && str_contains( $pc, 'Aucune licence' ), 'Chloé (sans achat) ne voit aucune clé' );
check( ! str_contains( $pa, '<script>alert(2)' ) && str_contains( $pa, '&lt;/textarea&gt;' ), 'clé hostile : échappée dans la page' );
$dash = $A->get( $mp )['body'];
check( ! str_contains( $dash, '<script>alert(1)' ) && str_contains( $dash, 'Alice' ), 'nom hostile : échappé dans le tableau de bord' );
foreach ( array( 'achats', 'fichiers', 'profil', '../../etc', "'; DROP TABLE x;--" ) as $sec ) {
	$res = $A->get( $mp . '?section=' . rawurlencode( $sec ) );
	check( 200 === $res['code'] && ! preg_match( '/Warning:|Fatal error|syntax error/i', $res['body'] ) && ! str_contains( $res['body'], $keyB ), "section « $sec » : aucune erreur, aucune donnée d’un autre client" );
}
$res = $A->get( '/' );
check( ! str_contains( $res['body'], 'wpadminbar' ), 'client connecté : pas de barre d’administration' );

title( 'C5. Un client n’entre pas dans le tableau de bord' );
foreach ( array( '/wp-admin/', '/wp-admin/profile.php', '/wp-admin/admin.php?page=arvator', '/wp-admin/users.php', '/wp-admin/plugins.php' ) as $path ) {
	$r = $A->get( $path );
	check( 302 === $r['code'] && str_contains( $r['location'], 'espace-membre' ), "client sur $path : renvoyé vers l’espace membre", $r['code'] . ' ' . $r['location'] . ' ' . substr( trim( strip_tags( $r['body'] ) ), 0, 160 ) );
}
$r = $A->post( '/wp-admin/admin-post.php', array( 'action' => 'arvator_upload_file', '_arv_nonce' => 'x' ) );
check( 403 === $r['code'], 'un client ne peut pas téléverser de fichier (403)', (string) $r['code'] );
$r = $A->post( '/wp-admin/admin-post.php', array( 'action' => 'arvator_demo_create', '_arv_nonce' => 'x' ) );
check( 403 === $r['code'], 'un client ne peut pas créer de client de démonstration (403)', (string) $r['code'] );
$r = $A->get( '/?rest_route=/wp/v2/users' );
check( ! str_contains( $r['body'], "ta$tag" ), 'un client ne voit pas la liste des comptes (API)' );
$r = $A->get( '/?rest_route=/wp/v2/users/' . $uB );
check( ! str_contains( $r['body'], "tb$tag" ) && ! str_contains( $r['body'], 'Bob' ), 'un client ne voit pas le compte d’un autre client (API)' );

/* ======================================================================== D. Téléchargement */
title( 'D. Téléchargement du robot' );
Arvator_Downloads::ensure_private_dir();
$dir = Arvator_Downloads::private_dir();
$fid = bin2hex( random_bytes( 8 ) ); $stored = bin2hex( random_bytes( 16 ) );
file_put_contents( $dir . '/' . $stored, 'CONTENU-SECRET-DU-ROBOT-' . $tag );
$files = Arvator_Downloads::files();
$files[ $fid ] = array( 'label' => 'Robot Arvator', 'stored' => $stored, 'original' => 'Arvator.algo', 'size' => 40, 'added' => $now );
update_option( 'arvator_private_files', $files, false );
check( is_file( $dir . '/index.php' ) && is_file( $dir . '/.htaccess' ), 'dossier privé verrouillé (index.php, .htaccess)' );
echo '  info  dossier privé : ' . $dir . "\n";

$files_page = $A->get( $mp . '?section=fichiers' )['body'];
preg_match( '#href="([^"]*action=arvator_download[^"]*)"#', $files_page, $mm );
$link = isset( $mm[1] ) ? html_entity_decode( $mm[1] ) : '';
check( '' !== $link, 'Alice (acheteuse) voit le lien de téléchargement' );
$r = $A->get( $link );
check( 200 === $r['code'] && str_contains( $r['body'], 'CONTENU-SECRET-DU-ROBOT-' . $tag ), 'Alice télécharge le robot' );
check( str_contains( $r['headers'], 'attachment' ) && str_contains( strtolower( $r['headers'] ), 'x-content-type-options: nosniff' ) && str_contains( strtolower( $r['headers'] ), 'no-store' ), 'en-têtes de téléchargement sûrs' );
check( ! str_contains( $link, $stored ), 'le nom réel du fichier sur le disque n’apparaît jamais dans le lien' );
$r = $A->get( preg_replace( '/_wpnonce=[a-f0-9]+/', '_wpnonce=0000000000', $link ) );
check( 403 === $r['code'] && ! str_contains( $r['body'], 'CONTENU-SECRET' ), 'lien avec fausse clé de sécurité refusé (403)' );
$r = $B->get( $link );
check( 403 === $r['code'] && ! str_contains( $r['body'], 'CONTENU-SECRET' ), 'le lien d’Alice ne fonctionne pas pour Bob (403)' );
$r = $v->get( $link );
check( 302 === $r['code'] && ! str_contains( $r['body'], 'CONTENU-SECRET' ), 'visiteur : renvoyé vers la connexion, aucun fichier' );
foreach ( array( '../../wp-config', '..%2F..%2Fetc%2Fpasswd', 'abc' ) as $bad ) {
	$r = $A->get( '/wp-admin/admin-post.php?action=arvator_download&f=' . $bad . '&_wpnonce=1' );
	check( in_array( $r['code'], array( 404, 403 ), true ) && ! str_contains( $r['body'], 'CONTENU-SECRET' ), "identifiant « $bad » refusé" );
}
// Chloé : lien valide pour sa session, mais aucun achat -> 403. On lui donne un achat le temps de récupérer le lien, puis on le retire.
$wpdb->insert( Arvator_Repo::table( 'orders' ), array( 'user_id' => $uC, 'stripe_session_id' => "cs_c_$tag", 'product' => 'cbot', 'amount_cents' => 1, 'status' => 'paid', 'is_test' => 1, 'created_at' => $now ) );
preg_match( '#href="([^"]*action=arvator_download[^"]*)"#', $C->get( $mp . '?section=fichiers' )['body'], $mc );
$wpdb->delete( Arvator_Repo::table( 'orders' ), array( 'user_id' => $uC ) );
$r = $C->get( html_entity_decode( $mc[1] ?? '/' ) );
check( ! empty( $mc[1] ) && 403 === $r['code'] && ! str_contains( $r['body'], 'CONTENU-SECRET' ), 'achat retiré : le téléchargement est aussitôt refusé (403)' );
check( (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Arvator_Repo::table( 'events' ) . " WHERE type = 'download' AND user_id = %d", $uA ) ) >= 1, 'le téléchargement d’Alice est journalisé' );

/* ======================================================================== E. Compte */
title( 'E. Mot de passe et données personnelles' );
$prof = $A->get( $mp . '?section=profil' )['body'];
$pn   = field( $prof, '_arv_nonce' );
$pwd  = static fn( string $c, string $n, string $n2, string $nonce ) => array( 'action' => 'arvator_password', '_arv_nonce' => $nonce, 'current' => $c, 'new' => $n, 'new2' => $n2 );
check( 'generic_invalid' === notice_of( $A->post( '/wp-admin/admin-post.php', $pwd( $pwA, 'Nouveau-mot-de-passe-1', 'Nouveau-mot-de-passe-1', 'faux' ) ) ), 'changement sans clé de sécurité valide refusé' );
check( 'pwd_wrong' === notice_of( $A->post( '/wp-admin/admin-post.php', $pwd( 'faux', 'Nouveau-mot-de-passe-1', 'Nouveau-mot-de-passe-1', $pn ) ) ), 'mot de passe actuel faux refusé' );
check( 'pwd_mismatch' === notice_of( $A->post( '/wp-admin/admin-post.php', $pwd( $pwA, 'Nouveau-mot-de-passe-1', 'Autre-mot-de-passe-12', $pn ) ) ), 'deux nouveaux mots de passe différents refusés' );
check( 'pwd_weak' === notice_of( $A->post( '/wp-admin/admin-post.php', $pwd( $pwA, 'court', 'court', $pn ) ) ), 'mot de passe trop court refusé' );
check( 'pwd_weak' === notice_of( $A->post( '/wp-admin/admin-post.php', $pwd( $pwA, $pwA, $pwA, $pn ) ) ), 'même mot de passe refusé' );
$newA = 'Nouveau-mot-de-passe-A-' . $tag;
check( 'pwd_ok' === notice_of( $A->post( '/wp-admin/admin-post.php', $pwd( $pwA, $newA, $newA, $pn ) ) ), 'changement de mot de passe réussi' );
check( str_contains( $A->get( $mp )['body'], 'Mon abonnement' ), 'la session reste ouverte après le changement' );
$X = new Browser( $url );
check( 'login_failed' === notice_of( member_login( $X, "ta$tag", $pwA ) ), 'l’ancien mot de passe ne fonctionne plus' );
clear_transients();
$X = new Browser( $url );
$rx = member_login( $X, "ta$tag", $newA );
check( 302 === $rx['code'] && '' === notice_of( $rx ), 'le nouveau mot de passe fonctionne', $rx['location'] );
$pn = field( $A->get( $mp . '?section=profil' )['body'], '_arv_nonce' );
for ( $i = 0; $i < 5; $i++ ) {
	$A->post( '/wp-admin/admin-post.php', $pwd( 'faux', 'Nouveau-mot-de-passe-1', 'Nouveau-mot-de-passe-1', $pn ) );
}
check( 'pwd_locked' === notice_of( $A->post( '/wp-admin/admin-post.php', $pwd( $newA, 'Encore-un-autre-mdp-1', 'Encore-un-autre-mdp-1', $pn ) ) ), '5 erreurs de mot de passe actuel : changement bloqué 15 minutes' );
clear_transients();

$privacy = static fn( string $kind, string $nonce ) => array( 'action' => 'arvator_privacy', '_arv_nonce' => $nonce, 'kind' => $kind );
$pn = field_in_form( $B->get( $mp . '?section=profil' )['body'], 'arvator_privacy', '_arv_nonce' );
check( 'generic_invalid' === notice_of( $B->post( '/wp-admin/admin-post.php', $privacy( 'export', 'faux' ) ) ), 'demande RGPD sans clé de sécurité refusée' );
check( 'generic_invalid' === notice_of( $B->post( '/wp-admin/admin-post.php', $privacy( 'hack', $pn ) ) ), 'type de demande inconnu refusé' );
$res = $B->post( '/wp-admin/admin-post.php', $privacy( 'export', $pn ) );
check( 'privacy_export' === notice_of( $res ), 'Bob demande l’export de ses données', notice_of( $res ) );
$cnt = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'user_request' AND post_title = %s", "b$tag@example.com" ) );
check( 1 === $cnt, 'la demande est créée pour l’adresse du compte connecté' );
check( 'privacy_limit' === notice_of( $B->post( '/wp-admin/admin-post.php', $privacy( 'export', $pn ) ) ), 'une seule demande par jour' );

title( 'E2. Export et effacement (RGPD)' );
$exp = Arvator_Privacy::export( "a$tag@example.com" );
$js  = wp_json_encode( $exp );
check( count( $exp['data'] ) >= 3 && str_contains( $js, 'KEY-ALICE' ) && ! str_contains( $js, 'KEY-BOB' ), 'export : données d’Alice uniquement' );
check( array() === Arvator_Privacy::export( 'inconnu@example.com' )['data'], 'export pour une adresse inconnue : vide' );
$er = Arvator_Privacy::erase( "b$tag@example.com" );
check( $er['items_removed'] && $er['items_retained'], 'effacement : pièces comptables conservées, le reste supprimé' );
check( 0 === count( Arvator_Repo::licenses_for_user( $uB ) ) && 0 === count( Arvator_Repo::orders_for_user( $uB ) ), 'après effacement : plus aucune donnée liée à Bob' );
check( 1 === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Arvator_Repo::table( 'orders' ) . ' WHERE user_id = 0 AND stripe_session_id = ' . "'cs_{$uB}_$tag'" ), 'la pièce comptable de Bob subsiste, sans lien avec lui' );

/* ======================================================================== F. Connexions limitées */
title( 'F. Limite des connexions' );
$L = new Browser( $url );
$last = '';
for ( $i = 0; $i < 7; $i++ ) {
	$last = notice_of( member_login( $L, "tc$tag", 'faux' . $i ) );
}
check( 'login_locked' === $last, '5 échecs : l’espace membre bloque la connexion' );
check( 'login_locked' === notice_of( member_login( $L, "tc$tag", $pwC ) ), 'même avec le bon mot de passe pendant le blocage' );
clear_transients();
$r = $anon->req( 'POST', '/xmlrpc.php', array(), array( 'Content-Type: text/xml' ) );
check( 403 === $r['code'], 'XML-RPC bloqué (403)', (string) $r['code'] );

/* ======================================================================== G. Administration */
title( 'G. Administration (administrateur)' );
$adm = new Browser( $url );
$login = $adm->post( '/wp-login.php', array( 'log' => 'admin', 'pwd' => 'Test-Only-12345', 'wp-submit' => 'Log In', 'testcookie' => '1' ) );
check( 302 === $login['code'], 'administrateur connecté (test)', (string) $login['code'] );
$page = $adm->get( '/wp-admin/admin.php?page=arvator' );
check( 200 === $page['code'] && str_contains( $page['body'], 'Fichiers privés' ), 'administrateur : page Fichiers privés accessible' );
$demo = $adm->get( '/wp-admin/admin.php?page=arvator-demo' );
$dn   = field_in_form( $demo['body'], 'arvator_demo_create', '_arv_nonce' );
$r    = $adm->post( '/wp-admin/admin-post.php', array( 'action' => 'arvator_demo_create', '_arv_nonce' => 'faux' ) );
check( 403 === $r['code'], 'création de démonstration sans clé de sécurité valide refusée (403)' );
$r    = $adm->post( '/wp-admin/admin-post.php', array( 'action' => 'arvator_demo_create', '_arv_nonce' => $dn ) );
check( 302 === $r['code'], 'client de démonstration créé' );
$demos = get_users( array( 'meta_key' => 'arvator_demo', 'meta_value' => '1', 'fields' => 'ID' ) );
check( 1 === count( $demos ) && 1 === count( Arvator_Repo::licenses_for_user( (int) $demos[0] ) ), 'le client de démonstration a ses données' );
$count_demos = static fn(): int => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'arvator_demo'" );
$demo = $adm->get( '/wp-admin/admin.php?page=arvator-demo' );
$dn   = field_in_form( $demo['body'], 'arvator_demo_delete', '_arv_nonce' );
$rd = $adm->post( '/wp-admin/admin-post.php', array( 'action' => 'arvator_demo_delete', '_arv_nonce' => $dn ) );
check( 0 === $count_demos(), 'clients de démonstration supprimés', $rd['code'] . ' ' . $rd['location'] . ' ' . substr( trim( strip_tags( $rd['body'] ) ), 0, 200 ) );
check( 0 === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Arvator_Repo::table( 'licenses' ) . " WHERE license_key LIKE 'CLÉ-DE-DÉMONSTRATION%'" ), 'leurs fausses données aussi' );
$r = $adm->post( '/wp-admin/admin-post.php', array( 'action' => 'arvator_upload_file', '_arv_nonce' => 'faux' ) );
check( in_array( $r['code'], array( 403, 302 ), true ) && ! str_contains( $r['body'], 'Fichier mis en ligne' ), 'téléversement sans clé de sécurité valide refusé' );


/* ======================================================================== H. Paiement Stripe (fausse API) */
title( 'H. Paiement Stripe (bac à sable simulé)' );
$stripe_log = (string) getenv( 'ARV_STRIPE_LOG' );
$stripe_fix = (string) getenv( 'ARV_STRIPE_FIXTURES' );
@unlink( $stripe_log );
$set_fix  = static function ( array $fix ) use ( $stripe_fix ): void {
	file_put_contents( $stripe_fix, wp_json_encode( $fix ) );
};
$stripe_calls = static function () use ( $stripe_log ): array {
	return is_file( $stripe_log ) ? array_map( static fn( $l ) => json_decode( $l, true ), file( $stripe_log, FILE_IGNORE_NEW_LINES ) ) : array();
};
$sign = static function ( string $payload, ?int $ts = null ): string {
	$ts = $ts ?? time();
	return 't=' . $ts . ',v1=' . hash_hmac( 'sha256', $ts . '.' . $payload, 'whsec_test_fake' );
};
// Envoi du corps brut (JSON) : curl direct, car Browser::req envoie un formulaire.
$post_raw = static function ( string $payload, string $sig ) use ( $url ): array {
	$ch = curl_init( $url . '/wp-json/arvator/v1/stripe' );
	curl_setopt_array( $ch, array( CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_HTTPHEADER => array( 'Content-Type: application/json', 'Stripe-Signature: ' . $sig ), CURLOPT_TIMEOUT => 60 ) );
	$body = (string) curl_exec( $ch );
	$code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
	curl_close( $ch );
	return array( 'code' => $code, 'json' => json_decode( $body, true ), 'body' => $body );
};

// -- Unités : signature et encodage
$p = '{"id":"evt_1"}';
check( Arvator_Stripe::verify_signature( $p, $sign( $p ), 'whsec_test_fake' ), 'signature valide acceptée' );
check( ! Arvator_Stripe::verify_signature( $p, $sign( $p ), 'whsec_autre' ), 'signature avec un autre secret refusée' );
check( ! Arvator_Stripe::verify_signature( $p . ' ', $sign( $p ), 'whsec_test_fake' ), 'corps modifié refusé' );
check( ! Arvator_Stripe::verify_signature( $p, $sign( $p, time() - 3600 ), 'whsec_test_fake' ), 'message vieux d\'une heure refusé (rejeu)' );
check( ! Arvator_Stripe::verify_signature( $p, '', 'whsec_test_fake' ) && ! Arvator_Stripe::verify_signature( $p, 'n\'importe quoi', 'whsec_test_fake' ), 'en-tête absent ou illisible refusé' );
check( 'a%5Bb%5D%5B0%5D=c&d=true' === Arvator_Stripe::encode( array( 'a' => array( 'b' => array( 'c' ) ), 'd' => true ) ), 'encodage des paramètres imbriqués' );

// -- Page d'achat
$buy = $anon->get( '/acheter/' );
check( 200 === $buy['code'] && str_contains( $buy['body'], 'value="annual" checked' ) && str_contains( $buy['body'], 'name="cgv"' ) && str_contains( $buy['body'], 'name="risks"' ), 'page d\'achat : formule annuelle par défaut, deux cases obligatoires' );
check( ! preg_match( '/card|carte bancaire.*<input|cvc/i', preg_replace( '/Paiement sécurisé.*?site\./su', '', $buy['body'] ) ), 'page d\'achat : aucun champ de carte bancaire' );
check( str_contains( $buy['headers'], 'no-store' ), 'page d\'achat jamais mise en cache' );
$nonce = field_in_form( $buy['body'], 'arvator_checkout', '_arv_nonce' );
$buyer = "acheteur_$tag@example.org";
$good  = array( 'action' => 'arvator_checkout', '_arv_nonce' => $nonce, 'plan' => 'annual', 'email' => $buyer, 'cgv' => '1', 'risks' => '1' );

$set_fix( array( 'POST checkout/sessions' => array( 'id' => 'cs_test_x', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_x' ) ) );
$r = $anon->post( '/wp-admin/admin-post.php', array( '_arv_nonce' => 'faux' ) + $good );
check( 'generic_invalid' === notice_of( $r ), 'achat sans clé de sécurité valide refusé' );
$r = $anon->post( '/wp-admin/admin-post.php', array( 'cgv' => '' ) + $good );
check( 'achat_invalid' === notice_of( $r ), 'achat sans case CGV refusé' );
$r = $anon->post( '/wp-admin/admin-post.php', array( 'risks' => '' ) + $good );
check( 'achat_invalid' === notice_of( $r ), 'achat sans case risques refusé' );
$r = $anon->post( '/wp-admin/admin-post.php', array( 'plan' => 'gratuit' ) + $good );
check( 'achat_invalid' === notice_of( $r ), 'formule inconnue refusée' );
$r = $anon->post( '/wp-admin/admin-post.php', array( 'email' => 'pas-un-email' ) + $good );
check( 'achat_invalid' === notice_of( $r ), 'email invalide refusé' );
check( 0 === count( $stripe_calls() ), 'aucun appel à Stripe pour les demandes refusées' );

$r = $anon->post( '/wp-admin/admin-post.php', $good );
check( 303 === $r['code'] && 'https://checkout.stripe.com/c/pay/cs_test_x' === $r['location'], 'achat valide : départ vers la page de paiement Stripe', $r['code'] . ' ' . $r['location'] );
$calls = $stripe_calls();
$body  = array();
parse_str( (string) ( $calls[0]['body'] ?? '' ), $body );
check( 'POST checkout/sessions' === ( $calls[0]['key'] ?? '' ) && 'Bearer rk_test_fake' === ( $calls[0]['auth'] ?? '' ), 'appel Checkout avec la clé du wp-config' );
check( 'subscription' === ( $body['mode'] ?? '' ) && 'price_annual_test' === ( $body['line_items'][0]['price'] ?? '' ) && 'price_bot_test' === ( $body['line_items'][1]['price'] ?? '' ), 'robot + abonnement annuel dans le même paiement' );
check( $buyer === ( $body['customer_email'] ?? '' ) && str_contains( (string) ( $body['success_url'] ?? '' ), 'arv_notice=achat_ok' ) && str_contains( (string) ( $body['cancel_url'] ?? '' ), '/acheter/' ), 'email et adresses de retour fixées par le site' );

$r2 = $anon->post( '/wp-admin/admin-post.php', array( 'plan' => 'monthly' ) + $good );
$body = array();
parse_str( (string) ( $stripe_calls()[1]['body'] ?? '' ), $body );
check( 'price_monthly_test' === ( $body['line_items'][0]['price'] ?? '' ), 'formule mensuelle : le bon prix est envoyé' );

$set_fix( array( 'POST checkout/sessions' => array( 'id' => 'cs_x', 'url' => 'https://evil.example.com/pay' ) ) );
$r = $anon->post( '/wp-admin/admin-post.php', $good );
check( 'achat_erreur' === notice_of( $r ), 'adresse de paiement hors Stripe refusée (pas de redirection)' );
$set_fix( array() );
$r = $anon->post( '/wp-admin/admin-post.php', $good );
check( 'achat_erreur' === notice_of( $r ), 'Stripe en panne : message clair, pas d\'erreur technique affichée' );

// -- Webhook : sécurité
$sess_id = "cs_test_$tag";
$event   = static function ( string $id, string $type, array $object, bool $live = false ): string {
	return (string) wp_json_encode( array( 'id' => $id, 'type' => $type, 'livemode' => $live, 'data' => array( 'object' => $object ) ) );
};
$ev1 = $event( "evt_{$tag}_1", 'checkout.session.completed', array( 'id' => $sess_id, 'payment_status' => 'paid' ) );
$r   = $post_raw( $ev1, 't=1,v1=abc' );
check( 400 === $r['code'], 'webhook : signature fausse → 400' );
$r = $post_raw( $ev1, $sign( $ev1, time() - 3600 ) );
check( 400 === $r['code'], 'webhook : message trop ancien → 400' );
check( 0 === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Arvator_Repo::table( 'orders' ) . " WHERE stripe_session_id = '$sess_id'" ), 'rien n\'est enregistré avec une mauvaise signature' );

// -- Webhook : achat complet (client inconnu → compte créé)
$full = array(
	'id' => $sess_id, 'payment_status' => 'paid', 'customer' => "cus_$tag", 'customer_email' => $buyer,
	'customer_details' => array( 'email' => $buyer ), 'invoice' => "in_$tag", 'payment_intent' => null, 'currency' => 'eur',
	'line_items' => array( 'data' => array(
		array( 'price' => array( 'id' => 'price_annual_test' ), 'amount_total' => 35880 ),
		array( 'price' => array( 'id' => 'price_bot_test' ), 'amount_total' => 150000 ),
	) ),
	'subscription' => array(
		'id' => "sub_$tag", 'status' => 'active', 'cancel_at_period_end' => false,
		'items' => array( 'data' => array( array( 'price' => array( 'id' => 'price_annual_test' ), 'current_period_end' => 1900000000 ) ) ),
	),
);
$set_fix( array( "GET checkout/sessions/$sess_id" => $full ) );
@unlink( $log );
$r = $post_raw( $ev1, $sign( $ev1 ) );
check( 200 === $r['code'] && 'ok' === ( $r['json']['status'] ?? '' ), 'webhook : achat accepté', $r['code'] . ' ' . $r['body'] );
$buyer_user = get_user_by( 'email', $buyer );
check( $buyer_user instanceof WP_User && in_array( Arvator_Roles::CLIENT, $buyer_user->roles, true ) && ! user_can( $buyer_user, 'edit_posts' ), 'compte créé avec le rôle client, sans droit d\'administration' );
$order = $buyer_user ? $wpdb->get_row( 'SELECT * FROM ' . Arvator_Repo::table( 'orders' ) . " WHERE stripe_session_id = '$sess_id'", ARRAY_A ) : null;
check( is_array( $order ) && 'paid' === $order['status'] && 150000 === (int) $order['amount_cents'] && 'cbot' === $order['product'] && 1 === (int) $order['is_test'] && (int) $order['user_id'] === (int) $buyer_user->ID, 'commande : robot 1 500 €, payée, marquée « test », au bon compte' );
$sub = $buyer_user ? $wpdb->get_row( 'SELECT * FROM ' . Arvator_Repo::table( 'subscriptions' ) . " WHERE stripe_subscription_id = 'sub_$tag'", ARRAY_A ) : null;
check( is_array( $sub ) && 'annual' === $sub['plan'] && 'active' === $sub['status'] && 0 === (int) $sub['cancel_at_period_end'] && str_starts_with( (string) $sub['current_period_end'], '2030-03-17' ), 'abonnement annuel enregistré avec sa date de fin' );
$m = mails();
check( 1 === count( $m ) && $buyer === $m[0]->to && str_contains( $m[0]->message, 'action=rp' ) && str_contains( $m[0]->message, 'performances simulées' ), 'email de confirmation avec lien pour choisir le mot de passe' );
check( Arvator_Repo::is_entitled( (int) $buyer_user->ID ), 'le client peut télécharger le robot' );

// -- Idempotence : même événement, renvoyé plusieurs fois
$again = $post_raw( $ev1, $sign( $ev1 ) );
$third = $post_raw( $ev1, $sign( $ev1 ) );
check( 200 === $again['code'] && 'duplicate' === ( $again['json']['status'] ?? '' ) && 'duplicate' === ( $third['json']['status'] ?? '' ), 'même événement renvoyé : reconnu, rien refait' );
check( 1 === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Arvator_Repo::table( 'orders' ) . " WHERE stripe_session_id = '$sess_id'" ) && 1 === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Arvator_Repo::table( 'subscriptions' ) . " WHERE stripe_subscription_id = 'sub_$tag'" ) && 1 === count( mails() ), 'toujours 1 commande, 1 abonnement, 1 email' );
// Un autre événement pour la MÊME session (cas réel : checkout.session.async_payment_succeeded, ou renvoi sous un autre id).
$ev1b = $event( "evt_{$tag}_1b", 'checkout.session.completed', array( 'id' => $sess_id, 'payment_status' => 'paid' ) );
$post_raw( $ev1b, $sign( $ev1b ) );
check( 1 === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Arvator_Repo::table( 'orders' ) . " WHERE stripe_session_id = '$sess_id'" ), 'même session sous un autre identifiant d\'événement : toujours 1 seule commande' );

// -- Paiement non encaissé
$ev2 = $event( "evt_{$tag}_2", 'checkout.session.completed', array( 'id' => "cs_unpaid_$tag", 'payment_status' => 'unpaid' ) );
$r   = $post_raw( $ev2, $sign( $ev2 ) );
check( 200 === $r['code'] && 0 === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Arvator_Repo::table( 'orders' ) . " WHERE stripe_session_id = 'cs_unpaid_$tag'" ), 'paiement non encaissé : aucune commande' );

// -- Type d'événement non géré
$ev3 = $event( "evt_{$tag}_3", 'customer.created', array( 'id' => 'cus_x' ) );
$r   = $post_raw( $ev3, $sign( $ev3 ) );
check( 200 === $r['code'] && 'ignored' === ( $r['json']['status'] ?? '' ), 'événement non géré : ignoré proprement' );

// -- Erreur pendant le traitement : l'événement est relâché, Stripe peut le renvoyer
$sess2 = "cs_test2_$tag";
$ev4   = $event( "evt_{$tag}_4", 'checkout.session.completed', array( 'id' => $sess2, 'payment_status' => 'paid' ) );
$set_fix( array() );
$r = $post_raw( $ev4, $sign( $ev4 ) );
check( 500 === $r['code'], 'Stripe injoignable pendant le traitement → 500 (Stripe renverra)' );
$set_fix( array( "GET checkout/sessions/$sess2" => array_merge( $full, array( 'id' => $sess2, 'invoice' => "in2_$tag", 'subscription' => array_merge( $full['subscription'], array( 'id' => "sub2_$tag" ) ) ) ) ) );
$r = $post_raw( $ev4, $sign( $ev4 ) );
check( 200 === $r['code'] && 'ok' === ( $r['json']['status'] ?? '' ), 'renvoi après panne : traité normalement' );
check( 2 === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Arvator_Repo::table( 'orders' ) . " WHERE user_id = {$buyer_user->ID}" ) && 1 === count( array_filter( mails(), static fn( $x ) => str_contains( $x->message, 'action=rp' ) ) ), 'achat rattaché au compte existant (par email), pas de 2e compte ni de 2e lien de mot de passe' );
$wpdb->query( 'DELETE FROM ' . Arvator_Repo::table( 'orders' ) . " WHERE stripe_session_id = '$sess2'" );
$wpdb->query( 'DELETE FROM ' . Arvator_Repo::table( 'subscriptions' ) . " WHERE stripe_subscription_id = 'sub2_$tag'" );

// -- Abonnement : changement et résiliation
$evu = $event( "evt_{$tag}_5", 'customer.subscription.updated', array( 'id' => "sub_$tag", 'status' => 'past_due', 'cancel_at_period_end' => true, 'items' => array( 'data' => array( array( 'current_period_end' => 1910000000 ) ) ) ) );
$post_raw( $evu, $sign( $evu ) );
$sub = $wpdb->get_row( 'SELECT * FROM ' . Arvator_Repo::table( 'subscriptions' ) . " WHERE stripe_subscription_id = 'sub_$tag'", ARRAY_A );
check( 'past_due' === $sub['status'] && 1 === (int) $sub['cancel_at_period_end'] && str_starts_with( (string) $sub['current_period_end'], '2030-07-' ), 'abonnement mis à jour (paiement en retard, résiliation à l\'échéance, nouvelle date)' );
$evd = $event( "evt_{$tag}_6", 'customer.subscription.deleted', array( 'id' => "sub_$tag", 'status' => 'canceled', 'items' => array( 'data' => array( array( 'current_period_end' => 1910000000 ) ) ) ) );
$post_raw( $evd, $sign( $evd ) );
check( 'canceled' === $wpdb->get_var( 'SELECT status FROM ' . Arvator_Repo::table( 'subscriptions' ) . " WHERE stripe_subscription_id = 'sub_$tag'" ), 'abonnement résilié enregistré' );
$evx = $event( "evt_{$tag}_7", 'customer.subscription.updated', array( 'id' => 'sub_inconnu', 'status' => 'active' ) );
$r   = $post_raw( $evx, $sign( $evx ) );
check( 200 === $r['code'], 'mise à jour d\'un abonnement encore inconnu : pas d\'erreur' );

// -- Remboursement
$wpdb->update( Arvator_Repo::table( 'subscriptions' ), array( 'status' => 'active' ), array( 'stripe_subscription_id' => "sub_$tag" ) );
$wpdb->insert( Arvator_Repo::table( 'licenses' ), array( 'user_id' => $buyer_user->ID, 'license_key' => "TEST-$tag", 'plan' => 'annual', 'expires_at' => '2030-01-01 00:00:00', 'status' => 'active', 'is_test' => 1, 'created_at' => $now, 'updated_at' => $now ) );
$set_fix( array( "DELETE subscriptions/sub_$tag" => array( 'id' => "sub_$tag", 'status' => 'canceled' ) ) );
$evr = $event( "evt_{$tag}_8", 'charge.refunded', array( 'id' => "ch_$tag", 'customer' => "cus_$tag", 'invoice' => "in_$tag", 'amount' => 185880, 'amount_refunded' => 5000 ) );
$post_raw( $evr, $sign( $evr ) );
check( 'paid' === $wpdb->get_var( 'SELECT status FROM ' . Arvator_Repo::table( 'orders' ) . " WHERE stripe_session_id = '$sess_id'" ), 'remboursement partiel (moins que le robot) : l\'achat reste valide' );
$evr2 = $event( "evt_{$tag}_9", 'charge.refunded', array( 'id' => "ch_$tag", 'customer' => "cus_$tag", 'invoice' => "in_$tag", 'amount' => 185880, 'amount_refunded' => 150000 ) );
$r    = $post_raw( $evr2, $sign( $evr2 ) );
check( 200 === $r['code'], 'remboursement du prix du robot : message accepté' );
check( 'refunded' === $wpdb->get_var( 'SELECT status FROM ' . Arvator_Repo::table( 'orders' ) . " WHERE stripe_session_id = '$sess_id'" ), 'achat marqué « remboursé »' );
check( 'revoked' === $wpdb->get_var( 'SELECT status FROM ' . Arvator_Repo::table( 'licenses' ) . " WHERE license_key = 'TEST-$tag'" ), 'la licence prend fin' );
check( 'canceled' === $wpdb->get_var( 'SELECT status FROM ' . Arvator_Repo::table( 'subscriptions' ) . " WHERE stripe_subscription_id = 'sub_$tag'" ) && in_array( "DELETE subscriptions/sub_$tag", array_column( $stripe_calls(), 'key' ), true ), 'l\'abonnement est arrêté chez Stripe' );
check( ! Arvator_Repo::is_entitled( (int) $buyer_user->ID ) || 1 === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Arvator_Repo::table( 'orders' ) . " WHERE user_id = {$buyer_user->ID} AND status = 'paid'" ), 'après remboursement : plus de droit de téléchargement' );
$post_raw( $evr2, $sign( $evr2 ) );
check( 1 === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Arvator_Repo::table( 'events' ) . " WHERE type = 'refund' AND user_id = {$buyer_user->ID}" ), 'remboursement renvoyé : traité une seule fois' );

// -- Portail client
$pc = new Browser( $url );
$wpdb->update( Arvator_Repo::table( 'subscriptions' ), array( 'status' => 'active' ), array( 'stripe_subscription_id' => "sub_$tag" ) );
$pwd = 'Test-Only-Portail-12345';
wp_set_password( $pwd, (int) $buyer_user->ID );
member_login( $pc, $buyer, $pwd );
$ach = $pc->get( '/espace-membre/?section=achats' );
check( str_contains( $ach['body'], 'name="action" value="arvator_portal"' ) && str_contains( $ach['body'], 'fin de la période déjà payée' ), 'espace membre : bouton du portail et rappel de la règle de résiliation' );
$pn = field_in_form( $ach['body'], 'arvator_portal', '_arv_nonce' );
$r  = $pc->post( '/wp-admin/admin-post.php', array( 'action' => 'arvator_portal', '_arv_nonce' => 'faux' ) );
check( 'generic_invalid' === notice_of( $r ), 'portail sans clé de sécurité valide refusé' );
$set_fix( array( 'POST billing_portal/sessions' => array( 'url' => 'https://billing.stripe.com/p/session/test_x' ) ) );
@unlink( $stripe_log );
$r = $pc->post( '/wp-admin/admin-post.php', array( 'action' => 'arvator_portal', '_arv_nonce' => $pn ) );
check( 303 === $r['code'] && 'https://billing.stripe.com/p/session/test_x' === $r['location'], 'portail : départ vers Stripe' );
$pb = array();
parse_str( (string) ( $stripe_calls()[0]['body'] ?? '' ), $pb );
check( "cus_$tag" === ( $pb['customer'] ?? '' ), 'le portail est ouvert pour le client de ce compte, jamais un autre' );
$r = $anon->post( '/wp-admin/admin-post.php', array( 'action' => 'arvator_portal', '_arv_nonce' => $pn ) );
check( ! str_contains( $r['location'], 'stripe.com' ) && ! str_contains( $r['body'], 'billing.stripe.com' ), 'portail sans être connecté : refusé', (string) $r['code'] );
// Un autre client (sans abonnement Stripe) ne peut pas ouvrir le portail de celui-ci.
$other = new Browser( $url );
member_login( $other, "ta$tag", $pwA );
$ao = $other->get( '/espace-membre/?section=achats' );
check( ! str_contains( $ao['body'], 'arvator_portal' ), 'un client sans abonnement Stripe ne voit pas le bouton du portail' );

// -- Nettoyage du bloc H
$wpdb->query( 'DELETE FROM ' . Arvator_Repo::table( 'events' ) . " WHERE external_id LIKE 'stripe:evt\\_{$tag}%'" );
Arvator_Repo::delete_test_rows_for_user( (int) $buyer_user->ID );
wp_delete_user( (int) $buyer_user->ID );
@unlink( $stripe_log );
@unlink( $stripe_fix );

/* ======================================================================== Nettoyage */
foreach ( array( $uA, $uB, $uC ) as $uid ) {
	Arvator_Repo::delete_test_rows_for_user( $uid );
	wp_delete_user( $uid );
}
$wpdb->query( 'DELETE FROM ' . Arvator_Repo::table( 'orders' ) . " WHERE stripe_session_id LIKE '%$tag%'" );
$wpdb->query( 'DELETE FROM ' . Arvator_Repo::table( 'events' ) . " WHERE external_id = 'ext_$tag' OR type = 'noext_$tag'" );
@unlink( $dir . '/' . $stored );
$files = Arvator_Downloads::files(); unset( $files[ $fid ] ); update_option( 'arvator_private_files', $files, false );
$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_type = 'user_request'" );
clear_transients();

echo "\n--------------------------------------------\n";
printf( "Résultat : %d réussis, %d échecs\n", $GLOBALS['ok'], $GLOBALS['fail'] );
exit( $GLOBALS['fail'] > 0 ? 1 : 0 );

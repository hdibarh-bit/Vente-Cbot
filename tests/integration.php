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
	/** Cookies supplémentaires envoyés à chaque requête (ex. choix sur les cookies). */
	public string $cookie = '';
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
		curl_setopt( $ch, CURLOPT_COOKIE, 'wordpress_test_cookie=WP%20Cookie%20check' . ( '' !== $this->cookie ? '; ' . $this->cookie : '' ) );
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
foreach ( array( 'a-propos', 'faq', 'contact', 'risques', 'mentions-legales', 'cgv', 'confidentialite', 'cookies', 'essai-gratuit', 'acheter', 'espace-membre' ) as $arv_slug ) {
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
$slugs = array( 'a-propos', 'faq', 'contact', 'risques', 'mentions-legales', 'cgv', 'confidentialite', 'cookies', 'essai-gratuit', 'acheter', 'espace-membre' );
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
check( str_contains( $cgv['body'], '14 jours' ) && ! str_contains( $cgv['body'], 'notice-provisional' ), 'CGV : règle des 14 jours, sans bandeau « provisoire »' );
check( str_contains( $cgv['body'], 'Hamid DIBARH, entrepreneur individuel exerçant sous le nom commercial COSMARTIS, 21 rue Taffin, 59970 Fresnes-sur-Escaut, France, SIRET 802 301 317 00029' ), 'CGV article 1 : identification complète du vendeur' );
$ml = $anon->get( '/mentions-legales/' );
check( str_contains( $ml['body'], '802 301 317 00029' ) && str_contains( $ml['body'], 'support@arvator.com' ), 'Mentions légales : SIRET et email' );
check( str_contains( $ml['body'], 'entrepreneur individuel exerçant sous le nom commercial COSMARTIS' ) && str_contains( $ml['body'], '21 rue Taffin, 59970 Fresnes-sur-Escaut, France' ), 'Mentions légales : nom commercial COSMARTIS et adresse complète' );
foreach ( array( 'risques', 'mentions-legales', 'cgv', 'confidentialite', 'cookies' ) as $pg ) {
	check( ! preg_match( '/notice-provisional|Version provisoire/u', $anon->get( '/' . $pg . '/' )['body'] ), "page « $pg » : aucun bandeau « provisoire »" );
}
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
check( $last && 'support@arvator.com' === $last->to, 'destinataire : support@arvator.com' );
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


/* ======================================================================== I. Essai gratuit et boutons */
title( 'I. Essai gratuit, boutons, rappel de renonciation' );
// Les essais précédents ont supprimé des pages : on repart d'une installation propre.
foreach ( $slugs as $s ) {
	$old = get_page_by_path( $s, OBJECT, 'page' );
	if ( $old ) {
		wp_delete_post( $old->ID, true );
	}
}
delete_option( 'arvator_pages_created' );
Arvator_Pages::create_missing();
clear_transients();
$trial_email = "essai_$tag@example.org";
$acct        = (string) ( 100000 + (int) hexdec( substr( $tag, 0, 4 ) ) );
$wpdb->query( 'DELETE FROM ' . Arvator_Repo::table( 'trials' ) . " WHERE email = '$trial_email'" );
$tp = $anon->get( '/essai-gratuit/' );
check( 200 === $tp['code'] && str_contains( $tp['body'], 'renonce à mon droit de rétractation' ) && str_contains( $tp['body'], 'name="account"' ), 'page d\'essai : renonciation annoncée avant la demande, champ compte démo' );
$tn   = field_in_form( $tp['body'], 'arvator_trial', '_arv_nonce' );
$tgood = array( 'action' => 'arvator_trial', '_arv_nonce' => $tn, 'email' => $trial_email, 'account' => $acct, 'waiver' => '1', 'consent' => '1' );
check( 'generic_invalid' === notice_of( $anon->post( '/wp-admin/admin-post.php', array( '_arv_nonce' => 'faux' ) + $tgood ) ), 'essai sans clé de sécurité valide refusé' );
check( 'essai_invalid' === notice_of( $anon->post( '/wp-admin/admin-post.php', array( 'waiver' => '' ) + $tgood ) ), 'essai sans case de renonciation refusé' );
check( 'essai_invalid' === notice_of( $anon->post( '/wp-admin/admin-post.php', array( 'consent' => '' ) + $tgood ) ), 'essai sans consentement refusé' );
check( 'essai_invalid' === notice_of( $anon->post( '/wp-admin/admin-post.php', array( 'account' => 'abc; DROP' ) + $tgood ) ), 'numéro de compte non numérique refusé' );
check( 'essai_invalid' === notice_of( $anon->post( '/wp-admin/admin-post.php', array( 'email' => 'nope' ) + $tgood ) ), 'email invalide refusé' );
check( ! Arvator_Trial::has_trial( $trial_email ), 'rien d\'enregistré pour les demandes refusées' );
@unlink( $log );
// n8n injoignable ici : la demande doit quand même être enregistrée et transmise à Hamid (activation à la main).
file_put_contents( (string) getenv( 'ARV_N8N_FIXTURES' ), wp_json_encode( array( 'licence-essai' => array( '__down' => true ) ) ) );
check( 'essai_ok' === notice_of( $anon->post( '/wp-admin/admin-post.php', $tgood ) ), 'demande valide acceptée (n8n injoignable : traitée à la main)' );
@unlink( (string) getenv( 'ARV_N8N_FIXTURES' ) );
check( Arvator_Trial::has_trial( $trial_email ) && Arvator_Trial::has_trial( strtoupper( $trial_email ) ), 'demande enregistrée (email insensible à la casse)' );
$m = mails();
check( 2 === count( $m ) && 'support@arvator.com' === $m[0]->to && str_contains( $m[0]->message, $acct ) && $trial_email === $m[1]->to && str_contains( $m[1]->message, 'droit de rétractation' ), 'email à Hamid + confirmation au demandeur avec le rappel de renonciation' );
$anon2 = new Browser( $url );
$tp2   = $anon2->get( '/essai-gratuit/' );
$tg2   = array( '_arv_nonce' => field_in_form( $tp2['body'], 'arvator_trial', '_arv_nonce' ) ) + $tgood;
check( 'essai_deja' === notice_of( $anon2->post( '/wp-admin/admin-post.php', $tg2 ) ), 'même email : un seul essai' );
check( 'essai_deja' === notice_of( $anon2->post( '/wp-admin/admin-post.php', array( 'email' => "autre_$tag@example.org" ) + $tg2 ) ), 'même numéro de compte avec un autre email : un seul essai' );
check( 'essai_ok' === notice_of( $anon2->post( '/wp-admin/admin-post.php', array( 'website' => 'http://spam' ) + $tg2 ) ) && 1 === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Arvator_Repo::table( 'trials' ) . " WHERE email LIKE '%$tag@example.org'" ), 'champ piège rempli : rien d\'enregistré' );

// Rappel de renonciation au paiement
$set_fix( array( 'POST checkout/sessions' => array( 'id' => 'cs_w', 'url' => 'https://checkout.stripe.com/c/pay/cs_w' ) ) );
@unlink( $stripe_log );
$bp  = $anon2->get( '/acheter/?plan=monthly' );
check( str_contains( $bp['body'], 'value="monthly" checked' ) && ! str_contains( $bp['body'], 'value="annual" checked' ), 'page d\'achat : formule présélectionnée par le bouton de la carte' );
$bg = array( 'action' => 'arvator_checkout', '_arv_nonce' => field_in_form( $bp['body'], 'arvator_checkout', '_arv_nonce' ), 'plan' => 'annual', 'email' => $trial_email, 'cgv' => '1', 'risks' => '1' );
check( 'achat_renonciation' === notice_of( $anon2->post( '/wp-admin/admin-post.php', $bg ) ) && 0 === count( $stripe_calls() ), 'après un essai : achat refusé sans la case de renonciation (aucun appel à Stripe)' );
$r = $anon2->post( '/wp-admin/admin-post.php', array( 'waiver' => '1' ) + $bg );
$wb = array();
parse_str( (string) ( $stripe_calls()[0]['body'] ?? '' ), $wb );
check( 303 === $r['code'] && '1' === ( $wb['metadata']['trial_waiver'] ?? '' ), 'après un essai : achat accepté avec la case, renonciation notée dans la commande Stripe' );
@unlink( $stripe_log );
$r = $anon2->post( '/wp-admin/admin-post.php', array( 'email' => "sans_essai_$tag@example.org" ) + $bg );
$wb = array();
parse_str( (string) ( $stripe_calls()[0]['body'] ?? '' ), $wb );
check( 303 === $r['code'] && '0' === ( $wb['metadata']['trial_waiver'] ?? '' ), 'sans essai : aucune renonciation demandée ni notée' );
$set_fix( array() );
@unlink( $stripe_log );

// Boutons d'appel à l'action
$home = $anon->get( '/' );
$count_link = static fn( string $html, string $needle ): int => substr_count( $html, $needle );
check( $count_link( $home['body'], '/essai-gratuit/' ) >= 4, 'accueil : plusieurs boutons d\'essai (en-tête, haut de page, résultats, bandeau, pied de page)', (string) $count_link( $home['body'], '/essai-gratuit/' ) );
check( $count_link( $home['body'], '/acheter/' ) >= 4, 'accueil : plusieurs boutons d\'achat', (string) $count_link( $home['body'], '/acheter/' ) );
check( str_contains( $home['body'], '/acheter/?plan=annual' ) && str_contains( $home['body'], '/acheter/?plan=monthly' ), 'accueil : un bouton d\'achat par formule' );
check( str_contains( $anon->get( '/faq/' )['body'], 'Prêt à essayer' ) && str_contains( $anon->get( '/a-propos/' )['body'], '/essai-gratuit/' ), 'FAQ et À propos : bandeau discret en bas de page' );
foreach ( array( 'risques', 'cgv', 'mentions-legales', 'confidentialite', 'cookies', 'acheter', 'espace-membre', 'essai-gratuit', 'contact' ) as $s ) {
	check( ! str_contains( $anon->get( '/' . $s . '/' )['body'], 'Prêt à essayer' ), "page « $s » : pas de bandeau d'appel à l'action" );
}
// Résultats : solde en euros + pourcentage (5 000 € au risque 1 % : 9 013 € en 2024, +80 % ; 23 186 € sur 2024 → 2026).
check( 1 === preg_match( '/results__amount">9\x{202F}013\x{00A0}€<\/span><span class="results__pct">\+80\x{00A0}%/u', $home['body'] ), 'résultats : solde en euros et pourcentage dans chaque case' );
check( str_contains( $home['body'], '2024 → 2026 enchaînés' ) && str_contains( $home['body'], "23\u{202F}186\u{00A0}€" ), 'résultats : colonne 2024 → 2026 enchaînés' );
check( 1 === preg_match( '/hero__card.*?9\x{202F}013\x{00A0}€.*?\+80\x{00A0}%/su', $home['body'] ), 'haut de page : solde en euros à côté du pourcentage' );
check( 1 === preg_match( '/class="is-neg"><span class="results__amount">491\x{00A0}€/u', $home['body'] ), 'résultats : année négative en rouge (500 €, 2026)' );
check( 1 === preg_match( '/site-header.*?\/acheter\/.*?\/essai-gratuit\//s', $home['body'] ) ||1 === preg_match( '/site-header.*?\/essai-gratuit\//s', $home['body'] ), 'en-tête : boutons d\'essai et d\'achat' );
$wpdb->query( 'DELETE FROM ' . Arvator_Repo::table( 'trials' ) . " WHERE email LIKE '%$tag@example.org'" );

/* ======================================================================== J. Parrainage */
title( 'J. Parrainage : lien, récompenses, remboursement, anti-fraude, virements' );
clear_transients();
@unlink( $log );
@unlink( $stripe_log );
$ref_tables = array( 'referrers', 'referrals', 'commissions' );
foreach ( $ref_tables as $t ) {
	check( $wpdb->get_var( "SHOW TABLES LIKE '" . Arvator_Repo::table( $t ) . "'" ) === Arvator_Repo::table( $t ), "table « $t » créée" );
}

// -- Unités : calculs, codes, IBAN
check( 22500 === Arvator_Referral::settings()['prime_cents'] && 0.10 === Arvator_Referral::settings()['rate'] && 30 === Arvator_Referral::settings()['delay_days'], 'réglages par défaut : 225 €, 10 %, 30 jours' );
check( 399 === Arvator_Referral::affiliation_cents( 3990 ) && 3588 === Arvator_Referral::affiliation_cents( 35880 ) && 0 === Arvator_Referral::affiliation_cents( 0 ), 'calcul : 10 % de 39,90 € = 3,99 € ; de 358,80 € = 35,88 €' );
check( 'ABCD2345' === Arvator_Referral::normalize_code( ' abcd-2345 ' ) && '' === Arvator_Referral::normalize_code( "AB'; DROP" ) && '' === Arvator_Referral::normalize_code( 'ABC' ), 'code : nettoyé, rien d\'autre que 8 lettres ou chiffres' );
check( Arvator_Referral::valid_iban( 'FR76 3000 6000 0112 3456 7890 189' ) && ! Arvator_Referral::valid_iban( 'FR76 3000 6000 0112 3456 7890 188' ) && ! Arvator_Referral::valid_iban( 'pas un iban' ), 'IBAN : clé de contrôle vérifiée' );
$enc = Arvator_Referral::encrypt( 'FR7630006000011234567890189' );
check( ! str_contains( $enc, '30006000' ) && 'FR7630006000011234567890189' === Arvator_Referral::decrypt( $enc ) && '' === Arvator_Referral::decrypt( 'v1:' . base64_encode( 'abimé-abimé-abimé-abimé-abimé-abimé' ) ), 'IBAN chiffré (illisible en base), déchiffrable, donnée abîmée refusée' );
check( "\u{2212}225\u{00A0}€" === Arvator_Referral::money( -22500 ) && "3,99\u{00A0}€" === Arvator_Referral::money( 399 ), 'montants affichés (déduction en négatif)' );

// -- Pages
foreach ( array( 'parrainage', 'conditions-parrainage' ) as $s ) {
	$old = get_page_by_path( $s, OBJECT, 'page' );
	if ( $old ) {
		wp_delete_post( $old->ID, true );
	}
}
update_option( 'arvator_pages_created', array_values( array_diff( (array) get_option( 'arvator_pages_created', array() ), array( 'parrainage', 'conditions-parrainage' ) ) ) );
Arvator_Pages::create_missing();
$pp = $anon->get( '/parrainage/' );
check( 200 === $pp['code'] && str_contains( $pp['body'], "225\u{00A0}€" ) && str_contains( $pp['body'], "10\u{00A0}%" ) && str_contains( $pp['body'], 'arvator_referral_signup' ) && str_contains( $pp['body'], '/conditions-parrainage/' ), 'page Parrainage : montants, formulaire gratuit, lien vers les conditions' );
check( ! preg_match( '/revenu passif|opportunit|gagnez|garanti\b/iu', strip_tags( $pp['body'] ) ), 'page Parrainage : aucun mot interdit (revenu passif, opportunité, gagnez, garanti)' );
check( str_contains( $pp['headers'], 'no-store' ), 'page Parrainage jamais mise en cache (formulaire)' );
$cp = $anon->get( '/conditions-parrainage/' );
check( 200 === $cp['code'] && ! str_contains( $cp['body'], 'notice-provisional' ) && str_contains( $cp['body'], 'déduit des récompenses suivantes' ), 'conditions : sans bandeau « provisoire », règle de déduction écrite' );
$home = $anon->get( '/' );
check( str_contains( $home['body'], 'class="referral"' ) && str_contains( $home['body'], 'Recommandez Arvator' ) && str_contains( $home['body'], '/parrainage/' ), 'accueil : bandeau « Recommandez Arvator » affiché dès que la page existe' );
check( str_contains( $home['body'], '/conditions-parrainage/' ), 'pied de page : lien vers les conditions du parrainage' );

// -- Inscription d'un visiteur (gratuite, sans achat)
$pemail = "parrain_$tag@example.org";
$sn     = field_in_form( $pp['body'], 'arvator_referral_signup', '_arv_nonce' );
$sgood  = array( 'action' => 'arvator_referral_signup', '_arv_nonce' => $sn, 'email' => $pemail, 'terms' => '1' );
check( 'generic_invalid' === notice_of( $anon->post( '/wp-admin/admin-post.php', array( '_arv_nonce' => 'faux' ) + $sgood ) ), 'inscription sans clé de sécurité valide refusée' );
check( 'parrain_invalid' === notice_of( $anon->post( '/wp-admin/admin-post.php', array( 'terms' => '' ) + $sgood ) ), 'inscription sans accepter les conditions refusée' );
check( 'parrain_mail' === notice_of( $anon->post( '/wp-admin/admin-post.php', array( 'website' => 'spam' ) + $sgood ) ) && ! get_user_by( 'email', $pemail ), 'champ piège rempli : rien de créé' );
check( 'parrain_mail' === notice_of( $anon->post( '/wp-admin/admin-post.php', $sgood ) ), 'inscription valide acceptée' );
$puser = get_user_by( 'email', $pemail );
$pref  = $puser ? Arvator_Referral::referrer( (int) $puser->ID ) : null;
check( $puser instanceof WP_User && in_array( Arvator_Roles::CLIENT, $puser->roles, true ) && is_array( $pref ) && 8 === strlen( (string) $pref['code'] ) && 'active' === $pref['status'], 'compte client et code de parrain créés, sans achat' );
$code = (string) ( $pref['code'] ?? '' );
$m    = mails();
check( 1 === count( $m ) && $pemail === $m[0]->to && 1 === substr_count( $m[0]->message, '?ref=' ) && str_contains( $m[0]->message, '/acheter/?ref=' . $code ) && str_contains( $m[0]->message, 'action=rp' ) && str_contains( $m[0]->message, 'performances simulées' ), 'email : lien de recommandation + lien pour choisir le mot de passe + rappel des risques' );
@unlink( $log );
check( 'parrain_mail' === notice_of( $anon->post( '/wp-admin/admin-post.php', array( 'email' => "a$tag@example.com" ) + $sgood ) ) && null === Arvator_Referral::referrer( $uA ), 'adresse déjà cliente : même réponse (pas de fuite), pas d\'inscription à sa place' );
check( 1 === count( mails() ) && "a$tag@example.com" === mails()[0]->to && str_contains( mails()[0]->message, 'rubrique « Parrainage »' ), '… mais un email lui explique comment activer son lien' );
$wpdb->query( 'DELETE FROM ' . Arvator_Repo::table( 'referrers' ) . ' WHERE user_id = ' . (int) $uA );

// -- Lien de recommandation : cookie, seulement avec l'accord du visiteur (bandeau cookies)
$nc = new Browser( $url );
$r  = $nc->get( '/?ref=' . $code );
check( 200 === $r['code'] && ! str_contains( $r['headers'], 'arv_ref=' ) && str_contains( $r['body'], 'id="consent"' ), 'lien ?ref= sans choix sur les cookies : aucun cookie déposé, la page demande l\'accord' );
check( str_contains( $r['headers'], 'no-store' ) || str_contains( $r['headers'], 'no-cache' ), 'lien ?ref= sans choix : page jamais mise en cache' );
$nc->cookie = 'arv_consent=v1-r0';
$r          = $nc->get( '/?ref=' . $code );
check( 200 === $r['code'] && ! str_contains( $r['headers'], 'arv_ref=' ), 'cookie de recommandation refusé : rien n\'est déposé' );
$ab = $nc->get( '/acheter/?ref=' . $code );
check( str_contains( $ab['body'], 'name="ref"' ) && str_contains( $ab['body'], 'value="' . $code . '"' ), 'refus : le code de l\'adresse est quand même prérempli sur la page d\'achat (sans cookie)' );
$nc->cookie = 'arv_consent=v1-r0; arv_ref=' . $code;
$r          = $nc->get( '/faq/' );
check( 1 === preg_match( '/Set-Cookie: arv_ref=(deleted)?;[^\r\n]*expires=Thu, 01[- ]Jan[- ]1970|Set-Cookie: arv_ref=;[^\r\n]*(expires|Max-Age=0)/i', $r['headers'] ), 'accord retiré : le cookie de recommandation existant est effacé', substr( $r['headers'], 0, 600 ) );
$ab = $nc->get( '/acheter/' );
check( ! str_contains( $ab['body'], 'value="' . $code . '"' ), 'accord retiré : le code n\'est plus prérempli' );
$vis         = new Browser( $url );
$vis->cookie = 'arv_consent=v1-r1';
$r           = $vis->get( '/?ref=' . strtolower( $code ) );
check( 302 === $r['code'] && ! str_contains( $r['location'], 'ref=' ) && preg_match( '/Set-Cookie: arv_ref=' . $code . ';.*HttpOnly/i', $r['headers'] ), 'cookie de recommandation accepté : code mémorisé (cookie HttpOnly), adresse nettoyée' );
check( str_contains( $r['headers'], 'no-store' ) || str_contains( $r['headers'], 'no-cache' ), 'lien ?ref= : réponse jamais mise en cache' );
$b2         = new Browser( $url );
$b2->cookie = 'arv_consent=v1-r1';
$r2         = $b2->get( '/?ref=ZZZZ9999' );
check( 302 === $r2['code'] && ! str_contains( $r2['headers'], 'arv_ref=' ), 'code inconnu : aucun cookie' );
$buy = $vis->get( '/acheter/' );
check( str_contains( $buy['body'], 'name="ref"' ) && str_contains( $buy['body'], 'value="' . $code . '"' ), 'page d\'achat : code du parrain prérempli' );

// -- Départ vers le paiement avec un code
$set_fix( array( 'POST checkout/sessions' => array( 'id' => 'cs_r', 'url' => 'https://checkout.stripe.com/c/pay/cs_r' ) ) );
@unlink( $stripe_log );
$f1    = "filleul1_$tag@example.org";
$cgood = array( 'action' => 'arvator_checkout', '_arv_nonce' => field_in_form( $buy['body'], 'arvator_checkout', '_arv_nonce' ), 'plan' => 'annual', 'email' => $f1, 'cgv' => '1', 'risks' => '1', 'ref' => $code );
check( 'achat_code' === notice_of( $vis->post( '/wp-admin/admin-post.php', array( 'ref' => 'ZZZZ9999' ) + $cgood ) ), 'code inconnu au paiement : message clair' );
check( 'achat_code' === notice_of( $vis->post( '/wp-admin/admin-post.php', array( 'email' => $pemail ) + $cgood ) ), 'parrain qui utilise son propre code : refusé' );
check( 0 === count( $stripe_calls() ), 'aucun appel à Stripe pour ces refus' );
$r  = $vis->post( '/wp-admin/admin-post.php', $cgood );
$cb = array();
parse_str( (string) ( $stripe_calls()[0]['body'] ?? '' ), $cb );
check( 303 === $r['code'] && $code === ( $cb['metadata']['ref'] ?? '' ), 'achat avec code : code transmis à Stripe' );
@unlink( $stripe_log );
$vis->post( '/wp-admin/admin-post.php', array( 'ref' => '' ) + $cgood );
$cb = array();
parse_str( (string) ( $stripe_calls()[0]['body'] ?? '' ), $cb );
check( '' === ( $cb['metadata']['ref'] ?? '' ), 'champ vidé : aucun code transmis' );

// -- Paiement confirmé : filleul rattaché, prime + 10 % du premier abonnement
$pid      = (int) $puser->ID;
$mk_sess  = static function ( string $sid, string $email, string $cus, string $ref, string $fp = '' ) use ( $full, $tag ): array {
	$s                       = $full;
	$s['id']                 = $sid;
	$s['customer']           = $cus;
	$s['customer_email']     = $email;
	$s['customer_details']   = array( 'email' => $email );
	$s['invoice']            = 'in_' . $sid;
	$s['metadata']           = array( 'ref' => $ref, 'plan' => 'annual' );
	$s['subscription']['id'] = 'sub_' . $sid;
	if ( '' !== $fp ) {
		$s['subscription']['default_payment_method'] = array( 'card' => array( 'fingerprint' => $fp ) );
	}
	return $s;
};
$buy_hook = static function ( string $sid, array $sess, string $evt ) use ( $set_fix, $event, $post_raw, $sign ): array {
	$set_fix( array( "GET checkout/sessions/$sid" => $sess ) );
	$e = $event( $evt, 'checkout.session.completed', array( 'id' => $sid, 'payment_status' => 'paid' ) );
	return $post_raw( $e, $sign( $e ) );
};
$coms = static function ( int $referrer ) use ( $wpdb ): array {
	return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Arvator_Repo::table( 'commissions' ) . ' WHERE referrer_user_id = %d ORDER BY id', $referrer ), ARRAY_A );
};
@unlink( $log );
$s1 = "cs_ref1_$tag";
$r  = $buy_hook( $s1, $mk_sess( $s1, $f1, "cus_f1_$tag", $code, "fp_f1_$tag" ), "evt_{$tag}_r1" );
check( 200 === $r['code'], 'achat du filleul accepté', $r['body'] );
$f1u = get_user_by( 'email', $f1 );
$rel = $f1u ? Arvator_Referral::referral_for( (int) $f1u->ID ) : null;
check( is_array( $rel ) && (int) $rel['referrer_user_id'] === $pid && 'ok' === $rel['status'], 'filleul rattaché à son parrain' );
$c = $coms( $pid );
check( 2 === count( $c ), 'deux récompenses créées', (string) count( $c ) );
$prime = $c[0] ?? array();
$aff1  = $c[1] ?? array();
check( 'prime' === ( $prime['kind'] ?? '' ) && 22500 === (int) ( $prime['amount_cents'] ?? 0 ) && 'pending' === ( $prime['status'] ?? '' ), 'prime d\'achat : 225 €, en attente' );
$delay = strtotime( (string) ( $prime['available_at'] ?? '' ) . ' UTC' ) - time();
check( $delay > 29.9 * 86400 && $delay < 30.1 * 86400, 'prime disponible 30 jours après le paiement' );
check( 'affiliation' === ( $aff1['kind'] ?? '' ) && 3588 === (int) ( $aff1['amount_cents'] ?? 0 ), 'premier abonnement annuel : 10 % = 35,88 €' );
check( 1 === (int) ( $prime['is_test'] ?? 0 ), 'récompense marquée « test » (bac à sable)' );
$buy_hook( $s1, $mk_sess( $s1, $f1, "cus_f1_$tag", $code, "fp_f1_$tag" ), "evt_{$tag}_r1" );
$buy_hook( $s1, $mk_sess( $s1, $f1, "cus_f1_$tag", $code, "fp_f1_$tag" ), "evt_{$tag}_r1bis" );
check( 2 === count( $coms( $pid ) ), 'idempotence : même achat reçu 3 fois → toujours 2 récompenses' );
$rel2 = $buy_hook( $s1, $mk_sess( $s1, $f1, "cus_f1_$tag", 'ZZZZ9999', "fp_f1_$tag" ), "evt_{$tag}_r1ter" );
check( (int) Arvator_Referral::referral_for( (int) $f1u->ID )['referrer_user_id'] === $pid, 'parrain fixé au premier achat : ne change plus' );

// -- Renouvellements d'abonnement (invoice.paid)
$inv = static function ( string $id, string $sub, int $paid, string $reason = 'subscription_cycle' ) use ( $event ): string {
	return $event( $id, 'invoice.paid', array( 'id' => 'in_' . $id, 'subscription' => $sub, 'amount_paid' => $paid, 'billing_reason' => $reason ) );
};
$e = $inv( "evt_{$tag}_i1", "sub_$s1", 35880, 'subscription_create' );
$post_raw( $e, $sign( $e ) );
check( 2 === count( $coms( $pid ) ), 'facture de création : déjà comptée à l\'achat, pas de doublon' );
$e = $inv( "evt_{$tag}_i2", "sub_$s1", 35880 );
$r = $post_raw( $e, $sign( $e ) );
$post_raw( $e, $sign( $e ) );
check( 200 === $r['code'] && 3 === count( $coms( $pid ) ) && 3588 === (int) $coms( $pid )[2]['amount_cents'], 'renouvellement payé : +10 %, reçu deux fois → compté une fois' );

// Nouvelle version de l'API Stripe : l'abonnement est rangé ailleurs dans la facture.
$e = $event( "evt_{$tag}_i3", 'invoice.paid', array( 'id' => "in_new_$tag", 'parent' => array( 'subscription_details' => array( 'subscription' => "sub_$s1" ) ), 'amount_paid' => 35880, 'billing_reason' => 'subscription_cycle' ) );
$post_raw( $e, $sign( $e ) );
check( 4 === count( $coms( $pid ) ), 'renouvellement (nouvelle API Stripe) : reconnu aussi' );
$e = $inv( "evt_{$tag}_i4", 'sub_inconnu', 3990 );
$r = $post_raw( $e, $sign( $e ) );
check( 200 === $r['code'] && 4 === count( $coms( $pid ) ), 'abonnement sans parrain : aucune récompense, pas d\'erreur' );

// -- Délai, « à payer », seuil de virement
$wpdb->query( 'UPDATE ' . Arvator_Repo::table( 'commissions' ) . " SET available_at = '2000-01-01 00:00:00' WHERE referrer_user_id = $pid" );
Arvator_Referral::promote_due();
check( 4 === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Arvator_Repo::table( 'commissions' ) . " WHERE referrer_user_id = $pid AND status = 'payable'" ), 'délai passé : récompenses « à payer »' );
$due   = Arvator_Referral::payouts_due();
$mine  = array_values( array_filter( $due, static fn( $d ) => $d['user_id'] === $pid ) );
check( 1 === count( $mine ) && 22500 + 3 * 3588 === $mine[0]['total'], 'à verser : prime + abonnements (cumul 107,64 € ≥ 50 €)', wp_json_encode( $mine ) );
$wpdb->query( 'UPDATE ' . Arvator_Repo::table( 'commissions' ) . " SET status = 'canceled' WHERE referrer_user_id = $pid AND kind = 'affiliation' AND id <> " . (int) $aff1['id'] );
$mine = array_values( array_filter( Arvator_Referral::payouts_due(), static fn( $d ) => $d['user_id'] === $pid ) );
check( 1 === count( $mine ) && 22500 === $mine[0]['total'], 'abonnements sous 50 € cumulés : seule la prime est versée' );
$wpdb->query( 'UPDATE ' . Arvator_Repo::table( 'commissions' ) . " SET status = 'payable' WHERE referrer_user_id = $pid AND kind = 'affiliation'" );

// -- Remboursement avant versement : prime annulée, abonnement payé conservé
$set_fix( array( "DELETE subscriptions/sub_$s1" => array( 'id' => "sub_$s1", 'status' => 'canceled' ) ) );
$e = $event( "evt_{$tag}_rf", 'charge.refunded', array( 'id' => "ch_r_$tag", 'customer' => "cus_f1_$tag", 'invoice' => "in_$s1", 'amount_refunded' => 150000 ) );
$post_raw( $e, $sign( $e ) );
$post_raw( $e, $sign( $e ) );
$p = $wpdb->get_row( 'SELECT * FROM ' . Arvator_Repo::table( 'commissions' ) . ' WHERE id = ' . (int) $prime['id'], ARRAY_A );
check( 'canceled' === $p['status'] && str_contains( (string) $p['note'], 'remboursement' ), 'achat remboursé : prime annulée avant versement' );
check( 'payable' === $wpdb->get_var( 'SELECT status FROM ' . Arvator_Repo::table( 'commissions' ) . ' WHERE id = ' . (int) $aff1['id'] ), '… la récompense sur le premier abonnement (non remboursé) reste due' );
check( 0 === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Arvator_Repo::table( 'commissions' ) . " WHERE referrer_user_id = $pid AND kind = 'adjustment'" ), '… et aucune déduction (rien n\'avait été versé)' );
$e = $inv( "evt_{$tag}_i5", "sub_$s1", 35880 );
$post_raw( $e, $sign( $e ) );
check( 4 === count( $coms( $pid ) ), 'après remboursement : plus aucune récompense sur ce client' );

// -- Contestation bancaire après versement : déduction
$f2 = "filleul2_$tag@example.org";
$s2 = "cs_ref2_$tag";
$buy_hook( $s2, $mk_sess( $s2, $f2, "cus_f2_$tag", $code, "fp_f2_$tag" ), "evt_{$tag}_r2" );
$f2u = get_user_by( 'email', $f2 );
$c2  = $wpdb->get_results( 'SELECT * FROM ' . Arvator_Repo::table( 'commissions' ) . ' WHERE referee_user_id = ' . (int) $f2u->ID . ' ORDER BY id', ARRAY_A );
check( 2 === count( $c2 ), 'deuxième filleul : deux récompenses' );
$wpdb->query( 'UPDATE ' . Arvator_Repo::table( 'commissions' ) . " SET status = 'paid', paid_at = '$now' WHERE referee_user_id = " . (int) $f2u->ID );
$set_fix( array( "GET charges/ch_d_$tag" => array( 'id' => "ch_d_$tag", 'invoice' => "in_$s2", 'customer' => "cus_f2_$tag" ) ) );
@unlink( $log );
$e = $event( "evt_{$tag}_dp", 'charge.dispute.created', array( 'id' => "dp_$tag", 'charge' => "ch_d_$tag" ) );
$r = $post_raw( $e, $sign( $e ) );
$post_raw( $e, $sign( $e ) );
$adj = $wpdb->get_results( 'SELECT * FROM ' . Arvator_Repo::table( 'commissions' ) . ' WHERE referee_user_id = ' . (int) $f2u->ID . " AND kind = 'adjustment' ORDER BY id", ARRAY_A );
check( 200 === $r['code'] && 2 === count( $adj ) && -22500 === (int) $adj[0]['amount_cents'] && -3588 === (int) $adj[1]['amount_cents'] && 'payable' === $adj[0]['status'], 'contestation après versement : prime et premier abonnement déduits (−225 €, −35,88 €), une seule fois', wp_json_encode( $adj ) );
check( (bool) array_filter( mails(), static fn( $x ) => 'support@arvator.com' === $x->to && str_contains( $x->subject, 'contestation' ) ), 'Hamid est prévenu de la contestation' );
$bal = (int) $wpdb->get_var( 'SELECT SUM(amount_cents) FROM ' . Arvator_Repo::table( 'commissions' ) . " WHERE referrer_user_id = $pid AND status = 'payable'" );
check( 3 * 3588 - 22500 - 3588 === $bal, 'solde « à payer » du parrain négatif après déduction', (string) $bal );
check( ! array_filter( Arvator_Referral::payouts_due(), static fn( $d ) => $d['user_id'] === $pid ), 'solde négatif : aucun virement proposé' );

// -- Anti-fraude : auto-parrainage
$fraud = static function ( string $label, string $sid, string $email, string $cus, string $fp, string $evt ) use ( $buy_hook, $mk_sess, $code, $pid, $wpdb, $log ): void {
	@unlink( $log );
	$buy_hook( $sid, $mk_sess( $sid, $email, $cus, $code, $fp ), $evt );
	$u   = get_user_by( 'email', $email );
	$rel = $u ? Arvator_Referral::referral_for( (int) $u->ID ) : null;
	$st  = $u ? $wpdb->get_col( 'SELECT status FROM ' . Arvator_Repo::table( 'commissions' ) . ' WHERE referee_user_id = ' . (int) $u->ID ) : array();
	$al  = array_filter( mails(), static fn( $x ) => 'support@arvator.com' === $x->to && str_contains( $x->subject, 'bloquée' ) );
	check( is_array( $rel ) && 'blocked' === $rel['status'] && $st && ! array_diff( $st, array( 'blocked' ) ) && $al, "auto-parrainage ($label) : récompenses bloquées + alerte à Hamid", wp_json_encode( $st ) );
};
// Le parrain achète lui-même avec son code (même compte, même email).
$fraud( 'même compte', "cs_self_$tag", $pemail, "cus_self_$tag", "fp_self_$tag", "evt_{$tag}_fr1" );
// Autre email, mais même client Stripe que le parrain.
$fraud( 'même client Stripe', "cs_cus_$tag", "autre1_$tag@example.org", "cus_self_$tag", '', "evt_{$tag}_fr2" );
// Autre email, autre client, mais même carte que le parrain.
$fraud( 'même carte', "cs_card_$tag", "autre2_$tag@example.org", "cus_x_$tag", "fp_self_$tag", "evt_{$tag}_fr3" );
check( ! array_filter( Arvator_Referral::payouts_due(), static fn( $d ) => in_array( $d['user_id'], array( $pid ), true ) && $d['total'] > 0 ), 'récompenses bloquées : jamais proposées au virement' );

// -- Espace membre du parrain
$pw = 'Parrain-Test-Only-' . $tag;
wp_set_password( $pw, $pid );
$pb = new Browser( $url );
member_login( $pb, $pemail, $pw );
$sec = $pb->get( '/espace-membre/?section=parrainage' );
check( 1 === substr_count( $sec['body'], 'data-copy="#arv-ref-' ) && str_contains( $sec['body'], 'value="' . esc_attr( Arvator_Checkout::url() ) . '?ref=' . $code . '"' ), 'espace parrain : un seul lien, vers la page d\'achat' );
$bl = ( new Browser( $url ) )->get( '/acheter/?ref=' . $code );
check( 200 === $bl['code'] && str_contains( $bl['body'], 'value="' . $code . '"' ) && ! str_contains( $bl['headers'], 'arv_ref=' ), 'lien direct vers l\'achat, sans accord sur les cookies : code prérempli, aucun cookie' );
check( 200 === $sec['code'] && str_contains( $sec['body'], '?ref=' . $code ) && str_contains( $sec['body'], 'data-copy="#arv-ref-link"' ) && str_contains( $sec['body'], 'Déduction' ) && str_contains( $sec['body'], 'Prime d’achat' ), 'espace parrain : lien à copier, récompenses et déductions visibles' );
check( ! str_contains( $sec['body'], $f1 ) && ! str_contains( $sec['body'], $f2 ), 'espace parrain : aucune adresse de filleul affichée' );
$alice = new Browser( $url );
member_login( $alice, "ta$tag", $newA );
$as = $alice->get( '/espace-membre/?section=parrainage' );
check( ! str_contains( $as['body'], $code ) && str_contains( $as['body'], 'arvator_referral_join' ), 'un autre client ne voit pas les données du parrain (il voit l\'inscription)' );
$jn = field_in_form( $as['body'], 'arvator_referral_join', '_arv_nonce' );
check( 'parrain_ok' === notice_of( $alice->post( '/wp-admin/admin-post.php', array( 'action' => 'arvator_referral_join', '_arv_nonce' => $jn, 'terms' => '1' ) ) ) && null !== Arvator_Referral::referrer( $uA ), 'un client connecté devient parrain en un clic' );
$bn    = field_in_form( $sec['body'], 'arvator_referral_bank', '_arv_nonce' );
$bgood = array( 'action' => 'arvator_referral_bank', '_arv_nonce' => $bn, 'holder' => '=HYPERLINK("x") Jean', 'iban' => 'fr76 3000 6000 0112 3456 7890 189', 'entity' => 'particulier' );
check( 'iban_invalid' === notice_of( $pb->post( '/wp-admin/admin-post.php', array( 'iban' => 'FR7630006000011234567890188' ) + $bgood ) ), 'IBAN faux refusé' );
check( 'iban_invalid' === notice_of( $pb->post( '/wp-admin/admin-post.php', array( 'entity' => 'entreprise', 'siret' => '123' ) + $bgood ) ), 'entreprise sans SIRET valide refusée' );
check( 'generic_invalid' === notice_of( $pb->post( '/wp-admin/admin-post.php', array( '_arv_nonce' => 'faux' ) + $bgood ) ), 'IBAN sans clé de sécurité valide refusé' );
check( 'iban_ok' === notice_of( $pb->post( '/wp-admin/admin-post.php', $bgood ) ), 'IBAN valide enregistré' );
$pref = Arvator_Referral::referrer( $pid );
check( ! str_contains( (string) $pref['iban_enc'], '30006000' ) && 'FR7630006000011234567890189' === Arvator_Referral::decrypt( (string) $pref['iban_enc'] ), 'IBAN stocké chiffré en base' );
$sec = $pb->get( '/espace-membre/?section=parrainage' );
check( str_contains( $sec['body'], 'FR76 •••• •••• 0189' ) && ! str_contains( $sec['body'], '30006000' ), 'espace parrain : IBAN masqué à l\'affichage' );
$r = $anon->post( '/wp-admin/admin-post.php', array( 'action' => 'arvator_referral_bank', '_arv_nonce' => $bn ) + $bgood );
check( ! in_array( notice_of( $r ), array( 'iban_ok' ), true ), 'IBAN sans être connecté : refusé' );

// -- Administration : virement, CSV, confirmation
// On remet le parrain en solde positif : la déduction est compensée par une nouvelle prime « à payer ».
$wpdb->insert( Arvator_Repo::table( 'commissions' ), array( 'referrer_user_id' => $pid, 'referee_user_id' => 0, 'order_id' => 0, 'kind' => 'prime', 'source_id' => "test:$tag", 'amount_cents' => 45000, 'status' => 'payable', 'available_at' => '2000-01-01 00:00:00', 'is_test' => 1, 'created_at' => $now, 'updated_at' => $now ) );
$ap = $adm->get( '/wp-admin/admin.php?page=arvator-parrainage' );
check( 200 === $ap['code'] && str_contains( $ap['body'], 'Préparer un lot de virements' ), 'administration : page Parrainage, virement proposé', (string) $ap['code'] );
check( ! str_contains( $ap['body'], '30006000' ), 'administration : IBAN masqué à l\'écran' );
foreach ( array( 'recompenses', 'parrains', 'reglages' ) as $tb ) {
	$x = $adm->get( '/wp-admin/admin.php?page=arvator-parrainage&tab=' . $tb );
	check( 200 === $x['code'] && ! preg_match( '/Warning:|Notice:|Fatal error|Deprecated:/', $x['body'] ), "administration : onglet « $tb » sans erreur" );
}
check( 403 === $pb->get( '/wp-admin/admin.php?page=arvator-parrainage' )['code'] || ! str_contains( $pb->get( '/wp-admin/admin.php?page=arvator-parrainage' )['body'], 'Préparer un lot' ), 'un client n\'accède pas à l\'administration du parrainage' );
$r = $adm->post( '/wp-admin/admin-post.php', array( 'action' => 'arvator_ref_batch_create', '_arv_nonce' => 'faux' ) );
check( in_array( $r['code'], array( 403, 302 ), true ) && ! str_contains( $r['location'], 'arvator-parrainage' ), 'lot sans clé de sécurité valide refusé', (string) $r['code'] );
$adm->post( '/wp-admin/admin-post.php', array( 'action' => 'arvator_ref_batch_create', '_arv_nonce' => field_in_form( $ap['body'], 'arvator_ref_batch_create', '_arv_nonce' ) ) );
$batch = (string) $wpdb->get_var( 'SELECT payout_batch FROM ' . Arvator_Repo::table( 'commissions' ) . " WHERE referrer_user_id = $pid AND status = 'payable' AND payout_batch IS NOT NULL LIMIT 1" );
check( 1 === preg_match( '/^V\d{14}$/', $batch ), 'lot de virements préparé' );
$ap  = $adm->get( '/wp-admin/admin.php?page=arvator-parrainage' );
$csv = $adm->post( '/wp-admin/admin-post.php', array( 'action' => 'arvator_ref_batch_csv', 'batch' => $batch, '_arv_nonce' => field_in_form( $ap['body'], 'arvator_ref_batch_csv', '_arv_nonce' ) ) );
$expected = number_format( ( 45000 + 3 * 3588 - 22500 - 3588 ) / 100, 2, ',', '' );
check( 200 === $csv['code'] && str_contains( $csv['headers'], 'text/csv' ) && str_contains( $csv['body'], 'FR7630006000011234567890189' ) && str_contains( $csv['body'], '"' . $expected . '"' ), 'CSV : IBAN complet et montant net (' . $expected . ' €)', substr( $csv['body'], 0, 300 ) );
check( str_contains( $csv['body'], '"\'=HYPERLINK' ), 'CSV : une cellule commençant par « = » est neutralisée (pas de formule)' );
$r = $anon->post( '/wp-admin/admin-post.php', array( 'action' => 'arvator_ref_batch_csv', 'batch' => $batch, '_arv_nonce' => field_in_form( $ap['body'], 'arvator_ref_batch_csv', '_arv_nonce' ) ) );
check( ! str_contains( $r['body'], 'FR7630006000011234567890189' ), 'CSV inaccessible sans être administrateur' );
$r = $pb->post( '/wp-admin/admin-post.php', array( 'action' => 'arvator_ref_batch_csv', 'batch' => $batch, '_arv_nonce' => field_in_form( $ap['body'], 'arvator_ref_batch_csv', '_arv_nonce' ) ) );
check( ! str_contains( $r['body'], 'FR7630006000011234567890189' ), 'CSV inaccessible à un client connecté' );
@unlink( $log );
$adm->post( '/wp-admin/admin-post.php', array( 'action' => 'arvator_ref_batch_paid', 'batch' => $batch, '_arv_nonce' => field_in_form( $ap['body'], 'arvator_ref_batch_paid', '_arv_nonce' ) ) );
check( 0 === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Arvator_Repo::table( 'commissions' ) . " WHERE payout_batch = '$batch' AND status <> 'paid'" ) && 4 <= (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Arvator_Repo::table( 'commissions' ) . " WHERE payout_batch = '$batch' AND status = 'paid'" ), 'virement confirmé : récompenses du lot « payées » (déductions comprises)' );
check( (bool) array_filter( mails(), static fn( $x ) => $pemail === $x->to && str_contains( $x->subject, 'virée' ) ), 'le parrain est prévenu par email' );

// -- Déblocage d'une récompense par l'administrateur
$blocked = (int) $wpdb->get_var( 'SELECT id FROM ' . Arvator_Repo::table( 'commissions' ) . " WHERE referrer_user_id = $pid AND status = 'blocked' LIMIT 1" );
$tabc    = $adm->get( '/wp-admin/admin.php?page=arvator-parrainage&tab=recompenses' );
$cn      = '';
foreach ( preg_split( '/<form\b/i', $tabc['body'] ) as $chunk ) {
	if ( str_contains( $chunk, 'name="id" value="' . $blocked . '"' ) && str_contains( $chunk, 'value="unblock"' ) ) {
		$cn = field( $chunk, '_arv_nonce' );
	}
}
$adm->post( '/wp-admin/admin-post.php', array( 'action' => 'arvator_ref_commission', 'id' => $blocked, 'op' => 'unblock', '_arv_nonce' => $cn ) );
check( 'pending' === $wpdb->get_var( 'SELECT status FROM ' . Arvator_Repo::table( 'commissions' ) . " WHERE id = $blocked" ), 'administrateur : récompense bloquée débloquée' );

// -- Cookies : la page explique les deux cookies et comment changer d'avis
$ck = $anon->get( '/cookies/' )['body'];
check( str_contains( $ck, 'arv_ref' ) && str_contains( $ck, 'arv_consent' ) && str_contains( $ck, 'Gérer les cookies' ), 'page Cookies : cookie de recommandation, cookie de choix, comment changer d\'avis' );

// -- Bandeau de consentement et pied de page
$hp = $anon->get( '/' )['body'];
check( 1 === preg_match( '/<section id="consent"[^>]*\shidden>/', $hp ), 'bandeau cookies présent, masqué tant que le script ne l\'affiche pas' );
check( 1 === preg_match( '/class="btn btn--primary btn--sm" data-consent="refuse">Tout refuser/', $hp ) && 1 === preg_match( '/class="btn btn--primary btn--sm" data-consent="accept">Tout accepter/', $hp ), 'bandeau : « Tout refuser » aussi visible que « Tout accepter »' );
check( str_contains( $hp, 'id="consent-ref">' ) && ! preg_match( '/id="consent-ref"[^>]*checked/', $hp ), 'bandeau : case « Recommandation » non précochée' );
check( str_contains( $hp, 'data-consent-open' ) && str_contains( $hp, 'Gérer les cookies' ), 'pied de page : lien « Gérer les cookies » pour changer d\'avis' );
check( 1 === preg_match( '/Développé par <a href="https:\/\/cosmartis\.com" target="_blank" rel="noopener">cosmartis\.com<\/a>/', $hp ), 'pied de page : « Développé par cosmartis.com » cliquable' );
foreach ( array( 'faq', 'acheter', 'espace-membre' ) as $pg ) {
	check( str_contains( $anon->get( '/' . $pg . '/' )['body'], 'id="consent"' ), "bandeau cookies présent sur la page « $pg »" );
}

// -- Données personnelles : export et effacement du parrain
$exp = Arvator_Privacy::export( $pemail );
$flat = wp_json_encode( $exp['data'] );
check( str_contains( $flat, $code ) && str_contains( $flat, 'FR76' ) && ! str_contains( $flat, '30006000' ), 'export RGPD : parrainage inclus, IBAN masqué' );
Arvator_Privacy::erase( $pemail );
check( null === Arvator_Referral::referrer( $pid ) && 0 === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Arvator_Repo::table( 'commissions' ) . " WHERE referrer_user_id = $pid" ) && 0 < (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Arvator_Repo::table( 'commissions' ) . " WHERE source_id = 'test:$tag'" ), 'effacement RGPD : IBAN et lien supprimés, récompenses gardées sans identité' );

// -- Nettoyage du bloc J
foreach ( array( $f1, $f2, $pemail, "autre1_$tag@example.org", "autre2_$tag@example.org" ) as $em ) {
	$u = get_user_by( 'email', $em );
	if ( $u ) {
		Arvator_Repo::delete_test_rows_for_user( (int) $u->ID );
		wp_delete_user( (int) $u->ID );
	}
}
$wpdb->query( 'DELETE FROM ' . Arvator_Repo::table( 'commissions' ) . " WHERE source_id LIKE '%$tag%' OR referrer_user_id IN (0, $pid, $uA)" );
$wpdb->query( 'DELETE FROM ' . Arvator_Repo::table( 'referrals' ) . " WHERE referrer_user_id IN ($pid, $uA)" );
$wpdb->query( 'DELETE FROM ' . Arvator_Repo::table( 'referrers' ) . " WHERE user_id IN ($pid, $uA)" );
$wpdb->query( 'DELETE FROM ' . Arvator_Repo::table( 'orders' ) . " WHERE stripe_session_id LIKE '%$tag%'" );
$wpdb->query( 'DELETE FROM ' . Arvator_Repo::table( 'subscriptions' ) . " WHERE stripe_subscription_id LIKE '%$tag%'" );
$wpdb->query( 'DELETE FROM ' . Arvator_Repo::table( 'events' ) . " WHERE external_id LIKE 'stripe:evt\\_{$tag}%'" );
$set_fix( array() );
@unlink( $stripe_log );


/* ======================================================================== K. Licence via n8n */
title( 'K. Licence : liaison avec le workflow n8n (paiement, renouvellement, résiliation, essai)' );
clear_transients();
$n8n_log = (string) getenv( 'ARV_N8N_LOG' );
$n8n_fix = (string) getenv( 'ARV_N8N_FIXTURES' );
@unlink( $n8n_log );
@unlink( $n8n_fix );
$n8n_set   = static function ( array $fix ) use ( $n8n_fix ): void {
	file_put_contents( $n8n_fix, wp_json_encode( $fix ) );
};
$n8n_calls = static function () use ( $n8n_log ): array {
	return is_file( $n8n_log ) ? array_map( static fn( $l ) => json_decode( $l, true ), file( $n8n_log, FILE_IGNORE_NEW_LINES ) ) : array();
};
$sync = static function ( int $uid ) use ( $wpdb ): array {
	return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Arvator_Repo::table( 'license_sync' ) . ' WHERE user_id = %d ORDER BY id', $uid ), ARRAY_A );
};
check( $wpdb->get_var( "SHOW TABLES LIKE '" . Arvator_Repo::table( 'license_sync' ) . "'" ) === Arvator_Repo::table( 'license_sync' ), 'table « license_sync » créée' );
check( Arvator_License::valid_account( '5936574' ) && ! Arvator_License::valid_account( '12' ) && ! Arvator_License::valid_account( "59365'74" ) && '5936574' === Arvator_License::clean_account( ' 593 6574 ' ), 'numéro de compte : chiffres uniquement (4 à 12)' );

// -- Page d'achat : numéro de compte facultatif, vérifié, transmis à Stripe
$set_fix( array( 'POST checkout/sessions' => array( 'id' => 'cs_k', 'url' => 'https://checkout.stripe.com/c/pay/cs_k' ) ) );
@unlink( $stripe_log );
$kb    = new Browser( $url );
$kpage = $kb->get( '/acheter/' );
check( str_contains( $kpage['body'], 'name="account"' ) && ! preg_match( '/name="account"[^>]*required/', $kpage['body'] ), 'page d\'achat : champ « compte cTrader » facultatif' );
$kgood = array( 'action' => 'arvator_checkout', '_arv_nonce' => field_in_form( $kpage['body'], 'arvator_checkout', '_arv_nonce' ), 'plan' => 'annual', 'email' => "k1_$tag@example.org", 'cgv' => '1', 'risks' => '1', 'account' => '7654321' );
check( 'achat_compte' === notice_of( $kb->post( '/wp-admin/admin-post.php', array( 'account' => '12ab' ) + $kgood ) ) && 0 === count( $stripe_calls() ), 'numéro de compte invalide : refusé avant Stripe' );
$kb->post( '/wp-admin/admin-post.php', $kgood );
$kbody = array();
parse_str( (string) ( $stripe_calls()[0]['body'] ?? '' ), $kbody );
check( '7654321' === ( $kbody['metadata']['compte'] ?? '' ), 'numéro de compte transmis à Stripe avec la commande' );

// -- Achat confirmé avec numéro de compte : licence activée via n8n
$ksess = static function ( string $sid, string $email, string $compte, string $plan = 'annual' ) use ( $full ): array {
	$s                       = $full;
	$s['id']                 = $sid;
	$s['customer']           = 'cus_' . $sid;
	$s['customer_email']     = $email;
	$s['customer_details']   = array( 'email' => $email );
	$s['invoice']            = 'in_' . $sid;
	$s['metadata']           = array( 'plan' => $plan, 'compte' => $compte );
	$s['subscription']['id'] = 'sub_' . $sid;
	$s['subscription']['items']['data'][0]['price']['id'] = 'annual' === $plan ? 'price_annual_test' : 'price_monthly_test';
	return $s;
};
$k1 = "cs_k1_$tag";
$buy_hook( $k1, $ksess( $k1, "k1_$tag@example.org", '7654321' ), "evt_{$tag}_k1" );
$k1u   = get_user_by( 'email', "k1_$tag@example.org" );
$k1id  = $k1u ? (int) $k1u->ID : 0;
$calls = $n8n_calls();
check( 1 === count( $calls ) && 'licence-paiement' === $calls[0]['endpoint'] && 'tok_test_fake' === $calls[0]['token'], 'achat : un appel au workflow n8n, avec le jeton du wp-config', wp_json_encode( $calls ) );
check( array( 'compte' => '7654321', 'plan' => 'annuel', 'action' => 'paiement' ) === array_intersect_key( (array) ( $calls[0]['body'] ?? array() ), array_flip( array( 'compte', 'plan', 'action' ) ) ) && "k1_$tag@example.org" === ( $calls[0]['body']['email'] ?? '' ), 'données envoyées : compte, formule annuelle, paiement, email' );
$klic = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Arvator_Repo::table( 'licenses' ) . ' WHERE user_id = %d', $k1id ), ARRAY_A );
check( is_array( $klic ) && 'active' === $klic['status'] && '7654321' === $klic['account_number'] && str_starts_with( (string) $klic['expires_at'], '2030-01-15' ) && '' === $klic['license_key'], 'licence enregistrée : active, compte, date de fin renvoyée par le serveur' );
$buy_hook( $k1, $ksess( $k1, "k1_$tag@example.org", '7654321' ), "evt_{$tag}_k1" );
$buy_hook( $k1, $ksess( $k1, "k1_$tag@example.org", '7654321' ), "evt_{$tag}_k1b" );
check( 1 === count( $n8n_calls() ), 'idempotence : achat reçu 3 fois → licence prolongée une seule fois' );

// -- Renouvellements
$e = $inv( "evt_{$tag}_k2", "sub_$k1", 35880, 'subscription_create' );
$post_raw( $e, $sign( $e ) );
check( 1 === count( $n8n_calls() ), 'facture de création : déjà traitée à l\'achat, pas de 2e prolongation' );
$e = $inv( "evt_{$tag}_k3", "sub_$k1", 35880 );
$post_raw( $e, $sign( $e ) );
$post_raw( $e, $sign( $e ) );
$calls = $n8n_calls();
check( 2 === count( $calls ) && 'paiement' === $calls[1]['body']['action'] && 'annuel' === $calls[1]['body']['plan'], 'renouvellement payé : licence prolongée une fois (même reçu deux fois)' );

// -- n8n injoignable : retenté plus tard, sans perte
$n8n_set( array( 'licence-paiement' => array( '__down' => true ) ) );
$e = $inv( "evt_{$tag}_k4", "sub_$k1", 35880 );
$r = $post_raw( $e, $sign( $e ) );
$last = $sync( $k1id );
$last = end( $last );
check( 200 === $r['code'] && 'pending' === $last['status'] && 1 === (int) $last['attempts'], 'n8n injoignable : Stripe reçoit « ok », l\'envoi reste à faire' );
@unlink( $n8n_fix );
Arvator_License::retry_pending();
$last = $sync( $k1id );
$last = end( $last );
check( 'done' === $last['status'] && 4 === count( $n8n_calls() ), 'tâche horaire : l\'envoi part dès que n8n répond' );
$n8n_set( array( 'licence-paiement' => array( '__down' => true ) ) );
@unlink( $log );
$e = $inv( "evt_{$tag}_k5", "sub_$k1", 35880 );
$post_raw( $e, $sign( $e ) );
$wpdb->query( 'UPDATE ' . Arvator_Repo::table( 'license_sync' ) . " SET attempts = 9 WHERE source_id = 'pay:inv:in_evt_{$tag}_k5'" );
Arvator_License::retry_pending();
check( 'error' === $wpdb->get_var( 'SELECT status FROM ' . Arvator_Repo::table( 'license_sync' ) . " WHERE source_id = 'pay:inv:in_evt_{$tag}_k5'" ) && (bool) array_filter( mails(), static fn( $x ) => 'support@arvator.com' === $x->to && str_contains( $x->subject, 'licence non mise à jour' ) ), 'après 10 essais : envoi en erreur et Hamid prévenu' );

// -- Administration : suivi et « Renvoyer »
@unlink( $n8n_fix );
$la = $adm->get( '/wp-admin/admin.php?page=arvator-licences' );
check( 200 === $la['code'] && str_contains( $la['body'], 'configurée' ) && str_contains( $la['body'], '7654321' ) && str_contains( $la['body'], 'Renvoyer' ), 'administration : page Licences, envoi en erreur avec bouton « Renvoyer »' );
$err_id = (int) $wpdb->get_var( 'SELECT id FROM ' . Arvator_Repo::table( 'license_sync' ) . " WHERE source_id = 'pay:inv:in_evt_{$tag}_k5'" );
$rn     = '';
foreach ( preg_split( '/<form\b/i', $la['body'] ) as $chunk ) {
	if ( str_contains( $chunk, 'name="id" value="' . $err_id . '"' ) ) {
		$rn = field( $chunk, '_arv_nonce' );
	}
}
check( ! str_contains( $pb->post( '/wp-admin/admin-post.php', array( 'action' => 'arvator_license_retry', 'id' => $err_id, '_arv_nonce' => $rn ) )['location'], 'renvoye=1' ), 'un client ne peut pas relancer un envoi' );
$adm->post( '/wp-admin/admin-post.php', array( 'action' => 'arvator_license_retry', 'id' => $err_id, '_arv_nonce' => $rn ) );
check( 'done' === $wpdb->get_var( 'SELECT status FROM ' . Arvator_Repo::table( 'license_sync' ) . " WHERE id = $err_id" ), '« Renvoyer » : l\'envoi part' );

// -- Refus du serveur de licences
$n8n_set( array( 'licence-paiement' => array( 'ok' => false, 'raison' => 'compte bloqué' ) ) );
@unlink( $log );
$e = $inv( "evt_{$tag}_k6", "sub_$k1", 35880 );
$post_raw( $e, $sign( $e ) );
$row = $wpdb->get_row( 'SELECT * FROM ' . Arvator_Repo::table( 'license_sync' ) . " WHERE source_id = 'pay:inv:in_evt_{$tag}_k6'", ARRAY_A );
check( 'error' === $row['status'] && 'compte bloqué' === $row['last_error'] && (bool) array_filter( mails(), static fn( $x ) => 'support@arvator.com' === $x->to ), 'refus du serveur : noté et Hamid prévenu' );
@unlink( $n8n_fix );

// -- Résiliation de l'abonnement : licence coupée une seule fois
$before = count( $n8n_calls() );
$e      = $event( "evt_{$tag}_k7", 'customer.subscription.deleted', array( 'id' => "sub_$k1", 'status' => 'canceled', 'items' => array( 'data' => array( array( 'current_period_end' => 1910000000 ) ) ) ) );
$post_raw( $e, $sign( $e ) );
$e2 = $event( "evt_{$tag}_k7b", 'customer.subscription.deleted', array( 'id' => "sub_$k1", 'status' => 'canceled' ) );
$post_raw( $e2, $sign( $e2 ) );
$calls = $n8n_calls();
check( $before + 1 === count( $calls ) && 'resilier' === end( $calls )['body']['action'] && '7654321' === end( $calls )['body']['compte'], 'abonnement résilié : « resilier » envoyé une seule fois' );
check( 'canceled' === $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM ' . Arvator_Repo::table( 'licenses' ) . ' WHERE user_id = %d', $k1id ) ), 'licence affichée « résiliée »' );

// -- Achat sans numéro de compte : la licence attend, puis s'active depuis l'espace membre
@unlink( $n8n_log );
$k2 = "cs_k2_$tag";
$buy_hook( $k2, $ksess( $k2, "k2_$tag@example.org", '', 'monthly' ), "evt_{$tag}_k8" );
$k2u  = get_user_by( 'email', "k2_$tag@example.org" );
$k2id = (int) $k2u->ID;
check( 0 === count( $n8n_calls() ) && 'waiting_account' === ( $sync( $k2id )[0]['status'] ?? '' ), 'achat sans numéro de compte : rien n\'est envoyé, la licence attend' );
$kpw = 'Client-K2-Test-Only-' . $tag;
wp_set_password( $kpw, $k2id );
$kc = new Browser( $url );
member_login( $kc, "k2_$tag@example.org", $kpw );
check( str_contains( $kc->get( '/espace-membre/' )['body'], 'attend le numéro de votre compte cTrader' ), 'tableau de bord : rappel « indiquez votre numéro de compte »' );
$ls = $kc->get( '/espace-membre/?section=licence' );
$an = field_in_form( $ls['body'], 'arvator_account', '_arv_nonce' );
check( '' !== $an && str_contains( $ls['body'], 'Activer ma licence' ), 'espace membre : formulaire « Activer ma licence »' );
check( 'generic_invalid' === notice_of( $kc->post( '/wp-admin/admin-post.php', array( 'action' => 'arvator_account', '_arv_nonce' => 'faux', 'account' => '5550001' ) ) ), 'numéro de compte sans clé de sécurité valide refusé' );
check( 'compte_invalide' === notice_of( $kc->post( '/wp-admin/admin-post.php', array( 'action' => 'arvator_account', '_arv_nonce' => $an, 'account' => '55a' ) ) ), 'numéro de compte invalide refusé' );
check( 'compte_active' === notice_of( $kc->post( '/wp-admin/admin-post.php', array( 'action' => 'arvator_account', '_arv_nonce' => $an, 'account' => '5550001' ) ) ), 'numéro saisi : licence activée' );
$calls = $n8n_calls();
check( 1 === count( $calls ) && '5550001' === $calls[0]['body']['compte'] && 'mensuel' === $calls[0]['body']['plan'], 'envoi à n8n avec le bon compte et la formule mensuelle' );
$ls = $kc->get( '/espace-membre/?section=licence' );
check( str_contains( $ls['body'], 'Rien à copier' ) && str_contains( $ls['body'], '5550001' ) && ! str_contains( $ls['body'], 'arvator_account' ), 'licence affichée sans clé à copier ; le numéro n\'est plus modifiable seul' );
check( 'compte_verrouille' === notice_of( $kc->post( '/wp-admin/admin-post.php', array( 'action' => 'arvator_account', '_arv_nonce' => $an, 'account' => '5550002' ) ) ) && '5550001' === Arvator_License::account_for( $k2id ), 'changement de compte après activation : refusé (passe par Hamid)' );
$anon_acc = $anon->post( '/wp-admin/admin-post.php', array( 'action' => 'arvator_account', '_arv_nonce' => $an, 'account' => '5550003' ) );
check( ! str_contains( $anon_acc['location'], 'compte_' ), 'sans être connecté : refusé' );

// -- Remboursement : licence coupée une seule fois (même si Stripe envoie ensuite la résiliation)
@unlink( $n8n_log );
$set_fix( array( "DELETE subscriptions/sub_$k2" => array( 'id' => "sub_$k2", 'status' => 'canceled' ) ) );
$e = $event( "evt_{$tag}_k9", 'charge.refunded', array( 'id' => "ch_k2_$tag", 'customer' => "cus_$k2", 'invoice' => "in_$k2", 'amount_refunded' => 150000 ) );
$post_raw( $e, $sign( $e ) );
$e = $event( "evt_{$tag}_k10", 'customer.subscription.deleted', array( 'id' => "sub_$k2", 'status' => 'canceled' ) );
$post_raw( $e, $sign( $e ) );
$calls = $n8n_calls();
check( 1 === count( $calls ) && 'resilier' === $calls[0]['body']['action'], 'remboursement : « resilier » envoyé une seule fois' );

// -- Remboursement avant activation : rien à couper, l'attente est annulée
@unlink( $n8n_log );
$k3 = "cs_k3_$tag";
$buy_hook( $k3, $ksess( $k3, "k3_$tag@example.org", '' ), "evt_{$tag}_k11" );
$k3id = (int) get_user_by( 'email', "k3_$tag@example.org" )->ID;
$set_fix( array( "DELETE subscriptions/sub_$k3" => array( 'id' => "sub_$k3", 'status' => 'canceled' ) ) );
$e = $event( "evt_{$tag}_k12", 'charge.refunded', array( 'id' => "ch_k3_$tag", 'customer' => "cus_$k3", 'invoice' => "in_$k3", 'amount_refunded' => 150000 ) );
$post_raw( $e, $sign( $e ) );
check( 0 === count( $n8n_calls() ) && 'canceled' === ( $sync( $k3id )[0]['status'] ?? '' ), 'remboursé avant activation : aucun envoi, attente annulée' );
check( false === Arvator_License::set_account( $k3id, '5550009' ) || 0 === count( $n8n_calls() ), 'numéro saisi après remboursement : aucune licence créée' );

// -- Essai gratuit activé automatiquement
clear_transients();
$te = "essai_k_$tag@example.org";
$ta = (string) ( 200000 + (int) hexdec( substr( $tag, 0, 4 ) ) );
$wpdb->query( 'DELETE FROM ' . Arvator_Repo::table( 'trials' ) . " WHERE email LIKE 'essai_k%$tag@example.org'" );
@unlink( $n8n_log );
@unlink( $log );
$tb = new Browser( $url );
$tp = $tb->get( '/essai-gratuit/' );
$tq = array( 'action' => 'arvator_trial', '_arv_nonce' => field_in_form( $tp['body'], 'arvator_trial', '_arv_nonce' ), 'email' => $te, 'account' => $ta, 'waiver' => '1', 'consent' => '1' );
check( 'essai_actif' === notice_of( $tb->post( '/wp-admin/admin-post.php', $tq ) ), 'essai : activé automatiquement' );
$calls = $n8n_calls();
check( 1 === count( $calls ) && 'licence-essai' === $calls[0]['endpoint'] && $ta === $calls[0]['body']['compte'] && $te === $calls[0]['body']['email'], 'essai : demande envoyée au workflow « licence-essai »' );
check( 'active' === $wpdb->get_var( "SELECT status FROM " . Arvator_Repo::table( 'trials' ) . " WHERE email = '$te'" ), 'essai noté « actif »' );
$m = mails();
check( 2 === count( $m ) && str_contains( $m[0]->subject, 'robot à envoyer' ) && $te === $m[1]->to && str_contains( $m[1]->message, 'est activé' ) && str_contains( $m[1]->message, 'droit de rétractation' ), 'emails : Hamid (robot à envoyer) + client (essai activé, rappel de renonciation)' );
$n8n_set( array( 'licence-essai' => array( 'ok' => false, 'raison' => 'essai déjà utilisé' ) ) );
$tq2 = array( 'email' => "essai_k2_$tag@example.org", 'account' => (string) ( (int) $ta + 1 ) ) + $tq;
check( 'essai_refuse' === notice_of( $tb->post( '/wp-admin/admin-post.php', $tq2 ) ), 'essai refusé par le serveur : message clair au visiteur' );

// -- Nettoyage du bloc K
foreach ( array( "k1_$tag@example.org", "k2_$tag@example.org", "k3_$tag@example.org" ) as $em ) {
	$u = get_user_by( 'email', $em );
	if ( $u ) {
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . Arvator_Repo::table( 'license_sync' ) . ' WHERE user_id = %d', $u->ID ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . Arvator_Repo::table( 'licenses' ) . ' WHERE user_id = %d', $u->ID ) );
		Arvator_Repo::delete_test_rows_for_user( (int) $u->ID );
		wp_delete_user( (int) $u->ID );
	}
}
$wpdb->query( 'DELETE FROM ' . Arvator_Repo::table( 'orders' ) . " WHERE stripe_session_id LIKE '%$tag%'" );
$wpdb->query( 'DELETE FROM ' . Arvator_Repo::table( 'subscriptions' ) . " WHERE stripe_subscription_id LIKE '%$tag%'" );
$wpdb->query( 'DELETE FROM ' . Arvator_Repo::table( 'trials' ) . " WHERE email LIKE 'essai_k%$tag@example.org'" );
$wpdb->query( 'DELETE FROM ' . Arvator_Repo::table( 'events' ) . " WHERE external_id LIKE 'stripe:evt\\_{$tag}%'" );
$set_fix( array() );
@unlink( $n8n_fix );
@unlink( $n8n_log );
@unlink( $stripe_log );


/* ======================================================================== L. Courtiers compatibles (espace membre) */
title( 'L. Courtiers compatibles : espace membre seulement, liens sans affiliation' );
clear_transients();
check( 'https://www.icmarkets.eu/fr/' === Arvator_Brokers::clean_url( 'https://www.icmarkets.eu/fr/?camp=123&ref=abc#top' ), 'adresse nettoyée : paramètres d\'affiliation retirés automatiquement' );
check( '' === Arvator_Brokers::clean_url( 'http://exemple.com' ) && '' === Arvator_Brokers::clean_url( 'javascript:alert(1)' ) && '' === Arvator_Brokers::clean_url( 'pas une adresse' ), 'adresse non https ou invalide : refusée' );
[ $pl, $pbad ] = Arvator_Brokers::parse( "Pepperstone | https://pepperstone.com/?aff=9\n\n<b>Pirate</b> | javascript:alert(1)\n | https://vide.com" );
check( 1 === count( $pl ) && 'Pepperstone' === $pl[0]['name'] && 'https://pepperstone.com/' === $pl[0]['url'] && 2 === count( $pbad ), 'saisie admin : lignes valides gardées, lignes douteuses ignorées' );
check( 4 === count( Arvator_Brokers::all() ), 'liste de départ : 4 courtiers' );
foreach ( array( '/', '/faq/', '/acheter/', '/parrainage/' ) as $pg ) {
	$b = $anon->get( $pg )['body'];
	check( ! str_contains( $b, 'brokers__item' ) && ! str_contains( $b, 'icmarkets' ), "aucun courtier sur la page publique « $pg »" );
}
// Alice a un achat payé le temps de ce bloc (les blocs précédents ont nettoyé ses données de test).
$wpdb->insert( Arvator_Repo::table( 'orders' ), array( 'user_id' => $uA, 'stripe_session_id' => "cs_brokers_$tag", 'product' => 'cbot', 'amount_cents' => 150000, 'status' => 'paid', 'is_test' => 1, 'created_at' => $now ) );
$al = new Browser( $url );
member_login( $al, "ta$tag", $newA );
$cs = $al->get( '/espace-membre/?section=courtiers' );
check( 200 === $cs['code'] && str_contains( $cs['body'], 'class="brokers"' ) && str_contains( $cs['body'], '>Pepperstone</a>' ), 'client ayant acheté : bandeau des courtiers affiché' );
check( 8 === substr_count( $cs['body'], 'target="_blank" rel="noopener noreferrer nofollow"' ) && ! preg_match( '/brokers__item" href="[^"]*\?/', $cs['body'] ), 'liens : site officiel, nouvel onglet, sans paramètre' );
check( str_contains( $cs['body'], 'aucune commission' ) && str_contains( $cs['body'], 'REGAFI' ) && str_contains( $cs['body'], 'risque élevé de perte en capital' ), 'avertissements : pas de commission, vérifier REGAFI, risque' );
$nb = new Browser( $url );
member_login( $nb, "tc$tag", $pwC );
$cc = $nb->get( '/espace-membre/?section=courtiers' );
check( ! str_contains( $cc['body'], 'brokers__item' ) && str_contains( $cc['body'], 'après votre achat' ), 'client sans achat : pas de liste' );
$ca = $adm->get( '/wp-admin/admin.php?page=arvator-courtiers' );
check( 200 === $ca['code'] && str_contains( $ca['body'], 'Pepperstone | https://pepperstone.com/' ), 'administration : liste modifiable (Arvator > Courtiers)' );
$adm->post( '/wp-admin/admin-post.php', array( 'action' => 'arvator_brokers_save', '_arv_nonce' => field_in_form( $ca['body'], 'arvator_brokers_save', '_arv_nonce' ), 'brokers' => "FxPro | https://www.fxpro.com/?ib=77\nIC Markets | https://www.icmarkets.eu/" ) );
$fresh = static function (): void {
	// Le serveur de test a écrit l'option : on vide le cache de ce processus avant de la relire.
	wp_cache_delete( 'arvator_brokers', 'options' );
	wp_cache_delete( 'notoptions', 'options' );
	wp_cache_delete( 'alloptions', 'options' );
};
$fresh();
check( 2 === count( Arvator_Brokers::all() ) && 'https://www.fxpro.com/' === Arvator_Brokers::all()[0]['url'], 'administration : liste enregistrée, paramètre d\'affiliation retiré' );
$r = $al->post( '/wp-admin/admin-post.php', array( 'action' => 'arvator_brokers_save', '_arv_nonce' => field_in_form( $ca['body'], 'arvator_brokers_save', '_arv_nonce' ), 'brokers' => 'Pirate | https://pirate.example/' ) );
$fresh();
check( 2 === count( Arvator_Brokers::all() ), 'un client ne peut pas modifier la liste' );
delete_option( 'arvator_brokers' );
$wpdb->query( 'DELETE FROM ' . Arvator_Repo::table( 'orders' ) . " WHERE stripe_session_id = 'cs_brokers_$tag'" );


/* ======================================================================== M. Adresse de contact */
title( 'M. Adresse support@arvator.com et domaine arvator.com' );
check( 'support@arvator.com' === Arvator_Site::EMAIL && 'arvator.com' === Arvator_Site::DOMAIN, 'constantes : support@arvator.com et arvator.com' );
check( 'Arvator · https://arvator.com · support@arvator.com' === Arvator_Site::signature(), 'signature des emails : domaine et adresse' );
$src = '';
foreach ( array_merge( glob( ARVATOR_CORE_DIR . '*.php' ), glob( ARVATOR_CORE_DIR . 'includes/*.php' ), glob( ARVATOR_CORE_DIR . 'includes/content/*.php' ), glob( get_theme_root() . '/arvator/*.php' ), glob( get_theme_root() . '/arvator/inc/*.php' ) ) as $f ) {
	if ( ! str_ends_with( $f, 'class-site.php' ) ) {
		foreach ( token_get_all( (string) file_get_contents( $f ) ) as $t ) { // code seulement : les commentaires (exemples wp-config) ne comptent pas
			$src .= is_array( $t ) ? ( in_array( $t[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ? '' : $t[1] ) : $t;
		}
	}
}
check( ! str_contains( $src, 'bot.cosmartis' ) && ! str_contains( $src, 'contact@cosmartis' ) && ! str_contains( $src, "'support@arvator.com'" ), 'aucune adresse ni domaine écrit en dur hors de la constante' );
$sig  = Arvator_Site::signature();
$ours = array_filter( mails(), static fn( $x ) => str_starts_with( (string) $x->subject, 'Arvator' ) || str_starts_with( (string) $x->subject, '[Arvator]' ) );
check( count( $ours ) >= 1 && array() === array_filter( $ours, static fn( $x ) => ! str_ends_with( rtrim( (string) $x->message ), $sig ) ), 'tous les emails du site (' . count( $ours ) . ') se terminent par la signature' );
// Page déjà en ligne avec l'ancienne adresse, jamais modifiée par Hamid : le texte est mis à jour à l'installation.
$mlp = get_page_by_path( 'mentions-legales', OBJECT, 'page' );
$wpdb->update( $wpdb->posts, array( 'post_content' => 'Ancien texte : contact@cosmartis.com' ), array( 'ID' => $mlp->ID ) );
clean_post_cache( $mlp->ID );
update_option( 'arvator_pages_rev', array( 'mentions-legales' => 1 ) + (array) get_option( 'arvator_pages_rev', array() ) );
Arvator_Pages::create_missing();
$mlc = (string) get_post( $mlp->ID )->post_content;
check( str_contains( $mlc, 'support@arvator.com' ) && ! str_contains( $mlc, 'contact@cosmartis.com' ), 'page jamais modifiée : nouvelle adresse mise en place automatiquement' );
foreach ( array( 'contact', 'mentions-legales', 'confidentialite', 'cgv', 'faq', 'a-propos' ) as $pg ) {
	$pb = $anon->get( '/' . $pg . '/' )['body'];
	check( str_contains( $pb, 'support@arvator.com' ) && ! str_contains( $pb, 'contact@cosmartis.com' ), "page « $pg » : nouvelle adresse, plus l'ancienne" );
}
foreach ( array( 'mentions-legales', 'cgv' ) as $pg ) {
	$pb = $anon->get( '/' . $pg . '/' )['body'];
	check( str_contains( $pb, 'arvator.com' ) && ! str_contains( $pb, 'bot.cosmartis.com' ), "page « $pg » : domaine arvator.com, plus bot.cosmartis.com" );
}

/* ======================================================================== N. Éditeur : photo, réseaux sociaux, adresse */
title( 'N. Éditeur : photo, réseaux sociaux, adresse postale' );
delete_option( 'arvator_social_facebook' );
delete_option( 'arvator_social_x' );
delete_option( 'arvator_social_linkedin' );
foreach ( array( 'a-propos', 'mentions-legales' ) as $pg ) {
	$pb = $anon->get( '/' . $pg . '/' )['body'];
	check( str_contains( $pb, 'class="author-card"' ) && str_contains( $pb, 'hamid-dibarh.jpg' ) && str_contains( $pb, 'Hamid DIBARH' ), "page « $pg » : photo et nom de l'éditeur" );
	check( str_contains( $pb, 'href="https://www.linkedin.com/in/hamid-dibarh-1936b7200"' ), "page « $pg » : lien LinkedIn" );
	check( str_contains( $pb, 'href="https://t.me/Bot_Arvator"' ), "page « $pg » : lien Telegram" );
	check( ! str_contains( $pb, 'social-links__item--facebook' ) && ! str_contains( $pb, 'social-links__item--x' ), "page « $pg » : Facebook et X masqués tant qu'ils n'ont pas de lien" );
}
$hp = $anon->get( '/' )['body'];
check( 1 === preg_match( '#<footer.*social-links--footer.*linkedin\.com/in/hamid-dibarh-1936b7200#s', $hp ), 'pied de page : lien LinkedIn' );
check( 1 === preg_match( '#<footer.*social-links--footer.*t\.me/Bot_Arvator#s', $hp ), 'pied de page : lien Telegram' );
check( 0 === substr_count( $hp, 'author-card' ), "pied de page : pas de photo" );
$photo = $anon->get( '/wp-content/plugins/arvator-core/assets/hamid-dibarh.jpg' );
check( 200 === $photo['code'] && strlen( $photo['body'] ) > 20000, 'photo livrée avec le plugin accessible' );
update_option( 'arvator_social_facebook', 'https://www.facebook.com/arvator' );
update_option( 'arvator_social_x', 'https://x.com/arvator' );
$hp = $anon->get( '/a-propos/' )['body'];
check( str_contains( $hp, 'href="https://www.facebook.com/arvator"' ) && str_contains( $hp, 'href="https://x.com/arvator"' ), 'liens Facebook et X affichés une fois renseignés' );
check( '' === Arvator_Author::clean_url( 'https://pirate.example/facebook.com', array( 'facebook.com' ) ) && '' === Arvator_Author::clean_url( 'http://www.facebook.com/a', array( 'facebook.com' ) ) && '' === Arvator_Author::clean_url( 'javascript:alert(1)', array( 'x.com' ) ), 'lien refusé : mauvais site, http ou javascript' );
check( '' === Arvator_Author::clean_url( 'https://t.me.pirate.example/Bot_Arvator', array( 't.me', 'telegram.me' ) ), 'lien Telegram refusé : faux domaine' );
check( 'https://www.facebook.com/arvator' === Arvator_Author::clean_url( 'https://www.facebook.com/arvator?fbclid=abc#x', array( 'facebook.com' ) ), 'paramètres de suivi retirés du lien' );
update_option( 'arvator_social_linkedin', '' );
check( ! str_contains( $anon->get( '/a-propos/' )['body'], 'linkedin.com' ), 'lien LinkedIn vidé : réseau masqué' );
delete_option( 'arvator_social_facebook' );
delete_option( 'arvator_social_x' );
delete_option( 'arvator_social_linkedin' );
foreach ( array( 'mentions-legales', 'cgv', 'confidentialite' ) as $pg ) {
	check( str_contains( $anon->get( '/' . $pg . '/' )['body'], '21 rue Taffin, 59970 Fresnes-sur-Escaut' ), "page « $pg » : adresse postale complète" );
}

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

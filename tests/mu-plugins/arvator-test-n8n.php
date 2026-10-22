<?php
// Test local uniquement : faux workflow n8n (aucun appel réel, aucune vraie licence créée).
// Réponse par point d'entrée lue dans ARV_N8N_FIXTURES ({"licence-paiement": {...}} ; {"__down": true} = n8n injoignable).
// Sans fixture : réponse « ok ». Chaque appel est noté (une ligne JSON) dans ARV_N8N_LOG.
foreach ( array(
	'ARVATOR_N8N_URL'   => 'https://n8n.test/webhook',
	'ARVATOR_N8N_TOKEN' => 'tok_test_fake',
) as $k => $v ) {
	if ( ! defined( $k ) ) {
		define( $k, $v );
	}
}
add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) {
		if ( ! str_starts_with( $url, 'https://n8n.test/webhook/' ) ) {
			return $pre;
		}
		$endpoint = substr( $url, strlen( 'https://n8n.test/webhook/' ) );
		$body     = json_decode( (string) ( $args['body'] ?? '' ), true );
		$log      = getenv( 'ARV_N8N_LOG' ) ?: sys_get_temp_dir() . '/arvator-n8n.log';
		file_put_contents( $log, wp_json_encode( array( 'endpoint' => $endpoint, 'body' => $body, 'token' => $args['headers']['x-admin-token'] ?? '' ) ) . "\n", FILE_APPEND );
		$file = getenv( 'ARV_N8N_FIXTURES' ) ?: sys_get_temp_dir() . '/arvator-n8n-fixtures.json';
		$fix  = is_file( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : array();
		$resp = $fix[ $endpoint ] ?? array( 'ok' => true, 'compte' => $body['compte'] ?? '', 'plan' => $body['plan'] ?? '', 'expiration' => '2030-01-15T10:00:00.000Z' );
		if ( ! empty( $resp['__down'] ) ) {
			return new WP_Error( 'http_request_failed', 'cURL error 28: timeout' );
		}
		return array( 'headers' => array(), 'cookies' => array(), 'filename' => null, 'response' => array( 'code' => 200, 'message' => 'OK' ), 'body' => wp_json_encode( $resp ) );
	},
	10,
	3
);

<?php
// Test local uniquement : fausse API Stripe (aucun appel réel) + réglages Stripe fictifs.
// Les réponses viennent d'un fichier JSON (ARV_STRIPE_FIXTURES), les appels sont notés dans ARV_STRIPE_LOG.
foreach ( array(
	'ARVATOR_STRIPE_SECRET'         => 'rk_test_fake',
	'ARVATOR_STRIPE_WEBHOOK_SECRET' => 'whsec_test_fake',
	'ARVATOR_STRIPE_PRICE_BOT'      => 'price_bot_test',
	'ARVATOR_STRIPE_PRICE_ANNUAL'   => 'price_annual_test',
	'ARVATOR_STRIPE_PRICE_MONTHLY'  => 'price_monthly_test',
) as $k => $v ) {
	if ( ! defined( $k ) ) {
		define( $k, $v );
	}
}
add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) {
		if ( ! str_starts_with( $url, 'https://api.stripe.com/v1/' ) ) {
			return $pre;
		}
		$path = substr( strtok( $url, '?' ), strlen( 'https://api.stripe.com/v1/' ) );
		$key  = $args['method'] . ' ' . $path;
		$log  = getenv( 'ARV_STRIPE_LOG' ) ?: sys_get_temp_dir() . '/arvator-stripe.log';
		file_put_contents( $log, wp_json_encode( array( 'key' => $key, 'body' => $args['body'] ?? '', 'query' => (string) wp_parse_url( $url, PHP_URL_QUERY ), 'auth' => $args['headers']['Authorization'] ?? '' ) ) . "\n", FILE_APPEND );
		$file = getenv( 'ARV_STRIPE_FIXTURES' ) ?: sys_get_temp_dir() . '/arvator-stripe-fixtures.json';
		$fix  = is_file( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : array();
		if ( ! isset( $fix[ $key ] ) ) {
			return array( 'headers' => array(), 'cookies' => array(), 'filename' => null, 'response' => array( 'code' => 404, 'message' => 'Not Found' ), 'body' => wp_json_encode( array( 'error' => array( 'message' => 'No such fixture: ' . $key ) ) ) );
		}
		return array( 'headers' => array(), 'cookies' => array(), 'filename' => null, 'response' => array( 'code' => 200, 'message' => 'OK' ), 'body' => wp_json_encode( $fix[ $key ] ) );
	},
	10,
	3
);

<?php
// Test local uniquement : aucun email n'est envoyé, chacun est noté (une ligne JSON) dans ARV_MAILLOG.
add_filter(
	'pre_wp_mail',
	static function ( $null, $atts ) {
		$log = getenv( 'ARV_MAILLOG' ) ?: sys_get_temp_dir() . '/arvator-mail.log';
		file_put_contents( $log, wp_json_encode( array( 'to' => is_array( $atts['to'] ) ? implode( ',', $atts['to'] ) : $atts['to'], 'subject' => $atts['subject'], 'message' => $atts['message'], 'headers' => $atts['headers'] ) ) . "\n", FILE_APPEND );
		return true;
	},
	10,
	2
);

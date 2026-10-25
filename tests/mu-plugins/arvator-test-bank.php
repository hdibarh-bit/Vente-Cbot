<?php
// Test local uniquement : coordonnées bancaires et secret FICTIFS pour le paiement par virement (IBAN d'exemple valide).
foreach ( array(
	'ARVATOR_VIREMENT_TITULAIRE' => 'Hamid DIBARH (test)',
	'ARVATOR_VIREMENT_IBAN'      => 'FR1420041010050500013M02606',
	'ARVATOR_VIREMENT_BIC'       => 'PSSTFRPPXXX',
	'ARVATOR_VIREMENT_SECRET'    => 'secret-de-test-virement-0123456789',
) as $k => $v ) {
	if ( ! defined( $k ) ) {
		define( $k, $v );
	}
}

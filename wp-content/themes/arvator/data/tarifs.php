<?php
// Tarifs et réglages publics — fichier généré par bin/sync-donnees.php. Ne pas modifier à la main.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array (
  'produit' => 
  array (
    'nom' => 'Arvator',
  ),
  'prix' => 
  array (
    'achatCbot' => 1500,
    'abonnementMensuel' => 39.9,
    'abonnementAnnuelTotal' => 358.8,
    'abonnementAnnuelParMois' => 29.9,
    'economieAnnuelle' => 120,
    'devise' => 'EUR',
    'mentionTvaAffichee' => false,
  ),
  'capitalConseille' => 2000,
  'risque' => 
  array (
    'defaut' => 1,
    'choix' => 
    array (
      0 => 1,
      1 => 2,
      2 => 3,
    ),
    'plafond' => 3,
  ),
  'graceJours' => 3,
  'essaiJours' => 14,
  'parrainage' => 
  array (
    'primeParAchat' => 225,
    'delaiPaiementJours' => 30,
  ),
);

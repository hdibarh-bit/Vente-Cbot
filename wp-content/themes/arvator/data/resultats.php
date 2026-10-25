<?php
// Résultats de backtest (performances simulées) — fichier généré par bin/sync-donnees.php. Ne pas modifier à la main.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array (
  'periode' => 
  array (
    'debut' => '2024-01-01',
    'fin' => '2026-10-25',
  ),
  'devise' => 'EUR',
  'livraison' => 
  array (
    'risqueParDefautPct' => 1,
    'risqueMaxPct' => 3,
    'choix' => 
    array (
      0 => 1,
      1 => 2,
      2 => 3,
    ),
  ),
  'methode' => 
  array (
    'resume' => 'Compte unique ouvert le 01/01/2024 et suivi jusqu\'au 25/10/2026, sans retrait ; frais IC Markets EU Raw Spread (cTrader) pris comme exemple de courtier (spreads, commission, swaps de nuit, taille minimale de position) ; réglage livré : 1 perte maximum par jour, 3 positions maximum en même temps ; trades refusés comptés.',
    'pertesMaxParJour' => 1,
    'positionsMax' => 3,
    'courtierExemple' => 'IC Markets (compte Raw Spread, cTrader)',
  ),
  'reference' => 
  array (
    'capital' => 5000,
    'risquePct' => 1,
    'soldeFinal' => 15202,
    'gainPct' => 204,
    'gainEur' => 10202,
    'pireBaissePct' => -20.1,
    'pireBaisseEur' => -3771,
    'debutBaisse' => '2026-09-02',
    'finBaisse' => '2026-10-21',
    'baisseEnCours' => true,
    'tradesPris' => 771,
    'partGagnantsPct' => 36.7,
    'tradesRefuses' => 1,
    'annees' => 
    array (
      0 => 
      array (
        'annee' => '2024',
        'soldeDebut' => 5000,
        'soldeFin' => 8036,
        'gainPct' => 60.7,
        'gainEur' => 3036,
        'pireBaissePct' => -17.3,
        'pireBaisseEur' => -1022,
        'debutBaisse' => '2024-04-01',
        'finBaisse' => '2024-05-09',
        'retourPlusHaut' => '2024-09-19',
        'enCours' => false,
      ),
      1 => 
      array (
        'annee' => '2025',
        'soldeDebut' => 8033,
        'soldeFin' => 11411,
        'gainPct' => 42.0,
        'gainEur' => 3378,
        'pireBaissePct' => -14.9,
        'pireBaisseEur' => -1349,
        'debutBaisse' => '2025-02-28',
        'finBaisse' => '2025-03-27',
        'retourPlusHaut' => '2025-04-30',
        'enCours' => false,
      ),
      2 => 
      array (
        'annee' => '2026',
        'soldeDebut' => 11411,
        'soldeFin' => 15202,
        'gainPct' => 33.2,
        'gainEur' => 3791,
        'pireBaissePct' => -20.1,
        'pireBaisseEur' => -3771,
        'debutBaisse' => '2026-09-02',
        'finBaisse' => '2026-10-21',
        'retourPlusHaut' => NULL,
        'enCours' => true,
      ),
    ),
  ),
  'tableaux' => 
  array (
    'r1' => 
    array (
      0 => 
      array (
        'capital' => 500,
        'soldeFinal' => 1415,
        'gainPct' => 183,
        'pireBaissePct' => -20.0,
        'pireBaisseEur' => -312,
        'debutBaisse' => '2026-05-06',
        'finBaisse' => '2026-08-19',
        'tradesRefuses' => 1745,
      ),
      1 => 
      array (
        'capital' => 1000,
        'soldeFinal' => 2248,
        'gainPct' => 125,
        'pireBaissePct' => -29.1,
        'pireBaisseEur' => -898,
        'debutBaisse' => '2026-05-06',
        'finBaisse' => '2026-10-20',
        'tradesRefuses' => 415,
      ),
      2 => 
      array (
        'capital' => 2000,
        'soldeFinal' => 6633,
        'gainPct' => 232,
        'pireBaissePct' => -18.2,
        'pireBaisseEur' => -1454,
        'debutBaisse' => '2026-09-02',
        'finBaisse' => '2026-10-21',
        'tradesRefuses' => 20,
      ),
      3 => 
      array (
        'capital' => 4000,
        'soldeFinal' => 12324,
        'gainPct' => 208,
        'pireBaissePct' => -19.8,
        'pireBaisseEur' => -2997,
        'debutBaisse' => '2026-09-02',
        'finBaisse' => '2026-10-21',
        'tradesRefuses' => 1,
      ),
      4 => 
      array (
        'capital' => 5000,
        'soldeFinal' => 15202,
        'gainPct' => 204,
        'pireBaissePct' => -20.1,
        'pireBaisseEur' => -3771,
        'debutBaisse' => '2026-09-02',
        'finBaisse' => '2026-10-21',
        'tradesRefuses' => 1,
      ),
      5 => 
      array (
        'capital' => 7500,
        'soldeFinal' => 24542,
        'gainPct' => 227,
        'pireBaissePct' => -19.7,
        'pireBaisseEur' => -5902,
        'debutBaisse' => '2026-09-02',
        'finBaisse' => '2026-10-21',
        'tradesRefuses' => 1,
      ),
      6 => 
      array (
        'capital' => 10000,
        'soldeFinal' => 31131,
        'gainPct' => 211,
        'pireBaissePct' => -20.0,
        'pireBaisseEur' => -7634,
        'debutBaisse' => '2026-09-02',
        'finBaisse' => '2026-10-21',
        'tradesRefuses' => 0,
      ),
      7 => 
      array (
        'capital' => 15000,
        'soldeFinal' => 46958,
        'gainPct' => 213,
        'pireBaissePct' => -19.9,
        'pireBaisseEur' => -11460,
        'debutBaisse' => '2026-09-02',
        'finBaisse' => '2026-10-21',
        'tradesRefuses' => 0,
      ),
      8 => 
      array (
        'capital' => 20000,
        'soldeFinal' => 63089,
        'gainPct' => 215,
        'pireBaissePct' => -20.0,
        'pireBaisseEur' => -15482,
        'debutBaisse' => '2026-09-02',
        'finBaisse' => '2026-10-21',
        'tradesRefuses' => 0,
      ),
    ),
    'r2' => 
    array (
      0 => 
      array (
        'capital' => 500,
        'soldeFinal' => 4264,
        'gainPct' => 753,
        'pireBaissePct' => -34.8,
        'pireBaisseEur' => -2194,
        'debutBaisse' => '2026-09-02',
        'finBaisse' => '2026-10-21',
        'tradesRefuses' => 118,
      ),
      1 => 
      array (
        'capital' => 1000,
        'soldeFinal' => 7287,
        'gainPct' => 629,
        'pireBaissePct' => -35.0,
        'pireBaisseEur' => -3795,
        'debutBaisse' => '2026-09-02',
        'finBaisse' => '2026-10-21',
        'tradesRefuses' => 4,
      ),
      2 => 
      array (
        'capital' => 2000,
        'soldeFinal' => 14029,
        'gainPct' => 601,
        'pireBaissePct' => -35.1,
        'pireBaisseEur' => -7331,
        'debutBaisse' => '2026-09-02',
        'finBaisse' => '2026-10-21',
        'tradesRefuses' => 0,
      ),
      3 => 
      array (
        'capital' => 4000,
        'soldeFinal' => 29148,
        'gainPct' => 629,
        'pireBaissePct' => -35.6,
        'pireBaisseEur' => -15512,
        'debutBaisse' => '2026-09-02',
        'finBaisse' => '2026-10-21',
        'tradesRefuses' => 0,
      ),
      4 => 
      array (
        'capital' => 5000,
        'soldeFinal' => 38402,
        'gainPct' => 668,
        'pireBaissePct' => -35.6,
        'pireBaisseEur' => -20451,
        'debutBaisse' => '2026-09-02',
        'finBaisse' => '2026-10-21',
        'tradesRefuses' => 0,
      ),
      5 => 
      array (
        'capital' => 7500,
        'soldeFinal' => 55862,
        'gainPct' => 645,
        'pireBaissePct' => -35.7,
        'pireBaisseEur' => -29790,
        'debutBaisse' => '2026-09-02',
        'finBaisse' => '2026-10-21',
        'tradesRefuses' => 0,
      ),
      6 => 
      array (
        'capital' => 10000,
        'soldeFinal' => 76018,
        'gainPct' => 660,
        'pireBaissePct' => -35.7,
        'pireBaisseEur' => -40612,
        'debutBaisse' => '2026-09-02',
        'finBaisse' => '2026-10-21',
        'tradesRefuses' => 0,
      ),
      7 => 
      array (
        'capital' => 15000,
        'soldeFinal' => 112004,
        'gainPct' => 647,
        'pireBaissePct' => -35.7,
        'pireBaisseEur' => -59939,
        'debutBaisse' => '2026-09-02',
        'finBaisse' => '2026-10-21',
        'tradesRefuses' => 0,
      ),
      8 => 
      array (
        'capital' => 20000,
        'soldeFinal' => 149516,
        'gainPct' => 648,
        'pireBaissePct' => -35.7,
        'pireBaisseEur' => -80031,
        'debutBaisse' => '2026-09-02',
        'finBaisse' => '2026-10-21',
        'tradesRefuses' => 0,
      ),
    ),
    'r3' => 
    array (
      0 => 
      array (
        'capital' => 500,
        'soldeFinal' => 6350,
        'gainPct' => 1170,
        'pireBaissePct' => -47.6,
        'pireBaisseEur' => -5459,
        'debutBaisse' => '2026-09-02',
        'finBaisse' => '2026-10-21',
        'tradesRefuses' => 33,
      ),
      1 => 
      array (
        'capital' => 1000,
        'soldeFinal' => 15310,
        'gainPct' => 1431,
        'pireBaissePct' => -48.2,
        'pireBaisseEur' => -13467,
        'debutBaisse' => '2026-09-02',
        'finBaisse' => '2026-10-21',
        'tradesRefuses' => 0,
      ),
      2 => 
      array (
        'capital' => 2000,
        'soldeFinal' => 30557,
        'gainPct' => 1428,
        'pireBaissePct' => -48.1,
        'pireBaisseEur' => -26799,
        'debutBaisse' => '2026-09-02',
        'finBaisse' => '2026-10-21',
        'tradesRefuses' => 0,
      ),
      3 => 
      array (
        'capital' => 4000,
        'soldeFinal' => 63810,
        'gainPct' => 1495,
        'pireBaissePct' => -48.1,
        'pireBaisseEur' => -55887,
        'debutBaisse' => '2026-09-02',
        'finBaisse' => '2026-10-21',
        'tradesRefuses' => 0,
      ),
      4 => 
      array (
        'capital' => 5000,
        'soldeFinal' => 82120,
        'gainPct' => 1542,
        'pireBaissePct' => -48.2,
        'pireBaisseEur' => -72097,
        'debutBaisse' => '2026-09-02',
        'finBaisse' => '2026-10-21',
        'tradesRefuses' => 0,
      ),
      5 => 
      array (
        'capital' => 7500,
        'soldeFinal' => 118893,
        'gainPct' => 1485,
        'pireBaissePct' => -48.1,
        'pireBaisseEur' => -104130,
        'debutBaisse' => '2026-09-02',
        'finBaisse' => '2026-10-21',
        'tradesRefuses' => 0,
      ),
      6 => 
      array (
        'capital' => 10000,
        'soldeFinal' => 161006,
        'gainPct' => 1510,
        'pireBaissePct' => -48.2,
        'pireBaisseEur' => -141204,
        'debutBaisse' => '2026-09-02',
        'finBaisse' => '2026-10-21',
        'tradesRefuses' => 0,
      ),
      7 => 
      array (
        'capital' => 15000,
        'soldeFinal' => 242328,
        'gainPct' => 1516,
        'pireBaissePct' => -48.2,
        'pireBaisseEur' => -212283,
        'debutBaisse' => '2026-09-02',
        'finBaisse' => '2026-10-21',
        'tradesRefuses' => 0,
      ),
      8 => 
      array (
        'capital' => 20000,
        'soldeFinal' => 323233,
        'gainPct' => 1516,
        'pireBaissePct' => -48.2,
        'pireBaisseEur' => -283259,
        'debutBaisse' => '2026-09-02',
        'finBaisse' => '2026-10-21',
        'tradesRefuses' => 0,
      ),
    ),
  ),
  'noteSmallCapital' => 'En dessous de 2 000 €, la taille minimale de position du courtier oblige le robot à refuser une partie des trades. Les résultats des petits capitaux sont donc moins réguliers. Exemple : au risque 1 %, 1 000 € fait moins bien que 500 €.',
  'limitesCommunes' => 
  array (
    0 => 'Il s\'agit d\'un backtest : des performances simulées, qui ne préjugent pas des résultats futurs.',
    1 => 'Les réglages ont été choisis sur le même historique que celui qui les teste : les résultats futurs seront probablement moins bons.',
    2 => 'Il n\'y a pas encore d\'historique en argent réel : les bots tournent en démo depuis septembre 2026.',
    3 => 'L\'avenir peut être très positif, nul ou négatif, avec une perte possible de tout le capital engagé.',
  ),
  'capitalConseille' => 
  array (
    'montant' => 2000,
    'obligatoire' => false,
  ),
);

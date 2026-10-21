<?php
// Résultats de backtest (performances simulées) — fichier généré par bin/sync-donnees.php. Ne pas modifier à la main.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array (
  'periode' => 
  array (
    'debut' => '2024-01-01',
    'fin' => '2026-10-07',
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
  'noteMethode' => 'Chaque année est simulée seule, en repartant du capital de départ au 1er janvier ; la colonne « 2024 → 2026 » est l\'enchaînement sur la période, sans retrait. 2026 s\'arrête au 7 octobre. Soldes de fin de mois.',
  'capitaux' => 
  array (
    0 => 500,
    1 => 1000,
    2 => 2000,
    3 => 5000,
    4 => 7500,
    5 => 10000,
  ),
  'risques' => 
  array (
    0 => 
    array (
      'risquePct' => 1,
      'livreParDefaut' => true,
      'lignes' => 
      array (
        0 => 
        array (
          'capital' => 500,
          'troisAns' => 
          array (
            'soldeFinal' => 1301,
            'multiplicateur' => 2.6,
            'gainPct' => 160,
            'pireBaissePct' => -12,
            'plusBasDuCompte' => 554,
          ),
          'parAnnee' => 
          array (
            2024 => 
            array (
              'soldeFinal' => 857,
              'gainPct' => 71,
            ),
            2025 => 
            array (
              'soldeFinal' => 529,
              'gainPct' => 6,
            ),
            '2026_au_7_oct' => 
            array (
              'soldeFinal' => 491,
              'gainPct' => -2,
            ),
          ),
          'tradesRefuses' => 3760,
          'tradesPris' => 520,
          'pireMois' => 
          array (
            0 => 
            array (
              'periode' => '2024',
              'mois' => 'avr. 2024',
              'moisPct' => -4,
              'soldeDebutMois' => 660,
              'soldeFinMois' => 632,
              'pireBaissePct' => -4,
              'plusBas' => 554,
              'moisPerdants' => 2,
              'moisTotal' => 12,
            ),
            1 => 
            array (
              'periode' => '2025',
              'mois' => 'avr. 2025',
              'moisPct' => -7,
              'soldeDebutMois' => 507,
              'soldeFinMois' => 471,
              'pireBaissePct' => -9,
              'plusBas' => 471,
              'moisPerdants' => 6,
              'moisTotal' => 12,
            ),
            2 => 
            array (
              'periode' => '2026',
              'mois' => 'mai 2026',
              'moisPct' => -5,
              'soldeDebutMois' => 527,
              'soldeFinMois' => 502,
              'pireBaissePct' => -11,
              'plusBas' => 491,
              'moisPerdants' => 5,
              'moisTotal' => 10,
            ),
            3 => 
            array (
              'periode' => '2024-2026',
              'mois' => 'déc. 2025',
              'moisPct' => -9,
              'soldeDebutMois' => 1252,
              'soldeFinMois' => 1135,
              'pireBaissePct' => -12,
              'plusBas' => 554,
              'moisPerdants' => 12,
              'moisTotal' => 34,
            ),
          ),
        ),
        1 => 
        array (
          'capital' => 1000,
          'troisAns' => 
          array (
            'soldeFinal' => 2135,
            'multiplicateur' => 2.1,
            'gainPct' => 113,
            'pireBaissePct' => -12,
            'plusBasDuCompte' => 988,
          ),
          'parAnnee' => 
          array (
            2024 => 
            array (
              'soldeFinal' => 1325,
              'gainPct' => 32,
            ),
            2025 => 
            array (
              'soldeFinal' => 1409,
              'gainPct' => 41,
            ),
            '2026_au_7_oct' => 
            array (
              'soldeFinal' => 1164,
              'gainPct' => 16,
            ),
          ),
          'tradesRefuses' => 2776,
          'tradesPris' => 669,
          'pireMois' => 
          array (
            0 => 
            array (
              'periode' => '2024',
              'mois' => 'juin 2024',
              'moisPct' => -12,
              'soldeDebutMois' => 1252,
              'soldeFinMois' => 1100,
              'pireBaissePct' => -12,
              'plusBas' => 988,
              'moisPerdants' => 5,
              'moisTotal' => 12,
            ),
            1 => 
            array (
              'periode' => '2025',
              'mois' => 'déc. 2025',
              'moisPct' => -11,
              'soldeDebutMois' => 1587,
              'soldeFinMois' => 1409,
              'pireBaissePct' => -11,
              'plusBas' => 1110,
              'moisPerdants' => 3,
              'moisTotal' => 12,
            ),
            2 => 
            array (
              'periode' => '2026',
              'mois' => 'juil. 2026',
              'moisPct' => -5,
              'soldeDebutMois' => 1193,
              'soldeFinMois' => 1134,
              'pireBaissePct' => -6,
              'plusBas' => 1070,
              'moisPerdants' => 5,
              'moisTotal' => 10,
            ),
            3 => 
            array (
              'periode' => '2024-2026',
              'mois' => 'juin 2024',
              'moisPct' => -12,
              'soldeDebutMois' => 1252,
              'soldeFinMois' => 1100,
              'pireBaissePct' => -12,
              'plusBas' => 988,
              'moisPerdants' => 14,
              'moisTotal' => 34,
            ),
          ),
        ),
        2 => 
        array (
          'capital' => 2000,
          'troisAns' => 
          array (
            'soldeFinal' => 6202,
            'multiplicateur' => 3.1,
            'gainPct' => 210,
            'pireBaissePct' => -10,
            'plusBasDuCompte' => 2061,
          ),
          'parAnnee' => 
          array (
            2024 => 
            array (
              'soldeFinal' => 2819,
              'gainPct' => 41,
            ),
            2025 => 
            array (
              'soldeFinal' => 3373,
              'gainPct' => 69,
            ),
            '2026_au_7_oct' => 
            array (
              'soldeFinal' => 2256,
              'gainPct' => 13,
            ),
          ),
          'tradesRefuses' => 1819,
          'tradesPris' => 761,
          'pireMois' => 
          array (
            0 => 
            array (
              'periode' => '2024',
              'mois' => 'juin 2024',
              'moisPct' => -8,
              'soldeDebutMois' => 2491,
              'soldeFinMois' => 2289,
              'pireBaissePct' => -8,
              'plusBas' => 2061,
              'moisPerdants' => 3,
              'moisTotal' => 12,
            ),
            1 => 
            array (
              'periode' => '2025',
              'mois' => 'déc. 2025',
              'moisPct' => -8,
              'soldeDebutMois' => 3676,
              'soldeFinMois' => 3373,
              'pireBaissePct' => -8,
              'plusBas' => 2192,
              'moisPerdants' => 3,
              'moisTotal' => 12,
            ),
            2 => 
            array (
              'periode' => '2026',
              'mois' => 'juil. 2026',
              'moisPct' => -7,
              'soldeDebutMois' => 2398,
              'soldeFinMois' => 2223,
              'pireBaissePct' => -10,
              'plusBas' => 2223,
              'moisPerdants' => 4,
              'moisTotal' => 10,
            ),
            3 => 
            array (
              'periode' => '2024-2026',
              'mois' => 'déc. 2025',
              'moisPct' => -9,
              'soldeDebutMois' => 4825,
              'soldeFinMois' => 4371,
              'pireBaissePct' => -10,
              'plusBas' => 2061,
              'moisPerdants' => 10,
              'moisTotal' => 34,
            ),
          ),
        ),
        3 => 
        array (
          'capital' => 5000,
          'troisAns' => 
          array (
            'soldeFinal' => 23186,
            'multiplicateur' => 4.6,
            'gainPct' => 364,
            'pireBaissePct' => -14,
            'plusBasDuCompte' => 5300,
          ),
          'parAnnee' => 
          array (
            2024 => 
            array (
              'soldeFinal' => 9013,
              'gainPct' => 80,
            ),
            2025 => 
            array (
              'soldeFinal' => 8646,
              'gainPct' => 73,
            ),
            '2026_au_7_oct' => 
            array (
              'soldeFinal' => 6672,
              'gainPct' => 33,
            ),
          ),
          'tradesRefuses' => 543,
          'tradesPris' => 863,
          'pireMois' => 
          array (
            0 => 
            array (
              'periode' => '2024',
              'mois' => 'avr. 2024',
              'moisPct' => -10,
              'soldeDebutMois' => 6174,
              'soldeFinMois' => 5564,
              'pireBaissePct' => -14,
              'plusBas' => 5300,
              'moisPerdants' => 3,
              'moisTotal' => 12,
            ),
            1 => 
            array (
              'periode' => '2025',
              'mois' => 'déc. 2025',
              'moisPct' => -3,
              'soldeDebutMois' => 8938,
              'soldeFinMois' => 8646,
              'pireBaissePct' => -3,
              'plusBas' => 5078,
              'moisPerdants' => 1,
              'moisTotal' => 12,
            ),
            2 => 
            array (
              'periode' => '2026',
              'mois' => 'juil. 2026',
              'moisPct' => -9,
              'soldeDebutMois' => 7161,
              'soldeFinMois' => 6513,
              'pireBaissePct' => -10,
              'plusBas' => 6430,
              'moisPerdants' => 4,
              'moisTotal' => 10,
            ),
            3 => 
            array (
              'periode' => '2024-2026',
              'mois' => 'avr. 2024',
              'moisPct' => -10,
              'soldeDebutMois' => 6174,
              'soldeFinMois' => 5564,
              'pireBaissePct' => -14,
              'plusBas' => 5300,
              'moisPerdants' => 10,
              'moisTotal' => 34,
            ),
          ),
        ),
        4 => 
        array (
          'capital' => 7500,
          'troisAns' => 
          array (
            'soldeFinal' => 26489,
            'multiplicateur' => 3.5,
            'gainPct' => 253,
            'pireBaissePct' => -30,
            'plusBasDuCompte' => 6104,
          ),
          'parAnnee' => 
          array (
            2024 => 
            array (
              'soldeFinal' => 10220,
              'gainPct' => 36,
            ),
            2025 => 
            array (
              'soldeFinal' => 16670,
              'gainPct' => 122,
            ),
            '2026_au_7_oct' => 
            array (
              'soldeFinal' => 9645,
              'gainPct' => 29,
            ),
          ),
          'tradesRefuses' => 339,
          'tradesPris' => 875,
          'pireMois' => 
          array (
            0 => 
            array (
              'periode' => '2024',
              'mois' => 'avr. 2024',
              'moisPct' => -17,
              'soldeDebutMois' => 8428,
              'soldeFinMois' => 6991,
              'pireBaissePct' => -30,
              'plusBas' => 6104,
              'moisPerdants' => 4,
              'moisTotal' => 12,
            ),
            1 => 
            array (
              'periode' => '2025',
              'mois' => 'avr. 2025',
              'moisPct' => 0,
              'soldeDebutMois' => 10052,
              'soldeFinMois' => 10045,
              'pireBaissePct' => 0,
              'plusBas' => 8656,
              'moisPerdants' => 1,
              'moisTotal' => 12,
            ),
            2 => 
            array (
              'periode' => '2026',
              'mois' => 'févr. 2026',
              'moisPct' => -14,
              'soldeDebutMois' => 10314,
              'soldeFinMois' => 8893,
              'pireBaissePct' => -15,
              'plusBas' => 8811,
              'moisPerdants' => 5,
              'moisTotal' => 10,
            ),
            3 => 
            array (
              'periode' => '2024-2026',
              'mois' => 'avr. 2024',
              'moisPct' => -17,
              'soldeDebutMois' => 8428,
              'soldeFinMois' => 6991,
              'pireBaissePct' => -30,
              'plusBas' => 6104,
              'moisPerdants' => 11,
              'moisTotal' => 34,
            ),
          ),
        ),
        5 => 
        array (
          'capital' => 10000,
          'troisAns' => 
          array (
            'soldeFinal' => 32103,
            'multiplicateur' => 3.2,
            'gainPct' => 221,
            'pireBaissePct' => -30,
            'plusBasDuCompte' => 7879,
          ),
          'parAnnee' => 
          array (
            2024 => 
            array (
              'soldeFinal' => 11721,
              'gainPct' => 17,
            ),
            2025 => 
            array (
              'soldeFinal' => 21268,
              'gainPct' => 113,
            ),
            '2026_au_7_oct' => 
            array (
              'soldeFinal' => 13377,
              'gainPct' => 34,
            ),
          ),
          'tradesRefuses' => 224,
          'tradesPris' => 857,
          'pireMois' => 
          array (
            0 => 
            array (
              'periode' => '2024',
              'mois' => 'avr. 2024',
              'moisPct' => -18,
              'soldeDebutMois' => 10824,
              'soldeFinMois' => 8930,
              'pireBaissePct' => -30,
              'plusBas' => 7879,
              'moisPerdants' => 5,
              'moisTotal' => 12,
            ),
            1 => 
            array (
              'periode' => '2025',
              'mois' => 'juin 2025',
              'moisPct' => -8,
              'soldeDebutMois' => 15577,
              'soldeFinMois' => 14338,
              'pireBaissePct' => -8,
              'plusBas' => 11002,
              'moisPerdants' => 1,
              'moisTotal' => 12,
            ),
            2 => 
            array (
              'periode' => '2026',
              'mois' => 'mai 2026',
              'moisPct' => -11,
              'soldeDebutMois' => 13781,
              'soldeFinMois' => 12238,
              'pireBaissePct' => -15,
              'plusBas' => 11746,
              'moisPerdants' => 5,
              'moisTotal' => 10,
            ),
            3 => 
            array (
              'periode' => '2024-2026',
              'mois' => 'avr. 2024',
              'moisPct' => -18,
              'soldeDebutMois' => 10824,
              'soldeFinMois' => 8930,
              'pireBaissePct' => -30,
              'plusBas' => 7879,
              'moisPerdants' => 14,
              'moisTotal' => 34,
            ),
          ),
        ),
      ),
    ),
    1 => 
    array (
      'risquePct' => 2,
      'livreParDefaut' => false,
      'lignes' => 
      array (
        0 => 
        array (
          'capital' => 500,
          'troisAns' => 
          array (
            'soldeFinal' => 4832,
            'multiplicateur' => 9.7,
            'gainPct' => 866,
            'pireBaissePct' => -26,
            'plusBasDuCompte' => 490,
          ),
          'parAnnee' => 
          array (
            2024 => 
            array (
              'soldeFinal' => 948,
              'gainPct' => 90,
            ),
            2025 => 
            array (
              'soldeFinal' => 1628,
              'gainPct' => 226,
            ),
            '2026_au_7_oct' => 
            array (
              'soldeFinal' => 657,
              'gainPct' => 31,
            ),
          ),
          'tradesRefuses' => 1875,
          'tradesPris' => 749,
          'pireMois' => 
          array (
            0 => 
            array (
              'periode' => '2024',
              'mois' => 'avr. 2024',
              'moisPct' => -24,
              'soldeDebutMois' => 712,
              'soldeFinMois' => 540,
              'pireBaissePct' => -26,
              'plusBas' => 490,
              'moisPerdants' => 4,
              'moisTotal' => 12,
            ),
            1 => 
            array (
              'periode' => '2025',
              'mois' => 'déc. 2025',
              'moisPct' => -16,
              'soldeDebutMois' => 1931,
              'soldeFinMois' => 1628,
              'pireBaissePct' => -16,
              'plusBas' => 626,
              'moisPerdants' => 4,
              'moisTotal' => 12,
            ),
            2 => 
            array (
              'periode' => '2026',
              'mois' => 'mai 2026',
              'moisPct' => -10,
              'soldeDebutMois' => 630,
              'soldeFinMois' => 564,
              'pireBaissePct' => -11,
              'plusBas' => 564,
              'moisPerdants' => 5,
              'moisTotal' => 10,
            ),
            3 => 
            array (
              'periode' => '2024-2026',
              'mois' => 'avr. 2024',
              'moisPct' => -24,
              'soldeDebutMois' => 712,
              'soldeFinMois' => 540,
              'pireBaissePct' => -26,
              'plusBas' => 490,
              'moisPerdants' => 10,
              'moisTotal' => 34,
            ),
          ),
        ),
        1 => 
        array (
          'capital' => 1000,
          'troisAns' => 
          array (
            'soldeFinal' => 5511,
            'multiplicateur' => 5.5,
            'gainPct' => 451,
            'pireBaissePct' => -22,
            'plusBasDuCompte' => 1075,
          ),
          'parAnnee' => 
          array (
            2024 => 
            array (
              'soldeFinal' => 1617,
              'gainPct' => 62,
            ),
            2025 => 
            array (
              'soldeFinal' => 2452,
              'gainPct' => 145,
            ),
            '2026_au_7_oct' => 
            array (
              'soldeFinal' => 1460,
              'gainPct' => 46,
            ),
          ),
          'tradesRefuses' => 1472,
          'tradesPris' => 780,
          'pireMois' => 
          array (
            0 => 
            array (
              'periode' => '2024',
              'mois' => 'avr. 2024',
              'moisPct' => -18,
              'soldeDebutMois' => 1391,
              'soldeFinMois' => 1142,
              'pireBaissePct' => -18,
              'plusBas' => 1075,
              'moisPerdants' => 3,
              'moisTotal' => 12,
            ),
            1 => 
            array (
              'periode' => '2025',
              'mois' => 'déc. 2025',
              'moisPct' => -18,
              'soldeDebutMois' => 3008,
              'soldeFinMois' => 2452,
              'pireBaissePct' => -19,
              'plusBas' => 1193,
              'moisPerdants' => 3,
              'moisTotal' => 12,
            ),
            2 => 
            array (
              'periode' => '2026',
              'mois' => 'juil. 2026',
              'moisPct' => -13,
              'soldeDebutMois' => 1472,
              'soldeFinMois' => 1281,
              'pireBaissePct' => -21,
              'plusBas' => 1281,
              'moisPerdants' => 4,
              'moisTotal' => 10,
            ),
            3 => 
            array (
              'periode' => '2024-2026',
              'mois' => 'mai 2026',
              'moisPct' => -19,
              'soldeDebutMois' => 6881,
              'soldeFinMois' => 5548,
              'pireBaissePct' => -22,
              'plusBas' => 1075,
              'moisPerdants' => 12,
              'moisTotal' => 34,
            ),
          ),
        ),
        2 => 
        array (
          'capital' => 2000,
          'troisAns' => 
          array (
            'soldeFinal' => 22442,
            'multiplicateur' => 11.2,
            'gainPct' => 1022,
            'pireBaissePct' => -28,
            'plusBasDuCompte' => 1876,
          ),
          'parAnnee' => 
          array (
            2024 => 
            array (
              'soldeFinal' => 3177,
              'gainPct' => 59,
            ),
            2025 => 
            array (
              'soldeFinal' => 5068,
              'gainPct' => 153,
            ),
            '2026_au_7_oct' => 
            array (
              'soldeFinal' => 4256,
              'gainPct' => 113,
            ),
          ),
          'tradesRefuses' => 774,
          'tradesPris' => 832,
          'pireMois' => 
          array (
            0 => 
            array (
              'periode' => '2024',
              'mois' => 'avr. 2024',
              'moisPct' => -17,
              'soldeDebutMois' => 2619,
              'soldeFinMois' => 2170,
              'pireBaissePct' => -28,
              'plusBas' => 1876,
              'moisPerdants' => 2,
              'moisTotal' => 12,
            ),
            1 => 
            array (
              'periode' => '2025',
              'mois' => 'déc. 2025',
              'moisPct' => -11,
              'soldeDebutMois' => 5707,
              'soldeFinMois' => 5068,
              'pireBaissePct' => -11,
              'plusBas' => 2324,
              'moisPerdants' => 1,
              'moisTotal' => 12,
            ),
            2 => 
            array (
              'periode' => '2026',
              'mois' => 'juil. 2026',
              'moisPct' => -20,
              'soldeDebutMois' => 4015,
              'soldeFinMois' => 3209,
              'pireBaissePct' => -24,
              'plusBas' => 3209,
              'moisPerdants' => 3,
              'moisTotal' => 10,
            ),
            3 => 
            array (
              'periode' => '2024-2026',
              'mois' => 'avr. 2024',
              'moisPct' => -17,
              'soldeDebutMois' => 2619,
              'soldeFinMois' => 2170,
              'pireBaissePct' => -28,
              'plusBas' => 1876,
              'moisPerdants' => 12,
              'moisTotal' => 34,
            ),
          ),
        ),
        3 => 
        array (
          'capital' => 5000,
          'troisAns' => 
          array (
            'soldeFinal' => 50955,
            'multiplicateur' => 10.2,
            'gainPct' => 919,
            'pireBaissePct' => -48,
            'plusBasDuCompte' => 3417,
          ),
          'parAnnee' => 
          array (
            2024 => 
            array (
              'soldeFinal' => 6829,
              'gainPct' => 37,
            ),
            2025 => 
            array (
              'soldeFinal' => 15719,
              'gainPct' => 214,
            ),
            '2026_au_7_oct' => 
            array (
              'soldeFinal' => 7210,
              'gainPct' => 44,
            ),
          ),
          'tradesRefuses' => 145,
          'tradesPris' => 863,
          'pireMois' => 
          array (
            0 => 
            array (
              'periode' => '2024',
              'mois' => 'avr. 2024',
              'moisPct' => -29,
              'soldeDebutMois' => 6237,
              'soldeFinMois' => 4427,
              'pireBaissePct' => -29,
              'plusBas' => 3417,
              'moisPerdants' => 5,
              'moisTotal' => 12,
            ),
            1 => 
            array (
              'periode' => '2025',
              'mois' => 'juin 2025',
              'moisPct' => -11,
              'soldeDebutMois' => 9920,
              'soldeFinMois' => 8859,
              'pireBaissePct' => -11,
              'plusBas' => 5892,
              'moisPerdants' => 3,
              'moisTotal' => 12,
            ),
            2 => 
            array (
              'periode' => '2026',
              'mois' => 'mai 2026',
              'moisPct' => -20,
              'soldeDebutMois' => 7698,
              'soldeFinMois' => 6123,
              'pireBaissePct' => -28,
              'plusBas' => 5969,
              'moisPerdants' => 5,
              'moisTotal' => 10,
            ),
            3 => 
            array (
              'periode' => '2024-2026',
              'mois' => 'avr. 2024',
              'moisPct' => -29,
              'soldeDebutMois' => 6237,
              'soldeFinMois' => 4427,
              'pireBaissePct' => -48,
              'plusBas' => 3417,
              'moisPerdants' => 14,
              'moisTotal' => 34,
            ),
          ),
        ),
        4 => 
        array (
          'capital' => 7500,
          'troisAns' => 
          array (
            'soldeFinal' => 98746,
            'multiplicateur' => 13.2,
            'gainPct' => 1217,
            'pireBaissePct' => -25,
            'plusBasDuCompte' => 7330,
          ),
          'parAnnee' => 
          array (
            2024 => 
            array (
              'soldeFinal' => 15333,
              'gainPct' => 104,
            ),
            2025 => 
            array (
              'soldeFinal' => 28394,
              'gainPct' => 279,
            ),
            '2026_au_7_oct' => 
            array (
              'soldeFinal' => 7723,
              'gainPct' => 3,
            ),
          ),
          'tradesRefuses' => 11,
          'tradesPris' => 863,
          'pireMois' => 
          array (
            0 => 
            array (
              'periode' => '2024',
              'mois' => 'avr. 2024',
              'moisPct' => -21,
              'soldeDebutMois' => 9812,
              'soldeFinMois' => 7746,
              'pireBaissePct' => -25,
              'plusBas' => 7330,
              'moisPerdants' => 2,
              'moisTotal' => 12,
            ),
            1 => 
            array (
              'periode' => '2025',
              'mois' => 'mars 2025',
              'moisPct' => -10,
              'soldeDebutMois' => 10910,
              'soldeFinMois' => 9854,
              'pireBaissePct' => -10,
              'plusBas' => 9782,
              'moisPerdants' => 3,
              'moisTotal' => 12,
            ),
            2 => 
            array (
              'periode' => '2026',
              'mois' => 'juil. 2026',
              'moisPct' => -15,
              'soldeDebutMois' => 7941,
              'soldeFinMois' => 6779,
              'pireBaissePct' => -32,
              'plusBas' => 6779,
              'moisPerdants' => 5,
              'moisTotal' => 10,
            ),
            3 => 
            array (
              'periode' => '2024-2026',
              'mois' => 'avr. 2024',
              'moisPct' => -21,
              'soldeDebutMois' => 9812,
              'soldeFinMois' => 7746,
              'pireBaissePct' => -25,
              'plusBas' => 7330,
              'moisPerdants' => 12,
              'moisTotal' => 34,
            ),
          ),
        ),
        5 => 
        array (
          'capital' => 10000,
          'troisAns' => 
          array (
            'soldeFinal' => 102059,
            'multiplicateur' => 10.2,
            'gainPct' => 921,
            'pireBaissePct' => -26,
            'plusBasDuCompte' => 9609,
          ),
          'parAnnee' => 
          array (
            2024 => 
            array (
              'soldeFinal' => 16432,
              'gainPct' => 64,
            ),
            2025 => 
            array (
              'soldeFinal' => 37854,
              'gainPct' => 279,
            ),
            '2026_au_7_oct' => 
            array (
              'soldeFinal' => 22217,
              'gainPct' => 122,
            ),
          ),
          'tradesRefuses' => 3,
          'tradesPris' => 854,
          'pireMois' => 
          array (
            0 => 
            array (
              'periode' => '2024',
              'mois' => 'avr. 2024',
              'moisPct' => -22,
              'soldeDebutMois' => 12963,
              'soldeFinMois' => 10113,
              'pireBaissePct' => -26,
              'plusBas' => 9609,
              'moisPerdants' => 3,
              'moisTotal' => 12,
            ),
            1 => 
            array (
              'periode' => '2025',
              'mois' => 'mars 2025',
              'moisPct' => -14,
              'soldeDebutMois' => 14842,
              'soldeFinMois' => 12761,
              'pireBaissePct' => -14,
              'plusBas' => 12761,
              'moisPerdants' => 3,
              'moisTotal' => 12,
            ),
            2 => 
            array (
              'periode' => '2026',
              'mois' => 'juil. 2026',
              'moisPct' => -10,
              'soldeDebutMois' => 21048,
              'soldeFinMois' => 18871,
              'pireBaissePct' => -18,
              'plusBas' => 12971,
              'moisPerdants' => 6,
              'moisTotal' => 10,
            ),
            3 => 
            array (
              'periode' => '2024-2026',
              'mois' => 'avr. 2024',
              'moisPct' => -22,
              'soldeDebutMois' => 12963,
              'soldeFinMois' => 10113,
              'pireBaissePct' => -26,
              'plusBas' => 9609,
              'moisPerdants' => 13,
              'moisTotal' => 34,
            ),
          ),
        ),
      ),
    ),
    2 => 
    array (
      'risquePct' => 3,
      'livreParDefaut' => false,
      'lignes' => 
      array (
        0 => 
        array (
          'capital' => 500,
          'troisAns' => 
          array (
            'soldeFinal' => 4180,
            'multiplicateur' => 8.4,
            'gainPct' => 736,
            'pireBaissePct' => -37,
            'plusBasDuCompte' => 551,
          ),
          'parAnnee' => 
          array (
            2024 => 
            array (
              'soldeFinal' => 1087,
              'gainPct' => 117,
            ),
            2025 => 
            array (
              'soldeFinal' => 1958,
              'gainPct' => 292,
            ),
            '2026_au_7_oct' => 
            array (
              'soldeFinal' => 630,
              'gainPct' => 26,
            ),
          ),
          'tradesRefuses' => 1339,
          'tradesPris' => 791,
          'pireMois' => 
          array (
            0 => 
            array (
              'periode' => '2024',
              'mois' => 'juin 2024',
              'moisPct' => -22,
              'soldeDebutMois' => 834,
              'soldeFinMois' => 652,
              'pireBaissePct' => -22,
              'plusBas' => 551,
              'moisPerdants' => 3,
              'moisTotal' => 12,
            ),
            1 => 
            array (
              'periode' => '2025',
              'mois' => 'déc. 2025',
              'moisPct' => -30,
              'soldeDebutMois' => 2797,
              'soldeFinMois' => 1958,
              'pireBaissePct' => -30,
              'plusBas' => 655,
              'moisPerdants' => 3,
              'moisTotal' => 12,
            ),
            2 => 
            array (
              'periode' => '2026',
              'mois' => 'mai 2026',
              'moisPct' => -18,
              'soldeDebutMois' => 709,
              'soldeFinMois' => 584,
              'pireBaissePct' => -19,
              'plusBas' => 584,
              'moisPerdants' => 4,
              'moisTotal' => 10,
            ),
            3 => 
            array (
              'periode' => '2024-2026',
              'mois' => 'mai 2026',
              'moisPct' => -30,
              'soldeDebutMois' => 5159,
              'soldeFinMois' => 3603,
              'pireBaissePct' => -37,
              'plusBas' => 551,
              'moisPerdants' => 10,
              'moisTotal' => 34,
            ),
          ),
        ),
        1 => 
        array (
          'capital' => 1000,
          'troisAns' => 
          array (
            'soldeFinal' => 28262,
            'multiplicateur' => 28.3,
            'gainPct' => 2726,
            'pireBaissePct' => -30,
            'plusBasDuCompte' => 1058,
          ),
          'parAnnee' => 
          array (
            2024 => 
            array (
              'soldeFinal' => 3945,
              'gainPct' => 294,
            ),
            2025 => 
            array (
              'soldeFinal' => 3247,
              'gainPct' => 225,
            ),
            '2026_au_7_oct' => 
            array (
              'soldeFinal' => 3662,
              'gainPct' => 266,
            ),
          ),
          'tradesRefuses' => 546,
          'tradesPris' => 834,
          'pireMois' => 
          array (
            0 => 
            array (
              'periode' => '2024',
              'mois' => 'avr. 2024',
              'moisPct' => -18,
              'soldeDebutMois' => 1646,
              'soldeFinMois' => 1346,
              'pireBaissePct' => -18,
              'plusBas' => 1058,
              'moisPerdants' => 3,
              'moisTotal' => 12,
            ),
            1 => 
            array (
              'periode' => '2025',
              'mois' => 'déc. 2025',
              'moisPct' => -12,
              'soldeDebutMois' => 3709,
              'soldeFinMois' => 3247,
              'pireBaissePct' => -12,
              'plusBas' => 1301,
              'moisPerdants' => 2,
              'moisTotal' => 12,
            ),
            2 => 
            array (
              'periode' => '2026',
              'mois' => 'juil. 2026',
              'moisPct' => -27,
              'soldeDebutMois' => 3399,
              'soldeFinMois' => 2491,
              'pireBaissePct' => -27,
              'plusBas' => 2205,
              'moisPerdants' => 3,
              'moisTotal' => 10,
            ),
            3 => 
            array (
              'periode' => '2024-2026',
              'mois' => 'avr. 2024',
              'moisPct' => -18,
              'soldeDebutMois' => 1646,
              'soldeFinMois' => 1346,
              'pireBaissePct' => -30,
              'plusBas' => 1058,
              'moisPerdants' => 14,
              'moisTotal' => 34,
            ),
          ),
        ),
        2 => 
        array (
          'capital' => 2000,
          'troisAns' => 
          array (
            'soldeFinal' => 28257,
            'multiplicateur' => 14.1,
            'gainPct' => 1313,
            'pireBaissePct' => -62,
            'plusBasDuCompte' => 1159,
          ),
          'parAnnee' => 
          array (
            2024 => 
            array (
              'soldeFinal' => 2235,
              'gainPct' => 12,
            ),
            2025 => 
            array (
              'soldeFinal' => 13885,
              'gainPct' => 594,
            ),
            '2026_au_7_oct' => 
            array (
              'soldeFinal' => 4180,
              'gainPct' => 109,
            ),
          ),
          'tradesRefuses' => 534,
          'tradesPris' => 855,
          'pireMois' => 
          array (
            0 => 
            array (
              'periode' => '2024',
              'mois' => 'avr. 2024',
              'moisPct' => -51,
              'soldeDebutMois' => 3048,
              'soldeFinMois' => 1507,
              'pireBaissePct' => -62,
              'plusBas' => 1159,
              'moisPerdants' => 3,
              'moisTotal' => 12,
            ),
            1 => 
            array (
              'periode' => '2025',
              'mois' => 'juin 2025',
              'moisPct' => -13,
              'soldeDebutMois' => 7741,
              'soldeFinMois' => 6711,
              'pireBaissePct' => -13,
              'plusBas' => 3030,
              'moisPerdants' => 2,
              'moisTotal' => 12,
            ),
            2 => 
            array (
              'periode' => '2026',
              'mois' => 'mai 2026',
              'moisPct' => -28,
              'soldeDebutMois' => 4661,
              'soldeFinMois' => 3342,
              'pireBaissePct' => -32,
              'plusBas' => 3156,
              'moisPerdants' => 5,
              'moisTotal' => 10,
            ),
            3 => 
            array (
              'periode' => '2024-2026',
              'mois' => 'avr. 2024',
              'moisPct' => -51,
              'soldeDebutMois' => 3048,
              'soldeFinMois' => 1507,
              'pireBaissePct' => -62,
              'plusBas' => 1159,
              'moisPerdants' => 12,
              'moisTotal' => 34,
            ),
          ),
        ),
        3 => 
        array (
          'capital' => 5000,
          'troisAns' => 
          array (
            'soldeFinal' => 106101,
            'multiplicateur' => 21.2,
            'gainPct' => 2022,
            'pireBaissePct' => -36,
            'plusBasDuCompte' => 4649,
          ),
          'parAnnee' => 
          array (
            2024 => 
            array (
              'soldeFinal' => 12830,
              'gainPct' => 157,
            ),
            2025 => 
            array (
              'soldeFinal' => 34656,
              'gainPct' => 593,
            ),
            '2026_au_7_oct' => 
            array (
              'soldeFinal' => 8415,
              'gainPct' => 68,
            ),
          ),
          'tradesRefuses' => 10,
          'tradesPris' => 862,
          'pireMois' => 
          array (
            0 => 
            array (
              'periode' => '2024',
              'mois' => 'avr. 2024',
              'moisPct' => -31,
              'soldeDebutMois' => 7229,
              'soldeFinMois' => 4961,
              'pireBaissePct' => -36,
              'plusBas' => 4649,
              'moisPerdants' => 2,
              'moisTotal' => 12,
            ),
            1 => 
            array (
              'periode' => '2025',
              'mois' => 'juin 2025',
              'moisPct' => -18,
              'soldeDebutMois' => 19808,
              'soldeFinMois' => 16302,
              'pireBaissePct' => -18,
              'plusBas' => 7263,
              'moisPerdants' => 3,
              'moisTotal' => 12,
            ),
            2 => 
            array (
              'periode' => '2026',
              'mois' => 'juil. 2026',
              'moisPct' => -23,
              'soldeDebutMois' => 9126,
              'soldeFinMois' => 7031,
              'pireBaissePct' => -36,
              'plusBas' => 6848,
              'moisPerdants' => 6,
              'moisTotal' => 10,
            ),
            3 => 
            array (
              'periode' => '2024-2026',
              'mois' => 'avr. 2024',
              'moisPct' => -31,
              'soldeDebutMois' => 7229,
              'soldeFinMois' => 4961,
              'pireBaissePct' => -36,
              'plusBas' => 4649,
              'moisPerdants' => 12,
              'moisTotal' => 34,
            ),
          ),
        ),
        4 => 
        array (
          'capital' => 7500,
          'troisAns' => 
          array (
            'soldeFinal' => 126451,
            'multiplicateur' => 16.9,
            'gainPct' => 1586,
            'pireBaissePct' => -41,
            'plusBasDuCompte' => 6668,
          ),
          'parAnnee' => 
          array (
            2024 => 
            array (
              'soldeFinal' => 14232,
              'gainPct' => 90,
            ),
            2025 => 
            array (
              'soldeFinal' => 43581,
              'gainPct' => 481,
            ),
            '2026_au_7_oct' => 
            array (
              'soldeFinal' => 16441,
              'gainPct' => 119,
            ),
          ),
          'tradesRefuses' => 1,
          'tradesPris' => 853,
          'pireMois' => 
          array (
            0 => 
            array (
              'periode' => '2024',
              'mois' => 'avr. 2024',
              'moisPct' => -33,
              'soldeDebutMois' => 11291,
              'soldeFinMois' => 7612,
              'pireBaissePct' => -41,
              'plusBas' => 6668,
              'moisPerdants' => 3,
              'moisTotal' => 12,
            ),
            1 => 
            array (
              'periode' => '2025',
              'mois' => 'mars 2025',
              'moisPct' => -19,
              'soldeDebutMois' => 12746,
              'soldeFinMois' => 10308,
              'pireBaissePct' => -19,
              'plusBas' => 10308,
              'moisPerdants' => 3,
              'moisTotal' => 12,
            ),
            2 => 
            array (
              'periode' => '2026',
              'mois' => 'juil. 2026',
              'moisPct' => -22,
              'soldeDebutMois' => 17862,
              'soldeFinMois' => 14001,
              'pireBaissePct' => -34,
              'plusBas' => 10848,
              'moisPerdants' => 7,
              'moisTotal' => 10,
            ),
            3 => 
            array (
              'periode' => '2024-2026',
              'mois' => 'avr. 2024',
              'moisPct' => -33,
              'soldeDebutMois' => 11291,
              'soldeFinMois' => 7612,
              'pireBaissePct' => -41,
              'plusBas' => 6668,
              'moisPerdants' => 14,
              'moisTotal' => 34,
            ),
          ),
        ),
        5 => 
        array (
          'capital' => 10000,
          'troisAns' => 
          array (
            'soldeFinal' => 205818,
            'multiplicateur' => 20.6,
            'gainPct' => 1958,
            'pireBaissePct' => -35,
            'plusBasDuCompte' => 9675,
          ),
          'parAnnee' => 
          array (
            2024 => 
            array (
              'soldeFinal' => 23613,
              'gainPct' => 136,
            ),
            2025 => 
            array (
              'soldeFinal' => 44533,
              'gainPct' => 345,
            ),
            '2026_au_7_oct' => 
            array (
              'soldeFinal' => 21966,
              'gainPct' => 120,
            ),
          ),
          'tradesRefuses' => 0,
          'tradesPris' => 855,
          'pireMois' => 
          array (
            0 => 
            array (
              'periode' => '2024',
              'mois' => 'avr. 2024',
              'moisPct' => -30,
              'soldeDebutMois' => 14928,
              'soldeFinMois' => 10391,
              'pireBaissePct' => -35,
              'plusBas' => 9675,
              'moisPerdants' => 2,
              'moisTotal' => 12,
            ),
            1 => 
            array (
              'periode' => '2025',
              'mois' => 'mars 2025',
              'moisPct' => -20,
              'soldeDebutMois' => 18411,
              'soldeFinMois' => 14803,
              'pireBaissePct' => -20,
              'plusBas' => 14803,
              'moisPerdants' => 3,
              'moisTotal' => 12,
            ),
            2 => 
            array (
              'periode' => '2026',
              'mois' => 'juil. 2026',
              'moisPct' => -17,
              'soldeDebutMois' => 22828,
              'soldeFinMois' => 19017,
              'pireBaissePct' => -28,
              'plusBas' => 13957,
              'moisPerdants' => 7,
              'moisTotal' => 10,
            ),
            3 => 
            array (
              'periode' => '2024-2026',
              'mois' => 'avr. 2024',
              'moisPct' => -30,
              'soldeDebutMois' => 14928,
              'soldeFinMois' => 10391,
              'pireBaissePct' => -35,
              'plusBas' => 9675,
              'moisPerdants' => 12,
              'moisTotal' => 34,
            ),
          ),
        ),
      ),
    ),
  ),
  'limitesCommunes' => 
  array (
    0 => 'Il s\'agit d\'un backtest : des performances simulées, qui ne préjugent pas des résultats futurs.',
    1 => 'Les réglages ont été choisis sur le même historique que celui qui les teste : les résultats futurs seront probablement moins bons.',
    2 => 'Il n\'y a pas encore d\'historique en argent réel : les bots tournent en démo depuis septembre 2026.',
    3 => 'L\'avenir peut être très positif, nul ou négatif, avec une perte possible de tout le capital engagé.',
  ),
  'capitalConseille' => 
  array (
    'montant' => 5000,
    'obligatoire' => false,
  ),
);

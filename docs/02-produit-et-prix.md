# Produit et prix

## Le produit
cBot cTrader qui tourne dans le cloud cTrader, sur le compte broker du client, même ordinateur éteint.

**Stratégie (bot principal + bot test réunis)**
- Suivi de tendance H1 : or, BTC, Nasdaq, DAX, ETH. Stop suiveur 3 ATR (activé à +2R pour le principal, dès l'entrée pour le test).
- Setups ICT A+ (prise de liquidité) en 15 min : Nasdaq, EURUSD, US 500, ETH.
- Objectif fixe : 5R (principal), 7R (test).
- Garde-fous : 1 trade par actif, 3 positions max, 2 pertes max/jour, trade refusé si le lot minimum fait risquer > 1,5× le risque prévu.
- Horaires : entrées du dimanche 23h59 au vendredi 17h (heure de Paris).

**Réglages client** : risque par trade et noms des symboles chez son broker. Plafond 3 % codé en dur (liste fixe 1 % / 2 % / 3 %). Pour changer : arrêter le cBot dans le cloud cTrader, modifier « risque », relancer.

**Capital conseillé : 5 000 € minimum.** En dessous, le lot minimum fait refuser beaucoup de trades (Nasdaq, DAX, or) et les résultats deviennent très irréguliers.
**Risque par défaut recommandé : 1 %** (à confirmer par Bouchra).

## Prix
| Formule | Prix | Total 1re année |
|---|---|---|
| Achat du cBot | 1 500 € (une fois) | — |
| Abonnement mensuel | 39,90 €/mois | 1 500 + 478,80 = 1 978,80 € |
| Abonnement annuel | 29,90 €/mois = 358,80 €/an payés d'avance | 1 500 + 358,80 = 1 858,80 € |

- L'abonnement est **obligatoire** pour garder la licence active. L'annuel économise 120 €/an : argument à mettre en avant.
- Au paiement : achat + premier abonnement dans le même passage en caisse.
- Sans abonnement payé : le cBot cesse d'ouvrir de nouveaux trades en fin de licence (voir `04-licences.md`).
- **Question ouverte : prix TTC ou HT ?** (dépend du statut/TVA de Bouchra).

## Résultats de backtest
Voir `config/resultats-backtest.json` (source unique) et `06-regles-legales-communication.md` pour les règles d'affichage. Référence du site : **1 % de risque**, toujours avec la pire baisse à côté.

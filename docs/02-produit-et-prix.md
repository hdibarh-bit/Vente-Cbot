# Produit et prix

## Le produit
cBot cTrader qui tourne dans le cloud cTrader, sur le compte broker du client, même ordinateur éteint.

> **INTERNE — NE JAMAIS PUBLIER SUR LE SITE (règle de Hamid du 21/10/2026).** Le site ne décrit pas la stratégie ; ce qui suit sert uniquement de contexte technique.

**Stratégie (bot principal + bot test réunis)**
- Suivi de tendance H1 : or, BTC, Nasdaq, DAX, ETH. Stop suiveur 3 ATR (activé à +2R pour le principal, dès l'entrée pour le test).
- Setups ICT A+ (prise de liquidité) en 15 min : Nasdaq, EURUSD, US 500, ETH.
- Objectif fixe : 5R (principal), 7R (test).
- Garde-fous : 1 trade par actif, 3 positions max, 2 pertes max/jour, trade refusé si le lot minimum fait risquer > 1,5× le risque prévu.
- Horaires : entrées du dimanche 23h59 au vendredi 17h (heure de Paris).

**Réglages client** : risque par trade et noms des symboles chez son broker. Plafond 3 % codé en dur (liste fixe 1 % / 2 % / 3 %). Pour changer : arrêter le cBot dans le cloud cTrader, modifier « risque », relancer.

**Capital conseillé : 2 000 € minimum** (décidé par Hamid le 25/10/2026, au lieu de 5 000 € : chez IC Markets, presque plus aucun trade n'est refusé à partir de 2 000 €). En dessous, le lot minimum fait refuser des trades et les résultats deviennent irréguliers. La référence mise en avant pour les résultats reste 5 000 €.
**Risque par défaut : 1 %** (décidé le 21/10/2026). Le client choisit 1 %, 2 % ou 3 %, jamais au-delà.

## Prix
| Formule | Prix | Total 1re année |
|---|---|---|
| Achat du cBot | 1 500 € (une fois) | — |
| Abonnement mensuel | 39,90 €/mois | 1 500 + 478,80 = 1 978,80 € |
| Abonnement annuel | 29,90 €/mois = 358,80 €/an payés d'avance | 1 500 + 358,80 = 1 858,80 € |

- L'abonnement est **obligatoire** pour garder la licence active. L'annuel économise 120 €/an : argument à mettre en avant.
- Au paiement : achat + premier abonnement dans le même passage en caisse.
- Sans abonnement payé : le cBot cesse d'ouvrir de nouveaux trades en fin de licence (voir `04-licences.md`).
- **TVA (décidé le 21/10/2026)** : auto-entrepreneur au départ, donc aucune mention TTC/HT. Passage en société plus tard : prix en TTC.

## Pourquoi un capital conseillé de 2 000 € minimum (à expliquer sur le site)
Décision de Hamid du 25/10/2026. **Ce n'est pas une obligation** : le client peut démarrer avec moins.
- Raison unique : un manque de capital fait **refuser des trades** (taille minimale de position du courtier). Le robot prend alors moins de trades et les résultats sont moins réguliers.
- Backtest du 25/10/2026 (trades refusés au risque 1 %) : 500 € : 1 745 · 1 000 € : 415 · 2 000 € : 20 · 4 000 € : 1 · 5 000 € : 1 · 10 000 € : 0. Aux risques 2 % et 3 %, 0 refusé dès 2 000 € (sauf 500 € et 1 000 € à 2 %).
- À afficher sur l'accueil, la FAQ et la page Tarifs, sans promettre de gains. Ne jamais présenter le capital minimum comme une garantie de résultat.

## Résultats de backtest (décision Cowork + Hamid, 25/10/2026)
- **Un seul backtest de référence**, qui remplace tout l'ancien (PDF du 21/10/2026, colonnes par année repartant du capital de départ, colonne chaînée) : compte unique ouvert le 01/01/2024 et suivi jusqu'au 25/10/2026, sans retrait, frais d'un vrai courtier inclus (IC Markets, compte Raw Spread cTrader, cité comme exemple, sans lien), trades refusés comptés. Réglage livré : **1 perte maximum par jour, 3 positions maximum** en même temps.
- Référence mise en avant : **5 000 € au risque 1 %** (15 202 €, +204 %, pire baisse −20,1 % / −3 771 € du 2 septembre au 21 octobre 2026, en cours). Tableaux par capital (500 € à 20 000 €) en onglets 1 % / 2 % / 3 %.
- Chiffres dans `config/resultats-backtest.json` (source unique).
- **Le bot est livré à 1 % de risque** ; le client peut monter jusqu'à 3 %, **jamais au-delà**.
- Toujours « performances simulées », pire baisse à côté de chaque gain (en % et en €, avec les dates), titre sur la méthode, jamais un montant en titre.
- **L'ancien tableau à 100 000 € est abandonné.** L'ancien PDF avec détails de stratégie est archivé dans `docs/sources/` et **ne doit jamais être diffusé**.
- Règles d'affichage : `06-regles-legales-communication.md`.

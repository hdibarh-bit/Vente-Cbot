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

## Pourquoi un capital conseillé de 5 000 € minimum (à expliquer sur le site)
Explication donnée par Hamid le 21/10/2026. **Ce n'est pas une obligation** : le client peut démarrer avec moins.
- C'est **la seule raison** : un manque de capital fait **refuser des trades** (par le bot ou par le broker). Avec le lot minimum du broker, un petit compte ferait risquer plus que le risque prévu, donc le trade est refusé (garde-fou du bot : refus si le lot minimum fait risquer plus de 1,5 fois le risque prévu).
- Avec 500 € (ou moins de 5 000 €), beaucoup de trades sont refusés, donc le bot trade moins et les résultats sont irréguliers.
- **Chiffres du PDF** (signaux refusés sur 2024 → 2026) : 500 € : 1 339 · 1 000 € : 546 · 2 000 € : 534 · 5 000 € : 10 · 7 500 € : 1 · 10 000 € : 0. Cause : le broker impose 1 contrat minimum sur le Nasdaq et le DAX et 0,01 lot sur l'or ; si ce minimum fait risquer plus de 1,5 fois le risque prévu, le trade est refusé.
- À partir de 5 000 €, beaucoup moins de trades sont refusés : le bot prend plus de trades, ce qui rapproche le comportement réel de la simulation.
- À afficher (en plus de la FAQ) sur les pages Tarifs et Résultats, sans promettre de gains. Ne jamais présenter le capital minimum comme une garantie de résultat.

## Résultats de backtest (décision de Hamid, 21/10/2026)
- **Source : le PDF « Simulation bots cTrader 2024-2026 »** (`assets/rapports/Simulation-bots-cTrader-2024-2026.pdf`). Simulations par **montant de capital, de 500 € à environ 10 000 €**, sur **2024, 2025 et 2026**, avec le **solde le plus bas** pour chaque montant. À présenter comme un backtest sur ces trois années.
- **L'ancien tableau à 100 000 € est abandonné** : il venait d'une simulation faite sur une prop firm, sans rapport avec le bot vendu.
- **Chiffres transcrits** dans `config/resultats-backtest.json` (statut provisoire). Le PDF actuel est au **risque 3 %/3 %**. **Décision de Hamid (21/10/2026)** : un **nouveau PDF** montrera les simulations à **1 %, 2 % et 3 %** ; le site dit que le bot est **livré à 1 % de risque** et que le client peut **monter jusqu'à 3 %, pas plus, à sa guise**. Les résultats se présentent comme un **backtest du bot sur 2024, 2025 et 2026** (performances simulées). Ne rien publier avant le nouveau PDF. Règles d'affichage dans `06-regles-legales-communication.md` : toujours « performances simulées » et solde le plus bas à côté de chaque gain.

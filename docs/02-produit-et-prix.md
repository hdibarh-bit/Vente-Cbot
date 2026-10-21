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
- **Chiffres du PDF à 3 % de risque** (signaux refusés sur 2024 → 2026) : 500 € : 1 339 · 1 000 € : 546 · 2 000 € : 534 · 5 000 € : 10 · 7 500 € : 1 · 10 000 € : 0. À 1 % : 3 760 · 2 776 · 1 819 · 543 · 339 · 224. Cause : le broker impose 1 contrat minimum sur le Nasdaq et le DAX et 0,01 lot sur l'or ; si ce minimum fait risquer plus de 1,5 fois le risque prévu, le trade est refusé.
- À partir de 5 000 €, beaucoup moins de trades sont refusés : le bot prend plus de trades, ce qui rapproche le comportement réel de la simulation.
- À afficher (en plus de la FAQ) sur les pages Tarifs et Résultats, sans promettre de gains. Ne jamais présenter le capital minimum comme une garantie de résultat.

## Résultats de backtest (décisions de Hamid, 21/10/2026)
- **Source : le PDF « Backtest cTrader – risque 1 %, 2 % et 3 % »** (`assets/rapports/Backtest-cTrader-risque-1-2-3-pourcent.pdf`, daté du 21/10/2026). Simulations par **capital de départ (500 €, 1 000 €, 2 000 €, 5 000 €, 7 500 €, 10 000 €)**, aux risques **1 %, 2 % et 3 %**, du **1er janvier 2024 au 7 octobre 2026**, sans retrait. Il ne contient **aucun détail de stratégie**.
- Chiffres transcrits dans `config/resultats-backtest.json` (source unique, modifiable).
- **Le bot est livré à 1 % de risque** ; le client peut monter à sa guise jusqu'à 3 %, **jamais au-delà**. Le site présente les trois niveaux, avec 1 % mis en avant comme réglage livré.
- Toujours présenté comme un **backtest du bot sur 2024, 2025 et 2026** (performances simulées), en pourcentage, avec le **solde le plus bas** et la **pire baisse** à côté de chaque gain. Les résultats réels viendront avec le temps.
- **L'ancien tableau à 100 000 € est abandonné** (simulation sur une prop firm, sans rapport avec le bot vendu). L'ancien PDF (3 %/3 % seulement, avec détails de stratégie) est archivé dans `docs/sources/` et **ne doit jamais être diffusé**.
- **Point d'attention à faire trancher par Hamid** : à 1 % de risque, des trades restent refusés au-dessus de 5 000 € (543 à 5 000 €, 339 à 7 500 €, 224 à 10 000 €, contre 10, 1 et 0 à 3 %). La raison du capital conseillé (« à partir de 5 000 €, beaucoup moins de refus ») est donc vraie surtout à 2-3 % ; à 1 %, la baisse maximale reste de −30 % pour 7 500 € et 10 000 €.
- Règles d'affichage : `06-regles-legales-communication.md`.

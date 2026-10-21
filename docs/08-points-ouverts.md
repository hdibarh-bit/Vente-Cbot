# Points ouverts et prochaines étapes

## Décisions à prendre par Hamid
- [x] Risque par défaut : 1 % (décidé le 21/10). Le fichier cBot actuel est encore à 3 %/3 % : à modifier.
- [x] TVA : auto-entrepreneur, pas de TTC/HT au départ (décidé le 21/10).
- [x] Parrain non client : touche ses commissions (décidé le 21/10).
- [x] Domaine : bot.cosmartis.com (décidé le 21/10).
- [x] Résultats : backtest du bot sur 2024-2026, jamais « 7 ans » (décidé le 21/10). Nouveau PDF à 1 %, 2 % et 3 % **à recevoir de Hamid** ; bot livré à 1 %, client libre jusqu'à 3 % (décidé le 21/10).
- [ ] Nom du produit (provisoire « Aurum ICT »).
- [ ] « Feu vert site » (voir `SYNC-BOT-ICT.md` §5/§6) et prompt final.
- [ ] Offre Hostinger actuelle (hébergement WordPress, Cloud ou VPS).

## Avant le lancement public (priorité)
- [ ] Compiler le cBot `IctSmcTendance` dans cTrader (onglet Algo → Build) et corriger les erreurs.
- [ ] Tester la connexion WebSocket (port 25345) depuis le cloud cTrader → licence automatique ou manuelle.
- [ ] Valider le juridique (avocat) : CGV, avertissements, programme de parrainage, statut « vente de logiciel ».

## cBot
- [ ] Vérification de licence, mode essai démo, risque par défaut 1 %, choix 1/2/3 % plafonné.
- [ ] Obscurcir le code, répartir la vérification de licence.

## Entreprise / paiement
- [ ] Statut d'entreprise, TVA, compte bancaire pro, compte Stripe validé pour l'activité.

## Contenus
- [ ] Mettre à jour le rapport PDF de backtest au réglage 1 % (téléchargement gratuit).

## Construction
- [ ] Site avec Claude Code (voir `prompt-claude-code.md`) — **après discussion avec Hamid**.
- [ ] Workflows n8n (paiements, licences, commissions, récap Telegram).
- [ ] Pilote : 3 à 5 clients test avant ouverture publique.

## Comptes / clés à demander au bon moment
Stripe (clés test puis prod, webhook secret) · accès WordPress/Hostinger · n8n (URL webhook + secret) · paire de clés ECDSA P-256 pour les licences (clé privée côté n8n uniquement).

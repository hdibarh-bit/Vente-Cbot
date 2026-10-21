# Points ouverts et prochaines étapes

## Décisions à prendre par Bouchra
- [ ] Risque par défaut du cBot : **1 % recommandé** (fichier actuel 3 %/3 %).
- [ ] Prix **TTC ou HT** (dépend du statut / TVA).
- [ ] Parrain non client : touche-t-il ses commissions ? (proposition : oui)
- [ ] Nom du produit (provisoire « Aurum ICT ») et nom de domaine.

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
- [ ] Site avec Claude Code (voir `prompt-claude-code.md`) — **après discussion avec Bouchra**.
- [ ] Workflows n8n (paiements, licences, commissions, récap Telegram).
- [ ] Pilote : 3 à 5 clients test avant ouverture publique.

## Comptes / clés à demander au bon moment
Stripe (clés test puis prod, webhook secret) · Supabase (URL, clés) · Resend · Vercel · n8n (URL webhook + secret) · paire de clés ECDSA P-256 pour les licences.

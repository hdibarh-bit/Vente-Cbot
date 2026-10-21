# CLAUDE.md — Site de vente du cBot ICT/SMC

## Qui / quoi
Bouchra vend son bot de trading ICT/SMC (cBot cTrader) via un site à thème doré, codé ici avec Claude Code.
Bouchra n'est pas technicienne : parle-lui en **français simple**, propose, et laisse-la décider.
Elle vend un **logiciel** : elle ne touche jamais à l'argent ni au compte du client.

## Règle de travail n°1
**Ne rien coder tant que Bouchra n'a pas validé le plan.** Étapes : plan court → maquette thème + accueil → pages publiques → paiement Stripe (mode test) → licences → espace client → parrainage → admin. Tout en **mode test** jusqu'à validation explicite du passage en production.

## Où trouver quoi
- `docs/01-contexte-projet.md` — état du projet, décisions, abandons
- `docs/02-produit-et-prix.md` — produit, prix, capital, risque
- `docs/03-parrainage-affiliation.md` — règles de commissions (1 seul niveau)
- `docs/04-licences.md` — format de clé, comportement du cBot, n8n
- `docs/05-site-pages-et-espaces.md` — pages publiques, espace client/parrain, admin, base de données, tunnel
- `docs/06-regles-legales-communication.md` — interdits / obligatoires (à respecter dans CHAQUE texte)
- `docs/07-theme-dore.md` — charte graphique
- `docs/08-points-ouverts.md` — décisions et tâches en attente
- `config/resultats-backtest.json` — chiffres de backtest (source unique, modifiable)
- `config/reglages.json` — prix, taux, seuils (source unique, modifiable)
- `prompt-claude-code.md` — brief complet de la mission (référence)
- `.env.example` — variables d'environnement attendues

## Stack recommandée
Next.js (App Router) + TypeScript + Tailwind · Supabase (PostgreSQL + lien magique, RLS activée) · Stripe (Checkout, Billing, portail, webhooks idempotents) · Resend · n8n existant (n8n.cosmartis.com, webhooks signés) · Vercel. Aucune clé secrète dans le code.

## Interdits absolus (résumé)
Aucune promesse de gain · toujours « performances simulées » + pire baisse à côté de chaque gain · aucun cumul spectaculaire en titre · aucune pub Forex/CFD · aucun lien d'affiliation broker · vocabulaire « récompense de recommandation », jamais « revenu » ou « opportunité ». Textes juridiques = provisoires, marqués « à faire valider par un avocat ».

## Tests obligatoires à écrire
Calcul des commissions · annulation après remboursement · génération/vérification des licences · webhooks Stripe (idempotence).

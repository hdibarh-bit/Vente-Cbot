# CLAUDE.md — Site de vente du cBot ICT/SMC

## Qui / quoi
Hamid vend son bot de trading ICT/SMC (cBot cTrader) via un site à thème doré, codé ici avec Claude Code.
Hamid n'est pas technicien : parle en **français simple**, propose, et laisse Hamid décider.
Hamid vend un **logiciel** : jamais d'accès à l'argent ni au compte du client.

## Règle n°0 — fichier de liaison Drive
**Avant toute session ou conversation, lire en entier `SYNC-BOT-ICT.md` sur Google Drive** (le chercher par titre `SYNC-BOT-ICT`, son identifiant change à chaque mise à jour). C'est le canal avec Claude/Cowork (chef de projet) ; il contient les décisions figées (section 2) et les consignes (section 5). **À la fin de chaque session, y écrire** : fait, bloqué, besoins. Mise à jour = créer le nouveau fichier (même nom, même dossier), vérifier qu'il est complet, puis mettre l'ancien à la corbeille. Jamais de secret dedans. Ne pas changer une décision de la section 2 sans l'accord écrit de Hamid. Ne pas le confondre avec `SYNC.md` (autre projet).

## Règle de travail n°1
**La construction démarre quand Hamid envoie son prompt final** (décidé le 21/10/2026), pas avant. **Réserve** : `SYNC-BOT-ICT.md` §5 exige aussi un « feu vert site » écrit par Claude (chef de projet) ; tant qu'il n'y est pas, demander à Hamid avant de coder. Étapes : thème + accueil → pages publiques → paiement Stripe (mode test) → espace client → parrainage → admin. Tout en **mode test** jusqu'à validation explicite du passage en production.

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

## Stack (décidée le 21/10/2026 — remplace Next.js/Supabase/Vercel)
- **WordPress sur Hostinger** (CMS que Hamid maîtrise). Objectif : léger, rapide, sûr.
- **Coût fixe zéro** : aucun plugin payant, pas de WooCommerce. Hamid ne paie que des commissions Stripe quand une vente a lieu.
- **Livrables** : (1) un **thème doré complet**, (2) **un plugin maison** (séparé du thème) avec toute la logique : espace client, parrainage, admin, webhooks. Installation = 2 fichiers zip. Seuls outils tiers : sécurité et sauvegardes gratuits.
- **Stripe seul** : Checkout hébergé (achat 1 500 € + 1er abonnement dans le même passage), Billing, portail client, factures, webhooks idempotents. Aucune donnée de carte sur le site.
- **n8n** (n8n.cosmartis.com) : détecte le paiement, génère et envoie la licence, le cBot la récupère via le port cloud cTrader (WebSocket 25345, **test cTrader encore à faire** ; repli : envoi par email). La clé privée de licence reste côté n8n/serveur, jamais dans WordPress ni dans le thème.
- Sécurité WordPress : 2FA admin, limitation des connexions, XML-RPC et éditeur de fichiers désactivés, sauvegardes quotidiennes hors serveur, requêtes préparées, nonces, échappement systématique, isolation stricte des données par client.
- Aucune clé secrète dans le code (constantes `wp-config.php`).

## Interdits absolus (résumé)
Aucune promesse de gain · toujours « performances simulées » + pire baisse à côté de chaque gain · aucun cumul spectaculaire en titre · aucune pub Forex/CFD · aucun lien d'affiliation broker · vocabulaire « récompense de recommandation », jamais « revenu » ou « opportunité ». Textes juridiques = provisoires, marqués « à faire valider par un avocat ».

## Tests obligatoires à écrire
Calcul des commissions · annulation après remboursement · génération/vérification des licences · webhooks Stripe (idempotence).

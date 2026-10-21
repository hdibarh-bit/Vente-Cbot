# Contexte projet — Vente du bot ICT/SMC

Source : Google Drive, « Contexte projet - Vente du bot ICT-SMC.docx » (21/10/2026, Hamid).

## Résumé
Hamid vend son bot de trading ICT/SMC sous forme de **cBot cTrader**, avec un **site de vente à thème doré** codé par Claude Code.

| Sujet | Décidé | Statut |
|---|---|---|
| Format | cBot installé dans le cloud cTrader du client, sans internet | Écrit (`IctSmcTendance.cs`), **jamais compilé** |
| Prix | 1 500 € + abonnement 39,90 €/mois ou 29,90 €/mois en annuel | Décidé |
| Parrainage | 225 € par vente (versés à J+30) + affiliation 10 % de l'abonnement | Décidé le 21/10/2026 |
| Licence | Clé signée envoyée par n8n, renouvelée à chaque paiement | Test de connexion cloud cTrader à faire |
| Site | Thème doré, codé par Claude Code | Brief : `prompt-claude-code.md` |

## Abandonné (ne pas réintroduire)
- VPS Hostinger par client → remplacé par le cloud cTrader.
- Parrainage à 5 niveaux → remplacé par 1 niveau.
- L'ancien document d'architecture du 20/10 est dépassé sur ces deux points.

## État technique du cBot
- Écrit en C# le 20/10/2026 (conversation « Bot cTrader stratégie tendance »). Moteur ICT : signaux identiques au bot n8n sur 322 situations de marché.
- Première compilation à faire dans cTrader (onglet Algo → Build).
- Le robot AMD (BTC/ETH) reste dans n8n et **ne fait pas partie de l'offre**.
- Fichier actuel réglé à 3 % / 3 % ; décision du 21/10/2026 : livrer à 1 % par défaut.

## Fichier de liaison
`SYNC-BOT-ICT.md` (Drive) : canal avec Claude/Cowork, à lire avant chaque session. Voir `CLAUDE.md` règle n°0.

## Autres ressources (Drive)
- Dossier « Trading Bot - Données backtest 2019-2026 » (CSV M15, 14 actifs) — propriétaire abbadibouchra77@gmail.com.
- Logos : « Logo de robot trader en or noir-1.png », « Logo doré futuriste BOT.COSMARTIS.COM.png » (dossier Drive du projet).
- Rapport PDF « Simulation bots cTrader 2024-2026 » (noir et doré) : à mettre à jour au réglage 1 % avant mise en téléchargement.
- Instance n8n : n8n.cosmartis.com. Alertes via Telegram.

## Ordre de lancement
1. Compiler le cBot. 2. Trancher la livraison de licence (test WebSocket). 3. Validation juridique. Puis pilote de 3 à 5 clients test avant ouverture publique.

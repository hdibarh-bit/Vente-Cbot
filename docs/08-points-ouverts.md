# Points ouverts et prochaines étapes

## Décisions à prendre par Hamid
- [x] Risque par défaut : 1 % (décidé le 21/10). Le fichier cBot actuel est encore à 3 %/3 % : à modifier.
- [x] TVA : auto-entrepreneur, pas de TTC/HT au départ (décidé le 21/10).
- [x] Parrain non client : touche ses commissions (décidé le 21/10).
- [x] Domaine : bot.cosmartis.com (décidé le 21/10).
- [x] Résultats : backtest du bot sur 2024-2026, jamais « 7 ans » (décidé le 21/10). Nouveau PDF à 1 %, 2 % et 3 % **à recevoir de Hamid** ; bot livré à 1 %, client libre jusqu'à 3 % (décidé le 21/10).
- [x] Le site ne décrit pas la stratégie ni les détails techniques (décidé le 21/10). Page « La stratégie » supprimée.
- [x] Nom du produit : **Arvator** (décidé le 21/10). Le logo existant porte « BOT.COSMARTIS.COM » : à refaire ou adapter au nom Arvator.
- [x] PDF de résultats : le nouveau PDF (1 %, 2 %, 3 %) ne contient aucun détail de stratégie. L'ancien PDF est archivé, ne pas le diffuser.
- [ ] « Feu vert site » (voir `SYNC-BOT-ICT.md` §5/§6) et prompt final.
- [x] Hébergement : Hostinger « Hébergement Premium » (mutualisé), précisé par Hamid le 21/10. **À clarifier** : existe-t-il aussi un serveur (VPS) pour le service de licence automatique (port 25345) ? Où tourne n8n.cosmartis.com ?

## Réponses de Hamid du 21/10/2026
- [x] Contact : contact@cosmartis.com.
- [x] Mentions légales : Hamid DIBARH, Fresnes-sur-Escaut, SIRET 80230131700029. **Adresse postale complète à ajouter** (obligatoire) ; mention de TVA à confirmer avec le comptable.
- [x] Remboursement (confirmé le 21/10) : 14 jours si achat sans essai, remboursement du **prix du robot** (pas de l'abonnement de la première période) ; pas de remboursement d'un achat fait après un essai. La renonciation au droit de rétractation est annoncée **avant la demande d'essai** (case obligatoire sur la page d'essai) et rappelée au paiement pour ceux qui ont fait l'essai.
- [x] Courtiers : « tout courtier proposant cTrader avec le cloud ».
- [x] Stripe : compte existant (clés de test à fournir à l'étape C).
- [x] Résiliation (confirmée le 21/10) : à tout moment depuis l'espace membre, effet à la fin de la période payée, période en cours non remboursée.
- [ ] Avocat : valider CGV, confidentialité, cookies, mentions légales, risques (bandeau « provisoire » à retirer ensuite).
- [ ] Envoi des emails : renseigner l'envoi SMTP dans wp-config.php (guide `10-installation-wordpress.md`).
- [ ] Vérifier la raison sociale d'hébergeur (Hostinger International Ltd., Larnaca, Chypre) dans les mentions légales.

## Avant le lancement public (priorité)
- [ ] Compiler le cBot `IctSmcTendance` dans cTrader (onglet Algo → Build) et corriger les erreurs.
- [ ] Tester la connexion WebSocket (port 25345) depuis le cloud cTrader → licence automatique ou manuelle.
- [ ] Valider le juridique (avocat) : CGV, avertissements, programme de parrainage (page « Conditions du programme de parrainage », provisoire), statut « vente de logiciel ».
- [x] Cookie de recommandation `arv_ref` : soumis au consentement depuis le 22/10 (bandeau cookies du thème 0.6.0, demande de Hamid). Reste à faire relire le bandeau et la page Cookies par l'avocat.
- [ ] Comptable : traitement des récompenses versées aux parrains (particuliers et entreprises), justificatifs.
- [ ] Hamid : confirmer que les récompenses sur abonnement attendent aussi 30 jours avant versement (choix prudent fait par Claude Code, modifiable).

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

## Licence (22/10/2026)
- [ ] Hamid : ajouter `ARVATOR_N8N_TOKEN` dans wp-config.php (guide `12-preparer-stripe.md` §8).
- [ ] Changement de compte cTrader après activation : aujourd'hui manuel (le client écrit, Hamid change dans n8n). À automatiser si le serveur de licences reçoit une action « changer de compte ».
- [ ] Essai gratuit : la licence est automatique, mais le fichier du robot est encore envoyé à la main par Hamid. Proposition : ouvrir le téléchargement aux personnes en essai depuis un compte membre.
- [ ] Sécurité anti-doublon côté serveur de licences : ignorer un même identifiant de paiement reçu deux fois.

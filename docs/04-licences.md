# Licences et automatisations n8n

## Principe
Clé signée que le cBot vérifie seul, **sans internet**. Elle contient le n° de compte cTrader du client et une date de fin. Signée côté serveur : impossible à fabriquer ou partager. Le cBot ne contient que la **clé publique** ; la clé privée reste sur le serveur (jamais dans le site ni le cBot).

## Format
`base64url(JSON) + "." + base64url(signature)`
JSON : `{ account, plan: "monthly" | "annual" | "trial", exp: ISO, demoOnly: boolean, v: 1 }`
Signature : **ECDSA P-256 / SHA-256** (compatible .NET 6 du cBot). Dossier `/cbot-licence` : clé publique + fonction C# d'exemple de vérification.

## Génération
À chaque paiement réussi (webhook Stripe). Mensuel : fin de période + 5 jours. Annuel : fin de période + 5 jours. Essai : 14 jours, `demoOnly = true`, **un seul essai par email et par compte**.

## Comportement du cBot
- Clé valide → trade normalement.
- Expirée → 3 jours de grâce, puis plus de nouveaux trades, mais gère les trades en cours (stop, sortie).
- Mauvais compte / clé modifiée → refuse de trader, l'indique dans son journal.
- Clé d'essai → 14 jours, compte **démo** uniquement (le cBot doit savoir reconnaître un compte démo : faisable dans cTrader, à coder).

## Décision du 21/10/2026
**C'est n8n qui détecte le paiement (Stripe), génère la licence et la livre au bot.** Le cBot la récupère via le port cloud. Le site WordPress n'héberge ni le service de licence ni la clé privée ; il affiche la licence dans l'espace client (reçue de n8n) et envoie les événements à n8n. Le service WebSocket Node (port 25345) reste à coder et documenter, mais **hors WordPress** (sur le VPS), et **non activé** tant que le test cTrader n'a pas réussi. Le dossier `/cbot-licence` (clé publique + exemple C# de vérification) reste à fournir. Réserve : la connexion WebSocket depuis le cloud cTrader reste à tester ; repli = envoi par email.

## Livraison de la clé (décision selon un test)
- **Automatique** : le cBot se connecte 1×/jour à un serveur de licences (WebSocket, **port 25345**, seul port autorisé dans le cloud cTrader). La doc cTrader se contredit : **test réel à faire** avec Hamid. Service Node séparé, codé et documenté mais activé seulement si le test réussit.
- **Manuelle (déjà sûre)** : n8n envoie la nouvelle clé par email 5 jours avant la fin ; le client la colle dans les réglages. Clé de 12 mois pour les abonnés annuels.

## À faire côté cBot (hors site)
Vérification de licence, mode essai démo, risque par défaut 1 %, choix 1/2/3 % (rien au-delà), **obscurcissement du code** et vérification de licence répartie à plusieurs endroits (le fichier peut être décompilé).

## Chaînes n8n (instance n8n.cosmartis.com, un seul workflow pour tous les clients)
1. **Paiement réussi** : enregistrement client, génération de clé, email avec lien espace client, prime de 225 € programmée à J+30 pour le parrain, alerte Telegram.
2. **Paiement d'abonnement échoué** : relances J+1, J+3, J+7, pas de nouvelle clé.
3. **Chaque paiement d'abonnement** : 10 % crédités au parrain.
4. **Chaque mois** : liste des virements d'affiliation préparée, validée par Hamid en un clic.
5. **Récap quotidien Telegram** : ventes, abonnements, impayés, commissions.

Événements envoyés par le site à n8n (webhook signé) : vente, paiement d'abonnement, échec de paiement, nouvelle commission, inscription au rapport gratuit, demande d'essai.

## Ce qui est codé côté site (plugin 0.6.0, 22/10/2026)
- Circuit A validé par Hamid : Stripe → WordPress → workflow n8n `licence-paiement` (corps JSON `compte`, `plan` = mensuel/annuel, `action` = paiement/resilier, `email`, `nom` ; en-tête `x-admin-token`). Essai : `licence-essai` (`compte`, `email`).
- Table `license_sync` : un envoi par paiement / résiliation, origine unique (`pay:inv:<facture>`, `res:sub:<abonnement>`) → jamais deux prolongations pour un même paiement ; nouvel essai horaire, alerte après 10 échecs.
- Numéro de compte cTrader : à l'achat (facultatif) ou dans l'espace membre ; verrouillé après activation (changement = demande à Hamid).
- Le site n'affiche pas de clé : le serveur renvoie seulement `ok` et `expiration`, le robot récupère sa licence lui-même.
- **Risque connu** : si n8n traite un envoi mais que sa réponse se perd (coupure réseau), l'envoi est retenté et la licence peut être prolongée deux fois. Peu probable ; à surveiller dans `Clients_cBot`. Remède durable : que le serveur de licences ignore un même identifiant de paiement reçu deux fois (le site pourrait l'envoyer).

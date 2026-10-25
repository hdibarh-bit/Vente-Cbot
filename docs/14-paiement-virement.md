# Paiement par virement SEPA (plugin 0.10.0)

Demande de Hamid du 25/10/2026. Le virement s'ajoute à la carte Stripe (rien n'est retiré du circuit Stripe). Il est proposé pour la **formule annuelle seulement** : robot 1 500 € + abonnement annuel 358,80 € = **1 858,80 €** au premier paiement, puis **358,80 € par an**. Pas de mensuel par virement (le mensuel passera plus tard par le prélèvement SEPA, non codé).

## Circuit
1. Le client choisit « Virement bancaire (SEPA) » sur la page *Acheter Arvator* (mêmes champs et mêmes cases que la carte, plus son **nom** pour la facture). Code promo et code de recommandation fonctionnent comme par carte ; le montant est calculé par le site. Si le total est de 0 €, le virement est refusé (choisir la carte).
2. Le site crée une commande **en attente** avec une **référence unique** `ARV-` + 6 chiffres aléatoires, puis affiche (et envoie par email) : titulaire, IBAN, BIC, **montant exact**, **référence à recopier** (bouton « copier »), conseil de virement instantané, délai « licence activée dans les 24 heures suivant la réception de votre virement » et un **QR code de virement SEPA (norme EPC)** généré par le plugin (aucun service extérieur). L'adresse de cette page contient un **jeton secret** (la référence seule n'y donne pas accès). Un même client qui recommande reste sur la même commande.
3. Le client vire. **n8n** lit les virements reçus sur Qonto une fois par jour (9 h, Paris) et appelle `POST /wp-json/arvator/v1/virement-recu` (voir ci-dessous).
4. Le site déroule **le même traitement qu'après un paiement Stripe** : compte client (email pour choisir son mot de passe), achat du robot et abonnement annuel enregistrés (fin = date de réception + 1 an), parrainage (prime de 225 € « à payer » 30 jours après la réception + 10 % de l'abonnement), code promo noté, **licence** via le circuit n8n existant (`licence-paiement`, origine `pay:bank:<référence>`), **facture** numérotée `FA-AAAA-NNNN`.

## Réglages `wp-config.php` (à poser par Hamid, jamais dans le dépôt)
```php
define( 'ARVATOR_VIREMENT_TITULAIRE', 'Nom du titulaire du compte' );
define( 'ARVATOR_VIREMENT_IBAN', 'FR76 ...' );
define( 'ARVATOR_VIREMENT_BIC', '...' );
define( 'ARVATOR_VIREMENT_SECRET', 'longue chaîne aléatoire (16 caractères au moins), la même dans n8n' );
```
Tant que ces quatre valeurs ne sont pas posées (et l'IBAN valide), le virement n'est pas proposé. Réglages dans **Arvator > Virements** : proposer le virement oui/non, délai d'annulation (30 jours), mention de TVA de la facture (à faire valider par le comptable).

## Point d'appel pour n8n
- **Adresse** : `POST https://arvator.com/wp-json/arvator/v1/virement-recu`
- **En-têtes** : `Content-Type: application/json`, `X-Arvator-Timestamp: <secondes Unix>`, `X-Arvator-Signature: <hexadécimal>`
- **Signature** : `HMAC-SHA256( clé = ARVATOR_VIREMENT_SECRET, message = horodatage + "." + corps brut )`, en hexadécimal. Horodatage de plus de 5 minutes ou signature fausse : **403**.
- **Corps (JSON)** :
```json
{ "transaction_id": "identifiant Qonto", "reference": "ARV-482913", "montant": 1858.80,
  "emetteur": "Nom de l'émetteur", "libelle": "libellé du virement", "date": "2026-10-26T08:12:00Z" }
```
`transaction_id` et `montant` sont obligatoires. Si `reference` est vide, le site la cherche dans `libelle` (« ARV 482913 », « arv-482913 » acceptés).
- **Exemple n8n (nœud Code)** :
```js
const crypto = require('crypto');
const ts = String(Math.floor(Date.now() / 1000));
const body = JSON.stringify({ transaction_id: $json.id, reference: $json.reference, montant: $json.amount, emetteur: $json.counterparty_name, libelle: $json.label, date: $json.settled_at });
const signature = crypto.createHmac('sha256', $env.ARVATOR_VIREMENT_SECRET).update(ts + '.' + body).digest('hex');
return [{ json: { body, ts, signature } }];
```
(Envoyer `body` tel quel, avec les deux en-têtes.)
- **Réponses** (JSON, `status`) : `ok` · `deja_traite` (transaction déjà vue, ou commande déjà payée) · `sans_reference` · `commande_inconnue` · `montant_insuffisant` (avec `attendu` et `recu`, commande toujours en attente) · `trop_percu` (accepté, licence lancée, Hamid à alerter) · `signature_invalide` (403) · `corps_invalide` (400) · `non_configure` (503) · `erreur_traitement` (500 : n8n peut réessayer, rien n'est dédoublé).
- **Idempotence** : un même `transaction_id` n'est traité qu'une fois ; en cas d'erreur 500 il est « relâché » pour un nouvel essai. Un virement reçu après l'annulation de la commande est accepté (l'argent est arrivé).
- n8n doit alerter Hamid (Telegram) pour tout `status` autre que `ok` / `deja_traite`.

## Commandes non payées
Rappels par email à **J+3** et **J+7** ; **annulation automatique à J+30** (modifiable). Tâche quotidienne WordPress (`arvator_bank_daily`).

## Renouvellement annuel
Pas de reconduction automatique. **30 jours avant l'échéance** le site crée une commande de renouvellement (nouvelle référence, 358,80 €, ou le montant réduit si le code promo était « à chaque paiement ») et envoie les rappels **J-30, J-14, J-7, J-3, J-1**, puis **J0** (licence échue, 3 jours de grâce) et **J+1, J+2** (le robot n'ouvre plus de nouveaux trades). Chaque email : montant, IBAN, référence, date limite, conseil de virement instantané. L'espace membre montre l'échéance, un bouton **« Renouveler par virement »**, les instructions des virements en attente et les factures. Paiement reçu : abonnement prolongé d'un an (à partir de l'ancienne échéance si elle n'est pas dépassée), licence prolongée via n8n, 10 % au parrain, nouvelle facture. La tâche quotidienne ne recrée jamais une commande de renouvellement annulée pour la même échéance.

## Administration (Arvator > Virements)
Liste par statut (en attente, payée, annulée, remboursée) avec recherche par référence ou email ; **Marquer payée** (identifiant de transaction obligatoire + date : même traitement que n8n) ; **Annuler** ; **Remboursement effectué** (Hamid rembourse lui-même par virement, puis clique : achat marqué remboursé, prime du parrain annulée ou déduite, licence coupée via n8n, abonnement arrêté) ; réglages.

## Sécurité
Signature HMAC + horodatage, comparaison à temps constant ; coordonnées bancaires dans `wp-config.php` seulement ; jeton secret pour la page des coordonnées ; facture accessible uniquement à son client (ou à un administrateur) ; requêtes préparées, clés de sécurité (nonces) sur chaque action ; RGPD : export inclus, effacement = factures gardées sans identité.

## Limites connues
- Le site ne lit pas la banque : tout repose sur l'appel de n8n (ou « Marquer payée »).
- Un virement en deux fois (complément) n'est pas additionné : « Marquer payée » à la main.
- Les achats par virement sont marqués « test » comme ceux de Stripe (même mode que la clé Stripe).
- Mention de TVA et texte des factures à faire valider par le comptable ; avocat conseillé pour les CGV.

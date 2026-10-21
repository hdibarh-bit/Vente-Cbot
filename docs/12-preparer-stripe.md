# Préparer Stripe (mode test) avant l'étape C

But : que Claude puisse construire le paiement (achat + abonnement) **sans aucun vrai argent**. Tout se fait d'abord en **mode test** de Stripe. Les intitulés de Stripe changent parfois un peu : l'idée reste la même.

## 1. Passer en mode test
Dans le tableau de bord Stripe, activez le **mode test** (interrupteur « Mode test » en haut à droite). Tout ce qui suit se fait dans ce mode.

## 2. Créer les produits et les prix (Catalogue de produits > Ajouter un produit)
| Produit | Prix à créer |
|---|---|
| « Arvator, achat du robot » | **1 500 €**, paiement **unique** |
| « Arvator, abonnement » | **39,90 €** par **mois** (récurrent) **et** **358,80 €** par **an** (récurrent) |

Notez l'identifiant de chacun des **trois prix** (il commence par `price_`). **Ce ne sont pas des secrets** : vous pouvez me les envoyer.

## 3. Récupérer les clés (Développeurs > Clés API)
- **Clé publiable** : commence par `pk_test_`.
- **Clé secrète** : commence par `sk_test_`.

**Ne m'envoyez JAMAIS la clé secrète dans un message.** Elle se met uniquement dans le fichier `wp-config.php` du site, avec des lignes que je vous donnerai à copier (à l'étape C). En production, on utilisera une clé restreinte.

## 4. Activer le portail client (Paramètres > Facturation > Portail client)
Activez-le et autorisez : annuler l'abonnement **à la fin de la période**, changer de formule (mensuelle ↔ annuelle), mettre à jour le moyen de paiement, voir et télécharger les factures.

## 5. Le point de réception des paiements (webhook)
Je vous donnerai l'**adresse exacte** à l'étape C (elle dépend du site). Vous créerez alors le point de terminaison dans Stripe (Développeurs > Webhooks) et vous copierez son **secret de signature** (`whsec_…`) dans `wp-config.php`, jamais ailleurs.

## 6. Factures
Dans les paramètres de facturation de Stripe, vérifiez le nom (Hamid DIBARH), l'adresse et le SIRET qui figurent sur les factures. Si vous êtes en franchise de TVA, la mention légale correspondante doit y figurer : **à confirmer avec votre comptable**.

## ⚠ Point à vérifier avant l'ouverture au public
Stripe limite ou refuse certaines activités liées au trading et aux marchés financiers. Votre activité est la **vente d'un logiciel** : décrivez-la ainsi, sans mot qui suggère un conseil ou un gain. Avant la mise en production, **demandez directement à Stripe** (support) que la vente d'un robot de trading sous forme de logiciel est acceptée sur votre compte. Si Stripe refusait, il faudrait prévoir un autre prestataire de paiement : mieux vaut le savoir **avant** de tout construire pour la production.

## Ce que vous m'envoyez
1. Les trois identifiants de prix (`price_…`).
2. La confirmation que le mode test est prêt et le portail client activé.
Je vous guide ensuite pour placer les clés dans `wp-config.php`.


## 6. Branchement du site (étape C) — à faire une fois le plugin 0.3.0 installé

### 6.1 Les lignes à ajouter dans `wp-config.php`
À coller **avant** la ligne « C'est tout, ne touchez pas à ce qui suit » (jamais ailleurs, jamais dans un message) :

```php
define( 'ARVATOR_STRIPE_SECRET', 'rk_test_...' );            // votre clé restreinte du bac à sable
define( 'ARVATOR_STRIPE_WEBHOOK_SECRET', 'whsec_...' );      // à l'étape 6.2
define( 'ARVATOR_STRIPE_PRICE_BOT', 'price_1UT3ndGi3zUNJ20g6odvYqxI' );
define( 'ARVATOR_STRIPE_PRICE_ANNUAL', 'price_1UT3pjGi3zUNJ20gRPhyyN9U' );
define( 'ARVATOR_STRIPE_PRICE_MONTHLY', 'price_1UT3qVGi3zUNJ20grHHXX4zU' );
```
(Les 3 `price_…` ci-dessus sont ceux du bac à sable. En production, on en crée 3 nouveaux et on remplace les 5 lignes.)

### 6.2 Le webhook (Stripe prévient le site après chaque paiement)
1. Stripe (en bac à sable) → Développeurs → Webhooks → **Ajouter un point de terminaison**.
2. Adresse : `https://bot.cosmartis.com/wp-json/arvator/v1/stripe`
3. Événements à cocher : `checkout.session.completed`, `customer.subscription.updated`, `customer.subscription.deleted`, `invoice.paid`, `invoice.payment_failed`, `charge.refunded`, `charge.dispute.created`.
   - **Depuis le plugin 0.5.0 (parrainage)** : si le webhook existe déjà, ajoutez-lui `invoice.paid` (récompense de 10 % sur chaque renouvellement d'abonnement) et `charge.dispute.created` (contestation bancaire : la récompense est retirée). Stripe → Développeurs → Webhooks → votre point de terminaison → « Modifier les événements ».
4. Après l'enregistrement, copier le **secret de signature** (`whsec_…`) dans la ligne `ARVATOR_STRIPE_WEBHOOK_SECRET`.

### 6.3 Droits de la clé restreinte
Écriture : Checkout Sessions, Customers, Subscriptions (nécessaire pour arrêter l'abonnement lors d'un remboursement), Customer portal sessions. Lecture : Prices, Products, Invoices, Charges, PaymentIntents, PaymentMethods (empreinte de la carte, pour repérer l'auto-parrainage). Si un droit manque, Stripe répond par une erreur claire (visible dans Développeurs → Journaux) : cocher la case indiquée.

### 6.4 Essai de bout en bout (bac à sable, 5 minutes)
1. Page « Acheter Arvator » → choisir l'annuel → payer avec la carte de test `4242 4242 4242 4242` (date future, code au choix).
2. Vérifier : email de confirmation reçu, compte créé, achat et abonnement visibles dans l'espace membre.
3. Espace membre → « Gérer mon abonnement » → le portail Stripe s'ouvre.
4. Dans Stripe : rembourser **1 500 €** de la facture → l'achat passe à « remboursé » et l'abonnement est arrêté.

## 7. Parrainage (plugin 0.5.0)

### 7.1 Une ligne de plus dans `wp-config.php` (recommandé, avant le premier parrain)
Les IBAN des parrains sont enregistrés **chiffrés**. Ajoutez une phrase secrète à vous, longue et inventée (jamais envoyée par message) :

```php
define( 'ARVATOR_DATA_KEY', 'une longue phrase secrète inventée, à garder aussi dans votre gestionnaire de mots de passe' );
```
Sans cette ligne, le chiffrement utilise les clés secrètes de WordPress. Ne changez plus cette phrase ensuite : les IBAN déjà enregistrés deviendraient illisibles (les parrains devraient les ressaisir).

### 7.2 Essai de bout en bout (bac à sable, 10 minutes)
1. Page « Parrainage » → s'inscrire avec une adresse A → email reçu avec le lien `?ref=CODE`.
2. Dans une fenêtre de navigation privée : ouvrir ce lien, puis « Acheter Arvator » : le code est prérempli. Payer avec la carte `4242 4242 4242 4242` et une adresse B.
3. Se connecter avec A → Espace membre → Parrainage : prime de 225 € « en attente » et 35,88 € (10 % de l'annuel), disponibles dans 30 jours. Saisir un IBAN de test (par exemple `FR76 3000 6000 0112 3456 7890 189`).
4. Dans Stripe, rembourser 1 500 € à B → la prime passe à « annulée ».
5. Arvator → Parrainage (tableau de bord WordPress) : onglets Virements, Récompenses, Parrains, Réglages.

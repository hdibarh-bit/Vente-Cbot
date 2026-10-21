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

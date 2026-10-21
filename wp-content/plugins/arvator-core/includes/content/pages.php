<?php
/**
 * Textes initiaux des pages. Modifiables ensuite dans Pages > Modifier.
 * Règles : aucune description de la stratégie, aucune promesse de gain, « performances simulées » partout.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(

	'a-propos' => array(
		'title' => 'À propos',
		'text'  => <<<'TXT'
## Un robot de trading automatique, présenté en toute transparence

Arvator est un robot de trading automatique. Il s'installe sur votre propre compte cTrader et fonctionne dans le cloud cTrader, même ordinateur éteint.

Arvator est conçu et édité par Hamid DIBARH, entrepreneur individuel établi à Fresnes-sur-Escaut.

## Notre façon de faire
- **Transparence :** nous montrons des performances simulées (backtest), toujours accompagnées des baisses de chaque simulation. Nous ne vendons pas de promesse.
- **Vos fonds restent chez vous :** nous ne touchons jamais à votre argent ni à votre compte. Nous vendons un logiciel.
- **Vous gardez le contrôle :** le robot est livré réglé à 1 % de risque par trade. Vous pouvez le monter à 2 % ou 3 %, jamais au-delà.
- **Pas de conseil :** Arvator n'est pas un conseil en investissement et ne gère pas votre compte à votre place.

## Ce qu'il faut savoir
Les résultats présentés sont des simulations sur 2024, 2025 et 2026. Le robot fonctionne en démonstration depuis septembre 2026 : il n'existe pas encore d'historique en argent réel. [Lire l'avertissement complet sur les risques](/risques/).

## Une question ?
Écrivez-nous depuis la page [Contact](/contact/) ou à {{email}}.
TXT,
	),

	'faq'      => array(
		'rev'   => 2,
		'title' => 'Questions fréquentes',
		'text'  => <<<'TXT'
## Le robot
?? Qu'est-ce qu'Arvator ?
Arvator est un robot de trading automatique pour cTrader. Il s'installe sur votre propre compte et fonctionne dans le cloud cTrader : il continue de travailler même si votre ordinateur est éteint.

?? Avec quel courtier fonctionne-t-il ?
Avec tout courtier proposant cTrader avec le cloud. Nous ne recommandons aucun courtier en particulier et ne touchons aucune commission d'un courtier.

?? Qui gère mon argent ?
Vous seul. Votre capital reste chez votre courtier, sur votre compte. Nous ne touchons jamais à votre argent ni à votre compte : nous vendons un logiciel.

?? Arvator garantit-il des gains ?
Non. Aucun robot ne peut garantir des gains. Les résultats affichés sont des performances simulées (backtest 2024-2026) qui ne préjugent pas des résultats futurs. Vous pouvez perdre tout ou partie du capital engagé. Voir la page [Risques](/risques/).

## Résultats et risque
?? Les résultats affichés sont-ils réels ?
Non, ce sont des simulations (backtest) réalisées sur 2024, 2025 et 2026. Le robot fonctionne en démonstration depuis septembre 2026 : il n'existe pas encore d'historique en argent réel.

?? Quel niveau de risque est livré ?
Le robot est livré réglé à 1 % de risque par trade. Vous pouvez le monter à 2 % ou 3 %, jamais au-delà. Un risque plus élevé amplifie aussi bien les gains que les baisses.

?? Comment changer le niveau de risque ?
Dans votre cloud cTrader, arrêtez le robot, modifiez le réglage « risque » (1 %, 2 % ou 3 %), puis relancez-le.

?? Pourquoi conseillez-vous au moins 5 000 € ?
Ce n'est pas une obligation. Avec un petit capital, la taille minimale de position imposée par le courtier ferait risquer plus que prévu : le robot refuse alors le trade. Il en prend donc moins, et les résultats deviennent plus irréguliers. Plus le capital est élevé, moins il y a de trades refusés.

## Prix, licence et abonnement
?? Combien ça coûte ?
Un achat unique de 1 500 € pour le robot, puis un abonnement obligatoire pour garder la licence active : 39,90 € par mois, ou 29,90 € par mois en formule annuelle (358,80 € payés d'avance pour l'année, soit 120 € d'économie par an). Au moment du paiement, vous réglez l'achat et le premier abonnement en une seule fois.

?? Que se passe-t-il si mon abonnement n'est plus payé ?
Votre licence expire. Après 3 jours de grâce, le robot ne prend plus de nouvelles positions, mais continue de gérer celles qui sont déjà ouvertes.

?? Où trouver ma licence et le robot ?
Dans votre [espace membre](/espace-membre/) : votre licence, le téléchargement du robot et votre abonnement.

?? Comment résilier mon abonnement ?
Depuis votre espace membre, à tout moment. La résiliation prend effet à la fin de la période déjà payée : votre licence reste active jusqu'à cette date. La période en cours n'est pas remboursée.

## Essai gratuit
?? Puis-je essayer avant d'acheter ?
Oui : un essai gratuit de 14 jours, uniquement sur un compte démo cTrader. Il vous permet de voir le robot travailler sans risquer d'argent. **En demandant l'essai, vous acceptez que, si vous achetez ensuite, vous renonciez à votre droit de rétractation :** l'achat n'est alors plus remboursable.

## Paiement et remboursement
?? Comment le paiement est-il sécurisé ?
Le paiement est traité par Stripe. Vos données de carte ne passent jamais par ce site.

?? Puis-je être remboursé ?
Si vous achetez sans avoir fait l'essai gratuit, vous disposez de 14 jours à compter de l'achat pour demander le remboursement du prix du robot (1 500 €). **L'abonnement de la première période n'est pas remboursé.** Le remboursement met fin à votre licence. Si vous avez fait l'essai gratuit avant d'acheter, vous avez renoncé à votre droit de rétractation : l'achat n'est pas remboursable. Pour demander un remboursement, écrivez à {{email}}.

## Compte et données
?? Comment accéder à mon espace membre ?
Votre compte est créé automatiquement après votre achat : vous recevez un email pour choisir votre mot de passe, puis vous vous connectez depuis la page [Espace membre](/espace-membre/).

?? Que faites-vous de mes données ?
Nous ne conservons que les données nécessaires à votre compte, à votre abonnement et à la facturation. Détails dans la [politique de confidentialité](/confidentialite/).

?? Comment vous contacter ?
Depuis la page [Contact](/contact/) ou par email à {{email}}.
TXT,
	),

	'contact'  => array(
		'title' => 'Contact',
		'text'  => <<<'TXT'
Une question avant d'acheter, un souci avec votre licence ou votre compte ? Écrivez-nous : nous répondons par email.

Vous pouvez aussi nous écrire directement à {{email}}.

[arvator_contact]
TXT,
	),

	'risques'  => array(
		'title' => 'Risques',
		'text'  => <<<'TXT'
[provisoire]
Le trading comporte un risque élevé de perte en capital, pouvant aller jusqu'à la totalité des sommes engagées. Les résultats présentés sont des performances simulées et ne préjugent pas des résultats futurs. Ce logiciel ne constitue pas un conseil en investissement.

## Vous pouvez perdre tout ou partie de votre capital
Investir sur les marchés financiers, en particulier avec un effet de levier, peut entraîner des pertes importantes. N'engagez que de l'argent que vous pouvez vous permettre de perdre.

## Les résultats affichés sont des simulations
- Ce sont des performances simulées (backtest), réalisées sur 2024, 2025 et 2026. Elles ne sont pas des résultats réels.
- Les réglages ont été choisis sur le même historique que celui qui les teste : les résultats futurs seront probablement moins bons.
- Il n'existe pas encore d'historique en argent réel : le robot fonctionne en démonstration depuis septembre 2026.
- L'avenir peut être très positif, nul ou négatif, avec une perte possible de tout le capital engagé.

## Les baisses font partie du fonctionnement
Chaque simulation affichée montre la pire baisse et le solde le plus bas. Plus le niveau de risque est élevé (1 %, 2 % ou 3 % par trade), plus les gains simulés comme les baisses peuvent être importants. Le robot est livré à 1 % ; vous pouvez le monter jusqu'à 3 %, jamais au-delà.

## Le capital compte
Avec un petit capital, beaucoup de trades sont refusés (taille minimale de position imposée par le courtier) : le robot en prend moins et les résultats sont plus irréguliers. Nous conseillons au moins 5 000 €, sans que ce soit une garantie de résultat.

## Vous restez responsable
- Vous êtes seul responsable de votre compte, de votre courtier, de vos réglages et de vos décisions.
- Nous ne touchons jamais à votre argent ni à votre compte.
- Arvator n'est pas un conseil en investissement et ne gère pas votre compte à votre place.
- Des incidents techniques (connexion, courtier, plateforme) peuvent survenir et affecter le fonctionnement du robot.

## Avant d'acheter
Lisez les [questions fréquentes](/faq/), les [conditions générales de vente](/cgv/) et, si vous avez un doute, demandez conseil à un professionnel agréé.
TXT,
	),

	'mentions-legales' => array(
		'title' => 'Mentions légales',
		'text'  => <<<'TXT'
[provisoire]
## Éditeur du site
Le site bot.cosmartis.com (marque « Arvator ») est édité par :
- Hamid DIBARH, entrepreneur individuel (micro-entrepreneur)
- Établi à Fresnes-sur-Escaut (France)
- SIRET : 802 301 317 00029
- Email : {{email}}
- Directeur de la publication : Hamid DIBARH

## Hébergeur
Hostinger International Ltd., 61 Lordou Vironos Street, 6023 Larnaca, Chypre.

## Propriété intellectuelle
Le robot Arvator, le site, ses textes, ses images et sa marque sont protégés. Toute reproduction, copie, diffusion ou extraction du robot ou de son code sans autorisation écrite est interdite.

## Avertissement
Arvator est un logiciel. Il ne constitue pas un conseil en investissement. Les résultats présentés sont des performances simulées. Voir la page [Risques](/risques/).

## Données personnelles
Voir la [politique de confidentialité](/confidentialite/) et la page [Cookies](/cookies/).
TXT,
	),

	'cgv'      => array(
		'rev'   => 2,
		'title' => 'Conditions générales de vente',
		'text'  => <<<'TXT'
[provisoire]
## 1. Éditeur et objet
Ces conditions s'appliquent à toute vente du robot de trading Arvator par Hamid DIBARH, entrepreneur individuel établi à Fresnes-sur-Escaut, SIRET 802 301 317 00029 (« l'éditeur »), au client qui passe commande sur bot.cosmartis.com. Contact : {{email}}.

## 2. Produit
Arvator est un logiciel (un robot cTrader) livré sous forme de fichier numérique. L'achat donne au client une licence d'utilisation personnelle, non exclusive et non transférable, liée à un numéro de compte cTrader. Le client reste propriétaire de son argent et de son compte : l'éditeur n'y a jamais accès.

## 3. Prix
- Achat du robot : 1 500 €, payé une seule fois.
- Abonnement obligatoire pour garder la licence active : 39,90 € par mois, ou 358,80 € par an (soit 29,90 € par mois, payés d'avance).
Les prix sont en euros et ceux affichés au moment de la commande s'appliquent.

## 4. Commande et paiement
Le paiement est traité par Stripe. Au moment de la commande, le client règle l'achat du robot et le premier abonnement en une seule fois. L'abonnement se renouvelle automatiquement chaque mois ou chaque année jusqu'à sa résiliation. En cas d'échec de paiement d'un abonnement, aucune nouvelle licence n'est délivrée.

## 5. Licence et abonnement
La licence est valable pour la période d'abonnement payée. À son expiration, une période de grâce de 3 jours s'applique ; ensuite le robot ne prend plus de nouvelles positions, mais continue de gérer celles déjà ouvertes. Le client s'interdit de copier, décompiler, modifier, revendre ou partager le robot ou sa licence.

## 6. Livraison
Le robot et la licence sont accessibles dans l'espace membre du client après la validation du paiement.

## 7. Essai gratuit
L'essai gratuit dure 14 jours et fonctionne uniquement sur un compte démo cTrader. Il est limité à un essai par personne. Avant de demander l'essai, le client est informé et accepte expressément que, s'il achète ensuite le robot, il renonce à son droit de rétractation.

## 8. Remboursement
- Achat sans essai gratuit : le client peut demander le remboursement du prix du robot (1 500 €) dans les 14 jours suivant l'achat, par email à {{email}}. L'abonnement de la première période n'est pas remboursé. Le remboursement met fin à la licence.
- Achat après un essai gratuit : le client a pu tester le robot et a expressément renoncé à son droit de rétractation avant l'essai ; l'achat n'est pas remboursable.

## 9. Résiliation
Le client peut résilier son abonnement à tout moment depuis son espace membre. La résiliation prend effet à la fin de la période déjà payée ; la période en cours n'est pas remboursée.

## 10. Responsabilité et risques
Le trading comporte un risque élevé de perte en capital. Les résultats présentés sont des performances simulées et ne préjugent pas des résultats futurs. Le client est seul responsable de ses réglages, de son compte, de son courtier et de ses décisions. Le logiciel ne constitue pas un conseil en investissement. Voir la page [Risques](/risques/).

## 11. Données personnelles
Les données du client sont traitées selon la [politique de confidentialité](/confidentialite/).

## 12. Droit applicable et litiges
Le droit français s'applique. En cas de difficulté, le client peut écrire à {{email}}. Le client consommateur peut aussi recourir gratuitement à un médiateur de la consommation (coordonnées communiquées sur demande).
TXT,
	),

	'confidentialite' => array(
		'title' => 'Politique de confidentialité',
		'text'  => <<<'TXT'
[provisoire]
## Qui est responsable de vos données
Hamid DIBARH, entrepreneur individuel établi à Fresnes-sur-Escaut (SIRET 802 301 317 00029), est responsable du traitement. Contact : {{email}}.

## Les données que nous traitons
- Votre compte : nom, adresse email, mot de passe (jamais en clair).
- Vos achats et abonnements : produit, montant, dates, état de l'abonnement (le paiement lui-même est traité par Stripe : nous ne voyons jamais votre numéro de carte).
- Votre licence : clé de licence et numéro de compte cTrader associé.
- Vos messages : le contenu de ce que vous nous écrivez depuis la page Contact.

## Pourquoi, et sur quelle base
- Gérer votre compte, votre achat et votre abonnement, et vous livrer le robot : exécution du contrat.
- Facturer et respecter nos obligations comptables : obligation légale.
- Répondre à vos messages : votre demande.
- Protéger le site contre les abus (limitation des connexions, anti-spam) : intérêt légitime.

## Combien de temps
Les données de votre compte sont conservées tant que votre compte existe. Les pièces comptables (achats, factures) sont conservées 10 ans, comme la loi l'exige. Les messages de contact ne sont pas conservés sur le site : ils arrivent dans notre boîte email.

## Qui y a accès
L'éditeur, ainsi que les prestataires strictement nécessaires : Stripe (paiement), Hostinger (hébergement du site) et notre fournisseur d'envoi d'emails. Nous ne vendons pas vos données.

## Vos droits
Vous pouvez accéder à vos données, les rectifier, les exporter ou demander leur effacement. Depuis votre [espace membre](/espace-membre/), rubrique « Mes informations », vous pouvez demander l'export ou l'effacement de vos données. Vous pouvez aussi écrire à {{email}}. Vous pouvez enfin saisir la CNIL (cnil.fr) en cas de difficulté.

## Cookies
Voir la page [Cookies](/cookies/).
TXT,
	),

	'cookies'  => array(
		'title' => 'Cookies',
		'text'  => <<<'TXT'
[provisoire]
## Ce site n'utilise que des cookies nécessaires
Ce site n'utilise que les cookies indispensables à son fonctionnement : ils servent à vous connecter à votre espace membre et à protéger votre compte. Ils sont supprimés à la fermeture de votre navigateur, ou après quelques jours si vous avez coché « Se souvenir de moi ».

## Ce que nous n'utilisons pas
Ce site n'utilise aucun cookie publicitaire et aucun outil de mesure d'audience. Si cela change, vous serez invité à donner votre accord avant tout dépôt de cookie.

## Paiement
Lorsque vous payez, vous êtes redirigé vers la page de paiement sécurisée de Stripe, qui dépose ses propres cookies nécessaires au paiement.
TXT,
	),

	'espace-membre' => array(
		'title' => 'Espace membre',
		'text'  => <<<'TXT'
[arvator_membre]
TXT,
	),
);

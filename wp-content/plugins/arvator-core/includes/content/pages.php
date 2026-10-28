<?php
/**
 * Textes initiaux des pages. Modifiables ensuite dans Pages > Modifier.
 * Règles : aucune description de la stratégie, aucune promesse de gain, « performances simulées » partout.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(

	// Page d'accueil : le thème affiche lui-même le contenu de l'accueil (front-page.php) ; cette page sert à la choisir dans
	// Réglages > Lecture. Le texte ci-dessous n'apparaît pas sur le site.
	'accueil'  => array(
		'rev'   => 1,
		'title' => 'Accueil',
		'text'  => <<<'TXT'
Page d'accueil du site. Son contenu est géré par le thème Arvator : ne pas le modifier ici.
TXT,
	),

	'a-propos' => array(
		'rev'   => 3,
		'title' => 'À propos',
		'text'  => <<<'TXT'
## Un robot de trading automatique, présenté en toute transparence

Arvator est un robot de trading automatique. Il s'installe sur votre propre compte cTrader et fonctionne dans le cloud cTrader, même ordinateur éteint.

Arvator est conçu et édité par Hamid DIBARH, entrepreneur individuel établi à Fresnes-sur-Escaut.

[arvator_auteur]

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
		'rev'   => 5,
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

?? Pourquoi conseillez-vous au moins 2 000 € ?
Ce n'est pas une obligation. Avec un petit capital, la taille minimale de position imposée par le courtier ferait risquer plus que prévu : le robot refuse alors le trade. Il en prend donc moins, et les résultats deviennent plus irréguliers. À partir de 2 000 €, notre backtest (qui inclut les frais d'un courtier, par exemple IC Markets) ne compte que 20 trades refusés au risque 1 %, et aucun aux risques 2 % et 3 %.

## Prix, licence et abonnement
?? Combien ça coûte ?
Un achat unique de 1 500 € pour le robot[arvator_offre_lancement], puis un abonnement obligatoire pour garder la licence active : 39,90 € par mois, ou 29,90 € par mois en formule annuelle (358,80 € payés d'avance pour l'année, soit 120 € d'économie par an). Au moment du paiement, vous réglez l'achat et le premier abonnement en une seule fois. Vous pouvez payer par carte bancaire (via Stripe) ou par virement bancaire (formule annuelle).

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
Le paiement par carte est traité par Stripe. Vos données de carte ne passent jamais par ce site. Pour un virement, c'est vous qui le faites depuis votre banque : nous ne voyons jamais vos identifiants bancaires.

?? Puis-je payer par virement ?
Oui, pour la formule annuelle : 1 500 € pour le robot + 358,80 € pour la première année d'abonnement, soit 1 858,80 € au premier paiement, puis 358,80 € par an. Après votre commande, vous recevez les coordonnées bancaires, le montant exact et une référence (par exemple ARV-123456) à recopier dans le libellé du virement. Votre licence est activée dans les 24 heures suivant la réception de votre virement. Le virement ne se reconduit pas tout seul : nous vous prévenons avant l'échéance et vous renouvelez en payant.

?? Puis-je être remboursé ?
Si vous achetez sans avoir fait l'essai gratuit, vous disposez de 14 jours à compter de l'achat pour demander le remboursement du prix du robot (1 500 €). **L'abonnement de la première période n'est pas remboursé.** Le remboursement met fin à votre licence. Si vous avez fait l'essai gratuit avant d'acheter, vous avez renoncé à votre droit de rétractation : l'achat n'est pas remboursable. Pour demander un remboursement, écrivez à {{email}}. Si vous avez payé par virement, nous vous remboursons par virement.

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
		'rev'   => 2,
		'title' => 'Contact',
		'text'  => <<<'TXT'
Une question avant d'acheter, un souci avec votre licence ou votre compte ? Écrivez-nous : nous répondons par email.

Vous pouvez aussi nous écrire directement à {{email}}.

[arvator_contact]
TXT,
	),

	'risques'  => array(
		'rev'   => 2,
		'title' => 'Risques',
		'text'  => <<<'TXT'
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
Avec un petit capital, beaucoup de trades sont refusés (taille minimale de position imposée par le courtier) : le robot en prend moins et les résultats sont plus irréguliers. Nous conseillons au moins 2 000 €, sans que ce soit une garantie de résultat.

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
		'rev'   => 5,
		'title' => 'Mentions légales',
		'text'  => <<<'TXT'
## Éditeur du site
Le site {{domain}} (marque « Arvator ») est édité par :
- Hamid DIBARH, entrepreneur individuel exerçant sous le nom commercial COSMARTIS
- Adresse : {{adresse}}
- SIRET : 802 301 317 00029
- Email : {{email}}
- Directeur de la publication : Hamid DIBARH

[arvator_auteur]

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
		'rev'   => 8,
		'title' => 'Conditions générales de vente',
		'text'  => <<<'TXT'
## 1. Éditeur et objet
Ces conditions s'appliquent à toute vente du robot de trading Arvator par Hamid DIBARH, entrepreneur individuel exerçant sous le nom commercial COSMARTIS, {{adresse}}, SIRET 802 301 317 00029 (« l'éditeur »), au client qui passe commande sur {{domain}}. Contact : {{email}}.

## 2. Produit
Arvator est un logiciel (un robot cTrader) livré sous forme de fichier numérique. L'achat donne au client une licence d'utilisation personnelle, non exclusive et non transférable, liée à un numéro de compte cTrader. Le client reste propriétaire de son argent et de son compte : l'éditeur n'y a jamais accès.

## 3. Prix
- Achat du robot : 1 500 €[arvator_offre_lancement], payé une seule fois.
- Abonnement obligatoire pour garder la licence active : 39,90 € par mois, ou 358,80 € par an (soit 29,90 € par mois, payés d'avance).
Les prix sont en euros et ceux affichés au moment de la commande s'appliquent.

## 4. Commande et paiement
Le paiement par carte est traité par Stripe. Au moment de la commande, le client règle l'achat du robot et le premier abonnement en une seule fois. L'abonnement payé par carte se renouvelle automatiquement chaque mois ou chaque année jusqu'à sa résiliation. En cas d'échec de paiement d'un abonnement, aucune nouvelle licence n'est délivrée.

Le client peut aussi payer par virement bancaire, pour la formule annuelle seulement (achat du robot et première année d'abonnement, puis chaque année). Après la commande, le client reçoit les coordonnées bancaires, le montant exact et une référence à indiquer dans le libellé du virement. La licence est activée dans les 24 heures suivant la réception du virement. Une commande non payée est annulée automatiquement après 30 jours. L'abonnement payé par virement ne se renouvelle pas automatiquement : le client est prévenu avant l'échéance et le renouvelle en effectuant un nouveau virement ; sans paiement, la période de grâce de 3 jours s'applique, puis le robot ne prend plus de nouvelles positions. Une facture est disponible dans l'espace membre. Le remboursement du prix du robot, lorsqu'il est dû, se fait par virement.

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
		'rev'   => 6,
		'title' => 'Politique de confidentialité',
		'text'  => <<<'TXT'
## Qui est responsable de vos données
Hamid DIBARH, entrepreneur individuel établi au {{adresse}} (SIRET 802 301 317 00029), est responsable du traitement. Contact : {{email}}.

## Les données que nous traitons
- Votre compte : nom, adresse email, mot de passe (jamais en clair).
- Vos achats et abonnements : produit, montant, dates, état de l'abonnement (le paiement par carte est traité par Stripe : nous ne voyons jamais votre numéro de carte ; pour un virement, nous gardons votre nom, votre email, le montant et la référence de la commande, et jamais vos coordonnées bancaires).
- Votre licence : clé de licence et numéro de compte cTrader associé.
- Vos messages : le contenu de ce que vous nous écrivez depuis la page Contact.
- Si vous êtes parrain : votre code de recommandation, les achats faits avec votre lien (date et état, sans identité de l'acheteur), vos récompenses, et pour le virement le nom du titulaire, votre IBAN (conservé chiffré), votre statut et, pour une entreprise, votre SIRET.
- Si vous avez été recommandé : le parrain auquel votre achat est rattaché.

## Pourquoi, et sur quelle base
- Gérer votre compte, votre achat et votre abonnement, et vous livrer le robot : exécution du contrat.
- Facturer et respecter nos obligations comptables : obligation légale.
- Gérer le programme de parrainage, verser les récompenses et prévenir la fraude (auto-parrainage) : exécution du contrat avec le parrain et intérêt légitime.
- Répondre à vos messages : votre demande.
- Protéger le site contre les abus (limitation des connexions, anti-spam) : intérêt légitime.

## Combien de temps
Les données de votre compte sont conservées tant que votre compte existe. Les pièces comptables (achats, factures) sont conservées 10 ans, comme la loi l'exige. Les messages de contact ne sont pas conservés sur le site : ils arrivent dans notre boîte email.

## Qui y a accès
L'éditeur, ainsi que les prestataires strictement nécessaires : Stripe (paiement par carte), notre banque (réception des virements), Hostinger (hébergement du site) et notre fournisseur d'envoi d'emails. Nous ne vendons pas vos données.

## Vos droits
Vous pouvez accéder à vos données, les rectifier, les exporter ou demander leur effacement. Depuis votre [espace membre](/espace-membre/), rubrique « Mes informations », vous pouvez demander l'export ou l'effacement de vos données. Vous pouvez aussi écrire à {{email}}. Vous pouvez enfin saisir la CNIL (cnil.fr) en cas de difficulté.

TXT . Arvator_Pages::CHAT_PRIVACY . <<<'TXT'


## Cookies
Voir la page [Cookies](/cookies/).
TXT,
	),

	'cookies'  => array(
		'rev'   => 4,
		'title' => 'Cookies',
		'text'  => <<<'TXT'
## Les cookies de ce site
Ce site n'utilise que deux sortes de cookies. Aucun cookie publicitaire, aucun outil de mesure d'audience, aucun partage avec des tiers.

### Cookies nécessaires (toujours actifs)
Ils sont indispensables au fonctionnement du site et ne demandent pas votre accord :
- **Connexion :** ils vous connectent à votre espace membre et protègent votre compte. Ils sont supprimés à la fermeture du navigateur, ou après quelques jours si vous avez coché « Se souvenir de moi ».
- **Vos choix sur les cookies (« arv_consent ») :** ils retiennent votre accord ou votre refus pendant 6 mois, pour ne pas vous redemander à chaque page. Au bout de 6 mois, la question vous est reposée.

### Cookie de recommandation (seulement avec votre accord)
Si vous arrivez sur le site par le lien d'un parrain, et **seulement si vous l'acceptez**, son code de recommandation est retenu 90 jours (cookie « arv_ref »), pour lui attribuer sa récompense si vous achetez. Il ne contient aucune donnée sur vous et n'est lu que par ce site. Si vous refusez, rien n'est déposé : vous pourrez toujours saisir le code du parrain à la main au moment de l'achat.

## Changer d'avis
Vous pouvez modifier ou retirer votre accord à tout moment avec le lien **« Gérer les cookies »** en bas de chaque page. Si vous retirez votre accord, le cookie de recommandation est supprimé. Refuser n'a aucune conséquence sur l'accès au site ni sur l'achat.

## Paiement
Lorsque vous payez, vous êtes redirigé vers la page de paiement sécurisée de Stripe, qui dépose ses propres cookies nécessaires au paiement.
TXT,
	),

	'parrainage' => array(
		'rev'   => 2,
		'title' => 'Parrainage',
		'text'  => <<<'TXT'
## Recommandez Arvator à vos proches
Vous connaissez quelqu'un que le trading automatique intéresse ? Partagez votre lien de recommandation. S'il achète le robot avec ce lien, vous recevez une **récompense de recommandation**.

[arvator_parrainage]

## Comment ça marche
- **Inscription gratuite**, sans obligation d'acheter le robot.
- Vous recevez un **lien personnel** à partager. Il est mémorisé 90 jours sur l'appareil de la personne qui clique, si elle accepte le cookie de recommandation ; sinon, elle peut saisir votre code au moment de l'achat.
- La récompense d'achat est disponible **30 jours après l'achat** (après le délai de remboursement), puis versée par **virement bancaire**.
- La récompense sur les abonnements est versée une fois par mois, dès que le cumul atteint 50 €.
- Seule la personne qui a recommandé est récompensée : il n'y a qu'un seul niveau.
- Si l'achat est remboursé ou contesté, la récompense correspondante est retirée.

## Recommander de façon honnête
Arvator est un logiciel, pas une promesse de gain. Quand vous le recommandez, ne promettez jamais de résultat : les performances présentées sur ce site sont **simulées** (backtest 2024-2026) et le trading comporte un risque élevé de perte en capital. La récompense de recommandation n'est jamais assurée : elle dépend uniquement des achats réellement faits avec votre lien.

Voir les [conditions du programme de parrainage](/conditions-parrainage/).
TXT,
	),

	'conditions-parrainage' => array(
		'rev'   => 4,
		'title' => 'Conditions du programme de parrainage',
		'text'  => <<<'TXT'
## 1. Objet
Le programme de parrainage Arvator permet à toute personne inscrite (le « parrain ») de recevoir une récompense de recommandation lorsqu'une personne qu'elle a recommandée (le « filleul ») achète le robot Arvator. Il est édité par Hamid DIBARH, entrepreneur individuel (voir les [mentions légales](/mentions-legales/)).

## 2. Inscription
L'inscription est gratuite et n'oblige à aucun achat. Elle est réservée aux personnes majeures. Chaque parrain reçoit un code et un lien personnels. Il est interdit de se parrainer soi-même.

## 3. Rattachement d'un filleul
Le filleul est rattaché au parrain dont le lien a été utilisé (le code est mémorisé 90 jours sur son appareil s'il accepte le cookie de recommandation) ou dont il saisit le code au moment de l'achat. Le parrain est fixé définitivement lors du premier achat du robot par le filleul.

## 4. Récompenses
- **Prime d'achat :** 225 € pour chaque achat du robot payé par un filleul.
- **Récompense sur abonnement :** 10 % de chaque paiement d'abonnement d'un filleul (soit 3,99 € par mois pour l'abonnement mensuel, 35,88 € par an pour l'abonnement annuel), tant qu'il reste abonné.
- Un seul niveau : seul le parrain direct est récompensé, jamais un « parrain du parrain ».
- Les montants en vigueur sont ceux affichés au moment de l'achat du filleul.

## 5. Disponibilité et versement
Chaque récompense est « en attente » pendant 30 jours après le paiement du filleul, puis devient « à payer ». La prime d'achat est versée par virement bancaire dès qu'elle est à payer. Les récompenses sur abonnement sont versées une fois par mois, à partir de 50 € cumulés. Le parrain indique son IBAN et son statut (particulier ou entreprise, avec son numéro SIRET) dans son espace membre ; sans IBAN, aucun virement n'est possible. Le parrain est seul responsable de la déclaration de ses récompenses auprès de l'administration fiscale, selon sa situation.

## 6. Remboursement, contestation, annulation
Si l'achat d'un filleul est remboursé (droit de rétractation) ou contesté auprès de sa banque, la récompense correspondante est retirée. Si elle n'a pas encore été versée, elle est annulée. Si elle a déjà été versée, son montant est déduit des récompenses suivantes du parrain ; à défaut, le parrain s'engage à le rembourser.

## 7. Fraude et bonne conduite
Sont interdits : l'auto-parrainage (même personne, même adresse email, même moyen de paiement), les faux comptes, le spam, la publicité trompeuse et toute promesse de gain. Le parrain doit présenter Arvator honnêtement : les performances présentées sont simulées et le trading comporte un risque élevé de perte en capital. Une récompense suspecte est bloquée le temps d'une vérification. En cas de manquement, le lien peut être suspendu et les récompenses concernées annulées.

## 8. Nature du programme
La récompense de recommandation n'est pas un salaire et n'est jamais assurée : elle dépend uniquement des achats réellement payés par des filleuls. Le parrain agit en son nom propre et ne représente pas Arvator.

## 9. Données personnelles
L'IBAN est conservé chiffré et sert uniquement au versement des récompenses. Le parrain ne voit aucune donnée personnelle de ses filleuls (seulement la date et l'état de leurs achats). Voir la [politique de confidentialité](/confidentialite/).

## 10. Modification et fin du programme
Les conditions peuvent évoluer ; les récompenses déjà acquises restent dues selon les conditions en vigueur au moment de l'achat du filleul. Le parrain peut quitter le programme à tout moment en écrivant à {{email}}.
TXT,
	),

	'essai-gratuit' => array(
		'title' => 'Essai gratuit de 14 jours',
		'text'  => <<<'TXT'
Essayez Arvator pendant 14 jours **sur un compte démo cTrader** : aucun argent n'est engagé. Il suffit d'une adresse email et du numéro de votre compte démo.

Ce que vous devez savoir avant de demander l'essai : **si vous achetez le robot après l'essai, vous renoncez à votre droit de rétractation**, et l'achat n'est alors plus remboursable. Sans essai, vous disposez de 14 jours pour demander le remboursement du prix du robot (voir les [conditions générales de vente](/cgv/)).

[arvator_essai]

L'essai est limité à un par personne. Les résultats présentés sur le site sont des performances simulées : le trading comporte un risque élevé de perte en capital.
TXT,
	),

	'acheter' => array(
		'title' => 'Acheter Arvator',
		'text'  => <<<'TXT'
[arvator_achat]
TXT,
	),

	'espace-membre' => array(
		'title' => 'Espace membre',
		'text'  => <<<'TXT'
[arvator_membre]
TXT,
	),
);

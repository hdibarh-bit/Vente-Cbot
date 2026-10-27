# Journal des versions

Chaque nouvelle version se réinstalle par-dessus l'ancienne (voir `docs/10-installation-wordpress.md`) : le logo, l'icône, les menus et les textes que vous avez réglés sont conservés.

## Thème Arvator

### 0.8.3 — 27/10/2026
- **Menu d'en-tête : « Accueil » en premier** (page d'accueil, marquée comme page courante quand on y est). Si vous avez créé votre propre menu dans Apparence > Menus, « Accueil » y est ajouté automatiquement quand il n'y figure pas.

### 0.8.2 — 25/10/2026
- Section Tarifs : « Paiement par carte (sécurisé par Stripe) ou par virement bancaire pour la formule annuelle ».

### 0.8.1 — 25/10/2026
- Explication du minimum conseillé de 2 000 € : « 20 trades refusés au risque 1 %, aucun aux risques 2 % et 3 % » (chiffres confirmés par Hamid).

### 0.8.0 — 25/10/2026
- **Un seul backtest de référence** (décision Cowork + Hamid du 25/10/2026) : « Backtest avec les frais réels d'un courtier ». Bloc de référence 5 000 € au risque 1 % (15 202 €, +204 %, pire baisse −20,1 % / −3 771 € en cours au 25/10/2026), détail par année du même compte, puis onglets 1 % / 2 % / 3 % par capital (500 € à 20 000 €) avec pire baisse (% et €, dates) et trades refusés, note sur les petits capitaux, méthode expliquée (IC Markets cité comme exemple de courtier, sans lien).
- Disparus : colonnes par année qui repartaient du capital de départ, colonne « 2024 → 2026 enchaînés », ancien rapport PDF.
- Capital minimum conseillé : **2 000 €** (au lieu de 5 000 €).

### 0.7.0 — 23/10/2026
- **Prêt pour une version anglaise, rien ne change à l'écran** (décision de Hamid : préparer seulement). Tous les textes du thème sont traduisibles (accueil, en-tête, pied de page, bandeau cookies, bandeau de recommandation, résultats, tarifs, menus par défaut, avertissement sur les risques). Fichier modèle : `languages/arvator.pot`. Voir `docs/13-version-anglaise.md`.
- Textes de l'accueil (Apparence > Personnaliser) : un texte laissé tel qu'à l'origine sera traduit ; un texte modifié par vous reste tel que vous l'avez écrit.

### 0.6.6 — 23/10/2026
- Photo de Hamid (À propos, Mentions légales) affichée **sans cadre** : plus de bordure, d'angles arrondis ni d'ombre.

### 0.6.5 — 23/10/2026
- Accueil : le bandeau « Recommandez Arvator ! » passe **tout en haut de la page, juste sous l'en-tête**.
- **Toute la carte est cliquable** (pastille « Recommandez Arvator ! », texte et image) et mène à la page Parrainage. Avant, seuls l'image et « voir conditions » réagissaient. Léger effet doré au survol.

### 0.6.4 — 22/10/2026
- Pied de page (sous le logo) : icônes des réseaux sociaux de Hamid (LinkedIn ; Facebook et X dès que leurs liens sont renseignés). Pas de photo.
- Style de la carte « éditeur » (photo, nom, réseaux) des pages À propos et Mentions légales, ordinateur et mobile.

### 0.6.3 — 22/10/2026
- Adresse du thème : https://arvator.com (domaine officiel, décision de Hamid du 22/10/2026). Aucun changement visible.

### 0.6.2 — 22/10/2026
- **Résultats en euros ET en pourcentage** (demande de Hamid : « ça parle beaucoup plus aux gens »). Chaque case du tableau affiche le solde simulé du compte (ex. « 9 013 € ») et, dessous, l'évolution (« +80 % »). Même chose dans la carte du haut de l'accueil.
- Nouvelle colonne **« 2024 → 2026 enchaînés »** : le solde si le robot avait tourné sur toute la période sans retrait (ex. 5 000 € → 23 186 € au risque 1 %), toujours à côté de la pire baisse et du solde le plus bas.
- Les années négatives s'affichent en rouge. Note sous le tableau et carte du haut précisées (montants = solde simulé en fin de période).

### 0.6.1 — 22/10/2026
- Style du bandeau défilant des courtiers (espace membre) : cadres or, noms argent, pause au survol, immobile si l'utilisateur limite les animations.

### 0.6.0 — 22/10/2026
- **Bandeau de consentement aux cookies** (RGPD, recommandations de la CNIL), en bas de page : « Tout refuser » aussi visible que « Tout accepter », « Personnaliser » (case « Recommandation » jamais précochée), lien « En savoir plus » vers la page Cookies. Le choix est retenu 6 mois, puis redemandé. Il ne bloque pas la lecture du site.
- Pied de page : lien « Gérer les cookies » (changer d'avis à tout moment) et « Développé par cosmartis.com » (lien vers cosmartis.com).

### 0.5.1 — 21/10/2026
- Pied de page : lien « Conditions du parrainage ».
- Page « Parrainage » : mise en forme des deux récompenses (225 € et 10 %).
- Correction : le texte des cases à cocher (achat, essai, parrainage) ne se coupe plus en colonnes quand il contient un lien.
- Un peu d'espace sous les tableaux de l'espace membre.

### 0.5.0 — 21/10/2026
- **Bandeau « Recommandez Arvator ! »** sur l'accueil (avant « Essayez avant d'acheter ») : « 225 €* par recommandation », image à droite (en dessous sur mobile), renvoi « *Récompense de recommandation versée 30 jours après l'achat, s'il n'est pas remboursé. Inscription gratuite, voir conditions ». Montant et délai repris de `config/reglages.json`.
- Le bandeau n'apparaît que lorsque la page « Parrainage » (adresse `parrainage`) est publiée : jamais d'offre sans ses conditions.
- Image : illustration dorée fournie ; remplaçable par une photo dans Apparence > Personnaliser > « Arvator — textes de l'accueil ».

### 0.4.0 — 21/10/2026
- **Appels à l'action partout, discrets** : en-tête (« Acheter » + « Essai gratuit »), haut de page (essai en bouton principal, tarifs en second), sous les trois étapes, sous les résultats, dans chaque carte de tarif (« Choisir l'annuel » / « Choisir le mensuel », formule présélectionnée sur la page d'achat), bandeau final (essai + achat), pied de page, et bandeau en bas des pages FAQ, À propos et Résultats. Aucun bandeau sur les pages légales, risques, achat, essai, contact et espace membre.
- Le bandeau « Essayez avant d'acheter » est toujours affiché.

### 0.3.1 — 21/10/2026
- Bouton « Acheter Arvator » dans la section Tarifs (page d'achat) ; mise en forme du choix d'abonnement.

### 0.3.0 — 21/10/2026
- Styles des nouvelles pages : FAQ (questions qui se déplient), formulaire de contact, espace membre (tableau de bord, achats, licence avec bouton « Copier », téléchargements, informations), avis « version provisoire » des textes légaux.
- Menu et pied de page : FAQ, À propos, Contact, Espace membre ; lien « Connexion » / « Mon espace » en haut à droite.
- L'espace membre s'affiche en pleine largeur ; correction d'un débordement sur mobile.

### 0.2.1 — 21/10/2026
- Toutes les mentions « bientôt » sont retirées du site. Un bouton dont la page n'existe pas est simplement masqué ; en haut de l'accueil, les boutons de repli mènent aux sections « Tarifs » et « Résultats ». Le bandeau « Essayez avant d'acheter » n'apparaît que lorsque la page d'essai gratuit existe.

### 0.2.0 — 21/10/2026
- Logo, icône du site (favicon), menus et textes de l'accueil modifiables depuis Apparence > Personnaliser.
- Plus de lien vers une page qui n'existe pas encore : le menu n'affiche que les pages publiées, les boutons concernés indiquent « bientôt disponible ».
- Icône dorée intégrée tant qu'aucune icône n'est choisie.
- Correction : le logo choisi ne crée plus de lien imbriqué.

### 0.1.0 — 21/10/2026
- Première version : thème doré, page d'accueil, résultats simulés 1 % / 2 % / 3 %, tarifs.

## Plugin Arvator Core

### 0.10.0 — 25/10/2026
- **Paiement par virement SEPA** (formule annuelle : robot + abonnement, puis renouvellement annuel). Voir `docs/14-paiement-virement.md`.
  - Page d'achat : choix « Virement bancaire (SEPA) » / « Carte bancaire », nom pour la facture ; le mensuel est masqué en mode virement.
  - Commande « en attente » avec référence unique `ARV-123456`, page et email avec IBAN, BIC, montant exact, référence à copier, délai de 24 h, **QR code de virement (EPC)** généré sans service extérieur ; une seule commande ouverte par client.
  - Point d'appel signé `POST /wp-json/arvator/v1/virement-recu` pour n8n (HMAC-SHA256 + horodatage 5 min, idempotent par identifiant de transaction ; réponses `ok`, `deja_traite`, `sans_reference`, `commande_inconnue`, `montant_insuffisant`, `trop_percu`).
  - Virement reçu : même traitement qu'un paiement Stripe (compte, email, achat, abonnement, parrainage, code promo, licence via n8n, facture numérotée).
  - Rappels J+3 / J+7, annulation automatique à J+30 ; renouvellement annuel (commande 30 jours avant, rappels J-30 à J+2), bouton « Renouveler par virement », factures dans l'espace membre.
  - Administration Arvator > Virements : liste, recherche, marquer payée, annuler, remboursement effectué, réglages. RGPD : export et effacement pris en compte.
  - FAQ, CGV et confidentialité : virement, délai d'activation, renouvellement sans reconduction automatique.
- Réseaux sociaux : Facebook, Instagram et X (0.9.4).

### 0.9.4 — 25/10/2026
- **Réseaux sociaux** : Facebook, Instagram (@arvatorbot) et X (@arvatorbot) ajoutés à côté de LinkedIn et Telegram (pied de page, À propos, Mentions légales). Modifiables dans Apparence > Personnaliser > « Arvator : éditeur et réseaux sociaux » ; un lien vidé masque le réseau.

### 0.9.3 — 25/10/2026
- FAQ : même explication chiffrée du minimum conseillé de 2 000 €.

### 0.9.2 — 25/10/2026
- FAQ et page Risques : capital minimum conseillé **2 000 €** (au lieu de 5 000 €), avec la raison (presque plus de trades refusés dans le backtest à partir de 2 000 €).

### 0.9.1 — 25/10/2026
- **Un code promo peut réduire le robot, l'abonnement, ou les deux en même temps**, chacun avec son pourcentage (ex. robot à -30 % + 6 mois d'abonnement offerts). Arvator > Codes promo : deux cases « Réduction sur le robot » et « Réduction sur l'abonnement » (0 = rien, 100 = offert).
- Le robot réduit garde le même produit Stripe, à un prix recalculé pour ce paiement ; l'abonnement réduit passe par un coupon Stripe.
- Les codes déjà créés avec la 0.9.0 sont repris automatiquement.
- Tests automatiques : 476 contrôles.

### 0.9.0 — 24/10/2026
- **Codes promo** (Arvator > Codes promo) : vous créez vos codes vous-même. Un code donne un avantage : un pourcentage sur le prix du robot, OU sur l'abonnement (100 % = offert) pour la 1re période, pendant un nombre de mois (formule mensuelle) ou à chaque paiement. Formule au choix (mensuel, annuel ou les deux), nombre d'utilisations maximum et date de fin facultatifs. Désactiver / réactiver en un clic ; liste des clients qui ont utilisé chaque code.
- **Page d'achat** : champ « Code promo » (affiché seulement s'il existe un code actif), prérempli par un lien `/acheter/?promo=CODE`. Code faux, terminé ou d'une autre formule : message clair. La réduction est appliquée par Stripe et visible sur sa page de paiement.
- Parrainage : prime de 225 € inchangée ; 10 % sur ce que le client paie vraiment (décision de Hamid).
- Mois offerts : chaque mois à 0 € prolonge quand même la licence. Premier paiement à 0 € accepté. Remboursement : comparé au prix réellement payé pour le robot.
- **À faire dans Stripe** : ajouter le droit « Coupons : écriture » à la clé restreinte (guide `docs/12-preparer-stripe.md` §9).
- Tests automatiques : 468 contrôles (46 nouveaux).

### 0.8.1 — 24/10/2026
- **Bandeau « Version provisoire » retiré** (demande de Hamid) des pages Risques, Mentions légales, CGV, Politique de confidentialité, Cookies et Conditions du programme de parrainage.
- **CGV, article 1** : le vendeur est identifié ainsi : « Hamid DIBARH, entrepreneur individuel exerçant sous le nom commercial COSMARTIS, 21 rue Taffin, 59970 Fresnes-sur-Escaut, France, SIRET 802 301 317 00029 ».
- **Mentions légales** : même identification (« entrepreneur individuel exerçant sous le nom commercial COSMARTIS », adresse avec « France »).
- Ces 6 pages se mettent à jour toutes seules si vous ne les avez jamais modifiées ; sinon elles sont signalées en haut du tableau de bord : faites la correction à la main.

### 0.8.0 — 23/10/2026
- **Prêt pour une version anglaise, rien ne change à l'écran ni dans les emails en français.** Textes traduisibles : espace membre (toutes les rubriques), page d'achat, essai gratuit, contact, parrainage, licence, courtiers, carte de l'éditeur, messages d'erreur et de confirmation, emails aux clients et aux parrains, export des données personnelles. Fichier modèle : `languages/arvator-core.pot`. L'administration et les emails d'alerte à Hamid restent en français.
- Paiement et portail Stripe affichés dans la langue de la page (français par défaut). La langue de la page d'achat est transmise à Stripe : l'email de confirmation de commande et l'email de virement aux parrains partent dans la langue du client.
- 415 tests réussis ; comparaison page par page avant/après : aucune différence visible.

### 0.7.5 — 23/10/2026
- Photo de Hamid : fond noir accordé à celui du site, pour qu'elle se fonde dans la page sans cadre visible (à installer avec le thème 0.6.6).

### 0.7.4 — 23/10/2026
- **Nouvelle photo de Hamid** (pages À propos et Mentions légales). Si une photo a été choisie dans Apparence > Personnaliser > « Arvator : éditeur et réseaux sociaux », c'est elle qui reste affichée : retirez-la pour voir la nouvelle.
- **Telegram ajouté** aux réseaux (https://t.me/Bot_Arvator), sur la carte de l'éditeur et dans le pied de page. Lien modifiable ou masquable au même endroit.

### 0.7.3 — 22/10/2026
- **Photo de Hamid** dans « À propos » et « Mentions légales », à l'endroit où son nom est cité, avec ses réseaux sociaux dessous (shortcode `[arvator_auteur]`). Photo livrée avec le plugin, remplaçable.
- **Réseaux sociaux** : LinkedIn rempli ; Facebook et X à compléter dans **Apparence > Personnaliser > « Arvator : éditeur et réseaux sociaux »**. Un réseau sans lien n'est pas affiché. Seuls les liens https du vrai réseau sont acceptés, sans paramètres de suivi.
- **Adresse postale complète** (21 rue Taffin, 59970 Fresnes-sur-Escaut) dans Mentions légales, CGV et Confidentialité, depuis une seule constante (`Arvator_Site::POSTAL`). Textes mis à jour (`rev` : à-propos 3, mentions légales 4, CGV 5, confidentialité 4).

### 0.7.2 — 22/10/2026
- **Domaine officiel arvator.com** (décision de Hamid du 22/10/2026, remplace bot.cosmartis.com) et **adresse officielle support@arvator.com** : une seule source pour les deux, `includes/class-site.php` (`Arvator_Site::EMAIL` et `Arvator_Site::DOMAIN`). Plus aucune valeur écrite en dur ailleurs (un test le vérifie).
- Pages **Mentions légales** et **CGV** : le domaine arvator.com remplace bot.cosmartis.com (textes mis à jour automatiquement si la page n'a jamais été modifiée à la main ; sinon elle est signalée dans le tableau de bord).
- **Tous les emails du site** (confirmation d'achat, essai, parrainage, virement, contact, alertes) se terminent par la même signature : « Arvator · https://arvator.com · support@arvator.com ».
- Les messages du formulaire Contact arrivent à support@arvator.com.

### 0.7.1 — 22/10/2026
- **Nouvelle adresse de contact : support@arvator.com** (définitive). Le formulaire de contact, les alertes (essais, contestations, licences, parrainage) et les emails du site l'utilisent ; elle s'affiche dans toutes les pages (mentions légales, CGV, confidentialité, FAQ, À propos, Contact, conditions du parrainage).
- Les pages jamais modifiées dans l'éditeur reçoivent la nouvelle adresse automatiquement à l'installation ; une page que vous avez modifiée est conservée et signalée en haut du tableau de bord (à corriger à la main).
- 390 tests automatiques réussis.

### 0.7.0 — 22/10/2026 — Courtiers compatibles
- Nouvelle rubrique **« Courtiers »** dans l'espace membre, réservée aux clients ayant acheté : bandeau défilant des courtiers proposant cTrader (liste indicative), chaque nom ouvre le site officiel dans un nouvel onglet. Avertissements : pas de recommandation, aucune commission, vérifier REGAFI / liste noire AMF, essai en démo, risque de perte.
- **Jamais sur les pages publiques** (décision de Hamid, risque de « publicité indirecte » Sapin 2).
- Liste modifiable dans **Arvator > Courtiers** (une ligne « Nom | https://adresse »). Tout paramètre d'adresse (`?ref=`, `?affid=`…) est retiré automatiquement : aucun lien d'affiliation possible.
- Liste de départ : Pepperstone, IC Markets, FxPro, FP Markets.
- 378 tests automatiques réussis.

### 0.6.2 — 22/10/2026
- Espace parrain : **un seul lien** de recommandation, qui mène directement à la page d'achat (code prérempli même si le visiteur refuse les cookies). Même lien dans l'email d'inscription.

### 0.6.1 — 22/10/2026
- Espace parrain : deuxième lien à copier, **direct vers la page d'achat** (`/acheter/?ref=CODE`). Le code du parrain y est toujours prérempli, même si le visiteur refuse les cookies. Conseil affiché au parrain ; les deux liens figurent aussi dans l'email d'inscription.
- 363 tests automatiques réussis.

### 0.6.0 — 22/10/2026 — Licence automatique (n8n)
- **Le site prévient le workflow n8n « Licences cBot – Paiements »** : achat et chaque renouvellement payé → licence prolongée ; résiliation ou remboursement → licence coupée. Jamais deux prolongations pour le même paiement, même si Stripe renvoie un message.
- **Numéro de compte cTrader** : champ facultatif sur la page d'achat, et rubrique « Ma licence » de l'espace membre (« Activer ma licence »). Verrouillé après activation ; un changement passe par Hamid.
- **Essai gratuit** activé automatiquement par le serveur de licences ; Hamid reçoit un email pour envoyer le robot. Si n8n ne répond pas, la demande arrive à Hamid comme avant.
- **n8n en panne** : nouvel essai toutes les heures, email à Hamid après 10 échecs. Nouvel écran **Arvator > Licences** (suivi, bouton « Renvoyer »).
- Espace membre : plus de clé à copier, la licence est récupérée par le robot ; date de fin et compte affichés.
- Réglage à ajouter dans wp-config.php : `ARVATOR_N8N_TOKEN` (voir `docs/12-preparer-stripe.md` §8).
- 361 tests automatiques réussis (fausse API n8n : aucun appel au vrai serveur pendant les tests).

### 0.5.1 — 22/10/2026
- Le cookie de recommandation (`arv_ref`) n'est plus déposé **qu'avec l'accord** du visiteur (bandeau cookies du thème 0.6.0). S'il retire son accord, le cookie est effacé à la visite suivante.
- Sans accord, le code du parrain reste utilisable : prérempli sur la page d'achat s'il est dans l'adresse (`/acheter/?ref=CODE`), sinon saisi à la main.
- Textes mis à jour (seulement si vous ne les avez pas modifiés) : Cookies, Parrainage, Conditions du programme de parrainage.
- 323 tests automatiques réussis.

### 0.5.0 — 21/10/2026 — Parrainage
- **Pages créées automatiquement** : « Parrainage » (montants, inscription gratuite) et « Conditions du programme de parrainage » (provisoire, à faire valider par un avocat). Le bandeau « Recommandez Arvator » de l'accueil s'affiche dès que la page Parrainage existe.
- **Devenir parrain** : gratuit, sans achat, depuis la page Parrainage (création du compte + email avec le lien) ou en un clic depuis l'espace membre.
- **Lien de recommandation** `?ref=CODE` mémorisé 90 jours ; le code est prérempli (et modifiable) sur la page d'achat, puis transmis à Stripe. Le parrain est fixé au premier achat.
- **Récompenses** : 225 € par achat du robot et 10 % de chaque abonnement payé (premier abonnement compris), « à payer » 30 jours après le paiement. Aucun doublon si Stripe renvoie un message.
- **Remboursement** du prix du robot : la prime est annulée (ou déduite des récompenses suivantes si elle a déjà été versée). **Contestation bancaire** : prime et premier abonnement retirés, Hamid prévenu par email.
- **Anti-fraude** : même compte, même email, même client Stripe ou même carte → récompenses bloquées + email d'alerte.
- **Espace membre > Parrainage** : lien à copier, récompenses par état, IBAN (enregistré chiffré, affiché masqué), particulier ou entreprise (SIRET).
- **Administration > Arvator > Parrainage** : virements (lot → fichier CSV nom/IBAN/montant → « virements effectués » → email au parrain), récompenses (débloquer/annuler), parrains (suspendre un lien), réglages (montants, taux, délai, seuil).
- Textes mis à jour (seulement si vous ne les avez pas modifiés) : Cookies (cookie de recommandation) et Confidentialité (données des parrains).
- Stripe : **2 événements à ajouter au webhook** : `invoice.paid` et `charge.dispute.created` (voir `docs/12-preparer-stripe.md`).
- 309 tests automatiques réussis (dont 90 pour le parrainage).

### 0.4.0 — 21/10/2026
- **Page « Essai gratuit de 14 jours »** (créée automatiquement) : email, numéro du compte démo cTrader, case obligatoire de **renonciation au droit de rétractation annoncée avant la demande**, consentement. Un seul essai par email et par numéro de compte. La demande est enregistrée et envoyée à contact@cosmartis.com ; le demandeur reçoit une confirmation avec le rappel. La licence d'essai elle-même arrive avec l'étape licence.
- **Rappel de renonciation au paiement** : si l'email a demandé un essai, l'achat exige la case de renonciation ; elle est notée dans la commande Stripe (`trial_waiver`). Sans essai : aucune renonciation demandée.
- Page d'achat : la formule peut être présélectionnée (`?plan=annual` ou `monthly`).
- Tests automatiques : 219 contrôles.

### 0.3.0 — 21/10/2026 (étape C : paiement Stripe, bac à sable)
- **Page « Acheter Arvator »** (créée automatiquement) : choix annuel ou mensuel, email, deux cases obligatoires (CGV, risques), départ vers la page de paiement Stripe. Robot (paiement unique) et premier abonnement dans le même paiement. Aucune donnée de carte sur le site.
- **Retour de paiement (webhook Stripe)** : signature vérifiée, messages de plus de 5 minutes refusés, chaque événement traité une seule fois, nouvel essai automatique par Stripe en cas d'erreur. Crée le compte client (email pour choisir son mot de passe), l'achat et l'abonnement ; suit les changements d'abonnement et les résiliations.
- **Remboursement** : dès que le montant remboursé couvre le prix du robot, l'achat passe à « remboursé », la licence est révoquée et l'abonnement est arrêté chez Stripe (l'abonnement de la première période n'est pas remboursé).
- **Espace membre** : bouton « Gérer mon abonnement, mes factures et ma carte » (portail client Stripe).
- Réglages dans `wp-config.php` (clé, secret du webhook, 3 prix) : voir `docs/12-preparer-stripe.md`.
- Tests automatiques : 186 contrôles (fausse API Stripe, aucun appel réel).

### 0.2.1 — 21/10/2026
- **Règles de remboursement précisées** dans la FAQ et les CGV : achat sans essai = 14 jours, remboursement du prix du robot, pas de remboursement de l'abonnement de la première période, le remboursement met fin à la licence ; achat après essai = renonciation au droit de rétractation, annoncée avant l'essai ; résiliation à la fin de la période payée, période en cours non remboursée.
- **Mise à jour des textes des pages** : un texte amélioré remplace celui de la page **seulement si vous ne l'avez jamais modifiée** ; sinon la page est conservée et un message dans l'administration vous invite à la relire.
- Tests automatiques : 129 contrôles.

### 0.2.0 — 21/10/2026
- **Pages créées automatiquement** (une seule fois, jamais écrasées) : À propos, FAQ, Contact, Risques, Mentions légales, CGV, Confidentialité, Cookies, Espace membre. Textes légaux provisoires, à faire valider par un avocat.
- **Formulaire de contact** protégé (clé de sécurité, champ piège, délai, limites par adresse IP et par email, aucun message conservé sur le site) ; envoi à contact@cosmartis.com ; envoi par SMTP possible via wp-config.php.
- **Espace membre sécurisé** : rôle « Client Arvator » sans accès au tableau de bord ; connexion ; tableau de bord, achats et abonnement, licence, téléchargement du robot, mot de passe, export et effacement des données (RGPD) ; isolation stricte des données de chaque client.
- **Administration** (menu « Arvator ») : mise en ligne du fichier du robot (stockage privé), clients de démonstration.
- Tables dédiées (achats, abonnements, licences, journal) ; traitement unique des événements (idempotence).
- Tests automatiques d'intégration (`tests/run.sh`) : 122 contrôles.

### 0.1.0 — 21/10/2026
- Sécurité dès l'activation : éditeur de fichiers désactivé, XML-RPC bloqué, comptes non listés, connexions limitées (5 échecs → blocage de 15 minutes), message d'erreur de connexion unique, en-têtes de sécurité.
- Aucune fonction métier pour l'instant (licences, espace client, parrainage et administration viendront ensuite).

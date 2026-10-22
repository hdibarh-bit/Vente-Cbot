# Installer et mettre à jour le site Arvator (WordPress)

Deux fichiers à installer, dans le dossier `releases/` du dépôt (ou envoyés par Claude) :

| Fichier | Rôle |
|---|---|
| `arvator-theme.zip` | L'apparence du site (thème doré) |
| `arvator-core.zip` | Le cœur du site : sécurité, pages (FAQ, À propos, Contact…), formulaire de contact, espace membre, paiement Stripe, parrainage (lien de recommandation, récompenses, virements), administration |

## Première installation

1. **Version de PHP** : dans hPanel (Hostinger) > Sites Web > Gérer > PHP, choisir **PHP 8.2 ou 8.3** (8.1 minimum).
2. **Thème** : tableau de bord WordPress > Apparence > Thèmes > Ajouter > Téléverser un thème > choisir `arvator-theme.zip` > Installer > **Activer**.
3. **Plugin** : Extensions > Ajouter > Téléverser une extension > choisir `arvator-core.zip` > Installer > **Activer**.
4. **Langue et titre** : Réglages > Général : Langue du site = **Français**, Titre du site = **Arvator**. Enregistrer.
5. **Adresses des pages** : Réglages > Permaliens > « Titre de la publication » > Enregistrer.
6. **Page d'accueil** : rien à régler, le thème affiche tout seul l'accueil.
7. **Pages créées automatiquement** par le plugin (au premier chargement du site) : À propos, FAQ, Contact, Risques, Mentions légales, CGV, Confidentialité, Cookies, Espace membre. Vérifiez-les dans Pages ; vous pouvez les modifier. Une page que vous supprimez n'est jamais recréée.
8. **Textes légaux** : un bandeau « Version provisoire » s'affiche sur les pages légales. Faites-les valider par un avocat, complétez l'adresse dans « Mentions légales », puis supprimez le bandeau (dans l'éditeur de la page).

## Réglages de l'espace membre et du contact
- **Envoi des emails** (messages du formulaire de contact, emails du site) : pour qu'ils arrivent bien (et pas en spam), créez la boîte `support@arvator.com` dans hPanel (Emails ; le domaine arvator.com doit y être ajouté, sinon l'adresse du serveur `smtp.hostinger.com` ci-dessous est à remplacer par celle de votre hébergeur d'emails), puis ajoutez dans `wp-config.php` (jamais ailleurs) :
  `define( 'ARVATOR_SMTP_HOST', 'smtp.hostinger.com' );` `define( 'ARVATOR_SMTP_PORT', 465 );` `define( 'ARVATOR_SMTP_SECURE', 'ssl' );` `define( 'ARVATOR_SMTP_USER', 'support@arvator.com' );` `define( 'ARVATOR_SMTP_PASS', 'le-mot-de-passe-de-la-boîte' );` `define( 'ARVATOR_MAIL_FROM', 'support@arvator.com' );`
  (valeurs exactes indiquées dans hPanel > Emails > Configuration). Testez ensuite le formulaire de contact.
- **Mettre le robot en ligne** : tableau de bord > Arvator > Fichiers privés : choisissez le fichier (.algo, .zip ou .pdf) et un nom. Il n'est visible de personne d'autre que des clients ayant acheté.
- **Voir l'espace membre comme un client** : Arvator > Clients de démonstration > Créer. Le mot de passe s'affiche une seule fois ; ouvrez une fenêtre de navigation privée pour vous connecter. Supprimez-les ensuite d'un clic.
- **Dossier privé** : par défaut au-dessus du dossier public du site si l'hébergement le permet. Vérifiez qu'on ne peut pas ouvrir l'adresse d'un fichier de ce dossier. Pour choisir l'emplacement, ajoutez dans `wp-config.php` : `define( 'ARVATOR_PRIVATE_DIR', '/chemin/vers/un/dossier/hors/du/site' );`

## Ce que vous réglez vous-même (sans passer par Claude)

Tout se fait dans **Apparence > Personnaliser** :

- **Logo et icône du site (favicon)** : « Identité du site ». Logo : de préférence PNG ou SVG à fond transparent, environ 400 × 100 px. Icône : image carrée d'au moins **512 × 512 px**.
- **Menus** : « Menus » > créer un menu, ajouter vos pages, choisir son emplacement (Menu principal, Menu du pied de page, Liens légaux). Tant qu'aucun menu n'est créé, le thème affiche automatiquement les pages existantes.
- **Textes de l'accueil** : « Arvator — textes de l'accueil » (petit texte, titre, introduction, texte du pied de page). Ne décrivez jamais la stratégie et ne promettez aucun gain.
- **Pages** : Pages > Ajouter. Une page n'apparaît dans le menu et sur les boutons qu'une fois **publiée**. Adresses attendues par le thème : `tarifs`, `resultats`, `essai-gratuit`, `rapport-gratuit`, `parrainage`, `faq`, `a-propos`, `contact`, `risques`, `mentions-legales`, `cgv`, `confidentialite`, `cookies`, `conditions-parrainage`, `espace-membre`.

Volontairement **non modifiable** dans le thème : l'avertissement sur les risques, les chiffres des résultats et les prix (ils doivent rester conformes à la loi et à ce qui est réellement facturé). Pour les changer, demander à Claude.

Les textes des pages (FAQ, À propos, textes légaux…) se modifient dans Pages, avec l'éditeur de WordPress.

## Sécurité à ajouter (gratuit, 10 minutes)

Le plugin Arvator Core active déjà : éditeur de fichiers désactivé, XML-RPC bloqué, liste des comptes masquée, connexions limitées (5 échecs = blocage 15 minutes), en-têtes de sécurité. Il reste deux protections à installer (Extensions > Ajouter) :

1. **Two Factor** (par l'équipe WordPress.org) : double authentification. Utilisateurs > Votre profil > activer une application d'authentification (ou les codes par email) **pour votre compte administrateur**.
2. **UpdraftPlus** (gratuit) : sauvegardes automatiques **hors serveur** (par exemple vers Google Drive), tous les jours. Vérifiez aussi les sauvegardes incluses dans votre offre Hostinger (hPanel).

Et : un mot de passe long et unique pour l'administrateur, et ne pas utiliser « admin » comme identifiant.

## Mettre à jour (quand Claude livre une nouvelle version)

1. Même chemin que l'installation : Apparence > Thèmes > Ajouter > Téléverser (ou Extensions > Ajouter > Téléverser).
2. WordPress dit que le thème (ou l'extension) existe déjà : cliquer **« Remplacer l'actuel par celui téléversé »**.
3. Vos réglages (logo, icône, menus, textes) sont **conservés**.
4. Le numéro de version est dans `CHANGELOG.md`.

## En cas de problème
- Page blanche après une mise à jour : réinstaller la version précédente de la même façon, puis prévenir Claude.
- Impossible de se connecter après plusieurs erreurs : attendre 15 minutes (blocage automatique).

# Installer et mettre à jour le site Arvator (WordPress)

Deux fichiers à installer, dans le dossier `releases/` du dépôt (ou envoyés par Claude) :

| Fichier | Rôle |
|---|---|
| `arvator-theme.zip` | L'apparence du site (thème doré) |
| `arvator-core.zip` | La sécurité (et plus tard : licences, espace client, parrainage, administration) |

## Première installation

1. **Version de PHP** : dans hPanel (Hostinger) > Sites Web > Gérer > PHP, choisir **PHP 8.2 ou 8.3** (8.1 minimum).
2. **Thème** : tableau de bord WordPress > Apparence > Thèmes > Ajouter > Téléverser un thème > choisir `arvator-theme.zip` > Installer > **Activer**.
3. **Plugin** : Extensions > Ajouter > Téléverser une extension > choisir `arvator-core.zip` > Installer > **Activer**.
4. **Langue et titre** : Réglages > Général : Langue du site = **Français**, Titre du site = **Arvator**. Enregistrer.
5. **Adresses des pages** : Réglages > Permaliens > « Titre de la publication » > Enregistrer.
6. **Page d'accueil** : rien à régler, le thème affiche tout seul l'accueil.

## Ce que vous réglez vous-même (sans passer par Claude)

Tout se fait dans **Apparence > Personnaliser** :

- **Logo et icône du site (favicon)** : « Identité du site ». Logo : de préférence PNG ou SVG à fond transparent, environ 400 × 100 px. Icône : image carrée d'au moins **512 × 512 px**.
- **Menus** : « Menus » > créer un menu, ajouter vos pages, choisir son emplacement (Menu principal, Menu du pied de page, Liens légaux). Tant qu'aucun menu n'est créé, le thème affiche automatiquement les pages existantes.
- **Textes de l'accueil** : « Arvator — textes de l'accueil » (petit texte, titre, introduction, texte du pied de page). Ne décrivez jamais la stratégie et ne promettez aucun gain.
- **Pages** : Pages > Ajouter. Une page n'apparaît dans le menu et sur les boutons qu'une fois **publiée**. Adresses attendues par le thème : `tarifs`, `resultats`, `essai-gratuit`, `rapport-gratuit`, `parrainage`, `faq`, `risques`, `mentions-legales`, `cgv`, `confidentialite`, `cookies`, `espace-client`.

Volontairement **non modifiable** dans le thème : l'avertissement sur les risques, les chiffres des résultats et les prix (ils doivent rester conformes à la loi et à ce qui est réellement facturé). Pour les changer, demander à Claude.

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

# Espace membre : conception et sécurité

Exigence de Hamid (21/10/2026) : « très bien codé et très solide » ; chaque client y retrouve ce qu'il a acheté, son abonnement, etc.

## Ce que voit un client (page « Espace membre »)
| Rubrique | Contenu |
|---|---|
| Tableau de bord | Résumé : robot acheté, abonnement, licence |
| Mes achats et abonnement | Achats (date, produit, montant, état) et abonnement (formule, état, échéance, résiliation prévue) |
| Ma licence | Clé de licence (bouton « Copier »), validité, numéro de compte cTrader |
| Téléchargements | Le robot, uniquement pour les clients ayant acheté |
| Mes informations | Adresse email, changement de mot de passe, export et effacement des données |

Les données apparaissent au fur et à mesure : achats et abonnements (étape C, Stripe), licences (étape D). Parrainage (étape E) et factures (portail Stripe, étape C) s'ajouteront.

## Comment c'est construit
- **Rôle « Client Arvator »** : un seul droit (`read`). Aucun accès au tableau de bord WordPress (redirection vers l'espace membre), pas de barre d'administration, pas de mots de passe d'application.
- **Données** : tables dédiées `arvator_orders`, `arvator_subscriptions`, `arvator_licenses`, `arvator_events` (préfixe WordPress). Toute lecture passe par `Arvator_Repo`, qui exige l'identifiant d'UN utilisateur, pris dans la session (jamais dans un formulaire ni une adresse). Requêtes préparées.
- **Actions** (connexion, mot de passe, demandes RGPD, téléchargement, contact) : clé de sécurité (nonce) propre à chaque action, aucune adresse de redirection fournie par le visiteur, messages limités à une liste prédéfinie.
- **Connexion** : limitation à 5 échecs par identifiant et 20 par adresse IP (blocage 15 minutes, même avec le bon mot de passe) ; message d'erreur unique ; page jamais mise en cache.
- **Téléchargement du robot** : fichier stocké sous un nom aléatoire, de préférence AU-DESSUS du dossier public (sinon dossier verrouillé) ; servi par le site après contrôle de la session, de la clé de sécurité et du droit (achat payé ou abonnement actif) ; téléchargement journalisé.
- **Mot de passe** : changement avec le mot de passe actuel, 12 caractères minimum, 5 essais maximum par 15 minutes.
- **RGPD** : export et effacement via les outils de WordPress (Outils > Exporter / Effacer des données personnelles) ; les pièces comptables sont conservées 10 ans mais détachées de la personne.
- **Idempotence** : un même événement (par exemple un webhook Stripe) n'est enregistré qu'une fois (identifiant externe unique).

## Tests automatiques
`ARV_WP=/chemin/vers/un/wordpress-de-test ./tests/run.sh` (voir `09-developpement-local.md`). 122 contrôles au 21/10/2026 : pages, contact (robots, injection d'en-têtes, limites), isolation entre trois clients, accès refusés (visiteur, autre client, tableau de bord), téléchargement (clé de sécurité, droit retiré, noms de fichiers piégés), mot de passe, RGPD, limite des connexions, XML-RPC, administration.

## Limites connues
- Le dossier privé est protégé par un emplacement hors site public ou par `.htaccess` (non testable avec le serveur de test) : à vérifier sur l'hébergement (tenter d'ouvrir l'adresse d'un fichier du dossier privé doit donner une erreur).
- Le changement d'adresse email passe par le formulaire de contact (vérification manuelle) : c'est volontaire, pour éviter la prise de contrôle d'un compte.
- Un audit de sécurité complet est prévu à l'étape G, avant l'ouverture au public.

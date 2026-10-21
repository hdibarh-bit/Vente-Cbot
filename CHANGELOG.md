# Journal des versions

Chaque nouvelle version se réinstalle par-dessus l'ancienne (voir `docs/10-installation-wordpress.md`) : le logo, l'icône, les menus et les textes que vous avez réglés sont conservés.

## Thème Arvator

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

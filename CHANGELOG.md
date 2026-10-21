# Journal des versions

Chaque nouvelle version se réinstalle par-dessus l'ancienne (voir `docs/10-installation-wordpress.md`) : le logo, l'icône, les menus et les textes que vous avez réglés sont conservés.

## Thème Arvator

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

### 0.1.0 — 21/10/2026
- Sécurité dès l'activation : éditeur de fichiers désactivé, XML-RPC bloqué, comptes non listés, connexions limitées (5 échecs → blocage de 15 minutes), message d'erreur de connexion unique, en-têtes de sécurité.
- Aucune fonction métier pour l'instant (licences, espace client, parrainage et administration viendront ensuite).

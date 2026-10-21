# Développement et tests en local

Ce dépôt contient le **code** du site, pas WordPress lui-même.

| Dossier | Contenu |
|---|---|
| `wp-content/themes/arvator/` | Thème doré (affichage uniquement) |
| `wp-content/plugins/arvator-core/` | Plugin maison : sécurité (v0.1.0) ; licences, parrainage, espace client, admin, webhooks : à venir |
| `releases/` | Fichiers zip prêts à installer (régénérés par `bin/build-zip.sh`) |
| `CHANGELOG.md` | Journal des versions |
| `tests/` | Tests d'intégration (`tests/run.sh`) : à lancer avant chaque livraison, sur un WordPress jetable |
| `config/` | Source unique des chiffres et réglages |
| `bin/sync-resultats.sh` | Régénère `data/resultats.php` et `data/tarifs.php` du thème depuis `config/*.json` (seuls les champs publics sont copiés : aucune note interne, aucun réglage de licence ou de parrainage) |
| `bin/build-zip.sh` | Fabrique `releases/arvator-theme.zip` et `releases/arvator-core.zip` (à installer dans WordPress, voir `10-installation-wordpress.md`) |
| `docs/maquettes/` | Captures d'écran des maquettes (ordinateur, mobile) |

## Règles de construction (rappel)
- Aucune ressource externe (polices hébergées dans le thème, pas de Google Fonts : respect du RGPD), pas de jQuery, un seul CSS et un seul JS.
- Tout texte affiché est échappé (`esc_html`, `esc_url`, `esc_attr`).
- Aucune description de la stratégie sur le site (voir `06-regles-legales-communication.md`).
- Les données du thème sont des fichiers PHP protégés (`ABSPATH`), jamais des JSON : un JSON dans le dossier du thème serait lisible par n'importe qui.
- La logique métier va dans le plugin, jamais dans le thème : changer de thème ne doit jamais casser les licences ni les commissions.

## Tester le thème sur un WordPress jetable (hors dépôt)
1. Cloner WordPress et l'extension « SQLite Database Integration » (base SQLite, aucun MySQL nécessaire).
2. Relier le thème : `ln -s <dépôt>/wp-content/themes/arvator <wordpress>/wp-content/themes/arvator`.
3. Lancer `php -S 127.0.0.1:8088 -t <wordpress>`, installer WordPress, activer le thème « Arvator ».
4. Contrôles faits le 21/10/2026 : syntaxe PHP (`php -l`), page d'accueil sans erreur ni avertissement, aucune requête externe, aucun débordement horizontal à 1366 px et 390 px, onglets de risque et menu mobile fonctionnels.

## Site réglé en français
Le site réel doit avoir la langue « Français » (Réglages > Général) pour que la page porte `lang="fr-FR"`.

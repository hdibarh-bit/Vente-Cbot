# Version anglaise du site : ce qui est prêt, ce qui reste

**Décision de Hamid (23/10/2026)** : *préparer seulement*. Le site reste **en français**. La version anglaise sera décidée plus tard, après les premières ventes en français.

## Ce qui est prêt (thème 0.7.0, plugin 0.8.0)
- Tous les textes écrits par le thème et par le plugin et vus par les visiteurs et les clients sont **traduisibles** : accueil, en-tête, pied de page, bandeau cookies, bandeau de recommandation, résultats, tarifs, page d'achat, essai gratuit, contact, parrainage, espace membre (toutes les rubriques), messages d'erreur et de confirmation, emails envoyés aux clients et aux parrains, export des données personnelles.
- Le français reste le texte d'origine : **rien ne change à l'écran** (vérifié page par page, et 415 tests automatiques réussis).
- Fichiers modèles de traduction (liste de tous les textes à traduire) :
  - thème : `wp-content/themes/arvator/languages/arvator.pot`
  - plugin : `wp-content/plugins/arvator-core/languages/arvator-core.pot`
- La page de paiement Stripe et le portail client Stripe s'affichent en anglais si la page du site est en anglais (sinon en français).
- La langue de la page d'achat est mémorisée : l'email de confirmation de commande (envoyé après la confirmation de Stripe) et l'email de virement aux parrains partent dans la langue du client.

## Ce qui reste en français, volontairement
- L'administration (écrans Arvator du tableau de bord) et les emails d'alerte envoyés à Hamid : Hamid est le seul à les voir.
- Le **contenu des pages** saisi dans WordPress (FAQ, À propos, CGV, Mentions légales, Confidentialité, Cookies, Risques, Conditions du parrainage…) : il se traduit page par page (voir plus bas), pas dans les fichiers de traduction.
- Les textes de l'accueil modifiés par Hamid dans Apparence > Personnaliser : un texte modifié n'est pas traduit automatiquement. Un texte laissé tel qu'à l'origine est traduit.

## Pour ouvrir la version anglaise plus tard (dans l'ordre)
1. **Décision de Hamid**, en connaissant les risques : règles de publicité et de vente de logiciels de trading propres à chaque pays visé (Royaume-Uni, États-Unis, etc.), à **vérifier avec l'avocat avant de viser ces pays**. Coût : traduction et validation des textes légaux, support client en anglais.
2. **Traduire les deux fichiers `.pot`** (Claude peut le faire), en respectant en anglais les mêmes règles qu'en français (`06-regles-legales-communication.md` : jamais de promesse de gain, toujours « simulated performance », pire baisse à côté de chaque gain, « referral reward », jamais « income » ni « opportunity »).
   Noms des fichiers produits :
   - thème : `wp-content/themes/arvator/languages/en_US.po` et `en_US.mo` (pour un thème, le nom est **seulement** la langue) ;
   - plugin : `wp-content/plugins/arvator-core/languages/arvator-core-en_US.po` et `arvator-core-en_US.mo`.
3. **Installer Polylang** (version gratuite, pas de coût fixe) : langues français (par défaut) et anglais, puis traduire chaque page (même adresse courte avec le préfixe `/en/`, à vérifier : le thème trouve les pages par leur adresse courte, par exemple `acheter`, `essai-gratuit`).
4. **Faire valider les textes légaux anglais par l'avocat**, comme les textes français.
5. **Montants** : aujourd'hui toujours écrits à la française (« 1 500 € », « 39,90 € »). En anglais on écrit « €1,500 » : à adapter au moment de la traduction.
6. Côté n8n et licence : rien à changer (aucun texte affiché au client par le site).

## Règle pour tout nouveau texte (Claude Code)
Tout texte affiché aux visiteurs ou aux clients s'écrit avec les fonctions de traduction de WordPress : `__( '…', 'arvator' )` dans le thème, `__( '…', 'arvator-core' )` dans le plugin (ou `esc_html_e`, `esc_html__`…). Après une livraison qui ajoute ou change des textes, régénérer les fichiers `.pot` (voir `09-developpement-local.md`).

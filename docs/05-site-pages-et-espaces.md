# Site : pages, espaces, base de données, tunnel

Site en **français**, thème doré (voir `07-theme-dore.md`). Nom du produit : **Arvator** (décidé le 21/10/2026). Domaine : **bot.cosmartis.com** (confirmé le 21/10/2026).

## Pages publiques
> **Règle du 21/10/2026 (Hamid)** : aucune page ni aucun texte ne décrit la stratégie ni ses détails techniques (pas d'ICT/SMC, pas d'unités de temps, pas de noms d'indices ou d'actifs, pas d'objectifs ni de garde-fous). Le public visé n'est pas fait de traders : il veut comprendre ce que fait le robot et voir ses performances simulées en euros et en pourcentage (montants ajoutés le 22/10/2026 à la demande de Hamid). Page « La stratégie » supprimée.
| Page | Contenu |
|---|---|
| Accueil | « Un robot de trading automatique, transparent, testé sur 2024, 2025 et 2026 » (backtest, performances simulées), 3 étapes, résultats simulés (backtest 2024-2026) au risque livré de 1 % + solde le plus bas, **section Tarifs** (achat 1 500 € + abonnement mensuel 39,90 € ou annuel 29,90 €/mois, premier paiement et coût de la première année, ce qui est inclus, abonnement obligatoire, capital à part), appel à l'essai gratuit et au rapport gratuit |
| Résultats | Backtest 2024-2026 par montant de capital (de 500 € à environ 10 000 €) avec le solde le plus bas, d'après le PDF ; explication du capital conseillé de 5 000 € ; méthode, limites ; PDF en téléchargement |
| Tarifs | Achat + mensuel ou annuel (annuel mis en avant), inclus, capital conseillé 5 000 € (avec l'explication : au-dessous, beaucoup de trades refusés) |
| Essai gratuit 14 j | Email + n° de compte **DÉMO** cTrader → licence d'essai par email |
| Rapport gratuit | PDF de backtest contre email (case RGPD) → événement n8n |
| Parrainage | Fonctionnement, montants, inscription gratuite |
| FAQ | Installation, cloud cTrader, licence, brokers compatibles, résiliation, risques |
| Risques | Avertissement complet, lié depuis chaque page |
| Légal | Mentions légales, CGV, confidentialité, cookies, conditions du programme de parrainage (provisoires, « à faire valider par un avocat ») |

Pied de page de chaque page : avertissement risques (texte dans `06-regles-legales-communication.md`).

## Espace client
- Téléchargement du cBot (fichier fourni plus tard ; emplacement privé hors accès public sur le serveur WordPress, téléchargement protégé).
- Clé de licence + date de fin + bouton copier.
- N° de compte cTrader : saisie/modification (2 changements/an max, sinon demande admin), régénère la licence.
- Abonnement : changer mensuel/annuel, résilier, factures (portail Stripe).
- Guide d'installation pas à pas (provisoire).
- Onglet Parrainage : lien, filleuls, commissions par statut, IBAN + statut.

## Administration (Hamid seul)
Tableau de bord (ventes, abonnements actifs par formule, impayés, revenus du mois, commissions à payer) · clients · licences (régénérer, révoquer) · parrains · commissions (valider, marquer payées, export CSV) · réglages (taux, montants, textes de résultats).

## Paiement et tunnel
- 3 cases obligatoires avant paiement : CGV ; lecture de l'avertissement risques ; **renonciation expresse au droit de rétractation** (logiciel fourni immédiatement).
- Webhooks Stripe **idempotents**. Échec d'abonnement : pas de nouvelle licence, événement à n8n.
- Factures conformes : auto-entrepreneur au départ, sans mention TTC/HT ; passage en société plus tard (prix en TTC).

**Entonnoir** : contenus utiles (pas de pub Forex/CFD) → rapport gratuit contre email → séquence email 5-7 mails sur 2 semaines (le robot et ses performances simulées avec leurs baisses, risques, installation, FAQ, invitation à l'essai ; sans détail de stratégie) → essai démo 14 j → offre principale → après achat : guide d'installation, email de bienvenue, rappel 5 j avant fin de licence, invitation au parrainage après 30 j.
Options plus tard : aide à l'installation en visio (payante) ; proposer le passage mensuel → annuel au 3e mois.

## Base de données (tables dédiées du plugin maison, base MySQL de WordPress)
`clients`, `commandes`, `abonnements`, `licences`, `parrains`, `parrainages` (filleul → parrain), `commissions`, `reglages`, `evenements` (journal). Chaque client ne voit que ses données (contrôle d'accès strict côté plugin, pas de RLS sous WordPress). Comptes clients : utilisateurs WordPress avec rôle dédié sans accès à l'admin.

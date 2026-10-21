# Site : pages, espaces, base de données, tunnel

Site en **français**, thème doré (voir `07-theme-dore.md`). Nom provisoire : « Aurum ICT » (à confirmer). Domaine : **bot.cosmartis.com** (confirmé le 21/10/2026).

## Pages publiques
| Page | Contenu |
|---|---|
| Accueil | « Un robot ICT/SMC transparent, testé sur 7 ans de données », 3 étapes, résultats simulés à 1 % + pire baisse, appel à l'essai gratuit et au rapport gratuit |
| La stratégie | Tendance H1, setups ICT A+, garde-fous, actifs, horaires |
| Résultats | Tableau des 3 niveaux de risque, courbe solde/baisses, méthode, limites |
| Tarifs | Achat + mensuel ou annuel (annuel mis en avant), inclus, capital conseillé 5 000 € |
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

**Entonnoir** : contenus utiles (pas de pub Forex/CFD) → rapport gratuit contre email → séquence email 5-7 mails sur 2 semaines (stratégie, résultats avec baisses, risques, installation, FAQ, invitation à l'essai) → essai démo 14 j → offre principale → après achat : guide d'installation, email de bienvenue, rappel 5 j avant fin de licence, invitation au parrainage après 30 j.
Options plus tard : aide à l'installation en visio (payante) ; proposer le passage mensuel → annuel au 3e mois.

## Base de données (tables dédiées du plugin maison, base MySQL de WordPress)
`clients`, `commandes`, `abonnements`, `licences`, `parrains`, `parrainages` (filleul → parrain), `commissions`, `reglages`, `evenements` (journal). Chaque client ne voit que ses données (contrôle d'accès strict côté plugin, pas de RLS sous WordPress). Comptes clients : utilisateurs WordPress avec rôle dédié sans accès à l'admin.

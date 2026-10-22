> **⚠ RÈGLE DU 21/10/2026 (Hamid) : le site ne parle PAS de la stratégie ni de détails techniques (ni ICT/SMC, ni unités de temps, ni noms d'indices ou d'actifs, ni objectifs, ni garde-fous). Seulement : le robot et ses performances en pourcentage, toujours en simulation (backtest 2024-2026). Les réels viendront plus tard.**
>
> **⚠ Brief d'origine, partiellement dépassé (21/10/2026).** La stack est désormais **WordPress sur Hostinger + Stripe seul + thème et plugin maison, 0 € fixe** ; la licence est gérée par **n8n** ; risque par défaut **1 %** ; **auto-entrepreneur** (pas de TTC/HT) ; **parrain non client payé** ; **grâce de 3 jours**. Voir `CLAUDE.md`, `docs/` et `SYNC-BOT-ICT.md` (Drive). Les sections « Stack recommandée », « Base de données » et « Licences » ci-dessous sont à lire avec ces changements.

# Mission
Construis le site de vente complet d'un robot de trading vendu sous forme de cBot cTrader. Le site est en français, avec un thème doré haut de gamme. Il gère la vente, les abonnements, les licences, un espace client, un programme de parrainage à un seul niveau et une administration. La propriétaire, Hamid, n'est pas développeuse : explique-lui chaque étape en français simple et demande-lui les comptes et clés nécessaires au bon moment.

Nom du produit : Arvator
Domaine : arvator.com (décision de Hamid du 22/10/2026 ; remplaçait bot.cosmartis.com)
Hébergement : Hostinger, offre « Hébergement Premium » (mutualisé)

# Le produit
- Un cBot cTrader (robot de trading) que le client installe dans le cloud cTrader, sur son propre compte broker. Il tourne même ordinateur éteint.
- [INTERNE — NE JAMAIS PUBLIER SUR LE SITE, voir règle du 21/10/2026] Stratégie : suivi de tendance H1 (or, BTC, Nasdaq, DAX, ETH) avec stop suiveur de 3 ATR, et setups ICT A+ de prise de liquidité en 15 minutes (Nasdaq, EURUSD, US 500, ETH). Objectifs fixes 5R et 7R. Garde-fous : 1 trade par actif, 3 positions max, 2 pertes max par jour. Entrées du dimanche 23h59 au vendredi 17h (Paris).
- Capital conseillé : 5 000 € minimum. Risque conseillé : 1 % par trade. Le client peut changer lui-même son risque dans les réglages du cBot, au choix 1 %, 2 % ou 3 % (liste fixe codée dans le cBot, rien au-delà de 3 %) (à expliquer dans la FAQ et le guide d'installation).
- Hamid vend un logiciel : elle ne gère jamais l'argent ni le compte du client.

# Prix
- Achat du cBot : 1 500 € (paiement unique).
- Abonnement obligatoire pour garder la licence active : 39,90 €/mois, ou 358,80 €/an (soit 29,90 €/mois, 120 € d'économie par an).
- Au paiement : achat + premier abonnement dans le même passage en caisse.

# Stack recommandée (adapte si tu as une bonne raison, et explique-la)
- Next.js (App Router) + TypeScript + Tailwind CSS.
- Supabase : base PostgreSQL + authentification par email (lien magique).
- Stripe : Checkout, Billing (abonnements), portail client, webhooks.
- Resend pour les emails transactionnels.
- n8n (instance existante de Hamid : n8n.cosmartis.com) : le site lui envoie des événements par webhook signé (vente, paiement d'abonnement, échec de paiement, nouvelle commission) pour les relances, la séquence email et les alertes Telegram.
- Déploiement sur Vercel. Toutes les clés secrètes en variables d'environnement, jamais dans le code.

# Thème doré (important)
- Fond noir profond (#0B0B0C), surfaces #141416, or principal #C9A227, or clair #E6C766, texte blanc cassé #F5F1E6, texte secondaire #A8A29A.
- Dégradé or discret sur les boutons principaux et les titres clés, filets dorés fins, beaucoup d'espace, aucune surcharge.
- Titres : une serif élégante (Playfair Display ou Cormorant Garamond). Texte : Inter.
- Graphiques en or et gris, lisibles. Contrastes accessibles (WCAG AA). Parfait sur mobile.
- Style : banque privée, pas casino. Aucun visuel de billets, de voitures de luxe ou de gains.

# Pages publiques
1. Accueil : promesse sobre (« Un robot de trading automatique, transparent, testé sur 2024, 2025 et 2026 » (backtest, performances simulées ; ne jamais écrire ICT, SMC, unités de temps ni noms d'indices)), fonctionnement en 3 étapes, résultats simulés (backtest 2024-2026) avec le solde le plus bas, appel à l'essai gratuit, appel au rapport gratuit.
2. (Page « La stratégie » SUPPRIMÉE le 21/10/2026 : le site ne décrit pas la stratégie. Il présente le robot et ses performances simulées en pourcentage.)
3. Résultats : tableau ci-dessous, graphique, méthode, limites (texte fourni plus bas).
4. Tarifs : achat + mensuel ou annuel (annuel mis en avant), ce qui est inclus, capital conseillé.
5. Essai gratuit 14 jours : formulaire email + numéro de compte DÉMO cTrader → licence d'essai envoyée par email.
6. Rapport gratuit : téléchargement du PDF de backtest contre une adresse email (case de consentement RGPD), envoi de l'événement à n8n pour la séquence email.
7. Parrainage : fonctionnement, montants, inscription gratuite.
8. FAQ, page Risques complète, Mentions légales, CGV, Confidentialité, Cookies, Conditions du programme de parrainage (textes provisoires clairement marqués « à faire valider par un avocat »).

# Résultats à afficher (décision de Hamid, 21/10/2026 — remplace le tableau d'origine à 100 000 €)
Source : le PDF « Simulation bots cTrader 2024-2026 » (`docs/sources/ARCHIVE-NE-PAS-DIFFUSER-Simulation-bots-cTrader-2024-2026.pdf`) : simulations par montant de capital, de 500 € à environ 10 000 €, sur 2024, 2025 et 2026, avec le solde le plus bas pour chaque montant. À présenter comme un backtest (performances simulées) sur ces trois années, avec le solde le plus bas à côté de chaque gain. L'ancien tableau à 100 000 € est abandonné (simulation sur une prop firm, sans rapport avec le bot vendu).
Expliquer le capital conseillé de 5 000 € : ce n'est pas une obligation ; en dessous, le manque de capital fait refuser beaucoup de trades (lot minimum du broker) ; à partir de 5 000 €, beaucoup moins de refus.
Les chiffres vont dans `config/resultats-backtest.json` pour que Hamid puisse les mettre à jour. N'affiche jamais de cumul spectaculaire en titre.

# Parrainage et affiliation (UN SEUL niveau, ne jamais payer les niveaux supérieurs)
- Inscription gratuite comme parrain, sans obligation d'acheter.
- Lien personnel ?ref=CODE, mémorisé 90 jours (cookie + champ dans Stripe Checkout). Parrain fixé au premier achat.
- Parrainage : 225 € par achat du cBot, une fois, à payer 30 jours après le paiement du client (au-delà du délai de rétractation).
- Affiliation : 10 % de chaque paiement d'abonnement du filleul : 3,99 € par mois (mensuel à 39,90 €) ou 35,88 € par an (annuel à 29,90 €/mois), tant qu'il reste abonné.
- Statuts : à payer → payée. Si remboursement ou impayé après versement, le montant est déduit des commissions suivantes du parrain (solde négatif possible). Cette règle est écrite dans une page « Conditions du programme de parrainage », que le parrain accepte à son inscription (case à cocher).
- Anti-fraude : pas d'auto-parrainage (même email, même client Stripe, même empreinte de carte) → commission bloquée + alerte admin.
- La prime de 225 € passe « à payer » 30 jours après le paiement du client, puis elle est signalée à l'admin et à n8n pour virement. Les commissions d'affiliation sont payées une fois par mois à partir de 50 € cumulés. Export CSV des virements (nom, IBAN, montant).
- Taux et montants dans une table de réglages modifiable depuis l'admin.
- Vocabulaire : « récompense de recommandation », jamais « revenu » ni « opportunité ».

# Licences
- Format : base64url(JSON) + "." + base64url(signature). JSON = { account: numéro de compte cTrader, plan: "monthly" | "annual" | "trial", exp: date de fin ISO, demoOnly: booléen, v: 1 }.
- Signature ECDSA P-256 / SHA-256 (compatible .NET 6 du cBot). Clé privée uniquement côté serveur ; fournis la clé publique et une fonction C# de vérification d'exemple dans un dossier /cbot-licence (pour intégration dans le cBot).
- Génération : à chaque paiement réussi (webhook Stripe). Mensuel : fin de période + 5 jours. Annuel : fin de période + 5 jours. Essai : 14 jours, demoOnly = true, un seul essai par email et par compte.
- Envoi par email + affichage dans l'espace client (bouton copier).
- Prévois aussi un endpoint WebSocket séparé (petit service Node, port 25345) où le cBot envoie son numéro de compte et reçoit sa licence à jour. Code-le et documente-le, mais il ne sera activé que si le test dans le cloud cTrader réussit.

# Espace client
- Téléchargement du cBot (fichier fourni plus tard par Hamid, prévois l'emplacement privé dans Supabase Storage).
- Clé de licence actuelle + date de fin + bouton copier.
- Numéro de compte cTrader : saisie et modification (2 changements par an max, sinon demande à l'admin), régénère la licence.
- Abonnement : changer mensuel/annuel, résilier, factures (portail client Stripe).
- Guide d'installation pas à pas (texte provisoire).
- Onglet Parrainage : lien, filleuls, commissions par statut, IBAN et statut (particulier/entreprise).

# Administration (Hamid seule)
- Tableau de bord : ventes, abonnements actifs par formule, impayés, revenus du mois, commissions à payer.
- Liste clients, licences (régénérer, révoquer), parrains, commissions (valider, marquer payées, export CSV), réglages (taux, montants, textes de résultats).

# Paiement et tunnel
- Avant le paiement, trois cases obligatoires : acceptation des CGV ; lecture de l'avertissement sur les risques ; renonciation expresse au droit de rétractation pour un logiciel fourni immédiatement.
- Webhooks Stripe gérés de façon idempotente (un même événement traité une seule fois).
- Échec de paiement d'abonnement : pas de nouvelle licence, événement envoyé à n8n pour les relances.

# Base de données (minimum)
clients, commandes, abonnements, licences, parrains, parrainages (filleul → parrain), commissions, reglages, evenements (journal). Active la sécurité par ligne (RLS) de Supabase : chaque client ne voit que ses données.

# Règles de communication (à respecter dans tous les textes)
- Aucune promesse de gain : pas de « revenu passif », « garanti », « x % par mois », pas de captures de gains isolées.
- Toujours écrire « performances simulées » et montrer la pire baisse à côté de chaque gain.
- Aucun lien d'affiliation vers un broker, aucune publicité pour le Forex ou les CFD.
- Avertissement sur les risques dans le pied de page de chaque page : « Le trading comporte un risque élevé de perte en capital, pouvant aller jusqu'à la totalité des sommes engagées. Les résultats présentés sont des performances simulées et ne préjugent pas des résultats futurs. Ce logiciel ne constitue pas un conseil en investissement. »
- Ton : expert, calme, transparent.

# Façon de travailler
1. Commence par me présenter un plan court (pages, base, flux de paiement), puis attends mon accord.
2. Construis par étapes : maquette du thème et page d'accueil d'abord (pour que je valide le style doré), puis pages publiques, paiement en mode test Stripe, licences, espace client, parrainage, admin.
3. Après chaque étape : montre-moi le résultat, dis ce qui reste à faire et ce dont tu as besoin de ma part (comptes, clés).
4. Tests automatiques pour : calcul des commissions, annulation après remboursement, génération et vérification des licences, webhooks Stripe.
5. Tout en mode test tant que je n'ai pas validé le passage en production.

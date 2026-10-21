# Mission
Construis le site de vente complet d'un robot de trading vendu sous forme de cBot cTrader. Le site est en français, avec un thème doré haut de gamme. Il gère la vente, les abonnements, les licences, un espace client, un programme de parrainage à un seul niveau et une administration. La propriétaire, Bouchra, n'est pas développeuse : explique-lui chaque étape en français simple et demande-lui les comptes et clés nécessaires au bon moment.

Nom du produit : [NOM DU PRODUIT] (provisoire : « Aurum ICT »)
Domaine : [DOMAINE]

# Le produit
- Un cBot cTrader (robot de trading) que le client installe dans le cloud cTrader, sur son propre compte broker. Il tourne même ordinateur éteint.
- Stratégie : suivi de tendance H1 (or, BTC, Nasdaq, DAX, ETH) avec stop suiveur de 3 ATR, et setups ICT A+ de prise de liquidité en 15 minutes (Nasdaq, EURUSD, US 500, ETH). Objectifs fixes 5R et 7R. Garde-fous : 1 trade par actif, 3 positions max, 2 pertes max par jour. Entrées du dimanche 23h59 au vendredi 17h (Paris).
- Capital conseillé : 5 000 € minimum. Risque conseillé : 1 % par trade. Le client peut changer lui-même son risque dans les réglages du cBot, au choix 1 %, 2 % ou 3 % (liste fixe codée dans le cBot, rien au-delà de 3 %) (à expliquer dans la FAQ et le guide d'installation).
- Bouchra vend un logiciel : elle ne gère jamais l'argent ni le compte du client.

# Prix
- Achat du cBot : 1 500 € (paiement unique).
- Abonnement obligatoire pour garder la licence active : 39,90 €/mois, ou 358,80 €/an (soit 29,90 €/mois, 120 € d'économie par an).
- Au paiement : achat + premier abonnement dans le même passage en caisse.

# Stack recommandée (adapte si tu as une bonne raison, et explique-la)
- Next.js (App Router) + TypeScript + Tailwind CSS.
- Supabase : base PostgreSQL + authentification par email (lien magique).
- Stripe : Checkout, Billing (abonnements), portail client, webhooks.
- Resend pour les emails transactionnels.
- n8n (instance existante de Bouchra : n8n.cosmartis.com) : le site lui envoie des événements par webhook signé (vente, paiement d'abonnement, échec de paiement, nouvelle commission) pour les relances, la séquence email et les alertes Telegram.
- Déploiement sur Vercel. Toutes les clés secrètes en variables d'environnement, jamais dans le code.

# Thème doré (important)
- Fond noir profond (#0B0B0C), surfaces #141416, or principal #C9A227, or clair #E6C766, texte blanc cassé #F5F1E6, texte secondaire #A8A29A.
- Dégradé or discret sur les boutons principaux et les titres clés, filets dorés fins, beaucoup d'espace, aucune surcharge.
- Titres : une serif élégante (Playfair Display ou Cormorant Garamond). Texte : Inter.
- Graphiques en or et gris, lisibles. Contrastes accessibles (WCAG AA). Parfait sur mobile.
- Style : banque privée, pas casino. Aucun visuel de billets, de voitures de luxe ou de gains.

# Pages publiques
1. Accueil : promesse sobre (« Un robot ICT/SMC transparent, testé sur 7 ans de données »), fonctionnement en 3 étapes, résultats simulés à 1 % avec la pire baisse, appel à l'essai gratuit, appel au rapport gratuit.
2. La stratégie : explication claire, actifs, horaires, garde-fous.
3. Résultats : tableau ci-dessous, graphique, méthode, limites (texte fourni plus bas).
4. Tarifs : achat + mensuel ou annuel (annuel mis en avant), ce qui est inclus, capital conseillé.
5. Essai gratuit 14 jours : formulaire email + numéro de compte DÉMO cTrader → licence d'essai envoyée par email.
6. Rapport gratuit : téléchargement du PDF de backtest contre une adresse email (case de consentement RGPD), envoi de l'événement à n8n pour la séquence email.
7. Parrainage : fonctionnement, montants, inscription gratuite.
8. FAQ, page Risques complète, Mentions légales, CGV, Confidentialité, Cookies, Conditions du programme de parrainage (textes provisoires clairement marqués « à faire valider par un avocat »).

# Résultats à afficher (performances simulées, compte de 100 000 €, janv. 2025 → 7 oct. 2026, frais inclus)
| Risque | 2025 | 2026 (au 7 oct.) | Pire mois | Baisse max entre fins de mois |
|---|---|---|---|---|
| 1 % (référence) | +81 % | +36 % | −8 % | environ −9 % |
| 2 % | +195 % | +76 % | −17 % | environ −18 % |
| 3 % | +332 % | +106 % | −26 % | environ −27 % |

Texte obligatoire sous le tableau : données 15 minutes 2019–2026 sur 14 actifs ; en cours de mois les baisses ont pu être plus fortes (jusqu'à −35/−40 % à 3 %) ; 2025 meilleure année, 2026 la plus difficile ; sur 2019–2026 à risque élevé la baisse maximale a atteint 77 % ; sous 5 000 € les résultats sont très irréguliers ; les réglages ont été choisis sur le même historique, donc les résultats futurs seront probablement moins bons ; pas encore d'historique en argent réel ; l'avenir peut être très positif, nul ou négatif, avec une perte possible de tout le capital.
N'affiche jamais de cumul spectaculaire en titre. Mets ces données dans un fichier de configuration pour que Bouchra puisse les mettre à jour (voir `config/resultats-backtest.json`).

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
- Téléchargement du cBot (fichier fourni plus tard par Bouchra, prévois l'emplacement privé dans Supabase Storage).
- Clé de licence actuelle + date de fin + bouton copier.
- Numéro de compte cTrader : saisie et modification (2 changements par an max, sinon demande à l'admin), régénère la licence.
- Abonnement : changer mensuel/annuel, résilier, factures (portail client Stripe).
- Guide d'installation pas à pas (texte provisoire).
- Onglet Parrainage : lien, filleuls, commissions par statut, IBAN et statut (particulier/entreprise).

# Administration (Bouchra seule)
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

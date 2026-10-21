# Parrainage et affiliation (1 seul niveau — décidé le 21/10/2026)

Deux récompenses distinctes. **Seul le parrain direct est payé**, jamais les niveaux supérieurs. Affiliation classique, autorisée en France, acceptée par Stripe.

| Récompense | Montant | Quand |
|---|---|---|
| Prime de parrainage | 225 € par achat du cBot (15 % de 1 500 €) | Une fois, « à payer » 30 jours après le paiement du client (après le délai de rétractation) |
| Affiliation mensuel | 10 % de 39,90 € = 3,99 €/mois | À chaque mensualité payée, tant que le filleul reste abonné |
| Affiliation annuel | 10 % de 358,80 € = 35,88 €/an (≈ 2,99 €/mois) | À chaque paiement annuel |

## Règles à coder
- Inscription **gratuite** comme parrain, sans obligation d'acheter (garde le programme du bon côté de la loi).
- Lien `?ref=CODE`, mémorisé **90 jours** (cookie + champ Stripe Checkout). Parrain fixé au **premier achat**, ne change plus.
- Statuts : en attente → à payer → payée.
- **Remboursement** (rétractation, remboursement, contestation bancaire) : la récompense correspondante est retirée, déduite des commissions suivantes (solde négatif possible) ou à rembourser. Écrit dans la page « Conditions du programme de parrainage », case à cocher à l'inscription. Le cas d'un parrain qui refuse est traité à part par Hamid.
- **Anti-fraude** : pas d'auto-parrainage (même email, même client Stripe, même empreinte de carte) → commission bloquée + alerte admin.
- Paiement : prime de 225 € virée à J+30 ; affiliation versée **une fois par mois à partir de 50 € cumulés**. Le parrain fournit IBAN + statut (particulier/entreprise). Export CSV des virements (nom, IBAN, montant).
- Taux et montants dans une table de réglages modifiable depuis l'admin (`config/reglages.json` = valeurs initiales).
- Espace parrain : lien, filleuls, commissions par statut, paiements.
- Vocabulaire : « récompense de recommandation », jamais « revenu » ni « opportunité ».

## Décision du 21/10/2026
Un parrain qui n'est pas client touche ses commissions comme les autres.

## Ce qui est codé (plugin 0.5.0, 21/10/2026)
- Tables `referrers` (code, IBAN chiffré, statut, SIRET), `referrals` (filleul → parrain, fixé au 1er achat), `commissions` (montants signés, origine unique = pas de doublon).
- Lien `?ref=CODE` : cookie `arv_ref` (90 jours, HttpOnly) + champ « Code de recommandation » prérempli à l'achat, transmis à Stripe.
- Prime de 225 € et 10 % du 1er abonnement créées à l'achat ; 10 % de chaque renouvellement (`invoice.paid`). **Hypothèse retenue** : les récompenses sur abonnement attendent aussi 30 jours avant d'être « à payer » (comme la prime), puis sont versées à partir de 50 € cumulés.
- Remboursement du prix du robot : prime annulée (ou déduite si déjà versée) ; la récompense sur le 1er abonnement reste due (cet abonnement n'est pas remboursé). Contestation bancaire : prime + 1er abonnement retirés.
- Anti-fraude : même compte, même email, même client Stripe ou même carte (empreinte Stripe) → récompenses bloquées + email d'alerte ; l'administrateur débloque ou annule.
- Administration (Arvator > Parrainage) : virements (lot → CSV nom/IBAN/montant → « virements effectués » → email au parrain), récompenses, parrains (suspendre), réglages.
- Le parrain ne voit aucune donnée personnelle de ses filleuls.

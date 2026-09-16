# Décompte copropriétaires ("répartition")

Des décomptes sont réalisés de manière **périodique** au cours d'un exercice, selon la configuration de l'ACP, et présentent la manière dont  tous les mouvements comptables de la période affectent le décompte de chaque copropriétaire.

Les décomptes sont considérés comme des factures de vente (journal des ventes), et ne peuvent donc être générés que pour les périodes non-clôturées, mais peuvent être visualisés indépendamment de la clôture des périodes.

Le principe est d'établir ce que doit payer chaque propriétaire, en tenant compte des éventuels montants déjà versés sur les fonds de réserve.

* on distingue les charges communes et les frais privatifs
* les frais et charges sont ventilés par lot (on reprend les charges autant de fois qu'il y a de lots pour le propriétaire)
* on détermine les quotités sur base de la clé de répartition à utiliser et des parts représentant les lots du propriétaire    
  au prorata du nombre de jours de propriété durant la période facturée    
* si le propriétaire ne détient aucun lot, on ne renseigne pas de quotités
* quand on prélève sur un fonds de réserve, le montant à répartir est négatif (la gestion des paiements liés aux appels de fonds de réserve est faite de manière distincte)

Les écritures se font en ajoutant le montant d'une charge commune au débit (+), et le montant prélevé sur un fonds de réserver au crédit (-), le tout au pro-rata correspondant aux propriétés du propriétaire. 

Note: Si des frais privatifs ont déjà été refacturés à un propriétaires, les écritures correspondantes sont balancées.

**Fréquence:**

* trimestriel
* quadrimestriel
* semestriel
* annuel 

La **numérotation des factures d'achat** peut prendre en compte la période (avec la numérotation qui recommence à chaque période).

Notes: 

* Une ACP peut être assujettie à la TVA (souvent pas)
* Si le propriétaire est assujetti à la TVA, la ventilation TVA est reprise sur le "décompte propriétaire" (considérer le "décompte propriétaire" est considéré comme **facture de vente** de la copro, et renseigné avec un numéro de "facture")
* Présentation de la répartition PROP / LOC (pour demande/justificatif de frais du propriétaire au locataire)
* Il faut empêcher la comptabilisation si une période précédente n’est pas clôturée.
* Déduction automatique des provisions pour charges.

Pour la présentation des décomptes, il y a plusieurs cas de figure:

* un propriétaire peut souhaiter avoir son décompte pour des lots regroupés (appart, garage, cave)
* un propriétaire peut souhaiter un décompte pour un lot seul (par exemple pour pouvoir présenter un décompte individuel à son locataire)

La piste retenue est de toujours présenter un décompte global, puis un décompte détaillé en annexe (avec regroupement ou non des lots, selon la configuration).

Il est possible de déduire comment les informations doivent être présentées sur base de la configuration pour l'ACP et de l'organisation des lots (lot principal et lots secondaires).

Pour générer le décompte propriétaire, on consulte les écritures comptables de la période concernée.

Pour chaque période, le décompte propriétaire se base sur **deux couches de répartition** distinctes mais complémentaires :

#### 1. Répartition des charges

Chaque charge imputée (par exemple une facture d’entretien) est associée à une clé de répartition (et donc à une quotité par lot).

Le montant à charge de chaque lot est calculé en appliquant la clé de répartition qui lui correspond sur chaque compte de charge (`61xx`) imputé  durant la période.

#### 2. Prise en compte des financements par fonds de réserve

Chaque utilisation de fonds de réserve est associée à un montant imputé à une charge spécifique et à une clé de répartition (toujours identique à celle utilisée lors de l'appel du fonds).

Le montant couvert par le fonds est déduit des charges selon cette clé de répartition.

⚠️ La clé pour l'utilisation du fonds peut être différente de celle utilisée pour répartir la charge elle-même.
 Cela signifie que certains copropriétaires peuvent financer une charge sans en bénéficier directement (si les clés sont différentes).

Le décompte des propriétaires a lieu à chaque clôture de période. On fait une seule écriture d'imputation aux copropriétaires.

Dans le workflow actuel, le décompte de charges (`ExpenseStatement`) est aussi le déclencheur technique de la clôture : lorsqu'il est posté, son action `close_fiscal_period` clôture la `FiscalPeriod` liée et, s'il s'agit de la dernière période de l'exercice, clôture également le `FiscalYear`.

```
Copropriétaire : Mme Dupont
└── Lot : 001A
    ├── Charges communes
    │   ├── 614 - Assurance
    │   │   ├── Prime Q1 (2.000 € * 3,5 %) → 70,00 €
    │   │   └── Prime Q2 (2.000 € * 3,5 %) → 70,00 €
    │   └── 610 - Entretien
    │       └── Nettoyage janvier (1.000 € * 2,0 %) → 20,00 €
    ├── Déductions fonds de réserve
    │   └── Travaux toiture financés (3.000 € * 4,0 %) → –120,00 €
    ├── Frais privatifs
    │   └── Remplacement parlophone → 180,00 €
    └── **Solde pour le lot :** 222,50 €
```

À la fin d’une période comptable, le système permet de générer un **état de répartition des charges** pour chaque propriétaire de la copropriété. Cette répartition prend en compte :

- Les dépenses communes (charges réparties selon des clés votées)
- Les frais privatifs (imputés à un ou plusieurs copropriétaires désignés)
- L’utilisation des fonds de réserve
- La durée de possession dans la période (prorata temporis)

## Chaîne technique de génération

La génération d'un `ExpenseStatement` proforma suit cette séquence :

1. sélectionner les lignes comptables à décompter ;
2. reconstituer l'historique des `Ownership` et des lots pendant la période ;
3. classifier les lignes en provisions, charges privatives, utilisations de fonds et charges communes ;
4. répartir les montants par `Ownership` et par lot ;
5. créer les `ExpenseStatementOwner` et leurs `ExpenseStatementOwnerLine` ;
6. vérifier l'équilibre de la répartition ;
7. générer l'écriture comptable du décompte ;
8. valider cette écriture et apurer les lignes sources ;
9. créer et, si possible, compenser un `Funding` par `Ownership` ;
10. clôturer la période et générer les correspondances individualisées.

### 1. Périmètre comptable sélectionné

Le calcul part des `AccountingEntryLine`, et non directement des factures ou des appels de fonds. Une ligne source doit :

- appartenir à la `FiscalPeriod` choisie ;
- avoir le statut `validated` ;
- appartenir à une `AccountingEntry` elle-même validée ;
- utiliser un compte de classe 6 ou 7 ;
- ne pas être un report à nouveau (`is_carry_forward = false`) ;
- ne pas avoir déjà été apurée par un autre décompte (`is_cleared = false`) ;
- appartenir à une écriture dont la date est comprise dans la période ;
- ne pas provenir d'un autre `ExpenseStatement`.

Lorsqu'une ligne est retenue par un décompte proforma, `clearing_expense_statement_id` établit temporairement le lien avec celui-ci. Au posting, `is_cleared` passe à `true`, ce qui empêche toute reprise dans un décompte ultérieur.

Les charges reportées sur un compte 490 ne sont donc pas sélectionnées directement. Lorsqu'elles sont réaffectées au débit d'un compte de charges de classe 6 au début de la période suivante, cette nouvelle ligne peut entrer dans le périmètre de cette période.

### 2. Historique des propriétaires et des lots

Le système calcule l'intersection de chaque `Ownership` avec la période comptable :

```text
jours applicables = nombre de jours inclus entre
    max(début de période, début de possession)
    et
    min(fin de période, fin de possession)
```

Ce calcul est ensuite effectué séparément pour chaque couple `Ownership × PropertyLot`. Pour un lot donné, le contrat implicite est que l'historique des propriétaires couvre la période sans trou ni chevauchement.

### 3. Classification et répartition des lignes

| Type | Source du montant | Règle de répartition |
| --- | --- | --- |
| Provisions | Exécutions réelles des appels de fonds | Attribution directe à l'`Ownership` et au lot de l'exécution |
| Charge privative | Ligne de facture, ligne bancaire ou opération diverse | Attribution directe à l'`Ownership` et au lot renseignés |
| Utilisation d'un fonds | Ligne comptable et configuration du `CondoFund` | Clé de répartition et prorata temporel, 100 % propriétaire |
| Charge commune | Ligne comptable et clé de la ligne source | Clé de répartition, prorata temporel et partage propriétaire/locataire |

#### Provisions

Pour une écriture liée à un `FundRequestExecution`, le montant théorique de la `FundRequestLine` n'est pas utilisé. Le calcul descend jusqu'à la ventilation effectivement exécutée :

```text
FundRequestExecution
└── FundRequestExecutionLine
    └── FundRequestExecutionLineEntry
        ├── Ownership
        ├── PropertyLot
        └── called_amount
```

Le montant intégré au décompte est :

```text
provision du décompte = -called_amount
```

Le signe négatif matérialise une provision déjà appelée, qui diminue le solde final du copropriétaire.

#### Charges privatives

La charge est déjà liée à un `Ownership` et à un lot ; aucune clé de répartition n'est appliquée. Si `owner_share` est exprimé en pourcentage :

```text
montant propriétaire = round(montant × owner_share / 100, 2)
montant locataire     = montant - montant propriétaire
```

Le reliquat d'arrondi éventuel est attribué au propriétaire afin de garantir que les deux parts recomposent exactement le montant de la ligne.

#### Utilisations de fonds

Les utilisations du fonds de réserve, du fonds spécial ou du fonds de roulement sont présentées comme des charges communes, mais sont intégralement supportées par le propriétaire :

```text
montant brut =
    montant comptable
    × quotes-parts du lot / total de la clé
    × jours de possession / jours de la période

propriétaire = round(montant brut, 2)
locataire    = 0
delta        = montant brut - propriétaire
```

La clé provient de la configuration du `CondoFund`. Elle peut être différente de la clé qui répartit la charge financée.

#### Charges communes

La formule générale est :

```text
montant brut du lot =
    montant comptable
    × quotes-parts du lot / total de la clé
    × jours de possession / jours de la période

montant arrondi du lot = round(montant brut du lot, 2)

propriétaire = round(montant arrondi du lot × owner_share / 100, 2)
locataire    = montant arrondi du lot - propriétaire

delta = montant brut du lot - propriétaire - locataire
```

Le delta cumule la différence entre les montants comptables bruts et les montants effectivement attribués au centime.

### 4. Matérialisation du décompte

Après le calcul :

- un `ExpenseStatementOwner` est créé pour chaque `Ownership` concerné ;
- ses détails deviennent des `ExpenseStatementOwnerLine` ;
- tous les montants persistés sont normalisés à deux décimales ;
- le prix de chaque ligne respecte `price = owner_amount + tenant_amount`.

Les lignes sont proches d'`InvoiceLine`, mais conservent les informations spécifiques nécessaires à la traçabilité : lot, compte, clé de répartition, type de charge, période de détention, TVA, parts propriétaire/locataire et delta assigné.

Le résultat est utilisé pour générer l'écriture comptable et les documents. Une fois le décompte posté, les `ExpenseStatementOwner` et `ExpenseStatementOwnerLine` persistés deviennent la source stable des réimpressions, même si la période est rouverte et que les données d'origine évoluent ensuite.

Le calcul s'appuie sur les écritures comptables validées, leurs lignes métier sources, les clés de répartition et l'historique des `Ownership`. Les pièces émises et les écritures validées ne sont plus modifiables ; une clé devenue obsolète est désactivée puis remplacée ; une mutation clôture l'ancien `Ownership` et en crée un nouveau. Ces règles préservent la traçabilité des données utilisées.

Le décompte peut être produit individuellement pour chaque `Ownership` ou regroupé dans un export unique, notamment pour le commissaire aux comptes.

### 5. Invariant d'équilibre de la répartition

Le contrôle principal est :

```text
Σ montant de tous les ExpenseStatementOwner
    = common_total
    + private_total
    + provisions_total
    - assigned_delta
```

avec :

```text
montant d'un ExpenseStatementOwner
    = Σ price de ses ExpenseStatementOwnerLine
```

### 6. Écriture comptable générée

L'écriture du décompte contient trois composantes :

```text
1. Extourne des lignes comptables décomptées
   débit généré  = crédit de la ligne source
   crédit généré = débit de la ligne source

2. Imputation aux comptes des copropriétaires
   montant = somme des ExpenseStatementOwnerLine du propriétaire

3. Écart d'arrondi éventuel
   compte operation_assignment = rounding_adjustment
```

Les mouvements sont agrégés et compensés par compte avant la création des lignes finales. L'écriture doit respecter `Σ débits = Σ crédits`. `assigned_delta` fait le pont entre le total comptable brut et la somme des montants arrondis attribués aux propriétaires ; un compte avec `operation_assignment = rounding_adjustment` est donc obligatoire lorsqu'il n'est pas nul.

Après la création de l'écriture, celle-ci est validée et les lignes sources liées au décompte sont marquées comme apurées.

### 7. Génération et compensation des Funding

Un `Funding` de type `expense_statement` est créé pour chaque `Ownership` :

```text
Funding.due_amount = Σ ExpenseStatementOwnerLine.price
```

Le système recherche ensuite, pour le même compte de contrôle, les `Funding` en attente de signe opposé. Ils sont compensés automatiquement du plus ancien au plus récent par des `FundingAllocation` liées.

```text
Décompte à payer           +100,00
Crédit copropriétaire       -30,00
                           -------
Reste effectivement dû      70,00
```

### 8. Posting, clôture et correspondances

Le passage de `proforma` à `posted` régénère le détail, génère et valide l'écriture, attribue le numéro de facture, apure les lignes sources, crée les `Funding`, puis clôture la période. Si la période est la dernière de l'exercice, le `FiscalYear` est également clôturé.

Une `ExpenseStatementCorrespondence` est ensuite créée par `Ownership` et par canal de communication applicable. La génération du document correspondant est planifiée séparément.

En cas de déverrouillage, l'écriture du décompte est extournée, les lignes sources sont désapurées et les `Funding` associés sont supprimés avant le retour au statut `proforma`. Une nouvelle génération peut alors tenir compte des corrections ou nouvelles écritures.

### 9. Invariants fonctionnels

1. Une ligne comptable source ne peut être incluse que dans un seul décompte.
2. Les lignes sources doivent être validées et rattachées à la bonne période.
3. L'historique des propriétaires doit couvrir correctement chaque lot.
4. Une clé de répartition doit contenir tous les lots concernés et avoir un total de quotes-parts cohérent.
5. Une charge privative doit avoir un `Ownership` et un lot explicites.
6. Une provision repose sur les `FundRequestExecutionLineEntry`, jamais sur le montant théorique de la `FundRequestLine`.
7. La somme des entrées d'exécution doit correspondre au montant comptabilisé par l'exécution.
8. `price = owner_amount + tenant_amount`.
9. La somme des propriétaires doit respecter la formule d'équilibre incluant `assigned_delta`.
10. Le montant du `Funding` doit être identique au total du propriétaire dans le décompte.
11. L'écriture comptable finale doit être équilibrée.
12. Un compte `rounding_adjustment` doit exister lorsqu'un delta d'arrondi est présent.

### Structure intermédiaire pour ventilation

Une structure en arborescence est générée pour chaque propriétaire, sur base de ses lots et des charges comptabilisées, et des dates de la période concernée, et est utilisée pour la génération des documents de "décompte propriétaires".

**Exemple:**

```
[
    {
        "id": 5,
        "schema": {
            "date_from": 670464000,
            "date_to": 678240000,
            "nb_days": 91,
            "owners": [
                {
                    "id": 2,
                    "name": "00001 - Charles MAX",
                    "nb_days": 61,
                    "date_from": 673056000,
                    "date_to": null,
                    "has_reserve_fund": true,
                    "has_private_expense": true,
                    "has_common_expense": true,
                    "property_lots": [
                        {
                            "id": 3,
                            "name": "00003 - 1C (APPARTEMENT) - 00001 - Charles MAX",
                            "code": "00003",
                            "ref": "1C",
                            "nature": "APPARTEMENT",
                            "has_reserve_fund": true,
                            "has_private_expense": true,
                            "has_common_expense": true,
                            "expenses": [
                                {
                                    "name": "reserve_fund",
                                    "apportionments": [
                                        {
                                            "id": 9,
                                            "name": "0005 - fonds de réserve (Q. 1000)",
                                            "total_shares": 1000,
                                            "shares": 275,
                                            "accounts": [
                                                {
                                                    "id": 707,
                                                    "name": "68160011 - Prélèvement fonds de réserve",
                                                    "code": "68160011",
                                                    "total_amount": -1000,
                                                    "owner": -184.34,
                                                    "tenant": 0,
                                                    "vat": 0,
                                                    "description": null,
                                                    "date": null
                                                }
                                            ]
                                        }
                                    ]
                                },
                                {
                                    "name": "private_expense",
                                    "apportionments": [
                                        {
                                            "id": 0,
                                            "name": "private",
                                            "total_shares": null,
                                            "shares": null,
                                            "accounts": [
                                                {
                                                    "id": 689,
                                                    "name": "6430000 - Frais privatifs",
                                                    "code": "6430000",
                                                    "total_amount": 0,
                                                    "owner": 2420,
                                                    "tenant": 0,
                                                    "vat": 420,
                                                    "description": "appareils",
                                                    "date": 671760000
                                                },
                                                {
                                                    "id": 689,
                                                    "name": "6430000 - Frais privatifs",
                                                    "code": "6430000",
                                                    "total_amount": 0,
                                                    "owner": 484,
                                                    "tenant": 0,
                                                    "vat": 84,
                                                    "description": "rfais en plus",
                                                    "date": 671760000
                                                }
                                            ]
                                        }
                                    ]
                                },
                                {
                                    "name": "common_expense",
                                    "apportionments": [
                                        {
                                            "id": 2,
                                            "name": "0001 - Charges communes (Q. 1000)",
                                            "total_shares": 1000,
                                            "shares": 275,
                                            "accounts": [
                                                {
                                                    "id": 481,
                                                    "name": "6100003 - Réparation protection incendie",
                                                    "code": "6100003",
                                                    "total_amount": 1210,
                                                    "owner": 223.05,
                                                    "tenant": 0,
                                                    "vat": 38.71,
                                                    "description": null,
                                                    "date": null
                                                },
                                                {
                                                    "id": 578,
                                                    "name": "6110009 - Autres travaux",
                                                    "code": "6110009",
                                                    "total_amount": 484,
                                                    "owner": 89.22,
                                                    "tenant": 0,
                                                    "vat": 15.48,
                                                    "description": null,
                                                    "date": null
                                                }
                                            ]
                                        }
                                    ]
                                }
                            ]
                        },
                        {
                            "id": 7,
                            "name": "00004 - GREZ (GARAGE) - 00001 - Charles MAX",
                            "code": "00004",
                            "ref": "GREZ",
                            "nature": "GARAGE",
                            "has_reserve_fund": true,
                            "has_private_expense": false,
                            "has_common_expense": true,
                            "expenses": [
                                {
                                    "name": "reserve_fund",
                                    "apportionments": [
                                        {
                                            "id": 9,
                                            "name": "0005 - fonds de réserve (Q. 1000)",
                                            "total_shares": 1000,
                                            "shares": 75,
                                            "accounts": [
                                                {
                                                    "id": 707,
                                                    "name": "68160011 - Prélèvement fonds de réserve",
                                                    "code": "68160011",
                                                    "total_amount": -1000,
                                                    "owner": -50.27,
                                                    "tenant": 0,
                                                    "vat": 0,
                                                    "description": null,
                                                    "date": null
                                                }
                                            ]
                                        }
                                    ]
                                },
                                {
                                    "name": "common_expense",
                                    "apportionments": [
                                        {
                                            "id": 2,
                                            "name": "0001 - Charges communes (Q. 1000)",
                                            "total_shares": 1000,
                                            "shares": 75,
                                            "accounts": [
                                                {
                                                    "id": 481,
                                                    "name": "6100003 - Réparation protection incendie",
                                                    "code": "6100003",
                                                    "total_amount": 1210,
                                                    "owner": 60.83,
                                                    "tenant": 0,
                                                    "vat": 10.56,
                                                    "description": null,
                                                    "date": null
                                                },
                                                {
                                                    "id": 578,
                                                    "name": "6110009 - Autres travaux",
                                                    "code": "6110009",
                                                    "total_amount": 484,
                                                    "owner": 24.33,
                                                    "tenant": 0,
                                                    "vat": 4.22,
                                                    "description": null,
                                                    "date": null
                                                }
                                            ]
                                        }
                                    ]
                                }
                            ]
                        }
                    ]
                },
                {
                    "id": 3,
                    "name": "00002 - Lucienne PRÉVAUT",
                    "nb_days": 91,
                    "date_from": null,
                    "date_to": null,
                    "has_reserve_fund": true,
                    "has_private_expense": false,
                    "has_common_expense": true,
                    "property_lots": [
                        {
                            "id": 1,
                            "name": "00001 - 1A (APPARTEMENT) - 00002 - Lucienne PRÉVAUT",
                            "code": "00001",
                            "ref": "1A",
                            "nature": "APPARTEMENT",
                            "has_reserve_fund": true,
                            "has_private_expense": false,
                            "has_common_expense": true,
                            "expenses": [
                                {
                                    "name": "reserve_fund",
                                    "apportionments": [
                                        {
                                            "id": 9,
                                            "name": "0005 - fonds de réserve (Q. 1000)",
                                            "total_shares": 1000,
                                            "shares": 225,
                                            "accounts": [
                                                {
                                                    "id": 707,
                                                    "name": "68160011 - Prélèvement fonds de réserve",
                                                    "code": "68160011",
                                                    "total_amount": -1000,
                                                    "owner": -225,
                                                    "tenant": 0,
                                                    "vat": 0,
                                                    "description": null,
                                                    "date": null
                                                }
                                            ]
                                        }
                                    ]
                                },
                                {
                                    "name": "common_expense",
                                    "apportionments": [
                                        {
                                            "id": 2,
                                            "name": "0001 - Charges communes (Q. 1000)",
                                            "total_shares": 1000,
                                            "shares": 225,
                                            "accounts": [
                                                {
                                                    "id": 481,
                                                    "name": "6100003 - Réparation protection incendie",
                                                    "code": "6100003",
                                                    "total_amount": 1210,
                                                    "owner": 272.25,
                                                    "tenant": 0,
                                                    "vat": 47.25,
                                                    "description": null,
                                                    "date": null
                                                },
                                                {
                                                    "id": 578,
                                                    "name": "6110009 - Autres travaux",
                                                    "code": "6110009",
                                                    "total_amount": 484,
                                                    "owner": 108.9,
                                                    "tenant": 0,
                                                    "vat": 18.9,
                                                    "description": null,
                                                    "date": null
                                                }
                                            ]
                                        }
                                    ]
                                }
                            ]
                        }
                    ]
                },
                {
                    "id": 4,
                    "name": "00003 - Etienne DUCHEMIN, Sarah DUCHEMIN, Louis DUCHEMIN",
                    "nb_days": 91,
                    "date_from": null,
                    "date_to": null,
                    "has_reserve_fund": true,
                    "has_private_expense": false,
                    "has_common_expense": true,
                    "property_lots": [
                        {
                            "id": 2,
                            "name": "00002 - 1B (APPARTEMENT) - 00003 - Etienne DUCHEMIN, Sarah DUCHEMIN, Louis DUCHEMIN",
                            "code": "00002",
                            "ref": "1B",
                            "nature": "APPARTEMENT",
                            "has_reserve_fund": true,
                            "has_private_expense": false,
                            "has_common_expense": true,
                            "expenses": [
                                {
                                    "name": "reserve_fund",
                                    "apportionments": [
                                        {
                                            "id": 9,
                                            "name": "0005 - fonds de réserve (Q. 1000)",
                                            "total_shares": 1000,
                                            "shares": 250,
                                            "accounts": [
                                                {
                                                    "id": 707,
                                                    "name": "68160011 - Prélèvement fonds de réserve",
                                                    "code": "68160011",
                                                    "total_amount": -1000,
                                                    "owner": -250,
                                                    "tenant": 0,
                                                    "vat": 0,
                                                    "description": null,
                                                    "date": null
                                                }
                                            ]
                                        }
                                    ]
                                },
                                {
                                    "name": "common_expense",
                                    "apportionments": [
                                        {
                                            "id": 2,
                                            "name": "0001 - Charges communes (Q. 1000)",
                                            "total_shares": 1000,
                                            "shares": 250,
                                            "accounts": [
                                                {
                                                    "id": 481,
                                                    "name": "6100003 - Réparation protection incendie",
                                                    "code": "6100003",
                                                    "total_amount": 1210,
                                                    "owner": 302.5,
                                                    "tenant": 0,
                                                    "vat": 52.5,
                                                    "description": null,
                                                    "date": null
                                                },
                                                {
                                                    "id": 578,
                                                    "name": "6110009 - Autres travaux",
                                                    "code": "6110009",
                                                    "total_amount": 484,
                                                    "owner": 121,
                                                    "tenant": 0,
                                                    "vat": 21,
                                                    "description": null,
                                                    "date": null
                                                }
                                            ]
                                        }
                                    ]
                                }
                            ]
                        },
                        {
                            "id": 8,
                            "name": "00005 - 1B-C (CAVE) - 00003 - Etienne DUCHEMIN, Sarah DUCHEMIN, Louis DUCHEMIN",
                            "code": "00005",
                            "ref": "1B-C",
                            "nature": "CAVE",
                            "has_reserve_fund": true,
                            "has_private_expense": false,
                            "has_common_expense": true,
                            "expenses": [
                                {
                                    "name": "reserve_fund",
                                    "apportionments": [
                                        {
                                            "id": 9,
                                            "name": "0005 - fonds de réserve (Q. 1000)",
                                            "total_shares": 1000,
                                            "shares": 175,
                                            "accounts": [
                                                {
                                                    "id": 707,
                                                    "name": "68160011 - Prélèvement fonds de réserve",
                                                    "code": "68160011",
                                                    "total_amount": -1000,
                                                    "owner": -175,
                                                    "tenant": 0,
                                                    "vat": 0,
                                                    "description": null,
                                                    "date": null
                                                }
                                            ]
                                        }
                                    ]
                                },
                                {
                                    "name": "common_expense",
                                    "apportionments": [
                                        {
                                            "id": 2,
                                            "name": "0001 - Charges communes (Q. 1000)",
                                            "total_shares": 1000,
                                            "shares": 175,
                                            "accounts": [
                                                {
                                                    "id": 481,
                                                    "name": "6100003 - Réparation protection incendie",
                                                    "code": "6100003",
                                                    "total_amount": 1210,
                                                    "owner": 211.75,
                                                    "tenant": 0,
                                                    "vat": 36.75,
                                                    "description": null,
                                                    "date": null
                                                },
                                                {
                                                    "id": 578,
                                                    "name": "6110009 - Autres travaux",
                                                    "code": "6110009",
                                                    "total_amount": 484,
                                                    "owner": 84.7,
                                                    "tenant": 0,
                                                    "vat": 14.7,
                                                    "description": null,
                                                    "date": null
                                                }
                                            ]
                                        }
                                    ]
                                }
                            ]
                        }
                    ]
                }
            ]
        },
        "name": "[proforma] - ",
        "state": "instance",
        "modified": "2025-04-19T11:47:34+00:00"
    }
]
```


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

### Sources des données

1. **Écritures comptables validées** dans la période (factures fournisseurs, charges reportées, appels aux fonds de réserve…).
2. **Configuration des lots** et des **clés de répartition**.
3. **Historique des propriétaires** et des dates de détention.



Un mécanisme permet d'être certain de ne jamais comptabiliser des écritures deux fois:

* lorsqu'une écriture est prise en compte dans un décompte, un lien est établi (`clearing_expense_statement`) vers le décompte correspondant 

* lors que le décompte est facturé, les écritures sont marquées comme décomptée (`is_cleared`)



### Logique d'établissement du décompte

* Récupération des écritures comptables validées sur la période (factures, frais, utilisations de fonds, etc.).

* Traitement des lignes de dépenses :
  
  * Charges communes (comptes 61x) réparties selon les clés définies
  * Frais privatifs affectés à des copropriétaires spécifiques
  * Utilisations de fonds de réserve traitées (selon la clé d'appel initiale)

* Proratisation des charges selon le nombre de jours de détention des lots par chaque propriétaire.

* A la date du premier jour de la période suivante : réaffectation des charges à reporter (comptes 490) vers les comptes de charges correspondants, au débit.

On génère un tableau (JSON) à 3 niveaux, reprenant le détail du décompte de clôture théorique (au moment où il est généré).

On utilise à la fois ce JSON :

* pour déterminer les écritures à réaliser pour clôturer la période
* pour générer le document de décompte

Les infos sont déterminées sur base : 

1) des écritures comptables (qui ne peuvent plus être modifiées une fois validées)
2) des InvoiceLines (qui ne peuvent plus être modifiées une fois la facture émise)
3) des clés de répartition (note: on empêche la modification des clés de répartition; si nécessaire, il est possible de les désactiver et de créer de nouvelles clés)
4) des Ownerships (qui peuvent également être modifiés: les ownerships ne peuvent pas être modifiés ni supprimés; ils peuvent être révoqués [date de fin] en cas de transfert. En cas de modification des lots attachés, un nouveau Ownership est créé)

Il faut pouvoir générer un décompte :

* soit de manière individuelle: pour l'envoyer à chaque copropriétaire (dans le sens Ownership) 
* soit de manière groupée: pour l'exporter en une fois (un seul fichier : pour le commissaire aux comptes) - car il s'agit d'un document assimilé à une facture de vente

La génération est fixée aux informations liées à la période et il est possible de générer le JSON à l'identique à tout moment, éventuellement pour un propriétaire en particulier (indépendamment du fait que les écritures aient été passées ou non).

Un `ExpenseStatement` est l'équivalent d'une facture (fonctionnement similaire à FundRequest)

Cas  particulier : dans le cas ou un exercice est clôturé, puis réouvert, et modifié 

* on extourne le décompte
* on le recrée (éventuellement en différé)

#### Structure de données

On utilise un objet appelé `ExpenseStatement`, qui joue le rôle d’une facture de vente à destination des copropriétaires.

Les lignes associées (`ExpenseStatementOwnerLine`) sont proches des lignes d’une facture classique (`InvoiceLine`), mais sont enrichies avec des champs spécifiques :
Elles permettent de faire le lien avec les écritures comptables générées,
Et d'assurer une traçabilité fine des montants imputés à chaque propriétaire ou lot.

Ce modèle permet de gérer des cas comme :

* Une annulation (dé-clôture) du décompte, sans supprimer les écritures d’origine.
* La possibilité de réimprimer ou reconstituer une version annulée plus tard, tout en tenant compte d’éventuelles nouvelles écritures ou corrections.

L’état généré contient :

- Un total global des charges constatées

- Un total réparti

- Un éventuel écart (arrondis) placé sur un compte dédié (`rounding_adjustment`)

- Pour chaque propriétaire :
  
  - La liste des lots concernés
  
  - Les charges ventilées par type (commune, privative, fonds)
  
  - Pour chaque charge : compte, clé de répartition, montant, TVA, ventilation propriétaire/locataire

### Types de dépenses gérées

#### 1. **Charges communes (61xx)**

Réparties selon les clés de répartition définies en AG.

- Le montant est ventilé selon les **quotes-parts des lots** et le **temps de possession** sur la période.
- Chaque ligne indique :
  - Le compte de charge
  - La clé de répartition utilisée
  - Le montant pour le propriétaire et éventuellement pour le locataire

#### 2. **Charges reportées (490)**

Même logique que les charges communes, mais le montant concerne une période future. Elles sont traitées comme une charge au moment du décompte.

#### 3. **Frais privatifs (643xxx)**

Imputés directement à un ou plusieurs copropriétaires.

- Pas de répartition : c’est une imputation directe.
- La ventilation propriétaire/locataire peut être précisée.

#### 4. **Utilisations de fonds de réserve (6816xxx)**

Montant retiré d’un fonds de réserve existant.

- Toujours imputé aux propriétaires uniquement (jamais aux locataires).
- La répartition doit suivre la même clé que celle utilisée pour alimenter le fonds.

### Proratisation dans le temps

Pour chaque propriétaire, on calcule le nombre de **jours de possession** sur la période, à partir des dates d’acquisition et de cession des lots. Ce nombre est utilisé pour répartir les charges au "prorata temporis", si plusieurs propriétaires se sont succédé dans la période.

### Structure inermédiaire pour ventilation

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

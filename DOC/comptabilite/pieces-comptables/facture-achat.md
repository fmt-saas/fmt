## Factures d'Achat

Une facture d'achat suit le workflow standard pour les factures : `proforma`, `invoice`, `cancelled`

Il est possible d’enregistrer une facture en tant que pro forma (statut proforma) dans un premier temps, afin de compléter ou corriger les informations avant validation finale.

A la validation (passage de 'proforma' à 'invoice')

* Une vérification de la conformité est réalisée (total déclaré et la somme des lignes, +autres contraintes).
* On assigne un numéro de cachet : invoice number (distinction `supplier_invoice_number`)
* On génère les écritures écritures immédiates
* On planifie les écritures pour chaque début de période à venir

## Informations à renseigner

Lors de l'encodage, les informations suivantes doivent être complétées :

| Champ                 | Description                                                                                                                                 |
| --------------------- | ------------------------------------------------------------------------------------------------------------------------------------------- |
| **Fournisseur**       | Identifié automatiquement sur base du numéro de TVA                                                                                         |
| **Date de facture**   | Date indiquée sur le document reçu                                                                                                          |
| **Numéro de facture** | Numéro du document transmis par le fournisseur                                                                                              |
| **VCS**               | Référence fournisseur (ex. n° client interne ou code de suivi fournisseur)                                                                  |
| **Période couverte**  | Facultative – utile si le fournisseur découpe par période ; champs : `date_from` (début) et `date_to` (fin), issus de `<cac:InvoicePeriod>` |
| **Date d’échéance**   | Champ `<cbc:PaymentDueDate>`, indiqué sur la facture                                                                                        |
| **Pièces jointes**    | Documents PDF, images ou annexes liées à la facture (`docs_ids`)                                                                            |
| **Numéro de cachet**  | Assigné automatiquement à la validation (statut = `invoice`) ; non modifiable après validation                                              |



## Gestion du numéro de TVA sur les factures fournisseurs

Le système de gestion des factures fournisseurs prend en compte les différentes situations légales existant en Belgique concernant le statut TVA des fournisseurs. Il y a un panel varié de situations possibles selon le régime TVA du fournisseur de de l'ACP : B2B, B2C, C2B, C2C.

### Distinction entre numéro d’entreprise et numéro de TVA

Toute entité juridique (société, ASBL, etc.) dispose d’un **numéro d’entreprise** attribué par la Banque-Carrefour des Entreprises (BCE).
Ce numéro identifie l’entité sur le plan administratif et juridique.

Le **numéro de TVA** correspond à l’activation de ce numéro d’entreprise auprès de l’administration TVA.
Il est généralement exprimé sous la forme :

```
BE0xxx.xxx.xxx
```

Toutes les entreprises ne sont pas assujetties à la TVA. Il est donc possible qu’une entreprise dispose d’un numéro d’entreprise sans disposer d’un numéro de TVA.

### Types de fournisseurs pris en charge

Le système prend en charge plusieurs types de fournisseurs, correspondant aux différentes situations rencontrées en pratique.

| Type de fournisseur                     | Statut TVA         | Numéro de TVA présent sur la facture |
| --------------------------------------- | ------------------ | ------------------------------------ |
| Entreprise assujettie à la TVA          | TVA appliquée      | Oui                                  |
| Entreprise sous régime de franchise TVA | TVA non facturée   | Oui                                  |
| Entreprise non assujettie à la TVA      | TVA non applicable | Non                                  |
| Particulier (syndic)                    | Hors TVA           | Non                                  |

Dans les cas où le fournisseur n’est pas assujetti à la TVA, la facture peut ne pas comporter de numéro de TVA. Le système accepte donc les factures fournisseurs **avec ou sans numéro de TVA**.

### Conséquence sur les contrôles de validation

Lors de l’encodage ou de l’import d’une facture fournisseur, le système applique les règles suivantes :

- le **numéro de TVA fournisseur est optionnel** ;
- les factures peuvent être enregistrées même si aucun numéro de TVA n’est renseigné ;
- la présence ou l’absence de TVA sur la facture dépend des informations fournies par le document et du statut du fournisseur.

Cette flexibilité permet de gérer correctement les factures provenant :

- d’entreprises assujetties à la TVA,
- d’entreprises bénéficiant du régime de franchise,
- d’entreprises ou d’organisations non assujetties,
- de particuliers.

### Compatibilité avec la facturation électronique (PEPPOL)

Le système est compatible avec les obligations de facturation électronique qui concernent principalement les transactions **entre entités assujetties à la TVA (B2B)**.

Toutefois, dans certains contextes — notamment celui des associations de copropriétaires (ACP), généralement non assujetties à la TVA — les factures fournisseurs peuvent continuer à être reçues sous forme traditionnelle (PDF ou papier). Le système permet donc la gestion simultanée de factures structurées et de factures non structurées.



## Distinction entre facture originale et ventilation analytique

Dans le traitement des factures d’achat, il est essentiel de distinguer la **facture fournisseur originale** (document juridique et fiscal) de la **ventilation comptable interne** (projection analytique propre à l’ACP).

La facture originale, qu’elle provienne de Peppol (UBL) ou d’un encodage manuel, constitue la référence intangible : ses montants HT, TVA, TVAC et ses groupes de taxe font foi et ne peuvent être modifiés.

La ventilation analytique est une reconstruction interne destinée à répartir la dépense entre différents bénéficiaires (blocs, lots, locataires, usages spécifiques). Elle peut différer de la structure du document fournisseur, mais doit rester strictement cohérente avec ses totaux.

Cette séparation garantit la conformité fiscale, la cohérence comptable et la traçabilité des montants répartis.


### 1. Distinction structurelle

#### Facture originale (juridique – figée)

- Montants HT, TVA, TVAC
- Groupes de taxe (par taux)
- `PayableAmount`
- Aucune modification autorisée

> Si l’ACP est assujettie à la TVA, le champ `total_vat` (montant total de taxe) doit impérativement être renseigné et correspondre au montant de TVA figurant sur la facture fournisseur.
> Ce montant constitue la base de la déclaration TVA et ne peut être altéré par la ventilation interne.

#### Ventilation interne (analytique ACP)

- Projection des montants vers blocs / lots / locataires
- Peut être différente de la structure fournisseur
- Doit rester strictement cohérente avec la facture


### 2. Segmentation obligatoire par groupe de taxe

- Construire les **TaxGroups** à partir de la facture (par taux).
- Toute ligne ventilée appartient à **un seul groupe de taxe**.
- Interdiction de mélanger plusieurs taux dans une même ligne ventilée.


### 3. Règles de ventilation

- La ventilation se fait **uniquement en HT**.
- La TVA est **répartie proportionnellement** au HT dans chaque groupe.
- Interdiction de recalculer la TVA via `rate × base`.
- Ajustement du dernier centime **dans chaque groupe** pour garantir :

```
Σ TVA ventilée (groupe) = TVA groupe facture
```


### 4. Gestion des écarts

#### Tolérances

- Cohérence facture : ≤ 0.01 €
- Cohérence ventilation : ≤ 0.05 € (paramétrable)

#### En cas de delta acceptable

- Générer automatiquement une écriture :
  - Compte 4991 – rounding_adjustment
  - Liée à la facture
  - Incluse dans la répartition

#### Si delta > tolérance

- Blocage avec erreur


### 5. TVA déductible (cas spécifiques)

- La déductibilité s’applique **après ventilation**.
- Jamais de recalcul de TVA.
- On utilise la TVA ventilée réelle comme base fiscale.
- Si l’ACP est assujettie (totalement ou partiellement), les montants globaux `total_ht` et `total_vat` doivent rester strictement conformes à la facture fournisseur afin de garantir la fiabilité de la déclaration TVA.


### 6. Invariants à garantir

À tout moment :

```
Σ HT ventilé = HT facture
Σ TVA ventilée = TVA facture
Σ TVAC ventilé = TVAC facture
Σ écritures comptables = PayableAmount
```

En cas d’ACP assujettie :

```
total_vat facture = montant de TVA déclaré
```


## Découpage de la facture en lignes d’imputation (`InvoiceLine`)

Lors de l’encodage d’une facture d’achat, le montant total de la facture n’est pas utilisée comme référence : ce sont les lignes de détail qui font foi pour l’imputation comptable.

Il est possible de définir une série de **lignes d’imputation**, chaque ligne permettant d'assigner des montants à des **comptes de charges** spécifiques. Ce découpage ne doit pas nécessairement à correspondre aux lignes présentes sur la facture d'origine : une facture peut être répartie différemment en fonction des critères comptables de la copropriété.

Une suggestion automatique est proposée sur base de la reconnaissance OCR, mais elle doit être validée ou corrigée par le gestionnaire.

Chaque ligne est associée à plusieurs éléments :

- **Un compte de charge** (généralement de type `61xx` ou autre catégorie spécifique).
- **Une clé de répartition**, définissant la manière dont les montants doivent être affectés.
- **Un ratio PROP / LOC**, utilisé pour répartir la charge entre les copropriétaires (ou autres entités concernées).

Ces informations sont copiées de la configuration du compte, mais peuvent être ajustées manuellement pour tenir compte de particularités ou d’exception de gestion.

Une assignation auto (compte, clé, part Prop/Loc) peut être faite via la sélection d'une "nature de dépense".

Les lignes de factures sont modélisées avec l'entité `InvoiceLine`.

Contraintes :

* il ne peut y avoir qu'une seule ligne pour un même compte de charge (dans le cas contraire, on ne peut pas retrouver à quel ligne correspond une imputation comptable)

#### Utilisation du fonds de roulement

Lorsque ce n'est pas précisé autrement, les charges sont considérées comme des charges communes qui devront être réparties entre les copropriétaires sur base de clés de répartitions.

Dans les autres situations, la part du paiement imputée au fonds de roulement correspond à la différence entre le montant total de la facture, la part éventuellement couverte par le ou les fonds de réserve, et les éventuelles parties privatives.

Pour chaque utilisation de fonds, il faut préciser le compte de réserve 160X utilisé.
Sur base du fonds sélectionné, le compte de charge correspondant (libellé « Appels ») est utilisé, par exemple 680010.
(Les informations de compte comptable et de clé de répartition ne peuvent pas être modifiées ; le ratio PROP / LOC n’est pas pertinent dans ce contexte.)

#### Frais privatifs

Certaines dépenses peuvent être considérées comme frais privatifs, c’est-à-dire applicables uniquement à un copropriétaire ou un lot spécifique, et non réparties entre l’ensemble des copropriétaires.

Dans ce cas, l’imputation se fait obligatoirement sur le compte 643 – Frais privatifs.

Les frais privatifs sont exclus des répartitions collectives et doivent faire l’objet d’un suivi individuel pour refacturation ou régularisation ultérieure.

#### Utilisation de fonds de réserve

Une partie ou la totalité d'une facture peut être payée via l'utilisation d'un ou plusieurs fonds de réserve.

**Le fonds de réserve est toujours utilisé avec la même clé de répartition que celle utilisée pour son appel.**
Cela garantit la traçabilité et l’équité dans la répartition entre copropriétaires (La clé de répartition ne peut pas être modifiée lors de l’utilisation du fonds.)

### Distinction entre date de facture, date d'imputation et période d'imputation

On fait une distinction entre la date de la facture (date d’émission par le fournisseur) et la période à laquelle les prestations ou services facturés se rapportent.

Si le fournisseur a renseigné une "période de facturation", celle-ci est utilisée par défaut comme intervalle de dates. Dans tous le cas, l'imputation peut  toujours être modifiée pour utiliser un autre intervalle ou une autre date.

Comme date pour l'imputation comptable , on utilise :

* la date d'imputation choisie (date d'encodage de la facture par défaut)
* si un intervalle de dates est renseigné (`date_from` et `date_to`), c’est la première date de l’intervalle qui est retenue 

Note : Lorsqu’une facture est financée, en tout ou en partie, par un fonds de réserve, l’imputation correspondante est toujours enregistrée à une seule date, indépendamment de la période couverte par la facture.

### Comptes comptables liés

Lors de la création d’un fonds de réserve, trois comptes comptables sont générés automatiquement, tous liés à la **même clé de répartition** (qui ne peut être modifiée, afin de garantir la cohérence entre appel et utilisation):

1. `160xxxx` – **Fonds de réserve** (passif)
   
   > Compte principal de stockage du fonds

2. `68160xx0` – **Appels de fonds de réserve**
   
   > Enregistrement des montants appelés auprès des copropriétaires

3. `68160xx1` – **Utilisation (consommation) du fonds de réserve**
   
   > Compte technique servant à enregistrer la sortie du fonds lors de son usage effectif



## Paiement

Il y a 3 méthodes de paiement possibles: 
a) par "domiciliation" (cette info doit être dans le contrat et est, en principe, reprise sur la facture d'achat)
b) par banque ("prélèvement direct") -> création SEPA
c) en utilisant un fonds de réserve -> création SEPA (compte épargne) 

* il est possible d'utiliser plusieurs fonds de réserve
* il est possible d'utiliser uniquement un fonds de réserve (pas de paiement via compte courant)

=> il faut deux indications "refuser la facture", "payer par domiciliation" (dans les deux cas, il faut empêcher de générer un SEPA) [on ne peut pas "ne pas payer" s'il y a une domiciliation]

Si domiciliation : pas de création de SEPA correspondante (pas d'implication sur l'utilisation ou non de fonds de réserve)
proposition (pas auto) de SEPA de transfert (épargne / à vue) uniquement si les fonds sont suffisants

!! ce n'est pas parce qu'une facture est encodée qu'on la paie
"ne pas payer" : ne pas générer le SEPA

possibilité de payer en plusieurs fois (Funding)

écran distinct pour les paiement (multi-factures), avec assignation des montants payés

### Utilisation du fonds de roulement

| Libellé                                  | Débit                                                                        | Crédit                             |
| ---------------------------------------- | ---------------------------------------------------------------------------- | ---------------------------------- |
| Paiement via le compte courant           | 440000 - Fournisseur : 1.000 €                                               | 550000 - Banque courant : 1.000 €  |
| Aucun mouvement direct sur le compte 100 | *Le compte 100 est affecté en fin d'exercice selon l’excédent ou le déficit* | *Pas d’écriture comptable directe* |

Rappel: le fonds de roulement (100) est un passif qui ne peut être alimenté que par des participations des copropriétaires (comptes copropriétaires) et n'est jamais directement utilisé (c'est le compte bancaire qui l'est).



### Utilisation de fonds de réserve

| Libellé                         | Débit                                  | Crédit                           |
| ------------------------------- | -------------------------------------- | -------------------------------- |
| Utilisation du fonds de réserve | 68160xx1 – Utilisation du fonds : 500€ |                                  |
| Affectation du fonds de réserve |                                        | 160xx – Fonds de réserve : 500 € |



## Informations complémentaires

Certaines informations additionnelles permettent de paramétrer le comportement du logiciel

* Paiement par domiciliation : si coché au niveau de la facture de vente, le paiement ne doit pas être fait à la main, l'information sera répercutée sur le Funding correspondant, et l'ordre de paiement (SEPA) ne pourra pas être généré (un extrait bancaire avec le mouvement sera reçu).



## Ecritures d'imputation

Lors de la **validation d’une facture d’achat**, une **écriture comptable unique** est automatiquement générée dans le journal concerné.
 Cette écriture peut comporter **plusieurs lignes**, en fonction des imputations définies (charges, reports, réserves...).

Les **libellés** des lignes tiennent compte des **périodes couvertes** par la facture, ce qui permet d’assurer une traçabilité fine dans le temps.



L'écriture d'imputation est toujours faite sur la période correspondant à la date d'émission de la facture, telle que renseignée par le fournisseur.

### Encodage de la facture

Lors de l’enregistrement d’une facture d'achat, une ligne d’imputation est créée pour le compte fournisseur (compte comptable associé au fournisseur).

Cette ligne est toujours présente, quelles que soient les opérations complémentaires (charges à reporter, utilisation d’un fonds de réserve, ou affectations en frais privatifs).

| Date       | Pièce              | Compte             | Libellé                           | Débit | Crédit |
| ---------- | ------------------ | ------------------ | --------------------------------- | ----- | ------ |
| 01/01/2025 | ACH 0041-2025-0001 | 440 - Fournisseurs | Prime du 01/01/2025 au 31/12/2025 |       | 2000   |

### Utilisation du fonds de roulement

Lorsque les lignes de la facture **ne sont pas des frais privatifs**, chaque ligne est imputée en débitant le compte de charge correspondant (ex. 61xx), selon sa nature.

| Date       | Pièce              | Compte             | Libellé                           | Débit | Crédit |
| ---------- | ------------------ | ------------------ | --------------------------------- | ----- | ------ |
| 01/01/2025 | ACH 0041-2025-0001 | 611000 - Assurance | Prime du 01/01/2025 au 31/12/2025 | 2000  |        |

### Répartition sur plusieurs périodes

Lors de son enregistrement, si une facture d'achat couvre plusieurs périodes ou exercices (ex. assurance annuelle, contrats de maintenance…), pour respecter le principe de rattachement des charges, on répartit la charge dans le temps (même si la facture est payée en une seule fois).

Pour gérer cette situation, on utilise des "écritures planifiées" pour anticiper les écritures sur les périodes ultérieures.



Un date_range est toujours renseigné et utilisé (la seule exception est une date seule [date_from = date_to]). Si le marqueur `has_date_range` est activé, il prime pour la répartition des charges; dans les autre cas, c'est la période de la fiscal_period qui est utilisée.



Exemple d’une facture de **2 000 €** pour une assurance couvrant du **01/01/2025 au 31/12/2025** :

| Date       | Pièce              | Compte                  | Libellé                           | Débit | Crédit |
| ---------- | ------------------ | ----------------------- | --------------------------------- | ----- | ------ |
| 01/01/2025 | ACH 0041-2025-0001 | 614 - Assurance         | Prime du 01/01/2025 au 31/03/2025 |       | 500    |
| 01/01/2025 | ACH 0041-2025-0001 | 614 - Assurance         | Prime du 01/04/2025 au 30/06/2025 |       | 500    |
| 01/01/2025 | ACH 0041-2025-0001 | 614 - Assurance         | Prime du 01/04/2025 au 30/06/2025 |       | 500    |
| 01/01/2025 | ACH 0041-2025-0001 | 49 - Charges à reporter | Prime du 01/07/2025 au 30/09/2025 | 500   |        |
| 01/01/2025 | ACH 0041-2025-0001 | 49 - Charges à reporter | Prime du 01/10/2025 au 31/12/2025 | 500   |        |
| 01/01/2025 | ACH 0041-2025-0001 | 49 - Charges à reporter | Prime du 01/10/2025 au 31/12/2025 | 500   |        |

**écriture planifiées:**

| Date       | Pièce              | Compte                  | Libellé                           | Débit | Crédit |
| ---------- | ------------------ | ----------------------- | --------------------------------- | ----- | ------ |
| 01/04/2025 | ACH 0041-2025-0001 | 49 - Charges à reporter | Prime du 01/04/2025 au 30/06/2025 |       | 500    |
| 01/04/2025 | ACH 0041-2025-0001 | 614 - Assurance         | Prime du 01/04/2025 au 30/06/2025 | 500   |        |

| Date       | Pièce              | Compte                  | Libellé                           | Débit | Crédit |
| ---------- | ------------------ | ----------------------- | --------------------------------- | ----- | ------ |
| 01/07/2025 | ACH 0041-2025-0001 | 49 - Charges à reporter | Prime du 01/07/2025 au 30/09/2025 |       | 500    |
| 01/07/2025 | ACH 0041-2025-0001 | 614 - Assurance         | Prime du 01/07/2025 au 30/09/2025 | 500   |        |

| Date       | Pièce              | Compte                  | Libellé                           | Débit | Crédit |
| ---------- | ------------------ | ----------------------- | --------------------------------- | ----- | ------ |
| 01/10/2025 | ACH 0041-2025-0001 | 49 - Charges à reporter | Prime du 01/10/2025 au 31/12/2025 |       | 500    |
| 01/10/2025 | ACH 0041-2025-0001 | 614 - Assurance         | Prime du 01/10/2025 au 31/12/2025 | 500   |        |

Note : en cas d'intersection partielle entre l'intervalle de dates choisi et les dates des périodes concernés, les montants assignés à chaque période sont proratisés sur base du nombre de jours concernés au sein de chaque période d'exercice.

#### Écritures prévisionnelles / écritures planifiées

Les écritures planifiées sont similaires à des écritures comptables mais restent hors comptabilité (elles ne sont pas directement visibles, ni reprises dans la balance).
Au moment de la création de la facture on génère les écritures planifiées (liées à la fois à une facture, à une copropriété et à une date).

* Les écriture planifiées ('planned') sont des éléments "système" : elles font partie de la logique système et ne peuvent pas être validées ou supprimées manuellement
* En cas d'annulation d'une pièce comptable, elles doivent être supprimées (en les détachant du invoice_id et en les supprimant)

### Utilisation de fonds de réserve

Lorsqu’une facture d’achat est financée, en tout ou en partie, par un fonds de réserve, une ou plusieurs lignes spécifiques sont ajoutées à l’écriture comptable pour en refléter l'utilisation. Cette imputation ne fait pas l’objet d’une répartition périodique: le montant imputé au fonds de réserve est enregistré **à une seule date**, qui correspond à la validation de la facture ou à la décision d’utilisation du fonds.

L’écriture comptable suit le schéma suivant :

| Compte                            | Débit | Crédit | Rôle                                       |
| --------------------------------- | ----- | ------ | ------------------------------------------ |
| `68160xx1 – Utilisation du fonds` | 500   |        | Représente la charge financée par le fonds |
| `160xx – Fonds de réserve`        |       | 500    | Diminution du passif lié au fonds          |

Cette écriture s’ajoute à celles liées aux charges (`61xx`), reports (`49`), et dettes fournisseurs (`440`) de la facture.

- La **clé de répartition** utilisée pour le fonds est **identique** à celle définie lors de l’appel initial du fonds (non modifiable).
- L’imputation au fonds ne dépend **ni de la date de facture**, ni de la période de prestation : elle est liée uniquement à la **date de validation**.
- L'utilisation du fonds n’est **pas répartie** dans les périodes suivantes : elle est **comptabilisée en une fois**.

| Libellé                                                   | Débit                              | Crédit                                  |
| --------------------------------------------------------- | ---------------------------------- | --------------------------------------- |
| Réception de la facture (ex. travaux votés : toiture)     | 672000 - Travaux toiture : 5.000 € | 440001 - Fournisseur : 5.000 €          |
| Paiement via le compte bancaire dédié au fonds de réserve | 440001 - Fournisseur : 5.000 €     | 550001 - Banque fonds réserve : 5.000 € |
| Enregistrement de la baisse du fonds de réserve (clôture) | 681601 - Travaux toiture : 5.000 € | 160001 - Fonds de réserve : 5.000 €     |

Note : Les factures sont toujours payées par le compte courant de la copropriété. Le syndic fait un transfert du compte épargne vers le compte courant. Cette double opération  pourrait être automatisée lors d'un paiement via un fonds de réserve (avec la création du SEPA et l'OD correspondante du compte épargne [associé au fonds de réserve] vers le compte courant).

### Frais privatifs

Lorsqu’une facture d’achat (ou une ou plusieurs de ses lignes) correspond à des **frais privatifs**, le montant concerné est refacturé à un ou plusieurs copropriétaires (ces frais ne sont pas répartis via une clé de répartition).

Deux approches sont possibles pour le traitement comptable de ces frais :

#### 1. Intégration différée via le décompte de charge

Les frais privatifs sont **encodés comme charges privatives**, mais **ne seront refacturés qu’au moment du décompte**.

- Aucun produit n’est généré immédiatement.
- La refacturation est effectuée dans le cadre du **décompte de charge**, générant les écritures associées.

#### 2. Refacturation immédiate (hors décompte de charge)

Permet de **refacturer directement les frais au copropriétaire concerné**, sans attendre la clôture d’une période comptable.

- Les frais **ne seront pas repris dans le décompte de charges**.
- Une écriture supplémentaire est générée dans les écritures de la facture d'achat.

Écritures toujours générées :

| Compte                   | Débit | Crédit | Description                           |
| ------------------------ | ----- | ------ | ------------------------------------- |
| 643xxx – Frais privatifs | x €   |        | Enregistrement de la charge privative |

Écritures supplémentaires avec "**refacturation immédiate des frais privatifs**":

| Compte                   | Débit | Crédit | Description                         |
| ------------------------ | ----- | ------ | ----------------------------------- |
| 4101xx – Copropriétaire  | x €   |        | Refacturation au copropriétaire     |
| 643xxx – Frais privatifs |       | x €    | Contrepassation du compte de charge |

### Arrondis

Lors de la répartition des charges entre les copropriétaires, de légers écarts peuvent apparaître en raison de l’arrondi des montants (au centime près). Ces écarts sont automatiquement compensés via un **compte dédié**, afin de garantir que les écritures restent équilibrées.

- La **somme des montants répartis** aux copropriétaires peut ne pas correspondre exactement au **montant total facturé** (écart de 0,01 € à quelques centimes).
- Pour assurer l’équilibre, cette différence est passée sur un compte d’ajustement.

#### Compte utilisé

- Compte 4991 – Écarts d’arrondi (`rounding_adjustment`)
- Ce compte est :
  - **Débité** si la répartition est **inférieure** au total facturé.
  - **Crédité** si la répartition est **supérieure** au total facturé.

#### Écriture comptable

L’écriture est générée **au moment de la clôture de période**, en complément des écritures principales du décompte :

| Compte                  | Débit | Crédit | Description                               |
| ----------------------- | ----- | ------ | ----------------------------------------- |
| 4991 – Écarts d’arrondi | x €   |        | Si la répartition est inférieure au total |
| 4991 – Écarts d’arrondi |       | x €    | Si la répartition est supérieure au total |

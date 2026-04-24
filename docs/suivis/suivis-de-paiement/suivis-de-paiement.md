## Suivis de paiement

### Récap

```
BankStatement (pièce justificative)
 └─ BankStatementLine (mouvement réel)  --- AccountingEntry (écriture comptable finale)
     └─ Payment(s) (ventilation du mouvement)
         └─ Funding(s) (créance/dette attendue)
             └─ pièces d'origine (PurchaseInvoice, ExpenseStatement, FundRequestExecution, MiscOperation)
```

### BankStatement

Un **BankStatement** représente un extrait bancaire reçu.
C’est la **pièce justificative** : il regroupe toutes les lignes (`BankStatementLine`) issues d’un relevé bancaire et constitue la source pour la création des écritures comptables.

#### Structure des lignes

Chaque `BankStatementLine` est normalisée pour permettre une gestion uniforme.

| Champ                  | Type      | Description                          |
| ---------------------- | --------- | ------------------------------------ |
| `transaction_id`       | string    | Identifiant unique de la transaction |
| `date`                 | date-time | Date de l’opération                  |
| `value_date`           | date-time | Date de valeur si différente         |
| `amount`               | number    | Montant débité ou crédité            |
| `currency`             | string    | Devise (ISO 4217)                    |
| `balance`              | number    | Solde du compte après transaction    |
| `counterparty`         | string    | Tiers associé                        |
| `counterparty_account` | string    | IBAN du tiers                        |
| `counterparty_bic`     | string    | BIC du tiers                         |
| `communication`        | string    | Communication libre ou structurée    |
| `reference`            | string    | Référence de paiement ou de virement |

### Funding

Un objet `Funding` représente un **flux de trésorerie attendu ou initié**, dans le cadre d’un financement, d’un virement, d’un remboursement ou d’un mouvement interne. Dans la grande majorité des cas, il s'agit d'un montant réclamé à un copropriétaire, dans le cadre d'un **appel de fonds**, d’un **état des dépenses** ou d’un **décompte de charges**. Il correspond à une attente comptable, liée à des écritures.

!!! note "Distinction entre suivi et comptabilité"
    💡 Le cumul des `Funding` relatifs aux appels de fonds ne reflète pas toujours la situation comptable réelle  : certains financements peuvent ne pas avoir encore été générés, annulés ou faire l'objet de situations particulières.  
    💡 Les funding et les paiements sont uniquement des moyens de suivre les paiements attendus et de générer des SEPA/QR codes, ils sont dissociés des écritures comptables (mais liés via l'objet auquel ils se rapportent), et permettent d'identifier à quel moment des écritures sont nécessaires ou peuvent être faites.

#### Origine (comptable)

Un `Funding` est le plus souvent rattaché, directement ou indirectement, à une **pièce comptable de référence**, telle que :

- un `FundRequestExecution` (appel de fonds exécuté)
- un `ExpenseStatement` (état des dépenses ou décompte)
- une `Invoice` (facture fournisseur - à payer)
- `MiscOperation` (OD de remboursement )
- `MoneyTransfer` (transfert entre comptes internes)

Notes : 

* dans le cas de Funding avec montant négatif (funding de paiement - typiquement facture d'achat), une option permet de générer un SEPA (ordre de paiement à envoyer à la banque)
* dans les autre cas, on peut générer un bordereau de paiement avec QR code (template selon la pièce comptable)

#### Typologie

Plusieurs `Funding` peuvent être générés à partir d'une seule pièce. 

Le type d'un `Funding` est identité via le champ `funding_type`:

| Type                | Description                                                         |
| ------------------- | ------------------------------------------------------------------- |
| `installment`       | Financement pour le versement d'un acompte (a priori, pas utilisé). |
| `reimbursement`     | Financement pour un remboursement.                                  |
| `transfer`          | Financement pour transfert interne entre comptes.                   |
| `invoice`           | Financement pour le paiement d'une facture d'achat.                 |
| `fund_request`      | Financement d'appel de fonds.                                       |
| `expense_statement` | Financement de régulation suite à un décompte périodique.           |
| `misc`              | Financement lié à une OD.                                           |

!!! note "Montants négatifs & Remboursements"
    Dans le cas d’un **montant négatif**, le `Funding` est tout de même créé (notamment pour visualiser le droit à remboursement), mais son traitement dépend du contexte : il peut être ignoré, soldé par compensation ou supprimé si aucun remboursement n’est demandé.

#### Particularités

* Montants **négatifs** possibles (ex. remboursement attendu)
* Génération possible d’un **ordre bancaire SEPA** pour paiements sortants
* Statut `is_sent` permet de suivre si le SEPA a été généré et transmis

#### Attribution automatique des paiements

Lors de la création d’un `Funding`, le système recherche automatiquement les **paiements disponibles** pour le copropriétaire concerné. Ces paiements, issus d’extraits bancaires et liés à des écritures comptables, sont alors affectés au `Funding` nouvellement créé, permettant de réduire le solde dû sans intervention manuelle.

#### Statuts d’un Financement (`status`)

Le champ `status` reflète exclusivement l’état financier du `Funding`, indépendamment de son éventuelle annulation :

| Statut           | Signification                                        |
| ---------------- | ---------------------------------------------------- |
| `pending`        | Aucun paiement n’a encore été affecté                |
| `debit_balance`  | Paiement partiel                                     |
| `balanced`       | Montant payé intégralement                           |
| `credit_balance` | Trop-perçu (ou versé) par rapport au montant attendu |

#### Lien avec les comptes bancaires

 Chaque `Funding` peut impliquer **un ou deux comptes bancaires**, selon son type et son rôle (entrant / sortant).

##### Champ `bank_account_id` (compte principal)

Le champ `bank_account_id` représente le **compte bancaire concerné par le mouvement principal**.

- Si le **montant (`amount`) est positif** : le compte `bank_account_id` est **le bénéficiaire attendu** du paiement (ex. : on attend un versement sur ce compte).
- Si le **montant est négatif** : le compte `bank_account_id` est **le compte à débiter** pour effectuer un paiement sortant.

> 🎯 **Interprétation métier** : `bank_account_id` est toujours "le compte concerné par le mouvement côté copropriété".

##### Champ `counterpart_bank_account_id` (compte opposé)

Le champ `counterpart_bank_account_id` est renseigné **seulement si le type de `Funding` l'exige**. Il permet de **spécifier l’autre extrémité du flux**, lorsque le mouvement est un **transfert ou un remboursement bilatéral**.

- Dans un **virement interne**, c’est le compte bancaire **de destination** si `bank_account_id` est le compte de départ.
- Dans un **remboursement**, c’est le compte bancaire **du tiers ou du client**.
- Dans un **appel de fonds**, ce champ est souvent laissé vide (on ne connaît pas les comptes des copropriétaires).

> 🔐 **Contrôle** : la présence ou l'absence de `counterpart_bank_account_id` dépend du **type** de `Funding`, via une contrainte conditionnelle.

##### Règles d’interprétation

| Montant       | `bank_account_id` est…               | `counterpart_bank_account_id` est…             |
| ------------- | ------------------------------------ | ---------------------------------------------- |
| Positif (> 0) | Le compte **recevant** le paiement   | (optionnel) Le compte de provenance (si connu) |
| Négatif (< 0) | Le compte **effectuant** le paiement | Le compte de destination                       |

##### Cas typiques

###### Appel de fonds / Paiement attendu :

```
amount = 150.00
bank_account_id = compte bancaire de la copropriété
counterpart_bank_account_id = null (le copropriétaire peut faire le versement via n'importe quel compte)
```

###### Paiement à un fournisseur (facture d'achat):

```
amount = -450.00
bank_account_id = compte bancaire de la copropriété
counterpart_bank_account_id = compte du fournisseur
```

###### Virement interne :

```
amount = -5000.00
bank_account_id = compte source
counterpart_bank_account_id = compte de destination
```

#### Annulation d’une pièce de référence

Lorsqu’un document de référence (appel, décompte…) est annulé :

- Tous les `Funding` associés sont marqués comme **annulés** (`is_cancelled = true`)
- Les `Payments` affectés à ces financements sont **détachés** (`funding_id = null`) et redeviennent disponibles pour être affectés à d’autres `Funding`, existants ou futurs.

#### Génération d’un ordre bancaire (SEPA)

Lorsqu'il contient à la foi un compte d'origine et de destination explicites, un `Funding` peut faire l'objet d'une **génération d’ordre bancaire**, matérialisée par un fichier **SEPA**.

Pour en assurer le suivi, un indicateur booléen `is_sent` est utilisé :

- `false` : le financement n’a pas encore été transmis sous forme d’ordre bancaire
- `true` : l’ordre de virement a été généré (et potentiellement transmis à la banque)

Ce flag permet d’identifier facilement les `Funding` en attente de traitement par le gestionnaire financier (comptable, syndic, etc.) et d’éviter les doublons ou oublis lors des campagnes de remboursement.

### PaymentReminder — Usage et rôle dans le modèle comptable

L’entité `PaymentReminder` est conçue pour assurer le suivi des montants dus par les copropriétaires dans un contexte de copropriété (ACP). Elle est indépendante des documents comptables d’origine et se concentre sur la **situation financière actuelle** d’un copropriétaire.

Son objectif est de fournir un cadre unifié pour gérer la **dette**, le **suivi des paiements** et les **actions de relance**, indépendamment de l’origine des montants dus.

#### 1. La dette (ce qui est dû)

Un `PaymentReminder` est toujours associé à un **montant restant dû** par un copropriétaire.

Ce montant peut provenir de différents artefacts comptables, tels que :

- `ExpenseStatement` (décomptes de charges)
- `FundRequestExecution` (appels de fonds pour provisions ou dépenses exceptionnelles)

Plutôt que de dépendre directement de ces documents, le `PaymentReminder` représente une **vue consolidée de la dette** à un instant donné.

Il répond à la question :

> *Quel est le montant que ce copropriétaire doit encore payer ?*

Cela permet de :

- agréger plusieurs sources de dette,
- rester stable même si les documents sous-jacents évoluent,
- découpler le suivi opérationnel de la structure comptable.

#### 2. Le paiement (ce qui a été payé)

Les paiements effectués par les copropriétaires sont gérés via l’entité `Funding`.

Le `PaymentReminder` prend en compte ces paiements pour déterminer le **solde restant dû**. Il s’agit donc d’un objet dynamique qui :

- reflète la **situation financière réelle** du copropriétaire,
- intègre naturellement les paiements partiels,
- s’appuie sur les données comptables à jour.

Ainsi, les relances sont toujours basées sur :

> *le montant réellement impayé, et non sur le montant initial demandé.*

#### 3. La relance (l’action à entreprendre)

Le rôle principal du `PaymentReminder` est de structurer les **actions de relance** à destination du copropriétaire.

Il matérialise le fait que :

- un montant reste impayé,
- un paiement est attendu,
- une action de suivi doit être engagée.

Un `PaymentReminder` peut correspondre à différents niveaux de relance, par exemple :

- rappel informel,
- relance formelle,
- mise en demeure.

Il peut inclure :

- une date de référence,
- le montant dû à cette date,
- le copropriétaire concerné,
- un niveau ou statut de relance.

### Payment

Les `Payments` représentent les mouvements bancaires (sommes **effectivement versées**).

Un paiement (`Payment`) est toujours lié à 

* une ligne d'extrait bancaire (`BankStatementLine`)

* un financement (`Funding`) (mais ce lien peut être rompu, voir ci-dessous)

Un Payment représente la contre partie d'une écriture liée à une pièce comptable et correspond donc à une écriture dans le livre FIN (BANK).

Notes : 

* Une ligne d'extrait peut être liée à plusieurs paiements (dans le cas où le montant versé est partiel ou correspond à plusieurs montants attendus), et donc à plusieurs financements.
* Un financement peut avoir été annulé, et il peut donc y avoir des Payment orphelins.

#### Règles

* un Payment est toujours créé à partir d’une **BankStatementLine**
* un Payment est toujours lié à un **Funding** (réconciliation auto ou manuelle)
* Une ligne d’extrait peut être décomposée en **plusieurs Payments**
* Inversement, plusieurs lignes d’extrait / paiements peuvent solder un même Funding

#### Workflow

1. Création d’un Payment (automatique ou manuelle)
2. Attribution à un Funding
3. Validation → rattachement à une écriture comptable
4. En cas d’annulation de Funding → Payments détachés et réaffectables

#### À la création du paiement

- Un `Payment` est toujours créé **à partir d’un extrait bancaire**;
- Il est initialement toujours lié à un Funding via réconciliation (auto ou manuelle);
- lorsqu'il est réconcilié, il est **rattaché à une écriture comptable**;
- La direction dépend du signe du montant (positif = réception, négatif = dépense)

#### Création d’un Funding

Lors de la création d'un nouveau financement, tous les `Payments` orphelins ou en crédit disponibles pour le copropriétaire sont **réaffectés automatiquement** au nouveau `Funding` (leur champ `funding_id` est mis à jour).

#### En cas d’annulation d’un Funding

- Tous les `Payments` liés sont **détachés** (`funding_id = NULL`), et peuvent alors être **réaffectés** manuellement ou automatiquement à un autre `Funding` actif (par défaut, au premier `Funding` non totalement payé)

### Logique entre Financements (`Funding`) et écritures comptables (`AccountingEntry`)

La création d'un Funding est toujours une action conséquente de la création d'une opération comptable : émission d'une facture de vente (ou assimilée), validation d'une facture d'achat, transfert de fonds d'un compte à un autre, remboursement, ...

L'opération comptable en question a généré des écritures.

Le financement sert de "collecteur de paiements" pour apurer ces écritures.
Dans d'autres cas, on fait une action qui aboutira à des écritures (assimilables à des OD).

Dans tous les cas, le Financement est en lien avec une pièce à laquelle correspond des écritures.

#### Principe

* **1 BankStatementLine = 1 AccountingEntry** (journal Banque)

* Chaque écriture contient :
  
  * Ligne Banque (550)
  * Contrepartie (400, 440, 6xx, 7xx…)

* Les Payments ventilent le lien vers les Fundings → lettrage partiel possible

#### Réconciliation

On fait en sorte de mettre le système dans une situation cohérente - où on a des extraits bancaires qui respectent un format standard (même dans le cas d'encodage manuel).

* réconcilier signifie "savoir comment on va faire les écritures dans la comptabilité"

* le lettrage correspond au rapprochement  entre une ligne d'extrait et une écriture comptable
  
  * cela permet de retrouver quelle est la ligne d'écriture comptable qui est apurée par la ligne d'extrait
  * 1 ligne d'extrait = 1 écriture comptable (avec 1 + nb paiements)  
  * note : il peut y avoir plusieurs lignes d'extrait qui apurent une même ligne d'écriture comptable (via des funding différents).

* ce sont les Funding qui permettent de savoir comment réaliser les écritures
  
  * => on fait en sorte qu'une ligne d'extrait soit toujours rattachée à un Funding

* pour les mouvements non attendus (e.g. bank fees), on créée un Funding au moment de la réconciliation : funding_type = misc
    (une indication du compte à utiliser peut être fournie manuellement par l'utilisateur)

* une ligne d'extrait est réconciliée et prête à être postée si la somme des paiements qui lui sont liés correspond à son montant 

* une ligne peut être liée à plusieurs paiements et, par conséquent, à plusieurs Funding
    Ex. un copropriétaire qui paie un montant qui couvre plusieurs appels de fonds, ou qui fait un seul paiement couvrant provisions et appels de fonds.
    Dans ces situations, la ligne doit être décomposée en plusieurs paiements (pour être liée à plusieurs Funding).

* Les actions suivantes sont possibles sur un extrait :  
  
  * attempt_reconcile
  * post (si is_reconciled)

* lorsqu'un extrait bancaire est "posted", on fait un refresh_status pour tous les fundings impactés

#### Types d'encodage

* Situation 1 : Une ligne avec une communication qui correspond à un match (un financement en attente de paiement)
  
      -> création automatique du Payment (brouillon)

* Situation 2 : Une ligne sans communication mais avec un montant attendu parmi les financements
  
      -> sur base des infos de la ligne, un compte de destination peut être associé, il est alors utilisé pour filtrer les Fundings existants et permettre la sélection

* Situation 3 : Une ligne (avec ou sans communication), mais pour un mouvement non attendu
  
      -> l'utilisateur sélectionne le compte de destination, un funding et un paiement sont créés (comme si on attendait le mouvement, mais sans écriture préalable de contrepartie)

#### Cas particuliers

* **Transferts internes & remboursements** :
  
  * Funding spécifique créé
  * Écriture générée seulement à la réception de l’extrait bancaire

* **Mouvements inattendus (frais bancaires, charges)** :
  
  * Funding `misc` créé lors de la réconciliation
  * L’utilisateur indique le compte comptable (6/7) et la TVA si applicable

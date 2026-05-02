## Extraits bancaires (`BankStatement`)

Un relevé bancaire est considéré comme une pièce comptable (mais n'en est pas réelelment une) et suit un process similaire à l’import et au traitement des autres types de documents.

### Encodage des extraits bancaires

#### 1. Concepts clés

* **Funding** : représente un mouvement **attendu** (ex. facture client ou fournisseur).
* **Payment** : opération métier qui relie une ou plusieurs **lignes d’extrait bancaire** à un **Funding**, ou directement à un **compte comptable** (si non attendu).
* **BankStatement** : relevé bancaire regroupant plusieurs lignes.
* **BankStatementLine** : mouvement bancaire réel (crédit/débit). C’est l’élément qui déclenche l’**écriture comptable**.
* **AccountingEntry** : écriture comptable validée, générée **lors du “post” du relevé**.

#### 2. Logique métier

##### Mouvement attendu (`Funding`)

* Exemple : facture client/fournisseur.
* La ligne bancaire est rapprochée automatiquement (via communication) ou manuellement à un Funding.
* Si paiement partiel ou multiple → utilisation de *Payments* pour ventiler.
* Écriture :
  * Encaissement client : **Débit Banque / Crédit Clients**
  * Paiement fournisseur : **Débit Fournisseurs / Crédit Banque**

##### Mouvement non attendu

* Exemple : indemnité d’assurance, frais bancaires.
* La ligne est affectée directement à un **compte comptable** (pas de Funding).
* Si compte en classe 6/7 → déclenche la **répartition copropriétaires** (clé, ventilation, privatif).
* Écriture :
  * Frais bancaires : **Débit 627 Frais bancaires / Crédit Banque**

##### Règles communes

* Un *Payment* est soit lié à un **Funding**, soit à un **compte comptable**.
* Une *BankStatementLine* peut être ventilée sur plusieurs *Payments*.
* Un *Funding* peut être soldé par plusieurs *Payments*.

#### 3. Réconciliation

* **Automatique** : tentative via la communication bancaire (réf. facture, montant, etc.).
* **Manuelle** : sélection explicite d’un Funding ou d’un compte comptable si l’auto-match a échoué.
* Une ligne est **reconciled** si le montant total des Payments associés = montant de la ligne.
* Un relevé est **is_reconciled** si toutes ses lignes sont réconciliées (ou ignorées).

#### 4. Comptabilisation (Posting)

1. Condition : le relevé doit être à la fois **is_balanced** (somme des lignes = delta soldes) et **is_reconciled**.
2. Action `post` sur le relevé :
   * Génération d’une **AccountingEntry par ligne d’extrait**.
   * Écritures respectent le **signe du montant** :
     * Montant positif : **Débit Banque / Crédit Contrepartie**
     * Montant négatif : **Débit Contrepartie / Crédit Banque**
   * Contrepartie déterminée par priorité :
     1. Funding (compte client/fournisseur)
     2. Payment → `accounting_account_id`
     3. Fallback → compte “Clients” (`trade_debtors`) ou autre compte par défaut.
3. Chaque ligne est marquée avec son `accounting_entry_id` pour éviter les doublons.

#### 5. Cas particuliers

* **Paiement partiel** : un Funding reste “ouvert” tant que la somme des Payments ne couvre pas le solde attendu.
* **Virement global** : une ligne bancaire peut être ventilée sur plusieurs Fundings via plusieurs Payments.
* **Écarts** : si la somme Payment ≠ Funding (ex. 0,05 € manquant), on peut affecter l’écart à un compte spécifique (ex. 658).
* **Ignored** : une ligne peut être ignorée (pas d’écriture) mais bloque la réconciliation tant qu’elle n’est pas explicitement marquée comme telle.

#### 6. Vue développeur

* **BankStatementLine** → déclenche `doGenerateAccountingEntry()` au moment du post.
* **Payments** :
  * contiennent le lien vers Funding ou `accounting_account_id`.
  * garantissent la ventilation entre plusieurs lignes / fundings.
* **AccountingEntry** :
  * créé avec :
    * `condo_id`, `entry_date`, `fiscal_year_id`, `fiscal_period_id`, `journal_id`
    * `origin_object_class = BankStatement`
    * `origin_object_id = <statement_id>`
  * lignes d’écritures : Banque vs Contrepartie (selon signe).

#### 7. Récapitulatif visuel (simplifié)

```
Funding (attendu)  <--->  Payment  <--->  BankStatementLine  -->  AccountingEntry
                                |
                                v
                        Compte comptable direct (si non attendu)
```

### Import des extraits

Les relevés bancaires peuvent provenir :

- d’un **relevé unique** lié à un seul compte,
- ou d’un **relevé global** (ex. fichier ISABEL) contenant plusieurs extraits, chacun lié à un compte distinct.

L’import se fait en deux étapes :

1. Création d’un objet `BankStatementImport` pour encapsuler l’ensemble des extraits.
2. Pour chaque extrait détecté :
   - Un objet `BankStatement` est généré,
   - Il est associé à un `DocumentProcess`,
   - Un fichier virtuel (XLSX) est généré pour la traçabilité.

### Traitement des lignes d’extrait (`BankStatementLine`)

Chaque `BankStatement` contient une ou plusieurs lignes représentant les opérations bancaires (entrées/sorties de fonds). Chaque ligne peut être marquée comme :

- **ignorée** (non pertinente),
- **à rembourser** (ex. erreur, trop-perçu),
- **réconciliée** (traitée).

#### Réconciliation

Une ligne est considérée comme **réconciliée** lorsque :

- Elle est associée à un ou plusieurs **paiements** (objets `Payment`),
- Ces paiements couvrent **exactement** le montant de la ligne d’extrait (versement réel),
- Chaque paiement est lié à un ou plusieurs **financements** (`Funding`) représentant des montants attendus.

Les paiements créés automatiquement sont initialement en **statut "brouillon"** (`proforma`).

### Actions possibles

#### Réconciliation manuelle ou automatique

L'action `reconcile` sur un `BankStatement` tente :

- Une **réconciliation automatique** ligne par ligne (si possible),
- Puis déclenche l’**enregistrement comptable** de l’extrait s’il est totalement réconcilié.

#### Enregistrement comptable (post)

L’action `post` d’un `BankStatement` :

- Est uniquement possible si **toutes les lignes** sont réconciliées,
- **Valide** les paiements associés (changement d’état),
- **Génère les écritures comptables** correspondantes à partir des paiements.

### Impact sur les financements (`Funding`)

Lorsqu’un **paiement est publié** :

- Le financement (`Funding`) auquel il est lié est **mis à jour** (changement d’état éventuel),
- Une **tentative d’enregistrement automatique** du financement est lancée,
  - Ce processus vise à **générer les écritures comptables** liées à la pièce d’origine du financement (facture, échéancier, etc.).

## Transfert bancaire interne (`MoneyTransfer`)

Le transfert de fonds entre deux **comptes bancaires** d’une copropriété est géré via l’objet `MoneyTransfer`. Il s’agit d’un **type spécialisé d’`operation diverse` (`MiscOperation`)**, conçu pour modéliser les **mouvements internes** entre comptes bancaires de la même copropriété, et qui implique la création d'écritures comptables à deux moments : 

1) lors de la création de la demande du transfert (et de l'envoi du SEPA correspondant)
2) lors de la réception de l'extrait bancaire qui acte le transfert au niveau de la banque

Un `MoneyTransfer` associe :

* un **compte source** (`bank_account_id`)
* un **compte de destination** (`counterpart_bank_account_id`)
* une **copropriété** (`condo_id`)
* un **montant** (`amount`)
* une **date comptable** (`posting_date`), généralement la date du jour

### Validation d'un transfert

Un transfert n'est valide (et autorisé) que s'il y a assez d'argent sur le compte d'origine.
Le montant disponible est identifié sur base de la balance courante du compte comptable associé (montant au débit).

### Déroulement comptable en deux temps

#### **1. Demande de transfert (ordre de virement)**

Lorsqu’un transfert est initié (manuellement ou via SEPA), une première écriture comptable est générée :

* le **compte source est crédité**
* un **compte transitoire `58xxxx` (bank\_transfer)** est débité

```plaintext
DEBIT   58xxxx - Compte transitoire "Virements internes"
CREDIT  512xxx - Compte bancaire source
```

> Cela permet de réduire immédiatement la disponibilité du compte source, même si le virement n’est pas encore effectif. Le montant est alors considéré comme "en transit".

#### **2. Réception du virement (encodage de l’extrait)**

Quand le virement est visible sur l’extrait du compte de destination, une seconde écriture est générée :

* le **compte de destination est débité**
* le **compte de virement interne est crédité**

```plaintext
DEBIT   512yyy - Compte bancaire destination
CREDIT  58xxxx - Compte transitoire "Virements internes"
```

> L’opération est alors **soldée**, le virement est visible des deux côtés.

### Réconciliation et suivi

#### Lien entre`Funding` et `MoneyTransfer`

Le suivi des opérations de versement est réalisé avec des financements (Funding).

Les deux `Funding` générées (demande et réception) référencent chacun l'objet `MoneyTransfer` d'origine, auquel ils sont liés.

Cela permet de suivre l’état de complétion (`is_complete`) et de vérifier que l'extrait bancaire a bien été comptabilisé.

#### Lien entre`Funding` et `MoneyTransfer`

``` Bank Statement > Bank Statement Line > Funding > {accounting document} > AccountingEntry```

Pour référencer une pièce comptable, une entrée comptable utilise les deux champs: 

`origin_object_class` and `origin_object_id`

Ceci est utilisé quel que soit la pièce liée : misc operation (OD); purchase invoice; expense statement; fund request.

### Lien entre`Funding` et export SEPA

Un `MoneyTransfer` peut être **lié à un `Funding`** lorsqu’il représente :

* une **demande de paiement SEPA** (ex. virement vers un autre compte)
* une **instruction métier validée** (ex. mouvement autorisé par l’AG)

Dans ce cas :

* le `Funding` permet de déclencher l’export SEPA (pain.001)
* l’état du `Funding` évolue au fil du traitement (créé, exporté, exécuté)
* l’objet `MoneyTransfer` devient la **traduction comptable du `Funding`**

### Calcul du solde disponible d’un compte bancaire

Pour éviter les erreurs (ex. : réinitier un transfert alors que des fonds sont déjà engagés), le solde disponible d’un compte est calculé comme suit :

```php
availableBalance = current_account_balance
                 - sum(pending outgoing Fundings)
                 + sum(pending incoming Fundings)
```

Ce champ calculé (`available_balance`) permet de :

* tenir compte du solde réel (`AccountBalance`)
* déduire les Fundings en attente d'exécution
* informer l'utilisateur du montant effectivement mobilisable

### Règles de validation avant transfert

Avant validation (`posted`), les conditions suivantes sont vérifiées :

* les deux comptes bancaires doivent appartenir à la même copropriété
* le montant doit être > 0
* la date comptable doit être définie
* le compte source doit disposer d’un solde suffisant (via `CurrentBalanceLine`)
* un compte comptable de type `bank_transfer` doit être défini

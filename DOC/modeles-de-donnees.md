# Modèle de Données pour la Gestion des ACP

Cette page centralise les entités métier et leurs règles de gestion. L'objectif est de conserver l'exhaustivité du modèle tout en facilitant son usage comme référence transversale.

## Gestion des Biens et des Propriétaires

### CommonArea

Représente les parties communes d'un immeuble en copropriété (ACP). Utilisé pour répartir les charges entre les copropriétaires selon des clés de répartition.

### Condominium

Désigne une copropriété ou un ensemble de biens immobiliers gérés collectivement. Contient les données essentielles à la gestion de l’ACP.

### CondoExternalRef

Référence externe associée à un immeuble en copropriété, permettant de lier les données à des sources ou systèmes tiers.

### Propriété (`Ownership`)

Définit les parts de propriété dans l’ACP. Sert à calculer les quotités et les obligations financières des copropriétaires.

Un **copropriétaire** peut être :

- Une **personne individuelle**.
- Un **groupe de personnes en indivision** (ex. héritiers, associés).
- Un **titulaire d’une partie démembrée de la propriété** (usufruitier ou nu-propriétaire).
- **Représenté par une autre personne** dans certaines situations (ex. mandataire, tuteur légal).

**Définition de la propriété en copropriété**  
Une **propriété** représente le **lien juridique** entre :

1. **Un bien immobilier** (appartement, cave, garage, etc.).
2. **Une ou plusieurs personnes** détenant des parts de propriété sur ce bien.

**Règles de gestion des propriétés :**

- Une **propriété ne peut jamais être supprimée** (sauf si aucune écriture ne la concerne).
- Les changements de copropriétaires se font par le biais de **mutations** (transfert de propriété : vente, donation, succession…).

**Démembrement d'une propriété**  
En copropriété, la propriété d’un lot peut être **démembrée** en **nue-propriété et usufruit**.

- **Propriétaires en pleine propriété**  
  Détiennent directement leur part sur la copropriété, avec tous les droits et devoirs associés.

- **Nus-propriétaires**  
  Possèdent un droit de propriété, mais ne peuvent pas utiliser ou louer le bien tant que l’usufruit existe.  
  Doivent payer les grosses réparations du bien (ravalement, toiture…).  
  À l’extinction de l’usufruit (ex. décès de l’usufruitier), ils récupèrent automatiquement la pleine propriété.

- **Usufruitiers**  
  Ont le droit d’occuper le bien ou de le louer et d’en percevoir les revenus (ex. loyers).  
  Doivent assumer les charges courantes (entretien, eau, chauffage…).  
  Ne peuvent pas vendre le bien sans l’accord des nus-propriétaires.

**Exemple de répartition :**

| **Propriétaire**            | **Pleine propriété (%)** | **Usufruit (%)** | **Nue-propriété (%)** |
| --------------------------- | ------------------------ | ---------------- | --------------------- |
| **Propriétaire 1**          | 25%                      | -                | -                     |
| **Propriétaire 2**          | 25%                      | -                | -                     |
| **Propriétaire 3**          | 25%                      | -                | -                     |
| **Propriétaire 4**          | -                        | **25%**          | -                     |
| **Enfant 1 Propriétaire 4** | -                        | -                | **10%**               |
| **Enfant 2 Propriétaire 4** | -                        | -                | **15%**               |
| **Total**                   | **75%**                  | **25%**          | **25%**               |

### Propriétaire (`Owner`)

Représente un copropriétaire, avec ses informations personnelles et sa part de propriété dans l’ACP.

Les parts sont assignées en fonction des droits sur la propriété. Les droits et devoirs découlent à la fois des parts et des titres de propriétés.

| **Droit / Devoir**                      | **Plein propriétaire** | **Usufruitier**                    | **Nu-propriétaire**                                                            |
| --------------------------------------- | ---------------------- | ---------------------------------- | ------------------------------------------------------------------------------ |
| **Droit de vote en assemblée générale** | ✅ Oui                 | ✅ Oui (pour la gestion courante)  | ✅ Oui (pour travaux lourds et décisions impactant la structure du bien)      |
| **Charges & entretien courantes**       | ✅ Oui                 | ✅ Oui (entretien, eau, chauffage…) | ❌ Non                                                                         |
| **Grosses réparations**                 | ✅ Oui                 | ❌ Non                              | ✅ Oui                                                                         |

### PropertyLot

Représente un lot privatif appartenant à un copropriétaire dans une copropriété. Utilisé pour le calcul des charges.

### PropertyLotNature

Décrit la nature d’un lot dans une copropriété (ex. appartement, garage, cave), ce qui influence la répartition des charges.

### OwnershipTransfer

Gère les transferts de propriété entre copropriétaires et l’impact sur la répartition des charges et des droits de vote.

| Nom du champ                    | Type      | Objet lié (le cas échéant)        | Obligatoire | Description                                                                         |
| ------------------------------- | --------- | --------------------------------- | ----------- | ----------------------------------------------------------------------------------- |
| `condo_id`                      | many2one  | `realestate\property\Condominium` | ✅           | Copropriété à laquelle appartient le lot.                                           |
| `property_lot_id`               | many2one  | `realestate\property\PropertyLot` |             | Lot principal concerné par le transfert.                                            |
| `property_lots_ids`             | many2many | `realestate\property\PropertyLot` |             | Lots de propriété supplémentaires associés au transfert.                            |
| `old_ownership_id`              | many2one  | `realestate\ownership\Ownership`  |             | Ancienne détention (propriétaire actuel).                                           |
| `new_ownership_id`              | many2one  | `realestate\ownership\Ownership`  |             | Nouvelle détention (nouveau propriétaire, si déjà encodé).                         |
| `is_existing_new_ownership`     | boolean   | —                                 |             | Indique si la nouvelle détention existe déjà.                                       |
| `request_date`                  | date      | —                                 |             | Date de réception de la demande initiale du notaire.                                |
| `confirmation_date`             | date      | —                                 |             | Date de réception de la confirmation du notaire.                                    |
| `transfer_date`                 | date      | —                                 |             | Date prévue pour l’acte de vente. Doit correspondre à la date de l’acte notarié.    |
| `seller_documents_sent_date`    | date      | —                                 |             | Date d’envoi des documents de cession au notaire.                                   |
| `financial_statement_sent_date` | date      | —                                 |             | Date d’envoi du relevé financier au notaire.                                        |
| `description`                   | string    | —                                 |             | Description libre du transfert de propriété.                                         |
| `status`                        | string    | —                                 |             | Statut du transfert : `draft`, `open`, `confirmed`, `accounting_pending`, `closed`. |

## Clés de Répartition et Gestion des Charges

### Clé de répartition (`Apportionment`)

Clé de répartition des charges dans une copropriété, définissant comment sont réparties les dépenses communes.

Une distinction est faite entre les clés de répartition et les clés statutaires (`is_statutory`), mais les deux types d'objets sont compatibles et il est toujours possible de créer une nouvelle clé de répartition à partir d'une clé statutaire.

Permettent d'associer un groupe de lots à un mode de comptabilisation de charges.

- Ces clés sont définies de manière statutaire.
- Les comptes comptables peuvent être assignés à des clés par défaut.

### CommonAreaType

Catégorie de partie commune, utile pour classifier et ventiler les charges selon leur usage.

## Gestion des Acteurs

### Identity

Représente une entité (personne ou organisation) impliquée dans la gestion de l’ACP.

### Mandate

Désigne un mandat donné à une personne ou une entreprise pour gérer certains aspects de la copropriété (ex. syndic).

### RoleAssignment

Attribue un rôle à une personne ou entité dans l’ACP (ex. président du conseil de copropriété, syndic, etc.).

### Role

Définit un rôle au sein de l’organisation de l’ACP.

### Employee

Un employé du syndic ou de l’ACP.

### Organisation

Représente une entreprise ou une entité légale, comme un syndic de copropriété.

### Group

Un regroupement d’utilisateurs ou d’entités au sein de la gestion de l’ACP.

### Supplier

### Suppliership

### SupplierType

##### Entretien & Maintenance courante (`maintenance`)

- `cleaning` : Nettoyage des communs
- `green` : Entretien des espaces verts
- `lift` : Entretien des ascenseurs
- `heating` : Entretien des chaudières / chaufferie
- `pest_control` : Dératisation / désinsectisation
- `sewage` : Vidange fosse septique / égouts
- `ventilation` : Maintenance ventilation / aération

##### Fournisseurs d'énergie, fluides & télécommunications (`utilities`)

- `electricity` : Électricité (communs, chaufferie, etc.)
- `gas` : Gaz (chauffage commun)
- `water` : Eau (commune ou individuelle refacturée)
- `telecom` : Fournisseurs Internet / TV / téléphonie (antennes, fibre, etc.)

##### Travaux & Projets techniques (`works`)

- `contractor` : Entrepreneurs en bâtiment (gros œuvre, toiture, façade…)
- `renovation` : Sociétés de rénovation énergétique
- `specialist` : Peintres, carreleurs, menuisiers, plombiers, électriciens, chauffagistes
- `architect` : Architectes, bureaux d’études
- `surveyor` : Géomètres
- `energy_cert` : Certificateurs PEB / audits énergétiques

##### Fourniture d’équipements & matériel (`equipment`)

- `common_supply` : Fourniture d’extincteurs, éclairage, mobilier commun
- `security` : Interphones / systèmes de sécurité
- `signage` : Boîtes aux lettres, signalétique
- `domotic` : Matériel domotique ou smart building

##### Services professionnels & gestion (`services`)

- `syndic` : Syndic professionnel
- `accounting` : Cabinet comptable / Expert-comptable
- `auditor` : Réviseur / commissaire aux comptes
- `legal` : Avocat / juriste
- `notary` : Notaire (actes de vente de communs, statuts, etc.)

##### Fournisseurs financiers & services bancaires (`finance`)

- `bank` : Banque (tenue de compte, frais bancaires, crédit ACP)
- `insurance` : Assurance (bâtiment, RC de l’ACP, protection juridique)

##### Mesures & répartition des consommations (`metering`)

- `allocator` : Tiers répartiteurs (Techem, Isat…)
- `meter_reading` : Sociétés de relevé de compteurs (eau, chauffage, etc.)

## Comptes Bancaires et Paiements

### BankAccount

Compte bancaire associé à l’ACP, utilisé pour la gestion des flux financiers.

### BankStatement

Relevé bancaire permettant le suivi des transactions financières de l’ACP.

### BankStatementLine

Ligne d’un relevé bancaire, représentant un mouvement financier.

### BankTransfer

Opération de transfert bancaire, souvent pour les paiements de fournisseurs ou les remboursements.

### FundRequest

Demande de fonds pour couvrir une dépense spécifique.

### FundRequestEntry / FundRequestLine

Détail d’une demande de fonds, avec le montant et l’affectation comptable.

### RefundRequest

Demande de remboursement pour une avance ou une correction comptable.

### Financements (`Funding`)

| Champ             | Type       | Description                          |
| ----------------- | ---------- | ------------------------------------ |
| `id`              | `int`      | Identifiant du Funding               |
| `owner_id`        | `many2one` | Copropriétaire concerné              |
| `expected_amount` | `float`    | Montant attendu                      |
| `status`          | `string`   | Statut du Funding                    |
| `is_cancelled`    | `boolean`  | Funding annulé ou non                |
| `due_date`        | `date`     | Date d’échéance                      |
| `document_id`     | `many2one` | Pièce d’origine (décompte, appel...) |

#### Status

| Valeur           | Description                          |
| ---------------- | ------------------------------------ |
| `pending`        | Aucun paiement affecté               |
| `debit_balance`  | Paiement partiel                     |
| `balanced`       | Montant payé exactement              |
| `credit_balance` | Trop-perçu par rapport au montant dû |

### Paiement (`Payment`)

| Champ                 | Type       | Description                              |
| --------------------- | ---------- | ---------------------------------------- |
| `id`                  | `int`      | Identifiant du paiement                  |
| `owner_id`            | `many2one` | Copropriétaire                           |
| `amount`              | `float`    | Montant du paiement (positif ou négatif) |
| `funding_id`          | `many2one` | Funding associé (nullable)               |
| `status`              | `string`   | `credit`, `allocated`, `orphan`          |
| `bank_statement_id`   | `many2one` | Référence à l’extrait bancaire           |
| `accounting_entry_id` | `many2one` | Écriture comptable associée              |

## Comptabilité et Budgétisation

### AccountingEntry

Écriture comptable enregistrant un mouvement financier.

### AccountingEntryLine

Ligne détaillant une écriture comptable.

### AccountingJournal

Journal comptable où sont enregistrées les écritures.

### AccountingRule

Définit une règle comptable applicable aux écritures.

### AccountingRuleLine

Détail d’une règle comptable, précisant les comptes impliqués.

### VatRule

Règle de TVA appliquée aux transactions.

### AccountTemplate

Modèle de compte comptable pour standardiser la comptabilisation des transactions.

### AccountChartTemplate

Modèle de plan comptable pour structurer la gestion financière de l’ACP.

### AccountChart

Plan comptable propre à une copropriété.

### AccountChartLine

Ligne du plan comptable, définissant un compte précis.

### Account

Un compte comptable spécifique utilisé dans la gestion des finances de l’ACP.

### Balance (`Balance > CurrentBalance`)

Représente le solde d’un compte comptable, utile pour suivre la situation financière de l’ACP.

Une entité supplémentaire `BalanceUpdateRequest` est utilisée pour garantir que les mises à jour de la balance soient séquentielles et atomiques, en évitant les problème de concurrence.

### FiscalYear

Exercice comptable d’une copropriété.

### CondominiumBudget

Budget annuel de l’ACP, détaillant les dépenses et les prévisions financières.

### CondominiumBudgetEntry

Ligne d’un budget de copropriété, détaillant une dépense ou une recette planifiée.

## Facturation et Gestion des Fournisseurs

### Facture (`Invoice`)

Facture reçue ou émise par l’ACP.

- référence interne: `{acp}-{année exercice}-{pièce n°}`
- référence externe (n° facture fournisseur)
- date échéance: date limite de paiement au fournisseur

=> affichage de la pièce jointe (possibilité de mettre à jour la pièce jointe)

=> possibilité de créer une facture sans pièce jointe (avec confirmation et rappel / avertissement)

=> possibilité de lier une facture à des travaux spécifiques (`FacilityWork`)

### InvoiceLine

Ligne d’une facture, détaillant un service ou une charge.

### SupplierContract

Contrat établi avec un fournisseur pour la prestation de services à l’ACP.

### SupplierRef

Référence d’un fournisseur dans le système de gestion de l’ACP.

## Assemblées Générales et Votes

### GeneralAssembly

Assemblée générale des copropriétaires.

### AssemblyNotice

Convocation à une assemblée générale.

### AssemblyResolution

Résolution adoptée lors d’une assemblée générale.

### ResolutionTemplate

Modèle de résolution utilisé pour uniformiser les décisions.

### AssemblyMinutes

Procès-verbal d’une assemblée générale.

### AssemblyMinutesEntry

Entrée spécifique dans un procès-verbal.

### AssemblyQuorum

Quorum requis pour valider une assemblée générale.

### QuorumEntry

Entrée de calcul du quorum.

### ResolutionCategory

Catégorisation des résolutions adoptées.

### AssemblyAttendance

Suivi des présences lors d’une assemblée.

### AssemblyResolutionVote

Vote exprimé sur une résolution.

## Tarification et Services Additionnels

### Product

Service ou produit vendu aux copropriétaires.

Les produits sont essentiellement destinés aux prestations complémentaires. Le catalogue principal se compose des éléments suivants :

- `Product`
- `Price`
- (`ProductModel` est prévu mais ne sera sans doute pas nécessaire)

La distinction entre le catalogue général et ceux spécifiques aux ACP se fait uniquement sur les `Price` et `PriceList`, pour lesquels un `condo_id` peut être renseigné (un `condo_id` null correspond au catalogue "Template").

### ProductModel

Modèle de produit/service standardisé.

### PriceList

Liste de prix appliqués aux services ou aux prestations liées à l’ACP.

Un syndic peut avoir une liste de prix "Template", qui peut être mise à jour à tout moment.

Chaque ACP gérée peut avoir une liste de prix qui lui est spécifique (pour tous les produits du catalogue). Un taux d'indexation est renseigné dans le contrat.

### Price

Tarif appliqué à un service ou un produit.

### ExtraServices

Services additionnels proposés dans le cadre de la gestion de la copropriété.

## Gestion Locative et Relations avec les Locataires

### Tenancy

Contrat de location d’un lot.

### TenancyTransfer

Transfert d’un contrat de location.

### Property

Bien immobilier, en général un lot privatif ou une partie commune.

### Tenant

Locataire d’un bien dans la copropriété.

## Gestion des Accès et Permissions

### Permission

Droit d’accès et d’action sur certaines fonctionnalités du système.

### User (externe)

Utilisateur externe du système.

### Team

Groupe de travail au sein de la gestion de l’ACP.

## Gestion du Suivi et des Tâches

### Tâche (`Task`)

Les tâches permettent de lister :

- les actions requises
- les choses en attente d'une autre partie (pour relance éventuelle)

Structure qui permet de référencer des entités qui impliquent une action.

`DATE`, `TYPE`, `ENTITY_REF`, `ACP`, `WAITING_FOR` (,`DONE_BY`)

Entities : `GeneralAssembly`, `Invoice`, `FacilityWork`

=> lignes classées par ordre de priorité, possibilité de classer par ACP (champ de filtre)

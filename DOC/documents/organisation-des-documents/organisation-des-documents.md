# Organisation des documents

L’organisation des documents fournit une **vue hiérarchique et navigable** des documents associés à une copropriété.
Elle est destinée à faciliter la **consultation humaine**, sans jamais constituer une source de vérité fonctionnelle.

Dans le logiciel, **les documents ne sont jamais identifiés, traités ou recherchés sur base de leur emplacement** dans une arborescence, mais uniquement via :

* leurs entités métier associées,
* leurs métadonnées,
* leur type (`DocumentType`).

L’arborescence documentaire est donc une **projection**, pas un mécanisme métier.


## Principe général

Les documents sont organisés dans une structure arborescente, comparable à un système de dossiers, appelée **EDMS** (*Electronic Document Management System*).

Cette organisation repose sur une entité unique : **`Node`**.

Un `Node` peut représenter :

* soit un **dossier logique**,
* soit un **document** rattaché à un dossier.

Chaque copropriété dispose de sa **propre arborescence**, indépendante des autres.


## Arborescence par copropriété

Lors de la création d’une copropriété (`Condominium`), une **arborescence par défaut** est automatiquement générée.

Cette arborescence :

* sert de modèle initial,
* peut évoluer dans le temps,
* est utilisée pour rattacher automatiquement les documents selon leur nature.

Dans le cas d’un **transfert de copropriété**, les documents existants peuvent être repris et rattachés à la nouvelle arborescence.

---

## Codes de dossiers et rôle programmatique

Chaque dossier système est identifié par un **code** (`code`) unique dans le contexte d’une copropriété.

Ce code permet :

* d’identifier le **rôle fonctionnel** du dossier,
* de rattacher automatiquement un document à un dossier lors de sa génération ou de son import,
* de garantir une organisation cohérente, même si les noms visibles évoluent.

⚠️ Important :
Le code d’un dossier **n’est jamais utilisé pour la recherche métier** des documents.
Il sert uniquement à leur **classement visuel**.

---

## Arborescence standard (exemple)

L’arborescence suivante illustre une organisation typique par copropriété (liste indicative):

| Code dossier                       | Usage principal                                    |
| ---------------------------------- | -------------------------------------------------- |
| `general_meetings`                 | Assemblées générales (convocations, PV, présences) |
| `council_minutes`                  | Conseils de copropriété                            |
| `tender_documents`                 | Devis et appels d’offres                           |
| `maintenance_logs`                 | Rapports d’entretien                               |
| `works_and_repairs`                | Travaux et interventions                           |
| `supplier_invoices`                | Factures et notes de crédit fournisseurs           |
| `bank_statements`                  | Relevés bancaires                                  |
| `operation_statements`             | États de charges, appels de fonds                  |
| `contracts` / `supplier_contracts` | Contrats fournisseurs                              |
| `insurance_contracts`              | Contrats et attestations d’assurance               |
| `legal_followup`                   | Contentieux et documents juridiques                |
| `justifications`                   | Pièces justificatives                              |
| `internal_notes`                   | Notes internes, PV produits                        |
| `ownership_transfers`              | Documents liés aux mutations                       |
| `imports`                          | Fichiers d’import temporaires                      |


Le **code** reste la référence fonctionnelle, pas l’intitulé.


## Rattachement automatique des documents

Lorsqu’un document est :

* importé,
* ou généré automatiquement,

il est **rattaché par défaut** à un dossier logique en fonction :

* de son `DocumentType`,
* et du `folder_code` associé à ce type.

Ce rattachement est automatique et cohérent, mais **reste secondaire** par rapport aux liens métier du document.



## L’entité `Node`

L’EDMS repose sur l’entité `Node`, qui permet de représenter :

* une arborescence hiérarchique,
* des dossiers et des documents dans une structure unique.

### Types de nœuds

Un `Node` peut être de deux types :

* **`folder`**
  Représente un dossier logique, pouvant contenir d’autres nœuds.

* **`document`**
  Représente un document précis, via un lien vers une entité `Document`.

Un nœud document référence **exactement un document**, identifié de manière unique.



## Identité et accès aux documents

Chaque document est identifié par un **UUID**, indépendant de son emplacement dans l’arborescence.

Cet identifiant est utilisé :

* pour les appels API,
* pour l’accès direct au document,
* pour les échanges avec l’EDMS.

👉 L’URL d’accès à un document est donc **stable**, même si son emplacement change.



## Visibilité et droits d’accès

Chaque nœud possède un niveau de visibilité :

* `public` : visible par tous les copropriétaires et le syndic,
* `protected` : visible uniquement par le syndic,
* `private` : visible par un copropriétaire spécifique et le syndic.

La visibilité :

* est **héritée par les nœuds enfants**,
* est synchronisée avec la visibilité du document associé,
* se propage automatiquement en cas de modification.



## Dossiers système

Certains dossiers sont marqués comme **système** (`is_system = true`) :

* ils sont créés automatiquement,
* leur structure ne peut pas être modifiée manuellement,
* ils garantissent la stabilité de l’organisation documentaire.

Ces dossiers assurent une cohérence globale, même en cas d’évolution du modèle.



## Comptage et navigation

Chaque dossier maintient un **compteur de documents**, calculé dynamiquement sur l’ensemble de ses descendants.

Ce mécanisme permet :

* une navigation rapide,
* une vision synthétique du contenu,
* sans dépendre de la profondeur réelle de l’arborescence.


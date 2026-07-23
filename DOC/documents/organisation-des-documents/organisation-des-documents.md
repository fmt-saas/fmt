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
* de déterminer la **visibilité par défaut** du dossier,
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

La visibilité EDMS est un mécanisme de **permissions de lecture** basé sur les rôles et les liens métier de l’utilisateur courant.

Elle s’applique à deux niveaux :

* les **dossiers** (`navigation\Node` de type `folder`) portent une visibilité de navigation ;
* les **documents** (`Document` et `navigation\Node` de type `document`) portent une visibilité de consultation.

La visibilité ne remplace pas les liens métier. Elle détermine seulement si un utilisateur peut voir le nœud ou consulter le document dans le périmètre auquel il est lié.

### Visibilité des dossiers

Un dossier ne peut avoir qu’une visibilité de navigation générale :

| Visibilité | Portée |
| --- | --- |
| `condo` | Dossier visible par les utilisateurs liés à la copropriété concernée et par l’agence. |
| `agency` | Dossier visible uniquement par l’agence ou le syndic. |

La visibilité par défaut d’un dossier est déterminée sur base de son `code`.

Exemples :

* un dossier destiné aux documents partagés avec la copropriété reçoit par défaut `condo` ;
* un dossier de travail interne, de suivi juridique ou administratif sensible reçoit par défaut `agency`.

Cette valeur par défaut est une aide au classement, pas une règle figée. La visibilité d’un dossier reste modifiable manuellement lorsque le contexte réel le justifie.

### Permissions sur les documents

Un document dispose d’un champ `document_visibility`. Le nœud documentaire correspondant dispose d’un champ `node_visibility`.

Ces deux valeurs doivent rester synchronisées pour les nœuds de type `document`.

Les niveaux utilisés sont :

| Visibilité | Permission de lecture |
| --- | --- |
| `agency` | Collaborateurs de l’agence ou du syndic uniquement. |
| `condo` | Utilisateurs liés à la copropriété concernée, plus l’agence. |
| `ownership` | Utilisateurs liés au dossier de propriété concerné, plus l’agence. |
| `owner` | Propriétaire concerné, plus l’agence. |
| `suppliership` | Fournisseur lié à la copropriété ou fournisseur concerné, plus l’agence. |

La permission effective dépend donc toujours de la visibilité **et** du lien métier renseigné sur le document ou le nœud :

* `condo_id` pour une visibilité `condo` ;
* `ownership_id` pour une visibilité `ownership` ;
* `owner_id` pour une visibilité `owner` ;
* `supplier_id` ou `suppliership_id` pour une visibilité `suppliership`.

Si le lien métier requis est absent, la visibilité ne peut pas être évaluée correctement et le document ne doit pas être rendu visible hors agence.

La visibilité par défaut d’un document est déterminée par son `DocumentType`, puis éventuellement affinée par son `DocumentSubtype`.

Exemples :

* un procès-verbal d’assemblée peut être proposé en `condo` ;
* un appel de fonds individualisé peut être proposé en `ownership` ;
* une pièce fournisseur peut être proposée en `suppliership` ;
* une note interne peut être proposée en `agency`.

Cette valeur par défaut reste modifiable manuellement. L’utilisateur peut corriger la visibilité lors de l’import ou après création du document, pour autant que le contexte métier requis soit renseigné.

### Synchronisation entre `Document` et `Node`

Pour un nœud de type `document`, `node_visibility` et `document_visibility` représentent la même permission de lecture.

Lorsqu’un document est rattaché à l’arborescence EDMS :

1. le document est placé dans le dossier logique déterminé par son type ou sous-type ;
2. un `Node` de type `document` est créé ou retrouvé ;
3. la visibilité du nœud documentaire est alignée avec celle du `Document` ;
4. toute modification manuelle de la visibilité doit maintenir les deux valeurs cohérentes.

Pour les dossiers, la visibilité reste indépendante de celle des documents contenus. Un dossier `condo` peut contenir uniquement les documents effectivement lisibles par l’utilisateur courant ; les documents plus restrictifs restent filtrés par leurs propres permissions.


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

La navigation ne doit pas afficher les dossiers vides.

Dans ce contexte, un dossier est considéré comme vide pour un utilisateur lorsque le nombre de documents lisibles par cet utilisateur dans ce dossier et ses sous-dossiers vaut zéro.

Le comptage utilisé par la navigation doit donc être calculé dans le contexte de l’utilisateur courant, en appliquant les mêmes règles de visibilité que pour les documents :

* les documents `agency` ne comptent que pour les collaborateurs de l’agence ;
* les documents `condo` comptent uniquement pour les utilisateurs liés à la copropriété ;
* les documents `ownership`, `owner` et `suppliership` comptent uniquement si l’utilisateur appartient au périmètre métier concerné.

Ce compteur de navigation peut être distinct d’un compteur technique global. La règle fonctionnelle est que l’utilisateur ne voie jamais un dossier qui ne contient aucun document consultable par lui.

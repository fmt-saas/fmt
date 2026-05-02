# Types de documents

Les **types de documents** (`DocumentType`) constituent le **pivot central** du système documentaire.
Ils permettent de relier un document à sa **finalité métier**, à son **objet cible**, et à l’ensemble des **règles applicables** tout au long de son cycle de vie.

Un `DocumentType` ne décrit donc pas uniquement *ce qu’est* un document, mais surtout **comment il doit être traité**.

---

## Rôle d’un `DocumentType`

Un `DocumentType` permet de déterminer, de manière programmatique :

* la **nature fonctionnelle** d’un document,
* l’**entité métier cible** à laquelle il se rattache,
* le **schéma de données attendu** (`json_schema`),
* les **règles de validation** à appliquer,
* les **règles d’intégration et d’enregistrement**,
* l’**organisation documentaire par défaut** (dossier logique).

Il constitue ainsi le **point d’entrée unique** pour tous les mécanismes de traitement liés à un document.

---

## Lien entre `DocumentType` et entités métier

Un `DocumentType` peut être associé explicitement à une **classe métier cible** via la propriété :

* `object_class`

Cette association permet :

* de savoir **quel type d’objet** doit être créé ou alimenté lors de l’import,
* de router correctement les règles de validation et d’intégration,
* de garantir une cohérence entre le document et son usage métier.

Exemple typique :

* un document de type `invoice` est associé à une entité `PurchaseInvoice`,
* un document de type `bank_statement` est associé à une entité `BankStatement`.

👉 Cette relation est déterminante dans le cadre du `DocumentProcess`.

---

## Organisation documentaire et `folder_code`

Chaque `DocumentType` définit un `folder_code`, qui indique **l’emplacement logique par défaut** des documents de ce type.

Important :

* ce dossier sert uniquement à l’**organisation et à la lisibilité**,
* il ne constitue **jamais un mécanisme fonctionnel**,
* la recherche et l’exploitation des documents ne reposent **jamais** sur les dossiers.

L’organisation par dossier est donc une **conséquence**, pas une règle métier.

---

## Schéma JSON et structure des données

Lorsqu’un `json_schema` est défini pour un `DocumentType`, il sert à :

* valider la structure du `document_json`,
* garantir la présence des champs attendus,
* normaliser les données exploitées par le système.

Ce schéma constitue la **base technique** de la complétude des documents, mais **ne remplace pas la validation métier**, qui intervient ultérieurement via les règles de validation.

---

## Sous-types de documents (`DocumentSubtype`)

Certains types de documents nécessitent une **spécialisation plus fine**, exprimée via des `DocumentSubtype`.

Un sous-type permet notamment :

* d’affiner les règles de validation,
* de différencier les stratégies d’intégration,
* d’adapter les règles d’étiquetage ou de classement.

Les sous-types sont toujours **rattachés à un `DocumentType`** et n’existent jamais de manière autonome.

Exemples courants :

* factures d’acompte, notes de crédit, factures de régularisation,
* procès-verbaux d’assemblées ordinaires ou extraordinaires,
* attestations spécifiques au sein des pièces justificatives.

---

## `DocumentType` et règles associées

Un `DocumentType` peut être associé à plusieurs familles de règles, chacune jouant un rôle distinct :

### Règles de validation (`ValidationRule`)

Définissent les contrôles métier bloquants à appliquer avant toute intégration.

Ces règles sont décrites en détail dans
[`document-validation.md`](../document-processing/document-validation.md).

---

### Règles d’enregistrement (`RecordingRule`)

Définissent comment un document validé est :

* converti en écritures comptables,
* ou transformé en entités opérationnelles.

Elles déterminent notamment :

* les comptes utilisés,
* les mouvements générés,
* les liens entre objets.

---

### Règles d’étiquetage (`LabelingRule`)

Définissent les libellés, catégories ou marqueurs appliqués au document, indépendamment de son traitement comptable ou juridique.

---

## Usage programmatique des `DocumentType`

Grâce à cette structuration, il est possible de :

* déterminer automatiquement **quelles règles appliquer** à un document,
* exécuter les validations appropriées sans logique conditionnelle dispersée,
* rendre le système extensible sans modifier les entités métier existantes.

L’ajout d’un nouveau type de document se fait donc principalement par **configuration**, et non par duplication de logique.

---

## À propos de la liste des types de documents

La liste exhaustive des `DocumentType` et `DocumentSubtype` disponibles est définie dans le code et peut évoluer dans le temps.

Cette documentation :

* n’a pas vocation à être une référence exhaustive,
* décrit les **principes**, pas les valeurs figées,
* s’appuie sur des exemples représentatifs.

Le code constitue la **source de vérité** pour la définition complète des types disponibles.

---

## En résumé

* `DocumentType` est le **pivot du système documentaire**.
* Il relie documents, entités métier et règles.
* Les dossiers sont une organisation secondaire.
* Les sous-types permettent une spécialisation fine.
* Les règles sont attachées aux types, pas aux objets.
* Le système est extensible sans refonte structurelle.


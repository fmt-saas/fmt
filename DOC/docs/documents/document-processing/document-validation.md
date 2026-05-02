# Document validation

La validation des documents constitue une **étape centrale et bloquante** du *document processing*.
Elle garantit qu’un document importé est **cohérent, complet et exploitable** avant toute intégration opérationnelle.

La validation s’applique aux **objets métier générés** à partir d’un document importé, mais elle est **pilotée par le `DocumentProcess`** et par les règles associées au type de document.



## Rôle de la validation dans le workflow

Dans le workflow du `DocumentProcess`, la validation correspond à la transition vers l’état :

> **`validated`**

Tant que cette étape n’est pas atteinte :

* le document ne peut pas être intégré,
* les actions ultérieures du workflow sont inaccessibles,
* le document reste signalé comme nécessitant une intervention.

La validation est donc **strictement bloquante**.



## Principe général des règles de validation

Les règles de validation ne sont **pas définies au niveau des entités métier**, mais au niveau du **type de document**.

Ce principe permet :

* une validation homogène pour tous les documents d’un même type,
* une séparation claire entre **structure métier** et **règles de contrôle**,
* une extensibilité sans modifier les entités cibles.

Le point d’entrée de la validation est toujours le **`DocumentType`**.



## `ValidationRule` et lien avec les `DocumentType`

Chaque `DocumentType` peut être associé à une **`ValidationRule`**, qui définit l’ensemble des contrôles à appliquer à un document de ce type.

* Une `ValidationRule` regroupe plusieurs lignes de validation.
* Chaque ligne est représentée par une entité `ValidationRuleLine`.
* L’ordre des lignes peut être significatif, selon les règles à appliquer.

Ce mécanisme permet de déterminer **programmatiquement** :

* si un document est validable,
* quelles règles doivent être exécutées,
* et dans quel contexte métier.



## `ValidationRuleLine` et contrôleurs de validation

Chaque `ValidationRuleLine` référence un **contrôleur de validation spécifique**.

Un contrôleur de validation :

* encapsule une règle métier précise,
* est responsable d’un contrôle ciblé,
* peut accéder à l’objet métier, à ses relations et à son contexte.

Exemples de contrôles :

* cohérence des montants,
* présence des champs obligatoires,
* compatibilité avec l’état d’une entité liée,
* règles comptables ou juridiques spécifiques.

Le contrôleur retourne un résultat explicite indiquant :

* si la validation est réussie ou non,
* et, en cas d’échec, un message explicatif.



## Gestion des erreurs et alertes

Lorsqu’une validation échoue :

* le `DocumentProcess` ne peut pas passer à l’état `validated`,
* une ou plusieurs **alertes** sont générées et associées à l’objet cible,
* ces alertes sont visibles dans l’interface de suivi (notamment dans les listes et vues détaillées).

Les alertes :

* sont indépendantes du statut du workflow,
* servent à attirer l’attention sur un problème précis,
* peuvent être recalculées lors d’une nouvelle tentative de validation.

Avant chaque nouvelle exécution de la validation, les alertes précédentes liées aux règles concernées sont supprimées, afin d’éviter toute ambiguïté.



## Validation et complétude des données

La validation intervient **après l’étape `completed`** du workflow.

Cela signifie que :

* les données attendues ont été identifiées,
* les champs requis ont été complétés,
* les entités internes nécessaires ont été associées.

La validation ne vise donc pas à *compléter* un document, mais à **confirmer que le document est exploitable tel quel**.



## Validation et intégration

Une validation réussie est un **pré-requis absolu** à l’intégration.

Une fois le `DocumentProcess` validé :

* les mécanismes d’intégration peuvent être déclenchés,
* les écritures comptables ou objets dérivés peuvent être générés définitivement,
* le document peut passer à l’état `integrated`.

Les règles d’intégration et de conversion sont définies séparément, notamment via les mécanismes associés aux `DocumentType`.



## Distinction avec la validation de schéma

La validation décrite dans ce document concerne exclusivement la **validation métier**.

Les contrôles suivants sont effectués **en amont**, indépendamment de cette étape :

* validation du schéma JSON du `document_json`,
* contrôle de la structure et des types de données,
* vérification de la complétude minimale.

Ces contrôles garantissent la **validité technique** des données, mais ne suffisent pas à autoriser l’intégration métier.


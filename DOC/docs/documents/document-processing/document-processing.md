# Document processing

Le *document processing* décrit l’ensemble des mécanismes qui permettent de **transformer un document entrant** (souvent externe) en un **objet métier exploitable**, validé et intégré dans les processus opérationnels du logiciel.

Ce traitement est requis dès lors qu’un document :

* provient d’une source externe (upload, email, numérisation),
* nécessite une analyse, une complétude ou une validation humaine,
* ou implique plusieurs étapes avant son intégration définitive.



## Portée du document processing

Tous les documents **ne passent pas** par un processus de traitement.

On distingue clairement deux cas :

* **Documents générés nativement par l’application**
  (ex. : facture générée après encodage manuel)
  → pas de `DocumentProcess`, intégration directe.

* **Documents importés depuis l’extérieur**
  (ex. : factures fournisseurs, extraits bancaires, documents juridiques)
  → traitement encadré par un `DocumentProcess`.

Le document processing s’applique exclusivement à ce second cas.



## Principe du `DocumentProcess`

Le `DocumentProcess` est une entité **transverse et générique**, indépendante du type d’objet métier cible.

Son rôle est de :

* porter le **workflow de traitement** d’un document importé,
* centraliser les étapes, les validations et les alertes,
* assurer la traçabilité du traitement, quel que soit le type de document.

Un même workflow de `DocumentProcess` s’applique :

* à une facture fournisseur,
* à un extrait bancaire,
* à un document administratif,
* ou à toute autre pièce importable.

👉 Le **statut du document importé** est donc porté par le `DocumentProcess`, et non par l’objet métier final.



## Workflow du DocumentProcess

Le workflow d’un `DocumentProcess` décrit le cycle de vie d’un document importé, c'est  à dire, l’ensemble des étapes nécessaires à la création
d’une pièce métier sur base de l’import d’un document externe.

Ce workflow est **strictement identique**, quel que soit l’objet cible
(facture, extrait bancaire, document administratif, etc.).

### Étapes du workflow

| Étape | Statut | Description |
|------:|--------|-------------|
| 1 | `created` | Le document a été importé. Le `DocumentProcess` est créé, mais aucune action n’a encore été effectuée. |
| 2 | `assigned` | Le document est pris en charge et assigné à un employé responsable du traitement. |
| 3 | `completed` | Le document a été analysé, identifié et complété. Les données nécessaires sont disponibles. |
| 4 | `validated` | Les règles métier ont été vérifiées avec succès. La validation est bloquante. |
| 5 | `integrated` | Le document est intégré définitivement dans les processus opérationnels (ex. comptabilité). |

Le statut courant du `DocumentProcess` constitue la **référence unique** pour déterminer
l’état d’avancement du traitement d’un document importé.


### Distinction entre workflow de traitement et statuts métier

Le workflow du `DocumentProcess` ne doit pas être confondu avec les statuts métier
des objets générés (par exemple `draft`, `proforma`, `posted` pour une facture d'achat).

- Le `DocumentProcess` décrit **le traitement du document importé**.
- Les statuts métier décrivent **l’état fonctionnel de l’objet cible**.

Un objet métier peut exister sous un statut temporaire (`draft`, `proforma`) tout en étant associé à un `DocumentProcess` encore en cours de traitement.



Tous les élément cibles qui font l'objet d'un suivi de traitement (DocumentProcess) ont des champs qui permettent de faire les liens : 

* assigned_employee_id
* alert
* document_process_status 

Ces champs sont synchronisés lors des actions réalisées par les utilisateurs.




## Démarrage et responsabilité du processus

Après l’import, le `DocumentProcess` est toujours pris en charge par un **acteur humain identifié**, généralement le `document_dispatch_officer`.

Son rôle est de :

* vérifier ou corriger l’identification automatique,
* assigner le document à la bonne personne si nécessaire,
* initier la complétude du document.

Le démarrage du traitement peut être :

* **manuel**, après upload,
* **semi-automatisé**, sur base de règles ou de reconnaissance.





## Étape de complétude (`Completion`)

La complétude correspond à la phase durant laquelle le document brut est transformé en un **document exploitable**, structuré et cohérent.

Elle se décompose en sous-étapes fonctionnelles distinctes :

| Sous-étape     | Objectif                                               |
| -------------- | ------------------------------------------------------ |
| Identification | Déterminer la nature fonctionnelle du document         |
| Extraction     | Extraire les valeurs exploitables (OCR, parsing, etc.) |
| Matching       | Associer le document à des entités internes existantes |
| Drafting       | Générer un document métier temporaire (*proforma*)     |

Ces mécanismes sont détaillés dans les fichiers suivants :

* `document-identification.md`
* `document-analysis.md`



## Documents temporaires et statuts métier

Pendant le traitement, le document métier généré peut exister sous des formes **non intégrées** (pas encore dans la compta):

* **`draft`**
  Utilisé lorsque l’encodage est incomplet ou nécessite plusieurs itérations.

* **`proforma`**
  Document lisible et vérifiable, sans incidence comptable ou opérationnelle.

Ces statuts permettent :

* la relecture,
* la vérification croisée,
* la correction avant validation finale.

Ils ne produisent **aucun effet réel** tant que la validation n’est pas acquise.





## Validation et blocage du workflow

La validation constitue une **étape bloquante** du document processing.

Elle repose sur :

* des règles définies par le `DocumentType`,
* des contrôles métiers explicites,
* des mécanismes d’alerte visibles sur l’objet cible.

Tant que la validation échoue :

* les étapes ultérieures sont inaccessibles,
* le document reste signalé dans les listes de suivi.

👉 Le fonctionnement détaillé de la validation est décrit dans
[`document-validation.md`](document-validation.md).



## Intégration finale

Une fois validé, le document peut être **intégré** :

* génération définitive des écritures comptables,
* synchronisation avec les entités cibles,
* prise en compte dans les processus opérationnels.

Cette étape marque la **fin du `DocumentProcess`**.

Les mécanismes comptables et de synchronisation sont détaillés dans
[`document-integration.md`](document-integration.md).



## Rôle des `DocumentType`

Tout au long du processus, le `DocumentType` joue un rôle central :

* il définit les champs attendus,
* les règles de validation,
* les stratégies d’intégration,
* les comportements spécifiques du document.

Le `DocumentProcess` reste générique ;
le `DocumentType` apporte la **spécialisation métier**.


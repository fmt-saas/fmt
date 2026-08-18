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



### Hors périmètre : documents générés et correspondances

Les documents générés nativement par l’application ne relèvent pas du `DocumentProcess`.

C’est notamment le cas :

* des documents produits par des renderers `render-html` puis `render-pdf` ;
* des versions de preview produites par `single-html` ou `single-pdf` ;
* des documents persistés dans le EDMS après génération ;
* des `DocumentCorrespondence` créées pour individualiser un document par destinataire.

Une `DocumentCorrespondence` peut bien aboutir à la création d’un `Document`, mais cela ne signifie pas qu’un processus de traitement documentaire est ouvert. Elle appartient à la logique de **production et de distribution documentaire**, tandis que le `DocumentProcess` appartient à la logique de **traitement d’un document entrant**.

La distinction est donc la suivante :

| Cas | Entité principale | Finalité |
| --- | --- | --- |
| Document importé à analyser | `DocumentProcess` | Identifier, compléter, valider et intégrer une pièce reçue |
| Document généré par l’application | `Document` | Conserver une trace documentaire produite par le système |
| Document individualisé pour un destinataire | `DocumentCorrespondence` | Relier un document généré à un propriétaire ou destinataire |
| Regroupement pour impression ou archive | `ExportingTask` / `ExportingTaskLine` | Produire un export asynchrone de documents ou correspondances |


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



### Coordination entre `DocumentProcess` et document cible

Le workflow est réparti entre deux responsabilités complémentaires :

* le `DocumentProcess` porte le workflow de traitement, l’assignation et les permissions utilisateur ;
* le document cible porte les informations métier propres à sa nature, par exemple la complétude ou les données de validation.

Une transition vers l’étape suivante peut donc dépendre de l’état des deux objets :

* côté `DocumentProcess`, le passage à l’étape suivante dépend de l’état du document cible lorsque des informations métier sont requises ;
* côté document cible, les actions qui font avancer le traitement dépendent du statut courant du `DocumentProcess`.

Tous les éléments cibles qui font l’objet d’un suivi de traitement (`DocumentProcess`) disposent de champs de liaison et de suivi :

* `assigned_employee_id`
* `alert`
* `document_process_status`

Ces champs sont synchronisés lors des actions réalisées par les utilisateurs. Le champ `document_process_status` permet notamment au document cible d’exposer l’état de son `DocumentProcess` sans porter directement le workflow de traitement.

Pour éviter les confusions, les transitions du workflow du `DocumentProcess` ne sont pas directement accessibles depuis la vue formulaire du `DocumentProcess`. Elles doivent être appelées indirectement via les actions du document cible, qui vérifient à la fois l’état métier du document et le statut du `DocumentProcess`.

Exemple pour une `PurchaseInvoice` :

* le statut métier de la facture reste présent, mais aucune action ne permet de l’activer directement dans le cadre du document processing ;
* le workflow de traitement est accessible uniquement via le `DocumentProcess` associé ;
* la facture expose le statut de son `DocumentProcess` via un champ dédié ;
* des actions spécifiques sur la facture, équivalentes aux transitions du `DocumentProcess`, appellent les transitions du `DocumentProcess` selon son statut courant.




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



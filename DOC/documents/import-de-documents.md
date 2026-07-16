# Import de documents

## Objectif

L’import de documents permet d’ajouter manuellement un fichier dans l’EDMS en précisant les métadonnées nécessaires à son classement et à son contrôle d’accès.

Cette opération est portée par l’entité technique `DocumentImport`.

`DocumentImport` n’est pas le document final. Il s’agit d’un objet temporaire utilisé par le formulaire d’import pour collecter :

* le fichier à déposer ;
* la copropriété concernée ;
* le type et le sous-type de document ;
* le niveau de visibilité ;
* éventuellement un propriétaire, un fournisseur ou un contexte de diffusion.

Lorsque le fichier est effectivement transmis, le système crée un `Document` final dans l’EDMS, lui applique les métadonnées renseignées, puis supprime l’objet `DocumentImport` temporaire.

## Différence entre `DocumentImport`, `Document` et `DocumentProcess`

Il faut distinguer trois notions.

| Entité | Rôle |
| --- | --- |
| `DocumentImport` | Objet temporaire de dépôt utilisé par l’interface d’import |
| `Document` | Document final stocké dans l’EDMS |
| `DocumentProcess` | Workflow de traitement d’un document entrant lorsqu’une analyse, validation ou intégration est nécessaire |

Un import manuel crée toujours un `Document`. Il ne crée pas nécessairement un `DocumentProcess`.

Le `DocumentProcess` intervient lorsque le document importé doit être analysé, identifié, complété, validé ou intégré dans un processus métier, par exemple pour une facture fournisseur ou un extrait bancaire. À l’inverse, un document historique, administratif ou informatif peut être simplement importé, classé et rendu consultable dans l’EDMS.

## Flux d’import

Le flux général est le suivant :

1. l’utilisateur ouvre un formulaire d’import ;
2. il renseigne les métadonnées de classement ;
3. il sélectionne ou dépose le fichier ;
4. le fichier est envoyé dans le champ binaire `data` ;
5. `DocumentImport` crée le `Document` final ;
6. le `Document` reçoit le type, le sous-type, la copropriété, la visibilité et les liens éventuels ;
7. les hooks du `Document` classent le document dans le bon nœud de l’EDMS ;
8. l’objet `DocumentImport` est supprimé.

L’objet `DocumentImport` ne doit donc pas être utilisé comme historique fonctionnel. L’historique et la consultation reposent sur le `Document` final.

## Métadonnées renseignées lors de l’import

Le formulaire d’import permet de renseigner les champs suivants.

| Champ | Rôle |
| --- | --- |
| `name` | Nom du document importé. Il peut être alimenté automatiquement depuis le nom du fichier. |
| `data` | Contenu binaire du fichier déposé. |
| `condo_id` | Copropriété à laquelle le document se rapporte. |
| `document_type_id` | Type GED du document. |
| `document_subtype_id` | Sous-type GED optionnel. |
| `document_visibility` | Niveau de visibilité du document. |
| `ownership_id` | Dossier de propriété concerné, si le document est propre à une propriété ou un copropriétaire. |
| `supplier_id` | Fournisseur lié au document, si applicable. |
| `broadcast_id` | Diffusion ou communication liée, si applicable. |

La copropriété (`condo_id`) est obligatoire, car elle détermine le contexte principal de classement. Le type de document est également obligatoire afin que l’EDMS puisse qualifier et organiser le document.

## Copropriété et classement EDMS

Le champ `condo_id` indique à quelle copropriété le document se rapporte.

Cette information est centrale pour plusieurs raisons :

* elle rattache le document au bon contexte métier ;
* elle permet de classer le document dans l’arborescence EDMS de la copropriété ;
* elle limite les choix de certains champs dépendants, comme le dossier de propriété ;
* elle permet d’appliquer les règles de visibilité propres au contexte.

Après création du `Document`, les mécanismes de classement du modèle `Document` utilisent les métadonnées disponibles, notamment le type, le sous-type, la copropriété, le fournisseur ou l’ownership, pour générer ou retrouver le nœud EDMS approprié.

## Visibilité du document

Le champ `document_visibility` définit qui peut consulter le document dans l’EDMS.

Les niveaux utilisés sont :

| Visibilité | Portée fonctionnelle |
| --- | --- |
| `agency` | Visible uniquement par l’agence ou le syndic. C’est la valeur par défaut. |
| `condo` | Visible au niveau de la copropriété. |
| `ownership` | Visible dans le contexte d’un dossier de propriété. |
| `owner` | Visible pour un propriétaire déterminé, lorsque cette granularité est utilisée. |
| `suppliership` | Visible dans le contexte d’un fournisseur lié à une copropriété. |

La visibilité est synchronisée avec le nœud EDMS associé. Lorsqu’un document est classé dans un nœud, la visibilité du document et celle du nœud doivent rester cohérentes.

Certains niveaux de visibilité imposent un contexte supplémentaire :

* pour une visibilité `ownership`, un `ownership_id` doit être renseigné ;
* pour une visibilité `suppliership`, le lien fournisseur-copropriété doit exister ou pouvoir être créé.

Si ces informations manquent, l’import est refusé afin d’éviter de créer un document visible dans un périmètre mal défini.

## Type, sous-type et visibilité par défaut

Le type de document (`DocumentType`) et le sous-type (`DocumentSubtype`) peuvent porter une visibilité par défaut.

Lorsque l’utilisateur sélectionne un type ou un sous-type, le formulaire peut automatiquement adapter `document_visibility` à la valeur prévue par cette catégorie documentaire.

Cela permet d’éviter que l’utilisateur doive connaître toutes les règles de visibilité. Par exemple, un type documentaire destiné uniquement à l’agence peut proposer automatiquement la visibilité `agency`, tandis qu’un document destiné à être partagé au niveau de la copropriété peut proposer `condo`.

L’utilisateur reste toutefois dans un formulaire d’import : il doit vérifier que la visibilité proposée correspond bien au contexte réel du document.

## Fournisseur et suppliership

Lorsqu’un document importé est lié à un fournisseur, le champ `supplier_id` peut être renseigné.

Si aucun lien `Suppliership` n’existe encore entre le fournisseur et la copropriété, le système peut le créer lors de l’import. Cela permet ensuite de classer ou retrouver le document dans le contexte fournisseur de la copropriété.

Ce mécanisme est utile pour les documents administratifs ou contractuels liés à un fournisseur, sans nécessairement passer par le workflow spécifique des factures d’achat.

## Import depuis un envoi groupé

Un document peut être importé directement depuis la préparation d’un envoi groupé (`Broadcast`).

Dans ce cas, le formulaire d’import reçoit le contexte de la copropriété et l’identifiant du `Broadcast`. Après création du `Document`, celui-ci est automatiquement rattaché à l’envoi groupé comme pièce jointe.

Ce mécanisme évite de devoir importer d’abord le document dans l’EDMS puis revenir le sélectionner manuellement dans l’envoi groupé. Le document reste néanmoins un `Document` normal de l’EDMS, avec ses métadonnées et sa visibilité propres.

## Types exclus de l’import manuel générique

Certains types de documents ne doivent pas être déposés via l’import manuel générique.

C’est notamment le cas des types liés à des workflows dédiés, comme :

* `supplier_invoice` ;
* `bank_statement`.

Ces documents ont des processus spécifiques d’analyse, d’extraction, de validation ou d’intégration. Ils doivent passer par les flux prévus pour les factures fournisseurs ou les extraits bancaires, afin que le `DocumentProcess` et les règles métier associées soient correctement appliqués.

## Synthèse

`DocumentImport` sert à déposer un document dans l’EDMS en lui donnant immédiatement son contexte métier.

L’utilisateur ne fait donc pas seulement un upload de fichier : il précise à quelle copropriété le document appartient, comment il doit être classé, et qui peut le consulter.

Une fois l’import confirmé, le seul objet fonctionnel à conserver est le `Document` final. `DocumentImport` disparaît après avoir servi de passerelle entre le formulaire d’upload et la création du document EDMS.

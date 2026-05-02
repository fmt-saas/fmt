# Documents

Dans le logiciel, un **document** est un **artefact métier structuré**, porteur de liens avec les entités du système.



## Terminologie

Les termes suivants sont utilisés de manière cohérente dans la documentation et dans le logiciel :

- **Pièces comptables** → *Accounting documents*
- **Documents juridiques** → *Legal documents*
- **Documents administratifs** → *Administrative documents*
- **Pièces jointes / annexes** → *Attachments*

Ces catégories reflètent des **finalités d’usage**, pas des différences techniques de stockage.



## Document ≠ fichier ≠ dossier

Un **Document** n’est pas équivalent à un fichier, ni à un dossier.

- Le **fichier** correspond aux **données binaires** (PDF, image, etc.).
- Le **Document** est une entité applicative qui :
  - référence ces données binaires,
  - porte des métadonnées métier,
  - est reliée à une ou plusieurs entités du système.
- Les **dossiers** sont une organisation secondaire, destinée à l’utilisateur, et **ne constituent jamais une source de vérité fonctionnelle**.

👉 Un document peut donc être retrouvé, traité ou analysé **indépendamment de son emplacement physique**.



## Rôle de l’entité `Document`

L’entité `Document` est utilisée pour :

- stocker et référencer les données binaires,
- qualifier ces données via un `document_type_id`,
- servir de **point d’ancrage** entre les fichiers et les objets métier.

Les données binaires associées à un `Document` représentent une **trace documentaire** d’une information traitée dans le logiciel, qu’elle soit :

- importée depuis l’extérieur,
- générée automatiquement,
- ajoutée ultérieurement comme pièce jointe.



## Documents et entités métier

Les documents sont toujours **rattachés à des entités cibles** (ex. : facture, extrait bancaire, procès-verbal, virement, etc.).

La logique de recherche et d’exploitation des documents repose sur ce principe fondamental :

> **On part toujours des entités métier, puis on retrouve les documents qui leur sont liés.**

Cela permet notamment :

- de retrouver automatiquement le dernier document pertinent (ex. : dernier PV d’AG),
- d’accéder à l’historique documentaire d’un objet,
- de raisonner en termes métier plutôt qu’en termes de fichiers.



## Documents d’origine et documents annexes

Pour certaines entités, notamment les **pièces comptables**, la présence de documents obéit à des règles précises.

### Documents d’origine

Pour toute pièce comptable, il existe **au minimum un document d’origine** (`is_origin = true`) :

- soit un document **importé**, à l’origine de la création de la pièce (`is_source = true`),
- soit un document **généré automatiquement** suite à un encodage manuel (`is_source = false`), comme une facture d’achat ou un extrait bancaire.

Ces documents constituent la **référence principale** de la pièce.

### Documents annexes

Il est également possible de rattacher, de manière arbitraire, des documents supplémentaires à une entité :

- pièces jointes,
- justificatifs complémentaires,
- annexes diverses.

Ces documents ne sont pas considérés comme des documents d’origine (`is_origin = false`), mais enrichissent le contexte documentaire de l’objet.



## Affichage des références comptables dans les documents

Lorsqu’un document est **rattaché à une pièce comptable**, il est souvent nécessaire de pouvoir identifier immédiatement **l’écriture comptable interne** qui lui correspond.

C’est notamment le cas lors :

- de vérifications internes,
- d’un contrôle du commissaire aux comptes,
- d’une consultation ultérieure des documents.

Pour faciliter cette identification, le système ajoute automatiquement **une surimpression (overlay)** dans certains documents lors de leur téléchargement.

Cette surimpression contient les informations principales issues de la comptabilisation de la pièce.

### Principe

La logique est implémentée au niveau de la **route de téléchargement des documents** (`documents_document`).

Lorsqu’un document est demandé :

1. le système identifie s’il est lié à une **pièce comptable** ;
2. si c’est le cas, il récupère les informations comptables pertinentes ;
3. si les conditions sont remplies, il renvoie **une version PDF avec overlay**, sinon le document original.

Le texte ajouté correspond généralement à :

```
YYYY-MM-DD | référence_comptable
```

Exemple :

```
2025-03-12 | PI-2025-0043
```

Le traitement est effectué **au point central de téléchargement des documents**, dans le controller `documents_document`

Cela garantit que :

- toute consultation d’un document comptable affiche les informations nécessaires,
- le comportement est cohérent quel que soit l’écran ou le contexte qui déclenche le téléchargement,
- les exports futurs (archives, audits, etc.) bénéficieront automatiquement de la même logique.



### Types de documents concernés

L’overlay est actuellement appliqué uniquement lorsque le document est lié à certaines entités comptables.

| Lien dans `Document`        | Entité source          | Informations utilisées           | Condition          | Texte affiché                 |
| --------------------------- | ---------------------- | -------------------------------- | ------------------ | ----------------------------- |
| `purchase_invoice_id`       | `PurchaseInvoice`      | `posting_date`, `invoice_number` | facture **postée** | `YYYY-MM-DD | invoice_number` |
| `expense_statement_id`      | `ExpenseStatement`     | `posting_date`, `invoice_number` | pièce **postée**   | `YYYY-MM-DD | invoice_number` |
| `fund_request_execution_id` | `FundRequestExecution` | `posting_date`, `invoice_number` | pièce **postée**   | `YYYY-MM-DD | invoice_number` |
| `bank_statement_id`         | `BankStatement`        | `date`, `name`                   | toujours appliqué  | `YYYY-MM-DD | name`           |

Notes :

- la date est affichée au format `YYYY-MM-DD`
- les informations sont concaténées avec le séparateur `|`
- le traitement ne s’applique actuellement **qu’aux documents PDF**



### Implémentation technique

Lorsque l’overlay doit être appliqué, la route de téléchargement appelle l’action :

```
documents_Document_add-overlay
```

Exemple d’invocation :

```
?get=documents_Document_add-overlay&id=122&overlay_text=2025-03-12%20|%20PI-2025-0043
```

Cette action :

1. charge le PDF original,
2. applique une surimpression contenant le texte fourni,
3. retourne le PDF modifié.

Le document retourné à l’utilisateur est donc **une version générée à la volée**, sans modifier le fichier original stocké dans l’EDMS.



## Métadonnées métier et datation des documents

La date de création technique d’un document ne constitue pas une information fiable du point de vue métier, notamment parce que :

- les documents peuvent être générés de manière asynchrone,
- leur création peut intervenir après coup.

La **datation fonctionnelle** d’un document repose donc sur les **métadonnées portées par l’entité cible**, telles que :

- `fiscal_year_id`
- `fiscal_period_id`

Ces informations permettent :

- de situer un document dans le temps métier,
- de retrouver des documents pertinents sur une période donnée,
- d’effectuer des recherches programmatiques fiables.



## DocumentType et descripteur de données

Chaque document est associé à un **type de document** (`DocumentType`), qui définit sa finalité et son contexte d’utilisation.

Pour chaque fichier associé à une entité métier, un **descripteur JSON** est également présent.
Ce descripteur respecte un schéma correspondant au `document_type_id` et permet :

- de structurer les informations spécifiques au document,
- de supporter les mécanismes de validation, d’étiquetage ou d’intégration,
- d’assurer une cohérence entre les documents, leurs usages et les règles applicables.

Le rôle central des `DocumentType` et des règles associées est détaillé dans la section dédiée à l’organisation des documents.




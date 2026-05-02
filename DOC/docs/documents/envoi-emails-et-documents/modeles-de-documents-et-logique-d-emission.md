## Modèle de documents et logique d’émission

Le logiciel gère plusieurs types de **documents formels** liés à la gouvernance et à la gestion financière d’une copropriété.
Bien que leur finalité juridique ou fonctionnelle diffère, ces documents reposent sur une **logique de modélisation commune**, garantissant cohérence, traçabilité et extensibilité.

### Types de documents concernés

Les principaux types de documents actuellement pris en charge sont :

* **AssemblyInvitation** — convocation à l’assemblée générale
* **AssemblyMinutes** — procès-verbal d’assemblée générale
* **ExpenseStatement** — décompte de charges
* **FundRequest** — appel de fonds
* **FundRequestReminder** — rappel de paiement

Tous ces documents suivent le même cycle de vie et la même logique d’émission et de distribution.



## Structure conceptuelle à trois niveaux

Chaque document est modélisé selon **trois niveaux distincts**, permettant de séparer clairement :

* la notion abstraite du document,
* l’acte formel d’émission,
* le document individuel adressé à un destinataire.

### 1. Pièce / Source de flux documentaire (niveau abstrait)

Le **Document** représente la définition logique et fonctionnelle du document :

* sa finalité et sa portée juridique,
* la structure de son contenu,
* les règles de génération,
* la gestion des versions et des pièces jointes.

À ce stade, le document n’est **lié à aucun destinataire** et n’est pas encore émis.

Exemples :

* `AssemblyInvitation`
* `AssemblyMinutes`
* `ExpenseStatement`
* `FundRequest`
* `FundRequestReminder`

### 2. Rendu de "document"

  Il ne s'agit pas d'un document à proprement parler, mais d'une représentation d'un flux d'information utilisant un template spécifique.

  Le flux est en PDF et est généré à la volée. Les renderer peuvent être utilisés soit pour visualiser une version "théorique " d'un document, s'il était généré à ce moment là. Soit pour générer le document proprement dit (dans ce cas un controller generate-document appelle le renderer puis stocke le résultat dans un nouveau document).

  On distingue les renderer html (render-html) et pdf (render-pdf). Par convention,  les seconds appellebt les premiers et convertissent le HTML via DomPDF.



### 3. Génération de Document (émission globale)

Cette étape correspond à l'**émission formelle** d’un document à un moment donné.

Il correspond à la décision d’émettre le document et d’en organiser la diffusion vers un ensemble de destinataires.

Caractéristiques :

* daté et traçable,
* rattaché à un seul Document,
* indépendant du canal de distribution.






### 4. DocumentCorrespondence (document individuel adressé)

Un **DocumentCorrespondence** représente le **document concret adressé à un destinataire donné** (personne physique ou, plus rarement, personne morale).

Il s’agit d’une **correspondance formelle**, générée à partir d’un DocumentIssuance, qui peut être :

* consultée directement via la plateforme,
* et, le cas échéant, transmise au destinataire via un canal de distribution.

Caractéristiques :

* associé à un destinataire unique,
* est rattaché à un document, qui peut être la concaténation de plusieurs documents (PDF)
* constitue un artefact stable, traçable et auditable,
* peut contenir :

  * un **contenu spécifique au destinataire** (par exemple pour les décomptes de charges),
  * ou un contenu commun avec une personnalisation limitée (en-tête d’adresse, salutations).

Exemples :

* `InvitationCorrespondence`
* `ExpenseStatementCorrespondence`
* `FundRequestCorrespondence`



## Canaux de distribution et préférences destinataire

Un **DocumentCorrespondence** peut être transmis via un **canal de distribution**, déterminé par les préférences du destinataire :

* envoi postal,
* envoi par email.

Le canal de distribution est une propriété du **DocumentCorrespondence**.
Il est **optionnel** et n’impacte ni le Document abstrait, ni le DocumentIssuance.

Cette approche permet :

* de distinguer clairement l’existence d’une correspondance de son éventuelle transmission,
* de combiner plusieurs modes de distribution pour une même émission,
* de respecter les préférences individuelles des destinataires,
* d’envisager facilement de futurs canaux (eBox, portail sécurisé, etc.).



## Variabilité du contenu

Selon le type de document :

* certains **DocumentCorrespondence** disposent d’un contenu entièrement individualisé
  (ex. `ExpenseStatement`, `FundRequest`),
* d’autres partagent un contenu identique, à l’exception des éléments liés au destinataire
  (adresse, en-tête, formules de politesse).

Cette variabilité est gérée exclusivement au niveau du **DocumentCorrespondence**.



## Vue d’ensemble conceptuelle

```text
Pièce
└─ Document
   └─ DocumentCorrespondence, per recipient, per delivery channel (postal | email)
```



Cette logique s'applique sur les types de documents suivants : 

* Convocation AG : `AssemblyInvitationCorrespondence`
* PV AG : `AssemblyMinutesCorrespondence`
* Décompte de charge : `ExpenseStatementCorrespondence`
* Appel de fonds : `FundRequestCorrespondence`
* Rappel de paiement : `FundRequestReminderCorrespondence`



### Récapitulatif des templates de rendu

Les templates de rendu sont organisés selon **leur contexte d’utilisation** :

- **stand-alone** : rendu autonome d’un document (prévisualisation, génération globale, archive)
- **correspondence** : rendu utilisé dans le cadre d’un `DocumentCorrespondence`, avec personnalisation destinataire



| Contexte       | Template                | Entité(s) concernée(s)              | Remarques                                             |
| -------------- | ----------------------- | ----------------------------------- | ----------------------------------------------------- |
| stand-alone    | `agenda`                | `AssemblyInvitation`                | Rendu générique de la convocation (sans destinataire) |
| stand-alone    | `mandate`               | `AssemblyMinutes`                   | Rendu du PV global de l’AG                            |
| stand-alone    | `minutes`               | `ExpenseStatement`                  | Vue complète d’un décompte (hors destinataire)        |
| stand-alone    | `fund_request`          | `FundRequest`                       | Appel de fonds générique                              |
| correspondence | `agenda`                | `AssemblyInvitationCorrespondence`  | Ajout des coordonnées et mentions destinataire        |
| correspondence | `mandate`               | `AssemblyMinutesCorrespondence`     | Distribution individualisée du PV                     |
| correspondence | `minutes`               | `ExpenseStatementCorrespondence`    | Contenu entièrement individualisé                     |
| correspondence | `fund_request`          | `FundRequestCorrespondence`         | Contenu individualisé par copropriétaire              |
| correspondence | `fund_request_reminder` | `FundRequestReminderCorrespondence` | Variante de rappel, dérivée de l’appel de fonds       |



### Règle de lecture

- **Un même template peut être utilisé dans plusieurs contextes**
  (stand-alone et correspondence), avec des données différentes.
- La **logique de personnalisation** (destinataire, montants, adresses, etc.)
  est **toujours injectée via le contexte de rendu**, jamais dans le template lui-même.
- Les templates restent **agnostiques du canal de distribution** (email, postal, etc.).



### Convention d’implémentation (rappel utile)

- `render-html`
  → produit le HTML à partir du template et du contexte
- `render-pdf`
  → appelle `render-html` puis convertit via DomPDF
- `generate-document`
  → orchestre la génération, le stockage et la création des `DocumentCorrespondence`

### Prévisualisation (preview) sans persistance

Dans certaines situations, il est nécessaire de proposer une **prévisualisation** d’un document qui n’existe pas encore comme artefact persistant.

Cas typique : un **décompte de charges global** au niveau de la copropriété.
Ce document n’existe pas en tant que PDF unique stocké ; en pratique, la génération réelle produit **autant de documents qu’il y a de copropriétaires concernés**.

#### Contrainte métier

Les `DocumentCorrespondence` portent normalement le rendu final via leurs endpoints `render-html` / `render-pdf`, car elles encapsulent :

- le document généré,
- l’`ownership`,
- et l’`owner` (destinataire final).

En phase de preview, ces correspondences n’existent pas encore.

#### Réponse technique

Pour permettre un rendu immédiat, le système utilise des **controllers de rendu temporaire** :

- des renderers de type `single-pdf` (non persistants),
- combinés à un controller de preview `render-pdf` pour un rendu instantané.

Objectif : obtenir un aperçu fidèle du futur document, sans créer de `DocumentCorrespondence` ni stocker de PDF définitif.

#### Paramètres de rendu côté single-html

Lorsqu’ils sont disponibles, les endpoints `single-html` acceptent :

- `ownership_id`,
- `owner_id` (optionnel).

Ces renderers sont ensuite réutilisés par les `render-html` des correspondences associées, qui injectent explicitement ces deux informations pour garantir la cohérence entre preview et rendu final.

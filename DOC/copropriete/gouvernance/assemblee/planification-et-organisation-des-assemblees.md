## Planification des assemblée générale

Les AG sont tenues sur base des prescriptions légales.

Possibilité de générer automatiquement un brouillon d'AG sur base de la date théorique (à la clôture d'une AG).

Note: lors d'une AG, la date de l'AG suivante peut être annoncée et/ou votée (nécessite un suivi: création tâche à faire).

## Organisation d'une assemblée

L’**organisateur** d’une assemblée générale est la **personne physique** qui agit au nom du syndic (ManagingAgent) pour initier et préparer la tenue de l’AG. Ce rôle couvre notamment l’envoi des convocations, la préparation de l’ordre du jour et la mise en place logistique de la séance. Selon la nature du syndic, l’organisateur est soit le syndic lui-même (copropriétaire bénévole ou professionnel indépendant), soit un représentant autorisé d’une société de gestion mandatée. Dans le système, l’organisateur est identifié par une `Identity` distincte du `ManagingAgent` et utilisée pour créer automatiquement l’`AssemblyAttendee` correspondant au syndic lors de l’AG, assurant ainsi la traçabilité et la cohérence entre la gestion contractuelle et le déroulement opérationnel de l’assemblée.

Lors d’une **Assemblée Générale d’ACP**, le **secrétaire de séance** n’est pas obligatoirement le représentant du syndic.

### Règles habituelles

- La loi ne désigne pas une personne fixe pour ce rôle : **l’AG élit le secrétaire au début de la séance**, en même temps que le président de séance et le(s) scrutateur(s).
- Le secrétaire peut être :
  - Un **copropriétaire** volontaire,
  - Un **membre du conseil de copropriété**,
  - Un **tiers invité** (par exemple : un comptable, un avocat ou un technicien), si l’AG l’accepte,
  - Le **représentant de l’agence** (gestionnaire), si l’AG préfère.

### Responsabilités du secrétaire

- Rédiger le procès-verbal pendant la séance.
- S’assurer que toutes les décisions et votes sont correctement consignés.
- Signer le procès-verbal avec le président et éventuellement les scrutateurs.

###

**Très souvent**, c’est le syndic / représentant de l’agence qui prend ce rôle, parce qu’il maîtrise la procédure et dispose du canevas de PV. Mais l’AG peut **décider qu’une autre personne** l’assume, si elle le souhaite.

## Modèles d'Assemblées, Points & Résolutions
Les `AssemblyTemplate` sont des **modèles prédéfinis** d’Assemblées Générales, créés pour faciliter et uniformiser la génération des AG à venir, qui sont:

- **Généraux** (valables pour toute l’agence)
- **Spécifiques à un type d’AG** (statutaire, extraordinaire, partielle, AG de reprise, etc.)

### Structure de base

#### `AssemblyTemplate`

Un `AssemblyTemplate` définit les **paramètres par défaut** d’une AG, notamment :

- **Métadonnées** (type, titre, heure, durée estimée)
- **Texte de convocation par défaut** (`call_text_template`)
- **Points de l’ordre du jour par défaut** via les `AssemblyItemTemplate`
- Option : **auto-génération du procès-verbal**

```yaml
class: AssemblyTemplate
fields:
  - title: string
  - condo_id: link to Condo
  - default_type: enum (statutory, ordinary, extraordinary, partial, takeover)
  - default_location: string
  - default_time: time
  - default_duration: int (in minutes)
  - is_default: boolean
  - call_text_template: text
  - default_items_ids: hasMany AssemblyItemTemplate
  - auto_generate_minutes: boolean
```

#### `AssemblyItemTemplate`

Les `AssemblyItemTemplate` définissent **les éléments individuels ou regroupés** de l’ordre du jour, qui seront instanciés sous forme de `AssemblyItem` lors de la création de l’AG.

```yaml
class: AssemblyItemTemplate
fields:
  - name: string                      # Titre affiché dans l'ordre du jour
  - code: string                      # Identifiant interne ou abrégé
  - description_call: text            # Texte lu ou envoyé dans la convocation
  - description_minutes: text         # Texte suggéré dans le PV
  - has_vote_required: boolean        # Ce point requiert un vote ?
  - majority: enum                    # Type de majorité requise (ex: 2/3, 3/4…)
  - apportionment_key_id: link        # Clé de répartition des voix (par quotité, par tantièmes spéciaux, etc.)
  - is_group: boolean                 # Le point est-il un regroupement de sous-points ?
  - children_ids: hasMany AssemblyItemTemplate # Sous-points si `is_group` est vrai
```

#### `Assembly`

L’objet `Assembly` représente une assemblée convoquée pour une copropriété. Il regroupe l’ensemble des métadonnées nécessaires à la gestion de la convocation, des présences, de l’ordre du jour, des votes et du procès-verbal.

```yaml
class: Assembly
fields:

  # Référencement
  - condo_id: link to Condominium                     # Copropriété concernée
  - template_id: link to AssemblyTemplate             # Template utilisé à la création (facultatif)

  # Informations générales
  - name: string                                      # Titre de l'assemblée
  - assembly_type: enum [statutory, takeover, ordinary, extraordinary, partial] # Type d’AG
  - assembly_date: datetime                           # Date et heure planifiées (non modifiables après envoi)
  - assembly_location: string                         # Lieu annoncé à l’avance
  - call_text: text                                   # Texte de convocation prérempli ou personnalisé

  # Statut de l’AG
  - status: enum [pending, ready, sent, held, adjourned, closed]  # Workflow d’évolution
    # Transitions : pending → ready → sent → held → closed | adjourned

  # Composition de l’AG
  - assembly_items_ids: hasMany AssemblyItem          # Points de l’ordre du jour
  - assembly_invitations_ids: hasMany AssemblyInvitation # Invitations envoyées
  - assembly_attendees_ids: hasMany AssemblyAttendee  # Présences / pouvoirs
  - assembly_votes_ids: hasMany AssemblyVote          # Votes enregistrés

  # Procès-verbal
  - assembly_minutes_id: link to AssemblyMinutes      # Lien vers le PV généré

  # Gestion des deux séances
  - session_time_start: time                          # Heure de début de la première séance
  - session_time_end: time                            # Heure de fin (facultatif)
  - is_second_session: boolean                        # Cette AG est-elle une deuxième séance ?
  - has_second_session: boolean                       # Une deuxième séance est-elle prévue ?
  - related_assembly_id: link to Assembly             # Assemblée liée si c’est une deuxième séance
  - second_session_assembly_id: link to Assembly      # Assemblée liée si une seconde séance est prévue
```

#### `AssemblyItem`

Un `AssemblyItem` correspond à un point inscrit à l’ordre du jour. Seuls les items **non groupés** peuvent faire l’objet d’un vote. Chaque item contient les textes pour la convocation et le procès-verbal, ainsi que les règles de vote.

```yaml
class: AssemblyItem
fields:

  # Identification
  - name: string                                      # Titre du point (affiché à l’ordre du jour)
  - code: string                                      # Code de référence (ex : "R1", "5.2")

  # Organisation
  - order: int                                        # Ordre d'affichage dans l’AG
  - is_from_template: boolean                         # Vient-il d’un template ?
  - assembly_item_template_id: link to AssemblyItemTemplate # Lien vers l’item du modèle

  # Contenu
  - description_call: text                            # Texte pour la convocation
  - description_minutes: text                         # Texte pour le PV

  # Vote et quorum
  - has_vote_required: boolean                        # Nécessite-t-il un vote ?
  - majority: enum [unanimity, absolute, 2_3, 3_4, 4_5, 1_5]  # Quorum requis
  - apportionment_key_id: link to ApportionmentKey    # Clé de répartition associée (facultative)

relations:
  - assembly_id: link to Assembly                     # Assemblée parente
```

- Les `AssemblyItem` suivent la hiérarchie (parent/enfant) définie dans les templates.
- Le champ `majority` permet d’indiquer le quorum requis pour le vote : ce dernier sera utilisé pour le calcul du résultat.
- Le champ `apportionment_key_id` permet d’affecter une clé de répartition spécifique (par exemple, charges spéciales ou fonds distincts).

### Types d'assemblées

| Code              | Description fonctionnelle                                    | Base / usage                                                 |
| ----------------- | ------------------------------------------------------------ | ------------------------------------------------------------ |
| `constitutive`    | AG initiale post-division ou post-construction, où l’ACP devient active. On y fixe l’exercice, désigne le syndic, constitue les fonds, etc. | Recommandée par la pratique, déclenche une logique spécifique. |
| `statutory`       | AG annuelle obligatoire avec ordre du jour standardisé (comptes, budget, syndic, etc.) | **Art. 3.87 §1 C.C.**                                        |
| `extraordinary`   | AG convoquée pour un ou plusieurs points urgents ou majeurs (travaux, modifications, etc.) | Usuel mais non encadré différemment dans le code civil.      |
| `recovery`        | AG convoquée pour relancer une copropriété inactive, sans syndic ou mal gérée. | Cas de reprise ou redémarrage – pas formalisé légalement mais courant. |
| `special`         | AG complexes ou segmentées (par bâtiment, par lot, par indivision, judiciaire…) | Rare mais utile dans les copropriétés complexes.             |
| `council_meeting` | Réunion du Conseil de Copropriété, avec ou sans vote, souvent consultative. | **Art. 3.87 §8 C.C.** prévoit la possibilité d’un conseil mais sans AG formelle. |

### Logique de regroupement

Un système de regroupement permet d'assigner un ou plusieurs points avec un "point" parent (`is_group`).

- **Seuls les `AssemblyItem` non groupés (issus d’un `AssemblyItemTemplate` avec `is_group: false`) peuvent être votés.**
- Des regroupements logiques sont affichés à l’écran ou dans les documents, mais **ne donnent pas lieu à un vote agrégé**.
- Il n'y a qu'un seul niveau de hiérarchie possible (les points de type "groupe" ne peuvent pas, à leur tour, avoir de sous-points de type "groupe").

**Groupes (`is_group: true`) :**

- Les `AssemblyItemTemplate` peuvent représenter **des sections non votables** de l’ordre du jour.
- Ces points groupés servent à **structurer l’AG**.
- Le champ `children_ids` liste les sous-points appartenant à ce groupe.
- Les groupes ne sont **jamais votés**. Seuls leurs enfants (`is_group: false`) peuvent faire l'objet de votes.

**Points (`is_group: false`) :**

- Chaque sous-point représente une résolution potentiellement soumise au vote.
- Il peut comporter une majorité spécifique (`majority`) et une clé de répartition (`apportionment_key_id`) pour déterminer les droits de vote.
- Si `has_vote_required` est à `false`, le point est informatif uniquement.

### Génération d’une AG depuis un template (`AssemblyTemplate`)

Lors de la création d’une Assemblée Générale à partir d’un `AssemblyTemplate`, le système applique une série d’actions automatiques, tout en laissant à l’utilisateur une marge de personnalisation.

- Copie des `AssemblyItemTemplate` en `AssemblyItem`, en respectant la hiérarchie parent/enfant.
- Pré-remplissage du texte de convocation (`call_text_template`) dans la convocation générée.

Une fois les points de l’ordre du jour générés :

- L’utilisateur peut **réorganiser** les items à l’aide du champ `order`.
- Il peut **modifier ou supprimer** les points générés automatiquement.
- Il peut également **ajouter de nouveaux points manuellement**, en dehors du modèle initial.

## Convocation à l'assemblée générale

Les documents suivants sont envoyés selon le canal de communication choisi :

* convocation
* ordre du jour
* document template de procuration (prérempli)

Possibilité de le télécharger via espace client.
La date de la convocation est fixe (doit être de min. 15 jours avant la tenue de l'AG).

Il faut tenir à disposition l'éventuelle liste des annexes.

### `AssemblyInvitation`

```txt
class: AssemblyInvitation
fields:
  - ownership_id
  - owner_id
  - assembly_id
  - sent_date (datetime)
  - sent_method (enum: paper, email)
  - is_acknowledged (boolean)
```

## Rôle de l'Assemblée Générale

L’Assemblée Générale (AG) joue un rôle central dans la gestion de la copropriété.

### Points systématiques à chaque AG

- **Élection du président et du secrétaire de séance**
- **Nomination ou renouvellement des mandats**
- **Décharge du syndic sortant**
- **Fixation de la date et de l’heure de la prochaine AG ordinaire**

### Suivi du déroulement d'une Assemblée Générale

Étapes: `opening`, `attendance_encoding`, `attendance_closure`, `proxy_validation`, `representation_validation`, `assembly_validation`, `agenda_processing`.

### Présence `AssemblyAttendee`

```txt
class: AssemblyAttendee
fields:
  - assembly_id (link to Assembly)
  - identity_id (link to Identity)
  - ownerships_ids (many2many, link to represented Ownerships, min 1)
  - has_mandate (boolean)
  - assembly_proxies_ids (link to AssemblyProxy)
  - has_signed (boolean)
  - shares (decimal, computed)
```

### `AssemblyProxy`

```txt
class: AssemblyProxy
fields:
  - assembly_id (link to Assembly, required)
  - ownership_id (link to Ownership, required)
  - identity_id (link to Identity, required)
  - proxy_type (enum: written, email, mandate, notarial)
  - proxy_document_id (link to Document)
  - shares (decimal, computed or optional)
  - is_valid (boolean, default: true)
  - invalidity_reason (string)
```

## Validation d'une assemblée

Pour qu'une Assemblée puisse se tenir, il faut atteindre un double quorum:

* plus de 50% des copropriétaires
* au moins la moitié des quotités de l'acte de base

## Résolutions et logique des votes

### `AssemblyVote`

```txt
class: AssemblyVote
fields:
  - assembly_item_id (link to AssemblyItem)
  - assembly_attendee_id
  - has_proxy (bool)
  - ownership_id (link to Ownership)
  - value (enum: for, against, abstain)
  - weight (decimal, computed)
```

## Procès verbal

### `AssemblyMinutes`

```yaml
class: AssemblyMinutes
fields:
  - assembly_id (link to Assembly, unique, required)
  - heading_text (string, computed or editable)
  - closing_text (text, nullable)
  - original_document_id (link to Document)
  - signed_document_id (link to Document, nullable)
  - signatures_ids (DocumentSignatures)
  - minutes_entries_ids (hasMany: AssemblyMinutesEntry)
  - status (pending, ready, signed)
```

### `AssemblyMinutesEntry`

```yaml
class: AssemblyMinutesEntry
fields:
  - assembly_minutes_id (link to AssemblyMinutes, required)
  - assembly_item_id (link to AssemblyItem, required)
  - comment_text (text, optional)
  - has_vote_required (computed)
  - vote_validated (bool)
  - vote_details (json array, optional)
```

## Assemblée non valide - Seconde AG

- Nouvelle convocation avec délai minimum de 15 jours.
- Ordre du jour identique.

## Procès-Verbal de Carence

- Constate l’absence de quorum.
- Annonce l’organisation de la seconde séance.

## Récapitulatif des tâches suivant la tenue d'une AG

* lorsque l'AG a validé les comptes
  -> création tâche "comptes approuvés par l'AG" / "comptes non approuvés : à corriger"

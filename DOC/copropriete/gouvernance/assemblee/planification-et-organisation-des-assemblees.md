## Planification des assemblées générales

Les AG sont tenues sur base des prescriptions légales.

Possibilité de générer automatiquement un brouillon d'AG sur base de la date théorique (à la clôture d'une AG).

Note: lors d'une AG, la date de l'AG suivante peut être annoncée et/ou votée (nécessite un suivi: création tâche à faire).

### Workflow principal

| Statut | Transition / événement | Effets et objets liés |
| --- | --- | --- |
| `pending` — Brouillon | Création depuis un `Condominium` et sélection d'un modèle | L'assemblée est liée à la copropriété par `condo_id` et au modèle par `assembly_template_id`. Le modèle génère les `AssemblyItem` et leurs groupes, avec éventuellement un lien vers un `Apportionment`. L'organisateur est calculé depuis le rôle `condo_manager` ou le syndic. Le dossier documentaire parent est le dossier `general_meetings` de la copropriété. Des annexes peuvent déjà être liées à l'assemblée ou à ses points. |
| `published` — Prêt | `publish` | Création ou rafraîchissement du snapshot des `Ownership` dans `ownerships_ids`. Les propriétés sont déterminées à la date de l'assemblée depuis les `PropertyLotOwnership` actifs portant sur les lots principaux. Les compteurs de propriétaires et de quotes-parts sont invalidés pour être recalculés. |
| `sending` — Envoi en cours | `send` | Le snapshot des propriétés est recalculé. Les anciennes `AssemblyInvitationCorrespondence` sont remplacées par une correspondance pour chaque combinaison propriété, propriétaire représentant et canal de communication configuré. Les envois électroniques génèrent le document de convocation et l'e-mail planifié. Les envois postaux alimentent une `ExportingTask` et ses `ExportingTaskLine`. |
| `sent` — Envoyé | `sent` | Les correspondances non électroniques sont marquées comme envoyées avec leur date. Les correspondances, documents, e-mails et tâches d'export restent rattachés à l'assemblée. La transition contrôle que l'archive postale est prête et téléchargée. Le contrôle effectif du statut des e-mails n'est actuellement pas actif. |
| `in_progress` — En cours | `open` | Création du registre immuable `register_document_id` à signer et d'un `AssemblyAttendee` secrétaire lié à l'identité de l'organisateur. Participants, signatures, mandats, représentations, votes et procès-verbal sont ensuite gérés par le sous-workflow de séance. |
| `held` — Terminée / tenue | `close`, après signature du PV | La version finale signée du procès-verbal est disponible dans `signed_minutes_document_id`; le document original pointe vers elle via `signed_document_id`. Le registre signé reste disponible dans `signed_register_document_id`. L'envoi du PV crée des `AssemblyMinutesCorrespondence` et, selon le canal, un document et un e-mail ou une tâche d'export postal. |
| `adjourned` — Terminée / ajournée | `adjourn` | La transition conserve les participants, mandats, représentations, registre et points déjà traités. Si une seconde session est planifiée, une nouvelle `Assembly` en brouillon est reliée à la première; les points, groupes, paramètres de vote et traductions sont copiés. |

Le détail des étapes internes du statut `in_progress` est décrit dans [Suivi du déroulé d'une Assemblée et prises de votes](suivi-et-prise-de-votes.md).

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

Ses relations directes structurent les différentes étapes du workflow :

| Objet lié | Cardinalité / champ principal | Rôle |
| --- | --- | --- |
| `Condominium` | many-to-one — `condo_id` | Copropriété concernée |
| `Identity` | many-to-one calculé — `assembly_organizer_identity_id` | Personne physique qui organise l'assemblée pour le syndic |
| `AssemblyTemplate` | many-to-one — `assembly_template_id` | Modèle utilisé pour construire l'ordre du jour |
| `AssemblyItem` | one-to-many — `assembly_items_ids` | Groupes, points et résolutions |
| `Ownership` | many-to-many — `ownerships_ids` | Propriétés concernées, figées à la publication ou à l'envoi |
| `AssemblyAttendee` | one-to-many — `assembly_attendees_ids` | Participants physiques |
| `AssemblyMandate` | one-to-many — `assembly_mandates_ids` | Procurations présentées |
| `AssemblyRepresentation` | one-to-many — `assembly_representations_ids` | Lien effectif entre un participant et une propriété représentée |
| `AssemblyVote` | one-to-many — `assembly_votes_ids` | Votes exprimés pendant le traitement des points |
| `AssemblyMinutesEntry` | one-to-many — `assembly_minutes_entries_ids` | Entrées constitutives du procès-verbal |
| `AssemblyInvitationCorrespondence` | one-to-many — `assembly_invitation_correspondences_ids` | Convocations individualisées |
| `AssemblyMinutesCorrespondence` | one-to-many — `assembly_minutes_correspondences_ids` | Envois individualisés du procès-verbal |
| `ExportingTask` | one-to-many et deux many-to-one spécifiques | Archives postales des convocations et du procès-verbal |
| `Document` | plusieurs many-to-one et one-to-many | Registre, procès-verbal, versions signées et annexes |
| `Node` | many-to-one calculé — `parent_node_id` | Dossier GED `general_meetings` |
| `Assembly` | `related_assembly_id` et `second_session_assembly_id` | Relation entre première et seconde session |

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

### Correspondances de convocation

La publication fige d'abord les `Ownership` concernées dans `ownerships_ids`. Lors de la création des convocations, ce snapshot est rafraîchi, puis les anciennes `AssemblyInvitationCorrespondence` sont supprimées et régénérées.

Une correspondance relie l'`Assembly`, l'`Ownership`, son `representative_owner_id` et un canal défini dans `OwnershipCommunicationPreference` :

- pour l'e-mail, le système planifie l'envoi, génère un `Document` de convocation et crée l'objet mail lié à la correspondance ;
- pour le courrier postal, il crée une `ExportingTask`, ses `ExportingTaskLine`, puis la relie à l'assemblée par `invitations_exporting_task_id`.

Après cette génération, `has_invitations_sent` indique que l'envoi a été préparé. Au passage au statut `sent`, les correspondances non électroniques reçoivent `is_sent` et `sent_date` ; les documents, e-mails et tâches d'export restent accessibles depuis l'assemblée.

## Rôle de l'Assemblée Générale

L’Assemblée Générale (AG) joue un rôle central dans la gestion de la copropriété.

### Points systématiques à chaque AG

- **Élection du président et du secrétaire de séance**
- **Nomination ou renouvellement des mandats**
- **Décharge du syndic sortant**
- **Fixation de la date et de l’heure de la prochaine AG ordinaire**

### Suivi du déroulement d'une Assemblée Générale

Le statut `in_progress` suit les étapes `opening`, `attendance_closure`, `mandate_validation`, `representation_validation`, `assembly_validation`, `agenda_processing`, `minutes_confirmation`, `minutes_signing` et `assembly_closing`.

La valeur `attendance_closure` existe dans la sélection, mais le traitement `close_attendance` passe actuellement directement de `opening` à `mandate_validation`. Le détail des objets créés à chaque étape est documenté dans [Suivi du déroulé d'une Assemblée et prises de votes](suivi-et-prise-de-votes.md).

### Participants, mandats et représentations

Un `AssemblyAttendee` représente une personne physique participant à la séance. Il est lié à une `Identity`, éventuellement à un `User`, et à une `DocumentSignature` portant sur le registre de présence.

Les procurations sont portées par les `AssemblyMandate`. Chaque mandat relie l'assemblée, le participant mandaté et l'`Ownership` représentée; il peut aussi contenir un document de mandat et des `AssemblyVoteIntention`. Pendant la validation, les mandats encore en brouillon sont supprimés et les autres sont contrôlés selon le propriétaire, la signature, les limites de procurations et les quotes-parts.

Les `AssemblyRepresentation` matérialisent ensuite la représentation effective d'une `Ownership` par un participant. Elles sont régénérées lors de la validation des représentations. La présence directe d'un propriétaire prévaut sur sa représentation par procuration.

## Validation d'une assemblée

Pour qu'une Assemblée puisse se tenir, il faut atteindre un double quorum:

* plus de 50% des copropriétaires
* au moins la moitié des quotités de l'acte de base

Si au moins 75% des quotes-parts sont représentées, le quorum est atteint sans devoir contrôler la proportion de copropriétaires. Une seconde session n'est pas soumise à ce quorum de présence. Voir [Vérification du quorum de présence](suivi-et-prise-de-votes.md#vérification-du-quorum-de-présence-assemblée-générale-acp--belgique).

## Résolutions et logique des votes

### `AssemblyVote`

À l'ouverture d'un point soumis au vote, des `AssemblyVote` sont synchronisés pour les `Ownership` représentées et concernées par l'`Apportionment` du point. Chaque vote relie l'assemblée, le point, le participant et la propriété; il peut aussi référencer une `AssemblyItemChoice`. Une `AssemblyVoteIntention` valide peut alimenter automatiquement ce vote.

## Procès verbal

### `AssemblyMinutesEntry`

Les `AssemblyMinutesEntry` sont liées directement à l'assemblée et au point traité. Elles stockent le texte et la synthèse du vote destinés au procès-verbal.

Quand tous les points sont fermés ou ajournés, l'assemblée est marquée comme complète. La confirmation crée `minutes_document_id`, version originale et immuable du procès-verbal. Le président et le secrétaire signent ce document au moyen de `DocumentSignature`, chacune étant aussi liée au participant concerné par `minutes_document_signature_id`.

La clôture exige au minimum les signatures du président et du secrétaire. Elle produit `signed_minutes_document_id`, classe ce document final dans le dossier `parent_node_id`, puis relie la version originale à la version signée par `signed_document_id`.

L'envoi du procès-verbal crée une `AssemblyMinutesCorrespondence` pour chaque combinaison `Assembly + Ownership + Owner + canal`. Le canal postal utilise `minutes_exporting_task_id` et des `ExportingTaskLine`; le canal électronique génère un document individuel du procès-verbal et un e-mail attaché à la correspondance. `has_minutes_sent` indique que cet envoi a été planifié ou généré.

## Assemblée non valide - Seconde AG

- Nouvelle convocation avec délai minimum de 15 jours.
- Ordre du jour identique.
- La première assemblée pointe vers la seconde par `second_session_assembly_id`; la seconde pointe vers la première par `related_assembly_id`.
- Les `AssemblyItem`, leurs groupes, les paramètres de vote et les traductions sont copiés dans la nouvelle assemblée en statut `pending`.

## Procès-Verbal de Carence

- Constate l’absence de quorum.
- Annonce l’organisation de la seconde séance.

## Récapitulatif des tâches suivant la tenue d'une AG

* lorsque l'AG a validé les comptes
  -> création tâche "comptes approuvés par l'AG" / "comptes non approuvés : à corriger"

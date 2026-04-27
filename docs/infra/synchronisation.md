# Synchronisation des données

## 1. Objectif général

Le système de **synchronisation** vise à harmoniser les données entre les instances locales (ex. syndics) et l'instance globale (FMT), tout en **préservant la responsabilité des données locales**.

👉 La position FMT :

> La responsabilité des données reste du ressort des **syndics**.
> Le système facilite la mise à jour, mais **n’impose jamais** de modification automatique sur les champs sensibles.



## 1.5 Initialisation d'instance et bootstrap de synchronisation

Cette section regroupe la logique d'initialisation qui a un impact direct sur la synchronisation.

### Initialisation d'une instance global

Action de référence : `do fmt_init_instance_global`.

Séquence :

1. vérifie `FMT_INSTANCE_TYPE = global`,
2. vérifie que `fmt` n'est pas déjà initialisé,
3. initialise successivement `core`, `identity`, `communication`, puis `fmt`,
4. optionnel : charge les données de démonstration (`demo=true`).

### Initialisation d'une instance agency

Action de référence : `do fmt_init_instance_agency`.

Séquence :

1. vérifie `FMT_INSTANCE_TYPE = agency`,
2. initialise `core`, `identity`, `communication`, puis `fmt`,
3. si `instance_uuid` fourni : enregistre l'UUID sur `Instance(1)`,
4. si `sync=true` :
   - garantit l'existence de l'entrée d'instance globale locale,
   - récupère les SyncPolicy depuis global (`do fmt_sync_SyncPolicy_pull-from-global --reset=true`),
   - lance un premier pull de données (`do fmt_sync_pull-from-global --accept=true`).

### Flux de synchronisation lancés au bootstrap

#### A. Synchronisation des politiques

`fmt_sync_SyncPolicy_pull-from-global` :

- exécutable côté agency,
- récupère les SyncPolicy/lines/conditions depuis global,
- nettoie les champs techniques avant création locale,
- peut fonctionner en reset complet.

#### B. Synchronisation des données descendantes

`fmt_sync_pull-from-global` :

- travaille policy par policy (`scope` protected/private, direction descending),
- interroge global depuis `last_sync`,
- tente un matching local (uuid, champs uniques, cas spécial `slug_hash` pour `identity\Identity`),
- crée des `UpdateRequest` et `UpdateRequestLine`,
- applique automatiquement les updates selon scope/options (`accept`, `unsupervised`, `forced`),
- met à jour `last_sync` à la fin de chaque policy.

> Pour le contrôle de conformité post-initialisation (check-init), voir [Vérification d'initialisation d'une instance](verification-initialisation-et-logique-de-synchronisation.md).

## 2. Suivi des demandes de modification

Les demandes de modification permettre de suivre et gérer les synchronisations qui sont proposées pour une instance Global ou Local.

Elles peuvent être acceptées **automatiquement** ou **manuellement** en fonction de :
- la configuration de synchronisation de l'entité
- la sensibilité des champs concernés

### Requête de mise à jour `UpdateRequest`

| Champ                      | Description                                                                         |
|----------------------------|-------------------------------------------------------------------------------------|
| `object_class`             | Classe de l’objet concerné                                                          |
| `object_id`                | Si création, alors identifiant de l'object concerné                                 |
| `object_name`              | Nom de l'object concerné                                                            |
| `is_new`                   | Concerne une création d'un nouvel objet                                             |
| `instance_id`              | Identifiant de l’instance locale                                                    |
| `managing_agent_id`        | Référence du syndic ou agent responsable                                            |
| `request_date`             | Date de la demande                                                                  |
| `source_type`              | Type de source (`locale`, `globale`, etc.)                                          |
| `source_origin`            | Origine de la donnée (`system`, `user`, `integration`, etc.)                        |
| `approval_user_id`         | Utilisateur ayant approuvé                                                          |
| `approval_reason`          | Motif d’approbation (`unsupervised`, `verified`)                                    |
| `rejection_user_id`        | Utilisateur ayant rejeté                                                            |
| `rejection_reason`         | Motif de rejet (`conflict`, `incorrect_data`, `outdated_data`, `duplicate_request`) |
| `status`                   | Status (`pending`, `approved`, `rejected`)                                          |
| `update_request_lines_ids` | Liste des valeurs des champs à synchroniser                                         |

### Valeur de requête de mise à jour `UpdateRequestLine`

| Champ               | Description                                  |
|---------------------|----------------------------------------------|
| `update_request_id` | Référence à `updateRequest`                  |
| `object_class`      | Classe de l'object concerné                  |
| `object_field`      | Champ de l’objet concerné                    |
| `is_new`            | Concerne une création d'un nouvel objet      |
| `new_value`         | Nouvelle valeur proposée                     |
| `old_value`         | Ancienne valeur (si modification d'un objet) |



## 3. Configuration de la synchronisation

Les politiques de synchronisation **SyncPolicy** déterminent la sensibilité et le comportement de synchro **par entité et par champ**.

### Politiques de synchronisation `SyncPolicy`

| Champ                    | Description                                                                                  |
|--------------------------|----------------------------------------------------------------------------------------------|
| `object_class`           | Classe concernée                                                                             |
| `field_unique`           | Champ à utiliser pour déterminer si un objet est déjà présent ou non (pour assignation UUID) |
| `sync_direction`         | Direction de la synchro : `ascending` (Local -> Global) ou `descending` (Global -> Local)    |
| `last_pull`              | Moment de la dernière synchronisation                                                        |
| `scope`                  | Niveau de visibilité de la classe (`protected`, `private`)                                   |
| `sync_policy_lines_ids`  | Liste des règles de synchronisation des champs                                               |

**Unique** sur `object_class` et `sync_direction`, donc deux politiques possibles pour chaque entité.

> Notes :
> * Si **pas de politique** de synchronisation pour une entité, alors elle n'est pas synchronisée.
> * Si seulement **une politique descendante** pour une entité, alors la synchronisation est seulement de `Global vers Local`. Par exemple si des données sont modifiables sur l'instance Global, mais pas sur les instances Local.
> * Si **une politique descendante et une ascendante** pour une entité, alors synchronisation est de `Global vers Local` et de `Local vers Global`.
> * Le cas d'une politique ascendante sans politique descendante ne semble pas pertinent. Mais pourrait être utilisée dans une situation très spécifique et rare.

#### Niveau de visibilité d'une classe

| Scope       | Description                                                                                                                 |
|-------------|-----------------------------------------------------------------------------------------------------------------------------|
| `private`   | Classe gérée exclusivement sur l'instance Globale, pas de modification possible par les instances Locales.                  |
| `protected` | Classe gérée sur l'instance Globale, mais pouvant faire l'objet de créations ou de modifications par les instances Locales. |

> Notes :
> * Une synchronisation descendante ou ascendante va créer des requêtes de mise à jour pour chaque création ou modification d'un objet.
> * Ces requêtes peuvent être acceptées automatiquement ou manuellement. Cette création d'une requête permet de garder un historique des modifications réalisées par les synchronisations.

| Scope       | Sync direction | Autorisé |      Requêtes de mise à jour       |
|-------------|----------------|:--------:|:----------------------------------:|
| `private`   | `descending`   |    X     |     acceptées automatiquement      |
| `private`   | `ascending`    |          |                 -                  |
| `protected` | `descending`   |    X     | à accepter manuellement sur Local  |
| `protected` | `ascending`    |    X     | à accepter manuellement sur Global |

> Notes :
> * Pour une **SyncPolicy** de scope `private` la direction de synchronisation ne peut pas être ascendante.
> * Pour une **SyncPolicy** de scope `private` les champs `id` et `*_id` peuvent être synchronisés (many2one, many2many), car les objets sont uniquement gérés (création/modification) sur l'instance **Global**.

### Champ de politiques de synchronisation `SyncPolicyLine`

| Champ            | Description                                                                               |
|------------------|-------------------------------------------------------------------------------------------|
| `sync_policy_id` | Référence à la politique de synchronisation                                               |
| `sync_direction` | Direction de la synchro : `ascending` (Local -> Global) ou `descending` (Global -> Local) |
| `object_class`   | Classe concernée                                                                          |
| `object_field`   | Champ concerné                                                                            |
| `scope`          | Niveau de visibilité du champ (`public`, `protected`, `private`)                          |

#### Niveau de visibilité d'un champ

| Scope       | Synchronisation                                                                 |
|-------------|---------------------------------------------------------------------------------|
| `private`   | Inexistante                                                                     |
| `protected` | **Supervisée** (via `UpdateRequest`)                                            |
| `public`    | **Automatique** (via `UpdateRequest` avec `approval_reason` = `'unsupervised'`) |

> Notes :
> * Sur les instances Locals, **tous les champs reçus sont considérés comme `protected`** par défaut (et nécessitent donc une validation manuelle).
> * Sur l'instance Global, seuls les champs explicitement marqués comme `public` peuvent être synchronisés sans supervision.



## 4. Types de synchronisation

Il existe deux types de synchronisations, qui sont toujours déclenché par une instance Local :
- Synchro ascendante : envoie des données de l'instance Local vers l'instance Global 
- Synchro descendante : récupération des données de l'instance Global par une instance Local

### 🔼 Synchro ascendante (Local → Global)

- Il n'existe pas de mises à jour ascendantes pour les entité `private`
- Les mises à jour descendantes pour les entités `protected` **sont appliquées automatiquement ou manuellement** en fonction des champs concernés :
  - Si **un des champs** qui doit être mis à jour est configuré comme `protected`, alors la mise à jour doit être appliquée **manuellement**
  - Si **tous les champs** qui doivent être mis à jour sont configurés comme `public`, alors la mise à jour est appliquée **automatiquement**
  - Les champs configurés comme `private` sont ignorés

#### Règles spécifiques :

| Scope       | Comportement Local                | Comportement Global                                                            |
|-------------|-----------------------------------|--------------------------------------------------------------------------------|
| `private`   | Champs ignoré                     | Jamais envoyées vers l'instance globale                                        |
| `protected` | Transmises vers l'instance global | Si un champs `protected` acceptation manuelle requise de l'`UpdateRequest`     |
| `public`    | Transmises vers l'instance global | Si uniquement des champs `public` acceptation automatique de l'`UpdateRequest` |

### 🔽 Synchro descendante (Global → Local)

- Les mises à jour descendantes pour les entités `private` **sont toujours appliquées automatiquement**
- Les mises à jour descendantes pour les entités `protected` **sont appliquées automatiquement ou manuellement** en fonction des champs concernés :
  - Si **un des champs** qui doit être mis à jour est configuré comme `protected`, alors la mise à jour doit être appliquée **manuellement**
  - Si **tous les champs** qui doivent être mis à jour sont configurés comme `public`, alors la mise à jour est appliquée **automatiquement**
  - Les champs configurés comme `private` sont ignorés

#### Règles spécifiques :

| Scope       | Comportement Global                        | Comportement Local                                                             |
|-------------|--------------------------------------------|--------------------------------------------------------------------------------|
| `private`   | Jamais envoyées vers les instances locales | Champs ignoré                                                                  |
| `protected` | Transmises vers les instances locales      | Si un champs `protected` acceptation manuelle requise de l'`UpdateRequest`     |
| `public`    | Transmises vers les instances locales      | Si uniquement des champs `public` acceptation automatique de l'`UpdateRequest` |

> Notes :
> * Un champ `protected` ou `public` sur Global **peut** être définis comme `private` sur Local, la valeur est donc **retournée par l'instance Global**, mais est **ignorée sur l'instance Local**.
> * Un champ `private` sur Global **ne peut pas** être définis comme `protected` ou `public` sur Local, car la valeur n'est **pas retournée par l'instance Global**.


## 5. Logique applicative : encodage et supervision

Lorsqu’un utilisateur encode ou modifie une donnée sur une instance Locale pour une classe `protected`:

- le système **propose la valeur connue au niveau Global**, si elle existe ;
- une **alerte** est affichée si la valeur saisie diffère du Global ;
- une **UpdateRequest** est créée pour suivi et validation éventuelle sur l'instance Globale.



## 6. Exemples de flux

### Exemple 1 – Champ public

Un champ `registration_date` (scope `public`) est mis à jour dans une instance locale.
 → Pas de synchro.

### Exemple 2 – Champ protégé

Un champ `address_street` (scope `protected`) est modifié.
 → Une `UpdateRequestLine` est envoyée vers l'instance Global avec `status = pending` 
 → Après vérification, un utilisateur Global valide (`approval_user_id`) ou rejette (`rejection_user_id`).

### Exemple 3 – Champ privé

Un champ `bank_account_iban` est modifié localement.
 → Non transmis vers Global (aucune synchro).



## 7. Rejets possibles

| Code                  | Signification                                 |
|-----------------------|-----------------------------------------------|
| `conflict`            | Conflit entre version locale et globale       |
| `incorrect_data`      | Données incohérentes ou erronées              |
| `outdated_data`       | Valeur basée sur une source ancienne          |
| `duplicate_request`   | Demande déjà enregistrée                      |
| `unauthorized_change` | Modification non autorisée selon la politique |



## Suivis

* Sur une base régulière un controller (cron) pourrait vérifier la synchronicité des valeurs entre une instance Locale et les valeurs correspondantes sur l'instance Globale 

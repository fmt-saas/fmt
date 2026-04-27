# Vérification d'initialisation d'une instance

## Objectif

Cette page documente le **contrôle d'initialisation** d'une instance **agency** depuis l'instance **global**.

Elle couvre :
- le point d'entrée de vérification côté global,
- le point d'entrée de collecte côté agency,
- la structure du rapport (`warnings`, `errors`, `logs`),
- les recommandations de remédiation.

> Pour la logique de synchronisation (politiques, flux ascendants/descendants, `UpdateRequest`, premier pull), voir la page dédiée : [Synchronisation des données](synchronisation.md).

---

## 1) Vue d'ensemble du mécanisme

Le contrôle est organisé en mode **client/serveur** :

1. L'instance **global** exécute `do fmt_sync_check-init`.
2. Cette action appelle l'instance **agency** sur `fmt_sync_init-data` (HTTP GET authentifié par Bearer token interne).
3. L'instance agency renvoie un snapshot structuré de ses données d'initialisation.
4. L'instance global compare ce snapshot avec ses propres références et retourne un rapport de conformité (`warnings`, `errors`, `logs`).

---

## 2) Action globale `do fmt_sync_check-init`

### Rôle

Valider qu'une instance agency est correctement initialisée vis-à-vis des exigences FMT (packages, données de référence, identité principale, rôles et settings).

### Préconditions

- L'action doit être exécutée sur une instance de type `global`.
- L'identifiant de l'instance cible (`id`) est obligatoire.
- Un token API interne doit exister pour l'instance agency ciblée (mapping par nom d'instance).

### Paramètres

- `id` (required) : many2one vers `infra\server\Instance`.
- `log_level` (optional) :
  - `warning` (défaut) : inclut warnings + errors.
  - `error` : conserve uniquement les erreurs.

### Étapes de traitement

#### Étape A — Chargement de la configuration de contrôle

Le fichier `fmt/init/config/check-init-config.json` est chargé pour définir :

- la liste des packages attendus,
- les entités à comparer,
- les champs uniques,
- les champs critiques (`error_fields`) et non bloquants (`warning_fields`),
- les contraintes sur identité principale, rôles et settings.

#### Étape B — Appel de l'agence

L'action appelle l'URL de l'instance agency :

- `GET /?get=fmt_sync_init-data`
- avec `Authorization: Bearer <token interne>`.

En cas de réponse non 200, le contrôle échoue.

#### Étape C — Vérifications réalisées

1. **Packages** : vérifie la présence de tous les packages requis.
2. **Entités** :
   - construit un domaine à partir des conditions de SyncPolicy descendantes (sauf pour l'entité SyncPolicy elle-même),
   - lit les objets côté global,
   - compare avec les objets remontés par l'agency via une logique basée sur `unique_fields`.
3. **Relations imbriquées** : vérification récursive des sous-objets configurés dans `relations`.
4. **Identité principale** :
   - présence de l'identité,
   - conformité des valeurs obligatoires,
   - détection de valeurs encore par défaut,
   - présence des relations obligatoires.
5. **Role assignments globaux** : vérifie la présence des rôles attendus au niveau global (`condo_id = null`).
6. **Settings** : compare les réglages non spécifiques copropriété (avec exclusions explicites).

> Le contrôle de vérification s'appuie sur des éléments de synchronisation (notamment les SyncPolicy descendantes) pour déterminer le périmètre attendu. La logique détaillée de synchronisation reste documentée dans [Synchronisation des données](synchronisation.md).

#### Étape D — Niveau de log

- Si `log_level = error`, les lignes `WARN` sont filtrées.
- Le résultat expose :
  - `warnings` (si mode warning),
  - `errors`,
  - `logs`.

### Format des logs

- Préfixe `ERR - ...` : non-conformité bloquante.
- Préfixe `WARN - ...` : écart non bloquant.

---

## 3) Endpoint agence `get fmt_sync_init-data`

### Rôle

Retourner au global un snapshot de l'état d'initialisation de l'instance agency.

### Préconditions

- Endpoint exécutable uniquement sur une instance de type `agency`.
- Vérifie et exploite le même fichier `check-init-config.json` pour produire un payload compatible avec les contrôles du global.

### Données retournées

Le payload contient :

- `packages` : packages initialisés localement,
- `entities` : données des entités déclarées dans la config,
- `main_identity` : identité principale (`Identity::id(1)`),
- `role_assignments` : rôles sans copropriété (`condo_id = null`),
- `settings` : settings globaux filtrés (hors settings dépendants de numéros et hors liste d'exclusion).

### Détail de collecte des entités

Pour chaque entité configurée :

1. récupération de la SyncPolicy descendante,
2. conversion des conditions de policy en domaine ORM,
3. lecture de tous les champs du schéma,
4. lecture enrichie des relations déclarées dans `relations`.

Ce comportement garantit que global et agency comparent le même périmètre logique.

---

## 4) Recommandations opérationnelles

1. Après initialisation d'une agency synchronisée, exécuter immédiatement `do fmt_sync_check-init` depuis global.
2. Corriger en priorité les `ERR` (bloquants).
3. Traiter ensuite les `WARN` pour homogénéiser les référentiels.
4. Rejouer le contrôle après correction jusqu'à obtenir `errors = 0`.

---

## 5) Exemple d'usage

### Depuis global

- `do fmt_sync_check-init --id=<instance_id> --log_level=warning`

### Depuis agency (appelé par global)

- `get fmt_sync_init-data`

---

## 6) Documentation liée

- [Synchronisation des données](synchronisation.md)
- [Authentification](authentification.md)

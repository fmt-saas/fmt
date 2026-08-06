# Quotas

## Objectif

Le mécanisme de quotas sert à suivre et éventuellement limiter certaines consommations d'une instance FMT.

Il couvre par exemple :

- le nombre de lots principaux ;
- le nombre de garages ou parkings ;
- le nombre de documents stockés dans l'EDMS ;
- le volume de stockage EDMS ;
- le nombre d'utilisateurs actifs ;
- le nombre d'appels Google Document AI ;
- le nombre d'e-mails envoyés ;
- la taille de la base de données.

Pour les PO, un quota répond à deux questions :

1. quelle consommation veut-on suivre ?
2. à partir de quelle limite faut-il alerter ou bloquer ?

Pour les DEV, le quota est une couche d'orchestration entre :

- une définition de métrique (`infra\metering\MetricDefinition`) ;
- un quota opérationnel (`infra\quota\Quota`) ;
- un ou plusieurs seuils (`infra\quota\QuotaThreshold`) ;
- des collecteurs de valeur (`infra_metering_*`) ;
- des contrôleurs de disponibilité (`infra_quota_check-*`) ;
- des actions déclenchées lorsque des seuils sont atteints (`infra_quota_handle-*`).

---

## Vue d'ensemble

Le système distingue deux moments de contrôle.

### 1. Vérification avant une opération

Avant certaines créations ou opérations sensibles, le code appelle :

```php
Quota::search([['code', '=', '<quota_code>']])
    ->do('check-availability', $values);
```

Cette vérification est utilisée comme garde-fou applicatif. Elle doit rester légère, car elle est exécutée dans des chemins critiques comme la création d'un document, d'un utilisateur ou d'un lot.

Elle ne doit pas recalculer toute la consommation réelle. Elle travaille principalement avec la valeur stockée du quota et les informations du candidat en cours de création.

### 2. Recalcul périodique de l'état réel

La tâche `infra_quota_refresh-values` rafraîchit les valeurs des quotas actifs via les collecteurs, puis vérifie les seuils.

Ce recalcul est plus coûteux, mais il est exécuté en tâche de fond ou via un flux d'administration. Il sert à maintenir l'état global cohérent avec les données réelles de l'instance.

---

## Modèle fonctionnel

### `MetricDefinition`

Une `MetricDefinition` décrit ce qui est mesuré.

Champs principaux :

| Champ | Rôle |
| --- | --- |
| `code` | Code stable de la métrique, par exemple `edms.storage.size`. |
| `name` | Libellé métier de la métrique. |
| `unit` | Unité de mesure : `count`, `bytes`, `calls`. |
| `collector` | Contrôleur `get` capable de calculer la valeur réelle. |
| `is_active` | Indique si la métrique est active. |

Le collecteur doit retourner une structure contenant au minimum :

```php
[
    'value' => <integer>
]
```

### `Quota`

Un `Quota` applique une logique de suivi ou de blocage à une métrique.

Champs principaux :

| Champ | Rôle |
| --- | --- |
| `metric_definition_id` | Métrique suivie par le quota. |
| `code` | Code calculé depuis la métrique. |
| `quota_type` | `instant` ou `period`. |
| `period_duration` | Durée de période : `day`, `week`, `month`, `year`. |
| `period_start` / `period_end` | Bornes de période pour les quotas périodiques. |
| `value` | Dernière valeur connue du quota. |
| `is_reached` | Etat agrégé : au moins un seuil bloquant est atteint. |
| `is_active` | Si faux, le quota est ignoré par les contrôles. |
| `availability_controller` | Contrôleur spécialisé pour vérifier un candidat. |
| `thresholds_ids` | Seuils associés au quota. |

`is_reached` est volontairement agrégé. Il ne dit pas quel seuil précis a été atteint. Il indique seulement que le quota est actuellement dans un état bloquant.

### `QuotaThreshold`

Un `QuotaThreshold` décrit une limite à partir de laquelle une action peut être exécutée et/ou le quota peut devenir bloquant.

Champs principaux :

| Champ | Rôle |
| --- | --- |
| `quota_id` | Quota concerné. |
| `threshold_type` | `blocking` ou `non_blocking`. |
| `value` | Valeur minimale à atteindre. |
| `max_value` | Valeur maximale optionnelle pour limiter la plage de déclenchement. |
| `action` | Contrôleur `do` à appeler lorsque le seuil se déclenche. |
| `trigger_policy` | Politique de déclenchement de l'action : `on_reach` ou `always`. |

`threshold_type = blocking` influence `Quota.is_reached`.

`threshold_type = non_blocking` permet de déclencher une action sans bloquer directement la fonctionnalité.

---

## Types de quota

### Quota instantané

Un quota `instant` mesure l'état courant de l'instance.

Exemples :

- nombre de documents EDMS ;
- volume de stockage EDMS ;
- nombre d'utilisateurs ;
- nombre de lots principaux ;
- taille de la base de données.

La valeur représente l'état actuel observé au dernier refresh.

### Quota périodique

Un quota `period` mesure une consommation cumulée sur une période.

Exemples :

- appels Google Document AI sur la semaine ;
- e-mails envoyés sur la semaine.

Les champs `period_start` et `period_end` définissent la fenêtre utilisée par le collecteur.

L'action `infra_quota_shift-periods` décale les périodes expirées vers la période courante et remet `is_reached` à `false`. Cela permet à un quota périodique de redevenir consommable au début d'une nouvelle période.

---

## Contrôle de disponibilité

### Rôle

`check-availability` répond à la question :

> L'opération candidate est-elle autorisée compte tenu de l'état actuel du quota ?

Il est utilisé dans des méthodes comme `cancreate()` avant de créer un objet.

Exemples de consommateurs :

- création d'un `documents\Document` ;
- création d'un `identity\User` ;
- création d'un `realestate\property\PropertyLot` ;
- exécution du spool d'e-mails.

### Logique générale

Pour chaque quota ciblé :

1. si `is_active = false`, le quota est ignoré ;
2. si aucun `availability_controller` n'est configuré :
   - `is_reached = true` bloque l'opération ;
   - sinon l'opération est autorisée par ce quota ;
3. si un `availability_controller` est configuré :
   - le contrôleur spécialisé reçoit le code du quota, le delta et les valeurs candidates ;
   - s'il retourne `allowed = false`, l'opération est bloquée.

Cette approche évite d'appeler systématiquement les collecteurs complets. Le contrôle avant opération reste donc rapide.

### Contrôleurs spécialisés

Les contrôleurs spécialisés sont des providers `get` placés dans `packages/infra/data/quota`.

Ils servent à gérer les cas où tous les candidats ne consomment pas réellement le quota.

Exemples :

- un utilisateur système ne doit pas compter dans `auth.users.count` ;
- un lot secondaire ne doit pas compter dans `property.main_lots.count` ;
- un lot qui n'est pas garage/parking ne doit pas compter dans `property.parkings.count` ;
- un document EDMS consomme à la fois un compteur de documents et un volume de stockage.

Contrat attendu :

```php
[
    'allowed'         => true|false,
    'reason'          => null|'quota_unavailable',
    'code'            => '<quota_code>',
    'value'           => <current_value>,
    'delta'           => <candidate_delta>,
    'projected_value' => <value + delta>,
    'threshold_id'    => null|<id>
]
```

Le provider doit rester léger. Il ne doit pas recalculer toute la métrique via le collecteur ; il se base sur la valeur stockée du quota et sur les valeurs candidates.

---

## Rafraîchissement des valeurs

### Action `refresh-value`

L'action ORM `refresh-value` est portée par `Quota`.

Elle :

1. lit la métrique associée ;
2. appelle le collecteur configuré dans `MetricDefinition.collector` ;
3. transmet `period_start` et `period_end` pour les quotas périodiques ;
4. met à jour `Quota.value` avec la valeur retournée.

### Action `check-thresholds`

Après rafraîchissement, `check-thresholds` évalue les seuils du quota.

Pour chaque seuil :

1. le seuil est considéré comme atteint si `Quota.value >= QuotaThreshold.value` ;
2. l'action du seuil n'est possible que si `max_value` est vide ou si `Quota.value <= max_value` ;
3. si le seuil est `blocking`, le quota devient `is_reached = true` ;
4. si les conditions de déclenchement sont remplies, le contrôleur `QuotaThreshold.action` est appelé.

L'état `Quota.is_reached` est ensuite synchronisé avec le résultat global des seuils bloquants.

---

## Politique de déclenchement des actions

Le champ `QuotaThreshold.trigger_policy` contrôle quand l'action du seuil est exécutée.

### `on_reach`

Politique par défaut.

L'action est déclenchée uniquement si le quota n'était pas déjà `is_reached` avant le check.

Cette politique évite de rejouer une action à chaque tâche de refresh lorsque le quota reste au-dessus de la limite.

Elle convient pour :

- des notifications ;
- des alertes de dépassement ;
- des opérations qui ne doivent pas être répétées inutilement.

Limite connue : `is_reached` est agrégé au niveau du quota. Si plusieurs seuils existent sur le même quota, `on_reach` ne distingue pas quel seuil précis a déjà été franchi.

### `always`

L'action est déclenchée à chaque `check-thresholds` tant que le seuil correspond.

Cette politique convient uniquement aux actions idempotentes ou volontairement répétables.

Exemples :

- resynchroniser un état technique ;
- recréer une alerte si le système en dépend ;
- exécuter une vérification périodique non destructive.

---

## Actions de seuil

Les actions de seuil sont des contrôleurs `do`, par exemple :

- `infra_quota_handle-property-main-lots-count-reached`
- `infra_quota_handle-property-parkings-count-reached`
- `infra_quota_handle-edms-document-count-reached`
- `infra_quota_handle-edms-storage-size-reached`
- `infra_quota_handle-google-docai-calls-count-reached`
- `infra_quota_handle-instance-users-count-reached`
- `infra_quota_handle-mail-outbound-count-reached`
- `infra_quota_handle-db-storage-size-reached`

Elles sont prévues pour centraliser les effets liés à un dépassement : notification, log, alerte, mise à jour d'état ou action administrative.

Par convention, elles doivent être idempotentes si le seuil utilise `trigger_policy = always`.

---

## Quotas configurés

| Code | Type | Collecteur | Disponibilité spécialisée | Action de seuil |
| --- | --- | --- | --- | --- |
| `property.main_lots.count` | `instant` | `infra_metering_read-property-main-lots-count` | `infra_quota_check-property-main-lots-count-availability` | `infra_quota_handle-property-main-lots-count-reached` |
| `property.parkings.count` | `instant` | `infra_metering_read-property-parkings-count` | `infra_quota_check-property-parkings-count-availability` | `infra_quota_handle-property-parkings-count-reached` |
| `edms.document.count` | `instant` | `infra_metering_read-edms-document-count` | `infra_quota_check-edms-document-count-availability` | `infra_quota_handle-edms-document-count-reached` |
| `edms.storage.size` | `instant` | `infra_metering_read-edms-storage-size` | `infra_quota_check-edms-storage-size-availability` | `infra_quota_handle-edms-storage-size-reached` |
| `google.docai.calls.count` | `period` | `infra_metering_read-google-docai-calls-count` | - | `infra_quota_handle-google-docai-calls-count-reached` |
| `auth.users.count` | `instant` | `infra_metering_read-instance-users-count` | `infra_quota_check-auth-users-count-availability` | `infra_quota_handle-instance-users-count-reached` |
| `email.outbound.count` | `period` | `infra_metering_read-mail-outbound-count` | - | `infra_quota_handle-mail-outbound-count-reached` |
| `db.storage.size` | `instant` | `infra_metering_read-db-storage-size` | - | `infra_quota_handle-db-storage-size-reached` |

---

## Tâches planifiées et santé de l'instance

Les tâches suivantes sont attendues dans le contrôle de santé de l'instance :

- `infra_quota_refresh-values`
- `infra_quota_shift-periods`

`infra_quota_refresh-values` maintient les valeurs et seuils à jour.

`infra_quota_shift-periods` réinitialise les fenêtres temporelles des quotas périodiques expirés.

Ces tâches sont vérifiées par `infra_server_refresh-self-status`.

---

## Recommandations de configuration

### Pour les PO

- Définir une limite claire par quota actif.
- Utiliser un seuil bloquant uniquement lorsqu'un dépassement doit empêcher l'opération.
- Préférer `trigger_policy = on_reach` pour les notifications ou alertes.
- Utiliser `trigger_policy = always` seulement si l'action peut être rejouée sans effet indésirable.
- Documenter la décision métier lorsque `is_active` est mis à `false`.

### Pour les DEV

- Garder les providers de disponibilité légers.
- Ne pas appeler les collecteurs complets depuis `check-availability`.
- Garder les collecteurs dans `infra_metering_*` et les contrôles candidats dans `infra_quota_check-*`.
- Utiliser `Quota::search(...)->do('check-availability', $values)` dans les `cancreate()` ou workflows équivalents.
- S'assurer que chaque `MetricDefinition.collector`, `Quota.availability_controller` et `QuotaThreshold.action` pointe vers un contrôleur existant.
- Préférer des actions de seuil idempotentes, surtout avec `trigger_policy = always`.

---

## Fichiers de référence

Classes :

- `packages/infra/classes/metering/MetricDefinition.class.php`
- `packages/infra/classes/quota/Quota.class.php`
- `packages/infra/classes/quota/QuotaThreshold.class.php`

Actions :

- `packages/infra/actions/quota/refresh-values.php`
- `packages/infra/actions/quota/shift-periods.php`
- `packages/infra/actions/quota/handle-*.php`

Providers :

- `packages/infra/data/metering/read-*.php`
- `packages/infra/data/quota/check-*-availability.php`

Initialisation et updates :

- `packages/fmt/init/data/scripts/30-create-infra_quotas.php`
- `packages/fmt/init/updates/20260806104500_update_infra_quota_controllers.php`

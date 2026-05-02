# Exercices comptables

Chaque ACP définit son exercice comptable en Assemblée Générale (AG).

* Un exercice est généralement d’une durée d’un an.
* Il peut être allongé ou raccourci :

  * pour le premier exercice (immeuble neuf / création de copropriété),
  * ou suite à un changement de syndic / décision d’AG (cas rare),
  * ou suite à une clôture anticipée (fin de mandat en cours d’exercice / période).
* L’exercice peut se terminer n’importe quel mois de l’année (dates arbitraires, décidées en AG).
* Les dates d’exercice sont définies dans les statuts mais peuvent être modifiées en AG.
* Les décomptes de charges sont adaptés à l’exercice défini.

### Périodicité des décomptes

Les copropriétés peuvent produire des décomptes selon une périodicité au choix, typiquement :

* trimestriel
* quadrimestriel
* semestriel
* annuel

> **Règle structurante** : les **décomptes** (ExpenseStatement) sont **toujours obligatoirement liés à une période** (FiscalPeriod).
> Corollaire : une période **clôturée** a nécessairement un **ExpenseStatement validé et intégré en comptabilité**.

### Numérotation des pièces et périodicité

Note : la numérotation de certaines pièces (ex. factures d’achat) peut prendre en compte la période (ex. numérotation qui recommence à chaque période).
Lors de la création d’un exercice, le système génère des séquences de numérotation liées aux périodes, via un setting (par copropriété) :

`finance, accounting, invoice.period_sequence.{fiscal_year_code}.{fiscal_period_code}`



## Principes généraux de gestion des exercices

### Deux règles de simplification

Pour limiter la complexité, deux règles structurantes s’appliquent au moteur de workflow :

1. **Les validations de transition (can_open, can_preclose, can_close, …) vérifient toujours vers l’arrière, jamais vers l’avant.**
   On n’impose pas qu’un futur (exercice suivant) existe ni qu’il soit dans un état particulier pour autoriser une transition.

2. **L’ouverture d’une année comptable est toujours manuelle (jamais automatique).**
   Aucune cascade implicite ne doit “ouvrir” un exercice à la place de l’utilisateur.

Ces deux règles rendent les transitions locales, testables, et évitent les effets domino.

### Transitions strictement séquentielles

Une règle supplémentaire s’applique également:

> On ne passe **obligatoirement** que d’un état au suivant, **sans saut**, et **tous les états sont indispensables**.

Exemples interdits :

* `draft → open`
* `open → closed`
* `preopen → preclosed`
* etc.

Chaque étape doit être traversée avec son action dédiée.

### Existence d’un exercice sans précédent

Il est possible d’ouvrir un exercice même s’il n’existe **aucun exercice précédent** (premier exercice, migration, import partiel…).
Dans ce cas, les règles “vers l’arrière” n’empêchent pas la transition ; les contraintes liées à l’exercice précédent ne s’appliquent que s’il existe.



## Modèle : FiscalYear, FiscalPeriod, ExpenseStatement

Trois concepts interagissent :

* **FiscalYear** : année comptable (conteneur temporel + structure des périodes).
* **FiscalPeriod** : période comptable (unité réelle de production/validation des décomptes).
* **ExpenseStatement** : décompte de charges (proforma puis validé/posté).

### Rôle central des périodes

Le système est conçu pour que la **préclôture et la clôture** ne soient pas des opérations “directes” sur l’année (FiscalYear), mais qu’elles soient **déclenchées par la dernière période et son ExpenseStatement**.

En pratique :

* Mettre en **préclôture** une période ⇒ génère un **ExpenseStatement proforma**
* **Clôturer** une période ⇒ exige un ExpenseStatement **validé + intégré en comptabilité (posté)**
* Si la période est la **dernière de l’exercice**, alors l’état du **FiscalYear** est ajusté en conséquence.



## Workflow : FiscalYear

Workflow officiel :

`draft → preopen → open → preclosed → closed`

### Draft

* Une année comptable `draft` est **vide** et **non imputable**.
* On peut y configurer les dates et la structure des périodes.
* Aucune écriture/aucune pièce ne peut être affectée à une année `draft`.

### Pré-ouverture (preopen)

La transition `draft → preopen` correspond à la **préparation structurée** de l’exercice.

Effets principaux :

* l’année passe de `draft` à `preopen` ;
* les dates de début et fin sont considérées comme prêtes à être activées ;
* la cohérence des périodes est vérifiée/figée selon la politique choisie ;
* génération des séquences de numérotation par période (si applicable) ;
* **création automatique d’un exercice suivant en `draft`** (année suivante).

> **Règle importante** : lorsque l’on pré-ouvre une année (preopen), il faut **toujours** créer une `draft` pour l’année suivante (même si elle ne sera pas ouverte tout de suite).
> Ainsi, la “chaîne” peut être préparée en avance sans effet automatique d’ouverture.

#### Preopen en série

Il est possible d’avoir autant d’années `preopen` en série que souhaité (préparation à l’avance).
Aucune limitation structurelle ne l’interdit, et cela ne déclenche pas d’ouverture automatique.

### Ouverture (open)

La transition `preopen → open` est **manuelle**.

Objectifs :

* activer l’exercice en tant qu’exercice “écrivable”
* permettre l’imputation sur ses périodes ouvertes

Points clés :

* on ne force pas l’existence ni l’état du futur (règle “on ne regarde pas vers l’avant”)
* l’ouverture ne déclenche pas une cascade “automatique” sur d’autres exercices
* il doit être possible d’ouvrir un exercice même sans précédent (premier exercice)

> Contrainte métier fréquente (si conservée dans ton modèle) : un seul exercice comptable est “en cours” dans l’interface métier.
> Cela dépend de la politique d’imputation / date pièce, mais reste compatible avec l’ouverture manuelle.

### Pré-clôture (preclosed)

Le statut `preclosed` d’un FiscalYear est **dérivé** : on ne met pas une année en préclôture comme action principale.
Il correspond au cas où la **dernière période** de l’exercice est en préclôture (ExpenseStatement annuel en finalisation / proforma).

Définition :

* `FiscalYear.preclosed` = “la dernière période est en `preclosed`, décompte annuel non finalisé”
* pour modifier des écritures (ajout / correction), il faudra ré-ouvrir (selon procédure) car l’année est considérée “figée” tant que le décompte n’est pas validé.

### Clôture (closed)

De même, `FiscalYear.closed` est essentiellement la conséquence de la clôture de la dernière période (et de son ExpenseStatement).

Définition :

* `FiscalYear.closed` = “toutes les périodes sont clôturées, y compris la dernière, et le décompte final est intégré en compta”

Après clôture :

* plus aucune imputation n’est possible sur l’exercice
* si une erreur est détectée après clôture, une écriture d’ajustement doit être passée dans l’exercice suivant
* une réouverture exceptionnelle n’existe que via opération spécifique (si ton modèle la prévoit), sinon elle est interdite

---

## Workflow : FiscalPeriod

Les périodes suivent toujours leur FiscalYear (structure, dates, appartenance).
Elles sont l’unité “opérationnelle” de production des décomptes.

Workflow opérationnel :

`draft → preopen → open → preclosed → closed`

> Remarque : même si tu choisis d’ouvrir “directement” toutes les périodes quand l’année s’ouvre, les états existent et restent indispensables (pas de saut).
> En pratique, `preopen` des périodes peut être géré automatiquement en même temps que le `preopen` de l’année, mais il reste un état réel (et testable).

### Ouverture des périodes

Par facilité:

* lorsqu’une année comptable est ouverte (`FiscalYear.open`), **on ouvre directement toutes ses périodes** (`FiscalPeriod.open`).
* cela permet d’affecter des pièces sur **n’importe quelle période** tant qu’elle n’est pas fermée (`closed`).

Règle d’imputation associée :

> On ne peut imputer une pièce comptable que sur une période **open**.
> Donc si une période est open, l’année est nécessairement dans un état permettant l’imputation (open).

### Préclôture d’une période : point d’entrée du décompte

Règle structurante :

* **On ne peut pas clôturer directement une période : uniquement la préclôture est directe.**
* Mettre une période en `preclosed` correspond à **générer un ExpenseStatement**.

Autrement dit :

* “Générer un décompte pour une période” = `FiscalPeriod.open → FiscalPeriod.preclosed`

#### Génération automatique d’un ExpenseStatement proforma

Règle explicite :

> À la préclôture d’une période (`open → preclosed`), on génère automatiquement un **ExpenseStatement proforma**.

Ce proforma :

* fige un instantané des écritures de la période
* sert à préparation/validation
* peut inclure des informations à confirmer / modifier :

  * numéro de compte
  * conditions de paiement
  * mentions administratives, etc.
* peut nécessiter validation par un gestionnaire et/ou comptable selon l’organisation

### Ordonnancement des périodes (séquence)

Règle explicite :

> Une période ne peut être pré-clôturée que si toutes les périodes précédentes sont clôturées.

Cela impose un flux chronologique strict :

* on ne prépare pas un décompte du T3 si le T2 n’est pas clôturé
* on évite les trous et incohérences de reporting

### Clôture d’une période : validation + intégration comptable

Règle explicite :

> Pour clôturer une période, il faut **valider** et **intégrer en comptabilité** un ExpenseStatement.

Donc :

* `FiscalPeriod.preclosed → FiscalPeriod.closed` n’est autorisé que si :

  * l’ExpenseStatement est validé
  * et ses écritures / effets sont postés en compta (“intégré”)

Conséquence :

* une période `closed` est juridiquement et comptablement finalisée
* aucune imputation ultérieure n’est possible sur cette période



## Interaction FiscalPeriod ↔ FiscalYear (dernière période)

### Générer un décompte pour la dernière période

Règle :

* “Générer un décompte pour la dernière période d’une année” = mettre la **période + l’année** en préclôture.

Mécaniquement :

* `last_period.open → last_period.preclosed` (déclenche ExpenseStatement proforma)
* entraîne `FiscalYear.open → FiscalYear.preclosed` (statut dérivé)

### Validation du décompte final

Règle :

* “Valider un décompte” = clôturer la période correspondante (`preclosed → closed`)
* et si c’est la dernière période :

  * clôturer également l’année (`FiscalYear.preclosed → FiscalYear.closed`)

En résumé :

* l’année ne “se clôture” pas directement
* ce sont la dernière période et son ExpenseStatement qui poussent l’année dans son état final



## Exigences de continuité et disponibilité

### Toujours au moins un FiscalYear en preopen

Règle explicite :

> Il faut toujours au moins 1 FiscalYear en `preopen` (sinon on ne peut pas faire d’écritures).

Cette règle est un garde-fou opérationnel :

* pour imputer correctement sans se retrouver “au bord” de l’exercice sans préparation du suivant
* pour garantir que la continuité est maintenue (au moins une année suivante préparée)



## Règles d’imputation (écritures / pièces)

### Attribution des écritures

* Les écritures sont attribuées à un exercice selon la date de la pièce comptable (ou règle métier équivalente).
* Les écritures sont toujours associées à une période comptable (FiscalPeriod), afin de permettre :

  * balances périodiques
  * décomptes structurés
  * clôtures séquentielles

### Autorisations d’imputation

Règle stricte actuelle :

* Une pièce comptable ne peut être imputée que sur une **période open**.
* Donc, implicitement :

  * l’année doit être dans un état permettant l’ouverture des périodes (typiquement `open`)

Dans la logique antérieure, tu avais aussi la notion :

* exercice précédent (non clôturé)
* exercice en cours
* exercice à venir
* interdiction d’imputer à plus d’un exercice dans le futur

Ces principes restent compatibles, mais dans la nouvelle architecture, la contrainte la plus forte et la plus simple est :

* l’imputation se fait par **période ouverte** (et donc par exercice ouvert)



## Création et gestion des exercices

### Ouverture / Pré-ouverture d’un exercice

Action structurante : `finance/accounting/fiscalyear::open` (et/ou action `preopen` selon ton implémentation)

À la création d’un exercice (pour une copropriété) :

* créer la structure de périodes selon le template de périodicité choisi
* générer les séquences par période
* mettre l’exercice dans un état cohérent avec son cycle (`draft`, puis `preopen` manuellement)
* créer l’exercice suivant en `draft` lors du passage en `preopen`
* ne pas ouvrir automatiquement l’exercice suivant (ouverture toujours manuelle)

### Gestion des périodes (template ACP)

* possibilité d’avoir une périodicité (trimestriel/quadrimestriel/semestriel/annuel)
* proposition d’un template de périodes modifiable par ACP
* contrôle de continuité des périodes
* en cas de modification des périodes :

  * forcer un recalcul des assignations de périodes et balances périodiques si nécessaire
  * pas d’impact sur la balance courante, sauf si ton modèle l’exige



## Cas particuliers pour la création/modification des périodes

Les trois cas suivants doivent être supportés.

### 1) Première ouverture (premier exercice)

Cas : immeuble neuf / première mise en comptabilité.

* le premier exercice démarre à une date réelle (réception provisoire parties communes ou 1ère partie privative)
* l’exercice se termine à une date fixée en AG
* le premier exercice peut être plus long ou plus court que 12 mois
* ensuite, les exercices reviennent à 12 mois à partir de la date de fin du premier exercice
* si décompte trimestriel :

  * seule la première période peut être plus longue/courte
  * puis périodes de 3 mois en 3 mois (idem pour autres périodicités)

Distinction utile :

* `date_init` : date réelle de début d’exercice
* `date_from` : date théorique calculée

Méthode :

1. trouver `date_from` théorique à partir de `date_to` : `date_to - 1 year + 1 day`
2. créer les périodes comme “ouverture classique” avec exceptions :

   * pour la première période :

     * si `date_from < date_init` ou `date_from > date_init`, alors `date_from = date_init`
   * pour toutes les périodes :

     * si `date_to < date_init` : ignorer

### 2) Modification (décision d’AG)

Cas rare : AG modifie l’exercice comptable (dates/structure).

Effets :

* exercice plus court ou plus long
* puis retour à la normalité ensuite

Contraintes :

* adaptation des périodes
* réassignation des écritures aux nouvelles périodes si nécessaire
* recalcul des balances périodiques / assignations

### 3) Clôture anticipée (fin de mandat en cours d’année)

Cas : fin de mandat du syndic en cours d’année ou de période.

Objectif :

* modifier la date de fin qui sert à comptabiliser les charges d’une période
* bloquer la date de début au premier jour suivant le dernier décompte comptabilisé (pas de trous)

Implications :

* existence (au moins `draft`/`preopen`) de l’exercice suivant ; si absent, le créer
* modifier date de début de l’exercice suivant (date fin + 1) avec réassignations si écritures déjà présentes
* modifier date de fin de l’exercice courant :

  * supprimer périodes postérieures à la nouvelle fin
  * réassigner écritures vers les périodes de l’exercice suivant si nécessaire
* ensuite :

  * passage logique via les mécanismes de périodes et décomptes (préclôture/clôture)

Note :

* si des écritures sont déjà imputées à l’exercice suivant, c’est la responsabilité de l’ancien syndic de transmettre infos et pièces (règle organisationnelle).



## ExpenseStatement (Décompte de charges)

### Objectif

Un ExpenseStatement est l’objet documentaire et comptable qui :

* matérialise le décompte de charges lié à une période
* permet la validation et l’intégration en comptabilité
* conditionne la clôture de la période (et éventuellement de l’année)

### Proforma

À la transition `FiscalPeriod.open → FiscalPeriod.preclosed` :

* génération automatique d’un ExpenseStatement **proforma**
* possibilité de compléter/ajuster certaines données :

  * numéro de compte
  * conditions de paiement
  * champs administratifs
* possibilité de validation interne (gestionnaire/comptable), selon politique

### Validation + intégration

Pour clôturer une période, l’ExpenseStatement doit être :

* validé
* posté/intégré en comptabilité

“Valider un décompte” = “clôturer la période correspondante”.



## Pièces de clôture / export

Après la clôture d’un exercice, une archive de clôture peut être générée, reprenant les pièces comptables à envoyer au commissaire aux comptes (ZIP contenant les fichiers XLS), typiquement :

* bilan
* balances
* liste de frais
* documents de clôture



## Récapitulatif des statuts

### FiscalYear

* `draft` : année vide, non imputable
* `preopen` : année préparée, suivante draft créée
* `open` : année active, périodes ouvertes, imputation possible
* `preclosed` : statut dédié à “dernière période en préclôture” (décompte annuel proforma en cours)
* `closed` : année finalisée, conséquence de clôture de la dernière période

### FiscalPeriod

* `draft` : période configurée mais non active
* `preopen` : période prête, structure validée
* `open` : imputation possible
* `preclosed` : proforma généré, période figée en attente de validation/intégration
* `closed` : ExpenseStatement validé + intégré, période verrouillée






## FiscalYear - workflow

Workflow : `draft → preopen → open → preclosed → closed`

| From status | To status   | Transition (action / invocation)                                                        | Conditions (`can_*`)                                                                                                                                                                                                                                              |
| ----------- | ----------- | --------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `draft`     | `preopen`   | `FiscalYear::preopen()` (action manuelle, ex: `finance/accounting/fiscalyear::preopen`) | `canPreopen()` : FY en `draft` ; dates valides ; périodes cohérentes ; si FY précédent existe ⇒ continuité dates + absence chevauchement                                                                                                                          |
| `preopen`   | `open`      | `FiscalYear::open()` (action manuelle, ex: `finance/accounting/fiscalyear::open`)       | `canOpen()` : FY en `preopen` ; aucune autre FY en `open` (unicité) ; si FY précédente existe ⇒ FY précédente ≠ `open` (et/ou FY précédente dans un état compatible selon règle métier) ; au moins une FY en `preopen` après l’opération (garantie de continuité) |
| `open`      | `preclosed` | **Indirect / automatique** via `FiscalPeriod::preclose()` sur **la dernière période**   | `canPreclose()` : FY en `open` ; lastPeriod.status = `preclosed` (état dérivé)                                                                                                                                                                                    |
| `preclosed` | `closed`    | **Indirect / automatique** via `FiscalPeriod::close()` sur **la dernière période**      | `canClose()` : FY en `preclosed` ; lastPeriod.status = `closed` (état dérivé)                                                                                                                                                                                     |

### Notes FiscalYear (effets automatiques)

* Lors de `draft → preopen` : création automatique d’un `FiscalYear` suivant en `draft`.
* Les états `preclosed` et `closed` ne sont **pas invoqués directement** : ils sont déclenchés par la dernière période + ExpenseStatement.



## FiscalPeriod - workflow

Workflow : `draft → preopen → open → preclosed → closed`

| From status | To status   | Transition (action / invocation)                                                           | Conditions (`can_*`)                                                                                                                                                                     |
| ----------- | ----------- | ------------------------------------------------------------------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `draft`     | `preopen`   | `FiscalPeriod::preopen()` (souvent appelé automatiquement lors du `FiscalYear::preopen()`) | `canPreopen()` : période en `draft` ; cohérence dates ; appartient à un FY en `preopen` ou supérieur                                                                                     |
| `preopen`   | `open`      | `FiscalPeriod::open()` (souvent appelé automatiquement lors du `FiscalYear::open()`)       | `canOpen()` : période en `preopen` ; FY parent en `open` ; (optionnel) aucune incohérence de séquence                                                                                    |
| `open`      | `preclosed` | `FiscalPeriod::preclose()` = génération d’un décompte (ExpenseStatement proforma)          | `canPreclose()` : période en `open` ; FY parent en `open` ; toutes les périodes précédentes = `closed` ; aucune pièce “en attente” bloquante ; (si dernière période) FY doit être `open` |
| `preclosed` | `closed`    | `FiscalPeriod::close()` = validation + intégration comptable de l’ExpenseStatement         | `canClose()` : période en `preclosed` ; ExpenseStatement existe ; ExpenseStatement.status = `validated` ; ExpenseStatement.posted = true                                                 |

### Notes FiscalPeriod (effets automatiques)

* Lors de `open → preclosed` :

  * génération automatique d’un `ExpenseStatement` **proforma**
  * blocage des imputations sur la période
* Lors de `preclosed → closed` :

  * intégration comptable finalisée
  * verrouillage définitif de la période
* Si la période est la dernière du FY :

  * `period.preclose()` entraîne FY → `preclosed`
  * `period.close()` entraîne FY → `closed`



## ExpenseStatement - Workflow

Workflow typique : `draft/proforma → validated → posted`

| From status | To status   | Transition (action / invocation)                | Conditions (`can_*`)                                                                                                            |
| ----------- | ----------- | ----------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------- |
| (none)      | `proforma`  | auto lors de `FiscalPeriod::preclose()`         | `FiscalPeriod.canPreclose()`                                                                                                    |
| `proforma`  | `validated` | `ExpenseStatement::validate()`                  | `canValidate()` : statement existe ; période = `preclosed` ; contrôles métier OK (comptes, montants, conditions paiement, etc.) |
| `validated` | `posted`    | `ExpenseStatement::post()` (intégration compta) | `canPost()` : validated ; génération écritures OK ; journaux disponibles ; pas d’erreur d’équilibre                             |




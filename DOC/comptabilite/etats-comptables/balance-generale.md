# Balance générale

La balance générale est un état comptable qui présente les mouvements et les soldes des comptes sur une période donnée. Elle permet à la fois de vérifier la cohérence des écritures et d’analyser l’évolution des comptes.

Dans le système, la balance n’est pas une simple liste de lignes comptables :
elle est structurée **par compte**, avec une logique de recalcul des soldes permettant de restituer fidèlement la situation comptable à n’importe quelle date.



## Structure de la balance

La balance est organisée **compte par compte**.

Pour chaque compte, elle présente :

1. un **solde initial** à la date de début de la période,
2. suivi des **écritures comptables** de la période,
3. avec un **solde recalculé après chaque ligne**.

Cela correspond à une vue proche d’un grand livre simplifié.

### Exemple de structure

Pour un compte donné :

* Solde au 01/03/2025
* Écriture du 05/03/2025 → nouveau solde
* Écriture du 12/03/2025 → nouveau solde
* …

Chaque ligne contient un solde cumulatif, permettant de suivre l’évolution du compte.



## Période et exercice comptable

La balance repose sur une période définie par :

* une date de début (`date_from`)
* une date de fin (`date_to`)

Un exercice comptable (`FiscalYear`) peut également être utilisé pour :

* déterminer une période par défaut,
* identifier le contexte comptable.

Cependant :

> Le calcul de la balance dépend uniquement des dates, pas de l’exercice.

Cela permet de consulter la balance sur des périodes arbitraires, y compris au milieu d’un exercice.



## Détermination des comptes inclus

Afin d’assurer une balance complète tout en restant performante, les comptes affichés sont déterminés dynamiquement.

Un compte est inclus s’il répond à au moins une des conditions suivantes :

* il possède un **solde initial non nul**,
* il a des **écritures dans la période**,
* il a un **historique avant la période** impactant son solde.

Concrètement, cela inclut :

* les comptes présents dans les soldes d’ouverture (`OpeningBalanceLine`),
* les comptes ayant subi des variations (`AccountBalanceChange`) avant ou pendant la période,
* les comptes ayant des écritures comptables (`AccountingEntryLine`) sur la période.

Cette approche garantit que :

> les comptes sans mouvement mais avec un solde existant sont bien affichés.



## Calcul du solde initial

Lorsque la période ne commence pas au début d’un exercice, le système reconstitue le solde du compte à la date de début.

Le calcul repose sur deux sources principales :

1. le solde d’ouverture de l’exercice (`OpeningBalanceLine`),
2. les variations de solde (`AccountBalanceChange`) entre le début de l’exercice et la date demandée.

Le principe est le suivant :

* le solde d’ouverture sert de base,
* le dernier changement de solde connu avant la date de début est appliqué,
* ce dernier état fait foi.

Ainsi :

> le solde initial correspond toujours au solde réel du compte à la date de début de la période.



## Calcul du solde sur la période

Une fois le solde initial déterminé, les écritures de la période sont appliquées chronologiquement.

Pour chaque ligne :

* le débit augmente le solde,
* le crédit le diminue,
* un nouveau solde est calculé immédiatement.

Cela produit un **solde progressif**, ligne par ligne.



## Nature des lignes retournées

Deux types de lignes coexistent dans la balance :

### 1. Ligne de solde initial

* générée automatiquement (ligne virtuelle),
* positionnée à la date de début,
* représente l’état du compte avant toute opération de la période.

### 2. Lignes d’écritures comptables

* issues des `AccountingEntryLine`,
* uniquement si l’écriture est validée (`status = validated`),
* enrichies avec les informations liées (compte, journal, écriture).



## Filtres disponibles

La balance peut être filtrée selon plusieurs critères :

* période (`date_from`, `date_to`)
* exercice comptable (`FiscalYear`)
* compte (`Account`)
* journal (`Journal`)
* type de compte :

  * fournisseurs
  * copropriétaires

Les filtres sont appliqués :

* au niveau des écritures pour les performances,
* et au niveau des comptes pour certaines catégories métier.



## Comportement fonctionnel

La balance garantit les comportements suivants :

* un compte avec un **solde non nul** est affiché, même sans écriture,
* un compte avec des **écritures sur la période** est affiché, même si son solde est nul,
* un compte ayant un **historique avant la période** est correctement valorisé.



## Objectifs de la balance générale

### Vérification comptable

La balance permet de vérifier que la comptabilité est équilibrée :

* total des débits = total des crédits

### Analyse des comptes

Elle permet de :

* suivre les mouvements par compte,
* analyser les opérations,
* comprendre l’évolution des soldes.

### Base des états comptables

Elle sert de fondation pour :

* le grand livre,
* le bilan,
* le compte de résultats,
* les décomptes analytiques (ex. copropriété).



## Remarques techniques

La balance est construite selon une logique **centrée sur les comptes**, et non sur les lignes.

Cela signifie que :

* chaque compte est traité indépendamment,
* le solde est recalculé dynamiquement,
* les données sont optimisées pour limiter les volumes tout en garantissant l’exactitude.

Cette approche permet de concilier :

* performance,
* précision comptable,
* flexibilité d’analyse.


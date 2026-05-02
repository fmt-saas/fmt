# Grand Livre (General Ledger)

Le **Grand Livre** est un état comptable qui présente l’ensemble des écritures comptables **classées par compte**. Il permet de suivre, pour chaque compte de la comptabilité, l’ensemble des mouvements qui l’affectent ainsi que l’évolution de son solde au fil du temps.

Contrairement à la **balance générale**, qui présente les mouvements de manière globale, le Grand Livre regroupe les lignes d’écriture par **compte comptable** afin de montrer l’historique détaillé des opérations qui impactent chaque compte.

Le Grand Livre est donc un outil essentiel pour comprendre l’origine d’un solde et analyser les opérations qui ont conduit à ce résultat.

## Structure des données

Le Grand Livre est construit à partir des **lignes d’écriture comptables** (`AccountingEntryLine`). Ces lignes sont regroupées par **compte comptable** (`Account`).

Pour chaque compte, les écritures sont présentées dans **l’ordre chronologique** afin de permettre le suivi du solde dans le temps.

Chaque ligne du Grand Livre contient généralement les informations suivantes :

- **Date de l’écriture** (`entry_date`)
  Date à laquelle l’opération est enregistrée dans la comptabilité.
- **Journal** (`journal_id`)
  Journal dans lequel l’écriture comptable a été enregistrée.
- **Écriture comptable** (`accounting_entry_id`)
  Référence de l’écriture comptable.
- **Description** (`description`)
  Texte décrivant l’opération.
- **Débit** (`debit`)
  Montant débité sur le compte.
- **Crédit** (`credit`)
  Montant crédité sur le compte.
- **Solde cumulatif**
  Solde du compte après la prise en compte de la ligne d’écriture.

Contrairement à la balance générale, le Grand Livre calcule généralement un **solde progressif**, qui représente l’évolution du solde du compte après chaque opération.

## Calcul du solde

Pour chaque compte, le solde évolue au fil des lignes selon la règle suivante :

```
nouveau_solde = solde_précédent + debit − credit
```

Ce calcul permet de visualiser immédiatement l’impact de chaque écriture sur le solde du compte.

Selon le type de compte (actif, passif, charge, produit), l’interprétation du solde peut varier, mais le mécanisme de calcul reste identique.

## Objectif du Grand Livre

Le Grand Livre remplit plusieurs fonctions importantes.

### Traçabilité des opérations comptables

Il permet de retracer **l’ensemble des opérations ayant affecté un compte**.
Chaque mouvement peut être relié à une écriture comptable précise, ce qui garantit la transparence et la traçabilité des opérations.

### Analyse détaillée des comptes

Le Grand Livre permet d’examiner en détail :

- les mouvements d’un compte particulier,
- l’origine d’un solde,
- la chronologie des opérations.

Il constitue ainsi un outil essentiel pour l’analyse comptable et pour les contrôles internes.

### Support pour les contrôles et audits

Lors d’un contrôle comptable ou d’un audit, le Grand Livre est utilisé pour :

- vérifier la cohérence des écritures,
- analyser les mouvements inhabituels,
- reconstituer les opérations ayant conduit à une situation comptable donnée.

## Relation avec les autres états comptables

Le Grand Livre s’inscrit dans une chaîne logique d’états comptables.

- Le **journal** enregistre les écritures comptables dans l’ordre chronologique.
- Le **Grand Livre** regroupe ces écritures **par compte**.
- La **balance générale** synthétise les mouvements de chaque compte sur une période.

Ces trois états reposent sur les mêmes écritures comptables, mais les présentent selon des perspectives différentes :

- **Journal** → vue chronologique des écritures
- **Grand Livre** → vue par compte
- **Balance** → vue synthétique des soldes


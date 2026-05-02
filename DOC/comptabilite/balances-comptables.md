# Balances et états comptables

# 1. Vue d’ensemble

Le moteur comptable repose sur une séparation stricte entre :

1. **La vérité comptable**
   → `AccountingEntry` et `AccountingEntryLine`

2. **Les projections cumulatives optimisées**
   → `AccountBalanceChange`

3. **Les points de contrôle comptables**
   → `ClosingBalance` et `OpeningBalance`

Cette séparation permet :

* Une traçabilité complète des écritures
* Une clôture annuelle juridiquement fiable
* Des calculs de balances rapides et scalables
* Une reconstruction possible en cas d’incohérence
* Une représentation claire du **bilan de clôture et du bilan d’ouverture**

Les balances (`ClosingBalance` et `OpeningBalance`) sont des **instantanés comptables**.
Elles ne constituent pas la source de vérité comptable mais servent de **points d’ancrage stables** dans l’historique comptable.



# 2. Les couches du moteur comptable

## 2.1 AccountingEntry / AccountingEntryLine (Source of Truth)

Les écritures validées (`status = validated`) constituent la seule réalité comptable active.

Caractéristiques :

* Une écriture validée n’est jamais modifiée.
* Toute correction passe par :

  * création d’une écriture inverse,
  * validation,
  * passage des deux écritures en `reversed`.
* Seules les écritures validées sont prises en compte dans les calculs comptables.

La vérité comptable est donc définie par :

```
SUM(AccountingEntryLine WHERE entry.status = 'validated')
```

Les écritures constituent le **journal comptable complet** et permettent la reconstitution intégrale de l’historique financier.



## 2.2 AccountBalanceChange (Projection cumulative)

`AccountBalanceChange` est une série temporelle cumulative.

Chaque ligne représente :

* l’état cumulé d’un compte
* à une date donnée
* après application de toutes les écritures validées jusqu’à cette date

Structure logique :

```
(condo_id, account_id, date)
debit_balance
credit_balance
```

Il n’y a **qu’une seule ligne par compte et par date de mouvement**.

Cette table permet de calculer très rapidement :

* balances
* variations sur période
* états comptables

sans avoir à parcourir toutes les écritures.



## 2.3 ClosingBalance (Snapshot de clôture)

`ClosingBalance` représente un **instantané figé de la comptabilité à la fin d’un exercice ou d’une période**.

Elle est générée automatiquement lors de la clôture d’un exercice.

Propriétés :

* instantané figé des soldes de tous les comptes
* point d’ancrage juridique pour les comptes annuels
* point d’ancrage technique pour les reconstructions

Une `ClosingBalance` correspond exactement à l’état comptable résultant de toutes les écritures validées jusqu’à la date de clôture.

Elle contient :

```
debit
credit
debit_balance
credit_balance
```

pour chaque compte.

Une fois validée, une `ClosingBalance` est **immuable**.



## 2.4 OpeningBalance (Snapshot d’ouverture)

`OpeningBalance` représente l’état des comptes **au début d’un exercice comptable**.

Elle correspond au **bilan d’ouverture de l’exercice**.

Dans le système, une `OpeningBalance` est générée automatiquement à partir de la `ClosingBalance` de l’exercice précédent.

Relation logique :

```
OpeningBalance(Y+1) = ClosingBalance(Y)
```

Cette balance permet :

* d’afficher le **bilan d’ouverture**
* d’avoir un point d’ancrage pour les états comptables
* de maintenir la continuité comptable entre exercices

Contrairement aux systèmes comptables classiques, le moteur **ne génère pas d’écritures de report dans les journaux**.

La continuité comptable est assurée par les snapshots :

```
ClosingBalance → OpeningBalance
```

Cela évite :

* la duplication artificielle d’écritures
* les déséquilibres possibles dans les journaux
* les erreurs de report.



# 3. Logique de fonctionnement

## 3.1 Principe cumulatif

Pour un compte donné :

```
balance(t_n) = balance(t_n-1) + delta(t_n)
```

où :

```
delta(t_n) = somme des lignes d’écriture validées à la date t_n
```

Si aucune écriture ne touche le compte à une date donnée, aucune ligne n’est créée.

La table reste **sparse** (clairsemée).



## 3.2 Mise à jour lors d’une validation

Lorsqu’une `AccountingEntry` passe en `validated` :

1. Les `AccountingEntryLine` sont parcourues.
2. Les lignes non encore postées (`is_posted = false`) sont agrégées par compte.
3. Pour chaque compte :

   * on récupère la balance précédente (< date)
   * on ajoute le delta
   * on met à jour ou crée la ligne `AccountBalanceChange`
4. Les lignes ultérieures sont ajustées en cas de backdating.
5. Les lignes sont marquées `is_posted = true`.

Contraintes importantes :

* une ligne postée ne doit jamais être repostée
* une ligne postée ne doit jamais redevenir non postée



## 3.3 Gestion des annulations

Annulation d’une écriture validée :

1. création d’une écriture inverse
2. validation de cette écriture inverse
3. passage des deux écritures en `reversed`

Effet :

* l’écriture inverse neutralise l’écriture initiale
* aucune suppression d’écriture
* aucune modification de projection existante

La projection reflète donc :

```
SUM(lignes postées)
```

et non :

```
SUM(lignes WHERE status='validated')
```



# 4. Calcul des balances et états comptables

Pour une plage `[date_from, date_to]` :

```
balance_end   = dernier AccountBalanceChange ≤ date_to
balance_start = dernier AccountBalanceChange < date_from
delta         = balance_end - balance_start
```

Cela permet de produire :

* balances
* bilan
* compte de résultat
* variation des comptes

sans lecture directe des `AccountingEntryLine`.



# 5. Logique de clôture d’un exercice

La clôture d’un exercice produit deux éléments :

1. un **snapshot de clôture**
2. un **snapshot d’ouverture pour l’exercice suivant**

Processus :

### Étape 1 — génération du ClosingBalance

Lors de la clôture d’un exercice :

```
ClosingBalance(Y)
```

est générée à partir des écritures de l’exercice.

Elle capture l’état final des comptes.



### Étape 2 — génération de l’OpeningBalance

Après la clôture :

```
OpeningBalance(Y+1)
```

est générée automatiquement.

Cette balance copie les lignes de la `ClosingBalance` précédente.

Relation :

```
ClosingBalance(Y)
        ↓
OpeningBalance(Y+1)
```

Cela garantit la continuité comptable.



### Cas du premier exercice

Pour le premier exercice d’une copropriété, une `OpeningBalance` peut être créée manuellement.

Elle correspond à l’**OD d’ouverture** initiale.
²
Les exercices suivants utilisent automatiquement la logique :

```
ClosingBalance → OpeningBalance
```



# 6. Contraintes techniques

## 6.1 Indexation obligatoire

La table `AccountBalanceChange` doit être indexée sur :

```
(condo_id, account_id, date)
```

Cela garantit :

* recherche rapide du dernier solde
* range scan efficace
* scalabilité



## 6.2 Unicité

L’unicité logique :

```
(condo_id, account_id, date)
```

est garantie par l’ORM.

La base ne porte qu’un index composite.



## 6.3 Transactions

Toutes les mises à jour doivent être transactionnelles :

* validation d’écriture
* mise à jour projection
* marquage `is_posted`

Sinon incohérence possible.



# 7. Risque d’erreur cumulative

Une erreur dans `AccountBalanceChange` se propage :

```
Si balance(t_k) est faux
→ balance(t_k+1) est faux
→ ...
```

Mais :

* la vérité comptable reste intacte
* la projection est reconstructible.



# 8. Rebuild du moteur

## 8.1 Rebuild complet

1. supprimer tous les `AccountBalanceChange`
2. rejouer toutes les écritures validées



## 8.2 Rebuild partiel basé sur ClosingBalance (recommandé)

1. trouver le dernier `ClosingBalance`
2. supprimer les projections postérieures
3. injecter la balance de clôture comme point d’ancrage
4. rejouer uniquement les écritures validées postérieures

Cette stratégie permet un **rebuild rapide même avec un historique important**.



# 9. Vérification d’intégrité

Deux niveaux de contrôle existent.

### Vérification à la clôture

Comparer :

```
ClosingBalance == AccountBalanceChange à la date de clôture
```

Cela garantit la cohérence entre projection et snapshot.



### Vérification périodique

Un recalcul partiel peut être effectué en comparant :

```
SUM(AccountingEntryLine)
```

avec les projections `AccountBalanceChange`.



# 10. Invariants du système

Le moteur repose sur les invariants suivants :

1. une écriture validée n’est jamais modifiée
2. toute correction passe par une écriture inverse
3. une ligne postée n’est jamais repostée
4. une ligne postée ne redevient jamais non postée
5. `ClosingBalance` est immuable
6. `OpeningBalance` correspond toujours à la `ClosingBalance` précédente
7. `AccountBalanceChange` est reconstructible
8. les écritures `reversed` n’ont plus d’effet actif


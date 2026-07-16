# Exercices comptables

Chaque ACP définit son exercice comptable en Assemblée Générale (AG).

* Un exercice est généralement d'une durée d'un an.
* Il peut être allongé ou raccourci pour le premier exercice, à la suite d'un changement de syndic, d'une décision d'AG ou d'une clôture anticipée.
* L'exercice peut se terminer n'importe quel mois de l'année.
* Les décomptes de charges sont produits selon les périodes comptables générées pour l'exercice.

Cette page décrit le fonctionnement actuel du code, principalement porté par :

* `finance\accounting\FiscalYear`
* `finance\accounting\FiscalPeriod`
* `realestate\funding\ExpenseStatement`

## Concepts

Trois objets structurent le cycle comptable.

* `FiscalYear` : exercice comptable. Il porte les dates, la fréquence de découpage, les périodes, le solde d'ouverture et le solde de clôture.
* `FiscalPeriod` : période comptable opérationnelle. C'est l'unité sur laquelle les écritures sont imputées et pour laquelle un décompte de charges est produit.
* `ExpenseStatement` : décompte de charges. Il est généré pour une période, puis son passage en comptabilité déclenche la clôture de cette période.

La règle importante est la suivante :

> La clôture opérationnelle d'une période comptable est déclenchée par l'`ExpenseStatement` de cette période. Si cette période est la dernière de l'exercice, le même traitement clôture aussi le `FiscalYear`.

## Périodicité

Un exercice est découpé selon la fréquence définie sur `FiscalYear.fiscal_period_frequency`.

Fréquences prévues :

* `Y` : annuel
* `S` : semestriel
* `Q` : trimestriel
* `T` : quadrimestriel
* `M` : mensuel

La génération des périodes est réalisée par l'action `FiscalYear.generate_periods`. Elle supprime les périodes existantes de l'exercice puis les recrée à partir des dates de début et de fin et de la fréquence.

Pour un premier exercice, le calcul tient compte des dates configurées sur la copropriété afin de pouvoir produire une première période plus courte ou plus longue.

## Workflow de l'exercice

Statuts de `FiscalYear` :

* `draft`
* `preopen`
* `open`
* `preclosed`
* `closed`

### `draft`

L'exercice est en préparation. Les dates, la fréquence et les périodes peuvent encore être structurées.

### `preopen`

Le passage en `preopen` prépare l'exercice avant ouverture.

Effets principaux :

* si aucune période n'existe, les périodes sont générées ;
* l'ordre, le code et le nom des périodes sont recalculés ;
* les séquences de numérotation sont générées ;
* si aucun exercice suivant n'existe, un exercice suivant est créé en `draft`.

Un exercice ne peut être pré-ouvert que s'il est en `draft`, que sa structure est cohérente, et qu'il ne précède pas un exercice déjà `open`.

### `open`

Le passage en `open` rend l'exercice actif pour l'imputation.

Conditions principales :

* l'exercice doit être en `preopen` ;
* un exercice suivant doit exister ;
* cet exercice suivant doit pouvoir être pré-ouvert ;
* les dates et périodes doivent rester cohérentes.

Effets principaux :

* l'exercice suivant est automatiquement passé en `preopen` ;
* la copropriété pointe vers l'exercice ouvert via `current_fiscal_year_id`.

### `preclosed`

Le statut `preclosed` correspond à une préclôture de l'exercice. Dans le flux normal, cette transition est provoquée par la préclôture de la dernière période.

Pour préclôturer un exercice :

* l'exercice doit être `open` ;
* l'exercice précédent, s'il existe, doit être `closed` ;
* toutes les périodes de l'exercice doivent être `preclosed` ou `closed`.

Effet principal :

* un solde de clôture provisoire est généré via `ClosingBalance.generate_balance_lines`.

### `closed`

Le statut `closed` est l'état final de l'exercice.

Conditions principales :

* l'exercice doit être `open` ou `preclosed` ;
* un exercice suivant doit exister ;
* l'exercice suivant doit être `open` ou `preopen`.

Effets principaux :

* le solde de clôture définitif est généré et validé ;
* un solde d'ouverture est généré et validé sur l'exercice suivant ;
* si l'exercice suivant est encore `preopen`, il est automatiquement ouvert.

Dans le flux métier normal, cette clôture est déclenchée par le décompte de charges de la dernière période, via l'action `ExpenseStatement.close_fiscal_period`.

### Retour exceptionnel en préclôture

Un exercice `closed` peut revenir en `preclosed` via la transition `repreclose`, sous conditions.

Conditions principales :

* l'exercice doit être `closed` ;
* aucun exercice plus récent ne peut être déjà `closed` ;
* l'exercice suivant doit exister ;
* l'exercice précédent, s'il existe, doit être `closed`.

Effets principaux :

* la dernière période est remise en `preclosed` ;
* le décompte posté de cette période est déverrouillé ;
* le solde d'ouverture de l'exercice suivant est supprimé ;
* le solde de clôture de l'exercice courant est supprimé.

## Workflow des périodes

Statuts de `FiscalPeriod` :

* `open`
* `preclosed`
* `closed`

Les périodes sont créées en `open` par défaut. Le code actuel ne définit pas de statuts `draft` ou `preopen` pour `FiscalPeriod`.

### `open`

La période est ouverte. Elle peut recevoir des pièces et écritures comptables, tant que les autres règles d'imputation l'autorisent.

### Préclôture d'une période

La transition `FiscalPeriod.preclose` passe une période de `open` à `preclosed`.

Conditions principales :

* la période doit appartenir à une copropriété et à un exercice ;
* ses dates de début et de fin doivent être définies ;
* la période doit être `open` ;
* l'exercice parent doit être `open` ;
* toutes les périodes précédentes du même exercice doivent être `closed` ;
* s'il existe déjà un `ExpenseStatement` pour la période, il doit être en `proforma`.

Effets principaux :

* un `ExpenseStatement` est créé pour la période s'il n'existe pas encore ;
* ce décompte reçoit les dates de la période, l'exercice et la copropriété ;
* l'action `ExpenseStatement.generate_statement` est exécutée ;
* si la période est la dernière de l'exercice, l'exercice est passé en `preclosed`.

Cette étape correspond à la génération du décompte proforma.

### Clôture d'une période

La transition `FiscalPeriod.close` passe une période `open` ou `preclosed` en `closed`, si l'exercice parent est `open` ou `preclosed`.

Dans le flux métier, cette transition n'est pas lancée directement par l'utilisateur : elle est appelée par l'`ExpenseStatement` au moment où le décompte est posté.

## Workflow du décompte de charges

L'`ExpenseStatement` est l'objet qui matérialise le décompte d'une période.

Statuts principaux :

* `proforma`
* `posted`
* `cancelled`

La transition métier importante est `validate`. Malgré son nom, elle fait passer le décompte de `proforma` à `posted`.

### Génération du proforma

La préclôture d'une période crée automatiquement un `ExpenseStatement` si aucun proforma n'existe déjà pour la période.

Le proforma :

* est lié à une copropriété ;
* est lié à un `FiscalYear` ;
* est lié à un `FiscalPeriod` ;
* reprend les dates de la période ;
* génère les lignes de décompte via `generate_statement`.

### Posting du décompte

Lors de la transition `ExpenseStatement.validate`, le code exécute notamment :

* `generate_statement`
* `generate_accounting_entries`
* `assign_invoice_number`
* `clear_accounting_entry_lines`
* `validate_accounting_entries`
* `create_fundings`
* `close_fiscal_period`

L'action `close_fiscal_period` est le point clé :

```php
FiscalPeriod::id($expenseStatement['fiscal_period_id']['id'])->transition('close');
if($expenseStatement['fiscal_period_id']['date_to'] === $expenseStatement['fiscal_year_id']['date_to']) {
    FiscalYear::id($expenseStatement['fiscal_year_id']['id'])->transition('close');
}
```

Conséquences :

* tout décompte posté clôture sa période comptable ;
* si la période clôturée est la dernière de l'exercice, le même décompte clôture aussi l'exercice ;
* le solde de clôture de l'exercice courant et le solde d'ouverture de l'exercice suivant sont générés par le workflow du `FiscalYear`.

## Ordonnancement obligatoire

Les périodes doivent être traitées chronologiquement.

Une période ne peut être préclôturée que si toutes les périodes précédentes du même exercice sont déjà `closed`.

Exemple :

* le T1 doit être clôturé avant la préclôture du T2 ;
* le T2 doit être clôturé avant la préclôture du T3 ;
* la dernière période déclenche la préclôture puis la clôture de l'exercice.

## Réouverture et corrections

La réouverture est exceptionnelle et se fait en sens inverse.

### Exercice fermé vers préclôturé

`FiscalYear.repreclose` :

* remet la dernière période en `preclosed` ;
* supprime le solde de clôture de l'exercice ;
* supprime le solde d'ouverture de l'exercice suivant.

### Période fermée vers préclôturée

`FiscalPeriod.repreclose` :

* exige que la période soit `closed` ;
* exige que l'exercice soit `preclosed` ;
* exige que toutes les périodes suivantes du même exercice soient `open` ;
* déverrouille le décompte posté correspondant.

Le déverrouillage de l'`ExpenseStatement` :

* annule l'écriture comptable liée ;
* remet les lignes d'écriture comme non décomptées ;
* supprime les financements générés par le décompte ;
* remet le décompte en `proforma`.

Un décompte ne peut être déverrouillé que s'il est `posted` et qu'aucun décompte posté plus récent n'existe pour la même copropriété.

## Récapitulatif

### Ouverture

1. Créer ou disposer d'un `FiscalYear` en `draft`.
2. Le passer en `preopen`.
3. Les périodes sont générées si nécessaire.
4. Les séquences sont générées.
5. Un exercice suivant est créé en `draft` s'il manque.
6. Passer l'exercice en `open`.
7. L'exercice suivant est automatiquement passé en `preopen`.

### Préclôture d'une période

1. L'utilisateur préclôture une période `open`.
2. Le système vérifie que toutes les périodes précédentes sont `closed`.
3. Un `ExpenseStatement` proforma est créé ou réutilisé.
4. Le décompte est généré.
5. Si c'est la dernière période, l'exercice passe en `preclosed`.

### Clôture d'une période

1. L'utilisateur valide le décompte proforma.
2. L'`ExpenseStatement` est posté.
3. Les écritures comptables sont générées et validées.
4. Les lignes d'écriture incluses dans le décompte sont marquées comme décomptées.
5. Les financements associés aux propriétaires sont créés.
6. L'action `ExpenseStatement.close_fiscal_period` clôture la période.

### Clôture d'un exercice

1. Le décompte de la dernière période est posté.
2. `ExpenseStatement.close_fiscal_period` clôture la dernière période.
3. Comme la date de fin de la période correspond à la date de fin de l'exercice, le `FiscalYear` est clôturé.
4. Le solde de clôture définitif est généré.
5. Le solde d'ouverture de l'exercice suivant est généré.
6. L'exercice suivant est ouvert automatiquement s'il était `preopen`.

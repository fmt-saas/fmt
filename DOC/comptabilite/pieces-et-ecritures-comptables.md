# Gestion des pièces comptables et des écritures



## 1. Principe fondamental

Le système repose sur le paradigme suivant :

> Une pièce postée n’est jamais supprimée physiquement.
> Toute correction repose sur des jeux d’écritures comptables.
> Le moteur comptable ne travaille que sur les écritures validées.

La vérité comptable est portée exclusivement par les `AccountingEntry`.

Les pièces (factures, appels de fonds, OD, etc.) sont des objets métier qui déclenchent la génération d’écritures comptables, mais ne constituent pas en elles-mêmes la réalité comptable.



## 2. Modèle conceptuel

### 2.1 Pièce comptable (Document métier)

Exemples :

- Invoice (achat / vente)
- FundCall
- MiscOperation

#### États possibles

- `proforma`
- `posted`
- `cancelled`

#### Champs structurants

- `accounting_entry_id`
  → référence vers l’écriture active (unique)
- `accounting_entries_ids`
  → historique complet des écritures générées



### 2.2 AccountingEntry

L’objet `AccountingEntry` matérialise une écriture comptable numérotée dans un journal.

#### Champs principaux

- `id`
- `document_id`
- `sequence_number`
- `journal_id`
- `date`
- `status`
  - `validated`
  - `reversed`
- `reversal_entry_id` (nullable)

Chaque `AccountingEntry` contient une ou plusieurs `AccountingEntryLine`, équilibrées (débit = crédit).



## 3. Rôle du moteur comptable

Toutes les balances, décomptes, reports et clôtures utilisent exclusivement :

```sql
WHERE status = 'validated'
```

Les écritures en `reversed` sont totalement ignorées par le moteur comptable.

Il n’existe pas de notion d’écriture “cachée” :
une écriture est soit active (`validated`), soit neutralisée (`reversed`).



## 4. Cycle de vie d’une pièce

### 4.1 Proforma

- Pas d’écriture comptable générée.
- Modifiable librement.
- Supprimable.

Transition vers `posted` :

- Génération d’une `AccountingEntry`
- Validation (`status = validated`)
- Affectation de `accounting_entry_id`
- Passage de la pièce en `posted`



### 4.2 Posted

- La pièce possède exactement une `AccountingEntry` active.
- Cette écriture est `validated`.
- Elle est intégrée dans les balances et décomptes.

Invariant structurel :

```
Si document.status = 'posted'
→ document.accounting_entry_id != null
→ accounting_entry.status = 'validated'
```



### 4.3 Cancelled

- La pièce a été annulée.
- Aucune écriture active ne subsiste.
- Elle n’est plus modifiable.



## 5. Annulation d’une pièce

### 5.1 Préconditions

- La pièce doit être `posted`.
- Elle doit avoir une `AccountingEntry` active (`validated`).

### 5.2 Processus technique

1. Récupération de `accounting_entry_id`.
2. Appel de `AccountingEntry.do('cancel')`.
3. Création automatique d’une écriture inverse.
4. Liaison bidirectionnelle via `reversal_entry_id`.
5. Passage des deux écritures en `reversed`.
6. Passage de la pièce en `cancelled`.
7. Suppression de `accounting_entry_id` (plus d’écriture active).

### 5.3 Résultat

- Aucune écriture `validated`.
- La séquence comptable reste continue.
- Historique complet conservé.
- Impact comptable nul.



## 6. Déblocage d’une pièce (unlock)

Le déblocage permet de revenir à un état modifiable sans considérer la pièce comme définitivement annulée.

### 6.1 Préconditions

- La pièce doit être `posted`.

### 6.2 Processus technique

1. Récupération de `accounting_entry_id`.
2. Exécution de `do('cancel')` sur l’écriture.
3. Neutralisation comptable (mécanisme identique à l’annulation).
4. Détachement de `accounting_entry_id`.
5. Conservation de l’historique via `accounting_entries_ids`.
6. Passage de la pièce en `proforma`.

### 6.3 Résultat

- Aucune écriture active.
- La pièce redevient modifiable.
- Les écritures annulées restent traçables.



## 7. Différence métier : Annulation vs Déblocage

| Action    | Écritures | Statut pièce | Modifiable |
| --------- | --------- | ------------ | ---------- |
| Annuler   | Reversal  | cancelled    | Non        |
| Débloquer | Reversal  | proforma     | Oui        |

Comptablement, les deux opérations neutralisent l’écriture.
La différence est strictement métier.



## 8. Correction d’une pièce

Pour corriger un montant ou une clé de répartition :

1. Déblocage de la pièce (neutralisation des écritures).
2. Modification des données.
3. Re-posting → génération d’une nouvelle `AccountingEntry`.

Résultat :

- Anciennes écritures : `reversed`
- Nouvelle écriture : `validated`
- Une seule écriture active à tout moment.



## 9. Règles d’intégrité

### 9.1 Unicité d’écriture active

Pour une pièce donnée :

```
Il ne peut exister qu’une seule AccountingEntry
telle que status = 'validated'
```

### 9.2 Reversal symétrique

Si A.reversal_entry_id = B.id
Alors B.reversal_entry_id = A.id

### 9.3 Interdictions

- Une écriture `reversed` ne peut redevenir `validated`.
- Une écriture ayant un `reversal_entry_id` ne peut être annulée à nouveau.
- Une pièce `cancelled` ne peut redevenir `proforma`.



## 10. Vues et contrôle des séquences

Par défaut :

- Les vues affichent uniquement les `validated`.

Optionnel :

- Affichage des `reversed` pour audit.
- Contrôle visuel des séquences.
- Vérification des annulations.



## 11. Conséquences architecturales

Cette approche garantit :

- Aucune suppression après validation.
- Séquence comptable continue.
- Neutralisation propre des erreurs.
- Traçabilité complète.
- Séparation claire entre logique métier et ledger.

Le système repose sur une règle simple :

> Seules les écritures `validated` représentent la réalité comptable active.

Toute correction passe par un mécanisme formel d’annulation.


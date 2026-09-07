# Comprendre le rapprochement des paiements et le lettrage

Cette page présente la logique générale qui relie les montants attendus, les mouvements bancaires et les écritures comptables. Elle s’adresse aux gestionnaires, comptables, PO et utilisateurs qui doivent comprendre le résultat d’un rapprochement sans entrer dans les détails d’implémentation.

## L’idée essentielle

Le système répond à trois questions différentes :

1. **Que reste-t-il à payer, à recevoir ou à conserver ?**
2. **Quel mouvement explique tout ou partie de ce montant ?**
3. **Quelles écritures comptables doivent être lettrées ensemble ?**

Ces questions sont liées, mais elles ne sont pas confondues :

| Niveau | Objet | Rôle |
| --- | --- | --- |
| Suivi métier | `Funding` | Représenter un montant ouvert : somme attendue, dette, crédit, trop-payé ou résiduel. |
| Affectation | `FundingAllocation` | Expliquer quelle part d’une source d’apurement est affectée à un montant ouvert. |
| Mouvement bancaire | `Payment` | Représenter une affectation provenant d’une ligne d’extrait bancaire. |
| Comptabilité | `AccountingEntryLine` et `Matching` | Regrouper les lignes comptables appartenant au même périmètre de lettrage. |

En langage courant :

```text
Funding = ce qui reste à suivre
FundingAllocation = comment ce montant est apuré
Payment = affectation provenant de la banque
Matching = regroupement comptable des écritures liées
```

## Pourquoi séparer rapprochement et lettrage ?

Le **rapprochement bancaire** explique un mouvement de banque. Par exemple, un virement de 1 000 € peut régler trois appels de fonds différents.

Le **lettrage comptable** regroupe les lignes au débit et au crédit qui appartiennent au même périmètre comptable. Une ligne comptable bancaire peut être agrégée alors que son montant est ventilé, dans le suivi métier, entre plusieurs montants ouverts.

Il n’existe donc pas nécessairement une correspondance ligne par ligne entre les affectations métier et les lignes d’un `Matching`. Les deux niveaux doivent aboutir à une situation cohérente, mais leur granularité peut différer.

## Cycle de fonctionnement

Le cycle habituel est le suivant :

```text
Une pièce ou une opération crée un montant à suivre
    ↓
Le système crée ou met à jour un Funding
    ↓
Un paiement, une OD, un crédit ou une compensation apporte un montant
    ↓
Une ou plusieurs affectations apurent les Funding concernés
    ↓
Les lignes comptables rejoignent le Matching de leur périmètre
    ↓
Les soldes et les statuts sont recalculés
```

Toute ligne comptable enregistrée sur un compte suivi doit être analysée. Selon sa nature et la situation existante, elle peut :

- créer un nouveau montant ouvert ;
- apurer un montant existant ;
- compenser un montant de sens inverse ;
- laisser un résiduel ;
- conduire à redistribuer une affectation antérieure ;
- rejoindre une position comptable déjà ouverte.

## Le rôle d’une ligne d’extrait bancaire

Une ligne d’extrait bancaire a un double rôle :

- elle matérialise le mouvement réellement observé sur le compte bancaire ;
- elle sert de source aux affectations qui expliquent ce mouvement.

Une seule ligne bancaire peut donc être répartie entre plusieurs `Funding`. Chaque part est identifiable séparément dans le suivi métier, même si la comptabilité conserve une écriture plus agrégée.

Exemple :

```text
Virement reçu : 1 000 €
    ├── 300 € affectés à l’appel A
    ├── 450 € affectés à l’appel B
    └── 250 € affectés à l’appel C
```

Le mouvement bancaire reste unique. Les trois affectations expliquent comment son montant est utilisé.

## Cas particulier des copropriétaires

Pour un copropriétaire, les montants peuvent provenir de comptes économiques distincts, notamment le fonds de réserve et le fonds de roulement. Le rapprochement est néanmoins centralisé sur le **compte collecteur du copropriétaire**.

Cette centralisation permet d’utiliser un paiement reçu sur le compte collecteur pour apurer les montants ouverts admissibles des deux fonds. Le compte comptable réel de chaque écriture reste conservé afin de pouvoir expliquer l’origine du montant.

Lors de la création et de la recherche des `Funding`, le compte collecteur constitue donc le périmètre commun de rapprochement. Le fonds d’origine ne doit pas être perdu pour autant.

## Ordre d’affectation : la règle FIFO

Pour les copropriétaires, un montant disponible est affecté en priorité aux montants ouverts les plus anciens du périmètre applicable. Cette règle « premier entré, premier sorti » concerne notamment :

- les paiements bancaires ;
- les trop-payés et crédits disponibles ;
- les opérations diverses créditrices ;
- les corrections ;
- les compensations entre montants de sens inverse.

L’arrivée d’un nouveau `Funding` ne lui donne pas priorité sur une dette plus ancienne. Si une affectation existante ne respecte plus l’ordre requis, elle peut devoir être découpée et redistribuée de manière traçable.

## Situations courantes

### Paiement partiel

Un paiement inférieur au montant attendu apure seulement une partie du `Funding`.

```text
Montant attendu : 1 000 €
Paiement reçu   :   400 €
Reste à suivre :   600 €
```

La ligne bancaire peut être entièrement expliquée, même si le `Funding` reste partiellement ouvert.

### Paiement groupé

Un seul paiement peut régler plusieurs montants ouverts. Le système ventile le paiement selon les règles d’éligibilité et de priorité, puis conserve une affectation distincte pour chaque montant apuré.

### Trop-payé ou paiement anticipé

Lorsque le paiement dépasse les montants ouverts, la partie excédentaire n’est pas perdue. Elle devient un crédit ou un montant résiduel traçable, utilisable pour une affectation ultérieure.

Si le paiement arrive avant la pièce qui crée la dette, il peut rester en attente ou créer un montant créditeur. Lors de l’arrivée ultérieure de la pièce, le système recherche les crédits disponibles avant de laisser une nouvelle dette ouverte, dans le respect de la règle d’ancienneté.

### Compensation

Deux `Funding` de sens inverse peuvent se compenser. Cette compensation doit être matérialisée par une affectation explicite ; une simple égalité des montants ne suffit pas à justifier le rapprochement.

### OD, correction et solde d’ouverture

Une opération diverse ou un solde d’ouverture suit la même logique que les autres sources comptables. Il doit avoir une origine comptable identifiable et peut créer, apurer ou corriger un montant ouvert.

## Comprendre les statuts

Le statut comptable d’une ligne bancaire et son niveau de rapprochement répondent à des questions différentes :

- `pending` : la ligne doit encore être analysée ou comptabilisée ;
- `posted` : la ligne a été comptabilisée et n’est plus modifiable comme une ligne en préparation ;
- `cancelled` : la ligne a été annulée selon le workflow prévu.

Certaines vues ou anciennes intégrations peuvent aussi exposer `pending`, `part`, `full` ou `not_applicable` comme niveau de rapprochement. Ce champ est historique : l’état métier doit être compris à partir des affectations réellement conservées et du montant restant à expliquer.

Attention :

- une ligne bancaire peut être entièrement expliquée sans solder complètement le `Funding` auquel elle est affectée ;
- un `Funding` peut être soldé par plusieurs mouvements ;
- un `Matching` équilibré décrit une position comptable, pas à lui seul le détail métier des paiements.

## Correction, annulation et historique

Un `Funding` arrivé à échéance ne doit pas être supprimé pour faire disparaître un écart. Il peut être soldé, compensé, corrigé ou annulé logiquement, mais son historique doit rester explicable.

De même, une correction de rapprochement doit intervenir sur sa cause : pièce, ligne bancaire, `Funding`, affectation, OD ou imputation comptable. Le système recalcule ensuite les soldes et le lettrage concernés.

L’objectif est de pouvoir répondre à tout moment aux questions suivantes :

- quel événement a créé le montant ouvert ?
- quel mouvement l’a apuré, totalement ou partiellement ?
- pourquoi une affectation a-t-elle été déplacée ou découpée ?
- quelles écritures composent la position comptable ?
- quel était l’état avant et après une correction ?

## À retenir

Le `Funding` est la vérité du suivi métier, l’affectation explique son apurement, et le `Matching` représente le regroupement comptable. L’utilisateur agit sur la cause métier ou comptable d’un rapprochement ; il ne doit pas créer un lien arbitraire uniquement parce que deux montants se ressemblent.

Pour la référence destinée aux DEV/PO, les invariants, la granularité comptable et les séquences de traitement, voir [Logique de réconciliation des paiements et du lettrage comptable](../../budget/logique-de-reconciliation-et-lettrage.md).

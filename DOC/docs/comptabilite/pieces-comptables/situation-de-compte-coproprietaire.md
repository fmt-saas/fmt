# Document de situation de compte copropriétaire

Le système permet de générer un document annexe présentant la **situation détaillée du compte d’un copropriétaire** à une date donnée.

Ce document correspond au **grand livre du compte auxiliaire du copropriétaire**, limité à l’exercice comptable en cours. Il fournit une vue chronologique complète de l’ensemble des mouvements ayant affecté la situation comptable du copropriétaire.

Ce document est généré automatiquement lors de la production du **décompte de charges propriétaire**. Il est annexé au PDF du décompte sous la forme d’un document distinct. Afin de garantir une mise en page cohérente dans l’assemblage final du PDF, la même règle de pagination est appliquée : chaque document est aligné sur un **nombre pair de pages**, ce qui permet d’assurer une séparation propre entre les différentes sections du dossier envoyé au copropriétaire.



## Contenu du document

Le document présente, dans l’ordre chronologique, l’ensemble des mouvements ayant impacté la situation comptable du copropriétaire.

Chaque ligne correspond à une **opération comptable ayant généré au moins une écriture sur un compte auxiliaire associé au copropriétaire**.

Ces opérations peuvent notamment provenir de :

- appels de provisions
- décomptes de charges
- appels exceptionnels
- paiements enregistrés via les extraits bancaires
- remboursements éventuels
- reports de solde provenant de l’exercice précédent

Chaque mouvement est présenté sous forme d’une ligne contenant les informations suivantes :

| Champ   | Description                                        |
| - | -- |
| Date    | Date comptable de l’opération                      |
| Infos   | Libellé explicatif de l’opération                  |
| Débit   | Montant porté au débit du compte copropriétaire    |
| Crédit  | Montant porté au crédit du compte copropriétaire   |
| Solde   | Solde cumulatif du compte après l’opération        |
| Extrait | Référence éventuelle de l’extrait bancaire associé |

Le solde affiché dans chaque ligne est un **solde cumulatif**, recalculé dynamiquement lors de la génération du document.

Le **solde final** représente la situation comptable du copropriétaire à la date demandée :

- solde positif → montant dû par le copropriétaire à l’ACP
- solde négatif → avance du copropriétaire sur l’ACP



## Solde initial

Le document commence par une ligne représentant la situation initiale du compte, appelée **solde initial**.

Ce solde correspond à la **balance d’ouverture des comptes du copropriétaire** pour l’exercice concerné.

Selon les cas, cette balance peut provenir :

- de la **clôture de l’exercice précédent**
- d’une **reprise de comptabilité lors d’un import initial**

Même si certains extraits comptables affichent un solde initial égal à zéro, la méthode correcte consiste à utiliser la **balance d’ouverture réelle des comptes auxiliaires** afin de garantir la continuité comptable entre les exercices.

La première ligne du document prend donc la forme suivante :

```
date: null
description: "Opening balance"
balance: opening_balance
```

Cette ligne permet de visualiser clairement le report de la situation comptable provenant de l’exercice précédent.



## Source des données

La situation de compte est reconstruite à partir des **écritures comptables (`AccountingEntryLine`)** associées au copropriétaire.

Dans le modèle de données, chaque écriture comporte un champ `ownership_id` permettant d’identifier directement le copropriétaire concerné. Cette relation permet de récupérer l’ensemble des mouvements comptables sans devoir reconstruire les comptes auxiliaires associés.

Les écritures prises en compte sont celles qui respectent les conditions suivantes :

- `condo_id` correspond à la copropriété concernée
- `ownership_id` correspond au copropriétaire concerné
- `status = validated`
- `entry_date` appartient à l’intervalle de dates demandé

Les écritures sont ensuite triées par ordre chronologique.



## Solde d’ouverture et projection de balance

Le solde initial est déterminé à partir des projections de balance stockées dans l’objet **`AccountBalanceChange`**.

Cet objet représente une série temporelle de balances cumulées par compte. Chaque enregistrement contient le total des débits et crédits d’un compte après l’application de toutes les écritures enregistrées à une date donnée.

Pour déterminer le solde d’ouverture du copropriétaire à une date donnée, le système :

1. récupère l’ensemble des comptes comptables associés au copropriétaire (`Account` avec `ownership_id`)
2. recherche, pour chacun de ces comptes, la dernière projection `AccountBalanceChange` dont la date est antérieure ou égale à la date de début
3. additionne les balances nettes de ces comptes afin d’obtenir le solde initial global du copropriétaire

Cette approche permet de déterminer le solde d’ouverture sans recalculer l’ensemble de l’historique comptable.



## Calcul du solde cumulatif

Une fois le solde initial déterminé, le document est construit en parcourant les écritures comptables dans l’ordre chronologique.

Le solde est recalculé ligne par ligne selon l’algorithme suivant :

```
balance = opening_balance

for entry in entries:

    balance = balance + entry.debit - entry.credit
```

Chaque ligne du document contient alors :

```
{
    entry_date,
    description,
    debit,
    credit,
    balance
}
```

Ce mécanisme garantit que le solde affiché à chaque ligne correspond exactement à la **situation comptable réelle du copropriétaire à la date de l’opération**.



## Totaux

En fin de document, les totaux suivants peuvent être calculés :

```
total_debit  = SUM(entries.debit)
total_credit = SUM(entries.credit)
```

Le solde final doit correspondre à :

```
opening_balance + total_debit - total_credit
```

Ce solde doit être strictement identique au solde du copropriétaire dans la balance comptable à la date demandée.
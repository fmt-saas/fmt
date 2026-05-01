# Lettrage

Pour faciliter la comptabilité et identifier les écritures qui nécessitent d'être balancées (et identifier les éventuelles anomalies), on utilise un système de lettrage.
Le lettrage consiste à relier une pièce comptable à son ou ses règlements, ou encore une note de crédit avec la facture qu’elle corrige.

Ce processus permet de vérifier que les dettes et créances ont bien été réglées et de s’assurer que les comptes de tiers (clients ou fournisseurs) reflètent une situation exacte.

### Lettrage (`Matching`)

* **Définition** : un *Matching* représente le rapprochement entre plusieurs écritures comptables.

* **Principe** : toute écriture comptable est, par nature, destinée à être rattachée à un Matching.

* **Règle fondamentale** : la somme des écritures associées à un même Matching doit être nulle, c’est-à-dire que les débits et crédits s’équilibrent.

* **Utilité** :
  
  * Identifier qu’une facture est soldée par un ou plusieurs paiements.
  * Regrouper plusieurs écritures qui s’annulent (ex. facture et avoir).
  * Conserver une trace structurée des rapprochements.

* **Caractère universel** : le Matching agit comme la mécanique de base du système, purement comptable et indépendant de toute notion de document.

### Financement (`Funding`)

* **Définition** : un *Funding* est toujours rattaché à une **pièce comptable** (`accounting document`): facture d'achat, appel de fonds, décompte de charge, transfert entre comptes, remboursement à un tier ou opération diverse

* **Rôle** : lier une pièce comptable à ses paiements effectifs.

Un Funding permettent les suivi des paiements (entrants ou sortants), afin de solder une pièce par exemple avec une OD ou une compensation.

Les Funding permettent également la génération d'ordre de mouvements bancaires (SEPA).

En plus du modèle Matching, les Funding utilisent des champs supplémentaires, dont les plus importants sont:

- `bank_account_id`
- `counterpart_bank_account_id` 
- `is_sent`
- `counterpart_accounting_account_id`

Un Funding peut inclure des écritures qui ne sont pas des paiements, afin de solder une pièce avec d’autres opérations.

### Universalité du lettrage

Avec la modélisation adoptée, il est toujours possible d’effectuer un lettrage, quelle que soit la situation :

* Lorsqu’une pièce existe, elle peut être rapprochée de paiements identifiés ou d’écritures arbitraires.
* Lorsqu’aucune pièce n’existe (par exemple une ligne bancaire isolée), l’écriture correspondante peut être intégrée dans un Matching générique.

Ainsi, toutes les écritures, qu’elles soient ou non rattachées à une pièce, peuvent être équilibrées entre elles via un Matching.

### Matching & Funding

* un Funding est un cas particulier d'un Matching
* un Funding est toujours rattaché à une pièce comptable "accounting document"
* les pièces comptables possibles sont : facture d'achat, appel de fonds, décompte de charge, transfert entre comptes, remboursement à un tier et opération diverse

Les écritures comptables impliquées se font en 1 ou 2 temps, en fonction du type de pièce

* facture d'achat, appel de fonds, décompte de charge, OD: 
  
  * des écritures sont faites au moment de l'encodage (validation) de la pièce
  * des écritures d'extourne sont faites dans le journal de banque lors du lettrage des extraits de banque

* remboursement, transfert : 
  
  * les écritures sont faites uniquement au moment de la réception du paiement (mouvement bancaire) sur base des fundings générés

Dans certains cas, des lignes d'extrait peuvent correspondre à un mouvement qui n'a pas été initié par une pièce comptable.
(mouvement non anticipé)

Dans ce cas, l'opération de "posting" de la bankStatement line déclenche alors la création d'une écriture, qui génère son écriture comptable et son Funding, on crée alors des paiements qu'on associe au Funding, et on poste le paiement pour balancer les écritures.

* Les Fundings permettent de lier des lignes d'extraits avec des objets métier qui ne génèrent pas nécessairement d'écritures (suivis de paiements/transfert) : on parle de réconciliation.
* Les Matching permettent le lettrage entre les lignes aux crédit et au débit des écritures sur un compte donné :  on parle de lettrage.

Les écritures comptables (accounting entry lines) peuvent donc être liées aux deux types d'objets (funding_id, matching_id).

### Logique du lettrage

* Une line d'extrait peut être rattachée à plusieurs Funding (via les Payments)
* Une écriture (accontingEntryLine) ne peut être rattachée qu'à un seul lettrage (Matching)

Logique de la synchronisation des lettrages: 

Lorsqu'on sélectionne plusieurs lignes pour les lettrer ensemble : 

1) on détache la ligne courante et les lignes fournies de leur matching_id (les éventuels Matching vides sont supprimés)
2) on créée un Matching et on y attache toutes les lignes
3) on met à jour les matching_level du Matching et des lignes

Lorsqu'on supprime un lettrage (Matching): 

* mise à jour de toutes les écritures qui y sont liées (matching_id = null)

Lorsqu'on modifie l'assignation à un lettrage (Matching)

* mise à jour du Matching et mise à jour de toutes les écritures qui y sont liées (`matching_level`: `part` ou `full`)

### Réconciliation automatique

Les écritures faites par les lignes d'extrait sur des comptes de balance, doivent normalement se réconcilier avec des écritures existantes.
Dans certains cas, les financements (Funding) permettent de faire un rapprochement automatique.

Lorsque des financements sont impliqués (mouvement attendu), le lettrage est généré automatiquement sur base des informations du Funding correspondant.

### Lettrage manuel ou semi-automatique

on choisit un compte de copropriétaire sur la bank statement line

a) soit il existe un funding (non balancé) mais on ne l'a pas identifié -> chercher les Funding pour le compte sélectionné (accounting_account_id)
b) soit il n'existe pas de funding, mais il existe des Matchings non balancés -> chercher les Matching pour le compte sélectionné (accounting_account_id)
c) soit il n'existe aucun Matching mais il y a des écritures non lettrées (non liées à un Matching/Funding) -> chercher les écritures orphelines sur ce compte

On précise le `aounting_account_id` :

1. S'il s'agit d'un funding, on crée un paiement (identique à la réconciliation auto)

2. S'il s'agit d'un matching, on stocke le Matching pour créer une écriture lors de la validation de la ligne et l'associer à ce Matching
    auto si paiement recu correspond exactement au solde d'un Matching non balancé 

3. Si aucun lettrage n'est fait, on créée une écriture dans le journal FIN/BANK non lettrée
    si aucun Matching ouvert n'existe avec une écriture sur le compte visé, on peut directement créer un Matching et mettre l'écriture dessus

L'application FMT utilise des Components Angular spécifiques pour lettrer les écritures (AccountingEntryLine)

#### Controller "matchAccountingEntry" pour lettrage arbitraire d'une écriture comptable

Dans le cas d'un lettrage arbitraire manuel, on attache simplement l'écriture au Matching

#### Controller "matchBankStatementLine" pour réconciliation manuelle d'une ligne d'extrait bancaire

input:

* id de ligne d'extrait (on retrouve le compte et le montant)

Une liste présente toutes les lignes d'écriture non totalement lettrées associées à ce compte comptable (filtre possible sur base d'une plage de dates)

Il est possible de sélectionner : 

* soit un ensemble d'écritures qui balancent le montant de la ligne d'extrait
* soit une seule ligne associée à un financement en attente de paiement

output: 

* un appel est fait à l'action `finance_bank_BankStatementLine_match`

Si un Funding est impliqué : un paiement est crée pour la ligne (dans ce cas, il n'y a pas de manipulation de lettrage à cette étape: elles seront faites au moment de la validation de la ligne d'extrait bancaire).
Dans le cas contraire, les lignes sélectionnées sont placées sur un nouveau lettrage (Matching)

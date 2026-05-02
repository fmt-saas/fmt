# Ecritures comptables

Le flux d'écritures comptables est alimenté par les factures et les pièces comptables.

Types d'écritures : 

* factures, notes de crédit, notes de frais : ligne par ligne (ACH - journal des achats)
* mouvements bancaires (BAN - journal des banques)
* opérations diverses (OD)
* comptabilisation provisions (imputation et report de charge automatique - partie dans les charges et partie à reporter)
* comptabilisation des appel de fonds (toujours avec clés de répartition)

Possibilité d'encodage avec ou sans période (par défaut, la date de prélèvement sur le compte bancaire):

* période (ex. nettoyage du bâtiment en janvier) [le plus courant] - possibilité de choisir des périodes prédéfinies
  * pour tout l'exercice (en cours)
  * pour le mois de la facture
  * pour le mois précédent (possibilité d'assigner la période par type de fournisseur)
* pas de période (date de validité date_fin = date_début)


Répartition sur la période renseignée :

* sur l'exercice: nb/jour exercice en cours / durée exercice 6xx (+)
* le reste sur le compte "charges à reporter" (bilan) 490 (+)

Au début d'exercice (automatique) : on utilise le compte de charge pour faire une OD => 490 en (-) et 6xx en (+)



Lors de l'encodage d'une facture, ligne par ligne, on renseigne le taux de TVA appliqué, mais il n'y a pas d'écriture pour la TVA (uniquement gérer pour pouvoir communiquer au proprio ce qu'il peut)

* possibilité de marquer une facture comme ne devant pas (encore) être payée (validation)
* identification / distinction entre factures domiciliées et non domiciliées (confusion "à ne pas payer")



Dans le cas où on a une facture, et qu'on sait qu'elle correspond à un montant qui devrait arriver à un moment sans savoir exactement quand, on crée une ligne sur un compte d'attente (49xx : directement au bilan) : 

* stocks (exemple : clés)
* sinistres
* factures en attente de répartition (comptable ne sait pas où la mettre : à reclasser ultérieurement)



Important: une fois qu'une écriture est faite, elle ne peut pas être supprimée, mais uniquement annulée par une autre écriture).



## Structure

Les écritures comptables sont modélisées sur base de deux entités:

- **AccountingEntry** : Représente une écriture comptable en tant qu’ensemble cohérent d’opérations affectant la comptabilité.
- **AccountingEntryLine** : Correspond à une ligne d’écriture détaillant l’impact sur un compte spécifique.

La logique est basée sur les principes suivants : 

* **Équilibre obligatoire** : La somme des montants débités et crédités d’une `AccountingEntry` doit toujours être égale.
*  **Transactionnalité** : Une écriture n’est prise en compte dans la comptabilité qu’après validation.

*  **Immutabilité** : Une fois validée, une écriture ne peut plus être supprimée, seule une contre-passation peut la corriger.



Chaque `AccountingEntry` est composé d’une ou plusieurs `AccountingEntryLines`, qui détaillent :

- Le **compte comptable** concerné.
- Le **montant au débit ou au crédit**.
- La **référence du journal** et de la période concernée.
- L’**éventuel rattachement à une pièce comptable**

Une `AccountingEntry` suit un cycle de validation et possède les statuts suivants :

1. **Pending** : Écriture en attente de validation manuelle, modifiable.
2. **Planned** : Écriture en attente de validation automatique, non modifiable.
3. **Validated** : Écriture définitivement enregistrée, impactant la balance.



| Situation                         | `status`    | `is_temp` | `is_cancelled` | Comptabilisée ? | Supprimable ?          |
| --------------------------------- | ----------- | --------- | -------------- | --------------- | ---------------------- |
| En attente de validation manuelle | `pending`   | false     | false          | ❌               | ✅                      |
| Planifiée pour futur              | `planned`   | false     | false          | ❌               | ✅                      |
| Validée (définitive)              | `validated` | false     | false          | ✅               | ❌                      |
| Validée temporaire                | `validated` | true      | false          | ✅               | ✅ (si période ouverte) |
| Annulée (via extourne)            | `validated` | false     | true           | ✅ (neutralisée) | ❌                      |



## Factures de ventes

En principe, une **ACP** n'a pas de vocation lucrative et n’émet donc pas de factures. Cependant, dans certains cas, une copropriété peut être amenée à facturer, notamment :

- **En cas de location d’un bien ou d’un équipement** appartenant à la copropriété.
- **Lors du partage de frais entre plusieurs copropriétés**, par exemple lorsqu’une **chaudière commune** est utilisée par plusieurs ACP (même avec des syndics distincts) et qu’une seule d’entre elles reçoit la facture du fournisseur.

Par ailleurs, les **appels de fonds** et les décomptes propriétaires sont considérés (et traités) comme des "factures de vente".



## Factures d'achat (fournisseurs)

Par convention, il y a toujours un compte comptable par fournisseur (assignés sur base de l'ID du fournisseur - issu de la DB centralisée).

Après import (document digestor), une tentative d'assignation automatique est déclenchée, afin de lier pièce avec le compte comptable du fournisseur correspondant. En cas d'échec, l'assignation doit être réalisée manuellement (débit du compte fournisseur).



Un système de "**lettrage**" permet d'assister le syndic dans la réconciliation des factures d'achat (facture, note de crédit) avec une imputation correspondante (paiement par banque ou via fonds de réserve).

Un code couleur (vert, jaune, rouge) renseigne sur le fait qu'une pièce comptable a pu ou non être réconciliée.



Les **factures fournisseurs** (qui incluent les factures et notes de crédit fournisseur, ainsi que les notes de frais du Syndic) sont toujours validées avant d'être encodées (écritures comptables).



* numérotation des factures d'achat (utilisation d'une référence interne unique, pour le commissaire aux comptes)
  * valeurs supportées dans le format: ```{year}, {period}, {sequence}```
  * séquence par période (défaut) ou à l'année (is fréquence = A)


## Notes de crédit

Des notes de crédit peuvent être émise de manière interne pour annuler des écritures qui doivent être corrigées.

Dans le cas où un copropriétaire demande l'annulation (complète ou partielle) d'une facture, la création de la note de crédit correspondante se fait en plusieurs étapes : 

1) **Formulaire de demande** (`RefundRequest`) :  
   - À transmettre au manager pour validation.  

   - Informations obligatoires:  
     - Facture concernée.  
     - Montant total ou partiel.  

2) **Validation et imputation** :  
   - Si le manager valide, la note de crédit est créée et imputée automatiquement (symétrique à la facture initiale).  

Une **note de crédit** ne peut pas être validée si elle ne se rapporte pas à une facture (par exemple qui n'avait pas été encodée car contestée).

**Rectification de l'imputation d'une facture**:

Une fois qu'une facture fournisseur est encodée elle est validée et ne peut plus être modifiée. Une action est disponible pour "**déverrouiller**" une facture : dans cas des OD sont générées pour annuler les écritures d'encodage.

Il est par contre possible de modifier certaines infos sans déverrouiller la facture:

- le fournisseur / l'imputation au compte comptable (à condition qu'il reste en classe 6)
- la clé de répartition
- la répartition PROP/LOC

En cas de changement (de manière atomique), un log permet de retrouver le changement + écran pour récap le suivi (@voir Tasks).



## Notes de frais

Dans le cas des **notes de frais**, la contrepartie est le gestionnaire (exemple: utilisation du compte "6" pour les frais d'AG), et lesécritures comptables sont enregistrés dans le journal des achats (ACH).



## Opérations diverses (OD)

Il est possible de créer manuellement et à tout moment, des écritures comptables d'OD (Opérations Diverses).

* ouverture comptable de l'immeuble (OD de reprise comptable)
  * seul moment où on autorise à encoder dans le compte bancaire
* modification de stock par rapport à quelque chose qui a été acheté sur un exercice clôturé
* clôture d'un compte sinistre (déduction de la franchise et pertes indirectes, mais qui ne tombe pas juste)
* transfert entre fonds de réserve (fonds trop perçu par rapport à la dépense finale)
* apurer le compte d'attente


Lorsqu'on utilise un compte de charge, la clé de répartition est automatiquement complétée (mais peut être modifiée manuellement), et les infos de période doivent toujours être précisées.








## Ecritures de clôture de période

A la clôture d'une période, des écritures sont générées pour transférer les charges des comptes de charge vers les comptes des copropriétaires, et permettre de : 

- Constater la consommation éventuelle de **fonds de réserve**
- Affecter les charges **réelles** de la période aux copropriétaires
- Conserver une trace des **frais privatifs**
- S'il s'agit de la dernière période : préparer l'exercice suivant en soldant les comptes temporaires liés aux provisions, aux charges à reporter, etc.


### Ecritures liées au décompte de charges

La validation du décompte correspond à la clôture d'une période (il n'est plus possible d'ajouter des charges à la période après cette étape).



Au débit : 

* les comptes propriétaires, selon ce qui est déterminé dans le décompte de charges

Au crédit : 

* les frais privatifs (s'ils ont déjà été mis sur le compte proprio, ils n'apparaissent pas dans le décompte)
* utilisation de fonds de réserve
* charges communes


#### Exemple


| Compte                      | Débit | Crédit | Description                                           |
| --------------------------- | ----- | ------ | ----------------------------------------------------- |
| `410xxx` – Copropriétaire   | X €   |        | Créance sur le copropriétaire (charge à lui facturer) |
| `611xxx` – Charges communes |       | Y €    | Extourne partielle ou totale des charges réparties    |
| `643xxx` – Frais privatifs  |       | Z €    | Pour les charges privatives                           |
| `681600x1` – Fonds utilisés |       | W €    | Si une part a été couverte par un fonds de réserve    |

Pour plus de lisibilité, une écriture distincte est réalisée par copropriétaire.


### Autres écritures de clôture


#### Charges à reporter 


Les écritures de charges à reporter sont planifiées au moment de l'encodage d'une facture d'achat (uniquement lorsqu'il y a une répartition sur plusieurs périodes), et sont générées au premier jour de la période concernée.



#### Utilisation des fonds de réserve

Les écritures liées aux dépenses couvertes par des fonds de réserve sont faites directement à la validation d'une facture d'achat.



#### Arrondi

Le solde d'arrondi ne représente que de quelques cents à quelques euros et est uniquement apuré en fin d'exercice comptable (annuel).



## Écritures d'ouverture de période

Il n'y a pas d'opération formelle d'ouverture de période : une période est toujours imputable tant qu'elle n'est pas clôturée.

Cependant, des opérations automatiques peuvent être planifiée pour le premier jour d'une période. C'est le cas, par exemple, des écritures comptables planifiées ('planned'), générées lors de la répartition d'une facture d'achat sur plusieurs périodes.



**Récap - 2 cas particuliers pour les accounting entries :**
  1. écritures de report temporaires ('is_temp' - sont prises en compte dans la balance, mais pouvoir être supprimées)
  2. écritures en attente d'ouverture de période ('planned' - ne sont pas prises en compte dans la balance, mais ne peuvent pas être supprimées)



A chaque ouverture de période comptable (passage de période), on peut identifier les écritures nécessaires (sur base de la date et de leur status "planned").

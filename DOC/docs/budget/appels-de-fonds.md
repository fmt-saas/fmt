## Appels de fonds
### Présentation 
Du budget voté en AG, découle la planification des appels de fonds en début d'exercice.

On planifie les appels (`FundRequest`) pour tout l'exercice et pour tous les copropriétaires. Les appels de fonds sont décomposés en entrées distinctes, selon une clé de répartition, entre les copropriétaires, et planifiés selon la date de début et la fréquence renseignés.

Un appel peut être modifié en cours d'année. Il peut en effet y avoir des montants prévisionnels qui sont établis en attendant la validation du budget (on n'appelle normalement pas de montants qu'on sait trop élevés).


Un appel de fonds est considéré comme **facture de vente**, et encodé comme tel (**journal de ventes**).

Types d'appels:

* fonds de roulement
* fonds de réserve
* provision pour charge
* provision pour charge exceptionnelle




#### Fonds de roulement

Le fonds de roulement d’une copropriété équivaut au capital d’une société, il est constitué au début de la vie de l’immeuble et reste fixe au passif du bilan. Il pourra cependant être adapté au cours de la vie de l’immeuble pour rester aligner avec l’inflation ou en cas de changement de type de décompte (il doit être plus important si les décomptes sont à frais réels sans appel de provisions pour permettre au syndic d’honorer les factures de la période en cours).  En théorie, le fonds de roulement correspond au compte courant.



A la création de la copro, on constitue un fonds de roulement pour payer les factures dans l'intervalle entre la réception des factures et les provisions versées par les copropriétaires. Cet appel utilise une clé de répartition "**charges communes**" pour calculer le montant dû par chaque propriétaire.

Notes :

* Le fonds de roulement est un compte du Passif (100), équilibré avec les comptes 4101ccccc des copropriétaires. 

* Lorsque tous les propriétaires ont tout payé, leurs comptes sont à 0 et tout est sur le compte de l'Actif "banque" (500).

* La quote-part dans le fonds de roulement est intégralement remboursée à un copropriétaire vendeur et réclamée au nouveau copropriétaire en cas de vente.

* Les provisions pour charge se mettent dans le compte 4101ccccc des copropriétaires.

* distinction : compte centralisateur et sous-compte (fournisseurs, copropriétaire, ...)
* il est toujours possible d'afficher un bilan comptable avec les montants regroupés par compte


##### Création d'un fonds de roulement

A la création d'un appel de fonds pour le fonds de roulement, on génère les répartitions par lot, selon les montants et les clés (en principe clés de charges).

##### Validation d'un fonds de roulement

A la validation d'un appel de fonds, les écritures sont ajoutées au compte de chaque propriétaire:

* **débit** sur les comptes **4101cccc** des copropriétaires (identifié par chaque copropriétaire)

et sur le compte de fond de roulement:

* **crédit** sur le compte **100000** (identifié par le code d'assignation "working_capital")





#### Fonds de réserve

Le fonds de réserve est une obligation légale : il faut conserver un minimum de 5% de l'exercice annuel en provision (dérogation pour les immeubles neufs).

Cependant, l’assemblée générale peut décider d’appeler des montants supérieurs. Il existe aussi des dérogations :

- Si la réception provisoire des parties communes à moins de 5 ans, les immeubles ne sont pas soumis à cette obligation
- Si l’assemblée générale décide de ne pas constituer de fonds de réserve à une majorité de 4/5ème des voix

Le fonds de réserve correspond à des provisions pour des gros travaux futurs. En théorie, ce fonds correspond à l'argent disponible sur le compte épargne.

Il est nécessaire de pouvoir faire le suivi des provisions financières dédiées aux dépenses exceptionnelles ou imprévues de la copropriété, et de planifier les appels de fonds et leur imputation dans les comptes.

* les fonds de réserve sont en compta sur un compte `16xx` (il peut y avoir plusieurs fonds de réserves - c'est nécessaire lorsqu'il y a des clés de répartition distinctes)
* un fonds de réserve est associé à une clé de répartition
* un fonds de réserve ne peut jamais passer en négatif


* un fonds de réserve doit toujours être utilisé avec la clé avec laquelle il a été appelé



Note : L'imputation d'une facture à un fonds de réserve se fait via des écritures comptables : 

* créditer un compte `68xxx` (compte "utilisation de fonds de réserve") 
* débiter un compte `16xx` (fonds de réserve) : le compte "fonds de réserve général" est le `160`



L'appel pour fonds de réserve est : 

* soit décidé en AG pour des travaux planifiés au cours de l'exercice à venir
* soit avec un appel unique pour des travaux nécessaires en cours d'exercice



Lors d'un appel de fonds de réserve:

* on crédite le compte "fonds de réserve" 16xxx
* on débite le compte propriétaire

Lorsqu'un copropriétaire fait le versement, sont compte 4100 est crédité, et le compte banque est débité (il peut être nécessaire de faire un transfert interne compte courant > compte épargne).



#### Appels de Provision pour charge

La demande des versements pour provision se fait en une seule fois, juste après la validation de l'AG (par courrier), avec un montant et un VCS, ainsi que la liste des dates d'échéances.

Note: le document de **décompte propriétaire** reprend une "**situation de compte**" en annexe, détaillant toutes les variations liées au compte du propriétaire en renseignant uniquement ce qui concerne l'exercice en cours.



**Validation d'un appel de provision pour charges:**

* crédit: **701** "provisions"
* débit : compte copropriétaire

Sur base du (dernier) budget validé en AG.



#### Appels de Provision pour charge exceptionnelle

Fonctionnement similaire à l'Appel de Provision, mais avec un montant distinct, sans répétition ("appel unique"), et non lié au budget. 

Une charge exceptionnelle peut correspondre au défaut de paiement d'un ou plusieurs copropriétaires, ou à l'augmentation importante et non anticipée de certaines charges.

L'appel est fait en déterminant la clé de répartition sur base du motif.



### Mise en Oeuvre

L'objectif est de permettre une **ventilation par propriétaire (Ownership)** des montants à appeler au cours d'un exercice comptable, en assurant une gestion rigoureuse et traçable des différents types d'appels de fonds.

Un appel peut se faire à tout moment et peut être éventuellement réparti sur un intervalle de temps.


Il existe **4 types d'appels** de fonds, chacun correspondant à une nature comptable différente :

| Type                 | Libellé                                 |
| -------------------- | --------------------------------------- |
| `working_fund`       | Fonds de roulement                      |
| `reserve_fund`       | Fonds de réserve                        |
| `expense_provisions` | Provisions pour charges courantes       |
| `work_provisions`    | Provisions pour charges exceptionnelles |

> Note : Pour un exercice fiscal, il peut y avoir plusieurs appels d'un même type.



#### Structure

- Appel de fonds (`FundRequest`)
   Représente l’appel global d’un certain type pour un exercice. Reprend la date de l'appel (ou intervalle de dates), le compte comptable à créditer, le compte bancaire de destination, et le délai de paiement.
- Lignes d'un appel (`FundRequestLine`)
   Permet d’associer un **montant** à une **clé de répartition**.
   Le montant total d'un `FundRequest` correspond à la somme de ses `FundRequestLine`.
- Ventilation par propriétaire (`FundRequestLineEntry`)
   Les montants à appeler sont **calculés et ventilés** automatiquement pour chaque propriétaire, en fonction de leurs lots, et sur base des quotités associées aux clés de répartition renseignées.



#### Workflow (`FundRequest`)

Une FundRequest passe par plusieurs états : `brouillon` > `validée` > `annulée`

Une fois qu'un `FundRequest` est validé, il n'est plus possile de changer la date et/ou la période. Par contre, tant qu'il n'est pas annulé, il est possible de modifier le montant des lignes et de re-générer la ventilation par propriétaire.



#### Actions disponibles (`FundRequest`)

- **Valider** la `FundRequest` : possible uniquement si aucune exécution n'a encore été facturée (exercice, date ou intervalle, compte comptable, ...)

- **Annuler** la `FundRequest` : possible uniquement si aucune exécution n'a encore été facturée (sauf si annulée)

  

#### Logique

* Les `FundRequest` et `FundRequestLine` sont créées manuellement.
* Les `FundRequestLineEntry` et `FundRequestLineEntryLot` sont créés automatiquement sur base de la configuration de la Copropriété (lots) et des clés de répartitions sélectionnées (action `generate_allocation`).
* Le **moment de l'appel** dépend à la fois de l'utilisation ou non d'un intervalle de temps (une ou plusieurs dates) et des conditions de versement (`payment_terms_id`). Ce sont les conditions de paiement qui définissent le moment auquel le montant est attendu, par rapport à une échéance (anticipatif ou à terme échu).
* Les dates exécution de l'appel dépendent soit d'une date unique, soit de l'intervalle de date fourni.
  * la fréquence (répétition tous les X mois) est basée sur celle de l'exercice comptable (trimestriel; quadrimestriel; semestriel; annuel), mais peut être adaptée manuellement
  * Dans tous les cas, les dates sont arbitraires, mais soumises à des contraintes d'intégrité (date de fin strictement supérieure à la date de début, ...)


- Le **compte à créditer** dépend du **type d'appel**, et peut être modifié dynamiquement (parmi les comptes compatibles définis dans le plan comptable associés au type d'appel).

- Tant qu’un **exercice n’est pas clôturé**, une `FundRequest` peut être modifiée (toute modification doit **entraîner un recalcul** des montants théoriques à appeler par propriétaire).



#### Types de Montants

- **Montant requis** : défini dans la `FundRequest`.

- **Montant alloué** : montant ventilé entre les copropriétaires.

- **Montant appelé** : montant réellement appelé via une exécution (le montant appelé correspond à la somme des `FundRequestExecution` facturées).

  
#### Exécution des Appels

Un **prévisionnel** est généré à partir des `FundRequest`, afin de déterminer :

- Combien chaque propriétaire devra verser
- À quelle **date**
- Pour quelle **période** (les appels peuvent être répartis sur plusieurs périodes.)

##### Logique


* Pour les **appel de provisions** et **appels exceptionnel**, pour les appels qui concernent une période, il y a une répartition au prorata selon la durée, au cours de chaque période concernée, pour laquel ils étaient officiellement propriétaire du lot. 

* Pour un appel exceptionnel ponctuel,  seuls les propriétaires concernés à la date de l’appel sont impliqués.
* Les **appel de fonds de roulement** et **appel de fonds de réserve** sont toujours envoyés en totalité aux copropriétaires connus à la date de création de l'appel (sans prorata).
* Lors de la génération des exécutions,  pour chaque exécution, si une exécution facturée existe déjà pour la date cible, le montant correspondant est déduit des lignes d'exécution suivantes.
* Une Exécution est considérée comme une **facture de vente** et génère des écritures dans le journal de vente de la copropriété convernées

  

##### Structure


- `FundRequestExecution`
   Lien entre un appel (`FundRequest`) et une période fiscale (`fiscal_period_id`).
- `FundRequestExecutionLine`
   Contient la ventilation d’un appel exécuté pour un propriétaire donné :
   `[ownership_id, fiscal_period_id, called_amount]`.
- Pour une `FundRequestExecution`, il y a:
  
   * une Accounting Entry
   * une série de Fundings (contrairement à une facture de vente classique, qui ne peut en avoir qu'un seul)



##### Contraintes d'intégrité

- Il n'est pas possible de générer les exécutions si le montant requis ne correspond pas au montant alloué
- Une exécution **planifiée non encore appelée** peut être supprimée.
- Une exécution **déjà comptabilisée** (écriture générée) ne peut plus être modifiée ni supprimée.
  - Elle peut toutefois être **annulée** par une **écriture d’extourne**.
- A tout moment, le total des exécutions (non annulées) doit correspondre au total de l'appel de fonds `FundRequest` (qui peut évoluer en cas de modification des lignes d'appel)



##### Actions disponibles (`FundRequestExecution`)

- Générer les **exécutions planifiées** à partir des prévisionnels
- Supprimer les **exécutions planifiées non appelées**
- **Extourner** les exécutions appelées (création d’une écriture d’annulation)



### Situations Particulières

#### A. Appels ajustés **sans modifier les appels comptabilisés**

> Le budget est ajusté, mais les appels déjà enregistrés sont conservés.

**Exemple :**

- Budget initial : 32.000 €
- 2 appels déjà faits de 8.000 €
- Nouveau budget : 40.000 €
- → 2 derniers appels prévus de 10.000 €

**Traitement :**

- Modifier la `FundRequest` à 36.000 €
   (les 2 appels restants complètent jusqu’à ce nouveau montant)


#### B. Appels ajustés **en répartissant uniquement la différence**

> Le syndic souhaite appeler la **différence totale** du nouveau budget sur les périodes restantes.

**Exemple :**

- Budget initial : 32.000 €
- Nouveau budget : 40.000 €
- 2 appels déjà faits de 8.000 €
- → Appels restants de 12.000 € x 2

**Traitement :**

- Modifier la `FundRequest` à 40.000 €


#### C. **Révision rétroactive** des appels

> Les appels antérieurs sont annulés, de nouveaux appels sont comptabilisés pour ces périodes, avec mise à jour du solde dû par propriétaire.

**Traitement :**

1. Marquer les exécutions antérieures comme **annulées** (extourner les écritures)
2. Modifier la `FundRequest` à 40.000 €
3. Replanifier toutes les exécutions (y compris rétroactives)
4. Générer un **état récapitulatif** par copropriétaire : montant déjà versé, ajustements, solde à payer / à rembourser

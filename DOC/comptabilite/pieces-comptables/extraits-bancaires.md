# Lignes d'extrait bancaire (`BankStatementLine`)

## Objectif

Les lignes d'extrait bancaire représentent les mouvements importés ou encodés depuis un extrait de compte bancaire. Dans FMT / gestion IMMO, elles servent de point d'entrée pour intégrer les paiements, remboursements, transferts et autres mouvements bancaires dans la comptabilité.

Chaque ligne d'extrait bancaire doit pouvoir générer une écriture comptable, indépendamment du fait qu'elle puisse être lettrée immédiatement ou rapprochée avec un financement (`Funding`). Le système doit donc distinguer trois notions :

1. **l'intégration comptable** de la ligne ;
2. **le rapprochement** avec un ou plusieurs financements ;
3. **le lettrage / matching** avec des lignes d'écriture comptable existantes.

Une ligne d'extrait peut être comptabilisée même si aucun lettrage n'est possible au moment de l'encodage.



## Principes généraux

### Compte comptable obligatoire

Chaque ligne d'extrait bancaire doit être associée à un compte comptable via le champ :

```text
accounting_account_id
````

Ce compte comptable détermine la nature du mouvement et permet d'identifier la stratégie de rapprochement applicable.

Exemples :

* compte fournisseur ;
* compte copropriétaire ;
* compte de transfert interne ;
* compte de frais bancaires ;
* compte d'intérêts ;
* compte de remboursement d'assurance.



### L'écriture comptable ne dépend pas du lettrage

La création de l'écriture comptable d'une ligne d'extrait bancaire ne doit pas être conditionnée par l'existence d'un lettrage complet.

Le lettrage est une opération complémentaire. Il peut réussir, échouer ou rester partiel sans bloquer l'intégration comptable de la ligne.

La logique attendue est la suivante :

> on tente de lettrer la ligne ; si le lettrage n'est pas possible, on intègre la ligne sans lettrage et le lettrage pourra être effectué ultérieurement.

Cette règle est importante pour éviter de bloquer la comptabilité lorsqu'un document justificatif, une note de crédit ou une écriture candidate n'est pas encore disponible.



### L'écriture comptable ne dépend pas du rapprochement avec un financement

Une ligne d'extrait bancaire ne doit pas non plus dépendre d'un rapprochement préalable avec un `Funding`.

Le rapprochement avec un financement permet d'expliquer ou de ventiler le mouvement bancaire, mais il ne conditionne pas la création de l'écriture comptable.

Une même ligne d'extrait peut être rapprochée avec plusieurs financements. C'est notamment le cas lorsqu'un seul paiement bancaire couvre plusieurs factures, appels de fonds ou remboursements.



## Types de lignes et stratégies de rapprochement

La stratégie de rapprochement dépend du compte comptable sélectionné sur la ligne d'extrait bancaire.

Le compte permet d'identifier si la ligne concerne :

* un fournisseur ;
* un copropriétaire ;
* un transfert entre comptes ;
* un autre type de mouvement non lettrable.



## Fournisseurs

### Compte concerné

Les mouvements fournisseurs sont généralement lettrés via le compte :

```text
440 - Fournisseurs
```

### Cas principal : paiement vers un fournisseur

Lorsqu'une ligne d'extrait correspond à un paiement vers un fournisseur, le système tente de retrouver un ou plusieurs financements candidats.

La recherche peut se baser notamment sur :

1. la communication structurée ;
2. le montant ;
3. le fournisseur ;
4. les financements ouverts ou non apurés.

L'objectif est de cibler le ou les financements les plus cohérents par rapport à la ligne bancaire.

Une ligne d'extrait peut correspondre à plusieurs transactions ou plusieurs factures. Par exemple, un seul virement au fournisseur peut couvrir plusieurs factures ouvertes.

Si aucun financement ou document en attente n'est retrouvé, la ligne est intégrée comptablement sans lettrage.

### Cas particulier : remboursement d'un fournisseur

Un remboursement fournisseur correspond généralement à une note de crédit.

Si la note de crédit existe déjà, le système peut tenter de rapprocher et lettrer le mouvement avec les écritures correspondantes.

Si la note de crédit n'a pas encore été reçue ou encodée, la ligne doit tout de même être intégrée. Le lettrage sera effectué ultérieurement, lorsque la note de crédit sera disponible.



## Copropriétaires

### Compte concerné

Les mouvements de copropriétaires sont généralement lettrés via le compte :

```text
410 - Copropriétaires
```

### Règle de rapprochement

Lorsqu'une ligne d'extrait correspond à un paiement d'un copropriétaire, le système tente de rapprocher le montant avec les financements non apurés du copropriétaire.

La règle métier attendue est de privilégier les financements les plus anciens.

Cela permet de respecter une logique chronologique d'apurement du compte copropriétaire : les dettes les plus anciennes sont soldées avant les plus récentes.

### Paiement partiel

Si le montant de la ligne ne permet pas d'apurer entièrement tous les financements candidats, le système peut créer un paiement partiel.

Dans ce cas, le dernier financement rapproché peut rester partiellement ouvert.



## Transferts entre comptes

### Compte concerné

Les transferts internes entre comptes bancaires ou financiers sont généralement lettrés via le compte :

```text
58 - Transferts internes
```

### Règle de rapprochement

Le rapprochement se fait principalement sur base :

* du compte comptable ;
* du montant ;
* du mouvement correspondant en sens inverse.

L'objectif est d'identifier l'écriture ou la ligne bancaire complémentaire permettant d'équilibrer le transfert.



## Autres mouvements

Certains mouvements bancaires ne sont pas destinés à être lettrés avec des financements.

Exemples :

* frais bancaires ;
* intérêts bancaires ;
* remboursement d'assurance ;
* autres produits ou charges directement comptabilisés.

Ces mouvements sont considérés comme non lettrables dans le contexte des financements.

Ils doivent néanmoins générer une écriture comptable complète et valide.



# Validation d'une ligne d'extrait bancaire

La validation d'une ligne d'extrait bancaire déclenche plusieurs traitements successifs.

L'objectif est de tenter le rapprochement et le lettrage lorsque c'est possible, puis de générer et valider l'écriture comptable.



## Étape 1 — Tentative de réconciliation

Lors de la validation, le système exécute une tentative de réconciliation via une logique de type :

```text
attempt_reconcile
```

Cette étape vise à rapprocher la ligne d'extrait bancaire avec un ou plusieurs `Funding`.

La recherche privilégie les financements les plus anciens lorsque la logique métier le permet, notamment pour les copropriétaires.

Si des financements candidats sont retrouvés, le système peut créer un ou plusieurs paiements associés.

Ces paiements peuvent être complets ou partiels selon le montant disponible sur la ligne d'extrait.



## Étape 2 — Génération de l'écriture comptable

Avant la comptabilisation effective, le système génère les écritures comptables via une logique de type :

```text
doGenerateAccountingEntry
```

Cette génération intervient dans le cycle de validation, notamment dans un traitement de type :

```text
onbeforePost
```

Le rôle de cette étape est de produire les écritures comptables correspondant à la ligne d'extrait bancaire.

La génération doit fonctionner même si aucun lettrage complet n'a pu être établi.



## Étape 3 — Gestion du matching

Après génération ou identification des écritures candidates, le système tente de gérer le `Matching`.

Le principe est le suivant :

* si un `Matching` complet existe déjà, il est conservé ;
* sinon, le `Matching` existant est supprimé s'il n'est pas cohérent ;
* un nouveau `Matching` est tenté à partir des écritures candidates.

Le système regarde si la ligne d'extrait dispose d'un ou plusieurs `Payment`.

Deux situations sont alors possibles :

1. des paiements existent : le matching automatique est tenté sur base de ces paiements ;
2. aucun paiement n'existe : le matching automatique est tenté sur base des écritures comptables candidates.



## Étape 4 — Validation de l'écriture comptable

Après la génération et la tentative de matching, l'écriture comptable est validée dans un traitement de type :

```text
onafterPost
```

Cette étape finalise l'intégration comptable de la ligne d'extrait bancaire.



# Lettrage et matching

## Principe général

Le lettrage n'est pas systématique.

Il dépend :

* du type de compte sélectionné sur la ligne d'extrait ;
* des financements candidats retrouvés ;
* des écritures comptables existantes ;
* de la situation du compte concerné au moment de l'encodage ;
* de la possibilité d'obtenir un matching équilibré.

Le système doit toujours tenter de lettrer lorsque les informations disponibles le permettent, mais l'absence de lettrage ne bloque pas l'intégration comptable.



## Objectif du matching

L'objectif du matching est d'associer des lignes d'écriture comptable de manière équilibrée.

Un matching est considéré comme équilibré lorsque la somme des débits correspond à la somme des crédits.

Autrement dit :

```text
sum(debit) = sum(credit)
```

Le matching peut se baser sur :

* un ou plusieurs comptes comptables ;
* un montant au débit ;
* un montant au crédit ;
* des écritures existantes ;
* des paiements créés depuis la ligne d'extrait ;
* des financements candidats.



## Identification des financements candidats

La première étape consiste à identifier les `Funding` candidats.

Cette identification utilise une stratégie différente selon la nature du compte comptable sélectionné :

* fournisseur ;
* copropriétaire ;
* transfert interne ;
* autre mouvement.

À ce stade, il n'y a pas encore de garantie que le matching sera équilibré.

L'objectif est de rapprocher autant que possible le montant de la ligne d'extrait avec les financements pertinents.



## Création des paiements

Pour chaque financement candidat, le système peut créer un `Payment` en statut pending.

Le montant de la ligne d'extrait est ventilé entre les différents financements candidats.

Lorsqu'une ligne couvre plusieurs financements, plusieurs paiements peuvent être créés.

Le dernier paiement peut être incomplet si le solde disponible sur la ligne d'extrait ne permet pas d'apurer complètement le financement correspondant.

Dans ce cas, le financement ne doit pas être marqué comme payé entièrement.



## Recherche des lignes d'écriture à lettrer

Pour chaque `Payment` créé, le système tente d'identifier les lignes d'écriture non lettrées correspondant au financement.

La recherche peut se baser sur :

* le montant total attendu du financement ;
* le montant du paiement ;
* le document ou financement associé ;
* les lignes comptables déjà existantes ;
* les matchings incomplets compatibles.

En principe, une écriture comptable du montant du financement doit exister en sens inverse.



## Ordre de priorité pour le matching

Lorsqu'un paiement est créé, le système tente de construire le matching selon l'ordre suivant :

1. rechercher un matching incomplet existant compatible ;
2. rechercher des lignes comptables non lettrées liées au financement ou au document ;
3. si aucune ligne candidate n'est trouvée, ne pas créer de matching.

Cette approche permet de compléter progressivement des matchings existants au lieu d'en recréer inutilement.



## Matching incomplet

Un matching peut rester incomplet lorsque le montant disponible ne permet pas d'équilibrer totalement les écritures.

C'est notamment le cas lorsqu'un paiement partiel est reçu ou lorsqu'un dernier paiement ne couvre qu'une partie d'un financement.

Dans ce cas, le matching incomplet peut être conservé.

Il pourra être complété ultérieurement lorsqu'un autre mouvement bancaire, paiement ou document comptable permettra d'équilibrer les écritures.



## Notes importantes

* Les lignes d'écriture déjà associées à un matching incomplet doivent être prises en compte.
* Si aucune ligne d'écriture candidate n'est trouvée, aucun matching ne doit être créé.
* Une seule ligne d'extrait bancaire peut entraîner la création de plusieurs matchings.
* Un matching incomplet peut être conservé lorsqu'il représente correctement une situation partielle.
* L'intégration comptable de la ligne d'extrait ne doit jamais dépendre de la réussite du matching.



# Synthèse fonctionnelle

Une ligne d'extrait bancaire suit la logique suivante :

1. elle doit toujours être associée à un compte comptable ;
2. elle peut être rapprochée avec un ou plusieurs financements ;
3. elle peut générer un ou plusieurs paiements ;
4. elle génère toujours une écriture comptable lors de sa validation ;
5. elle tente un lettrage lorsque les conditions sont réunies ;
6. elle peut être intégrée sans lettrage ;
7. elle peut produire un matching complet, incomplet ou aucun matching.

Le comportement attendu est donc robuste et progressif : le système comptabilise toujours ce qui peut l'être, tente d'automatiser le rapprochement et le lettrage, mais n'empêche pas l'encodage lorsque certaines informations ne sont pas encore disponibles.



# Points d'attention pour les développeurs

## Découplage entre comptabilisation et lettrage

La génération de l'écriture comptable ne doit pas dépendre :

* de l'existence d'un `Funding` ;
* de l'existence d'un `Payment` ;
* de l'existence d'un `Matching` complet ;
* de la possibilité de lettrer la ligne.

Le lettrage est un traitement complémentaire, non une condition préalable à la comptabilisation.



## Gestion des paiements multiples

Une ligne d'extrait bancaire peut couvrir plusieurs financements.

Le traitement doit donc permettre :

* la création de plusieurs `Payment` ;
* la ventilation du montant de la ligne ;
* la gestion d'un dernier paiement partiel ;
* le maintien d'un financement ouvert lorsque le paiement est incomplet.



## Conservation des matchings incomplets

Les matchings incomplets peuvent représenter une situation métier valide.

Ils ne doivent pas être supprimés systématiquement.

Avant de supprimer ou recréer un matching, le système doit vérifier s'il existe un matching incomplet compatible pouvant être complété.



## Cas sans candidat

Lorsqu'aucun financement, paiement ou ligne comptable candidate n'est trouvé, la ligne doit tout de même être intégrée comptablement.

Dans ce cas :

* aucun paiement n'est créé ;
* aucun matching n'est créé ;
* l'écriture comptable est générée et validée normalement.



# Exemples de cas métier

## Paiement fournisseur couvrant plusieurs factures

Un virement de 3 000 EUR est effectué à un fournisseur.

Trois factures ouvertes existent :

* facture A : 1 000 EUR ;
* facture B : 1 200 EUR ;
* facture C : 800 EUR.

La ligne d'extrait peut être rapprochée avec les trois financements.

Le système crée trois paiements et tente de lettrer les écritures fournisseurs correspondantes.



## Paiement partiel d'un copropriétaire

Un copropriétaire verse 500 EUR alors que deux appels de fonds restent ouverts :

* appel de fonds A : 300 EUR ;
* appel de fonds B : 400 EUR.

Le système privilégie l'appel de fonds le plus ancien.

Il crée :

* un paiement complet de 300 EUR sur l'appel A ;
* un paiement partiel de 200 EUR sur l'appel B.

L'appel B reste partiellement ouvert.

Le matching correspondant peut rester incomplet.



## Remboursement fournisseur sans note de crédit

Un fournisseur rembourse 250 EUR, mais la note de crédit n'a pas encore été reçue.

La ligne d'extrait est intégrée comptablement.

Aucun lettrage complet n'est possible à ce stade.

Le lettrage sera effectué ultérieurement lorsque la note de crédit sera encodée.



## Frais bancaires

Une ligne d'extrait correspond à des frais bancaires de 12 EUR.

Le compte comptable sélectionné correspond à des frais bancaires.

La ligne n'est pas rapprochée avec un financement et n'est pas lettrée.

Elle génère directement l'écriture comptable correspondante.


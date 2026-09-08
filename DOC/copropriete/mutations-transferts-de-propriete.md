# Mutations et régularisation comptable — référence DEV/PO

Cette page décrit le comportement effectivement implémenté pour les mutations (`OwnershipTransfer`) et leur régularisation comptable (`OwnershipTransferSettlement`). Elle s'adresse aux développeurs, Product Owners et analystes fonctionnels.

> Le code est la source de vérité. Les sections « Limites et écarts connus » signalent explicitement les différences entre l'intention métier initiale et le comportement courant.

Pour un parcours orienté écran, consulter le [guide utilisateur des mutations](../prise-en-main-et-fonctionnement/coproprietes/mutations.md).

## Périmètre fonctionnel

Une mutation couvre actuellement :

- une copropriété ;
- un dossier de propriété vendeur (`old_ownership_id`) ;
- un dossier de propriété acquéreur (`new_ownership_id`) ;
- un ou plusieurs lots ;
- une date de transfert, qui correspond à la date de l'acte ;
- les échanges documentaires avec le notaire ;
- une régularisation comptable active liée depuis la mutation.

Le traitement comptable analyse les fonds de roulement, les appels de fonds comptabilisés et les décomptes périodiques comptabilisés. Les pièces sources déjà comptabilisées ne sont pas modifiées : le delta est matérialisé par des opérations diverses (`MiscOperation`).

Les classes principales sont :

| Rôle | Classe |
| --- | --- |
| Dossier juridique et documentaire | `realestate\property\OwnershipTransfer` |
| Dossier de régularisation comptable | `realestate\property\transfer\OwnershipTransferSettlement` |
| Mouvement calculé par source et par lot | `OwnershipTransferSettlementLine` |
| Lien idempotent entre une source et une OD | `OwnershipTransferSettlementOperation` |
| Courrier vendeur ou acquéreur | `OwnershipTransferSettlementCorrespondence` |

## Deux workflows coordonnés

### Workflow de la mutation

| Statut technique | Libellé UI | Actions et destination |
| --- | --- | --- |
| `pending` | Brouillon | `open` → `open` |
| `open` | Ouvert | `send` → `seller_documents_sent` ; `confirm` → `confirmed` |
| `seller_documents_sent` | Documents vendeur envoyés | `confirm` → `confirmed` ; `to_complete` → `open` |
| `confirmed` | Confirmé | `revert_to_open` → `open` ; `settle` → `settled` |
| `financial_statement_sent` | État financier envoyé | `revert_to_open` → `open` ; `settle` → `settled` ; `to_complete` → `confirmed` |
| `settled` | Vente confirmée | `revert_to_confirmed` → `confirmed` ; `prepare_accounting` → `accounting_pending` |
| `accounting_pending` | Régularisation comptable en cours | `unlock` → `settled` ; `close` → `closed` |
| `closed` | Clôturé | `unlock` → `settled` |

L'action d'envoi du courrier notarial génère un PDF, l'enregistre comme `Document`, ajoute les pièces sélectionnées, met l'e-mail en file via la boîte du processus `legal`, puis ajoute une ligne à l'historique. Elle ne fait avancer automatiquement le workflow que depuis `open`, vers `seller_documents_sent`.

Le statut `financial_statement_sent` existe dans le modèle et les vues, mais aucune transition du workflow courant ni l'action d'envoi ne positionne ce statut. Il peut donc surtout être rencontré dans des données historiques ou positionnées par un autre mécanisme.

### Workflow de la régularisation

| Statut | Signification | Transitions |
| --- | --- | --- |
| `pending` | Calcul et OD en préparation | `validate` → `validated` ; `cancel` → `cancelled` |
| `validated` | OD comptabilisées | `close` → `closed` ; `cancel` → `cancelled` |
| `closed` | Régularisation clôturée | `cancel` → `cancelled` |
| `cancelled` | Régularisation annulée et conservée pour traçabilité | aucune |

Dans le flux standard, la transition `validate` exécute successivement le transfert des lots, la comptabilisation des OD, la génération et la planification des correspondances, puis la transition `close`. La régularisation et la mutation arrivent donc normalement à `closed` au cours de la même chaîne de validation.

## Volet notarial

La fiche mutation structure les informations communiquées au titre des paragraphes 1, 2 et 3 :

- §1 : soldes des fonds, arriérés du vendeur, appels votés, procédures, PV d'assemblée, décomptes et dernier bilan ;
- §2 : dépenses décidées, appels de fonds, acquisitions de parties communes, dettes de copropriété, situation actualisée du vendeur et emprunts ;
- §3 : date de l'acte, lots cédés et acquéreur.

À l'ouverture du dossier, les textes descriptifs sont initialisés depuis les modèles documentaires `ownership_transfer_paragraph_1` et `ownership_transfer_paragraph_2`. Les soldes de fonds, appels planifiés et arriérés sont ensuite rafraîchis. La confirmation actualise à nouveau les arriérés.

### Informations du §1

Le premier volet correspond à la situation communiquée avant le compromis. La fiche permet de préparer :

- les soldes des fonds de la copropriété et leur ventilation sur les lots concernés ;
- les arriérés du vendeur et les frais de dossier de mutation ;
- les appels destinés aux fonds et déjà décidés ;
- les procédures judiciaires en cours ;
- les procès-verbaux d'assemblée et décomptes périodiques joints ;
- le dernier bilan approuvé ;
- les informations complémentaires, notamment la citerne et le dossier d'intervention ultérieure.

Les arriérés sont calculés à partir des `Funding` du vendeur. Ils portent sur son dossier de propriété dans la copropriété et ne sont pas limités aux seuls lots vendus. Les soldes de fonds et les appels affichés dans ce volet sont des informations destinées au courrier notarial ; ils sont distincts des lignes du settlement comptable créées après l'acte.

### Informations du §2

Le second volet prépare les informations complémentaires demandées après le compromis :

- dépenses de conservation, entretien, réparation et réfection déjà décidées ;
- appels de fonds approuvés ou planifiés ;
- acquisition de parties communes ;
- dettes certaines de la copropriété liées à des litiges ;
- situation actualisée des arriérés du vendeur ;
- emprunts bancaires et tableaux d'amortissement éventuels.

Le champ `with_both_paragraphs` permet au rendu notarial d'inclure les deux ensembles d'informations dans une même correspondance.

Les délais métier généralement associés au dossier sont de 15 jours après `request_date` pour le §1 et de 30 jours après `confirmation_date` pour le §2. Les champs de date permettent cette traçabilité, mais le workflow inspecté n'impose pas ces délais et ne planifie pas de rappel automatique lorsque le §3 tarde à arriver.

### Informations du §3 et dates

`transfer_date` est la date économique et juridique déterminante pour le transfert des lots et les calculs comptables. La date de jouissance ou une date de réception d'un immeuble neuf ne joue aucun rôle dans l'algorithme décrit ici.

Les champs de suivi disponibles sont `request_date`, `confirmation_date`, `seller_documents_sent_date`, `financial_statement_sent_date` et `transfer_date`. Le modèle ne calcule pas de délai ni de rappel automatique à partir de ces dates. En particulier, les délais métier de 15 jours pour le §1, de 30 jours pour le §2 ou le rappel après l'acte ne sont pas contrôlés par ce workflow.

L'exercice et la période comptables sont calculés d'après la première date disponible selon l'étape du dossier, la date de transfert devenant la référence prioritaire lorsqu'elle est renseignée.

## Création de la régularisation

La transition `prepare_accounting`, accessible depuis `settled`, exige :

- une copropriété ;
- un vendeur et un acquéreur distincts ;
- au moins un lot ;
- une date de transfert.

Son hook `onbeforePrepareAccounting()` crée, s'il n'existe pas déjà, un `OwnershipTransferSettlement` avec :

- la mutation, la copropriété, le vendeur et l'acquéreur ;
- la date de transfert ;
- `snapshot_at = now()` ;
- `accounting_date = now()` par défaut ;
- le statut `pending`.

Le lien `ownership_transfer_settlement_id` est écrit sur la mutation, puis celle-ci passe à `accounting_pending`.

Cette étape ne génère ni ligne ni OD. Ces opérations sont déclenchées depuis la fiche de régularisation avec `Générer les lignes`, puis `Générer les OD`.

## Convention de signe

Une ligne exprime un transfert économique entre vendeur et acquéreur :

```text
applied_amount > 0
    vendeur crédité
    acquéreur débité

applied_amount < 0
    vendeur débité
    acquéreur crédité
```

Le calcul de base est :

```text
calculated_amount = actual_seller_amount - theoretical_seller_amount
```

Exemple : 100 EUR ont été comptabilisés au vendeur, mais 40 EUR seulement lui reviennent après prise en compte de la mutation. Le montant calculé est `100 - 40 = 60 EUR` : le vendeur est crédité de 60 EUR et l'acquéreur est débité de 60 EUR.

`calculated_amount` conserve le résultat avant arrondi final. `applied_amount`, modifiable tant que la régularisation est `pending`, est le montant à comptabiliser.

## Génération des lignes

### Préconditions

La génération n'est autorisée que si :

- la régularisation est `pending` ;
- toute OD déjà liée est encore `proforma` ;
- le dossier et son snapshot sont complets ;
- `snapshot_at` n'est pas antérieur à la date de transfert ;
- la copropriété possède au moins un fonds de roulement ;
- chaque fonds de roulement possède un compte, une clé de répartition et un total de quotes-parts strictement positif ;
- chaque lot vendu possède une quote-part pour la clé de chaque fonds de roulement.

### Fonds de roulement

Pour chaque `CondoFund` de type `working_fund` :

1. la position est prise à `transfer_date - 1 jour`, car le jour de la mutation appartient à l'acquéreur ;
2. le dernier `AccountBalanceChange` disponible à cette date fournit le solde `credit_balance - debit_balance` ;
3. ce solde est réparti entre les lots vendus selon `property_lot_shares / total_shares` ;
4. une ligne `working_fund_transfer` est calculée par lot.

Le vendeur est considéré comme détenteur réel du montant avant transfert et l'acquéreur comme détenteur théorique après transfert.

Seuls les soldes des fonds de type `working_fund` sont transférés directement. Les soldes des fonds de réserve ne font pas l'objet de cette étape. En revanche, une exécution comptabilisée d'appel de fonds peut être analysée quel que soit le type de l'appel, son affectation comptable étant déterminée par `FundRequestExecution::getDebitOperationAssignment()`.

### Appels de fonds comptabilisés

Toutes les `FundRequestExecution` de la copropriété qui sont `posted` et dont `posting_date <= snapshot_at` sont examinées. Le traitement retient seulement les entrées relatives aux lots vendus et au vendeur ou à l'acquéreur.

La période économique utilise `date_from` et `date_to`. À défaut, `posting_date` sert de date ponctuelle. Les bornes sont inclusives :

- période entièrement avant la mutation : 100 % vendeur ;
- période commençant le jour de la mutation ou après : 100 % acquéreur ;
- période à cheval : prorata journalier, le jour de mutation étant le premier jour acquéreur.

Le type de correction est `post_transfer_call` si `period_from >= transfer_date`, sinon `current_period_provision`. Une source entièrement antérieure peut donc être classée comme provision de période courante, mais son delta est normalement nul et sa ligne n'est pas persistée.

### Décomptes périodiques comptabilisés

Toutes les `ExpenseStatement` `posted` avant le snapshot sont examinées. Sont exclus les décomptes qui cumulent les deux conditions suivantes :

- l'exercice est `closed` ;
- la période du décompte est la dernière période de l'exercice.

Un décompte sans ligne vendeur/acquéreur pour les lots transférés est ignoré. Pour les autres, le code :

1. agrège les montants réellement comptabilisés par lot et par partie ;
2. appelle `ExpenseStatement::simulateOwnershipTransferData()` pour simuler la ventilation après mutation ;
3. compare réel et théorique ;
4. crée des lignes `expense_statement_adjustment` sans modifier le décompte source.

### Arrondis et persistance

Les lignes sont groupées par source comptable. Dans chaque groupe :

1. les lots sont triés par identifiant croissant ;
2. le total brut est arrondi à deux décimales ;
3. chaque ligne sauf la dernière est arrondie à deux décimales ;
4. la dernière absorbe le reliquat de centimes ;
5. les lignes dont `abs(applied_amount) < 0,005` ne sont pas enregistrées.

Les montants réel, théorique et calculé sont enregistrés avec quatre décimales ; le montant retenu l'est avec deux.

`has_accounted_sources` est déterminé par la présence de groupes analysés, pas par la présence finale d'un delta non nul. Comme chaque fonds de roulement configuré produit un groupe avant filtrage des montants nuls, cet indicateur peut être vrai alors qu'aucune ligne n'est finalement enregistrée. `alert_summary` est un simple résumé textuel du nombre de lignes et de sources.

### Effet d'une régénération

La régénération n'effectue pas de fusion avec les lignes existantes. Elle supprime d'abord les lignes des OD pro forma, supprime toutes les lignes de régularisation, puis recrée le résultat complet.

En conséquence, toute modification manuelle de `applied_amount` est perdue si l'utilisateur relance `Générer les lignes`. Il n'existe actuellement ni justification d'override, ni exclusion, ni marquage d'obsolescence, ni empreinte de calcul.

## Génération des opérations diverses

Seules les lignes dont le montant retenu est non nul sont groupées. Il existe au plus une OD par source grâce à la clé :

```text
settlement:{settlement_id}:{source_type}:{source_id}
```

| Source | Regroupement | Date de comptabilisation |
| --- | --- | --- |
| Fonds de roulement | régularisation + fonds | date de transfert |
| Appel couvrant la mutation | régularisation + exécution | date de transfert |
| Appel postérieur | régularisation + exécution | `posting_date` de l'exécution |
| Décompte | régularisation + décompte | `accounting_date` de la régularisation |

Avant création, chaque date doit être aujourd'hui ou antérieure, couverte par une période `open` ou `preclosed`, dans un exercice `preopen`, `open` ou `preclosed`. La copropriété doit également disposer :

- d'un journal `MISC` ;
- d'un compte bancaire principal ;
- des comptes copropriétaire correspondant à l'affectation du fonds ou de l'appel.

Pour chaque lot, une ligne crédite/débite le compte vendeur et une ligne miroir débite/crédite le compte acquéreur. Pour le fonds de roulement, deux lignes supplémentaires sur le compte du fonds déplacent l'affectation analytique vendeur/acquéreur sans modifier le solde global du fonds.

L'OD est créée puis publiée en `proforma`. Une nouvelle génération remplace une OD encore pro forma. Une OD déjà `posted` est réutilisée uniquement si sa clé, sa date et son montant correspondent ; une divergence bloque le traitement.

## Validation, écritures et Funding

La validation exige que toutes les OD liées soient encore `proforma`. Le code n'impose toutefois pas qu'il existe au moins une ligne ou une OD : une régularisation sans correction peut être validée.

Après la transition vers `validated`, le hook :

1. transfère les lots au nouvel acquéreur ;
2. poste chaque OD ;
3. renseigne `validated_at` ;
4. génère les correspondances ;
5. planifie leur émission ;
6. clôture la régularisation.

Le post d'une `MiscOperation` utilise le workflow générique : génération et validation de l'écriture comptable, création des `Funding`, attribution du numéro d'opération. Pour chaque ligne de compte propriétaire :

```text
due_amount = debit - credit
```

Un débit acquéreur produit donc un `Funding` positif ; un crédit vendeur, un `Funding` négatif. Ces objets ont `funding_type = misc_operation` et sont liés à l'OD, pas directement au settlement.

Le comportement générique tente ensuite de compenser automatiquement le nouveau Funding avec les Funding ouverts de signe opposé sur le même compte de contrôle. Il peut créer des `FundingAllocation` et tenter un lettrage des lignes comptables. Aucun `Payment` ni mouvement bancaire n'est créé directement par la régularisation.

## Transfert des lots et historique de propriété

Le transfert physique n'a pas lieu lors de `prepare_accounting`, mais après validation de la régularisation :

- l'acquéreur est validé si nécessaire, avec `date_from = transfer_date` ;
- `PropertyLot.active_ownership_id` est remplacé par l'acquéreur ;
- le lien historique ouvert du vendeur reçoit `date_to = transfer_date - 1 jour` ;
- un lien historique ouvert acquéreur est créé s'il n'en existe pas déjà ;
- si le vendeur ne conserve aucun lot actif dans la copropriété, son `Ownership.date_to` prend la veille de la mutation ;
- les exécutions des appels actifs dont la période couvre la date de mutation sont régénérées via `FundRequest::generate_executions()`.

Cette dernière opération porte sur les appels actifs couvrant la date de mutation. Le code ne parcourt pas explicitement « tous les proformas de l'exercice » ; il délègue la régénération au mécanisme standard du `FundRequest`.

## Correspondances et clôture

Après validation, les correspondances existantes sont supprimées puis régénérées selon les préférences `technical_communication` du vendeur et de l'acquéreur. Une correspondance est créée pour chaque canal activé : `email`, `postal`, `postal_registered` ou `postal_registered_receipt`. En l'absence de préférence active, le recommandé simple est utilisé. Le destinataire est le représentant du dossier de propriété ; sans représentant, aucune correspondance n'est créée pour ce rôle.

La planification :

- programme l'action d'envoi différé des e-mails environ une minute plus tard ;
- crée une `ExportingTask` avec une ligne par méthode postale ;
- renseigne `correspondences_dispatch_started_at`.

L'e-mail est marqué émis lorsque le message avec PDF est accepté dans la file (`is_sent = true`, `sent_date = now()`). L'export postal fusionne les PDF et crée un document d'export.

Dans le code courant, la policy `can_close` ne contient aucun contrôle actif : le bloc qui attendait les e-mails et le téléchargement postal est commenté. La validation enchaîne donc immédiatement la clôture du settlement et de la mutation après planification, sans attendre l'envoi réel ni le téléchargement de l'export. L'export postal ne met pas non plus `is_sent` à jour.

## Annulation et déverrouillage

L'action `Déverrouiller` de la mutation appelle `cancel` sur la régularisation, efface le lien actif, puis remet la mutation à `settled`.

La policy d'annulation vérifie notamment :

- que les OD comptabilisées et leurs écritures peuvent être annulées dans des périodes et exercices encore admissibles ;
- qu'aucune écriture n'est déjà extournée ;
- que les lots sont encore affectés soit au vendeur, soit à l'acquéreur ;
- si le lot est chez l'acquéreur, que les deux segments attendus de l'historique existent.

Le rollback :

- annule les OD `posted` ou supprime les brouillons/proformas ;
- remet les lots encore détenus par l'acquéreur chez le vendeur ;
- rouvre les segments historiques du vendeur et supprime les segments acquéreur créés à la date de mutation ;
- rouvre le dossier vendeur si sa date de fin correspond à la veille de la mutation ;
- régénère les exécutions des appels actifs couvrant la date de mutation.

L'annulation générique d'une OD supprime ses Funding seulement s'ils n'ont pas été envoyés. Les Funding déjà envoyés ne sont pas supprimés par ce mécanisme et doivent être pris en compte lors de l'analyse d'une annulation.

## Modèle de données utile

### `OwnershipTransferSettlement`

Conserve le contexte figé (`snapshot_at`), la date comptable, les montants nets, la présence de sources, le résumé du calcul, les lignes, les OD, les correspondances, les informations de planification, les timestamps de validation/clôture et les logs techniques.

`seller_net_amount` et `buyer_net_amount` reçoivent actuellement le même total signé. Leur sens est donné par la convention de signe, et non par deux soldes opposés.

### `OwnershipTransferSettlementLine`

Conserve la source et le lot, la période et les jours par partie, les montants réels et théoriques, le montant calculé, le montant retenu et le lien vers le wrapper d'OD. Seul `applied_amount` est destiné à l'ajustement manuel. Une ligne ne peut plus être modifiée ou supprimée après validation du settlement.

### `OwnershipTransferSettlementOperation`

Relie une source, une clé idempotente et une `MiscOperation`. Les contraintes d'unicité portent sur `(settlement_id, operation_key)` et `(settlement_id, misc_operation_id)`.

### `OwnershipTransferSettlementCorrespondence`

Étend `DocumentCorrespondence` et ajoute le rôle du destinataire, l'indicateur de document généré, l'accusé de réception, les e-mails liés et le lien de téléchargement. L'unicité est définie par `(settlement_id, recipient_role, communication_method)`.

## Idempotence et atomicité observables

- `prepare_accounting` ne recrée pas de settlement tant que la mutation en référence déjà un.
- La clé d'opération empêche la duplication d'une OD pour une même source.
- Les créations de Funding compensent les montants déjà générés pour la même OD et le même tiers avant de créer un delta complémentaire.
- La régénération des correspondances supprime et recrée les lignes tant que l'émission n'a pas commencé.
- Une fois `correspondences_dispatch_started_at` renseigné, une nouvelle planification est refusée.

Le code ne déclare pas de transaction explicite englobant toute la préparation ou toute la validation. Les hooks enchaînent plusieurs écritures et actions ; le niveau d'atomicité réel dépend donc du moteur ORM et du contrôleur de transition.

## Limites et écarts connus

Les points suivants ne doivent pas être supposés comme implémentés :

- il n'existe pas de champ unique en base imposant « un settlement historique par mutation » ; le lien de la mutation désigne seulement le settlement actif ; après annulation, une nouvelle régularisation peut être créée ;
- les domaines des champs guident la sélection de lots et de dossiers de propriété dans l'UI, mais `can_prepare_accounting` ne revalide pas explicitement que chaque lot est encore détenu par le vendeur ni que les deux dossiers appartiennent à la copropriété ;
- la préparation ne transfère pas les lots et ne génère pas automatiquement les lignes ;
- les ajustements manuels ne survivent pas à une régénération des lignes ;
- il n'existe ni inclusion/exclusion motivée, ni override justifié, ni ligne obsolète, ni hash/version de calcul ;
- les Funding utilisent le type générique `misc_operation` et peuvent être compensés automatiquement ;
- aucune liaison directe Funding → settlement/wrapper n'est définie ; la traçabilité passe par l'OD ;
- la clôture n'attend actuellement pas l'émission effective des correspondances ;
- le statut `financial_statement_sent` n'est pas atteint par le workflow courant ;
- la validation n'impose pas explicitement la présence de lignes ou d'OD ;
- `alert_summary` n'est pas une alerte applicative et aucune alerte dédiée au settlement n'est créée ;
- aucune prise en charge dédiée des mutations successives sur une même période n'est visible dans ce workflow.

Ces écarts sont importants pour les critères d'acceptation : une évolution future devra décider si elle préserve le comportement courant ou si elle rétablit l'intention métier initiale.

## Points d'entrée techniques

| Besoin | Point d'entrée |
| --- | --- |
| Préparer la régularisation | transition `prepare_accounting` sur `OwnershipTransfer` |
| Calculer/recalculer les lignes | action ORM `generate_lines` sur le settlement |
| Générer les OD pro forma | action ORM `generate_operations` |
| Valider et comptabiliser | transition `validate` |
| Transférer les lots | action interne `transfer_property_lots` |
| Régénérer les appels actifs | action interne `refresh_fund_request_executions` |
| Générer les correspondances | action interne `generate_correspondences` |
| Planifier les envois | action `dispatch_correspondences` |
| Annuler et restaurer | transition `cancel` / action `rollback` |

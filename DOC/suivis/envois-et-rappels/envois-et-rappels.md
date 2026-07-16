# Envois et rappels

## Objectif

Les envois et rappels couvrent les mécanismes permettant de communiquer avec les copropriétaires ou autres destinataires à partir des informations du logiciel.

On distingue plusieurs usages :

* l’envoi individuel d’un document ou d’une correspondance ;
* l’envoi groupé d’un même message à un ensemble de destinataires ;
* l’export postal ou l’impression groupée de documents ;
* les rappels automatisés liés à des échéances ou à des paiements.

Ces mécanismes partagent parfois les mêmes documents, mais ils ne répondent pas au même besoin opérationnel.

## Documents générés et conservation EDMS

À chaque début de période, certains documents peuvent être générés automatiquement, par exemple les appels de fonds.

Le processus fonctionnel typique est le suivant :

1. l’appel de fonds est comptabilisé ;
2. les écritures nécessaires sont générées ;
3. le document est produit ;
4. le document est conservé dans l’EDMS du propriétaire ou de la copropriété ;
5. le document peut ensuite être envoyé, exporté ou consulté.

La conservation dans l’EDMS répond à un besoin de traçabilité et de conservation longue durée. L’envoi n’est qu’un canal de diffusion du document : il ne remplace pas l’archivage documentaire.

## Envoi individuel de documents

Les documents comme les appels de fonds, les décomptes ou les convocations doivent pouvoir être envoyés individuellement.

Le canal dépend des préférences et paramètres du destinataire. Dans la fiche propriétaire, on peut distinguer notamment :

* le mode de réception des documents courants : email ou courrier postal ;
* le mode de réception des convocations d’assemblée générale : recommandé, email ou courrier postal selon les règles applicables et les consentements enregistrés.

Pour les documents individualisés, la logique de référence reste la `DocumentCorrespondence` : elle relie le document généré à un destinataire donné et sert de base à la distribution.

## Envoi groupé (`Broadcast`)

Un `Broadcast` représente un envoi groupé par email.

Il permet de préparer un message électronique commun destiné à plusieurs destinataires liés à une copropriété. Le mécanisme est conçu pour les communications de masse, par exemple une information générale aux copropriétaires ou l’envoi d’un message accompagné d’un ou plusieurs documents.

Un `Broadcast` porte notamment :

* la copropriété concernée (`condo_id`) ;
* le nom fonctionnel de l’envoi ;
* les destinataires sélectionnés ;
* l’objet et le contenu HTML du message ;
* les documents joints ;
* les emails générés lors du traitement.

Le `Broadcast` ne remplace pas les correspondances documentaires individualisées. Il sert à envoyer un même message à un groupe de destinataires, avec éventuellement des pièces jointes communes.

## Sélection des destinataires

La préparation d’un `Broadcast` commence par la sélection des destinataires.

Le système permet de sélectionner les destinataires par plusieurs niveaux :

* des `Ownership` concernés ;
* des `Owner` concernés ;
* les `Identity` qui recevront effectivement l’email.

Lorsqu’un `Ownership` est ajouté, les propriétaires liés peuvent être ajoutés automatiquement. Lorsqu’un `Owner` est ajouté, l’identité correspondante est ajoutée comme destinataire effectif.

Cette chaîne est importante :

```text
Ownership -> Owner -> Identity -> adresse email
```

Le message final est envoyé aux adresses email portées par les `Identity`. Avant de valider la sélection, au moins une identité doit être présente, et les identités utilisées doivent disposer d’une adresse email.

## Étapes de préparation

La création d’un `Broadcast` suit deux étapes internes lorsque l’envoi est encore en brouillon.

| Étape | Rôle |
| --- | --- |
| `recipients_selection` | Sélectionner la copropriété et les destinataires. |
| `content_edition` | Rédiger l’objet, le contenu HTML et ajouter les pièces jointes. |

L’action **Valider destinataires** fait passer l’envoi de la sélection des destinataires vers l’édition du contenu.

L’action **Valider contenu** vérifie le contenu et marque l’envoi comme prêt si les conditions sont remplies.

## Statuts du `Broadcast`

Le workflow d’un `Broadcast` utilise les statuts suivants.

| Statut | Signification |
| --- | --- |
| `draft` | L’envoi est en préparation. |
| `ready` | Les destinataires et le contenu sont validés. |
| `scheduled` | L’envoi est planifié pour traitement. |
| `processing` | Le système génère les emails. |
| `processed` | Les emails ont été générés et mis en file d’envoi. |

Le passage à `ready` est bloqué si l’envoi ne contient pas de destinataire, si une identité n’a pas d’adresse email, si l’objet est vide ou si le corps du message est vide.

## Planification et traitement

Une fois le `Broadcast` prêt, l’utilisateur peut le planifier.

La planification crée une tâche technique qui exécutera le traitement de l’envoi. Le traitement ne consiste pas à afficher immédiatement les emails à l’écran : il génère les messages et les place dans la file d’envoi.

Lors du traitement :

1. le `Broadcast` passe en statut `processing` ;
2. le système parcourt les identités destinataires ;
3. un email est préparé pour chaque adresse valide ;
4. l’objet et le corps HTML du `Broadcast` sont utilisés ;
5. les documents attachés sont ajoutés comme pièces jointes ;
6. chaque email est placé dans la file d’envoi ;
7. le `Broadcast` passe en statut `processed` ;
8. les emails générés sont reliés au `Broadcast`.

Le suivi des emails générés se fait via la liste des `mails_ids` associée à l’envoi groupé.

## Pièces jointes et documents

Un `Broadcast` peut contenir une ou plusieurs pièces jointes issues de l’EDMS.

Les documents joints sont des `Document` liés à la copropriété concernée. Ils peuvent être sélectionnés parmi les documents existants ou ajoutés au moment de la préparation de l’envoi.

L’ajout d’un nouveau document depuis un `Broadcast` passe par le formulaire d’import documentaire. Le document importé est alors créé dans l’EDMS et rattaché à l’envoi groupé comme pièce jointe.

Ce fonctionnement permet de conserver la pièce jointe comme document EDMS tout en l’utilisant dans une communication groupée.

## Différence avec les correspondances et exports

`Broadcast`, `DocumentCorrespondence` et `ExportingTask` ne répondent pas au même besoin.

| Mécanisme | Usage principal | Résultat |
| --- | --- | --- |
| `DocumentCorrespondence` | Produire un document individualisé pour un destinataire. | Un document par destinataire. |
| `Broadcast` | Envoyer un même message email à plusieurs destinataires. | Des emails mis en file d’envoi. |
| `ExportingTask` | Regrouper des documents pour impression ou archive. | Une archive ou des PDF fusionnés. |

Un `Broadcast` peut utiliser des documents comme pièces jointes, mais il ne crée pas une correspondance individualisée par destinataire. À l’inverse, une `DocumentCorrespondence` porte un document propre à un destinataire et peut ensuite être envoyée selon son canal de distribution.

## Impression groupée et envoi postal

Pour l’envoi postal, la logique est différente de celle du `Broadcast`.

Il peut être nécessaire de générer un PDF contenant tous les courriers à envoyer, par exemple un fichier par copropriété. Dans ce cas, le système doit veiller aux contraintes d’impression, notamment au fait qu’un document de copropriétaire commence sur une page impaire afin de permettre l’impression recto-verso.

Ces fichiers peuvent ensuite être remis à un prestataire postal ou conservés comme archive de l’envoi par le syndic.

Cette logique relève des exports de correspondances et de l’impression groupée, pas du `Broadcast` email.

## Rappels

Les rappels sont des communications déclenchées par une situation métier, par exemple un paiement attendu ou une échéance non respectée.

Ils peuvent être automatisés, mais leur impact métier doit rester traçable. Dans certains cas, l’envoi d’un rappel peut aussi être facturé comme prestation du syndic.

La logique de rappel doit donc distinguer :

* le déclencheur métier du rappel ;
* le document ou message généré ;
* le canal d’envoi ;
* la trace conservée dans l’EDMS ou dans le suivi des emails ;
* l’éventuelle facturation de la prestation.

## Synthèse

Le `Broadcast` est le mécanisme d’envoi groupé par email.

Il permet de sélectionner un ensemble de destinataires, de rédiger un message commun, d’ajouter des documents EDMS en pièces jointes, puis de planifier la génération des emails.

Il doit être utilisé pour les communications groupées. Pour les documents individualisés, la référence reste la `DocumentCorrespondence`. Pour l’impression ou l’archivage groupé, la référence reste l’export de correspondances.

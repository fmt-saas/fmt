# Exports de correspondances

## Objectif

Les exports de correspondances permettent de regrouper des documents générés afin de préparer une opération de diffusion, d’impression ou d’archivage.

Ils répondent à un besoin différent de la génération documentaire : la génération produit les documents individuels, tandis que l’export organise ces documents sous une forme exploitable par un utilisateur ou par un processus externe.

Les cas typiques sont :

* produire un fichier `.zip` contenant plusieurs regroupements PDF ;
* fusionner des correspondances destinées à l’impression papier ;
* préparer un lot postal ;
* produire une archive documentaire liée à une émission ;
* suivre une génération longue sans bloquer l’interface utilisateur.

## Différence entre génération et export

La génération d’un document aboutit à un `Document` persistant, éventuellement lié à une `DocumentCorrespondence` lorsque le document est individualisé pour un destinataire.

L’export intervient ensuite pour organiser une série de documents. Il peut utiliser des documents déjà générés, ou déclencher une génération technique nécessaire à la constitution du lot. Le résultat attendu n’est pas une correspondance individuelle, mais un output groupé : un PDF fusionné, plusieurs PDF classés, ou une archive `.zip`.

| Opération | Résultat principal | Entités concernées |
| --- | --- | --- |
| Génération de document | Un PDF stocké comme `Document` | `Document`, `DocumentCorrespondence` |
| Export de correspondances | Un regroupement ou une archive | `ExportingTask`, `ExportingTaskLine` |
| Envoi email | Un message avec pièce jointe | `DocumentCorrespondence`, suivi d’envoi |
| Impression groupée | Un ou plusieurs PDF fusionnés | `ExportingTaskLine`, `Document` technique |

## `ExportingTask`

`ExportingTask` représente une demande globale d’export.

Elle porte le contexte général de l’opération : le nom de l’export, la copropriété concernée, le statut du traitement et les lignes d’export associées.

Une tâche d’export est utilisée lorsque l’opération peut être longue ou produire plusieurs fichiers. Elle évite de réaliser tout le travail dans une requête interactive et permet de suivre l’état d’avancement.

Une `ExportingTask` peut être dans un état d’attente, en cours d’exécution, prête à être téléchargée ou en erreur. Lorsqu’elle est prête, l’utilisateur peut récupérer le résultat via l’action de téléchargement associée.

## `ExportingTaskLine`

`ExportingTaskLine` représente une unité de travail dans une tâche d’export.

Chaque ligne décrit ce qui doit être généré ou récupéré :

* un nom fonctionnel ;
* un controller à exécuter ;
* des paramètres d’exécution ;
* un statut de traitement ;
* éventuellement un `Document` généré comme résultat de la ligne.

Cette structure permet de découper un export global en plusieurs traitements indépendants. Par exemple, une ligne peut produire le PDF fusionné d’un groupe de correspondances, tandis qu’une autre produit un autre regroupement destiné à un usage différent.

Le controller référencé par la ligne est responsable de produire le contenu attendu. Dans le cas d’un export postal, ce contenu est généralement un PDF prêt à être intégré dans l’archive finale.

## Traitement asynchrone

Les exports sont générés de manière asynchrone.

Le traitement est pris en charge par une action de type cron qui sélectionne une `ExportingTask` à traiter, exécute les lignes en attente, stocke les résultats et met à jour les statuts.

Ce fonctionnement est important pour plusieurs raisons :

* un export peut nécessiter de nombreux rendus PDF ;
* la fusion de documents peut être coûteuse ;
* la création d’une archive peut prendre du temps ;
* l’utilisateur doit pouvoir lancer l’export puis revenir le télécharger plus tard.

Une tâche déjà en cours peut être détectée afin d’éviter que plusieurs exports lourds soient exécutés simultanément. Si une tâche reste bloquée trop longtemps, elle peut être remise en attente ou marquée en erreur selon la logique d’exécution.

## Regroupement et fusion PDF

L’export de correspondances sert souvent à préparer un envoi postal.

Dans ce cas, les documents individuels ne sont pas seulement listés : ils peuvent être regroupés et fusionnés en PDF plus volumineux, selon une logique fonctionnelle.

Les regroupements possibles dépendent du contexte métier :

* par copropriété ;
* par type de courrier ;
* par canal de distribution ;
* par ordre d’impression ;
* par lot postal.

La fusion PDF ne remplace pas les `DocumentCorrespondence`. Elle produit un output pratique pour l’impression. Les correspondances restent les objets de référence pour savoir quel document a été produit pour quel destinataire.

## Archive ZIP

Le résultat final d’un export peut être une archive `.zip`.

Cette archive peut contenir :

* un PDF fusionné unique ;
* plusieurs PDF correspondant à différents regroupements ;
* des fichiers complémentaires nécessaires à l’opération d’impression ou d’archivage.

L’archive représente le résultat téléchargeable de la `ExportingTask`. Lorsque la tâche est prête, le téléchargement parcourt les lignes d’export finalisées, récupère leurs documents et construit l’archive finale.

Après téléchargement, la tâche peut être marquée comme exportée afin de distinguer les exports prêts mais non récupérés des exports déjà utilisés.

## Impression groupée

Pour l’impression groupée, la logique attendue est la suivante :

1. les correspondances individuelles sont identifiées ;
2. les documents nécessaires sont générés ou récupérés ;
3. les PDF sont regroupés selon la règle d’impression ;
4. chaque regroupement produit un document technique ;
5. les documents techniques sont placés dans une archive `.zip` ;
6. l’utilisateur télécharge l’archive et l’utilise pour l’impression ou l’envoi postal.

Les documents techniques liés aux lignes d’export ne doivent pas être confondus avec les documents métier visibles dans l’EDMS. Ils sont produits pour constituer l’export et peuvent n’avoir qu’une durée de vie opérationnelle limitée.

## Cas de l’envoi email

L’envoi email suit une logique différente de l’export postal.

Pour un email, la règle fonctionnelle est généralement : une `DocumentCorrespondence`, un email, une pièce jointe. Le document joint est le `Document` associé à la correspondance.

L’emailing peut être planifié ou exécuté par lot, mais il ne nécessite pas nécessairement de fusion PDF ni d’archive `.zip`. Le suivi porte plutôt sur l’état d’envoi de chaque correspondance et sur la traçabilité de la communication.

L’export postal et l’envoi email partagent donc la même base documentaire, mais pas le même output :

* l’email distribue chaque correspondance individuellement ;
* l’export postal regroupe des correspondances pour une opération physique ou administrative.

## Cycle de vie d’un export

Le cycle de vie général d’un export peut être résumé ainsi :

1. création d’une `ExportingTask` ;
2. création des `ExportingTaskLine` nécessaires ;
3. passage de la tâche en traitement asynchrone ;
4. exécution des controllers définis sur les lignes ;
5. stockage des documents produits par les lignes ;
6. passage de la tâche à l’état prêt ;
7. téléchargement de l’archive `.zip` ;
8. marquage éventuel de l’export comme récupéré.

En cas d’erreur sur une ligne, la tâche peut être marquée en échec et un log d’export permet de tracer la cause du problème. L’objectif est de rendre les exports longs contrôlables, relançables et auditables.

## Synthèse

La génération documentaire et l’export de correspondances sont complémentaires.

La génération répond à la question : **quel document doit exister pour quel destinataire ?**

L’export répond à la question : **comment regrouper ces documents pour une opération collective ?**

Cette séparation permet de conserver une trace individualisée dans le EDMS tout en produisant des outputs groupés adaptés à l’impression, à l’archivage ou à d’autres modes de diffusion.

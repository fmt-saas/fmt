# Fonctionnement des envois de correspondances

## Vue d’ensemble

Les envois de documents individualisés reposent sur une séparation entre la préparation des destinataires, la génération du PDF et sa distribution. La création d’une correspondance ne produit donc pas immédiatement un fichier : elle enregistre d’abord quel document devra être adressé, à qui, pour quelle propriété et par quel canal.

Le PDF est généré plus tard, au premier besoin. Il peut être déclenché par l’envoi d’un e-mail ou par la préparation d’un export postal. Cette génération différée évite de produire des documents inutilisés et permet à plusieurs canaux de réutiliser le même PDF individuel.

Les mécanismes généraux de rendu et de stockage sont décrits dans [Génération de documents](generation-de-documents.md). La constitution des lots imprimables est détaillée dans [Exports de correspondances](exports-de-correspondances.md).

## Objets à distinguer

| Objet | Rôle |
| --- | --- |
| Rendu HTML ou PDF | Aperçu ou résultat technique temporaire, sans persistance obligatoire. |
| `Document` | PDF persistant, conservé comme artefact individuel dans l’EDMS. |
| `DocumentCorrespondence` | Lien entre un document, un destinataire, une propriété et un canal de communication. |
| `ExportingTask` | Traitement asynchrone qui regroupe les documents destinés à l’envoi papier. |
| `ExportingTaskLine` | Unité de travail produisant un regroupement PDF, généralement pour un canal postal donné. |

Il n’existe pas, dans ce flux, d’entité générique `Artifact`. L’artefact individuel est principalement représenté par un `Document`. Les PDF fusionnés produits pour un export sont également stockés comme des `Document`, mais ce sont des documents techniques qui ne sont pas visibles dans l’EDMS.

## Sélection des destinataires et des canaux

Pour chaque propriété concernée, le système retient le propriétaire représentant. Il consulte ensuite les préférences de communication de l’`Ownership` pour le motif de l’envoi.

Les canaux disponibles pour les convocations et les procès-verbaux d’assemblée générale sont :

* `email` ;
* `postal` ;
* `postal_registered` ;
* `postal_registered_receipt`.

Une `DocumentCorrespondence` distincte est créée pour chaque canal sélectionné. Si plusieurs canaux sont activés, le même destinataire possède donc plusieurs correspondances pour le même envoi. Si aucun canal n’est configuré, le courrier postal recommandé (`postal_registered`) est utilisé par défaut.

Cette distinction est importante : la correspondance représente une intention d’envoi par un canal précis, tandis que le `Document` représente le contenu PDF individuel. Plusieurs correspondances peuvent ainsi partager le même document.

## Génération différée et réutilisation du document

La création des correspondances n’entraîne pas immédiatement la génération des PDF. Le document individuel est produit lorsque le traitement e-mail ou l’export postal rencontre une correspondance qui ne possède pas encore de `document_id`.

Avant de générer un nouveau PDF, le système recherche une correspondance du même type pour le même contexte métier, la même propriété et le même propriétaire. Si un document existe déjà, il est réutilisé. Un envoi combinant e-mail et courrier postal peut donc utiliser un seul PDF individualisé sans le générer deux fois.

Une fois créé, le document individuel est rattaché à la copropriété, au contexte métier concerné, à l’`Ownership` et au `Owner`. Il est placé dans le dossier EDMS approprié avec une visibilité limitée à la propriété.

## Distribution par e-mail

Lorsque des correspondances utilisent le canal `email`, une tâche planifiée lance leur traitement. Pour chaque correspondance :

1. le document manquant est généré ou un document déjà produit est récupéré ;
2. un e-mail individuel est créé à partir du template e-mail adapté ;
3. le `Document` de la correspondance est ajouté comme pièce jointe ;
4. l’e-mail est placé dans la file de la mailbox du processus de gouvernance ;
5. la correspondance est marquée comme envoyée avec sa date d’envoi.

La règle fonctionnelle est donc : **une correspondance e-mail → un e-mail → un document individualisé en pièce jointe**.

Le template utilisé pour l’enveloppe e-mail est distinct du template documentaire, même s’ils partagent le même code fonctionnel. Les convocations d’une seconde session utilisent une variante dédiée pour le document et pour l’e-mail.

## Distribution postale

Les canaux postaux ne créent pas d’e-mail. Ils alimentent une `ExportingTask` destinée à préparer les fichiers à imprimer.

Une `ExportingTaskLine` est créée pour chaque canal postal présent parmi les correspondances. Chaque ligne :

1. sélectionne les correspondances de son canal ;
2. génère les documents individuels manquants ;
3. récupère les PDF individuels ;
4. les fusionne dans un PDF groupé ;
5. stocke le résultat comme un `Document` technique non visible dans l’EDMS.

Le traitement asynchrone exécute les lignes et fait passer la tâche à l’état `ready` lorsque tous les regroupements sont disponibles. Le téléchargement construit alors une archive ZIP contenant un PDF par canal postal et marque la tâche comme téléchargée avec `is_exported = true`.

Le PDF groupé ne remplace pas les documents individuels : ceux-ci restent les artefacts persistants qui assurent la traçabilité par destinataire.

## Exemple : convocations d’assemblée générale

### Préparation des correspondances

Lors de l’action **Créer les convocations**, le système :

1. recalcule les propriétés concernées par l’assemblée ;
2. supprime les anciennes `AssemblyInvitationCorrespondence` de cette assemblée ;
3. crée les correspondances pour chaque propriété qui possède un propriétaire représentant ;
4. détermine les canaux au moyen des `OwnershipCommunicationPreference` configurées pour le motif `general_assembly_call` ;
5. applique le recommandé postal par défaut si aucune préférence n’active de canal.

### Contenu du document individuel

Au premier besoin, le PDF de convocation est assemblé avec `qpdf` dans l’ordre suivant :

1. la lettre personnalisée adressée au propriétaire ;
2. l’ordre du jour de l’assemblée ;
3. le mandat propre à la propriété.

Le résultat devient un `Document` de type `general_assembly_document`, de sous-type `invite`, avec une visibilité `ownership`. Il est lié à l’assemblée, à l’`Ownership` et au `Owner`, puis placé dans le dossier EDMS des assemblées.

### Fin du processus d’envoi

Après la mise en file des e-mails et la préparation des documents papier, l’assemblée reste dans son étape d’envoi. Lorsqu’un export postal existe, il doit être à l’état `ready` et avoir été effectivement téléchargé avant que les convocations puissent être marquées comme envoyées.

L’action **Marquer comme envoyé** marque ensuite les correspondances non électroniques comme envoyées. Les correspondances e-mail sont, quant à elles, marquées lors de leur mise en file individuelle.

## Exemple : envoi du procès-verbal

Le flux du procès-verbal est similaire à celui des convocations, mais il ne peut commencer qu’après la clôture de sa signature.

### Production du procès-verbal source

Le cycle de production est le suivant :

1. un procès-verbal non signé est généré pour confirmation ;
2. les signatures requises sont collectées ;
3. la clôture des signatures génère le document signé et renseigne `signed_minutes_document_id` ;
4. l’assemblée passe au statut `held`, à l’étape `assembly_closing`.

### Création et distribution des correspondances

L’action **Envoyer le PV** :

1. recrée les `AssemblyMinutesCorrespondence` ;
2. applique les préférences configurées pour le motif `general_assembly_minutes` ;
3. utilise le recommandé postal par défaut lorsqu’aucun canal n’est activé ;
4. programme le traitement des correspondances e-mail ;
5. crée une tâche et une ligne d’export pour chaque canal postal présent.

Le PDF individuel fusionne la lettre d’accompagnement personnalisée et le procès-verbal signé. Les annexes de l’assemblée ne sont actuellement pas ajoutées au PDF envoyé ; elles restent accessibles depuis la plateforme.

## Synthèse du flux

```text
Assemblée générale
└── propriétés concernées
    └── propriétaire représentant
        └── préférence(s) de communication
            └── une correspondance par canal
                └── génération différée du Document individuel
                    ├── email : un e-mail + le Document en pièce jointe
                    └── postal : regroupement par canal → PDF fusionné → ZIP
```

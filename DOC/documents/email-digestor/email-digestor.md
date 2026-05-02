# Email Digestor

L’**Email Digestor** est un mécanisme d’**ingestion de documents entrants par email**.
Il permet de connecter une ou plusieurs boîtes email au système afin de transformer automatiquement les **pièces jointes reçues** en **documents pris en charge par le processus standard de traitement**.

L’email n’est pas considéré comme un objet métier en soi, mais comme un **canal d’entrée** vers le système documentaire.

---

## Principe général

Le fonctionnement de l’Email Digestor repose sur les éléments suivants :

* des **Mailboxes** configurées dans le système,
* un accès en lecture aux messages entrants
* l’extraction des pièces jointes autorisées
* la création automatique de documents
* le **démarrage immédiat d’un `DocumentProcess`** pour chaque document importé.

👉 Une fois importé, un document issu d’un email est traité **exactement comme tout autre document importé**, sans logique spécifique liée à l’email.

---

## Mailboxes

Une **Mailbox** représente un compte email configuré pour recevoir des documents entrants.

Chaque Mailbox définit notamment :

* le serveur mail  et ses paramètres d’accès,
* le mode d’authentification,
* l’état de validation du compte,
* la date de dernière synchronisation.

Une Mailbox validée peut être interrogée périodiquement afin de récupérer **uniquement les nouveaux messages** depuis la dernière synchronisation.

---

## Récupération des emails

Lors de chaque synchronisation :

1. Le système se connecte à la Mailbox (imap ou API)
2. Les messages reçus depuis la dernière synchronisation sont récupérés.
3. Chaque message est enregistré comme une entité `Email`, avec :

   * l’expéditeur,
   * les destinataires,
   * le sujet,
   * la date,
   * le contenu du message.

Les emails déjà connus (identifiés par leur `message_id`) sont ignorés afin d’éviter toute duplication.

---

## Traitement des pièces jointes

Pour chaque email entrant :

* seules les **pièces jointes autorisées** (PDF, documents bureautiques, feuilles de calcul, etc.) sont prises en compte,
* chaque pièce jointe est convertie en une entité `Document`,
* le document est lié à l’email source via le champ `email_id`.

Les messages sans pièce jointe exploitable sont ignorés du point de vue documentaire.

---

## Démarrage automatique du traitement

Dès la création d’un document issu d’une pièce jointe :

* l’action `start_processing` est déclenchée automatiquement,
* un `DocumentProcess` est créé et associé au document,
* le document est marqué comme **document d’origine** (`is_origin = true`).

À partir de ce moment, le document suit **le workflow standard du DocumentProcess** :

> `created → assigned → completed → validated → integrated`

Aucune logique spécifique à l’email n’intervient après cette étape.

---

## Lien entre email, document et traitement

* L’email sert de **trace contextuelle** (source de réception).
* Le document devient l’**objet central** du traitement.
* Le `DocumentProcess` porte l’intégralité du workflow.

Cette séparation garantit que :

* le traitement documentaire reste cohérent,
* les règles métier ne dépendent jamais du canal d’entrée,
* un document importé par email est strictement équivalent à un document importé manuellement.

---

## Statut des emails et documents

Les documents disposent de leur propre statut de suivi (`imported`, `pending`, `processed`, `ignored`), indépendamment du workflow du `DocumentProcess`.

L’email source peut être considéré comme **traité** dès lors que :

* toutes ses pièces jointes ont été importées,
* et que les documents correspondants ont été pris en charge par un `DocumentProcess`.

---

## Cas d’usage couverts

Ce mécanisme permet notamment :

* la centralisation automatique des documents reçus par email,
* la suppression des manipulations manuelles (téléchargement / upload),
* la traçabilité des échanges entrants,
* l’intégration directe dans les processus métier existants (comptabilité, juridique, gestion, etc.).

---

## Positionnement dans l’architecture

L’Email Digestor :

* **n’implémente aucune logique métier**,
* **ne classe pas définitivement les documents**,
* **ne valide rien**,
* **ne décide pas du type final du document**.

Il se limite volontairement à un rôle unique :

> **injecter des documents entrants dans le système de traitement standard**.


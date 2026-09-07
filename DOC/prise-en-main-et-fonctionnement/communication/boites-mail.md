# Boîtes mail

## Où trouver cet écran ?

Menu `Configuration` > `Communication` > `E-mails` > `Boîtes mail`.

L'accès à la configuration détaillée dépend des droits de l'utilisateur. En cas de doute sur les paramètres de connexion, il est recommandé de s'adresser à un administrateur.

## À quoi sert une boîte mail ?

Une `Boîte mail` relie une adresse e-mail au logiciel. Elle peut servir :

* à recevoir des e-mails et à importer leurs pièces jointes dans les documents ;
* à envoyer les e-mails produits par les processus de gestion ;
* ou à assurer les deux fonctions.

Les options `Envois` et `Réception` sont indépendantes. Une boîte peut donc être réservée aux documents entrants, aux communications sortantes ou être utilisée dans les deux sens.

La boîte mail n'est pas un webmail complet destiné à rédiger librement tous les messages d'une agence. Elle sert principalement de point d'entrée et de sortie pour les traitements réalisés dans l'application.

## Comprendre la liste des boîtes mail

La liste présente notamment :

* l'adresse e-mail ;
* le type d'authentification ;
* le fournisseur OAuth, lorsqu'il est utilisé ;
* l'autorisation d'envoyer ;
* l'autorisation de recevoir ;
* le statut de la connexion.

Deux statuts sont possibles :

* `Non connectée` : la boîte est créée, mais sa connexion doit encore être établie ou renouvelée ;
* `Connectée` : l'authentification a abouti et la boîte peut utiliser les fonctions activées.

Une adresse e-mail ne peut être enregistrée que dans une seule boîte mail.

## Ajouter et connecter une boîte mail

L'action `Ajouter une boîte mail` permet d'enregistrer une nouvelle adresse et de choisir son usage.

Deux modes d'authentification sont prévus :

* l'authentification basique, au moyen d'un identifiant, d'un mot de passe et des paramètres des serveurs IMAP et SMTP ;
* l'authentification OAuth pour un compte Google ou Microsoft, au moyen de l'action `Connexion OAuth`.

Avec OAuth, l'utilisateur est redirigé vers le fournisseur de messagerie afin d'autoriser la connexion. Le mot de passe du compte n'est pas saisi dans l'application. Lorsque l'autorisation est terminée, la boîte passe au statut `Connectée`.

Si une boîte OAuth repasse au statut `Non connectée`, une nouvelle connexion peut être nécessaire, par exemple lorsque l'autorisation ne peut plus être renouvelée.

## Recevoir des e-mails et importer les pièces jointes

L'action `Recevoir` est disponible sur une boîte connectée pour laquelle l'option `Réception` est activée. Depuis la liste, l'action équivalente est nommée `Télécharger`.

Lors d'une récupération :

1. l'application recherche les nouveaux messages reçus depuis la dernière synchronisation ;
2. chaque nouveau message est enregistré avec son expéditeur, ses destinataires, son sujet, sa date et son contenu ;
3. les messages déjà connus sont ignorés afin d'éviter les doublons ;
4. les pièces jointes prises en charge sont transformées en documents ;
5. chaque document importé rejoint le circuit habituel de traitement des documents.

La date `Dernière synchro` indique le point de départ de la prochaine récupération. Pour une nouvelle boîte, ce point de départ est placé par défaut environ 24 heures avant sa création : la première récupération ne reprend donc pas nécessairement tout l'historique du compte.

Un message sans pièce jointe exploitable peut être conservé comme e-mail, mais il ne crée pas de document à traiter.

```text
Boîte mail → e-mail entrant → pièce jointe → document → traitement documentaire
```

## Envoyer des e-mails

Lorsqu'une opération métier prépare une communication — par exemple une convocation, un procès-verbal, un appel de fonds ou un rappel de paiement — l'application crée un e-mail sortant et le place en attente dans la boîte associée au domaine de gestion concerné.

Le traitement d'envoi recherche les e-mails sortants en attente et utilise :

* le serveur SMTP pour une authentification basique ;
* le service Google ou Microsoft pour une authentification OAuth.

L'action `Envoyer` permet également de lancer l'envoi des messages en attente de la boîte. Elle n'est visible que si la boîte est connectée et que l'option `Envois` est activée.

La section `Outbox` de la fiche permet de consulter les e-mails sortants liés à cette adresse. La section `Inbox` présente de la même manière les e-mails entrants lorsque la réception est activée.

```text
Opération métier → e-mail en attente → boîte mail attribuée → destinataire
```

## Quelle adresse est utilisée pour un envoi ?

Les domaines de gestion, tels que la finance, la gouvernance, le juridique ou la communication, peuvent être associés à des boîtes mail différentes. Cette association détermine l'adresse utilisée comme expéditeur pour les e-mails produits par chaque domaine.

Lorsqu'aucune autre boîte d'envoi connectée n'existe, la première boîte activée pour les envois et connectée est automatiquement affectée à l'ensemble des domaines de gestion. Un administrateur peut ensuite adapter ces associations dans les `Tâches de gestion`.

## Parcours conseillé

1. Ouvrir la liste `Boîtes mail`.
2. Vérifier l'adresse, les options `Envois` et `Réception`, ainsi que le statut.
3. Si nécessaire, ajouter la boîte ou renouveler sa connexion OAuth.
4. Pour une boîte de réception, utiliser `Recevoir` puis vérifier les e-mails et documents importés.
5. Pour une boîte d'envoi, vérifier la section `Outbox` et utiliser `Envoyer` si des messages sont en attente.
6. Si une communication utilise une adresse inattendue, faire vérifier la boîte attribuée au domaine concerné dans les `Tâches de gestion`.

## En cas de problème

Vérifier en priorité :

* que la boîte est `Connectée` ;
* que l'option `Envois` ou `Réception` correspondant à l'action souhaitée est activée ;
* que la date de dernière synchronisation est cohérente ;
* que la connexion OAuth n'a pas besoin d'être renouvelée ;
* que le bon compte est associé au domaine de gestion concerné.

Les paramètres de serveur, les identifiants techniques et les jetons d'accès sont des informations sensibles. Leur modification doit être réservée aux personnes chargées de l'administration de la messagerie.



# Authentification

## Principe général

L’authentification des utilisateurs sur la plateforme FMT est **centralisée** et s’effectue, en principe, **exclusivement via l’instance \*Global\***.
Cette approche permet une gestion unifiée des identités et une propagation sécurisée des sessions entre les différentes instances locales.



## Mécanisme d’authentification

L’authentification repose sur l’une des **méthodes supportées** par la plateforme :

- **pwd** (mot de passe)
- **passkey** (clé de sécurité)
- **mfa** (authentification multifacteur)
- ou toute autre méthode compatible avec le module d’authentification central.

Lorsqu’un utilisateur s’authentifie avec succès, il obtient un **`access_token`**, valide **uniquement pour l’instance en cours**.

Si la connexion est effectuée sur l’**instance Global**, un **second jeton**, le **`global_token`**, est généré.

Ce jeton est **valide pour tous les sous-domaines** (instances locales) rattachés à l’instance Global, et permet une authentification transparente sur celles-ci sans reconnexion explicite.



## Synchronisation entre Global et Local

Lorsqu’un utilisateur tente d’accéder directement à une **instance Locale**, le mécanisme suivant est appliqué :

1. Le navigateur transmet, via une requête `userinfo`, le **`global_token`** obtenu précédemment.
2. Si aucun **`access_token`** local n’est présent, une **requête de vérification** est envoyée à l’instance Global.
3. L’instance Global vérifie si un **`user_id`** existe pour l’un des comptes liés à l’identité associée, pour l’instance ciblée.
4. Si c’est le cas, le **`user_id`** est retrouvé à partir du **UUID** retourné, et un **`access_token`** local est alors généré dynamiquement.



## Gestion de la connexion sur l’instance Global

Lors d’une connexion sur l’instance Global :

- Les **informations liées à l’utilisateur** (profil, rôles, associations) sont récupérées.
- Si le compte de l’utilisateur est **associé à une seule instance locale**, une **redirection automatique** est effectuée vers cette instance.
- Dans le cas contraire, un **Dashboard** est affiché, présentant la **liste des copropriétés (Condominium)** associées à son compte, selon les **rôles et permissions** qui lui sont attribués.



## Connexion et interface sur une instance Locale

Lorsqu’un utilisateur accède à une **instance Locale** (directement ou via redirection) :

- L’interface et le **Dashboard** affichés dépendent **strictement du rôle** de l’utilisateur.
- Pour les **Propriétaires**, si leur compte est associé à plusieurs copropriétés, une **sélection initiale** est demandée afin de choisir la copropriété à consulter avant de charger le contenu.



## Récap du flux d’authentification

| Étape | Instance concernée | Action principale                       | Jeton                              |
| ----- | ------------------ | --------------------------------------- | ---------------------------------- |
| 1     | Global             | Authentification utilisateur            | `access_token` + `global_token`    |
| 2     | Locale             | Vérification via `global_token`         | Génération du `access_token` local |
| 3     | Global / Locale    | Redirection ou sélection de copropriété | Session persistante                |




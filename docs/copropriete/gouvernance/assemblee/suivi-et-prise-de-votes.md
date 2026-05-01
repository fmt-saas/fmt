# Suivi du déroulé d'une Assemblée et prises de votes





## Création automatique des votes à l’ouverture d’une résolution

Lorsqu’une **résolution** est ouverte et qu’elle **implique un vote**, le système procède automatiquement à la création et à la préparation des votes selon les règles suivantes :

#### 1. Création des votes

- Un **vote** (statut initial : `pending`) est créé **pour chaque propriété (ownership)** concernée par la résolution **et** dont le propriétaire est **présent ou représenté** à l’assemblée.
- Cette création se base sur la liste des **ownerships impliqués** dans le point de résolution (`involved_ownerships_ids`).

#### 2. Vérification des représentations

Pour chaque **ownership représenté**, le système vérifie :

- que le type de représentation (`representation_type`) est cohérent ;
- et, dans le cas d’une **représentation par procuration** (`proxy`), que le **mandat d’assemblée** associé (`assembly_mandate_id`) :
  - a un statut `validated`,
  - et est marqué comme `is_valid = true`.

Les ownerships représentés sans mandat valide sont **exclus de la création de vote**.

#### 3. Application des intentions de vote

Avant de finaliser le vote, le système recherche une **intention de vote existante** correspondant à la combinaison :

- `ownership_id` = même propriété,
- `assembly_item_id` = même résolution,
- et, le cas échéant, `assembly_mandate_id` identique si la représentation est de type `proxy`.

Si une intention valide est trouvée :

- les valeurs (`vote_value`, `assembly_item_choice_id`) sont reprises,
- et le vote est automatiquement **“casté”** (transition du statut `pending` → `casted`).

#### 4. Gestion des statuts de vote

Chaque vote possède un **statut** qui reflète son état :

- `pending` → le vote est enregistré mais non encore exprimé ;
- `casted` → le vote a été validé ou confirmé.

En cas de **re-vote**, les votes concernés sont remis au statut `pending`.
 Les **résultats de vote** ne sont visibles qu’une fois les votes passés au statut `casted`.


## Vérification du quorum de présence (Assemblée générale ACP – Belgique)

Cette règle implémente la vérification du **quorum de présence** applicable aux assemblées générales de copropriété (ACP), conformément à l’**article 3.87, §5 du Code civil belge** (anciennement article 577-6).

### 1. Cas de la deuxième séance

Si l’assemblée se tient en **deuxième séance**, aucun quorum de présence n’est requis : l’assemblée peut délibérer valablement quel que soit le nombre de copropriétaires présents ou représentés.

Dans ce cas, le test considère automatiquement que le quorum est atteint.

### 2. Cas de la première séance : règle générale

Lors d’une **première séance**, l’assemblée ne peut délibérer valablement que si les copropriétaires présents ou représentés remplissent une condition de quorum définie par la loi.

Le test calcule deux ratios :

- **ratio des quotes-parts** représentées :
  `count_represented_shares / count_shares`
- **ratio des copropriétaires (dossiers de propriété / ownerships)** présents ou représentés :
  `count_represented_owners / count_owners`

### 3. Cas particulier : quorum atteint automatiquement à partir de 75%

Conformément au texte légal, si les copropriétaires présents ou représentés détiennent **au moins trois quarts (75%) des quotes-parts**, le quorum est automatiquement considéré comme atteint.

Dans ce cas, il n’est pas nécessaire de vérifier la proportion de copropriétaires présents.

### 4. Vérification du double quorum (si moins de 75% des quotes-parts sont représentées)

Si moins de 75% des quotes-parts sont représentées, la loi impose un **double quorum** :

1. **Plus de 50% des copropriétaires** doivent être présents ou représentés
   (strictement supérieur à 50%)
2. **Au moins 50% des quotes-parts** doivent être présentes ou représentées
   (supérieur ou égal à 50%)

Si l’une de ces deux conditions n’est pas remplie, l’assemblée ne peut pas délibérer valablement.


### Référence légale

Ces règles sont basées sur l’**article 3.87, §5 du Code civil belge** (ancien art. 577-6), qui définit les conditions de quorum de présence pour la validité des délibérations d’une assemblée générale de copropriété.

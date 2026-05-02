## Clés de répartition


Lors de la création d’une copropriété, un **plan comptable spécifique** lui est attribué sur la base d’un **template général**.

- Une **clé statutaire** doit obligatoirement être définie manuellement dès la création.
- Cette clé statutaire peut être utilisée pour **générer une clé de répartition**, qui est souvent la **clé de répartition des charges par défaut**.



Les clés de répartition sont propres à chaque copropriété (ID local), garantissant leur personnalisation selon les règles spécifiques de la copropriété.

Certains comptes du plan comptable peuvent être associés à une clé de répartition prédéfinie, indiquée directement dans le template du plan comptable.

Lors de la mise en place du plan comptable pour une copropriété, un mapping est effectué entre :

1. Les **comptes du template général**.
2. Le **plan comptable propre à la copropriété**.
3. La **clé de répartition** associée (si définie dans le template).

### Attribution des codes aux clés de répartition

- **Code unique par clé :** chaque clé dispose d'un identifiant propre, toujours associé à une copropriété donnée.
- **Génération automatique :** les codes sont attribués par le système et comptent toujours **quatre caractères**.
- **Format des codes :**
  - **Clé statutaire :** une copropriété possède toujours **une seule** clé statutaire, identifiée par le code `STAT`.
  - **Clés additionnelles :** les autres clés utilisent un code **numérique** sur quatre positions, complété par des zéros en préfixe (`0001`, `0002`, ...).

### Utilisation des codes d’identification dans les templates

Un template peut référencer une clé de répartition via son code (par exemple `STAT` ou `0001`). Lors de l'application du template à une copropriété, le système recherche la clé dont le code correspond et l'utilise pour les calculs ou sections générées.

### Convention actuelle et limitations

- **Code standard reconnu – `STAT` :** aujourd'hui, seul le code `STAT` est garanti comme existant dans toutes les copropriétés.
- **Autres codes :** l'usage de codes numériques dans les templates nécessite de vérifier manuellement que la clé équivalente existe dans la copropriété cible.

## 🔐 Permissions et Autorisations

### 1. Principe général

Le système distingue **deux niveaux de permissions** :

- un **niveau bas** (technique, basé sur les ACL),

- et un **niveau haut** (métier, basé sur les rôles et autorisations).

Une configuration de base assure la cohérence entre les groupes, leurs permissions respectives, et les rôles auxquels ils sont associés.



### 2. Niveau bas : Permissions (ACL)

- Les **Permissions** définissent les droits d’accès à des classes d’objets ou à leurs actions génériques.

- Elles sont attribuées à des **Users** ou à des **Groups**.

- Les **Users** héritent automatiquement des permissions assignées aux **Groups** auxquels ils appartiennent.

Ce niveau correspond à la sécurité d’infrastructure et à la logique d’accès applicatif.



### 3. Niveau haut : Autorisations (Grants)

- Les entités **Employee** (liées à un `User`) représentent les acteurs métiers.

- Un **Role** peut être attribué à un ou plusieurs employés.

- Les **Roles** peuvent être associés à un ou plusieurs **Groups** : ils héritent ainsi des permissions techniques (ACL) de ces groupes.

- Les employés peuvent être regroupés en **Teams**, chacune étant associée à un **Role** unique ; cela permet d’attribuer un rôle à plusieurs utilisateurs simultanément.

- Des **Autorisations (Grants)** peuvent ensuite être accordées aux **Roles** pour permettre l’exécution d’**actions métier** ou de **transitions** spécifiques sur certaines classes d’objets.

Ce niveau introduit la logique métier et le contrôle des transitions de workflow.



### 4. Schéma conceptuel

```
                  +-----------------+
                  |   Permission    |   (ACL : droits techniques)
                  +-----------------+
                            ^
                            |
+--------+      +--------+  |  +--------+      +---------+
|  User  | <--> |Employee|  |  | Group  | ---> |  Role   |
+--------+      +--------+  |  +--------+      +---------+
                            |                      |
                            |                      v
                            |                 +-----------+
                            |                 |  Grant    |  (autorisations sur actions / transitions)
                            |                 +-----------+
                            |
                            |
                    (héritage / cohérence)
```





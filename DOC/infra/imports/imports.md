# Imports

Objectif : pouvoir importer des configurations complètes de Copropriétés depuis un modèles d'import standardisé (CSV/XLS).

## Stratégie de création des objets

### création d'une copropriété
création Condominium


### création d'un coropriétaire
(disableEvents)
création Identity avec les données
création Owner
lien entre Owner et Identity
synchro Identity > Owner
(enableEvents)


### création d'un lot

création PropertyLot
création d'un Ownership

assignation de Owners au Ownership
reset Ownership values (calc)


Il y a toujours une identité pour certains objets


si c'est une création auto, l'identité doit exister avant la création de l'objet
si c'est une création manuelle, l'identité est créée au moment de la création de l'objet




## Optipro


### Fichier Propriétaires (CSV)

| Champ                     | Description                                                                 |
|---------------------------|-----------------------------------------------------------------------------|
| Code                      | Code (unique à l'ACP) du copropriétaire (ex. : "C0196")                     |
| Civilité                  | "Monsieur", "Madame", "Monsieur et/ou Madame"                               |
| Nom                       | Nom de famille (en majuscules)                                              |
| Prénom                    | Prénom (Capitalized)                                                        |
| Adresse                   | Nom de rue et numéro + dispatch                                             |
| Code postal               | Code postal (BE)                                                            |
| Ville                     | Localité/ville                                                              |
| Pays                      | "Belgique"                                                                  |
| Emails                    | Adresses email, séparées par " - "                                          |
| Téléphones                | Numéros de téléphone (format libre)                                         |
| Langue parlée             | ? (Invalide) "be"                                                           |
| Mode de contact           | "email" ou "papier"                                                         |
| Mode de contact AG        | "email", "papier", ou "recommande"                                          |
| VCS                       | VCS assigné (caduque)                                                       |
| Login                     | ? /                                                                         |
| Copropriété nom           | Nom de l'ACP (libellé en toutes lettres)                                    |
| Copropriété code dossier  | Code de l'ACP (ex: "0002")                                                  |
| Lots                      | Liste des lots identifiés par [Référence bien]                              |
| Nombre de lots            | Nombre de lots (calculé autrement)                                          |
| Quotités                  | Nombre de quotités (caduque)                                                |
| Conseil de copropriété    | Flag pour marquer le Owner comme participant au CC                          |
| Président                 | Flag pour marquer le Owner comme président du CC                            |


### Fichier Lors (CSV)

| Champ                         | Description                                                                                     |
|-------------------------------|-------------------------------------------------------------------------------------------------|
| Référence bien                | Référence (unique à l'ACP) d'identification du lot (notation arbitraire)                        |
| Numéro cadastral              | ? Numéro cadastral (format inconnu)                                                             |
| Nature                        | Nature du bien [optipro] : COMMERCE, APPARTEMENT, TRIPLEX                                       |
| Quotités (clé 0001)           | Entier renseignant les quotités sur base de l'ACP (? en /1000, possiblement plusieurs colonnes) |
| Adresse (bien)                | Nom de rue et numéro de l’immeuble de l’ACP                                                     |
| Code postal (bien)            | Code postal (BE)                                                                                |
| Ville (bien)                  | Localité/ville                                                                                  |
| Pays (bien)                   | "Belgique"                                                                                      |
| Civilité                      | Civilité du propriétaire du lot                                                                 |
| Nom                           | Nom de famille (en majuscules) du propriétaire du lot                                           |
| Prénom                        | Prénom (Capitalized) du propriétaire du lot                                                     |
| Adresse (propriétaire)        | Nom de rue et numéro + dispatch                                                                 |
| Code postal (propriétaire)    | Code postal (BE)                                                                                |
| Ville (propriétaire)          | Localité/ville                                                                                  |
| Pays (propriétaire)           | "Belgique"                                                                                      |
| Emails                        | Adresses email, séparées par " - "                                                              |
| Téléphones                    | Numéros de téléphone (format libre)                                                             |
| Nom du bâtiment               | ? /                                                                                             |
| Numéro lot                    | Numéro (? séquentiel) (? unique) du lot                                                         |
| Étage                         | ? Entier ou nom (REZ, 1ER, ...) de l'étage                                                      |
| Numéro porte                  | ? Numéro de porte de palier                                                                     |
| Surface                       | ? Surface (en mètres carrés)                                                                    |

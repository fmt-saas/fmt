### Entrées, blocs & bâtiments (`PropertyEntrance`)

- Une **copropriété (Condominium)** peut comporter **plusieurs entrées** : bâtiments, blocs, cages d’escalier, adresses distinctes.  
- Chaque **entrée** est liée à une copropriété (`condo_id`).  
- L’**adresse** d’une entrée reprend par défaut celle de la copropriété, mais le **numéro / rue** peut être différent (ex. copropriété au n°10, mais entrée au n°12).  
- Le **nom de l’entrée** est généré automatiquement à partir de l’adresse, garantissant un affichage homogène.  
- Un **code interne** unique et immuable est attribué automatiquement pour assurer la traçabilité et les intégrations techniques.  

#### Points techniques
- **`condo_id`** : relation `many2one` vers la copropriété. Obligatoire, en lecture seule.  
- **`address_street`** : champ calculé et stocké, pré-rempli depuis la copropriété. Peut être modifié pour refléter une adresse spécifique.  
- **`name`** : champ calculé instantané, dépendant de l’adresse, stocké pour faciliter la recherche et l’affichage.  
- **`code`** : champ calculé, stocké et en lecture seule, généré par la fonction `calcPropertyEntranceCode`. Sert de référence technique stable.  
- **`description`** : champ libre pour des précisions (ex. “Bloc A”, “Entrée côté jardin”).  

### Exemple
- Copropriété “Résidence Atlas” : adresse principale “Rue des Érables 10, 1000 Bruxelles”.  
  - Entrée A : `address_street = "Rue des Érables 10"`  
  - Entrée B : `address_street = "Rue des Érables 12"`  

Ces deux entrées appartiennent à la **même copropriété**, mais disposent chacune de leur propre adresse et d’un identifiant interne distinct.

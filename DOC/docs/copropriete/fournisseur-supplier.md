## Fournisseur (`Supplier`)

Les Fournisseurs sont des objets "Protected" et tendent à être commun à toutes les DB (mais ne le sont pas toujours).
De manière similaire aux Ownership, les fournisseurs sont identifiés relativement à une copropriété, sur base d'un code de séquence assigné au niveau d'un entité `Suppliership`.

Au cours du temps (ou en même temps, dans certaines circonstances particulières), une copropriété peut avoir plusieurs contrats avec un même fournisseur. Ce lien est matérialisé par les entités `SupplierContract`.


Création d'un fournisseur : 
(import depuis la DB MASTER)
-> identification sur base du numéro d'entreprise


A la création, assignation d'un compte comptable, créé comme sous-compte de "suppliers" (440), avec le nom du fournisseur

Possibilité d'associer à une série de comptes de charge.


Un même fournisseur peut être contracté par différentes copropriétés et, théoriquement, fournir plusieurs services différents pour une même copropriété:
* toujours un seul compte comptable (copro) par fournisseur
* pouvoir associer une copropriété à un fournisseur (Suppliership) et à un Contrat spécifique (SupplierContract)



### Banques

Les banques sont des fournisseurs (utilisent la même table).
L'identification peut se faire via le code BIC/Swift.
Par convention, on considère que les extraits bancaires, quelle que soit leur source, sont émis par le fournisseur correspondant à la banque.
Corollaire : lors de la création des Copropriété, des Suppliership doivent être ajoutés sur base des comptes bancaires définis pour une Copropriété.

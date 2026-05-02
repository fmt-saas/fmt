## Propriétaires (`Ownership`)

A la création d'un propriétaire, on créée des comptes comptables spécifiques dans le plan comptable 

|type de fonds|identifiant|compte|
|--|--|--|
|fonds de réserve|co_owners_reserve_fund|4100xxxxx|
|fonds de roulement|co_owners_working_fund|4101xxxxx|


Ces comptes sont retrouvés via la codes "operation_assignment" co_owners_reserve_fund et co_owners_working_fund

### Représentant & préférences de communication 

* Un **représentant** est toujours défini pour chaque `Ownership`.
  * Pour un `Ownership` **unique**, le propriétaire unique est automatiquement désigné représentant.
  * Pour un `Ownership` **joint** (indivision), le premier propriétaire encodé est représentant par défaut, mais ce choix peut être modifié manuellement.
* Les **préférences de communication** sont attachées à l’`Ownership` dans son ensemble et peuvent être ajustées par le propriétaire désigné comme représentant.
* Pour les envois **par courrier**, l’adresse utilisée est choisie parmi celles du `representative owner`.



Sur le plan légal, il faut nécessairement un représentant:

* on peut avoir un représentant externe
* ou un représentant parmi les owners  

Les deux sont mutuellement exclusifs.


Dans le cas d'un représentant interne, il est possible qu'il s'agisse d'un couple (marié ou non) en indivision
-> on génère une ligne address_recipient pour les envois postaux

* s'il y a plusieurs owners, il faut pouvoir définir des préférences de communication pour chaque owner (pour le représentant, et pour les autres)
* par défaut seule la ligne de préférence est créée pour le representative_owner
* si, pour un motif de communication, une ligne de préférence renseigne le canal `postal*`, il ne peut pas y avoir d'autre lignes postal* pour ce motif, quelque soit le destinataire (il peut par contre y avoir plusieurs lignes avec des destinataires différents pour le canal `email`)
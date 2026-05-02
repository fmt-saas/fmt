# Comptes bancaires

Les comptes bancaires sont associés aux identités.

Par défaut, une identité permet de préciser un compte bancaire principal. Mais une identité peut être associée à un nombre non limité de comptes bancaires supplémentaires.

Pour faciliter l'encodage, il y a une synchro entre la liste de compte et le compte renseigné dans l'identité : de sorte que le compte de l'identité est toujours présent également dans la liste.

La clé d'unicité des `BankAccount` est le numéro IBAN (il ne peut pas y avoir deux comptes avec le même IBAN / deux Identity ne peuvent jamais avoir un compte identique).

Les comptes bancaires référencés dans le cadre des copropriétés sont des entités spécifiques :

### CondominiumBankAccount

Un CondominiumBankAccount est un compte bancaire, qui comprend des informations supplémentaires liées à une copropriété (`condo_id`).

Il y a 3 types de comptes bancaires (bank_account_type): 'bank_current', 'bank_savings', 'bank_tier'.

* Une copropriété peut avoir plusieurs comptes de chaque type.
* Un compte bancaire permet de faire le lien avec un compte du plan comptable de l'ACP  (accounting_account_id).
* Les comptes bancaire font l'objet d'une séquence perùettant de les distinguer.
* Un des comptes courants peut être marqué comme principal pour les mouvements "achats/vente" (is_primary).
* Un des comptes épargnes peut être marqué comme principal pour les mouvements liés aux fonds de réserve.

### SuppliershipBankAccount

Les ssocie un fournisseur (`Suppliership`) et un compte bancaire spécifique (le compte bancaire à utiliser est susceptible de changer en fonction du type de contrat ou selon le compte bancaire détenu par la copropriété - pour minimiser les frais et les délais). Cette entité consistue un lien entre un compte bancaire (et le fournisseur correspondant) et une copropriété.

Quand on ajoute un fournisseur à une copropriété, par défaut, on ajoute le compte bancaire principal du fournisseur (`is_primary`) dans les `SuppliershipBankAccount`.

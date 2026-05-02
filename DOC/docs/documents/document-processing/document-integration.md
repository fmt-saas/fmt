### Intégration comptable

Tous les documents importés suivent un **workflow de validation**. L’entité `DocumentProcess` en est la concrétisation : c’est uniquement par son intermédiaire que les pièces sont intégrées dans la comptabilité.

#### Création d’une pièce

1. **Upload d’un document**
   - Démarrage d’un `DocumentProcess` et création de l’entité `Document`.
   - Génération de la pièce comptable (`PurchaseInvoice`, `BankStatement`, …).
2. **Facture créée manuellement**
   - Création d’une `Invoice` suivie d’un `DocumentProcess` lié.
   - Upload du document → création du `Document` et synchronisation du `document_json`.

#### Mise à jour des informations

L’entité cible doit stocker toutes les données nécessaires aux traitements ultérieurs :

- **Factures :** écritures comptables et répartition des comptes.
- **Extraits bancaires :** informations de paiement pour la génération SEPA.

Ces données sont centralisées dans le champ `document_json`. Toute modification de la pièce implique de mettre à jour le `DocumentProcess` correspondant : une méthode spécifique permet d’actualiser le JSON en respectant le schéma du `DocumentType`.

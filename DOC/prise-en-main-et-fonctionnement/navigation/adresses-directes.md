# Navigation par URL

L’application permet d’accéder directement à certaines sections ou à des éléments précis en utilisant une **adresse (URL) spécifique**.

Ce mécanisme permet notamment :

- d’ouvrir directement une copropriété
- d’accéder à une section spécifique (comptabilité, lots, AG, etc.)
- de consulter directement un élément précis (facture, exercice, lot…)

Ces liens peuvent être utilisés par exemple dans :

- des e-mails
- des documents
- des favoris du navigateur

Les identifiants présents dans l’adresse permettent à l’application d’ouvrir l’élément correspondant.

------

# Principales routes de navigation

| Adresse                        | Description                         |
| ------------------------------ | ----------------------------------- |
| `/condo`                       | Liste des copropriétés              |
| `/condo/{condo_id}`            | Fiche d’une copropriété             |
| `/condo/{condo_id}/config`     | Configuration de la copropriété     |
| `/condo/{condo_id}/accounting` | Section comptable de la copropriété |

------

# Comptabilité

| Adresse                                                      | Description                    |
| ------------------------------------------------------------ | ------------------------------ |
| `/condo/{condo_id}/accounting/fiscal-year`                   | Liste des exercices comptables |
| `/condo/{condo_id}/accounting/fiscal-year/{fiscal_year_id}`  | Fiche d’un exercice comptable  |
| `/condo/{condo_id}/accounting/general-assembly`              | Liste des assemblées générales |
| `/condo/{condo_id}/accounting/general-assembly/{general_assembly_id}` | Fiche d’une assemblée générale |
| `/condo/{condo_id}/accounting/purchase-invoice`              | Liste des factures d’achat     |
| `/condo/{condo_id}/accounting/purchase-invoice/{purchase_invoice_id}` | Fiche d’une facture d’achat    |

------

# Gestion des copropriétaires et des lots

| Adresse                                            | Description                   |
| -------------------------------------------------- | ----------------------------- |
| `/condo/{condo_id}/ownership`                      | Liste des droits de propriété |
| `/condo/{condo_id}/ownership/{ownership_id}`       | Fiche d’un droit de propriété |
| `/condo/{condo_id}/property-lot`                   | Liste des lots                |
| `/condo/{condo_id}/property-lot/{property_lot_id}` | Fiche d’un lot                |

------

# Principe des identifiants

Dans les adresses ci-dessus :

- **{condo_id}** : identifiant de la copropriété
- **{fiscal_year_id}** : identifiant de l’exercice comptable
- **{general_assembly_id}** : identifiant de l’assemblée générale
- **{purchase_invoice_id}** : identifiant de la facture
- **{ownership_id}** : identifiant du droit de propriété
- **{property_lot_id}** : identifiant du lot

Ces identifiants sont automatiquement utilisés par l’application pour afficher le bon élément.

------

💡 Petite remarque UX/doc (importante) :
dans une doc utilisateur, il est souvent préférable d’utiliser une notation comme :

```
/condo/{condo_id}
```

plutôt que

```
/condo/:condo_id
```

car le `:` est plutôt **un symbole technique de route Angular**, alors que `{}` est **la convention classique de documentation**.


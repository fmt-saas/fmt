#  Gestion des extraits bancaires (`BankStatement`)


Un BankStatement est la pièce justificative représentant un extrait bancaire importé dans le système. Il contient un ensemble de lignes (BankStatementLine) correspondant aux mouvements financiers enregistrés par la banque.
Chaque ligne est associée à une ou plusieurs écritures comptables.

## Bank Statement Schema

Pour modéliser les imports des extraits bancaires, on utilise une **Structure de transaction bancaire unifiée** sous la forme d'un schéma JSON.

### Formats d'import supportés

Les relevés bancaires peuvent être importés dans différents formats.
Les **imports Isabel** constituent le premier cas pris en charge :

- CODA
- CAMT.053
- XLSX *(Isabel uniquement)*
- MT940

### Structure de transaction bancaire unifiée

| Champ | Type | Description |
| ----- | ---- | ----------- |
| `transaction_id` | string | Identifiant unique de la transaction |
| `date` | date-time | Date de l'opération |
| `value_date` | date-time | Date de valeur si différente de `date` |
| `amount` | number | Montant débité ou crédité |
| `currency` | string | Devise (ISO 4217) |
| `balance` | number | Solde du compte après la transaction |
| `counterparty` | string | Tiers associé à l'opération |
| `counterparty_account` | string | IBAN du tiers |
| `counterparty_bic` | string | BIC du tiers |
| `communication` | string | Communication libre ou structurée |
| `reference` | string | Référence de paiement ou de virement |

### Schéma JSON

```json
{
    "$schema": "https://json-schema.org/draft/2020-12/schema",
    "$id": "urn:fmt:json-schema:finance:bank-statement",
    "title": "Unified Bank Statement",
    "type": "object",
    "required": [
        "account",
        "currency",
        "start_balance",
        "end_balance",
        "transactions"
    ],
    "properties": {
        "account": {
            "type": "string"
        },
        "currency": {
            "type": "string",
            "pattern": "^[A-Z]{3}$"
        },
        "start_balance": {
            "type": "number"
        },
        "end_balance": {
            "type": "number"
        },
        "transactions": {
            "type": "array",
            "items": {
                "type": "object",
                "required": [
                    "transaction_id",
                    "date",
                    "amount",
                    "balance"
                ],
                "properties": {
                    "transaction_id": {
                        "type": "string"
                    },
                    "date": {
                        "type": "string",
                        "format": "date-time"
                    },
                    "value_date": {
                        "type": "string",
                        "format": "date-time"
                    },
                    "amount": {
                        "type": "number"
                    },
                    "currency": {
                        "type": "string",
                        "pattern": "^[A-Z]{3}$"
                    },
                    "balance": {
                        "type": "number"
                    },
                    "counterparty": {
                        "type": "string"
                    },
                    "counterparty_account": {
                        "type": "string"
                    },
                    "counterparty_bic": {
                        "type": "string"
                    },
                    "communication": {
                        "type": "string"
                    },
                    "reference": {
                        "type": "string"
                    }
                }
            }
        }
    },
    "additionalProperties": false
}
```


**Exemple de structure JSON pour les extraits bancaires:**

```json
{
	"account_iban": "BE71 0961 2345 6789",
	"statement_number": "0000123456",
	"opening_balance": 1000.00,
	"opening_date": "2024-05-01",
	"closing_balance": 1200.00,
	"closing_date": "2024-05-10",
	"statement_currency": "EUR",
	"bank_bic": "CREGBEBB",
	"account_holder": "FMT solutions",
	"account_type": "current",
	"transactions": [
		{
			"entry_date": "2024-05-05",
			"value_date": "2024-05-05",
			"amount": -150.00,
			"currency": "EUR",
			"transaction_type": "sepa_direct_debit",
			"sequence_number": 123,
			"received_at": "2024-05-05T10:45:00Z",
			"mandate_id": "MANDATE-2023-XYZ",
			"client_reference": "Facture 2024-87",
			"structured_reference": "+++123/4567/89012+++",
			"bank_reference": "987654321",
			"unstructured_reference": "Paiement pour facture avril",
			"counterparty_name": "EDF Luminus",
			"counterparty_iban": "BE23 0910 1111 2222",
			"counterparty_bic": "GEBA BE BB",
			"counterparty_details": "Rue de l'Énergie, Liège",
			"transaction_message": "Paiement automatique"
		}
	]
}
```


### Formats d'import supportés

#### Isabel

Voici les colonnes présentes lors d'un import **XLS Isabel** :

- Account
- Account holder
- Bank
- Account type
- Bic
- Type of account information
- Statement number
- Statement currency
- Opening balance date
- Opening balance
- Closing balance date
- Closing balance
- Closing available balance
- Entry date
- Value date
- Transaction amount
- Transaction currency
- Transaction type
- Client reference
- Structured Reference
- Unstructured Reference
- Bank reference
- Counterparty name
- Counterparty account
- Counterparty bank BIC
- Counterparty data
- Transaction message
- Sequence number
- Reception Date/Time
- stFreeMessage



## Traitement des extraits bancaires

!!! note "Processus détaillé"
    Le processus détaillé du traitement des extraits bancaires et du suivi des paiements est repris dans la section [Suivi de Paiement](/suivis/suivis-de-paiement/suivis-de-paiement/).

Lors de la réception d’un fichier d’extrait bancaire, on ne sait pas a priori s’il s’agit :

- d’un **relevé unique lié à un seul numéro de compte**, ou
- d’un **relevé multiple**, comme dans le cas des fichiers ISABEL contenant les extraits de plusieurs comptes (ex. mandats ACP multiples).



###  Étape 1 : Import d’un fichier d’extraits multiples

Le fichier brut est traité via une entité spécifique : `BankStatementImport`.

Cette entité se charge de :

1. **Extraire les extraits individuels** à partir du fichier global
2. Pour chaque extrait :
   - Créer un objet `DocumentProcess`
   - Générer un fichier virtuel (par exemple XLS) pour audit ou traçabilité
   - Créer un objet `BankStatement` lié



### Étape 2 : Traitement d’un `BankStatement`

Chaque `BankStatement` contient une ou plusieurs lignes (`BankStatementLine`). Ces lignes doivent être **réconciliées** .

Elles peuvent l'être soit avec des pièces en attente de paiement (via un `Funding`), soit de manière manuelle (s'il s'agit d'une charge ou d'un produit à répartir sur l'ensemble de la copropriété; ou s'il s'agit d'un mouvement exceptionnel, à traiter comme une OD).

#### Statuts et validations

- Lors de la création, un `BankStatement` peut être en **état provisoire** (`proforma`)
- Les lignes (`BankStatementLine`) peuvent être liées à un ou plusieurs **paiements**

#### Validation

Lorsqu’un extrait est validé :

- Le `BankStatement` passe à l’état **"posted"**
- Tous les paiements associés sont **validés**
- Des **écritures comptables** correspondantes à chaque ligne sont générées
- Chaque écriture référence la ligne d'extrait source via le champ `origin_object_class`




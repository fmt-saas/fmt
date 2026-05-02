### Document Analysis

L'analyse de contenu des documents est réalisée de deux manières complémentaires :

1. **Analyse documentaire OCR** via des services externes appelés par API (ex: Mindee, Google Vision).
2. **Parsing natif** (par exemple XML → JSON) avec extraction via XPath, ou regex sur le texte brut lorsqu'aucune structure exploitable n'est disponible.



Chaque document est associé à deux informations clés :

- `content_type` : décrit le format ou type MIME du document source (ex: `application/xml`, `application/pdf`).
- `Document Type` : identifie la nature du document (facture, relevé, contrat, etc.) et permet d'associer un **schéma JSON de référence** utilisé pour sa conversion et sa validation.

Chaque document converti contient les métadonnées suivantes :

- `document_json` : le document converti dans un format JSON standardisé.
- `analysis_version` : identifie le service et la version de l'API utilisée pour analyser le document.
- `analysis_json` : contient le résultat brut de l'analyse documentaire.



Tous les documents sont validés en interne contre le schéma JSON avant conversion XML ou automatisation.

Chaque `DocumentType` est strictement lié à un schéma, ce qui garantit la cohérence des données entre les formats.



#### Conversions prises en charge

| Source                              | Cible                       |
| ----------------------------------- | --------------------------- |
| Facture UBL (XML)                   | JSON (urn:purchase-invoice) |
| Facture Mindee (JSON brut)          | JSON (urn:purchase-invoice) |
| Facture JSON (urn:purchase-invoice) | UBL (PEPPOL)                |
| Extrait bancaire (CODA)             | JSON (urn:bank-statement)   |
| Extrait bancaire (ISABEL XLS)       | JSON (urn:bank-statement)   |

### Identification des meta-données 

**Utilisation des numéros d'identification fournisseurs :** 

```
customer_number
contract_number
installation_number
        │
        └── has one or more → meter_number(s)
                 │
                 └── has → meter_ean (électricité/gaz uniquement)
```




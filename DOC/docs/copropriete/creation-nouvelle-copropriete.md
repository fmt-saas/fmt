## Création d'une nouvelle Copropriété (import)

Création d'une ACP:

1) encoder les infos signalétiques
2) encoder les propriétés, propriétaires et locataires
3) encoder les clés statutaires
4) créer des clés de répartition
5) importer un template de chart of accounts

A la création d'une copropriété, on créée des séquences pour : 

* les copropriétaires: `realestate.main.ownership.sequence` [`condo_id`]
* les lots: `realestate.main.property_lot.sequence` [`condo_id`]

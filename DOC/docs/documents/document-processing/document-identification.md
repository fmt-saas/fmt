## Documents Identification

### Types & sous-types de documents
L'étape d'identification consiste à retrouver le `DocumentType` et `DocumentSubtype` du codument, sur base de son content-type et des informations qu'il contient.

La liste des [types et sous-types est reprise dans la partie organisation des documents](/documents/organisation-des-documents/types-de-documents/).



### Origines des pièces

| Origine        | Description                                                  |
| -------------- | ------------------------------------------------------------ |
| **`upload`**   | Ajout manuel d’un document via l’interface par un utilisateur (par exemple, un scan, ou une importation de dossier externe) |
| **`email`**    | Document reçu par email via ton module de récupération (avec lien possible vers le mail source) |
| **`internal`** | Document généré par l'application elle-même (ex. facture créée depuis un module de gestion, PV généré automatiquement, etc.) |
| **`api`**      | Document reçu depuis une API externe (par exemple plateforme de relevé de compteurs) |



### Traitements possibles

| Type de document               | Description / Objectif                                       | Exemples                                            | Traitement attendu                                           |
| ------------------------------ | ------------------------------------------------------------ | --------------------------------------------------- | ------------------------------------------------------------ |
| **Facture fournisseur**        | Facture liée à un service ou contrat régulier ou ponctuel    | Eau, gaz, électricité, nettoyage, assurance, syndic | 🟢 Traitement automatique : création d’une facture fournisseur, tentative de réconciliation comptable |
| **Note de crédit fournisseur** | Note de crédit en lien avec une facture antérieure           | Correction suite à erreur de consommation           | 🟢 Traitement automatique : création d’un avoir et rattachement à la facture initiale |
| **Relevé de compteur**         | Donnée utile à la répartition des charges individuelles ou collectives | Eau, gaz, électricité                               | 🟠 Traitement semi-automatique : extraction de valeurs, rattachement à des compteurs |
| **Bordereau d’entretien**      | Attestation ou compte rendu d’intervention technique         | Chaudière, extincteurs, ascenseur                   | 🟡 Archivage lié au fournisseur + au suivi technique ou au lot concerné |
| **Attestation / certificat**   | Document prouvant une conformité, une assurance ou une intervention réglementaire | Certificat d’assurance, attestation de ramonage     | 🟡 Archivage avec tag “conformité” / “assurance”, contrôle date de validité |
| **Devis**                      | Proposition chiffrée en réponse à une demande                | Travaux, dépannage, rénovation                      | 🟠 Rattachement à un dossier ou un suivi fournisseur / sinistre. Peut déclencher un workflow décisionnel |
| **Bon de commande**            | Commande validée par le syndic ou le conseil                 | Bon signé avec mention du budget                    | 🟡 Archivage, contrôle cohérence avec facture ultérieure      |
| **Bon de livraison**           | Preuve de réalisation d’un service ou livraison de matériel  | PV d’assemblée générale, bon d'entretien signé      | 🟡 Archivage, rapprochement possible avec une commande ou facture |
| **Contrat fournisseur**        | Document contractuel de long terme                           | Contrat de nettoyage, ascenseur, assurance          | 🟢 Archivage structuré avec métadonnées (date de début/fin, indexation fournisseur, relances) |
| **Relance fournisseur**        | Demande de paiement ou suivi d’une facture émise             | Relance facture impayée                             | 🔴 Alerte ou création de tâche manuelle                       |
| **Correspondance**             | Message sans pièce formelle mais à valeur informative        | Infos techniques, réponses à un litige              | 🟠 Archivage, analyse du contexte : création d’un commentaire ou rattachement à un suivi |
| **Document juridique**         | Documents notariaux, assignations, expertises                | Mise en demeure, ordonnance judiciaire              | 🔴 Routage manuel ou supervision renforcée, rattachement à un dossier juridique |
| **Pièce justificative**        | Documents annexes destinés à compléter un dossier            | ticket de caisse, RIB, attestation URSSAF, Kbis     | 🟡 Archivage structuré avec rattachement au fournisseur       |
| **Conditions générales**       | CGV ou annexes contractuelles                                | PDF “Conditions générales”                          | 🔘 Facultatif : archivage ou suppression si non pertinent     |




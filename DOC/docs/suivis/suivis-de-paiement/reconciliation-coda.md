## Réconciliation CODA

Dans les réconciliations de paiements

* s'il y a un match parfait : (VCS + montant), on l'applique en priorité
* sinon on applique la logique du plus vieux dû payé en premiers





Utilisation du compte 58 pour transfert de compte bancaire (au moment où on réconcilie un CODA)



Paiement immédiat qui écrit dans le fournisseur et dans le compte bancaire.

 En Belgique, les extraits bancaires font foi et c’est toujours l’encodage d’un extrait (coda) qui écrit dans le journal "Banque".

* mouvement: comptes bancaire

* contrepartie: le fournisseur, un copropriétaire ou un autre compte 



### Funding d'achat

Lors d'un paiement d'une facture d'achat (via compte bancaire), une réconciliation est nécessaire lors de la réception des extraits bancaires (toujours via le compte courant).

L'objectif des Fundings d'achat est : 

* de permettre de faire la réconciliation entre un CODA et une opération de paiement en attente (lien entre ligne d'extrait, compte fournisseur et facture d'achat)
* de permettre de vérifier le lettrage entre une facture et des paiements bancaires
* de permettre de faire le lien entre l'utilisation d'un fonds de réserve (paiements bancaires) et les charges d'origine (on retrouve tous les Fundings pour lesquels il y a un paiement avec le compte épargne, et on liste toutes les `PaymentLine` correspondantes)



Note : Il y a une obligation de retenue en cas de défaut de paiement, vérification via centrale ONSS

https://www.checkobligationderetenue.be/

https://finances.belgium.be/fr/E-services/check-obligation-de-retenue

 


#### Imputation sur une seule date

Pour chaque ligne, il y a un seul Funding et une seule exécution (montant à verser au fournisseur).



#### Imputation sur un intervalle de dates


Si l'intervalle mentionné dépasse la période de l'exercice en cours, il faut répartir la facture au prorata temporis sur la période concernée et imputer le solde (ce qui est en dehors de la période) en "charge à reporter".

Il faut ensuite une ré-imputation automatique au 1er jour de chaque période suivante concernée, basée sur la même logique.

**Exemple :** 
Exercice comptable du 01/01 au 31/12 de chaque année, décompte trimestriel.

Assurance incendie du 01/11/2023 au 31/10/2024 (366 jours) pour 1.000,00 €

* 1.000,00 € / 366 x 61 = 166,67 € à imputer en charge dans le décompte du 4ème trimestre 2023

* 1.000,00 € / 366 x 305 = 833,33 € à inscrire au bilan (compte 490 - Charges à reporter) par OD automatique

Au 1er trimestre 2024 (Date de début = 01/01/2024 - Date de fin = 31/03/2024) :
833,33 € / 305 x 91 = 248,63 € à ré-imputer en charges par OD automatique

Si on planifie les moments de manière anticipative, on risque des incohérences en cas de modification de l'exercice en cours (périodes à venir)
-> il faut avoir une logique qui génère un état sur base de la configuration actuelle et qui permet de continuer les imputations à chaque nouvelle période en considérant les factures/charges qui ne sont pas entièrement assignées.



| Date     | Opération                             | Compte                      | Débit   | Crédit  |
| -------- | ------------------------------------- | --------------------------- | ------- | ------- |
|          | **Écriture 1 (imputation facture)**   |                             |         |         |
| 01-11-23 | Réception facture assurance 2023–2024 | 613000 - Assurance          | 166.67  |         |
| 01-11-23 | Réception facture assurance 2023–2024 | 490000 - Charges à reporter | 833.33  |         |
| 01-11-23 | Réception facture assurance 2023–2024 | 440000 - Fournisseur        |         | 1000.00 |
|          |                                       |                             |         |         |
|          | **Écriture 2 (extrait bancaire)**     |                             |         |         |
| 15-12-23 | Paiement fournisseur                  | 440000 - Fournisseur        |         | 1000.00 |
| 15-12-23 | Paiement fournisseur                  | 550000 - Banque             | 1000.00 |         |
|          |                                       |                             |         |         |
|          | **Écriture 3 (ouverture Q1 2024)**    |                             |         |         |
| 01-01-24 | Réimputation Q1 2024                  | 613000 - Assurance          | 248.63  |         |
| 01-01-24 | Réimputation Q1 2024                  | 490000 - Charges à reporter |         | 248.63  |
|          |                                       |                             |         |         |
|          | **Écriture 4 (ouverture Q2 2024)**    |                             |         |         |
| 01-04-24 | Réimputation Q2 2024                  | 613000 - Assurance          | 248.63  |         |
| 01-04-24 | Réimputation Q2 2024                  | 490000 - Charges à reporter |         | 248.63  |
|          |                                       |                             |         |         |
|          | **Écriture 5 (ouverture Q3 2024)**    |                             |         |         |
| 01-07-24 | Réimputation Q3 2024                  | 613000 - Assurance          | 251.37  |         |
| 01-07-24 | Réimputation Q3 2024                  | 490000 - Charges à reporter |         | 251.37  |
|          |                                       |                             |         |         |
|          | **Écriture 6 (ouverture Q4 2024)**    |                             |         |         |
| 01-10-24 | Réimputation Q4 2024                  | 613000 - Assurance          | 84.70   |         |
| 01-10-24 | Réimputation Q4 2024                  | 490000 - Charges à reporter |         | 84.70   |






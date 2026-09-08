# Gérer une mutation

Une mutation permet de suivre la vente d'un ou plusieurs lots, les échanges avec le notaire et la régularisation comptable entre le vendeur et l'acquéreur.

## Ouvrir les mutations

Dans le menu principal, ouvrir `Biens`, puis `Mutations`.

La liste permet de retrouver les dossiers en cours et leur statut. Ouvrir une ligne pour consulter ou poursuivre le dossier.

## Avant de commencer

Préparer au minimum :

- la copropriété ;
- le dossier de propriété du vendeur ;
- les lots vendus ;
- la date de la demande ;
- les coordonnées du contact ou de l'étude notariale.

L'acquéreur et la date de l'acte peuvent être complétés plus tard, mais ils sont obligatoires avant la régularisation comptable.

## Parcours conseillé

### 1. Créer et ouvrir le dossier

Créer une mutation, sélectionner le vendeur et les lots concernés, puis enregistrer. Utiliser `Ouvrir` lorsque les informations de base sont complètes.

À l'ouverture, l'application prépare les textes du courrier et actualise les soldes de fonds, les appels planifiés et les arriérés du vendeur.

### 2. Préparer le premier courrier notarial

Vérifier la section `3.94 §1` :

- soldes des fonds et arriérés ;
- appels de fonds ;
- procédures ou dettes à signaler ;
- PV d'assemblée, décomptes, bilan et autres pièces jointes ;
- frais de dossier et informations complémentaires.

Ajouter les destinataires dans `Contacts` et les documents à joindre dans `Pièces jointes`. Le bouton `Prévisualiser l'e-mail` permet de contrôler le courrier avant l'envoi.

`Envoyer un e-mail` crée le PDF, joint les documents sélectionnés et place le message dans la file d'envoi. Le premier envoi depuis le statut `Ouvert` fait passer le dossier à `Documents vendeur envoyés`.

### 3. Compléter le second volet et confirmer la vente

Après réception des informations du notaire :

1. utiliser `Confirmer` pour passer à l'étape suivante ;
2. vérifier la section `3.94 §2`, actualiser les informations financières et envoyer le courrier correspondant si nécessaire ;
3. renseigner le nouveau propriétaire et la date de transfert dans `3.94 §3` ;
4. vérifier soigneusement les lots transférés ;
5. utiliser `Confirmer la vente`.

Le dossier passe alors à `Vente confirmée`. La date de transfert correspond à la date de l'acte : le jour de cette date appartient à l'acquéreur.

### 4. Préparer la comptabilité

Depuis une vente confirmée, utiliser `Préparer la comptabilité`. L'application crée une fiche `Régularisation comptable` et place la mutation en `Régularisation comptable en cours`.

Ouvrir cette fiche, puis suivre cet ordre :

1. choisir ou vérifier la `Date comptable` ;
2. cliquer sur `Générer les lignes` ;
3. contrôler les lignes de fonds de roulement, d'appels de fonds et de décomptes ;
4. si nécessaire, ouvrir une ligne et adapter le `Montant retenu` ;
5. cliquer sur `Générer les OD` ;
6. contrôler les opérations diverses pro forma ;
7. cliquer sur `Valider`.

Un montant retenu positif crédite le vendeur et débite l'acquéreur. Un montant négatif fait l'inverse.

> Relancer `Générer les lignes` remplace toutes les lignes et efface les adaptations manuelles du montant retenu.

## Ce que fait la validation

La validation :

- comptabilise les opérations diverses ;
- crée les montants à payer ou à rembourser associés ;
- attribue les lots à l'acquéreur à partir de la date de l'acte ;
- termine l'historique du vendeur la veille ;
- recalcule les appels actifs concernés ;
- prépare les courriers du vendeur et de l'acquéreur ;
- programme les e-mails et les exports postaux selon leurs préférences de communication ;
- clôture la régularisation et la mutation.

Le traitement des e-mails et exports peut continuer en arrière-plan alors que le dossier est déjà affiché comme clôturé. Après validation, vérifier la section `Correspondances`, la file d'envoi et, le cas échéant, la tâche d'export postal.

## En cas d'erreur

Les blocages les plus fréquents sont :

- fonds de roulement sans compte ou sans clé de répartition ;
- lot sans quote-part dans la clé du fonds ;
- période comptable fermée ou absente pour la date choisie ;
- journal d'opérations diverses ou compte bancaire principal manquant ;
- compte comptable du vendeur ou de l'acquéreur manquant ;
- représentant ou préférence de communication incomplet.

Corriger la configuration, puis reprendre l'étape qui a échoué.

## Déverrouiller une mutation

Le bouton `Déverrouiller` est disponible pendant la régularisation ou après clôture. Il tente d'annuler les OD, de rendre les lots au vendeur et de restaurer l'historique de propriété.

Cette action peut être refusée si les périodes ont été clôturées, si les écritures ne peuvent plus être annulées ou si les lots ont été réaffectés depuis. Comme elle touche la comptabilité et l'historique des lots, contrôler le résultat avec le comptable avant de poursuivre.

Pour les règles de calcul et les détails techniques, consulter la [référence DEV/PO](../../copropriete/mutations-transferts-de-propriete.md).

# Factures d'achat

## Où trouver cet écran ?

Fiche `Copropriété` > `Comptabilité` > `Factures d'achat`

## À quoi sert cet écran ?

La vue `Factures d'achat` sert à traiter les factures fournisseurs d'une copropriété : compléter les informations, vérifier les montants, valider puis intégrer la facture à la comptabilité.

## Vue liste

La vue `Factures (traitement)` permet de suivre les factures en cours de traitement documentaire.

Elle donne accès aux factures importées, complétées, validées ou intégrées.

## Fiche Facture d'achat

La fiche contient notamment :

* `Lignes de facture`
* `Utilisations des fonds`
* `Écritures comptables`
* `Suivi paiements`
* `E-mail`
* `Tâches`

Champs importants :

* `Fournisseur`
* `Contrat fournisseur`
* `Numéro de facture`
* `Compte bancaire fournisseur`
* `Compte bancaire copropriété`
* `Date d'émission`
* `Exercice comptable`
* `période comptable`
* `Date de comptabilisation`
* `Montant TTC`

## Actions principales

* `Importer une facture`
* `Marquer completée`
* `Marquer valide`
* `Intégrer`
* `Marquer non complété`
* `Marquer non validé`
* `Supprimer`
* `Annuler`
* `Débloquer`

## Parcours conseillé

1. Importer ou ouvrir la facture.
2. Vérifier le fournisseur, le contrat fournisseur et le compte bancaire.
3. Compléter les lignes de facture.
4. Vérifier l'exercice comptable, la période comptable et la date de comptabilisation.
5. Marquer la facture comme complétée.
6. Marquer la facture comme valide.
7. Intégrer la facture à la comptabilité.

## Contrôle des documents en double

Lorsque la facture est marquée comme complétée, son fichier source est comparé à tous les documents déjà importés à l'aide de son hash SHA-256.

Si le même fichier existe déjà, la facture est bloquée comme doublon, même si le document existant appartient à une autre copropriété ou si son traitement a été annulé ou supprimé. La facture pro forma et son traitement sont retirés, tandis que le document importé reste conservé pour la traçabilité.

Ce contrôle identifie les fichiers dont le contenu binaire est strictement identique. Un PDF régénéré ou modifié peut avoir un hash différent, même si son contenu visible paraît identique.

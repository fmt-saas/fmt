## Plan comptable

La loi belge a défini un plan comptable minimum normalisé "**PCMN**"( version retravaillée par 1000 millièmes en annexes).

### Paramétrage des comptes


* 100000 – Fonds de roulement 
* 160000 – Fonds de réserve.

* Comptes de charges (clé de répartition, répartition propriétaire/locataire).
  

- Ajout d’une clé de répartition pour les mutations :
    - Automatisation du calcul des quotes-parts dans les courriers de réponse aux notaires.
    - Transfert de fonds après une vente.


Pour le plan comptable d'une copropriété, on doit pouvoir associer un compte à un copropriétaire, à un fournisseur, à un compte bancaire, à un fonds de réserve, fonds de roulement, …

On utilise une logique basée sur le rôle du compte (`operation_assignment`), sur le code de compte (max. 8 chiffres), et sur l'assignation de codes aux entités susceptibles d'être liées à un compte.

Un code de compte comptable reprend le code centralisateur en préfixe, et le code de l'entité visée en suffixe

Exemples: 

* 4100        co_owners_reserve_fund
* 4101        co_owners_working_fund
* 4400        suppliers 

A partit de là, une série comptes peuvent être créés en utilisant les comptes parents comme "templates"

* 4100xxxxx
* 4101xxxxx
* 440xxxx

**Un seul compte comptable par lot et copropriété**
 Toutes les écritures (appel de charges, paiements, remboursements, etc.) sont centralisées sur **un seul compte comptable** par lot, même en présence de plusieurs propriétaires solidaires.
 
Les comptes spéciaux sont créés de manière assistées ou automatique, à la création d'entités spécifiques.

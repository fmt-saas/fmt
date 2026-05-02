## Syndic / Agence (`ManagingAgent`)

Le managing agent est :

* soit l'organisation [1] pour les instances LOCALES
(dans ce cas les données doivent être synchronisées en cas de modification entre Organisation et ManagingAgent)

* soit un des managing agent référencés, pour l'instance MASTER
  (dans ce cas, synchro entre ManagingAgent et l'identité liée)

=> il faut un param de config pour savoir de quel type d'instance il s'agit

## **Gestion des syndics (`ManagingAgent`) dans l’ACP**

La gestion des syndics dans le système vise à modéliser **l’ensemble des acteurs et relations contractuelles** permettant l’administration d’une copropriété (ACP).
 Elle doit :

- Identifier clairement **qui est ou a été syndic** d’une ACP.
- Suivre **l’historique des mandats** et des **contrats** associés.
- Permettre de représenter aussi bien les syndics **bénévoles** que **professionnels**.
- Garantir la **traçabilité légale** en cas de contrôle ou de litige.





### **Entités principales**



#### 1. ManagingAgent

Représente **l’entité (personne physique ou morale)** qui assure la fonction de syndic.

- Peut être un **copropriétaire bénévole** (`owner`) ou un **professionnel** (`professional`).
- Hérite de `purchase\supplier\Supplier` pour bénéficier des fonctionnalités liées à un fournisseur (coordonnées, facturation, paiements).
- Peut être liée à plusieurs copropriétés au fil du temps.
- Conserve un historique des relations via les contrats et mandats.

**Champs clés** :

- `agent_identity_type` : nature de l’agent (`owner`, `professional`).
- `identity_id` : lien vers l’identité (personne physique ou morale).
- `condominiums_ids` : copropriétés gérées (vue historique ou actuelle).
- `management_contracts_ids` : liste des contrats passés avec cette entité.



#### 2. ManagingAgentContract

Représente **le contrat de gestion** liant une ACP et un syndic.

- Définit les **conditions juridiques et financières**.
- Peut couvrir un ou plusieurs mandats successifs.
- Permet de stocker la **durée prévue** et les **termes contractuels**.
- Ne reflète pas forcément la période effective d’exercice du syndic (c’est le mandat qui la définit).

**Champs clés** :

- `condo_id` : copropriété concernée.
- `managing_agent_id` : syndic contractant.
- `date_from` / `date_to` : période prévue de validité.
- `indexation_rate` : taux d’indexation annuel.
- `max_duration` : durée maximale prévue par contrat.
- `is_active` : état actuel du contrat.



#### 3. ManagingAgentMandate

Représente **la nomination effective** du syndic par une décision de l’AG ou du juge.

- Définit **la période réelle** pendant laquelle le syndic exerce.
- Permet de préciser **le mode de nomination** (`elected`, `court_appointed`, `provisional`).
- Peut être lié à un contrat existant, ou fonctionner sans (cas d’un syndic bénévole sans convention écrite).
- Inclut le **motif de fin** (`termination_reason`) et la date de fin réelle.

**Champs clés** :

- `condo_id` : copropriété concernée.
- `managing_agent_id` : syndic nommé.
- `contract_id` : contrat associé (optionnel).
- `date_from` / `date_to` : début et fin du mandat.
- `appointment_type` : mode de nomination.
- `termination_reason` : cause de fin du mandat.
- `is_active` : statut actuel du mandat.

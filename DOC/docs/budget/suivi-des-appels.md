## Suivi des appels 

### Financements (`Funding`)

Les financements (`Funding`) sont associés aux exécutions (`FundRequestExection`) et permettent d'établir un récapitulatif de l'appel auprès des copropriétaires.

Ils permettent d'identifier si des montants ont déjà reçus: on les utilise pour récapituler les éventuels appels précédents qui auraient été corrigés. Dans le cas contraire, il n'est pas nécessaire de faire mention des éventuels appels (corrigés) précédents.



#### Logique

* Un financement (`Funding`) issu d'une `FundRequest`est toujours censé être associé à une `FundRequestExecution` (qui se comporte comme une facture).
Corollaire: Il peut y avoir plusieurs financements pour un même `FundRequest`, mais un seul par execution et par owner.

* Les Fundings sont générés au moment de la confirmation d'une exécution.


* Lorsqu'on créée une nouvelle exécution, les éventuels fundings non vides existants associés à un propriétaire sont associés à la nouvelle exécution, et le montant appelé est ajusté en fonction des montants de ces fundings (à compléter ou à rembourser).
* Les financements (`Funding`) sont créés lors de l'activation d'une exécution d'appel de fonds (et sont associés à l'exécution en question).
* En cas d'annulation d'une exécution, on marque les fundings associés comme annulés. Si certains fundings ont déjà des paiements, ceux-ci sont conservés (ils sont associés à un ownership), et on les détache de l'exécution annulée et de leur funding initial. 

* Lors de l'activation d'une nouvelle exécution, on identifie les Payments non liés à un funding, et on décompte le total correspondant du montant appelé (pour l'exécution concernée).



### Communication Structurée / VCS

Pour identifier les paiements dans les extraits (CODA), une **communication structurée VCS** est utilisée, suivant la logique ci-dessous.

Le but du VCS est de pouvoir réconcilier les virements reçus sur un compte donné avec le Funding / ExecutionLine correspondante.

Postulats : 

* tous les financements (Funding) pour appel de fonds à destination d'un même copropriétaire utilisent la même communication structurée (VCS).

* une ACP peut disposer de plusieurs comptes bancaires (très souvent le cas)
* un propriétaire peut posséder des lots dans des ACP différentes
* un extrait est nécessairement lié à un compte bancaire (qui est nécessairement lié à une ACP)

Les situations anticipées :

a. un propriétaire n'utilise pas de communication (dans ce cas, pas de gestion automatique)
b. un propriétaire se trompe de communication (utilise une ancienne)
c. un propriétaire utilise une communication d'une autre ACP
d. un propriétaire utilise une bonne communication, mais avec un montant incorrect
e. un propriétaire n'utilise pas de communication mais un montant correct (dans ce cas, pas de gestion automatique)  
Réconciliation avec la **stratégie VCS** : **+++CCC/CCCO/OOOXX+++**

> C : Condominium (ACP/Copropriété)
> O : Ownership
> X : Contrôle



a. /
b. on identifie le financement via le code propriétaire et le montant
c. on peut éventuellement retrouver le propriétaire, en retrouvant l'instance correspondant au code de l'ACP (via l'instance MASTER), et en l'interrogeant pour connaître l'identity correspondant à ce code (c'est une opération complexe, mais possible)
d. on ajoute le montant au(x) financement(s) les plus anciens 
e. /

Pour le point d, on automatise, en suivant préconisation légale d’apurer toujours les montants les plus anciens d’abord. Si nécessaire, il reste possible de modifier un lettrage manuellement via les comptes comptables.

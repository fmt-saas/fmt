<?php

use realestate\governance\AssemblyTemplate;
use realestate\governance\AssemblyItemTemplate;

['orm' => $orm] = eQual::inject(['orm']);
$events = $orm->disableEvents();


/* =========================================================
 * AG ordinaire
 * =======================================================*/

$assemblyTemplate = AssemblyTemplate::create([
    'name'           => 'Assemblée générale ordinaire',
    'assembly_type'  => 'statutory',
    'description'    => "Assemblée annuelle obligatoire, convoquée au moins une fois par an. Elle traite les points récurrents comme l’approbation des comptes, le budget, la reconduction du syndic, le rapport sur les contentieux et la désignation du commissaire aux comptes. C’est le cadre standard de gestion annuelle d’une ACP.",
    'lang'           => 'fr'
])->first();

AssemblyItemTemplate::create([
    'order'                   => 1,
    'has_parent_group'        => false,
    'assembly_template_id'    => $assemblyTemplate['id'],
    'name'                    => 'Constitution du bureau de séance',
    'code'                    => 'assembly_officers_appointment',
    'has_vote_required'       => true,
    'majority'                => 'absolute',
    'helper'                  => 'Art. 3.87 §5 et §10 du Code civil – Désignation obligatoire d’un président et d’un secrétaire de séance.',
    'description_call'        => "L’article 3.87, §5 du Code civil énonce que : « L'assemblée générale est présidée par un copropriétaire. »<br /> Proposition soumise au vote : désigner un copropriétaire comme président de l’assemblée générale des copropriétaires.<br />L’article 3.87, §10 du Code civil explique que l’assemblée générale désigne le secrétaire lors de l’ouverture de la séance.",
    'description_ballot'      => "Décision à prendre en vue de désigner le/la Président(e) et le/la secrétaire de séance.",
    'description_minutes'     => "Après en avoir débattu, l’Assemblée Générale décide, à la majorité requise, de nommer comme Président(e) de séance: <br/> et comme Secrétaire de séance: ",
    'lang'                    => 'fr'
])->first();

AssemblyItemTemplate::create([
    'order'                   => 2,
    'has_parent_group'        => false,
    'assembly_template_id'    => $assemblyTemplate['id'],
    'name'                    => "Information à donner sur l’état du ou des contentieux en cours",
    'code'                    => 'contentious_cases_info',
    'helper'                  => "Art. 3.87 §9 du Code civil – Le syndic rend compte de sa gestion à l’AG, y compris les contentieux en cours.",
    'description_call'        => "Communication",
    'description_minutes'     => "L’Assemblée Générale, après avoir entendu le rapport du syndic et obtenu toutes les explications nécessaires, à la majorité requise, prend acte des informations données par le syndic et entérine les actions entreprises pour mener à bien la/les procédure(s).",
    'lang'                    => 'fr'
])->first();

AssemblyItemTemplate::create([
    'is_group'                => true,
    'has_parent_group'        => false,
    'order'                   => 3,
    'assembly_template_id'    => $assemblyTemplate['id'],
    'name'                    => "Approbation des comptes arrêtés",
    'code'                    => 'accounts_approval',
    'helper'                  => "Art. 3.91 §2, 1° du Code civil – L’AG statue sur l’approbation des comptes présentés par le syndic.",
    'description_call'        => "Approbation des comptes arrêtés de la copropriété.",
    'description_minutes'     => "L'Assemblée Générale, après avoir examiné les documents joints à la convocation et en avoir délibéré, à la majorité requise, approuve/n'approuve pas/surssoit à l'approbation des comptes présentés par le syndic arrêtés à la date du ...",
    'lang'                    => 'fr'
])->first();

AssemblyItemTemplate::create([
    'order'                   => 4,
    'has_parent_group'        => false,
    'assembly_template_id'    => $assemblyTemplate['id'],
    'name'                    => "Présentation du budget de l’exercice",
    'code'                    => 'budget_approval',
    'has_vote_required'       => true,
    'majority'                => 'absolute',
    'helper'                  => "Art. 3.91 §2, 2° du Code civil – L’AG statue sur le budget prévisionnel annuel.",
    'description_call'        => "Présentation du budget de l’exercice annuel.",
    'description_ballot'      => "Approbation du budget de l'exercice et autorisation de procéder aux appels provisionnels.",
    'description_minutes'     => "L'Assemblée Générale, après avoir examiné le projet de budget joint à la convocation et en avoir délibéré, à la majorité requise, fixe le budget de l'exercice à la somme de ... €.<br />Elle autorise le syndic à procéder aux appels provisionnels en proportion du budget voté et des clés de répartition prévues dans l'acte de base de la copropriété.<br />Ce budget vaut également pour les appels provisionnels de l’exercice suivant en l’absence de nouvelle décision.",
    'lang'                    => 'fr'
])->first();

AssemblyItemTemplate::create([
    'order'                   => 5,
    'has_parent_group'        => false,
    'assembly_template_id'    => $assemblyTemplate['id'],
    'name'                    => "Adaptation éventuelle du fonds de roulement",
    'code'                    => 'working_fund_adjustment',
    'has_vote_required'       => true,
    'majority'                => 'absolute',
    'helper'                  => "Bonne pratique – Ajustement régulier recommandé pour assurer la trésorerie de la copropriété.",
    'description_call'        => "...",
    'description_ballot'      => "...",
    'description_minutes'     => "",
    'lang'                    => 'fr'
])->first();

AssemblyItemTemplate::create([
    'order'                   => 6,
    'has_parent_group'        => false,
    'assembly_template_id'    => $assemblyTemplate['id'],
    'name'                    => "Nomination du(des) commissaire(s) aux comptes",
    'code'                    => 'appointment_auditors',
    'has_vote_required'       => true,
    'majority'                => 'absolute',
    'helper'                  => "Art. 3.91 §2, 6° du Code civil – L’AG statue sur le contrôle des comptes (obligatoire sauf dispense statutaire).",
    'description_call'        => "...",
    'description_ballot'      => "...",
    'description_minutes'     => "",
    'lang'                    => 'fr'
])->first();

AssemblyItemTemplate::create([
    'order'                   => 7,
    'has_parent_group'        => false,
    'assembly_template_id'    => $assemblyTemplate['id'],
    'name'                    => "Liste des fournisseurs réguliers",
    'code'                    => 'supplier_list_approval',
    'majority'                => 'absolute',
    'helper'                  => "Usage courant – Recommandé pour validation et transparence sur la reconduction des prestataires habituels.",
    'description_call'        => "...",
    'description_ballot'      => "...",
    'description_minutes'     => "...",
    'lang'                    => 'fr'
])->first();

AssemblyItemTemplate::create([
    'order'                   => 8,
    'has_parent_group'        => false,
    'assembly_template_id'    => $assemblyTemplate['id'],
    'name'                    => "Reconduction du mandat de Syndic",
    'code'                    => 'syndic_mandate_renewal',
    'majority'                => 'absolute',
    'helper'                  => "Art. 3.88 §1 et §2 du Code civil – L’AG doit statuer sur le renouvellement ou le changement de syndic à échéance.",
    'description_call'        => "...",
    'description_ballot'      => "...",
    'description_minutes'     => "",
    'lang'                    => 'fr'
])->first();

AssemblyItemTemplate::create([
    'order'                   => 9,
    'has_parent_group'        => false,
    'assembly_template_id'    => $assemblyTemplate['id'],
    'name'                    => "Travaux, entretiens à prévoir et financement",
    'code'                    => 'work_decision',
    'majority'                => 'absolute',
    'helper'                  => "Art. 3.91 §2, 5° du Code civil – L’AG peut décider de travaux nécessaires ou imposés.",
    'description_call'        => "...",
    'description_ballot'      => "...",
    'description_minutes'     => "",
    'lang'                    => 'fr'
])->first();

AssemblyItemTemplate::create([
    'order'                   => 10,
    'has_parent_group'        => false,
    'assembly_template_id'    => $assemblyTemplate['id'],
    'name'                    => "Financement des travaux",
    'code'                    => 'work_funding_mode',
    'majority'                => 'absolute',
    'helper'                  => "Bonne gestion – Choix du mode de financement selon le type de travaux et les liquidités disponibles.",
    'description_call'        => "...",
    'description_ballot'      => "...",
    'description_minutes'     => "",
    'lang'                    => 'fr'
])->first();

AssemblyItemTemplate::create([
    'order'                   => 11,
    'has_parent_group'        => false,
    'assembly_template_id'    => $assemblyTemplate['id'],
    'name'                    => "Information sur les contrats actifs",
    'code'                    => 'active_contracts_info',
    'majority'                => 'absolute',
    'helper'                  => "Usage courant – Permet la transparence sur les engagements contractuels de la copropriété.",
    'description_call'        => "...",
    'description_ballot'      => "...",
    'description_minutes'     => "",
    'lang'                    => 'fr'
])->first();

AssemblyItemTemplate::create([
    'order'                   => 12,
    'has_parent_group'        => false,
    'assembly_template_id'    => $assemblyTemplate['id'],
    'name'                    => "Mandat au syndic pour modifier les contrats",
    'code'                    => 'contract_modification_mandate',
    'majority'                => 'absolute',
    'helper'                  => "Usage courant – Permet d’anticiper les renouvellements ou résiliations contractuelles sans nouvelle AG.",
    'description_call'        => "...",
    'description_ballot'      => "...",
    'description_minutes'     => "",
    'lang'                    => 'fr'
])->first();

AssemblyItemTemplate::create([
    'order'                   => 13,
    'has_parent_group'        => false,
    'assembly_template_id'    => $assemblyTemplate['id'],
    'name'                    => "Mandat au syndic pour changer de fournisseur énergie",
    'code'                    => 'energy_supplier_mandate',
    'majority'                => 'absolute',
    'helper'                  => "Usage courant – Optimisation des coûts d’énergie pour la copropriété.",
    'description_call'        => "...",
    'description_ballot'      => "...",
    'description_minutes'     => "",
    'lang'                    => 'fr'
])->first();

AssemblyItemTemplate::create([
    'order'                   => 14,
    'has_parent_group'        => false,
    'assembly_template_id'    => $assemblyTemplate['id'],
    'name'                    => "Montant des marchés à partir duquel mise en concurrence est requise",
    'code'                    => 'tender_threshold',
    'majority'                => '2_3',
    'helper'                  => "Art. 3.89 §5, 2° du Code civil – L’AG fixe ce seuil pour encadrer les dépenses importantes.",
    'description_call'        => "...",
    'description_ballot'      => "...",
    'description_minutes'     => "",
    'lang'                    => 'fr'
])->first();

AssemblyItemTemplate::create([
    'order'                   => 15,
    'has_parent_group'        => false,
    'assembly_template_id'    => $assemblyTemplate['id'],
    'name'                    => "Montant des marchés/travaux avec cahier des charges",
    'code'                    => 'specification_threshold',
    'majority'                => '2_3',
    'helper'                  => "Bonne pratique – Utilisation d’un cahier des charges pour les marchés complexes ou supérieurs à un seuil.",
    'description_call'        => "...",
    'description_ballot'      => "...",
    'description_minutes'     => "",
    'lang'                    => 'fr'
])->first();

AssemblyItemTemplate::create([
    'order'                   => 16,
    'has_parent_group'        => false,
    'assembly_template_id'    => $assemblyTemplate['id'],
    'name'                    => "Fixation d’une quinzaine pour les prochaines AG ordinaires",
    'code'                    => 'setting_ag_period',
    'majority'                => 'absolute',
    'helper'                  => "Facilite l’organisation des prochaines AG et la régularité annuelle.",
    'description_call'        => "...",
    'description_ballot'      => "...",
    'description_minutes'     => "",
    'lang'                    => 'fr'
])->first();

AssemblyItemTemplate::create([
    'order'                   => 17,
    'has_parent_group'        => false,
    'assembly_template_id'    => $assemblyTemplate['id'],
    'name'                    => "Contrats dont la durée excède celle du mandat de syndic",
    'code'                    => 'long_term_contracts',
    'majority'                => 'absolute',
    'helper'                  => "Art. 3.89 §4 du Code civil – L’AG doit approuver tout contrat dépassant la durée du mandat du syndic.",
    'description_call'        => "...",
    'description_ballot'      => "...",
    'description_minutes'     => "",
    'lang'                    => 'fr'
])->first();

AssemblyItemTemplate::create([
    'order'                   => 18,
    'has_parent_group'        => false,
    'assembly_template_id'    => $assemblyTemplate['id'],
    'name'                    => "Mise à jour du Règlement d’Ordre Intérieur",
    'code'                    => 'roi_update',
    'majority'                => 'absolute',
    'helper'                  => "Art. 3.93 §3 du Code civil – Obligation de mise à jour du ROI pour conformité avec la loi de 2018.",
    'description_call'        => "...",
    'description_ballot'      => "...",
    'description_minutes'     => "",
    'lang'                    => 'fr'
])->first();

AssemblyItemTemplate::create([
    'order'                   => 19,
    'has_parent_group'        => false,
    'assembly_template_id'    => $assemblyTemplate['id'],
    'name'                    => "Détermination des sanctions pour non-paiement des charges",
    'code'                    => 'sanctions_unpaid_dues',
    'majority'                => 'absolute',
    'helper'                  => "Bonne pratique – Clause dissuasive pour retards de paiement, souvent statutaire.",
    'description_call'        => "...",
    'description_ballot'      => "...",
    'description_minutes'     => "",
    'lang'                    => 'fr'
])->first();

AssemblyItemTemplate::create([
    'order'                   => 20,
    'has_parent_group'        => false,
    'assembly_template_id'    => $assemblyTemplate['id'],
    'name'                    => "Points inscrits à l’ordre du jour à la demande d’un copropriétaire",
    'code'                    => 'owner_proposed_items',
    'majority'                => 'absolute',
    'helper'                  => "Art. 3.87 §3 du Code civil – Tout copropriétaire peut demander l’ajout de points à l’ordre du jour.",
    'description_call'        => "...",
    'description_ballot'      => "...",
    'description_minutes'     => "",
    'lang'                    => 'fr'
])->first();

AssemblyItemTemplate::create([
    'order'                   => 21,
    'has_parent_group'        => false,
    'assembly_template_id'    => $assemblyTemplate['id'],
    'name'                    => "Remplacement de la concierge",
    'code'                    => 'concierge_replacement',
    'majority'                => 'absolute',
    'helper'                  => "Point occasionnel lié à la gestion du personnel – soumis à vote si changement prévu.",
    'description_call'        => "...",
    'description_ballot'      => "...",
    'description_minutes'     => "",
    'lang'                    => 'fr'
])->first();



/* =========================================================
 * AG constituante
 * =======================================================*/

$assemblyTemplate = AssemblyTemplate::create([
    'name'           => 'Assemblée générale constituante',
    'assembly_type'  => 'constitutive',
    'description'    => "Première assemblée générale d’une copropriété après l’acte de base ou la division d’un immeuble. Elle sert à rendre l’ACP opérationnelle : fixation de l’exercice comptable, désignation du syndic, approbation d’un premier budget, constitution des fonds (roulement et réserve) et adoption de règles de fonctionnement. À utiliser uniquement si l’ACP n’a jamais été formellement activée.",
    'lang'           => 'fr'
])->first();

$assemblyItemGroup = AssemblyItemTemplate::create([
    'is_group'                => true,
    'has_parent_group'        => false,
    'order'                   => 1,
    'assembly_template_id'    => $assemblyTemplate['id'],
    'name'                    => 'Mise en place de la gouvernance',
    'code'                    => 'governance_setup',
    'helper'                  => "Regroupe les décisions relatives à l'organisation de l’ACP : désignation du syndic, constitution du conseil de copropriété, et composition du bureau de séance.",
    'description_call'        => "Section dédiée à l'organisation initiale de la copropriété.",
    'description_minutes'     => "Les décisions relatives à la mise en place des organes de gestion de l’ACP ont été prises comme suit :",
    'lang'                    => 'fr'
])->first();

$assemblyItemGroup = AssemblyItemTemplate::create([
    'is_group'                => true,
    'has_parent_group'        => false,
    'order'                   => 2,
    'assembly_template_id'    => $assemblyTemplate['id'],
    'name'                    => 'Configuration comptable',
    'code'                    => 'accounting_configuration',
    'helper'                  => "Permet de définir les paramètres de fonctionnement comptable de base pour la copropriété : exercice, périodicité des provisions et des décomptes, et choix du compte bancaire.",
    'description_call'        => "Paramétrage initial du fonctionnement comptable de l’ACP.",
    'description_minutes'     => "Les modalités de gestion comptable ont été fixées comme suit :",
    'lang'                    => 'fr'
])->first();

AssemblyItemTemplate::create([
    'has_parent_group'        => true,
    'parent_group_id'         => $assemblyItemGroup['id'],
    'order'                   => 1,
    'assembly_template_id'    => $assemblyTemplate['id'],
    'name'                    => 'Exercice comptable de la copropriété',
    'code'                    => 'fiscal_year_definition',
    'has_vote_required'       => true,
    'majority'                => 'absolute',
    'helper'                  => "Bonne pratique en AG constitutive : permet de fixer officiellement les dates de l’exercice comptable.",
    'description_call'        => "Il est proposé de fixer l’exercice comptable du 1er janvier au 31 décembre de chaque année et de fixer le premier exercice comptable du ... au 31 décembre",
    'description_ballot'      => "Décision à prendre en vue de désigner les dates d'Exercice Comptable.",
    'description_minutes'     => "L’Assemblée Générale décide, à la majorité requise, de valider les dates d'Exercice Comptable",
    'lang'                    => 'fr'
])->first();

AssemblyItemTemplate::create([
    'has_parent_group'        => true,
    'parent_group_id'         => $assemblyItemGroup['id'],
    'order'                   => 2,
    'assembly_template_id'    => $assemblyTemplate['id'],
    'name'                    => 'Périodicité des décomptes',
    'code'                    => 'statement_periodicity',
    'has_vote_required'       => true,
    'majority'                => 'absolute',
    'helper'                  => "Usage – permet de fixer la fréquence de communication des décomptes de charges aux copropriétaires.",
    'description_call'        => "Proposition d’opter pour un décompte annuel.",
    'description_ballot'      => "Décision à prendre en vue de désigner la périodicté des décomptes.",
    'description_minutes'     => "L’Assemblée Générale décide, à la majorité requise, de valider la périodicité des décomptes.",
    'lang'                    => 'fr'
])->first();

AssemblyItemTemplate::create([
    'has_parent_group'        => true,
    'parent_group_id'         => $assemblyItemGroup['id'],
    'order'                   => 3,
    'assembly_template_id'    => $assemblyTemplate['id'],
    'name'                    => 'Périodicité des provisions',
    'code'                    => 'provision_frequency',
    'has_vote_required'       => true,
    'majority'                => 'absolute',
    'helper'                  => "Usage – détermine la fréquence des appels de provisions : mensuelle, trimestrielle, etc.",
    'description_call'        => "Proposition d’établir des provisions mensuelles.",
    'description_ballot'      => "Décision à prendre en vue de désigner la périodicté des provisions.",
    'description_minutes'     => "L’Assemblée Générale décide, à la majorité requise, de valider la périodicité des provisions.",
    'lang'                    => 'fr'
])->first();

$assemblyItemGroup = AssemblyItemTemplate::create([
    'is_group'                => true,
    'has_parent_group'        => false,
    'order'                   => 3,
    'assembly_template_id'    => $assemblyTemplate['id'],
    'name'                    => 'Approbation budgétaire initiale',
    'code'                    => 'initial_budget_approval',
    'helper'                  => "Regroupe les décisions relatives à l’établissement du premier budget de fonctionnement et à la constitution des fonds obligatoires.",
    'description_call'        => "Vote du budget initial de l’ACP et constitution des réserves financières.",
    'description_minutes'     => "Les décisions suivantes ont été prises concernant le budget initial et les fonds de fonctionnement :",
    'lang'                    => 'fr'
])->first();

AssemblyItemTemplate::create([
    'has_parent_group'        => true,
    'parent_group_id'         => $assemblyItemGroup['id'],
    'order'                   => 1,
    'assembly_template_id'    => $assemblyTemplate['id'],
    'name'                    => 'Budget de fonctionnement',
    'code'                    => 'initial_operating_budget',
    'has_vote_required'       => true,
    'majority'                => 'absolute',
    'helper'                  => "Bonne pratique en AG constitutive – premier budget annuel proposé pour lancer la comptabilité.",
    'description_call'        => "Proposition de fixer le budget de fonctionnement annuel à ... et les provisions mensuelles à ...",
    'description_ballot'      => "Décision à prendre en vue de désigner la périodicté des décomptes.",
    'description_minutes'     => "L’Assemblée Générale décide, à la majorité requise, de valider le budget de fonctionnement.",
    'lang'                    => 'fr'
])->first();

AssemblyItemTemplate::create([
    'has_parent_group'        => true,
    'parent_group_id'         => $assemblyItemGroup['id'],
    'order'                   => 2,
    'assembly_template_id'    => $assemblyTemplate['id'],
    'name'                    => 'Fonds de roulement',
    'code'                    => 'working_fund_creation',
    'has_vote_required'       => true,
    'majority'                => 'absolute',
    'helper'                  => "Art. 3.92 §5 du Code civil – Permet la constitution initiale du fonds de roulement de la copropriété.",
    'description_call'        => "Décision de répartir le solde actuel du fonds de roulement au prorata des quotités comme montant de constitution de celui-ci.",
    'description_ballot'      => "Décision à prendre en vue de valider le fonds de roulement.",
    'description_minutes'     => "L’Assemblée Générale décide, à la majorité requise, de valider le fonds de roulement.",
    'lang'                    => 'fr'
])->first();

AssemblyItemTemplate::create([
    'has_parent_group'        => true,
    'parent_group_id'         => $assemblyItemGroup['id'],
    'order'                   => 3,
    'assembly_template_id'    => $assemblyTemplate['id'],
    'name'                    => 'Fonds de réserve',
    'code'                    => 'reserve_fund_creation',
    'has_vote_required'       => true,
    'majority'                => 'absolute',
    'helper'                  => "Art. 3.92 §2 du Code civil – Fonds de réserve obligatoire, minimum 5 % du budget sauf décision contraire motivée.",
    'description_call'        => "obligation légale (minimum 5% du budget).",
    'description_ballot'      => "Décision à prendre en vue de valider le fonds de réserve.",
    'description_minutes'     => "L’Assemblée Générale décide, à la majorité requise, de valider le fonds de réserve.",
    'lang'                    => 'fr'
])->first();

AssemblyItemTemplate::create([
    'is_group'                => true,
    'has_parent_group'        => false,
    'order'                   => 4,
    'assembly_template_id'    => $assemblyTemplate['id'],
    'name'                    => 'Cadre administratif et assurances',
    'code'                    => 'administrative_setup',
    'helper'                  => "Contient les éléments liés à la conformité administrative : assurance, règlement d’ordre intérieur, calendrier des AG futures.",
    'description_call'        => "Mise en place du cadre légal et administratif de la copropriété.",
    'description_minutes'     => "Les mesures suivantes ont été adoptées concernant le cadre administratif :",
    'lang'                    => 'fr'
])->first();

$assemblyItemGroup = AssemblyItemTemplate::create([
    'is_group'                => true,
    'has_parent_group'        => false,
    'order'                   => 5,
    'assembly_template_id'    => $assemblyTemplate['id'],
    'name'                    => 'Validation des informations initiales',
    'code'                    => 'initial_information_validation',
    'helper'                  => "Présentation des données de reprise ou de lancement (créances, contrats existants, fournisseurs…). Permet de démarrer sur une base claire.",
    'description_call'        => "Validation des données transmises ou identifiées à la constitution de l’ACP.",
    'description_minutes'     => "Les informations suivantes ont été portées à la connaissance de l’Assemblée Générale et validées :",
    'lang'                    => 'fr'
])->first();

AssemblyItemTemplate::create([
    'has_parent_group'        => true,
    'parent_group_id'         => $assemblyItemGroup['id'],
    'order'                   => 1,
    'assembly_template_id'    => $assemblyTemplate['id'],
    'name'                    => 'Situation des débiteurs',
    'code'                    => 'debtor_status',
    'helper'                  => "Art. 3.87 §9 du Code civil – Obligation pour le syndic de communiquer les dettes des copropriétaires.",
    'description_call'        => "Communication",
    'description_minutes'     => "L’Assemblée Générale décide, à la majorité requise, de valider le budget de fonctionnement.",
    'lang'                    => 'fr'
])->first();




/* =========================================================
 * AG extraordinaire
 * =======================================================*/

$assemblyTemplate = AssemblyTemplate::create([
    'name'           => 'Assemblée générale extraordinaire',
    'assembly_type'  => 'extraordinary',
    'description'    => "Assemblée convoquée en dehors du calendrier annuel obligatoire, généralement pour traiter un ou plusieurs points urgents ou spécifiques : travaux importants, modification des statuts ou du règlement d’ordre intérieur, décisions exceptionnelles, etc.",
    'lang'           => 'fr'
])->first();

AssemblyItemTemplate::create([
    'order'                   => 1,
    'has_parent_group'        => false,
    'assembly_template_id'    => $assemblyTemplate['id'],
    'name'                    => 'Constitution du bureau de séance',
    'code'                    => 'assembly_officers_appointment',
    'has_vote_required'       => true,
    'majority'                => 'absolute',
    'helper'                  => "Art. 3.87 §5 et §10 du Code civil – Désignation obligatoire d’un président et d’un secrétaire de séance.",
    'description_call'        => "Ouverture de séance et désignation du président et du secrétaire.",
    'description_ballot'      => "Décision à prendre en vue de désigner le/la Président(e) et le/la secrétaire de séance.",
    'description_minutes'     => "Après en avoir délibéré, l’Assemblée Générale nomme le/la Président(e) de séance : ... et le/la Secrétaire de séance : ...",
    'lang'                    => 'fr'
])->first();

$grpExtraPoints = AssemblyItemTemplate::create([
    'is_group'                => true,
    'has_parent_group'        => false,
    'order'                   => 2,
    'assembly_template_id'    => $assemblyTemplate['id'],
    'name'                    => 'Points urgents ou spécifiques',
    'code'                    => 'extraordinary_points',
    'helper'                  => "Section regroupant les décisions exceptionnelles ou urgentes soumises à l’AG.",
    'description_call'        => "Présentation des points exceptionnels à l’ordre du jour.",
    'description_minutes'     => "Les décisions suivantes ont été adoptées concernant les points exceptionnels :",
    'lang'                    => 'fr'
])->first();

AssemblyItemTemplate::create([
    'assembly_template_id'    => $assemblyTemplate['id'],
    'has_parent_group'        => true,
    'parent_group_id'         => $grpExtraPoints['id'],
    'order'                   => 1,
    'name'                    => 'Travaux exceptionnels',
    'code'                    => 'extraordinary_works',
    'has_vote_required'       => true,
    'majority'                => '2_3',
    'helper'                  => "Art. 3.91 §2, 5° du Code civil – Décision sur la réalisation de travaux importants ou urgents hors budget ordinaire.",
    'description_call'        => "Décision concernant la réalisation de travaux exceptionnels.",
    'description_ballot'      => "Vote sur l’approbation et le financement des travaux exceptionnels.",
    'description_minutes'     => "L’Assemblée Générale décide, à la majorité requise, d’autoriser/réfuser les travaux exceptionnels suivants : ...",
    'lang'                    => 'fr'
])->first();

AssemblyItemTemplate::create([
    'assembly_template_id'    => $assemblyTemplate['id'],
    'has_parent_group'        => true,
    'parent_group_id'         => $grpExtraPoints['id'],
    'order'                   => 2,
    'name'                    => 'Modification des statuts',
    'code'                    => 'statutes_change',
    'has_vote_required'       => true,
    'majority'                => '4_5',
    'helper'                  => "Art. 3.89 §1 et suivants du Code civil – Décisions relatives aux statuts.",
    'description_call'        => "Proposition de modification des statuts.",
    'description_ballot'      => "Vote sur l’adoption des modifications proposées.",
    'description_minutes'     => "L’Assemblée Générale adopte/rejette les modifications suivantes : ...",
    'lang'                    => 'fr'
])->first();

AssemblyItemTemplate::create([
    'assembly_template_id'    => $assemblyTemplate['id'],
    'has_parent_group'        => true,
    'parent_group_id'         => $grpExtraPoints['id'],
    'order'                   => 3,
    'name'                    => 'Modification du règlement d’ordre intérieur',
    'code'                    => 'roi_change',
    'has_vote_required'       => true,
    'majority'                => 'absolute',
    'helper'                  => "Art. 3.89 §1 et suivants du Code civil – Décisions relatives au règlement d’ordre intérieur.",
    'description_call'        => "Proposition de modification du ROI.",
    'description_ballot'      => "Vote sur l’adoption des modifications proposées.",
    'description_minutes'     => "L’Assemblée Générale adopte/rejette les modifications suivantes : ...",
    'lang'                    => 'fr'
])->first();

AssemblyItemTemplate::create([
    'assembly_template_id'    => $assemblyTemplate['id'],
    'has_parent_group'        => true,
    'parent_group_id'         => $grpExtraPoints['id'],
    'order'                   => 4,
    'name'                    => 'Décision judiciaire / nomination d’un administrateur provisoire',
    'code'                    => 'judicial_decision',
    'has_vote_required'       => true,
    'majority'                => 'absolute',
    'helper'                  => "Cas exceptionnel prévu par décision judiciaire ou nécessité de désigner un administrateur provisoire.",
    'description_call'        => "Examen de la décision judiciaire ou proposition de nomination d’un administrateur provisoire.",
    'description_ballot'      => "Décision à prendre concernant la nomination d’un administrateur provisoire.",
    'description_minutes'     => "Après discussion, l’Assemblée Générale décide, à la majorité requise, de nommer/ne pas nommer un administrateur provisoire.",
    'lang'                    => 'fr'
])->first();

AssemblyItemTemplate::create([
    'assembly_template_id'    => $assemblyTemplate['id'],
    'has_parent_group'        => true,
    'parent_group_id'         => $grpExtraPoints['id'],
    'order'                   => 5,
    'name'                    => 'Autres points exceptionnels',
    'code'                    => 'other_extraordinary_items',
    'helper'                  => "Permet d’ajouter des décisions spécifiques à la situation particulière de la copropriété.",
    'description_call'        => "Présentation d’autres points exceptionnels à débattre.",
    'description_minutes'     => "Les décisions suivantes ont été adoptées concernant les autres points exceptionnels :",
    'lang'                    => 'fr'
])->first();


/* =========================================================
 * AG de reprise
 * =======================================================*/
$assemblyTemplate = AssemblyTemplate::create([
    'name'           => 'Assemblée de reprise',
    'assembly_type'  => 'recovery',
    'description'    => "Assemblée convoquée pour relancer une copropriété bloquée ou non gérée depuis un certain temps (absence de syndic, absence d’AG depuis plusieurs années, documents perdus, etc.). Elle sert à remettre en ordre la gestion : désignation d’un syndic, reconstitution des fonds, régularisation administrative. Elle peut combiner des points typiques d'une AG constitutive et ordinaire.",
    'lang'           => 'fr'
])->first();


AssemblyItemTemplate::create([
    'order'                   => 1,
    'has_parent_group'        => false,
    'assembly_template_id'    => $assemblyTemplate['id'],
    'name'                    => 'Constitution du bureau de séance',
    'code'                    => 'assembly_officers_appointment',
    'has_vote_required'       => true,
    'majority'                => 'absolute',
    'helper'                  => "Art. 3.87 §5 et §10 du Code civil – Désignation obligatoire d’un président et d’un secrétaire de séance.",
    'description_call'        => "Désignation du président et du secrétaire de séance.",
    'description_ballot'      => "Vote pour désigner le/la Président(e) et le/la secrétaire de séance.",
    'description_minutes'     => "L’Assemblée Générale nomme le/la Président(e) de séance : ... et le/la Secrétaire de séance : ...",
    'lang'                    => 'fr'
])->first();

$grpGovernance = AssemblyItemTemplate::create([
    'is_group'                => true,
    'has_parent_group'        => false,
    'order'                   => 2,
    'assembly_template_id'    => $assemblyTemplate['id'],
    'name'                    => 'Mise en place / réactivation de la gouvernance',
    'code'                    => 'recovery_governance',
    'helper'                  => "Désignation ou reconfirmation du syndic, constitution éventuelle du conseil de copropriété, régularisation des mandats.",
    'description_call'        => "Réactivation des organes de gouvernance de l’ACP.",
    'description_minutes'     => "Les décisions suivantes ont été prises concernant la gouvernance :",
    'lang'                    => 'fr'
])->first();

AssemblyItemTemplate::create([
    'assembly_template_id'    => $assemblyTemplate['id'],
    'has_parent_group'        => true,
    'parent_group_id'         => $grpGovernance['id'],
    'order'                   => 1,
    'name'                    => 'Désignation du syndic',
    'code'                    => 'syndic_appointment',
    'has_vote_required'       => true,
    'majority'                => 'absolute',
    'helper'                  => "Art. 3.88 du Code civil – Obligation de désigner un syndic pour relancer la gestion.",
    'description_call'        => "Proposition de désignation d’un syndic (professionnel ou bénévole).",
    'description_ballot'      => "Vote sur la désignation du syndic.",
    'description_minutes'     => "L’Assemblée Générale décide, à la majorité requise, de désigner comme syndic : ...",
    'lang'                    => 'fr'
])->first();

$grpAccounting = AssemblyItemTemplate::create([
    'is_group'                => true,
    'has_parent_group'        => false,
    'order'                   => 3,
    'assembly_template_id'    => $assemblyTemplate['id'],
    'name'                    => 'Cadre comptable et financier',
    'code'                    => 'recovery_accounting',
    'helper'                  => "Fixation de l’exercice, périodicité des provisions/décomptes, et constat de la situation financière.",
    'description_call'        => "Mise en place ou régularisation du cadre comptable.",
    'description_minutes'     => "Les décisions suivantes ont été adoptées concernant le cadre comptable et financier :",
    'lang'                    => 'fr'
])->first();

AssemblyItemTemplate::create([
    'assembly_template_id'    => $assemblyTemplate['id'],
    'has_parent_group'        => true,
    'parent_group_id'         => $grpAccounting['id'],
    'order'                   => 1,
    'name'                    => 'Fixation de l’exercice comptable',
    'code'                    => 'fiscal_year_reset',
    'has_vote_required'       => true,
    'majority'                => 'absolute',
    'helper'                  => "Bonne pratique – permet de remettre à jour la période comptable officielle de l’ACP.",
    'description_call'        => "Proposition de fixer l’exercice comptable du ... au ...",
    'description_ballot'      => "Vote sur la fixation de l’exercice comptable.",
    'description_minutes'     => "L’Assemblée Générale valide les nouvelles dates de l’exercice comptable : ...",
    'lang'                    => 'fr'
])->first();

AssemblyItemTemplate::create([
    'assembly_template_id'    => $assemblyTemplate['id'],
    'has_parent_group'        => true,
    'parent_group_id'         => $grpAccounting['id'],
    'order'                   => 2,
    'name'                    => 'Situation financière reconstituée',
    'code'                    => 'financial_reconstruction',
    'helper'                  => "Présentation de l’état des comptes, dettes, créances et reconstitution d’un solde de départ.",
    'description_call'        => "Présentation de la situation financière et des arriérés éventuels.",
    'description_minutes'     => "L’Assemblée Générale prend acte de la situation financière reconstituée : ...",
    'lang'                    => 'fr'
])->first();

$grpFunds = AssemblyItemTemplate::create([
    'is_group'                => true,
    'has_parent_group'        => false,
    'order'                   => 4,
    'assembly_template_id'    => $assemblyTemplate['id'],
    'name'                    => 'Reconstitution des fonds',
    'code'                    => 'funds_recovery',
    'helper'                  => "Reconstitution des fonds obligatoires (roulement, réserve).",
    'description_call'        => "Décision sur la reconstitution des fonds.",
    'description_minutes'     => "Les décisions suivantes ont été adoptées concernant les fonds :",
    'lang'                    => 'fr'
])->first();

AssemblyItemTemplate::create([
    'assembly_template_id'    => $assemblyTemplate['id'],
    'has_parent_group'        => true,
    'parent_group_id'         => $grpFunds['id'],
    'order'                   => 1,
    'name'                    => 'Reconstitution du fonds de roulement',
    'code'                    => 'working_fund_recovery',
    'has_vote_required'       => true,
    'majority'                => 'absolute',
    'helper'                  => "Art. 3.92 §5 du Code civil – Fonds de roulement obligatoire.",
    'description_call'        => "Décision de reconstituer le fonds de roulement au prorata des quotités.",
    'description_ballot'      => "Vote sur la reconstitution du fonds de roulement.",
    'description_minutes'     => "L’Assemblée Générale valide la reconstitution du fonds de roulement.",
    'lang'                    => 'fr'
])->first();

AssemblyItemTemplate::create([
    'assembly_template_id'    => $assemblyTemplate['id'],
    'has_parent_group'        => true,
    'parent_group_id'         => $grpFunds['id'],
    'order'                   => 2,
    'name'                    => 'Reconstitution du fonds de réserve',
    'code'                    => 'reserve_fund_recovery',
    'has_vote_required'       => true,
    'majority'                => 'absolute',
    'helper'                  => "Art. 3.92 §2 du Code civil – Fonds de réserve obligatoire (minimum 5 % du budget).",
    'description_call'        => "Décision de reconstituer le fonds de réserve.",
    'description_ballot'      => "Vote sur la reconstitution du fonds de réserve.",
    'description_minutes'     => "L’Assemblée Générale valide la reconstitution du fonds de réserve.",
    'lang'                    => 'fr'
])->first();

$grpRegularisation = AssemblyItemTemplate::create([
    'is_group'                => true,
    'has_parent_group'        => false,
    'order'                   => 5,
    'assembly_template_id'    => $assemblyTemplate['id'],
    'name'                    => 'Validation des dettes, créances et contrats',
    'code'                    => 'debts_contracts_validation',
    'helper'                  => "Régularisation administrative des dettes, créances et contrats en cours.",
    'description_call'        => "Présentation et validation des éléments financiers et contractuels connus.",
    'description_minutes'     => "L’Assemblée Générale valide la liste des dettes, créances et contrats suivants : ...",
    'lang'                    => 'fr'
])->first();

AssemblyItemTemplate::create([
    'order'                   => 6,
    'has_parent_group'        => false,
    'assembly_template_id'    => $assemblyTemplate['id'],
    'name'                    => 'Budget de reprise',
    'code'                    => 'recovery_budget',
    'has_vote_required'       => true,
    'majority'                => 'absolute',
    'helper'                  => "Adoption d’un budget de redémarrage pour assurer la continuité des charges.",
    'description_call'        => "Présentation d’un budget de reprise pour la copropriété.",
    'description_ballot'      => "Vote sur l’approbation du budget de reprise.",
    'description_minutes'     => "L’Assemblée Générale approuve/rejette le budget de reprise : ...",
    'lang'                    => 'fr'
])->first();

AssemblyItemTemplate::create([
    'order'                   => 7,
    'has_parent_group'        => false,
    'assembly_template_id'    => $assemblyTemplate['id'],
    'name'                    => 'Autres décisions de régularisation',
    'code'                    => 'other_recovery_decisions',
    'helper'                  => "Points divers visant à régulariser la situation de la copropriété.",
    'description_call'        => "Présentation des autres mesures nécessaires à la reprise.",
    'description_minutes'     => "Les décisions suivantes ont été adoptées pour régulariser la gestion : ...",
    'lang'                    => 'fr'
])->first();


/* =========================================================
 * Réunion CC
 * =======================================================*/
$assemblyTemplate = AssemblyTemplate::create([
    'name'           => 'Réunion du Conseil de copropriété',
    'assembly_type'  => 'council_meeting',
    'description'    => "Réunion interne du Conseil de copropriété (organe consultatif). Elle permet d’examiner des points techniques, de préparer l’ordre du jour de l’AG, d’émettre des avis ou de suivre l’exécution de certaines décisions. Aucune décision ne peut y être prise au nom de l’ACP sauf si un mandat spécifique lui a été accordé par une AG.",
    'lang'           => 'fr'
])->first();



$orm->enableEvents($events);

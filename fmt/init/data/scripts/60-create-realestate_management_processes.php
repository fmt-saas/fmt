<?php

use realestate\management\ManagementProcess;

$processes = [
    'finance' => [
        'en' => [
            'name'        => 'Finance',
            'description' => 'Fund calls, statements, account extracts, reimbursements, payment reminders and financial formal notices.'
        ],
        'fr' => [
            'name'        => 'Finance',
            'description' => 'Appels de fonds, décomptes, extraits de compte, remboursements, rappels de paiement, mises en demeure financières.'
        ]
    ],
    'legal' => [
        'en' => [
            'name'        => 'Legal',
            'description' => 'Sales, transfers, notary questionnaires, dated statements, statutes, base deeds, contracts and disputes.'
        ],
        'fr' => [
            'name'        => 'Juridique',
            'description' => 'Ventes, mutations, questionnaires notariaux, états datés, statuts, actes de base, contrats, contentieux.'
        ]
    ],
    'governance' => [
        'en' => [
            'name'        => 'Governance',
            'description' => 'General meetings, notices, proxies, votes, minutes, condominium council and statutory auditor topics.'
        ],
        'fr' => [
            'name'        => 'Gouvernance',
            'description' => 'Assemblées générales, convocations, procurations, votes, procès-verbaux, conseil de copropriété, commissaire aux comptes.'
        ]
    ],
    'communication' => [
        'en' => [
            'name'        => 'Communications',
            'description' => 'Collective messages, general information, announcements, interventions, building access, incidents, instructions and building life.'
        ],
        'fr' => [
            'name'        => 'Communications',
            'description' => 'Messages collectifs, informations générales, annonces, interventions, accès aux immeubles, incidents, consignes et vie de l\'immeuble.'
        ]
    ]
];

foreach($processes as $code => $translations) {
    $process = ManagementProcess::search([['code', '=', $code]])->first();

    if(!$process) {
        $process = ManagementProcess::create(array_merge(['code' => $code], $translations['en']))->first();
    }
    else {
        ManagementProcess::id($process['id'])->update($translations['en'], 'en');
    }

    ManagementProcess::id($process['id'])->update($translations['fr'], 'fr');
}
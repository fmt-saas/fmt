<?php

use realestate\management\TaskCategory;

$categories = [
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

foreach($categories as $code => $translations) {
    $category = TaskCategory::search([['code', '=', $code]])->first();

    if(!$category) {
        $category = TaskCategory::create(array_merge(['code' => $code], $translations['en']))->first();
    }
    else {
        TaskCategory::id($category['id'])->update($translations['en'], 'en');
    }

    TaskCategory::id($category['id'])->update($translations['fr'], 'fr');
}
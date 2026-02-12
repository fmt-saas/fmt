<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use realestate\governance\Assembly;

[$params, $providers] = eQual::announce([
    'description'   => 'Generate an html view of the vote forms for a given Assembly.',
    'deprecated'    => 'This action is deprecated and should not be used anymore. Use either `realestate_governance_Assembly_voteforms_render-pdf` or `realestate_governance_Assembly_voteforms_single-pdf` providers instead.',
    'params'        => [
        'id' => [
            'description'       => 'Identifier of the specific Assembly to consider.',
            'type'              => 'many2one',
            'foreign_object'    => 'realestate\governance\Assembly',
            'required'          => true
        ],

        'debug' => [
            'type'              => 'boolean',
            'default'           => false
        ],

        'view_id' => [
            'description'       => 'View id of the template to use.',
            'type'              => 'string',
            'default'           => 'print.voteforms'
        ],

        'lang' => [
            'description'       => 'Language in which labels and multilang field have to be returned (2 letters ISO 639-1).',
            'type'              => 'string',
            'default'           => 'fr'
        ]
    ],
    'access' => [
        'visibility'    => 'protected'
    ],
    'response' => [
        'content-type'  => 'text/html',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers' => ['context'],
    'constants' => ['L10N_TIMEZONE', 'L10N_LOCALE']
]);

/** @var \equal\php\Context $context */
$context = $providers['context'];

/**
 * Action
 */

$assembly = Assembly::id($params['id'])
    ->read([
        'ownerships_ids' => ['name']
    ])
    ->first(true);

if(!$assembly) {
    throw new Exception('unknown_assembly', EQ_ERROR_UNKNOWN_OBJECT);
}

usort($assembly['ownerships_ids'], fn($a, $b) => strcmp($a['name'], $b['name']));

try {
    foreach($assembly['ownerships_ids'] as $ownership) {
        $html = (string) eQual::run('get', 'realestate_governance_Assembly_voteforms_single-html', [
                'id'            => $params['id'],
                'ownership_id'  => $ownership['id']
            ]);
    }
}
catch(Exception $e) {
    trigger_error('APP::Error while rendering template'.$e->getMessage(), EQ_REPORT_ERROR);
    throw new Exception($e->getMessage(), EQ_ERROR_INVALID_CONFIG);
}

$context->httpResponse()
        ->body($html)
        ->send();

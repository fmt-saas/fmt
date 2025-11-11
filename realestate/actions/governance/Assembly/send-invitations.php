<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use documents\Document;
use realestate\governance\Assembly;
use realestate\governance\AssemblyInvitation;

[$params, $providers] = eQual::announce([
    'description'   => "Checks if all owners have been invited to the target assembly.",
    'params'        => [
        'id' =>  [
            'type'              => 'many2one',
            'description'       => "The assembly the invitation sending refers to.",
            'foreign_object'    => 'realestate\governance\Assembly',
            'required'          => true
        ],

        'communication_method' => [
            'type'              => 'string',
            'description'       => 'Method of sending.',
            'help'              => 'This controllers expect only digital communication methods (e.g. email).',
            'selection'         => [
                'email',
                'postal',
                'postal_registered',
                'postal_registered_receipt'
            ]
        ]

    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'dispatch']
]);

/**
 * @var \equal\php\Context                 $context
 * @var \equal\dispatch\Dispatcher         $dispatch
 */
['context' => $context, 'dispatch' => $dispatch] = $providers;


$assembly = Assembly::id($params['id'])
    ->read(['status', 'step', 'condo_id', 'name'])
    ->first();

if(!$assembly) {
    throw new Exception("unknown_assembly", EQ_ERROR_UNKNOWN_OBJECT);
}

// fetch invitations relating to given communication_method
$assemblyInvitations = AssemblyInvitation::search([
        [ 'assembly_id', '=', $assembly['id'] ],
        [ 'communication_method', '=', $params['communication_method'] ]
    ])
    ->read(['is_sent', 'document_id']);

$assembly_invitations_ids = [];

foreach($assemblyInvitations as $assembly_invitation_id => $assemblyInvitation) {

    // limit to digital communication methods
    if(!in_array($assemblyInvitation['communication_method'], ['email'], true)) {
        continue;
    }

    // #memo - `export-invitations` and `send-invitations` are the only controllers where documents are generated for Assembly invites
    if(!$assemblyInvitation['document_id']) {
        // generate document, add it to EDMS, and attach it to invitation
        eQual::run('do', 'realestate_governance_AssemblyInvitation_generate-document', ['id' => $assembly_invitation_id]);
    }

    $assemblyInvitation = AssemblyInvitation::id($assembly_invitation_id)
        ->read(['document_id' => ['data']])
        ->first();

    if(!$assemblyInvitation['document_id']) {
        continue;
    }

    $assembly_invitations_ids[] = $assembly_invitation_id;
}

// send all generated documents
foreach($assembly_invitations_ids as $assembly_invitation_id) {
    try {
        eQual::run('do', 'realestate_governance_AssemblyInvitation_send', ['id' => $assembly_invitation_id]);
    }
    catch(Exception $e) {
        trigger_error('APP::Error while sending documents ' . $e->getMessage(), EQ_REPORT_ERROR);
        throw new Exception($e->getMessage(), EQ_ERROR_INVALID_CONFIG);
    }
}

$context->httpResponse()
        ->status(204)
        ->send();

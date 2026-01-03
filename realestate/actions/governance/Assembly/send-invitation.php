<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use realestate\governance\Assembly;
use realestate\governance\AssemblyInvitationCorrespondence;

[$params, $providers] = eQual::announce([
    'description'   => "Send all email invites for the target assembly.",
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
            'default'           => 'email',
            'selection'         => [
                'email'
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
$assemblyInvitationCorrespondences = AssemblyInvitationCorrespondence::search([
        [ 'assembly_id', '=', $assembly['id'] ],
        [ 'communication_method', '=', $params['communication_method'] ]
    ])
    ->read(['is_sent', 'document_id']);

$assembly_invitation_correspondences_ids = [];

foreach($assemblyInvitationCorrespondences as $assembly_invitation_id => $assemblyInvitationCorrespondence) {

    // #memo - `export-invitation` and `send-invitation` are the only controllers where documents are generated for Assembly invites
    if(!$assemblyInvitationCorrespondence['document_id']) {
        // generate document, add it to EDMS, and attach it to invitation
        eQual::run('do', 'realestate_governance_AssemblyInvitationCorrespondence_generate-document', ['id' => $assembly_invitation_id]);
    }

    $assemblyInvitationCorrespondence = AssemblyInvitationCorrespondence::id($assembly_invitation_id)
        ->read(['document_id' => ['data']])
        ->first();

    if(!$assemblyInvitationCorrespondence['document_id']) {
        continue;
    }

    $assembly_invitation_correspondences_ids[] = $assembly_invitation_id;
}

// send all generated documents
foreach($assembly_invitation_correspondences_ids as $assembly_invitation_id) {
    try {
        eQual::run('do', 'realestate_governance_AssemblyInvitationCorrespondence_send', ['id' => $assembly_invitation_id]);
    }
    catch(Exception $e) {
        trigger_error('APP::Error while sending documents ' . $e->getMessage(), EQ_REPORT_ERROR);
        throw new Exception($e->getMessage(), EQ_ERROR_INVALID_CONFIG);
    }
}

$context->httpResponse()
        ->status(204)
        ->send();

<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use documents\DocumentSignature;
use realestate\governance\Assembly;
use realestate\governance\AssemblyAttendee;
use realestate\governance\AssemblyRepresentation;

[$params, $providers] = eQual::announce([
    'description'   => "Delete an assembly attendee.",
    'params'        => [

        'id' =>  [
            'type'              => 'many2one',
            'description'       => "The assembly attendee to delete.",
            'foreign_object'    => 'realestate\governance\Assembly',
            'required'          => true
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
 * @var \equal\php\Context          $context
 * @var \equal\dispatch\Dispatcher  $dispatch
 */
['context' => $context, 'dispatch' => $dispatch] = $providers;

$attendee = AssemblyAttendee::id($params['id'])
    ->read([
        'attendee_role',
        'register_document_signature_id',
        'assembly_id' => [
            'status',
            'step'
        ],
    ])
    ->first();

if(!$attendee) {
    throw new Exception("unknown_assembly_attendee");
}

if($attendee['attendee_role'] !== 'attendee') {
    #todo - handle deletion of secretary and president
    throw new Exception("secretary_or_president_cannot_be_deleted");
}

if($attendee['assembly_id']['status'] !== 'in_progress') {
    throw new Exception("wrong_assembly_status");
}

if($attendee['assembly_id']['step'] !== 'opening') {
    throw new Exception("wrong_assembly_step");
}

// 1) delete attendee and object related to it

if($attendee['register_document_signature_id']) {
    DocumentSignature::id($attendee['register_document_signature_id'])->delete();
}

AssemblyRepresentation::search(['attendee_id', '=', $attendee['id']])->delete();

AssemblyAttendee::id($attendee['id'])->delete();

// 2) refresh assembly valid/invalid alert

Assembly::id($attendee['assembly_id']['id'])->update(['count_represented_shares' => null, 'count_represented_owners' => null]);

$dispatch->cancel('realestate.workflow.assembly.invalid', 'realestate\governance\Assembly', $attendee['assembly_id']['id']);
$dispatch->cancel('realestate.workflow.assembly.valid', 'realestate\governance\Assembly', $attendee['assembly_id']['id']);

try {
    Assembly::id($attendee['assembly_id']['id'])->assert('is_assembly_valid');

    $dispatch->dispatch('realestate.workflow.assembly.valid', 'realestate\governance\Assembly', $attendee['assembly_id']['id'], 'notice');
}
catch(Exception $e) {
    $dispatch->dispatch('realestate.workflow.assembly.invalid', 'realestate\governance\Assembly', $attendee['assembly_id']['id'], 'important');
}

$context->httpResponse()
        ->status(205)
        ->send();

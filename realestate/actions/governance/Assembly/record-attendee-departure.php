<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use realestate\governance\Assembly;
use realestate\governance\AssemblyAttendee;

[$params, $providers] = eQual::announce([
    'description'   => "Accept the minutes of the Assembly and make sure an Attendee is marked as president.",
    'params'        => [
        'id' =>  [
            'type'              => 'many2one',
            'description'       => "The assembly the attendee want to leave early.",
            'foreign_object'    => 'realestate\governance\Assembly',
            'required'          => true
        ],
        'attendee_id' => [
            'type'              => 'many2one',
            'description'       => "The attendee who wants to leave early.",
            'foreign_object'    => 'realestate\governance\AssemblyAttendee',
            'required'          => true,
            'domain'            => [
                ['assembly_id', '=', 'object.id'],
                ['attendee_role', 'not in', ['secretary', 'president']],
                ['has_early_departure', '=', false]
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
 * @var \equal\php\Context  $context
 */
['context' => $context] = $providers;

$assembly = Assembly::id($params['id'])
    ->read(['status', 'step', 'assembly_organizer_identity_id'])
    ->first();

if(!$assembly) {
    throw new Exception("unknown_assembly", EQ_ERROR_UNKNOWN_OBJECT);
}

if($assembly['status'] !== 'in_progress') {
    throw new Exception("assembly_status_must_be_in_progress", EQ_ERROR_INVALID_PARAM);
}

if($assembly['step'] !== 'agenda_processing') {
    throw new Exception("assembly_step_must_be_agenda_processing", EQ_ERROR_INVALID_PARAM);
}

$attendee = AssemblyAttendee::id($params['attendee_id'])
    ->read(['assembly_id', 'has_early_departure', 'attendee_role', 'identity_id'])
    ->first();

if(!$attendee) {
    throw new Exception("unknown_attendee", EQ_ERROR_UNKNOWN_OBJECT);
}

if($attendee['identity_id'] === $assembly['assembly_organizer_identity_id']) {
    throw new Exception("organizer_cannot_leave_early", EQ_ERROR_UNKNOWN_OBJECT);
}

if($attendee['has_early_departure']) {
    throw new Exception("attendee_already_left", EQ_ERROR_UNKNOWN_OBJECT);
}

if(in_array($attendee['attendee_role'], ['secretary', 'president'])) {
    throw new Exception("secretary_or_president_cannot_leave_early", EQ_ERROR_UNKNOWN_OBJECT);
}

if($attendee['assembly_id'] !== $assembly['id']) {
    throw new Exception("assembly_attendee_mismatch", EQ_ERROR_UNKNOWN_OBJECT);
}

AssemblyAttendee::id($attendee['id'])->do('leave_assembly');

$context->httpResponse()
        ->status(200)
        ->send();

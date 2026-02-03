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
            'description'       => "The assembly the invitation refers to.",
            'foreign_object'    => 'realestate\governance\Assembly',
            'required'          => true
        ],
        // choice of attendee (must be owner) as president
        'president_attendee_id' => [
            'type'           => 'many2one',
            'label'          => 'President Attendee',
            'description'    => "Attendee who presided the assembly.",
            'foreign_object' => 'realestate\governance\AssemblyAttendee',
            'foreign_field'  => 'assembly_id',
            'domain'         => [
                ['assembly_id', '=', 'object.id'],
                ['attendee_role', 'in', ['attendee', 'president']],
                ['is_valid', '=', true],
                ['is_owner', '=', true],
                ['has_left', '=', false]
            ],
            'required'       => true
        ],
        'secretary_attendee_id' => [
            'type'           => 'many2one',
            'label'          => 'Secretary Attendee',
            'description'    => "Attendee who acted as secretary for the assembly.",
            'foreign_object' => 'realestate\governance\AssemblyAttendee',
            'foreign_field'  => 'assembly_id',
            'domain'         => [
                ['assembly_id', '=', 'object.id'],
                ['attendee_role', 'in', ['attendee', 'secretary']],
                ['is_valid', '=', true],
                ['has_left', '=', false]
            ],
            'required'       => true
        ]
    ],
    'constants'     => ['AUTH_SECRET_KEY'],
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

if($params['president_attendee_id'] === $params['secretary_attendee_id']) {
    throw new Exception("president_and_secretary_must_be_distinct", EQ_ERROR_INVALID_PARAM);
}

$assembly = Assembly::id($params['id'])
    ->read(['id', 'step', 'status'])
    ->first();

if(!$assembly) {
    throw new Exception("unknown_assembly", EQ_ERROR_UNKNOWN_OBJECT);
}

if($assembly['status'] !== 'in_progress') {
    throw new Exception("assembly_wrong_status", EQ_ERROR_UNKNOWN_OBJECT);
}

if($assembly['step'] !== 'minutes_confirmation') {
    throw new Exception("assembly_wrong_step", EQ_ERROR_UNKNOWN_OBJECT);
}

$attendee = AssemblyAttendee::id($params['president_attendee_id'])
    ->first();

if(!$attendee) {
    throw new Exception('invalid_provided_attendee', EQ_ERROR_INVALID_PARAM);
}

AssemblyAttendee::id($params['president_attendee_id'])->do('promote_president');

// if a secretary was provided
if($params['secretary_attendee_id']) {
    // check if there is already one: if so, and it's an owner, set to attendee
    AssemblyAttendee::search(['attendee_role', '=', 'secretary'])->update(['attendee_role' => 'attendee']);
    // in any case, mark the selected attendee as secretary and validate them
    $secretary = AssemblyAttendee::id($params['secretary_attendee_id'])
        ->read(['status'])
        ->update(['attendee_role' => 'secretary'])
        ->first();

    if($secretary['status'] !== 'validated') {
        // transition to validated
        AssemblyAttendee::id($params['secretary_attendee_id'])->transition('validate');
    }
}

Assembly::id($params['id'])->do('accept_minutes');

$context->httpResponse()
        ->status(204)
        ->send();

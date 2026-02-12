<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use realestate\governance\AssemblyAttendee;
use realestate\governance\AssemblyItem;
use realestate\governance\AssemblyVote;
use realestate\governance\AssemblyVoteIntention;
use realestate\property\PropertyLotApportionmentShare;
use realestate\property\PropertyLotOwnership;

[$params, $providers] = eQual::announce([
    'description'   => "Update a vote value. Only non-casted votes can be updated.",
    'help'          => "This controller is meant to be used by front-end. Update an existing vote for an assembly item (resolution) by an attendee representing an ownership (property lots holder). Does not cast the vote.",
    'params'        => [
        'id' =>  [
            'type'             => 'many2one',
            'foreign_object'   => 'realestate\governance\AssemblyItem',
            'description'      => 'Identifier of the Assembly item (resolution).',
        ],
        'attendee_id' => [
            'type'             => 'many2one',
            'label'            => 'Attendee',
            'foreign_object'   => 'realestate\governance\AssemblyAttendee',
            'description'      => 'Attendee casting the vote.',
            'required'         => true,
            'domain'           => [
                ['assembly_id', '=', 'object.assembly_id'],
                ['has_representation', '=', true]
            ]
        ],
        'ownership_id' => [
            'type'             => 'many2one',
            'label'            => 'Ownership',
            'foreign_object'   => 'realestate\ownership\Ownership',
            'description'      => 'Identifier of the property lots holder.',
            'required'         => true,
            'domain'           => ['condo_id', '=', 'object.condo_id']
        ],
        'vote_value' => [
            'type'             => 'string',
            'description'      => "Vote value: 'for', 'against', or 'abstain'.",
            'selection'        => ['for', 'against', 'abstain'],
            'required'         => true
        ]
    ],
    'access' => [
        'visibility' => 'protected'
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'auth']
]);

/**
 * @var \equal\php\Context                  $context
 */
['context' => $context, 'auth' => $auth] = $providers;

if(!isset($params['id'])) {
    throw new Exception("missing_id", EQ_ERROR_INVALID_PARAM);
}

$assemblyItem = AssemblyItem::id($params['id'])
    ->read([
        'name',
        'condo_id',
        'apportionment_id',
        'assembly_id' => ['assembly_date', 'assembly_type']
    ])
    ->first();

if(!$assemblyItem) {
    throw new Exception("unknown_assembly_item", EQ_ERROR_INVALID_PARAM);
}

// Only individuals who hold at least one share in the targeted allocation key are authorized to vote.
$propertyLotOwnerships = PropertyLotOwnership::search([
        ['condo_id', '=', $assemblyItem['condo_id']],
        ['ownership_id', '=', $params['ownership_id']]
    ])
    ->read(['property_lot_id', 'date_to']);

$property_lots_ids = [];
foreach($propertyLotOwnerships as $propertyLotOwnership) {
    if(!$propertyLotOwnership['date_to'] || $propertyLotOwnership['date_to'] >= $assemblyItem['assembly_id']['assembly_date']) {
        $property_lots_ids[] = $propertyLotOwnership['property_lot_id'];
    }
}

$apportionmentShares = PropertyLotApportionmentShare::search([
        ['condo_id', '=', $assemblyItem['condo_id']],
        ['apportionment_id', '=', $assemblyItem['apportionment_id']],
        ['property_lot_id', 'in', $property_lots_ids]
    ]);

if($apportionmentShares->count() <= 0) {
    throw new Exception("ownership_without_shares", EQ_ERROR_INVALID_PARAM);
}

$attendee = AssemblyAttendee::id($params['attendee_id'])
    ->read(['has_early_departure'])
    ->first();

if(!$attendee) {
    throw new Exception("unknown_attendee", EQ_ERROR_INVALID_PARAM);
}

if($attendee['has_early_departure']) {
    throw new Exception("left_attendee_cannot_cast_vote", EQ_ERROR_INVALID_PARAM);
}

$assemblyVote = AssemblyVote::search([
        ['assembly_item_id', '=', $params['id']],
        ['assembly_attendee_id', '=', $params['attendee_id']],
        ['ownership_id', '=', $params['ownership_id']]
    ])
    ->read(['status', 'cast_by'])
    ->first();

if(!$assemblyVote) {
    throw new Exception("unknown_assembly_vote", EQ_ERROR_INVALID_PARAM);
}

$assemblyVoteIntention = AssemblyVoteIntention::search([
        ['condo_id', '=', $assemblyItem['condo_id']],
        ['assembly_id', '=', $assemblyItem['assembly_id']['id']],
        ['assembly_item_id', '=', $assemblyItem['id']],
        ['ownership_id', '=', $params['ownership_id']]
    ])
    ->first();

if($assemblyVoteIntention) {
    throw new Exception("vote_intention_cannot_be_overwritten", EQ_ERROR_NOT_ALLOWED);
}

$user_id = $auth->userId();

// #memo - user that casted a vote can always update it later if needed
if($assemblyVote['cast_by'] !== $user_id) {
    if($assemblyVote['status'] === 'casted') {
        throw new Exception("already_casted_assembly_vote", EQ_ERROR_INVALID_PARAM);
    }
}

// perform a direct assignment update of the existing vote
AssemblyVote::id($assemblyVote['id'])
    ->update([
            'vote_value'        => $params['vote_value']
        ]);

$context->httpResponse()
        ->status(201)
        ->send();

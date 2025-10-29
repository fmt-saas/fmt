<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use realestate\governance\AssemblyItem;
use realestate\property\PropertyLotApportionmentShare;
use realestate\property\PropertyLotOwnership;

[$params, $providers] = eQual::announce([
    'description'   => "Create a new assembly for a condominium using an assembly template.",
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
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context                  $context
 */
['context' => $context] = $providers;

if(!isset($params['id'])) {
    throw new Exception("missing_id", EQ_ERROR_INVALID_PARAM);
}

$assemblyItem = AssemblyItem::id($params['id'])
    ->read(['name', 'assembly_id' => ['assembly_date', 'assembly_type'], 'condo_id', 'apportionment_id'])
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

AssemblyItem::id($params['id'])
    ->do('cast_vote', [
            'attendee_id'       => $params['attendee_id'],
            'ownership_id'      => $params['ownership_id'],
            'vote_value'        => $params['vote_value']
        ]);

$context->httpResponse()
        ->status(201)
        ->send();

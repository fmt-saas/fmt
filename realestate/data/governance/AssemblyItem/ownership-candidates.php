<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use equal\orm\Domain;
use realestate\governance\Assembly;
use realestate\governance\AssemblyAttendee;
use realestate\governance\AssemblyItem;
use realestate\governance\AssemblyMandate;
use realestate\governance\AssemblyVote;
use realestate\ownership\Owner;
use realestate\ownership\Ownership;

[$params, $providers] = eQual::announce([
    'description'   => 'Retrieve the list of Ownerships that are allowed to cast a vote for a given resolution.',
    'params'        => [
        'id' => [
            'description'       => 'Identifier of the specific AssemblyItem to consider (resolution).',
            'type'              => 'many2one',
            'foreign_object'    => 'realestate\governance\AssemblyItem',
            'required'          => true
        ],
        'domain' => [
            'description'       => 'Optional conditional domain.',
            'type'              => 'array',
            'default'           => []
        ]
    ],
    'access'        => [
        'visibility' => 'protected'
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'accept-origin' => '*'
    ],
    'providers'     => ['context'],
    'constants'     => ['L10N_TIMEZONE', 'L10N_LOCALE']
]);

/** @var \equal\php\Context $context */
$context = $providers['context'];

$assemblyItem = AssemblyItem::id($params['id'])
    ->read(['condo_id', 'assembly_id', 'involved_ownerships_ids'])
    ->first();

if(!$assemblyItem) {
    throw new Exception('unknown_assembly_item', EQ_ERROR_UNKNOWN_OBJECT);
}

// 1) retrieve all owners involved in the resolution (@see AssemblyItem)
$map_ownerships_ids = array_fill_keys($assemblyItem['involved_ownerships_ids'], true);

// 2) remove ownerships for which a vote has already been casted
$assemblyVotes = AssemblyVote::search([
        ['assembly_item_id', '=', $assemblyItem['id']],
        ['status', '=', 'casted']
    ])
    ->read(['ownership_id']);

foreach($assemblyVotes as $assemblyVote) {
    if(isset($map_ownerships_ids[$assemblyVote['ownership_id']])) {
        unset($map_ownerships_ids[$assemblyVote['ownership_id']]);
    }
}

$domain = new Domain($params['domain']);

$domain->merge(new Domain([
        ['id', 'in', array_keys($map_ownerships_ids)],
    ]));

$ownerships = Ownership::search($domain->toArray())
    ->read(['id', 'name'])
    ->adapt('json')
    ->get(true);


$context->httpResponse()
        ->body(array_values($ownerships))
        ->send();

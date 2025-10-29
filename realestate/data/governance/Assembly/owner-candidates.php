<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use equal\orm\Domain;
use realestate\governance\Assembly;
use realestate\governance\AssemblyAttendee;
use realestate\governance\AssemblyMandate;
use realestate\ownership\Owner;
use realestate\ownership\Ownership;

[$params, $providers] = eQual::announce([
    'description'   => 'Retrieve the list of Owners that can be selected as attendee or .',
    'params'        => [
        'id' => [
            'description'       => 'Identifier of the specific Assembly to consider.',
            'type'              => 'many2one',
            'foreign_object'    => 'realestate\governance\Assembly',
            'required'          => true
        ],
        'owner_id' => [
            'description'       => 'Optional identifier of the owner for whom the search is requested (to be excluded).',
            'type'              => 'many2one',
            'foreign_object'    => 'realestate\ownership\Owner',
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

$assembly = Assembly::id($params['id'])
    ->read(['condo_id', 'assembly_date'])
    ->first();

if(!$assembly) {
    throw new Exception('unknown_assembly', EQ_ERROR_UNKNOWN_OBJECT);
}


// 1) retrieve all owners from the related condominium
$map_ownerships_ids = [];

$domain = new Domain($params['domain']);

$domain->merge(new Domain([
        [
            ['condo_id', '=', $assembly['condo_id']],
            ['date_to', '=', null]
        ],
        [
            ['condo_id', '=', $assembly['condo_id']],
            ['date_to', '<=', $assembly['assembly_date']]
        ]
    ]));

$ownerships_ids = Ownership::search($domain->toArray())->ids();

foreach($ownerships_ids as $ownership_id) {
    $map_ownerships_ids[$ownership_id] = true;
}

// remove ownerships for which we already have a mandate
$mandates = AssemblyMandate::search([['assembly_id', '=', $params['id']], ['status', '=', 'validated']])
    ->read(['ownership_id']);

foreach($mandates as $mandate_id => $mandate) {
    if(isset($map_ownerships_ids[$mandate['ownership_id']])) {
        unset($map_ownerships_ids[$mandate['ownership_id']]);
    }
}

$ownerships = Ownership::ids(array_keys($map_ownerships_ids))->read(['owners_ids']);

$map_owners_ids = [];

foreach($ownerships as $ownership_id => $ownership) {
    foreach($ownership['owners_ids'] as $owner_id) {
        $map_owners_ids[$owner_id] = true;
    }
}

// prevent having current owner amongst the results
if(isset($params['owner_id']) && isset($map_owners_ids[$params['owner_id']])) {
    unset($map_owners_ids[$params['owner_id']]);
}

$map_identities_ids = [];

$owners = Owner::ids(array_keys($map_owners_ids))
    ->read([
        'name',
        'identity_id',
        'ownership_id'
    ])
    ->adapt('json')
    ->get(true);

// 2) filter out identities already marked as attendee of for whom we have received a mandate

$attendees = AssemblyAttendee::search([
        ['assembly_id', '=', $params['id']],
        ['assembly_id', '=', $params['id']]
    ])
    ->read(['identity_id']);

foreach($attendees as $attendee_id => $attendee) {
    $map_identities_ids[$attendee['identity_id']] = true;
}

foreach($owners as $index => $owner) {
    if(isset($map_identities_ids[$owner['identity_id']])) {
        unset($owners[$index]);
    }
}

$context->httpResponse()
        ->body(array_values($owners))
        ->send();

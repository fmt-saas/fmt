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
    'description'   => 'Retrieve the list of Owners that can be selected as attendee or to be represented through a mandate.',
    'params'        => [
        'id' => [
            'description'       => 'Identifier of the specific Assembly to consider.',
            'type'              => 'many2one',
            'foreign_object'    => 'realestate\governance\Assembly',
            'required'          => true
        ],
        'domain' => [
            'description'       => 'Optional conditional domain to apply on Ownerships.',
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

$map_ownerships_ids = array_fill_keys($ownerships_ids, true);

// remove ownerships for which we already have a mandate
$mandates = AssemblyMandate::search([
        ['assembly_id', '=', $params['id']],
        ['attendee_id', '<>', null],
        ['ownership_id', '<>', null],
        // #memo - we should filter on validated mandates (to allow encoding of a mandate while another was a mistake), but it leads to possibility of picking the same owner several times
        // ['status', '=', 'validated']
    ])
    ->read(['ownership_id'])
    ->get(true);


foreach($mandates as $index => $mandate) {
    if(isset($map_ownerships_ids[$mandate['ownership_id']])) {
        unset($map_ownerships_ids[$mandate['ownership_id']]);
    }
}


// 2) filter out ownerships that relates to an existing attendee
$attendees = AssemblyAttendee::search([
        ['assembly_id', '=', $params['id']]
    ])
    ->read(['identity_id']);


foreach($attendees as $attendee_id => $attendee) {
    $owners = Owner::search([
            ['condo_id', '=', $assembly['condo_id']],
            ['identity_id', '=', $attendee['identity_id']]
        ])
        ->read(['ownership_id']);

    foreach($owners as $owner_id => $owner) {
        if(isset($map_ownerships_ids[$owner['ownership_id']])) {
            unset($map_ownerships_ids[$owner['ownership_id']]);
        }
    }
}

$ownerships = Ownership::ids(array_keys($map_ownerships_ids))
    ->read(['name', 'statutory_shares'])
    ->adapt('json')
    ->get(true);

$context->httpResponse()
        ->body(array_values($ownerships))
        ->send();

<?php

use hr\employee\Employee;
use hr\role\RoleAssignment;
use hr\Team;
use identity\Identity;
use identity\Organisation;
use identity\User;
use realestate\management\ManagingAgent;


['orm' => $orm] = eQual::inject(['orm']);

$events = $orm->disableEvents();

$check_config = file_get_contents(EQ_BASEDIR.'/packages/fmt/init/config/check-init-config.json');
if($check_config) {
    $check_config = json_decode($check_config, true);
}

$main_identity_data = [
    'type_id'               => 3,
    'type'                  => 'CO',
    'legal_name'            => 'Nom de votre organisation',
    'short_name'            => 'Votre nom',
    'registration_number'   => '0755885564',
    'has_parent'            => false,
    'nationality'           => 'BE',
    'lang_id'               => 2,
    'address_street'        => 'Rue de l\'Eglise 1',
    'address_zip'           => '1000',
    'address_city'          => 'Bruxelles',
    'address_country'       => 'BE',
    'has_vat'               => true,
    'vat_number'            => 'BE0755885564',
    'is_active'             => true
];

if($check_config) {
    $main_identity_data = array_merge($main_identity_data, $check_config['main_identity']['default_values']);
}

// Main organisation
$identity = Identity::create($main_identity_data)->first();

$organisation = Organisation::create([
        'id'                    => 1,
        'identity_id'           => $identity['id']
    ])
    ->do('sync_from_identity')
    ->first();

$managing_agent_data = [
    'address_street'        => 'Rue de l\'Eglise 1',
    'address_zip'           => '1000',
    'address_city'          => 'Bruxelles',
    'address_country'       => 'BE'
];
foreach(array_keys($managing_agent_data) as $field) {
    if(!empty($main_identity_data[$field])) {
        $managing_agent_data[$field] = $main_identity_data[$field];
    }
}

$managingAgent = ManagingAgent::create(array_merge(['identity_id' => $identity['id']], $managing_agent_data))
    ->do('sync_from_identity')
    ->first();

Identity::id($identity['id'])->update([
        'organisation_id'       => $organisation['id'],
        'managing_agent_id'     => $managingAgent['id']
    ]);

// create Team
Team::create([
        'name' => 'Équipe principale'
    ]);

$orm->enableEvents($events);

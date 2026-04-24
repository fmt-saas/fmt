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



// first Employee

$employee = Employee::create()
    ->first();

$identity = Identity::create([
        'type_id'           => 1,
        'type'              => 'IN',
        'firstname'         => 'First',
        'lastname'          => 'Employee',
        'email'             => 'user@fmtsolutions.be',
        'has_parent'        => false,
        'nationality'       => 'BE',
        'lang_id'           => 2,
        'address_country'   => 'BE',
        'has_vat'           => false,
        'is_active'         => true,
        'employee_id'       => $employee['id']
    ])
    ->read(['name', 'email'])
    ->first();

Employee::id($employee['id'])
    ->update(['identity_id' => $identity['id']])
    ->do('sync_from_identity');

User::create([
        'login'         => $identity['email'],
        'language'      => 'fr',
        'validated'     => true,
        'instance_id'   => 1,
        // users
        'groups_ids'    => [2]
    ])
    ->update(['identity_id' => $identity['id']])
    ->do('sync_from_identity');


// create Team
$team = Team::create([
        'name' => 'Équipe principale'
    ])
    ->update(['employees_ids' => [$employee['id']]])
    ->first();


// assign employee as accountant for all condos
RoleAssignment::create([
        'condo_id'      => null,
        // accountant
        'role_id'       => 3
    ])
    ->update([
        'employee_id'   => $employee['id']
    ]);

// assign employee as condo_manager for all condos
RoleAssignment::create([
        'condo_id'      => null,
        // condo_manager
        'role_id'       => 4
    ])
    ->update([
        'employee_id'   => $employee['id']
    ]);

// assign employee as document_dispatch_officer for all condos
RoleAssignment::create([
        'condo_id'      => null,
        // document_dispatch_officer
        'role_id'       => 9
    ])
    ->update([
        'employee_id'   => $employee['id']
    ]);


$orm->enableEvents($events);

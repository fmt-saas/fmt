<?php

use hr\employee\Employee;
use hr\role\RoleAssignment;
use hr\Team;
use identity\Identity;
use identity\Organisation;
use identity\User;
use purchase\supplier\Supplier;
use realestate\management\ManagingAgent;

// Main organisation
$identity = Identity::create([
        'id'                    => 1,
        'type_id'               => 3,
        'type'                  => 'CO',
        'registration_number'   => '0755885564',
        'has_parent'            => false,
        'nationality'           => 'BE',
        'lang_id'               => 2,
        'address_country'       => 'BE',
        'has_vat'               => true,
        'is_active'             => true
    ])
    ->first();

Organisation::create([
        "identity_id" => $identity['id']
    ])
    ->do('sync_from_identity');

ManagingAgent::create([
        "identity_id" => $identity['id']
    ])
    ->do('sync_from_identity');

Supplier::create([
        "identity_id" => $identity['id']
    ])
    ->do('sync_from_identity');


// first Employee

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
        'is_active'         => true
    ])
    ->read(['name', 'email'])
    ->first();

$employee = Employee::create([
        "identity_id" => $identity['id']
    ])
    ->do('sync_from_identity')
    ->first();

User::create([
        'login'         => $identity['email'],
        'language'      => 'fr',
        'validated'     => true,
        // users
        'groups_ids'    => [2]
    ])
    ->update(['identity_id' => $identity['id']])
    ->do('sync_from_identity');


// create Team
$team = Team::create([
        'name' => 'Equipe principale'
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

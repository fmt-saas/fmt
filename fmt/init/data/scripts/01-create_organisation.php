<?php

use hr\employee\Employee;
use identity\Identity;
use identity\Organisation;
use realestate\management\ManagingAgent;

// Main organisation
$identity = Identity::create([
        'id'                => 1,
        'type_id'           => 3,
        'type'              => 'CO',
        'has_parent'        => false,
        'nationality'       => 'BE',
        'lang_id'           => 2,
        'address_country'   => 'BE',
        'has_vat'           => true,
        'is_active'         => true
    ])
    ->first();

Organisation::create([
        "identity_id" => $identity['id']
    ])
    ->first();

ManagingAgent::create([
    "identity_id" => $identity['id']
]);

// first Employee

$identity = Identity::create([
        'type_id'           => 1,
        'type'              => 'IN',
        'firstname'         => 'First',
        'lastname'          => 'Employee',
        'has_parent'        => false,
        'nationality'       => 'BE',
        'lang_id'           => 2,
        'address_country'   => 'BE',
        'has_vat'           => false,
        'is_active'         => true
    ])
    ->first();

$employee = Employee::create([
        "identity_id" => $identity['id']
    ])
    ->do('sync_from_identity')
    ->first();

eQual::run('do', 'hr_employee_Employee_create-user', ['id' => $employee]);
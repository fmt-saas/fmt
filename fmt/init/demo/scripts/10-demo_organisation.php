<?php

use finance\bank\BankAccount;
use hr\employee\Employee;
use hr\role\RoleAssignment;
use hr\Team;
use identity\Identity;
use identity\Organisation;
use identity\User;
use realestate\management\ManagingAgent;

['orm' => $orm] = eQual::inject(['orm']);

$events = $orm->disableEvents();


// Main organisation

$identity = Identity::create([
        "id" => 1,
        "type_id" => 3,
        "type" => "CO",
        "legal_name" => "Perfect Syndic SRL",
        "short_name" => "Perfect Syndic",
        "has_parent" => false,
        "bank_account_iban" => "BE08457219881558",
        "bank_account_bic" => "GKCCBEBB",
        "nationality" => "BE",
        "lang_id" => 2,
		"address_street" => "Rue des Éloges, 27",
		"address_city" => "Watermael-Boitsfort",
		"address_zip" => "1170",
        "address_country" => "BE",
        "has_vat" => true,
        "vat_number" => "BE0755885564",
        "email" => "immo.services@example.com",
        "phone" => "+32487654312",
        "is_active" => true
    ])
    ->first();

Organisation::create([
        "identity_id" => $identity['id']
    ])
    ->first();

BankAccount::create([
        "owner_identity_id" => $identity['id'],
        "description"   =>  "Compte à vue",
        "bank_account_iban" =>  "BE23510349013565"
    ]);

$managingAgent = ManagingAgent::create([
        "id"                    => 1,
        "supplier_type_id"      => 1,
        "identity_id"           => 1,
        'agent_identity_type'   => 'professional'
    ])
    ->first();

Identity::search()->do('refresh_bank_accounts');

// create Team
$team = Team::create([
        'name' => 'Equipe principale'
    ])
    ->first();

// create Employee
$identity = Identity::create([
        "type_id" => 3,
        "type" => "IN",
        "firstname" => "Jean",
        "lastname" => "Louis",
        "lang_id" => 2
    ])
    ->first();

$user = User::create([
        'identity_id'   => $identity['id'],
        'login'         => 'admin@fmt.yb.run',
        'password'      => 'safe_pass',
        'language'      => 'fr',
        'validated'     => true,
        'groups_ids'    => [2]
    ])
    ->first();

// will create related Identity
$employee = Employee::create([
        'identity_id'   => $identity['id'],
    ])
    ->first();


User::search()->do('sync_from_identity');
Employee::search()->do('sync_from_identity');

Team::id($team['id'])->update(['employees_ids' => $employee['id']]);

// assign employee as manager for all condos
RoleAssignment::create([
        'condo_id'      => null,
        'employee_id'   => $employee['id'],
        'role_id'       => 1
    ]);



$orm->enableEvents($events);

// sync values from Identities
Organisation::search()->do('sync_from_identity');
ManagingAgent::id($managingAgent['id'])->do('sync_from_identity');

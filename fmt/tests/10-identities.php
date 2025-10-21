<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use hr\employee\Employee;
use identity\Identity;

$providers = eQual::inject(['context', 'orm', 'auth', 'access']);

$tests = [

    '1101' => [
            'description'       => "Create Misc Operation.",
            'help'              => "Create an accounting entry, with 2 balanced lines. Entry balance test is expected to return true.",
            'return'            => ['boolean'],
            'arrange'           => function() use($providers) {

                    $identity = Identity::create([
                        "type_id"   => 1,
                        "type"      => "IN",
                        "lang_id"   => 2
                    ])->first();

                    return $identity['id'];
                },
            'act'               => function($identity_id) use($providers) {
                    Employee::create()->update(['identity_id' => $identity_id]);

                    return $identity_id;
                },
            'assert'            => function($identity_id) use($providers) {
                    $employee = Employee::search([['identity_id', '=', $identity_id]])->first();
                    $identity = Identity::id($identity_id)->read(['employee_id'])->first();

                    return $identity['employee_id'] == $employee['id'];
                },
            'rollback'          => function() use($providers) {
                }
        ],

];
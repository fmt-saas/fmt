<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use hr\employee\Employee;
use identity\User;
use infra\server\Instance;
use realestate\ownership\Owner;
use realestate\ownership\Ownership;
use realestate\property\Condominium;

[$params, $providers] = eQual::announce([
    'description'   => 'Retrieve the list of current user, using the global token (intended for Global instance).',
    'params'        => [
    ],
    'access' => [
        'visibility'        => 'protected'
    ],
    'response'      => [
        'accept-origin' => '*',
        'content-type'  => 'application/json'
    ],
    'constants'     => ['FMT_INSTANCE_TYPE', 'AUTH_SECRET_KEY'],
    'providers'     => ['context', 'orm', 'auth']
]);

['context' => $context, 'orm' => $orm, 'auth' => $auth] = $providers;

$result = [];

if(constant('FMT_INSTANCE_TYPE') === 'global') {
    // fetch the instances of the user (based on global_token)
    $instances = eQual::run('get', 'fmt_user_instances');

    $instances_ids = [];

    foreach($instances as $instance) {
        $instances_ids[] = $instance['id'];
    }

    $result = Condominium::search(['instance_id', 'in', $instances_ids])
        ->read(['id', 'name', 'code', 'instance_id' => ['id', 'name', 'url']])
        ->adapt('json')
        ->get(true);
}
else {
    $owner_id = null;
    $user_id = $auth->userId();

    $user = User::id($user_id)
        ->read(['identity_id' => ['owners_ids', 'employee_id']])
        ->first();

    if($user) {
        $condos_ids = [];

        $owners_ids = $user['identity_id']['owners_ids'] ?? [];
        $employee_id = $user['identity_id']['employee_id'] ?? null;

        if($employee_id) {
            $employee = Employee::id($employee_id)
                ->read(['role_assignments_ids' => ['@domain' => ['is_external', '=', false], 'role_code', 'condo_id']])
                ->first();

            foreach($employee['role_assignments_ids'] as $roleAssignment) {
                $condos_ids[] = $roleAssignment['condo_id'];
            }
        }
        elseif(count($owners_ids)) {
            $condos_ids = array_filter(
                array_column(
                    Owner::ids($owners_ids)->read(['condo_id'])->get(true),
                    'condo_id'
                )
            );
        }

        $result = Condominium::ids($condos_ids)
            ->read(['id', 'name', 'code'])
            ->adapt('json')
            ->get(true);

    }
}


$context
    ->httpResponse()
    ->body($result)
    ->send();

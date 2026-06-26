<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use communication\template\Template;
use core\Task;
use documents\DocumentSubtype;
use documents\DocumentType;
use finance\bank\Bank;
use infra\server\Instance;
use infra\server\InstanceCheck;
use purchase\supplier\Supplier;

[$params, $providers] = eQual::announce([
    'description'       => "Refreshes the status of the main instance.",
    'help'              => "The main instance is supposed to be the first one.",
    'params'            => [
    ],
    'access'            => [
        'visibility'        => 'protected'
    ],
    'response'          => [
        'content-type'      => 'application/json',
        'charset'           => 'utf-8',
        'accept-origin'     => '*'
    ],
    'constants'         => [
        'ENV_MODE',
        'FILE_STORAGE_MODE',
        'SERVICE_ORM_COLLECTION_CLASS',
        'SERVICE_ACCESS_ACCESSCONTROLLER',
        'AUTH_SECRET_KEY',
        'GOOGLE_DOCAI_PRIVATE_KEY',
        'GOOGLE_DOCAI_CLIENT_EMAIL',
        'GOOGLE_DOCAI_PROJECT_ID',
        'GOOGLE_DOCAI_PROCESSOR_ID',
        'GOOGLE_GMAIL_CLIENT_ID',
        'GOOGLE_GMAIL_CLIENT_SECRET',
        'MS_TENANT_ID',
        'MS_OUTLOOK_CLIENT_ID',
        'MS_OUTLOOK_CLIENT_SECRET'
    ],
    'providers'         => ['context']
]);

/**
 * @var \equal\php\Context $context
 */
['context' => $context] = $providers;

$main_instance = Instance::id(1)
    ->read(['id'])
    ->first();

if(!$main_instance) {
    throw new Exception('main_instance_missing', EQ_ERROR_INVALID_CONFIG);
}

$allowed_equal_branches = ['2.0.1'];
$allowed_fmt_branches = ['main'];

$data = [];
$checks = [];

$set_check = function($name, $description, $value) use (&$checks) {
    $checks[$name] = [
        'name'          => $name,
        'description'   => $description,
        'value'         => (bool) $value
    ];
};

$logs = [
    'branch_equal'      => ['allowed' => $allowed_equal_branches],
    'branch_fmt'        => ['allowed' => $allowed_fmt_branches],
    'mismatch_configs'  => [],
    'missing_configs'   => [],
    'entities'          => [],
    'tasks'             => []
];

/*
    eQual branch
*/

$equal_version_data = eQual::run('get', 'core_version');

if(!empty($equal_version_data['branch'])) {
    $data['branch_equal'] = $equal_version_data['branch'];
    $set_check(
        'is_branch_equal_ok',
        'Is the eQual git branch version ok to use.',
        in_array($data['branch_equal'], $allowed_equal_branches)
    );
}

if(isset($equal_version_data['up_to_date'])) {
    $set_check(
        'is_branch_equal_up_to_date',
        'Is the eQual git branch up to date.',
        $equal_version_data['up_to_date']
    );
}


/*
    FMT branch
*/

$fmt_version_data = eQual::run('get', 'fmt_version');

if(!empty($fmt_version_data['branch'])) {
    $data['branch_fmt'] = $fmt_version_data['branch'];
    $set_check(
        'is_branch_fmt_ok',
        'Is the FMT git branch version ok to use.',
        in_array($data['branch_fmt'], $allowed_fmt_branches)
    );
}

if(isset($fmt_version_data['up_to_date'])) {
    $set_check(
        'is_branch_fmt_up_to_date',
        'Is the FMT git branch up to date.',
        $fmt_version_data['up_to_date']
    );
}


/*
    Configuration file
*/

$expected_config = [
    'ENV_MODE'                          => 'production',
    'FILE_STORAGE_MODE'                 => 'DB',
    'SERVICE_ORM_COLLECTION_CLASS'      => 'fmt\orm\Collection',
    'SERVICE_ACCESS_ACCESSCONTROLLER'   => 'fmt\access\AccessController',
];

$is_config_ok = true;
foreach($expected_config as $key => $expected_value) {
    $value = constant($key);
    if($value !== $expected_value) {
        $is_config_ok = false;

        $logs['mismatch_configs'][] = sprintf("Config %s has value '%s' but '%s' is expected", $key, $value, $expected_value);
    }
}

$required_config = [
    'AUTH_SECRET_KEY',
    'GOOGLE_DOCAI_PRIVATE_KEY',
    'GOOGLE_DOCAI_CLIENT_EMAIL',
    'GOOGLE_DOCAI_PROJECT_ID',
    'GOOGLE_DOCAI_PROCESSOR_ID',
    'GOOGLE_GMAIL_CLIENT_ID',
    'GOOGLE_GMAIL_CLIENT_SECRET',
    'MS_TENANT_ID',
    'MS_OUTLOOK_CLIENT_ID',
    'MS_OUTLOOK_CLIENT_SECRET',
];

foreach($required_config as $key) {
    if(!defined($key) || empty(constant($key))) {
        $is_config_ok = false;

        $logs['mismatch_configs'][] = sprintf('Config %s is required', $key);
    }
}

$set_check(
    'is_config_file_ok',
    'Is the configuration file valid.',
    $is_config_ok
);


/*
    Data
*/

$entities = [
    DocumentType::getType(),
    DocumentSubtype::getType(),
    Supplier::getType(),
    Bank::getType(),
    Template::getType()
];

$is_required_data_ok = true;
foreach($entities as $entity) {
    $ids = $entity::search()->ids();
    if(!count($ids)) {
        $is_required_data_ok = false;
    }

    $logs['entities'][$entity] = count($ids);
}

$set_check(
    'is_required_data_ok',
    'Are the required data correctly configured.',
    $is_required_data_ok
);


/*
    Tasks
*/

$required_tasks = [
    'core_spool_run',
    'core_spool_sync-alerts',
    'documents_export_cron_run',
    'finance_accounting_generate-expense-statements',
    'realestate_funding_verify-expired-fundings',
    'infra_server_refresh-self-status'
];

$tasks = Task::search([
        ['controller', 'in', $required_tasks],
        ['is_recurring', '=', true]
    ])
    ->read(['controller'])
    ->get();

$is_tasks_ok = true;
foreach($required_tasks as $required_task) {
    $task_found = false;
    foreach($tasks as $task) {
        if($task['controller'] === $required_task) {
            $task_found = true;
            break;
        }
    }

    if(!$task_found) {
        $is_tasks_ok = false;

        $logs['tasks'][] = sprintf("The task '%s' is missing or not recurring.", $required_task);
    }
}

$set_check(
    'is_tasks_ok',
    'Are the recurring tasks correctly configured.',
    $is_tasks_ok
);


/*
    Update instance data
*/

Instance::id(1)->update(
    array_merge(
        ['refreshed' => time(), 'refreshed_logs' => json_encode($logs, JSON_PRETTY_PRINT)],
        $data
    )
);

foreach($checks as $check) {
    $existing_check = InstanceCheck::search([
            ['instance_id', '=', $main_instance['id']],
            ['name', '=', $check['name']]
        ])
        ->read(['id'])
        ->first();

    if($existing_check) {
        InstanceCheck::id($existing_check['id'])->update([
            'description'   => $check['description'],
            'value'         => $check['value']
        ]);
    }
    else {
        InstanceCheck::create([
            'instance_id'   => $main_instance['id'],
            'name'          => $check['name'],
            'description'   => $check['description'],
            'value'         => $check['value']
        ]);
    }
}

/*
    Create response
*/

$result = eQual::run('get', 'infra_server_self-status');

$context
    ->httpResponse()
    ->body($result)
    ->status(200)
    ->send();

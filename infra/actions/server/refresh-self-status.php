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
    // #memo - placing required constants here would block the script if missing
    'constants'         => [],
    'providers'         => ['context']
]);

/**
 * @var \equal\php\Context $context
 */
['context' => $context] = $providers;

$instance = Instance::id(1)
    ->read(['id'])
    ->first();

if(!$instance) {
    throw new Exception('instance_missing', EQ_ERROR_INVALID_CONFIG);
}


/**
 * REQUIREMENTS DEFINITION
 */

$allowed_equal_branches = ['2.0.1'];
$allowed_fmt_branches = ['main'];

$required_config = [
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
    'MS_OUTLOOK_CLIENT_SECRET',
];

$expected_config_values = [
    'ENV_MODE'                          => 'production',
    'FILE_STORAGE_MODE'                 => 'DB',
    'SERVICE_ORM_COLLECTION_CLASS'      => 'fmt\orm\Collection',
    'SERVICE_ACCESS_ACCESSCONTROLLER'   => 'fmt\access\AccessController',
];

$required_entities = [
    DocumentType::getType(),
    DocumentSubtype::getType(),
    Supplier::getType(),
    Bank::getType(),
    Template::getType()
];

$required_tasks = [
    'fmt_spool_run',
    'core_spool_sync-alerts',
    'documents_export_cron_run',
    'finance_accounting_generate-expense-statements',
    'realestate_funding_verify-expired-fundings',
    'infra_server_refresh-self-status',
    'infra_quota_refresh-values',
    'infra_quota_shift-periods'
];


/**
 * CHECKS
 */

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

$is_config_ok = true;
foreach($required_config as $key) {
    if(empty($GLOBALS['EQ_CONFIG_ARRAY'][$key])) {
        $is_config_ok = false;
        $logs['missing_configs'][] = sprintf('Config %s is required', $key);
    }
}

foreach($expected_config_values as $key => $expected_value) {
    if(empty($GLOBALS['EQ_CONFIG_ARRAY'][$key])) {
        $is_config_ok = false;
        $logs['missing_configs'][] = sprintf('Config %s is required', $key);
    }
    else {
        $value = $GLOBALS['EQ_CONFIG_ARRAY'][$key];
        if($value !== $expected_value) {
            $is_config_ok = false;
            $logs['mismatch_configs'][] = sprintf("Config %s has value '%s' but '%s' is expected", $key, $value, $expected_value);
        }
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

$is_required_data_ok = true;
foreach($required_entities as $entity) {
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

$tasks = Task::search(['controller', 'in', $required_tasks])
    ->read(['controller', 'is_recurring', 'is_active'])
    ->get();

$is_tasks_ok = true;
foreach($required_tasks as $required_task) {
    $task = null;
    foreach($tasks as $ta) {
        if($ta['controller'] === $required_task) {
            $task = $ta;
            break;
        }
    }

    if(!$task) {
        $is_tasks_ok = false;
        $logs['tasks'][] = sprintf("The task '%s' is missing.", $required_task);
    }
    elseif(!$task['is_recurring']) {
        $is_tasks_ok = false;
        $logs['tasks'][] = sprintf("The task '%s' is not recurring.", $required_task);
    }
    elseif(!$task['is_active']) {
        $is_tasks_ok = false;
        $logs['tasks'][] = sprintf("The task '%s' is not active.", $required_task);
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
            ['instance_id', '=', $instance['id']],
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
            'instance_id'   => $instance['id'],
            'name'          => $check['name'],
            'description'   => $check['description'],
            'value'         => $check['value']
        ]);
    }
}

$context
    ->httpResponse()
    ->status(201)
    ->send();

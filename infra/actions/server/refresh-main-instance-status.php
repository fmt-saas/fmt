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

$instance = Instance::id(1)
    ->read(['name'])
    ->first();

if(!isset($instance)) {
    throw new Exception("main_instance_not_found", EQ_ERROR_UNKNOWN_OBJECT);
}

$allowed_equal_branches = ['2.0.1'];
$allowed_fmt_branches = ['main'];


/*
    eQual branch
*/

$equal_version_data = eQual::run('get', 'core_version');

if(!empty($equal_version_data['branch'])) {
    $data['branch_equal'] = $equal_version_data['branch'];
    $data['is_branch_equal_ok'] = in_array($data['branch_equal'], $allowed_equal_branches);
}

if(isset($equal_version_data['up_to_date'])) {
    $data['is_branch_equal_up_to_date'] = $equal_version_data['up_to_date'];
}


/*
    FMT branch
*/

$fmt_version_data = eQual::run('get', 'fmt_version');

if(!empty($fmt_version_data['branch'])) {
    $data['branch_fmt'] = $fmt_version_data['branch'];
    $data['is_branch_fmt_ok'] = in_array($data['branch_fmt'], $allowed_fmt_branches);
}

if(isset($fmt_version_data['up_to_date'])) {
    $data['is_branch_fmt_up_to_date'] = $fmt_version_data['up_to_date'];
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
    if(constant($key) !== $expected_value) {
        $is_config_ok = false;
        break;
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
        break;
    }
}

$data['is_config_file_ok'] = $is_config_ok;


/*
    Data
*/

$document_types_ids = DocumentType::search()->ids();
$document_sub_types_ids = DocumentSubtype::search()->ids();
$suppliers_ids = Supplier::search()->ids();
$banks_ids = Bank::search()->ids();
$templates_ids = Template::search()->ids();

$data['is_required_data_ok'] = count($document_types_ids)
    && count($document_sub_types_ids)
    && count($suppliers_ids)
    && count($banks_ids)
    && count($templates_ids);


/*
    Tasks
*/

$required_tasks = [
    'core_spool_run',
    'core_spool_sync-alerts',
    'documents_export_cron_run',
    'realestate_funding_verify-expired-fundings'
];

$tasks_ids = Task::search(['controller', 'in', $required_tasks])->ids();
$data['is_tasks_ok'] = count($tasks_ids) === count($required_tasks);


/*
    Update instance data
*/

Instance::id(1)->update($data);

/*
    Create response
*/

$result = Instance::id(1)
    ->read(array_keys($data))
    ->adapt('json')
    ->first(true);

$context
    ->httpResponse()
    ->body($result)
    ->status(200)
    ->send();

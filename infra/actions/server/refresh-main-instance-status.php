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

exec("cd /var/www/html && git branch --show-current", $branch_equal_output);
$data['branch_equal'] = $branch_equal_output[0] ?? null;
$data['is_branch_equal_ok'] = in_array($data['branch_equal'], $allowed_equal_branches);

exec("git -C /var/www/html fetch");
exec("git -C /var/www/html rev-parse HEAD", $local);
exec("git -C /var/www/html rev-parse @{u}", $remote);
$data['is_branch_equal_up_to_date'] = $local[0] === $remote[0];


/*
    FMT branch
*/

exec("cd /var/www/html/packages && git branch --show-current", $branch_fmt_output);
$data['branch_fmt'] = $branch_fmt_output[0] ?? null;
$data['is_branch_fmt_ok'] = in_array($data['branch_fmt'], $allowed_fmt_branches);

exec("git -C /var/www/html/packages fetch");
exec("git -C /var/www/html/packages rev-parse HEAD", $local);
exec("git -C /var/www/html/packages rev-parse @{u}", $remote);
$data['is_branch_fmt_up_to_date'] = $local[0] === $remote[0];


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
    if(empty(constant($key))) {
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

$context
    ->httpResponse()
    ->status(204)
    ->send();

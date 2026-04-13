<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use equal\http\HttpRequest;
use fmt\sync\SyncPolicy;
use fmt\sync\SyncPolicyCondition;
use fmt\sync\SyncPolicyLine;

[$params, $providers] = eQual::announce([
    'description'   => 'Pull the all the SyncPolicy from GLOBAL instance to local FMT instance.',
    'help'          => 'This action connects to the GLOBAL instance and pulls all SyncPolicy.',
    'params'        => [
        'reset' => [
            'type'              => 'boolean',
            'description'       => 'Remove existing SyncPolicies.',
            'default'           => false
        ]
    ],
    'access' => [
        'visibility'    => 'private'
    ],
    'response'      => [
        'accept-origin' => '*',
        'content-type'  => 'application/json'
    ],
    'constants'     => ['FMT_INSTANCE_TYPE', 'FMT_API_INTERNAL_TOKEN', 'FMT_API_URL_GLOBAL'],
    'providers'     => ['context', 'orm']
]);

/**
 * @var \equal\php\Context          $context
 * @var \equal\orm\ObjectManager    $orm
 */
['context' => $context, 'orm' => $orm] = $providers;

if(constant('FMT_INSTANCE_TYPE') !== 'agency') {
    throw new Exception('invalid_instance_type', EQ_ERROR_NOT_ALLOWED);
}

if($params['reset']) {
    SyncPolicy::search()->delete(true);
    SyncPolicyLine::search()->delete(true);
}
else {
    $sync_policies_ids = SyncPolicy::search()->ids();
    if(!empty($sync_policies_ids)) {
        throw new Exception('sync_policies_already_exist', EQ_ERROR_INVALID_PARAM);
    }
}

try {
    $syncPolicyModel = $orm->getModel(SyncPolicy::class);
    $sync_policy_schema = $syncPolicyModel->getSchema();

    $syncPolicyLineModel = $orm->getModel(SyncPolicyLine::class);
    $sync_policy_line_schema = $syncPolicyLineModel->getSchema();

    $syncPolicyConditionModel = $orm->getModel(SyncPolicyCondition::class);
    $sync_policy_condition_schema = $syncPolicyConditionModel->getSchema();

    $fields = array_keys($sync_policy_schema);
    $fields['sync_policy_lines_ids'] = array_keys($sync_policy_line_schema);
    $fields['sync_policy_conditions_ids'] = array_keys($sync_policy_condition_schema);

    $request = new HttpRequest('GET ' . rtrim(constant('FMT_API_URL_GLOBAL'), '/') . '/?get=core_model_collect' .
        '&entity=' . urlencode(SyncPolicy::class) .
        '&fields=' . urlencode(json_encode($fields))
    );

    $request
        ->header('Content-Type', 'application/json')
        ->header('Authorization', 'Bearer ' . constant('FMT_API_INTERNAL_TOKEN'));

    $response = $request->send();
    $data = $response->body();

    foreach($data as $sync_policy_data) {
        $lines_data = $sync_policy_data['sync_policy_lines_ids'];
        unset($sync_policy_data['sync_policy_lines_ids']);

        $conditions_data = $sync_policy_data['sync_policy_conditions_ids'];
        unset($sync_policy_data['sync_policy_conditions_ids']);

        foreach($sync_policy_data as $field => $value) {
            if(in_array($field, ['id', 'creator', 'modifier', 'created', 'modified', 'state', 'deleted', 'last_pull'])) {
                unset($sync_policy_data[$field]);
            }
        }

        $syncPolicy = SyncPolicy::create($sync_policy_data)->first();
        foreach($lines_data as $line_data) {
            foreach($line_data as $field => $value) {
                if(in_array($field, ['id', 'creator', 'modifier', 'created', 'modified', 'state', 'deleted'])) {
                    unset($line_data[$field]);
                }
            }

            SyncPolicyLine::create(array_merge(
                $line_data,
                ['sync_policy_id' => $syncPolicy['id']]
            ));
        }
        foreach($conditions_data as $condition_data) {
            foreach($condition_data as $field => $value) {
                if(in_array($field, ['id', 'creator', 'modifier', 'created', 'modified', 'state', 'deleted'])) {
                    unset($condition_data[$field]);
                }
            }

            if(is_null($condition_data['value'])) {
                $condition_data['value'] = 'NULL';
            }

            SyncPolicyCondition::create(array_merge(
                $condition_data,
                ['sync_policy_id' => $syncPolicy['id']]
            ));
        }
    }
}
catch(Exception $e) {
    trigger_error("APP::error while fetching or creating sync policies: " . $e->getMessage(), EQ_REPORT_ERROR);
    throw new Exception('unable_to_create_object', EQ_ERROR_UNKNOWN);
}

$context
    ->httpResponse()
    ->status(200)
    ->send();



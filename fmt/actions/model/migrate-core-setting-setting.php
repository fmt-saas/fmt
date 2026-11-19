<?php

use equal\data\adapt\DataAdapterProviderSql;

[$params, $providers] = eQual::announce([
    'description'   => 'Migrate the model discriminator for core_setting_setting.',
    'params'        => [
        'confirm' => [
            'description'   => 'Explicit confirmation required to apply the migration.',
            'type'          => 'boolean',
            'default'       => false,
        ],
        'dry_run' => [
            'description'   => 'Report required changes without modifying the database.',
            'type'          => 'boolean',
            'default'       => false,
        ],
    ],
    'access'        => [
        'visibility'    => 'protected',
        'groups'        => ['admins'],
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'UTF-8',
        'accept-origin' => '*'
    ],
    'constants'     => ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_DBMS'],
    'providers'     => ['context', 'db']
]);

['context' => $context, 'db' => $dbConnector] = $providers;

$dry_run = $params['dry_run'] ?? false;
if(!$dry_run && ($params['confirm'] ?? false) !== true) {
    throw new Exception('missing_confirmation', EQ_ERROR_MISSING_PARAM);
}

eQual::run('do', 'test_db-access');
$db = $dbConnector->connect();
if(!$db) {
    throw new Exception('missing_database', EQ_ERROR_INVALID_CONFIG);
}

$table = 'core_setting_setting';
$reported_rows = 365;
$tables = array_fill_keys($db->getTables(), true);
if(!isset($tables[$table])) {
    throw new Exception('missing_table:' . $table, EQ_ERROR_INVALID_CONFIG);
}

$columns = $db->getTableColumns($table);
$model_column_missing = !in_array('model', $columns, true);
if($model_column_missing && !$dry_run) {
    $sql_adapter_provider = new DataAdapterProviderSql();
    $sql_type = $sql_adapter_provider->get('text/plain')->castOutType('text/plain:200');
    $db->sendQuery($db->getQueryAddColumn($table, 'model', ['type' => $sql_type, 'null' => true]));
    $columns = $db->getTableColumns($table);
    $model_column_missing = false;
}

$required_columns = ['id', 'model', 'type', 'object_class'];
$available_required_columns = $model_column_missing && $dry_run
    ? array_values(array_diff($required_columns, ['model']))
    : $required_columns;
$missing_columns = array_values(array_diff($available_required_columns, $columns));
if(count($missing_columns)) {
    throw new Exception('missing_columns:' . implode(',', $missing_columns), EQ_ERROR_INVALID_CONFIG);
}
$read_columns = $model_column_missing
    ? array_values(array_diff($required_columns, ['model']))
    : $required_columns;

$classify = static function(array $row): string {
    return 'fmt\\setting\\Setting';
};

$model_ids = [];
$row_count = 0;
$result = $db->getRecords($table, $read_columns);
while($row = $db->fetchArray($result)) {
    ++$row_count;
    $model = $classify($row);
    if(($row['model'] ?? null) !== $model) {
        $model_ids[$model][] = (int) $row['id'];
    }
}

$pending_by_model = [];
foreach($model_ids as $model => $ids) {
    $pending_by_model[$model] = count($ids);
}
$pending_rows = array_sum($pending_by_model);

$migrated_rows = 0;
if(!$dry_run && count($model_ids)) {
    $db->sendQuery('START TRANSACTION');
    try {
        foreach($model_ids as $model => $ids) {
            foreach(array_chunk($ids, 1000) as $chunk) {
                $db->setRecords($table, $chunk, ['model' => $model]);
                $migrated_rows += count($chunk);
            }
        }
        $db->sendQuery('COMMIT');
    }
    catch(Throwable $e) {
        $db->sendQuery('ROLLBACK');
        throw $e;
    }
}

if(!$dry_run) {
    $result = $db->getRecords($table, $required_columns);
    while($row = $db->fetchArray($result)) {
        if(($row['model'] ?? null) !== $classify($row)) {
            throw new Exception('model_migration_verification_failed:' . (int) $row['id'], EQ_ERROR_UNKNOWN);
        }
    }
}

$context
    ->httpResponse()
    ->status(200)
    ->body([
        'table'                => $table,
        'dry_run'              => $dry_run,
        'model_column_missing' => $model_column_missing,
        'reported_rows'        => $reported_rows,
        'current_rows'         => $row_count,
        'pending_rows'         => $pending_rows,
        'pending_by_model'     => $pending_by_model,
        'migrated_rows'        => $migrated_rows
    ])
    ->send();


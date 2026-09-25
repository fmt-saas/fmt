<?php

use equal\data\adapt\DataAdapterProviderSql;

[$params, $providers] = eQual::announce([
    'description'   => 'Migrate the model discriminator for core_mail.',
    'params'        => [
        'confirm' => [
            'description'   => 'Explicit confirmation required to execute the migration.',
            'type'          => 'boolean',
            'required'      => true,
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

if(($params['confirm'] ?? false) !== true) {
    throw new Exception('missing_confirmation', EQ_ERROR_MISSING_PARAM);
}

eQual::run('do', 'test_db-access');
$db = $dbConnector->connect();
if(!$db) {
    throw new Exception('missing_database', EQ_ERROR_INVALID_CONFIG);
}

$table = 'core_email_email';
$reported_rows = 85;
$tables = array_fill_keys($db->getTables(), true);
$source_table = 'core_mail';
if(isset($tables[$source_table]) && isset($tables[$table])) {
    throw new Exception('ambiguous_tables:' . $source_table . ',' . $table, EQ_ERROR_INVALID_CONFIG);
}
if(isset($tables[$source_table])) {
    $db->sendQuery('ALTER TABLE core_mail RENAME TO core_email_email');
    $tables = array_fill_keys($db->getTables(), true);
}
if(!isset($tables[$table])) {
    throw new Exception('missing_table:' . $table, EQ_ERROR_INVALID_CONFIG);
}

$columns = $db->getTableColumns($table);
if(!in_array('model', $columns, true)) {
    $sql_adapter_provider = new DataAdapterProviderSql();
    $sql_type = $sql_adapter_provider->get('text/plain')->castOutType('text/plain:200');
    $db->sendQuery($db->getQueryAddColumn($table, 'model', ['type' => $sql_type, 'null' => true]));
    $columns = $db->getTableColumns($table);
}

$required_columns = ['id', 'model', 'direction'];
$missing_columns = array_values(array_diff($required_columns, $columns));
if(count($missing_columns)) {
    throw new Exception('missing_columns:' . implode(',', $missing_columns), EQ_ERROR_INVALID_CONFIG);
}

$classify = static function(array $row): string {
    if(($row['direction'] ?? null) === 'incoming') {
            return 'communication\\email\\Email';
        }
        if(($row['direction'] ?? null) === 'outgoing') {
            return 'fmt\\core\\Mail';
        }
        throw new Exception('unclassifiable_row:' . (int) $row['id'], EQ_ERROR_INVALID_CONFIG);
};

$model_ids = [];
$row_count = 0;
$result = $db->getRecords($table, $required_columns);
while($row = $db->fetchArray($result)) {
    ++$row_count;
    $model = $classify($row);
    if(($row['model'] ?? null) !== $model) {
        $model_ids[$model][] = (int) $row['id'];
    }
}

$migrated_rows = 0;
if(count($model_ids)) {
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

$result = $db->getRecords($table, $required_columns);
while($row = $db->fetchArray($result)) {
    if(($row['model'] ?? null) !== $classify($row)) {
        throw new Exception('model_migration_verification_failed:' . (int) $row['id'], EQ_ERROR_UNKNOWN);
    }
}

$context
    ->httpResponse()
    ->status(200)
    ->body([
        'table'         => $table,
        'reported_rows' => $reported_rows,
        'current_rows'  => $row_count,
        'migrated_rows' => $migrated_rows
    ])
    ->send();


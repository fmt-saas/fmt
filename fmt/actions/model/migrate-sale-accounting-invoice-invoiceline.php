<?php

use equal\data\adapt\DataAdapterProviderSql;

[$params, $providers] = eQual::announce([
    'description'   => 'Migrate the model discriminator for sale_accounting_invoice_invoiceline.',
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

$table = 'sale_accounting_invoice_invoiceline';
$reported_rows = 62;
$tables = array_fill_keys($db->getTables(), true);
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

$required_columns = ['id', 'model', 'invoice_id'];
$missing_columns = array_values(array_diff($required_columns, $columns));
if(count($missing_columns)) {
    throw new Exception('missing_columns:' . implode(',', $missing_columns), EQ_ERROR_INVALID_CONFIG);
}

$invoice_table = 'sale_accounting_invoice_invoice';
if(!isset($tables[$invoice_table])) {
    throw new Exception('missing_table:' . $invoice_table, EQ_ERROR_INVALID_CONFIG);
}
$invoice_columns = $db->getTableColumns($invoice_table);
$missing_invoice_columns = array_values(array_diff(['id', 'invoice_type'], $invoice_columns));
if(count($missing_invoice_columns)) {
    throw new Exception('missing_columns:' . implode(',', $missing_invoice_columns), EQ_ERROR_INVALID_CONFIG);
}
$invoice_types = [];
$invoice_result = $db->getRecords($invoice_table, ['id', 'invoice_type']);
while($invoice = $db->fetchArray($invoice_result)) {
    $invoice_types[(int) $invoice['id']] = $invoice['invoice_type'] ?? null;
}
$classify = static function(array $row) use($invoice_types): string {
    $invoice_id = (int) ($row['invoice_id'] ?? 0);
        if(!array_key_exists($invoice_id, $invoice_types)) {
            throw new Exception('missing_parent_invoice:' . (int) $row['id'], EQ_ERROR_INVALID_CONFIG);
        }
        $models = [
            'expense_statement' => 'realestate\\funding\\ExpenseStatementOwnerLine',
            'fund_request'      => 'realestate\\funding\\FundRequestExecutionLine',
            'invoice'           => 'sale\\accounting\\invoice\\SaleInvoiceLine',
            'credit_note'       => 'sale\\accounting\\invoice\\SaleInvoiceLine',
        ];
        $invoice_type = $invoice_types[$invoice_id];
        if(!isset($models[$invoice_type])) {
            throw new Exception('unclassifiable_row:' . (int) $row['id'], EQ_ERROR_INVALID_CONFIG);
        }
        return $models[$invoice_type];
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


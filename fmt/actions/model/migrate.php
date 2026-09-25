<?php

[$params, $providers] = eQual::announce([
    'description'   => 'Run every model discriminator migration in a deterministic order.',
    'params'        => [
        'confirm' => [
            'description'   => 'Explicit confirmation required to execute all model migrations.',
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
    'providers'     => ['context']
]);

['context' => $context] = $providers;

if(($params['confirm'] ?? false) !== true) {
    throw new Exception('missing_confirmation', EQ_ERROR_MISSING_PARAM);
}

// Fail before starting the sequence when the configured database is unavailable.
eQual::run('do', 'test_db-access');

// Discover migration actions and keep their execution order stable across runs.
$migration_files = glob(__DIR__.'/migrate-*.php');
if($migration_files === false || !count($migration_files)) {
    throw new Exception('missing_model_migrations', EQ_ERROR_INVALID_CONFIG);
}
sort($migration_files, SORT_STRING);

$results = [];
foreach($migration_files as $migration_file) {
    $action_name = pathinfo($migration_file, PATHINFO_FILENAME);
    if(!preg_match('/^migrate-[a-z0-9-]+$/D', $action_name)) {
        throw new Exception('invalid_model_migration_name:' . $action_name, EQ_ERROR_INVALID_CONFIG);
    }

    $controller = 'fmt_model_' . $action_name;
    try {
        $result = eQual::run('do', $controller, ['confirm' => true]);
    }
    catch(Throwable $e) {
        $completed_actions = array_column($results, 'action');
        $message = 'model_migration_failed:' . $controller;
        if(count($completed_actions)) {
            $message .= ':completed=' . implode(',', $completed_actions);
        }
        $message .= ':reason=' . $e->getMessage();

        $code = $e->getCode() ?: EQ_ERROR_UNKNOWN;
        throw new Exception($message, $code, $e);
    }

    $results[] = [
        'action' => $controller,
        'result' => $result,
    ];
}

$context
    ->httpResponse()
    ->status(200)
    ->body([
        'migration_count' => count($results),
        'migrations'      => $results
    ])
    ->send();

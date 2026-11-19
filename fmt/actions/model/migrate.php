<?php

[$params, $providers] = eQual::announce([
    'description'   => 'Run every model discriminator migration in a deterministic order.',
    'params'        => [
        'confirm' => [
            'description'   => 'Explicit confirmation required to apply all model migrations.',
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
    'providers'     => ['context']
]);

['context' => $context] = $providers;

$dry_run = $params['dry_run'] ?? false;
if(!$dry_run && ($params['confirm'] ?? false) !== true) {
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
        $result = eQual::run('do', $controller, [
            'confirm' => !$dry_run,
            'dry_run' => $dry_run
        ]);
    }
    catch(Throwable $e) {
        if($dry_run) {
            trigger_error(
                "APP::Model migration audit failed for {$controller}: {$e->getMessage()}",
                EQ_REPORT_WARNING
            );
            $error = 'model_migration_audit_failed';
            $message = $e->getMessage();
            if(str_starts_with($message, 'missing_table:')) {
                $error = 'missing_table';
            }
            elseif(str_starts_with($message, 'missing_columns:')) {
                $error = 'missing_columns';
            }
            elseif(str_starts_with($message, 'unexpected_existing_rows:')) {
                $error = 'unexpected_existing_rows';
            }
            elseif(str_contains($message, '_unclassifiable_row:')) {
                $error = 'unclassifiable_rows';
            }
            $results[] = [
                'action' => $controller,
                'result' => [
                    'status' => 'error',
                    'error'  => $error
                ]
            ];
            continue;
        }

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

$summary = [
    'schema_changes'    => 0,
    'pending_rows'      => 0,
    'blocked_rows'      => 0,
    'failed_migrations' => 0
];
foreach($results as $migration) {
    $result = $migration['result'] ?? null;
    if(!is_array($result)) {
        continue;
    }
    if(($result['model_column_missing'] ?? false) === true) {
        ++$summary['schema_changes'];
    }
    $summary['pending_rows'] += (int) ($result['pending_rows'] ?? 0);
    $summary['blocked_rows'] += (int) ($result['blocked_rows'] ?? 0);
    if(($result['status'] ?? null) === 'error') {
        ++$summary['failed_migrations'];
    }
}

$context
    ->httpResponse()
    ->status(200)
    ->body([
        'migration_count' => count($results),
        'dry_run'         => $dry_run,
        'summary'         => $summary,
        'migrations'      => $results
    ])
    ->send();

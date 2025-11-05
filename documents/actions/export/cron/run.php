<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use documents\export\ExportingTask;
use documents\export\ExportingTaskLine;
use equal\http\HttpRequest;

[$params, $providers] = eQual::announce([
    'description' => 'Envoie un document PDF à Google Document AI et retourne le résultat.',
    'params' => [
        'id' =>  [
            'description'       => 'Optional identifier of a specific exporting task to run.',
            'type'              => 'many2one',
            'foreign_object'    => 'documents\export\ExportingTask'
        ]
    ],
    'response' => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8'
    ],
    // #todo - mark as private (only from scheduler)
    'access'        => [ 'visibility' => 'protected' ],
    'providers'     => ['context', 'orm']
]);

['context' => $context, 'orm' => $orm] = $providers;


$now = time();

// check if a task is already active, if so, do nothing and wait for the next cycle
$runningExportingTask = ExportingTask::search(['status', '=', 'running'])->first();

if($runningExportingTask) {
    // exit wit no error code
    throw new Exception('task_in_progress', 0);
}


// take the first one by creation date
$exportingTask = ExportingTask::search(['status', '=', 'idle'], ['limit' => 1, 'sort' => ['created' => 'asc']])
    ->update(['status' => 'running'])
    ->read(['exporting_task_lines_ids' => ['controller', 'params']])
    ->first();

// no task in queue
if(!$exportingTask) {
    // exit wit no error code
    throw new Exception('no_task_awaiting', 0);
}


$has_failing_line = false;

foreach($exportingTask['exporting_task_lines_ids'] as $exporting_task_line_id => $exportingTaskLine) {

    [$status, $log] = ['', ''];

    ExportingTaskLine::id($exporting_task_line_id)
        ->update([
            'pid'       => getmypid(),
            'status'    => 'running'
        ]);

    try {
        $body = json_decode($exportingTaskLine['params'], true);
        // run the task - expected to generate a PDF document
        $data = \eQual::run('do', $exportingTaskLine['controller'], $body);
        $status = 'success';
        $log = (string) json_encode($data, JSON_PRETTY_PRINT);
        if(!$data['document_id'] ?? null) {
            throw new Exception('missing_document_in_response', EQ_ERROR_UNKNOWN);
        }
        // assign generated document to Export
        // #memo - documents linked to ExportingTaskLine are not visible in the EDMS, and are meant to be removed when at ExportingTask expiry
        ExportingTaskLine::id($exporting_task_line_id)
            ->update([
                'document_id'   => $data['document_id'],
                'status'        => 'ready'
            ]);
    }
    catch(\Exception $e) {
        // error occurred during execution
        trigger_error("PHP::Error while running scheduled job [{$exportingTaskLine['id']}]: ".$e->getMessage(), QN_REPORT_ERROR);
        $has_failing_line = true;
        $status = 'error';
        $msg = $e->getMessage();
        $data = @unserialize($msg);
        if(is_array($data)) {
            $data = json_encode($data, JSON_PRETTY_PRINT);
        }
        $log = ($data) ? $data : $msg;
        ExportingTaskLine::id($exporting_task_line_id)
            ->update([
                'status'    => 'failing'
            ]);
    }

    // create a new TaskLog holding result
    $orm->create('documents\export\ExportingTaskLog', [
            'task_id'       => $exportingTask['id'],
            'task_line_id'  => $exporting_task_line_id,
            'status'        => $status,
            'log'           => "<pre>{$log}</pre>"
        ]);

}


ExportingTask::id($exportingTask['id'])
    ->update(['status' => $has_failing_line ? 'failing' : 'ready']);


$context->httpResponse()
    ->status(204)
    ->send();

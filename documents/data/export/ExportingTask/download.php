<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use documents\Document;
use documents\export\ExportingTask;
use equal\text\TextTransformer;

[$params, $providers] = eQual::announce([
    'description'   => "Checks if all owners have been invited to the target assembly.",
    'params'        => [
        'id' =>  [
            'type'              => 'many2one',
            'description'       => "The assembly the invitation refers to.",
            'foreign_object'    => 'documents\export\ExportingTask',
            'required'          => true
        ]
    ],
    'response'      => [
        'content-type'  => 'application/zip',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'dispatch']
]);

/**
 * @var \equal\php\Context                 $context
 * @var \equal\dispatch\Dispatcher         $dispatch
 */
['context' => $context, 'dispatch' => $dispatch] = $providers;

$exportingTask = ExportingTask::id($params['id'])
    ->read(['status', 'name', 'exporting_task_lines_ids' => ['status', 'document_id']])
    ->first();

if(!$exportingTask) {
    throw new Exception("unknown_exporting_task", EQ_ERROR_UNKNOWN_OBJECT);
}

if($exportingTask['status'] !== 'ready') {
    throw new Exception("non_ready_exporting_task", EQ_ERROR_UNKNOWN_OBJECT);
}

// generate the zip archive
$tmp_file = tempnam(sys_get_temp_dir(), 'zip');
$zip = new ZipArchive();
if($zip->open($tmp_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    // could not create the ZIP archive
    throw new Exception("Unable to create a ZIP file.", EQ_ERROR_UNKNOWN);
}

foreach($exportingTask['exporting_task_lines_ids'] as $exporting_task_line_id => $exportingTaskLine) {
    if($exportingTaskLine['status'] !== 'ready') {
        continue;
    }

    $document = Document::id($exportingTaskLine['document_id'])
        ->read(['name', 'data', 'extension'])
        ->first();

    $zip->addFromString($document['name'] . '.' . $document['extension'], $document['data']);
}

$zip->close();

// read raw data
$data = file_get_contents($tmp_file);
unlink($tmp_file);

$max_length = 128;
$export_name = substr(str_replace(' ', '_', TextTransformer::normalize($exportingTask['name'])), 0, $max_length);


ExportingTask::id($params['id'])->update(['is_exported' => true]);


$context->httpResponse()
        ->header('Content-Disposition', 'attachment; filename="' . $export_name . '.zip"')
        ->body($data, true)
        ->send();

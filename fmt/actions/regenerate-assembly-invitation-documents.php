<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use documents\Document;
use documents\export\ExportingTask;
use documents\export\ExportingTaskLine;
use realestate\governance\Assembly;
use realestate\governance\AssemblyInvitationCorrespondence;

[$params, $providers] = eQual::announce([
    'description' => 'Regenerate the existing invitation correspondence documents and printable exports of an assembly while preserving their relations.',
    'params'      => [
        'id' => [
            'type'           => 'many2one',
            'description'    => 'Identifier of the assembly whose invitation documents must be regenerated.',
            'foreign_object' => 'realestate\governance\Assembly',
            'required'       => true
        ]
    ],
    'access'      => [
        'visibility' => 'protected'
    ],
    'response'    => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'   => ['context']
]);

/** @var \equal\php\Context $context */
['context' => $context] = $providers;

$assembly = Assembly::id($params['id'])
    ->read(['invitations_exporting_task_id', 'ownerships_ids'])
    ->first();

if(!$assembly) {
    throw new Exception('unknown_assembly', EQ_ERROR_UNKNOWN_OBJECT);
}

$exportingTask = null;
if($assembly['invitations_exporting_task_id']) {
    $exportingTask = ExportingTask::id($assembly['invitations_exporting_task_id'])
        ->read([
            'status',
            'exporting_task_lines_ids' => ['controller', 'params', 'document_id']
        ])
        ->first();

    if($exportingTask && $exportingTask['status'] === 'running') {
        throw new Exception('invitation_export_task_running', EQ_ERROR_INVALID_PARAM);
    }
}

$correspondences = AssemblyInvitationCorrespondence::search([
        ['assembly_id', '=', $assembly['id']],
        ['document_id', '<>', null]
    ])
    ->read(['ownership_id', 'document_id']);

if(!count($correspondences)) {
    throw new Exception('no_generated_invitation_documents', EQ_ERROR_INVALID_PARAM);
}

// A document can be shared by several correspondences when they target the same owner and ownership.
$documents_to_regenerate = [];
$ignored_historical_correspondence_ids = [];
$assembly_ownerships_map = array_fill_keys($assembly['ownerships_ids'], true);
foreach($correspondences as $correspondence_id => $correspondence) {
    if(!isset($assembly_ownerships_map[$correspondence['ownership_id']])) {
        $ignored_historical_correspondence_ids[] = $correspondence_id;
        continue;
    }

    $document_id = $correspondence['document_id'];
    if(isset($documents_to_regenerate[$document_id])) {
        continue;
    }

    $documents_to_regenerate[$document_id] = [
        'correspondence_id' => $correspondence_id,
        'ownership_id'      => $correspondence['ownership_id']
    ];
}

$mergePdfData = static function(array $pdf_data): string {
    $temp_files = [];
    $output_file = tempnam(sys_get_temp_dir(), 'merged_pdf_');

    if($output_file === false) {
        throw new Exception('unable_to_create_temporary_file', EQ_ERROR_UNKNOWN);
    }

    try {
        foreach($pdf_data as $data) {
            $temp_file = tempnam(sys_get_temp_dir(), 'pdf_');
            if($temp_file === false || file_put_contents($temp_file, $data) === false) {
                throw new Exception('unable_to_create_temporary_file', EQ_ERROR_UNKNOWN);
            }
            $temp_files[] = $temp_file;
        }

        $escaped_files = array_map('escapeshellarg', $temp_files);
        $escaped_output = escapeshellarg($output_file);
        $command = 'qpdf --empty --pages ' . implode(' ', $escaped_files) . ' -- ' . $escaped_output . ' 2>&1';

        exec($command, $output_lines, $result_code);
        if($result_code !== 0 || !is_file($output_file)) {
            trigger_error("APP::qpdf merge failed:\n" . implode("\n", $output_lines), EQ_REPORT_ERROR);
            throw new Exception('pdf_merge_failed', EQ_ERROR_UNKNOWN);
        }

        $result = file_get_contents($output_file);
        if($result === false) {
            throw new Exception('unable_to_read_generated_document', EQ_ERROR_UNKNOWN);
        }

        return $result;
    }
    finally {
        foreach($temp_files as $temp_file) {
            if(is_file($temp_file)) {
                @unlink($temp_file);
            }
        }
        if(is_file($output_file)) {
            @unlink($output_file);
        }
    }
};

$refreshDocumentData = static function(int $document_id, string $data): void {
    $document = Document::id($document_id)
        ->read(['id'])
        ->first();

    if(!$document) {
        throw new Exception('unknown_invitation_document', EQ_ERROR_UNKNOWN_OBJECT);
    }

    // `data` refreshes the MD5 hash and metadata. The SHA-256 hash and stored link
    // are invalidated explicitly because they are not declared as dependents of `data`.
    Document::id($document_id)
        ->update([
            'data'        => $data,
            'hash_sha256' => null,
            'link'        => null
        ])
        ->read(['hash', 'hash_sha256', 'link']);
};

$regenerated_document_ids = [];

foreach($documents_to_regenerate as $document_id => $document_info) {
    $invitation_pdf = (string) eQual::run(
        'get',
        'realestate_governance_AssemblyInvitationCorrespondence_render-pdf',
        ['id' => $document_info['correspondence_id']]
    );

    $agenda_pdf = (string) eQual::run(
        'get',
        'realestate_governance_Assembly_agenda_render-pdf',
        ['id' => $assembly['id']]
    );

    $mandate_pdf = (string) eQual::run(
        'get',
        'realestate_governance_Assembly_mandate_render-pdf',
        [
            'id'           => $assembly['id'],
            'ownership_id' => $document_info['ownership_id']
        ]
    );

    $merged_pdf = $mergePdfData([$invitation_pdf, $agenda_pdf, $mandate_pdf]);
    $refreshDocumentData($document_id, $merged_pdf);
    $regenerated_document_ids[] = $document_id;

    // The correspondence download URL contains the document hash and must be rebuilt.
    AssemblyInvitationCorrespondence::search(['document_id', '=', $document_id])
        ->write(['download_link' => null])
        ->read(['download_link']);
}

$regenerated_export_document_ids = [];

if($exportingTask) {
    foreach($exportingTask['exporting_task_lines_ids'] as $task_line_id => $taskLine) {
        if($taskLine['controller'] !== 'realestate_governance_Assembly_export-invitation') {
            throw new Exception('unexpected_invitation_export_controller', EQ_ERROR_INVALID_CONFIG);
        }

        $line_params = json_decode($taskLine['params'], true);
        if(!is_array($line_params) || ($line_params['id'] ?? null) != $assembly['id']) {
            throw new Exception('invalid_invitation_export_params', EQ_ERROR_INVALID_CONFIG);
        }

        $result = eQual::run('do', $taskLine['controller'], $line_params);
        $new_document_id = $result['document_id'] ?? null;
        if(!$new_document_id) {
            throw new Exception('missing_document_in_response', EQ_ERROR_UNKNOWN);
        }

        $line_document_id = $taskLine['document_id'];
        if($line_document_id) {
            $newDocument = Document::id($new_document_id)
                ->read(['data'])
                ->first();

            if(!$newDocument) {
                throw new Exception('unknown_generated_export_document', EQ_ERROR_UNKNOWN_OBJECT);
            }

            $refreshDocumentData($line_document_id, $newDocument['data'] ?? '');
            Document::id($new_document_id)->delete(true);
        }
        else {
            $line_document_id = $new_document_id;
        }

        ExportingTaskLine::id($task_line_id)->update([
            'document_id' => $line_document_id,
            'pid'         => null,
            'status'      => 'ready'
        ]);

        $regenerated_export_document_ids[] = $line_document_id;
    }

    ExportingTask::id($exportingTask['id'])
        ->update([
            'status'        => 'ready',
            'is_exported'   => false,
            'last_run'      => time(),
            'download_link' => null
        ])
        ->read(['download_link']);
}

$context->httpResponse()
    ->body([
        'assembly_id'                    => $assembly['id'],
        'correspondences_count'          => count($correspondences),
        'ignored_historical_correspondence_ids' => $ignored_historical_correspondence_ids,
        'regenerated_document_ids'       => $regenerated_document_ids,
        'regenerated_export_document_ids' => $regenerated_export_document_ids
    ])
    ->send();

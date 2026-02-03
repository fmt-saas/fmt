<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use documents\Document;
use documents\navigation\Node;
use realestate\governance\AssemblyMinutesCorrespondence;

[$params, $providers] = eQual::announce([
    'description'   => "Create a document for a given assembly invitation.",
    'params'        => [
        'id' =>  [
            'type'             => 'many2one',
            'foreign_object'   => 'realestate\governance\AssemblyMinutesCorrespondence',
            'description'      => 'Identifier of the Assembly invitation.',
            'required'          => true
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context                  $context
 */
['context' => $context] = $providers;


$assemblyMinutesCorrespondence = AssemblyMinutesCorrespondence::id($params['id'])
    ->read([
        'status', 'condo_id', 'ownership_id', 'name',
        'assembly_id' => ['signed_minutes_document_id']
    ])
    ->first();

if(!$assemblyMinutesCorrespondence) {
    throw new Exception("unknown_assembly_invitation", EQ_ERROR_UNKNOWN_OBJECT);
}

if(!($assemblyMinutesCorrespondence['assembly_id']['signed_minutes_document_id'] ?? null)) {
    throw new Exception('missing_signed_minutes_document', EQ_ERROR_INVALID_CONFIG);
}

// retrieve FS Node relating to general meetings (assemblies)
$parentNode = Node::search([
        ['condo_id', '=', $assemblyMinutesCorrespondence['condo_id'] ],
        ['node_type', '=', 'folder'],
        ['code', '=', 'general_meetings']
    ])
    ->first();

// generate document and add it to EDMS
$temp_files = [];
$output_file = tempnam(sys_get_temp_dir(), 'merged_pdf_');

// 1) correspondence first page
$data1 = eQual::run('get', 'realestate_governance_AssemblyMinutesCorrespondence_render-pdf', ['id' => $assemblyMinutesCorrespondence['id']]);

$temp = tempnam(sys_get_temp_dir(), 'pdf_');
file_put_contents($temp, $data1 ?? '');
$temp_files[] = $temp;

// 2) signed version of the General Assembly minutes
$data2 = eQual::run('get', 'documents_document', ['id' => $assemblyMinutesCorrespondence['assembly_id']['signed_minutes_document_id']]);

$temp = tempnam(sys_get_temp_dir(), 'pdf_');
file_put_contents($temp, $data2 ?? '');
$temp_files[] = $temp;

// 3) append attachments, if any
// #memo - General Assembly attachments are not sent, but accessible through the platform
/*
$documents = Document::search([['assembly_id', '=', $assemblyMinutesCorrespondence['assembly_id']['id']], ['is_assembly_minutes_attachment', '=', true]])
    ->read(['data']);

foreach($documents as $document_id => $document) {
    $temp = tempnam(sys_get_temp_dir(), 'pdf_');
    file_put_contents($temp, $document['data'] ?? '');
    $temp_files[] = $temp;
}
*/

// merge all generated documents
try {
    if(!count($temp_files)) {
        throw new Exception('no_files_generated', EQ_ERROR_UNKNOWN);
    }
    $escaped_files = array_map('escapeshellarg', $temp_files);
    $escaped_output = escapeshellarg($output_file);
    $cmd = 'qpdf --empty --pages ' . implode(' ', $escaped_files) . ' -- ' . $escaped_output . ' 2>&1';

    exec($cmd, $output_lines, $result_code);

    if($result_code !== 0 || !file_exists($output_file)) {
        trigger_error("APP::qpdf merge failed:\n" . implode("\n", $output_lines), EQ_REPORT_ERROR);
        throw new Exception('pdf_merge_failed', EQ_ERROR_UNKNOWN);
    }

    $output = file_get_contents($output_file);
}
catch(Exception $e) {
    trigger_error('APP::Error while merging documents ' . $e->getMessage(), EQ_REPORT_ERROR);
    throw new Exception($e->getMessage(), EQ_ERROR_INVALID_CONFIG);
}
finally {
    foreach($temp_files as $file) {
        if(isset($file) && is_file($file)) {
            @unlink($file);
        }
    }
    if(isset($output_file) && is_file($output_file)) {
        @unlink($output_file);
    }
}

// generate document and add it to EDMS
$document = Document::create([
        'name'          => 'Procès verbal Assemblée - ' . $assemblyMinutesCorrespondence['name'],
        'data'          => $output,
        'condo_id'      => $assemblyMinutesCorrespondence['condo_id']
    ])
    ->update([
        // place node in dedicated folder
        'parent_node_id'    => $parentNode['id'] ?? null,
        // make node private
        'ownership_id'      => $assemblyMinutesCorrespondence['ownership_id']
    ])
    ->first();

if(!$document) {
    throw new Exception('document_creation_failed', EQ_ERROR_UNKNOWN);
}

// attach generated document to invitation
AssemblyMinutesCorrespondence::id($assemblyMinutesCorrespondence['id'])->update(['document_id' => $document['id']]);


$context->httpResponse()
        ->status(201)
        ->send();

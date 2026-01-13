<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use documents\Document;
use documents\navigation\Node;
use realestate\governance\AssemblyInvitationCorrespondence;

[$params, $providers] = eQual::announce([
    'description'   => "Create a document for a given assembly invitation.",
    'params'        => [
        'id' =>  [
            'type'             => 'many2one',
            'foreign_object'   => 'realestate\governance\AssemblyInvitationCorrespondence',
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


$assemblyInvitationCorrespondence = AssemblyInvitationCorrespondence::id($params['id'])
    ->read(['status', 'condo_id', 'assembly_id', 'ownership_id', 'name'])
    ->first();

if(!$assemblyInvitationCorrespondence) {
    throw new Exception("unknown_assembly_invitation", EQ_ERROR_UNKNOWN_OBJECT);
}


// retrieve FS Node relating to general meetings (assemblies)
$parentNode = Node::search([
        ['condo_id', '=', $assemblyInvitationCorrespondence['condo_id'] ],
        ['node_type', '=', 'folder'],
        ['code', '=', 'general_meetings']
    ])
    ->first();


$temp_files = [];
$output_file = tempnam(sys_get_temp_dir(), 'merged_') . '.pdf';

// generate document and add it to EDMS
$data1 = eQual::run('get', 'realestate_governance_AssemblyInvitationCorrespondence_render-pdf', ['id' => $assemblyInvitationCorrespondence['id']]);

$temp = tempnam(sys_get_temp_dir(), 'pdf_') . '.pdf';
file_put_contents($temp, $data1 ?? '');
$temp_files[] = $temp;

$data2 = eQual::run('get', 'realestate_governance_Assembly_mandate_render-pdf', ['id' => $assemblyInvitationCorrespondence['assembly_id'], 'ownership_id' => $assemblyInvitationCorrespondence['ownership_id']]);

$temp = tempnam(sys_get_temp_dir(), 'pdf_') . '.pdf';
file_put_contents($temp, $data2 ?? '');
$temp_files[] = $temp;


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
        @unlink($file);
    }
    @unlink($output_file);
}


$document = Document::create([
        'name'          => 'Convocation Assemblée - ' . $assemblyInvitationCorrespondence['name'],
        'data'          => $output,
        'condo_id'      => $assemblyInvitationCorrespondence['condo_id']
    ])
    ->update([
        // place node in dedicated folder
        'parent_node_id'    => $parentNode['id'] ?? null,
        // make node private
        'ownership_id'      => $assemblyInvitationCorrespondence['ownership_id']
    ])
    ->first();

if(!$document) {
    throw new Exception('document_creation_failed', EQ_ERROR_UNKNOWN);
}

// attach generated document to invitation
AssemblyInvitationCorrespondence::id($assemblyInvitationCorrespondence['id'])->update(['document_id' => $document['id']]);


$context->httpResponse()
        ->status(201)
        ->send();

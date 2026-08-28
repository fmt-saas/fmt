<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use documents\Document;
use realestate\property\transfer\OwnershipTransferSettlement;
use realestate\property\transfer\OwnershipTransferSettlementCorrespondence;

[$params, $providers] = eQual::announce([
    'description' => 'Merge settlement correspondence documents for one postal communication method.',
    'params'      => [
        'id' => [
            'type'           => 'many2one',
            'foreign_object' => 'realestate\property\transfer\OwnershipTransferSettlement',
            'description'    => 'Settlement whose correspondences must be exported.',
            'required'       => true
        ],
        'communication_method' => [
            'type'        => 'string',
            'description' => 'Postal communication method to export.',
            'selection'   => ['postal', 'postal_registered', 'postal_registered_receipt'],
            'required'    => true
        ]
    ],
    'response'  => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers' => ['context']
]);

['context' => $context] = $providers;

$settlement = OwnershipTransferSettlement::id($params['id'])
    ->read(['name', 'condo_id'])
    ->first();

if(!$settlement) {
    throw new Exception('unknown_ownership_transfer_settlement', EQ_ERROR_UNKNOWN_OBJECT);
}

$correspondences = OwnershipTransferSettlementCorrespondence::search([
        ['settlement_id', '=', $settlement['id']],
        ['communication_method', '=', $params['communication_method']]
    ])
    ->read(['document_id']);

$temp_files = [];
$output_file = tempnam(sys_get_temp_dir(), 'settlement_correspondences_');

try {
    foreach($correspondences as $correspondence_id => $correspondence) {
        if(!$correspondence['document_id']) {
            eQual::run(
                'do',
                'realestate_property_transfer_OwnershipTransferSettlementCorrespondence_generate-document',
                ['id' => $correspondence_id]
            );
        }

        $correspondence = OwnershipTransferSettlementCorrespondence::id($correspondence_id)
            ->read(['document_id' => ['data']])
            ->first();
        if(!$correspondence['document_id']) {
            continue;
        }

        $temp_file = tempnam(sys_get_temp_dir(), 'settlement_correspondence_');
        file_put_contents($temp_file, $correspondence['document_id']['data'] ?? '');
        $temp_files[] = $temp_file;
    }

    if(!count($temp_files)) {
        throw new Exception('no_settlement_correspondence_document', EQ_ERROR_UNKNOWN);
    }

    $escaped_files = array_map('escapeshellarg', $temp_files);
    $command = 'qpdf --empty --pages '
        . implode(' ', $escaped_files)
        . ' -- '
        . escapeshellarg($output_file)
        . ' 2>&1';

    exec($command, $output_lines, $result_code);
    if($result_code !== 0 || !is_file($output_file)) {
        trigger_error('APP::Settlement correspondence PDF merge failed: ' . implode(PHP_EOL, $output_lines), EQ_REPORT_ERROR);
        throw new Exception('settlement_correspondence_pdf_merge_failed', EQ_ERROR_UNKNOWN);
    }

    $output = file_get_contents($output_file);
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

$document = Document::create([
        'name'         => "Export - {$settlement['name']} ({$params['communication_method']})",
        'content_type' => 'application/pdf',
        'data'         => $output,
        'condo_id'     => $settlement['condo_id']
    ])
    ->first();

if(!$document) {
    throw new Exception('settlement_correspondence_export_creation_failed', EQ_ERROR_UNKNOWN);
}

$context->httpResponse()
    ->body(['document_id' => $document['id']])
    ->send();


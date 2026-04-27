<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use documents\Document;
use realestate\funding\PaymentReminder;
use realestate\funding\PaymentReminderCorrespondence;

[$params, $providers] = eQual::announce([
    'description'   => 'Export payment reminder correspondences by communication method as a merged PDF document.',
    'params'        => [
        'id' =>  [
            'type'              => 'many2one',
            'description'       => 'The Payment Reminder the export refers to.',
            'foreign_object'    => 'realestate\funding\PaymentReminder',
            'required'          => true
        ],

        'communication_method' => [
            'type'              => 'string',
            'description'       => 'Method of sending.',
            'selection'         => [
                'postal',
                'postal_registered',
                'postal_registered_receipt'
            ],
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
 * @var \equal\php\Context $context
 */
['context' => $context] = $providers;

$paymentReminder = PaymentReminder::id($params['id'])
    ->read(['status', 'condo_id', 'name'])
    ->first();

if(!$paymentReminder) {
    throw new Exception('unknown_payment_reminder', EQ_ERROR_UNKNOWN_OBJECT);
}

$paymentReminderCorrespondences = PaymentReminderCorrespondence::search([
        ['payment_reminder_id', '=', $paymentReminder['id']],
        ['communication_method', '=', $params['communication_method']]
    ])
    ->read(['is_sent', 'document_id']);

$temp_files = [];
$output_file = tempnam(sys_get_temp_dir(), 'merged_pdf_');

foreach($paymentReminderCorrespondences as $payment_reminder_correspondence_id => $paymentReminderCorrespondence) {
    if(!$paymentReminderCorrespondence['document_id']) {
        eQual::run('do', 'realestate_funding_PaymentReminderCorrespondence_generate-document', ['id' => $payment_reminder_correspondence_id]);
    }

    $paymentReminderCorrespondence = PaymentReminderCorrespondence::id($payment_reminder_correspondence_id)
        ->read(['document_id' => ['data']])
        ->first();

    if(!$paymentReminderCorrespondence['document_id']) {
        continue;
    }

    $temp = tempnam(sys_get_temp_dir(), 'pdf_');
    file_put_contents($temp, $paymentReminderCorrespondence['document_id']['data'] ?? '');
    $temp_files[] = $temp;
}

$output = '';

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
    trigger_error('APP::Error while merging payment reminder documents ' . $e->getMessage(), EQ_REPORT_ERROR);
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

$document = Document::create([
        'name'          => 'Export - ' . $paymentReminder['name'] . ' (' . $params['communication_method'] . ')',
        'content_type'  => 'application/pdf',
        'data'          => $output,
        'condo_id'      => $paymentReminder['condo_id']
    ])
    ->first();

$context->httpResponse()
        ->body([
            'document_id' => $document['id']
        ])
        ->send();

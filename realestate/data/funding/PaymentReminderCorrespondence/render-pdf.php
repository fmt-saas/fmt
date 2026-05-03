<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use Dompdf\Dompdf;
use Dompdf\Options as DompdfOptions;
use realestate\funding\PaymentReminderCorrespondence;

[$params, $providers] = eQual::announce([
    'description'   => 'Generate a PDF individual request for a given Payment Reminder Correspondence (relates to a single Ownership).',
    'params'        => [
        'id' => [
            'description'       => 'Identifier of the specific PaymentReminderCorrespondence to consider.',
            'type'              => 'many2one',
            'foreign_object'    => 'realestate\funding\PaymentReminderCorrespondence',
            'required'          => true
        ]
    ],
    'access'        => [
        'visibility' => 'protected'
    ],
    'response'      => [
        'content-type'  => 'application/pdf',
        'accept-origin' => '*'
    ],
    'providers'     => ['context'],
    'constants'     => ['L10N_TIMEZONE', 'L10N_LOCALE']
]);

/** @var \equal\php\Context $context */
$context = $providers['context'];

$paymentReminderCorrespondence = PaymentReminderCorrespondence::id($params['id'])
    ->read(['ownership_id', 'owner_id', 'payment_reminder_id'])
    ->first();

if(!$paymentReminderCorrespondence) {
    throw new Exception('unknown_payment_reminder_correspondence', EQ_ERROR_UNKNOWN_OBJECT);
}


$temp_files = [];
$output_file = tempnam(sys_get_temp_dir(), 'merged_pdf_');

try {

    try {
        $pdf = eQual::run('get', 'realestate_funding_fiscalperiod_paymentreminder_single-pdf', [
                'payment_reminder_id' => $paymentReminderCorrespondence['payment_reminder_id'],
                'ownership_id'        => $paymentReminderCorrespondence['ownership_id'],
                'owner_id'            => $paymentReminderCorrespondence['owner_id']
            ]);

        $temp = tempnam(sys_get_temp_dir(), 'pdf_');
        file_put_contents($temp, $pdf);
        $temp_files[] = $temp;
    }
    catch(Exception $e) {
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
    trigger_error('APP::Error while rendering template' . $e->getMessage(), EQ_REPORT_ERROR);
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


$context->httpResponse()
        // ->header('Content-Disposition', 'attachment; filename="document.pdf"')
        ->header('Content-Disposition', 'inline; filename="document.pdf"')
        ->body($output ?? '')
        ->send();

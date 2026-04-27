<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use realestate\funding\PaymentReminder;
use realestate\funding\PaymentReminderCorrespondence;

[$params, $providers] = eQual::announce([
    'description'   => 'Send all payment reminder correspondences for the target reminder and communication method.',
    'params'        => [
        'id' =>  [
            'type'              => 'many2one',
            'description'       => 'The Payment Reminder to process.',
            'foreign_object'    => 'realestate\funding\PaymentReminder',
            'required'          => true
        ],

        'communication_method' => [
            'type'              => 'string',
            'description'       => 'Method of sending.',
            'help'              => 'This controller expects only digital communication methods (e.g. email).',
            'default'           => 'email',
            'selection'         => [
                'email'
            ]
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

$payment_reminder_correspondences_ids = [];

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

    $payment_reminder_correspondences_ids[] = $payment_reminder_correspondence_id;
}

foreach($payment_reminder_correspondences_ids as $payment_reminder_correspondence_id) {
    try {
        eQual::run('do', 'realestate_funding_PaymentReminderCorrespondence_send-email', ['id' => $payment_reminder_correspondence_id]);
    }
    catch(Exception $e) {
        trigger_error('APP::Error while sending payment reminder documents ' . $e->getMessage(), EQ_REPORT_ERROR);
        throw new Exception($e->getMessage(), EQ_ERROR_INVALID_CONFIG);
    }
}

$context->httpResponse()
        ->status(204)
        ->send();

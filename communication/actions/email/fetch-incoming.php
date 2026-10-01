<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use communication\email\Mailbox;

[$params, $providers] = eQual::announce([
    'description' => 'Fetch new emails for all validated receiving mailboxes.',
    'params'      => [],
    'access'      => [
        'visibility' => 'private'
    ],
    'response'    => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'   => ['context']
]);

/**
 * @var equal\php\Context $context
 */
['context' => $context] = $providers;

$mailboxes_ids = Mailbox::search([
        ['status', '=', 'validated'],
        ['can_receive', '=', true]
    ])
    ->ids();

$failed_mailboxes_ids = [];

foreach($mailboxes_ids as $mailbox_id) {
    try {
        eQual::run(
            'do',
            'communication_email_Mailbox_fetch',
            ['id' => $mailbox_id],
            true
        );
    }
    catch(Exception $e) {
        $failed_mailboxes_ids[] = $mailbox_id;
        trigger_error(
            "APP::Unable to fetch incoming emails for Mailbox [{$mailbox_id}]: {$e->getMessage()}",
            EQ_REPORT_WARNING
        );
    }
}

if(!empty($failed_mailboxes_ids)) {
    throw new Exception(
        'mailboxes_fetch_failed:'.implode(',', $failed_mailboxes_ids),
        EQ_ERROR_UNKNOWN
    );
}

$context->httpResponse()
    ->status(204)
    ->send();

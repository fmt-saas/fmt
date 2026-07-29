<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use core\email\Email;

[$params, $providers] = eQual::announce([
    'description'   => "Check if an email has actually been sent.",
    'help'          => "If no mail have been sent or if the most recent mail is marked as failed, an alert is raised.",
    'params'        => [
        'id' =>  [
            'description'       => 'Identifier of the Email for which the check is requested.',
            'type'              => 'many2one',
            'foreign_object'    => 'core\email\Email',
            'required'          => true
        ]
    ],
    'access'        => [
        'visibility'    => 'protected'
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'cron', 'dispatch']
]);

/**
 * @var \equal\php\Context              $context
 * @var \equal\cron\Scheduler           $cron
 * @var \equal\dispatch\Dispatcher      $dispatch
 */
['context' => $context, 'cron' => $cron, 'dispatch' => $dispatch] = $providers;

$email = Email::id($params['id'])
    ->read(['status', 'object_class', 'object_id'])
    ->first();

if(!$email || $email['status'] !== 'sent') {
    $dispatch->dispatch('fmt.monitoring.failed_email_sending', Email::getType(), $email['id'], 'important', 'fmt_monitoring_check-email', $params);
    $dispatch->cancel('fmt.monitoring.failed_email_sending', $email['object_class'], $email['object_id']);
}
else {
    $dispatch->cancel('fmt.monitoring.failed_email_sending', Email::getType(), $email['id']);
    $dispatch->cancel('fmt.monitoring.failed_email_sending', $email['object_class'], $email['object_id']);
}

$context
    ->httpResponse()
    ->send();

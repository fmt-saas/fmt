<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/

use sale\receivable\Receivable;

list($params, $providers) = eQual::announce([
    'description'   => 'Invoice all pending receivables.',
    'params'        => [],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

/** @var \equal\php\Context $context */
['context' => $context] = $providers;

$pending_receivables_ids = Receivable::search(['status', '=', 'pending'])->ids();

eQual::run('do', 'sale_receivable_invoice', ['ids' => $pending_receivables_ids]);

$context->httpResponse()
        ->status(204)
        ->send();

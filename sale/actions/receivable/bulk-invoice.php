<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
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

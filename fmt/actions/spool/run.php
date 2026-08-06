<?php
/*
    This file is part of the eQual framework <http://www.github.com/equalframework/equal>
    Some Rights Reserved, eQual framework, 2010-2024
    Original author(s): Cédric FRANCOYS
    Licensed under GNU LGPL 3 license <http://www.gnu.org/licenses/>
*/

use fmt\core\Mail;
use infra\quota\Quota;

[$params, $providers] = eQual::announce([
    'description'	=>	"Send emails that are currently in spool queue.",
    'params' 		=>	[],
    'access' => [
        'visibility'        => 'private'
    ],
    'response'      => [
        'content-type'      => 'application/json',
        'charset'           => 'utf-8',
        'accept-origin'     => '*'
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context  $context
 */
['context' => $context] = $providers;

Quota::search([
        ['code', '=', 'email.outbound.count']
    ])
    ->do('check-availability');

Mail::flush();

Quota::search([
        ['code', '=', 'email.outbound.count']
    ])
    ->do('check-thresholds');

$context
    ->httpResponse()
    ->status(204)
    ->send();

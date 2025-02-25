<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
list($params, $providers) = eQual::announce([
    'description'   => "Update the status of given order from quote to confirm.",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the targeted order.',
            'type'          => 'integer',
            'min'           => 1,
            'required'      => true
        ]
    ],
    'access' => [
        'visibility'        => 'protected',
        'groups'            => ['order.default.user']
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\orm\ObjectManager            $orm
 */
list($context, $orm) = [$providers['context'], $providers['orm']];

eQual::run('do', 'sale_order_do-option', ['id' => $params['id']]);
eQual::run('do', 'sale_order_do-confirm', ['id' => $params['id']]);

$context->httpResponse()
        ->status(204)
        ->send();
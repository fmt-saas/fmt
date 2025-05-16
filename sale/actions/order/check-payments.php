<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\order\Order;
use sale\order\Funding;

list($params, $providers) = eQual::announce([
    'description'   => "Checks if all due payments have been received for a given order.",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the order the check against payments.',
            'type'          => 'integer',
            'required'      => true
        ],
    ],
    'access' => [
        'visibility'        => 'protected'
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
list($context, $orm) = [ $providers['context'], $providers['orm']];

$order = Order::id($params['id'])->read(['id', 'name', 'fundings_ids'])->first(true);

if(!$order) {
    throw new Exception("unknown_order", QN_ERROR_UNKNOWN_OBJECT);
}

$fundings = Funding::ids($order['fundings_ids'])->read(['order_id', 'due_date', 'is_paid'])->get();

$result = [];
$httpResponse = $context->httpResponse()->status(200);

$is_paid = true;
if($fundings) {
    foreach($fundings as $fid => $funding) {
        if($funding['order_id'] == $order['id'] && $funding['due_date'] <= time() && !$funding['is_paid']) {
            $is_paid = false;
            $result[] = $fid;
            break;
        }
    }
}

if(!$is_paid) {
    $httpResponse->status(qn_error_http(QN_ERROR_NOT_ALLOWED));
}

$httpResponse->body($result)
             ->send();

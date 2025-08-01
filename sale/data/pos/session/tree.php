<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/

use sale\pos\CashdeskSession;

// announce script and fetch parameters values
list($params, $providers) = announce([
    'description'	=>	"Provide a fully loaded tree for a given CashdeskSession.",
    'params' 		=>	[
        'id' => [
            'description'   => 'Identifier of the session for which the tree is requested.',
            'type'          => 'integer',
            'required'      => true
        ]
    ],
    'access' => [
        'visibility'        => 'protected',
        'groups'            => ['pos.default.user'],
    ],
    'response' => [
        'content-type'      => 'application/json',
        'charset'           => 'utf-8',
        'accept-origin'     => '*'
    ],
    'providers' => ['context']
]);

list($context) = [$providers['context']];

$tree = [
    'id',
    'amount_opening',
    'cashdesk_id',
    'status',
    'operations_ids' => [
        'id',
        'amount',
        'type'
    ],
    'orders_ids' => [
        'id',
        'name',
        'created',
        'status',
        'total',
        'price'
    ]
];

$cashdesksessions = CashdeskSession::id($params['id'])->read($tree)->adapt('txt')->get(true);

if(!$cashdesksessions || !count($cashdesksessions)) {
    throw new Exception("unknown_order", QN_ERROR_UNKNOWN_OBJECT);
}

$cashdesksession = reset($cashdesksessions);

$context->httpResponse()
        ->body($cashdesksession)
        ->send();
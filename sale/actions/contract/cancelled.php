<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
use sale\contract\Contract;

list($params, $providers) = announce([
    'description'   => "Sets contract as cancelled.",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the contract to cancel.',
            'type'          => 'integer',
            'min'           => 1,
            'required'      => true
        ],
    ],
    'access' => [
        'groups'            => ['admins'],
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm', 'auth'] 
]);


list($context, $orm, $auth) = [$providers['context'], $providers['orm'], $providers['auth']];



Contract::id($params['id'])->update(['status' => 'cancelled']);


$context->httpResponse()
        ->status(204)
        ->send();
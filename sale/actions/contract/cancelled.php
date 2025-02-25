<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
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
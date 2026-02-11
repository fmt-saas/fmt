<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use realestate\governance\Assembly;
use realestate\governance\AssemblyItem;

[$params, $providers] = eQual::announce([
    'description'   => "Clone a given selection of Assembly objects.",
    'params'        => [
        'ids' =>  [
            'type'              => 'one2many',
            'description'       => "List of assembly ids to clone.",
            'foreign_object'    => 'realestate\governance\Assembly',
            'required'          => true
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'dispatch']
]);

/**
 * @var \equal\php\Context                 $context
 * @var \equal\dispatch\Dispatcher         $dispatch
 */
['context' => $context, 'dispatch' => $dispatch] = $providers;

// ensure booking object exists and is readable
$assemblies = Assembly::ids($params['ids'])
    ->do('clone');

$context->httpResponse()
    ->status(201)
    ->send();

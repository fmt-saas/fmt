<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use realestate\governance\Assembly;

[$params, $providers] = eQual::announce([
    'description'   => "Delete a given selection of Assembly objects, if their status allows deletion.",
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
$assemblies = Assembly::ids($params['ids'])->read(['status']);

foreach($assemblies as $id => $assembly) {
    if(!in_array($assembly['status'], ['pending', 'published'], true)) {
        throw new Exception('cannot_remove_assembly_with_sending_in_progress', EQ_ERROR_INVALID_PARAM);
    }
}

$assemblies->delete(true);

$context->httpResponse()
    ->status(204)
    ->send();

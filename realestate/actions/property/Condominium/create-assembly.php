<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use realestate\governance\Assembly;
use realestate\governance\AssemblyTemplate;

[$params, $providers] = eQual::announce([
    'description'   => "Create a new assembly for a condominium using an assembly template.",
    'params'        => [
        'id' =>  [
            'type'             => 'many2one',
            'foreign_object'   => 'realestate\property\Condominium',
            'description'      => 'Identifier of the Condominium.',
        ],
        'assembly_template_id' => [
            'type'             => 'many2one',
            'label'            => 'Assembly Template',
            'foreign_object'   => 'realestate\governance\AssemblyTemplate',
            'description'      => 'Identifier of the Assembly Template.',
            'required'         => true
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context                  $context
 */
['context' => $context] = $providers;

if(!isset($params['id']) && !isset($params['ids'])) {
    throw new Exception("missing_id_or_ids", EQ_ERROR_INVALID_PARAM);
}

$assemblyTemplate = AssemblyTemplate::id($params['assembly_template_id'])
    ->read(['name', 'assembly_type'])
    ->first();

if(!$assemblyTemplate) {
    throw new Exception("unknown_assembly_template", EQ_ERROR_INVALID_PARAM);
}

Assembly::create([
        'condo_id'              => $params['id'],
        'name'                  => $assemblyTemplate['name'],
        'assembly_type'         => $assemblyTemplate['assembly_type'],
    ])
    // trigger `onupdateAssemblyTemplateId`
    ->update([
        'assembly_template_id'  => $params['assembly_template_id'],
    ])
    ->do('auto_assign_location');

$context->httpResponse()
        ->status(201)
        ->send();

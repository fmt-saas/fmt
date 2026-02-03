<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use realestate\governance\AssemblyAttendee;
use realestate\governance\AssemblyItem;
use realestate\property\PropertyLotApportionmentShare;
use realestate\property\PropertyLotOwnership;

[$params, $providers] = eQual::announce([
    'description'   => "Create a new assembly for a condominium using an assembly template.",
    'params'        => [
        'id' =>  [
            'type'             => 'many2one',
            'foreign_object'   => 'realestate\governance\AssemblyItem',
            'description'      => 'Identifier of the Assembly item (resolution).',
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

if(!isset($params['id'])) {
    throw new Exception("missing_id", EQ_ERROR_INVALID_PARAM);
}

// attempt to close item
AssemblyItem::id($params['id'])->transition('close');

$context->httpResponse()
        ->status(204)
        ->send();

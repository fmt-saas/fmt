<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use documents\DocumentSubtype;
use documents\DocumentType;
use equal\data\DataGenerator;
use identity\Identity;
use purchase\supplier\Supplier;

[$params, $providers] = eQual::announce([
    'description'   => 'Generate and set uuid that are missing on objects.',
    'params'        => [
        'entity' => [
            'type'          => 'string',
            'usage'         => 'orm/entity',
            'description'   => 'Full name (including namespace) of the specific class to export (e.g. "core\\User").',
            'help'          => 'If left empty, all DocumentType, DocumentSubtype, Identity and Supplier are handled.'
        ]
    ],
    'access' => [
        'visibility'    => 'protected'
    ],
    'response'      => [
        'accept-origin' => '*',
        'content-type'  => 'application/json'
    ],
    'constants'     => ['FMT_INSTANCE_TYPE'],
    'providers'     => ['context', 'orm']
]);

/**
 * @var \equal\php\Context          $context
 * @var \equal\orm\ObjectManager    $orm
 */
['context' => $context, 'orm' => $orm] = $providers;

if(constant('FMT_INSTANCE_TYPE') !== 'global') {
    throw new Exception('invalid_instance_type', EQ_ERROR_NOT_ALLOWED);
}

$entities_classes = [
    DocumentType::class,
    DocumentSubtype::class,
    Identity::class,
    Supplier::class
];

if(isset($params['entity'])) {
    $entities_classes = [$params['entity']];
}

foreach($entities_classes as $entity_class) {
    $objects = $entity_class::search(['uuid', 'is', null])
        ->read(['uuid'])
        ->get();

    foreach($objects as $id => $object) {
        do {
            $uuid = DataGenerator::uuid();
            $existing = $orm->search($entity_class, ['uuid', '=', $uuid]);
        } while( $existing > 0 && count($existing) > 0 );

        $entity_class::id($id)->update(['uuid' => $uuid]);
    }
}

$context
    ->httpResponse()
    ->status(200)
    ->send();

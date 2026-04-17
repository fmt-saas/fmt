<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use documents\DocumentSubtype;
use documents\DocumentType;
use equal\data\DataGenerator;
use finance\bank\BankAccount;
use identity\Identity;
use identity\User;
use purchase\supplier\Supplier;
use realestate\property\Condominium;

[$params, $providers] = eQual::announce([
    'description'   => 'Generate and set uuid that are missing on objects.',
    'params'        => [
        'entity' => [
            'type'          => 'string',
            'usage'         => 'orm/entity',
            'description'   => 'Full name (including namespace) of the specific class to export (e.g. "core\\User").',
            'help'          => 'If left empty all (DocumentType, DocumentSubtype, Identity, Supplier and Condominium) are handled.'
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

$entities_classes_links = [
    DocumentType::getType()     => [],
    DocumentSubtype::getType()  => ['document_type'],
    Identity::getType()         => [],
    Supplier::getType()         => ['identity'],
    Condominium::getType()      => ['identity'],
    BankAccount::getType()      => ['owner_identity'],
    User::getType()             => ['identity']        // #memo - 'instance' relation isn't required, because its uuid is automatically generated when an agency is created
];

if(isset($params['entity'])) {
    $entities_classes = [];
    if($params['entity'] === DocumentSubtype::getType()) {
        // needs DocumentType to create a valid link
        $entities_classes[] = DocumentType::getType();
    }
    elseif(in_array($params['entity'], [Supplier::getType(), Condominium::getType()])) {
        // needs Identity to create a valid link
        $entities_classes[] = Identity::getType();
    }

    $entities_classes[] = $params['entity'];
}

foreach($entities_classes_links as $entity_class => $links) {
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

    if(!empty($links)) {
        foreach($links as $link) {
            $objects = $entity_class::search([
                [$link.'_id', 'is not', null],
                [$link.'_uuid', 'is', null]
            ])
                ->read([$link.'_id' => ['uuid']])
                ->get();

            foreach($objects as $id => $object) {
                $entity_class::id($id)->update([$link.'_uuid' => $object[$link.'_id']['uuid']]);
            }
        }
    }
}

$context
    ->httpResponse()
    ->status(200)
    ->send();

<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use equal\orm\Domain;
use equal\orm\DomainCondition;

[$params, $providers] = eQual::announce([
    'description'   => 'Advanced search for Documents: returns a collection according to extra parameters.',
    'extends'       => 'core_model_collect',
    'params'        => [

        'entity' =>  [
            'description'       => 'name',
            'type'              => 'string',
            'default'           => 'documents\Document'
        ],

        'domain' => [
            'description'       => 'Criteria that results have to match (series of conjunctions)',
            'type'              => 'array',
            'default'           => []
        ],

        /* Filters */

        'condo_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'realestate\property\Condominium',
            'description'       => "The condominium the document relates to.",
            'default'           => function($domain) {
                $domain = new Domain($domain);

                $condo_id = null;
                foreach($domain->getClauses() as $clause) {
                    foreach($clause->getConditions() as $condition) {
                        if($condition->getOperand() === 'condo_id' && $condition->getOperator() === '=') {
                            $condo_id = $condition->getValue();
                            break 2;
                        }
                    }
                }

                return $condo_id;
            }
        ],

        'ownership_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'realestate\ownership\Ownership',
            'description'       => "The ownership the document relates to.",
            'domain'            => ['condo_id', '=', 'object.condo_id']
        ],

        'owner_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'realestate\ownership\Owner',
            'description'       => "The owner the document relates to.",
            'domain'            => ['condo_id', '=', 'object.condo_id']
        ],

        'supplier_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'purchase\supplier\Supplier',
            'description'       => "The supplier the document relates to.",
            'domain'            => [
                [
                    ['condo_id', '=', 'object.condo_id']
                ],
                [
                    ['condo_id', '=', null]
                ]
            ]
        ],

        'suppliership_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'purchase\supplier\Suppliership',
            'description'       => "The suppliership the document relates to.",
            'domain'            => ['condo_id', '=', 'object.condo_id']
        ],

        'document_type_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'documents\DocumentType',
            'description'       => "The document type."
        ],

        'document_subtype_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'documents\DocumentSubtype',
            'description'       => "The document sub-type."
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
 * @var \equal\php\Context  $context
 */
['context' => $context] = $providers;

$domain = new Domain($params['domain']);

if(isset($params['condo_id']) && $params['condo_id'] > 0) {
    $domain->addCondition(new DomainCondition('condo_id', '=', $params['condo_id']));
}

if(isset($params['ownership_id']) && $params['ownership_id'] > 0) {
    $domain->addCondition(new DomainCondition('ownership_id', '=', $params['ownership_id']));
}

if(isset($params['owner_id']) && $params['owner_id'] > 0) {
    $domain->addCondition(new DomainCondition('owner_id', '=', $params['owner_id']));
}

if(isset($params['supplier_id']) && $params['supplier_id'] > 0) {
    $domain->addCondition(new DomainCondition('supplier_id', '=', $params['supplier_id']));
}

if(isset($params['suppliership_id']) && $params['suppliership_id'] > 0) {
    $domain->addCondition(new DomainCondition('suppliership_id', '=', $params['suppliership_id']));
}

if(isset($params['document_type_id']) && $params['document_type_id'] > 0) {
    $domain->addCondition(new DomainCondition('document_type_id', '=', $params['document_type_id']));
}

if(isset($params['document_subtype_id']) && $params['document_subtype_id'] > 0) {
    $domain->addCondition(new DomainCondition('document_subtype_id', '=', $params['document_subtype_id']));
}

$params['domain'] = $domain->toArray();
$result = eQual::run('get', 'model_collect', $params, true);

$context
    ->httpResponse()
    ->body($result)
    ->send();

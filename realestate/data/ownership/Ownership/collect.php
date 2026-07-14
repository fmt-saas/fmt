<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use equal\orm\Domain;
use equal\orm\DomainCondition;
use realestate\ownership\Ownership;
use realestate\property\PropertyLot;
use realestate\property\PropertyLotOwnership;

[$params, $providers] = eQual::announce([
    'description'   => "Advanced search for Ownership: returns a collection according to extra parameters.",
    'extends'       => 'core_model_collect',
    'params'        => [

        'entity' =>  [
            'type'              => 'string',
            'description'       => "Full name of the entity to collect. (Forced to ownership)",
            'default'           => 'realestate\ownership\Ownership'
        ],

        'domain' => [
            'type'              => 'array',
            'description'       => "Criteria that results have to match (series of conjunctions).",
            'default'           => []
        ],

        /* Filters */

        'condo_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'realestate\property\Condominium',
            'description'       => "The property lot the ownership file relates to.",
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

        'name' => [
            'type'              => 'string',
            'description'       => "The name of the ownership."
        ],

        'property_lot_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'realestate\property\PropertyLot',
            'description'       => "The property lot the ownership file relates to.",
            'domain'            => ['condo_id', '=', 'object.condo_id']
        ],

        'property_entrance_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'realestate\property\PropertyEntrance',
            'description'       => "The property entrance the ownership file relates to.",
            'domain'            => ['condo_id', '=', 'object.condo_id']
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

if(!empty($params['name'])) {
    $domain->addCondition(new DomainCondition('name', 'like', "%{$params['name']}%"));
}

if(isset($params['property_lot_id']) && $params['property_lot_id'] > 0) {
    $ownerships = Ownership::search()
        ->read(['property_lots_ids'])
        ->get();

    $map_ownerships_ids = [];
    foreach($ownerships as $id => $ownership) {
        if(in_array($params['property_lot_id'], $ownership['property_lots_ids'])) {
            $map_ownerships_ids[$id] = true;
        }
    }

    $domain->addCondition(
        new DomainCondition('id', 'in', array_keys($map_ownerships_ids))
    );
}

if(isset($params['property_entrance_id']) && $params['property_entrance_id'] > 0) {
    $property_lots_ids = PropertyLot::search(['property_entrance_id', '=', $params['property_entrance_id']])->ids();

    $pro_lot_ownerships = PropertyLotOwnership::search(['property_lot_id', 'in', $property_lots_ids])
        ->read(['ownership_id'])
        ->get();

    $map_ownerships_ids = [];
    foreach($pro_lot_ownerships as $id => $pro_lot_ownership) {
        $map_ownerships_ids[$pro_lot_ownership['ownership_id']] = true;
    }

    $domain->addCondition(
        new DomainCondition('id', 'in', array_keys($map_ownerships_ids))
    );
}

$params['domain'] = $domain->toArray();
$result = eQual::run('get', 'model_collect', $params, true);

$context
    ->httpResponse()
    ->body($result)
    ->send();

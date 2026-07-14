<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use equal\orm\Domain;
use equal\orm\DomainCondition;

[$params, $providers] = eQual::announce([
    'description'   => "Advanced search for Owner: returns a collection according to extra parameters.",
    'extends'       => 'core_model_collect',
    'params'        => [

        'entity' =>  [
            'type'              => 'string',
            'description'       => "Full name of the entity to collect. (Forced to ownership)",
            'default'           => 'realestate\ownership\Owner'
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
            'description'       => "The name of the owner."
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
    $domain->addCondition(new DomainCondition('name', 'ilike', "%{$params['name']}%"));
}

$params['domain'] = $domain->toArray();
$result = eQual::run('get', 'model_collect', $params, true);

$context
    ->httpResponse()
    ->body($result)
    ->send();

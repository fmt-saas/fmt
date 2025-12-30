<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use equal\orm\Domain;
use equal\orm\DomainCondition;

[$params, $providers] = eQual::announce([
    'description'   => 'Advanced search for Accounts: returns a collection according to extra parameters.',
    'extends'       => 'core_model_collect',
    'params'        => [

        'entity' =>  [
            'description'       => 'name',
            'type'              => 'string',
            'default'           => 'realestate\governance\Assembly'
        ],

        'domain' => [
            'description'   => 'Criterias that results have to match (series of conjunctions)',
            'type'          => 'array',
            'default'       => []
        ],


        /* Filters */

        'date_from' => [
            'type'    => 'date',
            'default' => null
        ],

        'date_to' => [
            'type'    => 'date',
            'default' => null
        ],

        'condo_id' => [
            'type'           => 'many2one',
            'foreign_object' => 'realestate\property\Condominium',
            'default'        => function ($domain = []) {
                $condo_id = null;
                $origDomain = new Domain($domain);
                foreach($origDomain->getClauses() as $clause) {
                    foreach($clause->getConditions() as $condition) {
                        if($condition->getOperand() === 'condo_id') {
                            $condo_id = $condition->getValue();
                            break 2;
                        }
                    }
                }
                return $condo_id;
            }
        ],

        'status' => [
            'type'              => 'string',
            'description'       => 'Current status of the assemblies being searched.'
        ],


    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => [ 'context', 'orm' ]
]);
/**
 * @var \equal\php\Context $context
 * @var \equal\orm\ObjectManager $orm
 */
['context' => $context, 'orm' => $orm] = $providers;

//   Add conditions to the domain to consider advanced parameters
$domain = new Domain($params['domain']);

foreach($domain->getClauses() as $clause) {
    foreach($clause->getConditions() as $condition) {
        if($condition->getOperand() === 'name') {
            // const cond = ['name', 'ilike', '%' + word + '%'];
            break;
        }
    }
}


if(isset($params['date_from']) && $params['date_from']) {
    $domain->addCondition(new DomainCondition('assembly_date', '>=', $params['date_from']));
}

if(isset($params['date_to']) && $params['date_to']) {
    $domain->addCondition(new DomainCondition('assembly_date', '<=', $params['date_to']));
}

$params['domain'] = $domain->toArray();
$result = eQual::run('get', 'model_collect', $params, true);

$context->httpResponse()
        ->body($result)
        ->send();

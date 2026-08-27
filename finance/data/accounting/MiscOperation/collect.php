<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use equal\orm\Domain;
use equal\orm\DomainCondition;

[$params, $providers] = eQual::announce([
    'description' => 'Advanced search for miscellaneous operations by fiscal year and journal.',
    'extends'     => 'core_model_collect',
    'params'      => [
        'entity' => [
            'description' => 'Name of the entity to collect.',
            'type'        => 'string',
            'default'     => 'finance\accounting\MiscOperation'
        ],
        'domain' => [
            'description' => 'Criteria that results have to match.',
            'type'        => 'array',
            'default'     => []
        ],
        'condo_id' => [
            'type'           => 'many2one',
            'foreign_object' => 'realestate\property\Condominium',
            'description'    => 'The condominium the miscellaneous operations relate to.',
            'default'        => function($domain = []) {
                $condo_id = null;

                $original_domain = new Domain($domain);
                foreach($original_domain->getClauses() as $clause) {
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
        'fiscal_year_id' => [
            'type'           => 'many2one',
            'foreign_object' => 'finance\accounting\FiscalYear',
            'description'    => 'The fiscal year the miscellaneous operations relate to.',
            'domain'         => ['condo_id', '=', 'object.condo_id']
        ],
        'journal_id' => [
            'type'           => 'many2one',
            'foreign_object' => 'finance\accounting\Journal',
            'description'    => 'The journal the miscellaneous operations relate to.',
            'domain'         => ['condo_id', '=', 'object.condo_id'],
            'visible'        => ['condo_id', '<>', null]
        ]
    ],
    'response' => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers' => ['context']
]);

/** @var \equal\php\Context $context */
['context' => $context] = $providers;

$domain = new Domain();

if(!empty($params['condo_id'])) {
    $domain->addCondition(new DomainCondition('condo_id', '=', $params['condo_id']));
}

if(!empty($params['fiscal_year_id'])) {
    $domain->addCondition(new DomainCondition('fiscal_year_id', '=', $params['fiscal_year_id']));
}

if(!empty($params['journal_id'])) {
    $domain->addCondition(new DomainCondition('journal_id', '=', $params['journal_id']));
}

$params['domain'] = $domain->toArray();
$result = eQual::run('get', 'model_collect', $params, true);

$context->httpResponse()
    ->body($result)
    ->send();

<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use equal\orm\Domain;
use equal\orm\DomainCondition;
use finance\accounting\FiscalYear;
use finance\accounting\Journal;

[$params, $providers] = eQual::announce([
    'description'   => 'Advanced search for Accounting entries: returns a collection according to extra parameters.',
    'extends'       => 'core_model_collect',
    'params'        => [
        'entity' =>  [
            'description'       => 'name',
            'type'              => 'string',
            'default'           => 'finance\accounting\AccountingEntry'
        ],

        'domain' => [
            'description'   => 'Criterias that results have to match (series of conjunctions)',
            'type'          => 'array',
            'default'       => []
        ],

        'condo_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'realestate\property\Condominium',
            'description'       => "The condominium the accounting entry relates to.",
            'default'           => function($domain=[]) {
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
        'fiscal_year_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'finance\accounting\FiscalYear',
            'description'       => "The fiscal year the accounting entry relates to.",
            'domain'            => ['condo_id', '=', 'object.condo_id'],
        ],
        'journal_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'finance\accounting\Journal',
            'description'       => "The journal the accounting entry relates to.",
            'domain'            => ['condo_id', '=', 'object.condo_id'],
        ],
        'date_from' => [
            'type'               => 'date',
            'description'        => "First date of the time interval.",
            'default'           => function($fiscal_year_id=null) {
                if($fiscal_year_id) {
                    $fiscalYear = FiscalYear::id($fiscal_year_id)->read(['date_from'])->first();
                    if($fiscalYear) {
                        return $fiscalYear['date_from'];
                    }
                }
                return null;
            }
        ],
        'date_to' => [
            'type'              => 'date',
            'description'       => "Last date of the time interval.",
            'default'           => function($fiscal_year_id=null) {
                if($fiscal_year_id) {
                    $fiscalYear = FiscalYear::id($fiscal_year_id)->read(['date_to'])->first();
                    if($fiscalYear) {
                        return $fiscalYear['date_to'];
                    }
                }
                return null;
            }
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

if(isset($params['date_from'], $params['date_to']) || isset($params['fiscal_year_id']) && $params['fiscal_year_id'] > 0) {
    if(isset($params['fiscal_year_id']) && $params['fiscal_year_id'] > 0) {
        $fiscalYear = FiscalYear::id($params['fiscal_year_id'])
            ->read(['date_from', 'date_to'])
            ->first();
        $date_from = $fiscalYear['date_from'];
        $date_to = $fiscalYear['date_to'];
    }
    else {
        $date_from = $params['date_from'];
        $date_to = $params['date_to'];
    }

    $domain->addCondition(new DomainCondition('entry_date', '>=', $date_from));
    $domain->addCondition(new DomainCondition('entry_date', '<=', $date_to));
}


if(isset($params['journal_id']) && $params['journal_id'] > 0) {
    $journal = Journal::id($params['journal_id'])->read(['journal_type'])->first();
    if($journal && $journal['journal_type'] !== 'LEDG') {
        $domain->addCondition(new DomainCondition('journal_id', '=', $params['journal_id']));
    }
}

$params['domain'] = $domain->toArray();
$result = eQual::run('get', 'model_collect', $params, true);

$context->httpResponse()
        ->body($result)
        ->send();

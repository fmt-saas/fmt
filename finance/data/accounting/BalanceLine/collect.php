<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use equal\orm\Domain;
use finance\accounting\Account;
use finance\accounting\AccountingEntry;
use finance\accounting\AccountingEntryLine;
use finance\accounting\FiscalYear;
use realestate\property\Condominium;

list($params, $providers) = eQual::announce([
    'description'   => 'Advanced search for Balance Lines: returns a collection of Reports according to extra parameters.',
    'extends'       => 'core_model_collect',
    'params'        => [

        'entity' =>  [
            'description'       => 'name',
            'type'              => 'string',
            'default'           => 'finance\accounting\BalanceLine',
            'help'              => 'This value should be relayed from view and be either CurrentBalanceLine or ClosingBalanceLine.'
        ],

        'has_fiscal_year' => [
            'type'              => 'boolean',
            'label'             => 'Fiscal Year',
            'description'       => "Toggle to specify fiscal year or use arbitrary dates.",
            'default'           => true
        ],

        'fiscal_year_id' => [
            'type'              => 'many2one',
            'description'       => "The fiscal year the balance refers to.",
            'foreign_object'    => 'finance\accounting\FiscalYear',
            'domain'            => ['condo_id', '=', 'object.condo_id'],
            'default'           => function($domain=[]) {
                $fiscal_year_ids = FiscalYear::search(Domain::conditionAdd($domain, ['status', '=', 'open']),  ['sort' => ['date_from' => 'desc']])->ids();
                return count($fiscal_year_ids) ? current($fiscal_year_ids) : null;
            }
        ],

        'date_from' => [
            'type'              => 'date',
            'description'       => 'First date of the time range.',
        ],

        'date_to' => [
            'type'              => 'date',
            'description'       => 'Last date of the time range.'
        ],

        'condo_id' => [
            'type'              => 'many2one',
            'description'       => "The condominium the fiscal year refers to.",
            'help'              => "When a fiscal year is not linked to a condominium, it relates to the organisation itself.",
            'foreign_object'    => 'realestate\property\Condominium',
            'default'           => function($domain=[]) {
                $condos_ids = Condominium::search($domain)->ids();
                return count($condos_ids) ? current($condos_ids) : null;
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
list($context, $orm) = [ $providers['context'], $providers['orm'] ];


$result = [];

// Add conditions to the domain to consider advanced parameters
$domain = $params['domain'];

// fiscal year is given : use BalanceLine
if($params['has_fiscal_year']) {
    if(isset($params['fiscal_year_id']) && $params['fiscal_year_id'] > 0) {
        $domain = Domain::conditionAdd($domain, ['fiscal_year_id', '=', $params['fiscal_year_id']]);
        $params['domain'] = $domain;
        $result = eQual::run('get', 'model_collect', $params, true);
    }
}
// arbitrary dates : compute result on the fly, based on accounting entries
else {
    if(isset($params['date_from'], $params['date_to']) && $params['date_from'] > 0 && $params['date_to'] > 0) {
        // #memo - condo_id is expected to be in the domain
        $domain_entries = Domain::conditionAdd(Domain::conditionAdd($domain, ['entry_date', '>=', $params['date_from']]), ['entry_date', '<=', $params['date_to']]);
        $entries_ids = AccountingEntry::search($domain_entries)->ids();

        $entry_lines = AccountingEntryLine::search(['accounting_entry_id', 'in', $entries_ids])
            ->read(['account_id', 'debit', 'debit'])
            ->get();

        $map_accounts_ids = [];
        $totals = [];

        foreach($entry_lines as $line) {
            $account_id = $line['account_id'];
            $map_accounts_ids[$account_id] = true;
            $debit   = $line['debit'];
            $credit  = $line['credit'];

            $totals[$account_id]['debit']  = ($totals[$account_id]['debit'] ?? 0) + $debit;
            $totals[$account_id]['credit'] = ($totals[$account_id]['credit'] ?? 0) + $credit;
        }
        // fetch all accounts at once
        $accounts = Account::ids(array_keys($map_accounts_ids))->read(['id', 'name'])->get();

        // generate virtual fields
        $i = 1;
        foreach($totals as $account_id => &$line) {
            $line['id'] = $i++;
            $line['fiscal_year_id'] = null;
            $delta = $line['debit'] - $line['credit'];
            $line['debit_balance']  = max($delta, 0.0);
            $line['credit_balance'] = max(-$delta, 0.0);
            $line['account_id'] = [
                'id'    => $account_id,
                'name'  => $accounts[$account_id]['name']
            ];
        }

        $result = array_values($totals);
    }
}



$context->httpResponse()
        ->body($result)
        ->send();

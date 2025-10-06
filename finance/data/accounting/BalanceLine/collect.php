<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/

use equal\orm\Domain;
use equal\orm\DomainCondition;
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

        'condo_id' => [
            'type'              => 'many2one',
            'description'       => "The condominium the fiscal year refers to.",
            'help'              => "When a fiscal year is not linked to a condominium, it relates to the organisation itself.",
            'foreign_object'    => 'realestate\property\Condominium',
            'default'           => function($domain=[]) {
                // #memo - in some cases fiscal_year_id is provided in $domain and is not valid for Condominium schema
                $condo_id = null;

                // $user_id = $this->am->userId();
                // Setting::get_value('fmt', 'organization', 'user.condo_id', null, ['user_id' => $user_id]);

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
            'description'       => "The fiscal year the balance refers to.",
            'foreign_object'    => 'finance\accounting\FiscalYear',
            'domain'            => ['condo_id', '=', 'object.condo_id'],
            'default'           => function($condo_id=null) {
                $fiscal_year_ids = FiscalYear::search([
                        ['status', '=', 'open'],
                        ['condo_id', '=', $condo_id],
                    ],  ['sort' => ['date_from' => 'desc']])
                    ->ids();
                if(count($fiscal_year_ids) <= 0) {
                    $fiscal_year_ids = FiscalYear::search([
                            ['status', '=', 'preopen'],
                            ['condo_id', '=', $condo_id],
                        ],  ['sort' => ['date_from' => 'asc']])
                        ->ids();
                }
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


/*
// #todo - virtual Ownership collector account
    - collecteur virtuel : enlever le 4è digit pour les comptes ownerships, et fusionner les comptes avec code identique
    - 410xxxxx -> co_owners_reserve_fund + co_owners_working_fund
*/


$result = [];

// Add conditions to the domain to consider advanced parameters
$domain = $params['domain'];

// #todo - on doit fonctionner de la même manière pour toutes les sutations pour ne pas gérze trop de cas

if(isset($params['fiscal_year_id']) && $params['fiscal_year_id'] > 0) {
    $fiscalYear = FiscalYear::id($params['fiscal_year_id'])->read(['date_from', 'date_to'])->first();
    $date_from = $fiscalYear['date_from'];
    $date_to = $fiscalYear['date_to'];
}
elseif(isset($params['date_from'], $params['date_to'])) {
    $date_from = $params['date_from'];
    $date_to = $params['date_to'];
}
else {
    // missing mandatory param
    throw new Exception('missing_fiscal_year_or_dates', EQ_ERROR_MISSING_PARAM);
}

if($date_from <= 0 || $date_to <= 0) {
    // invalid param
    throw new Exception('invalid_dates', EQ_ERROR_INVALID_PARAM);
}

// #memo - condo_id is expected to be in the domain
$domainEntries = new Domain($domain);
$domainEntries->addCondition(new DomainCondition('entry_date', '>=', $date_from));
$domainEntries->addCondition(new DomainCondition('entry_date', '<=', $date_to));

$entries_ids = AccountingEntry::search($domainEntries->toArray())->ids();

$entry_lines = AccountingEntryLine::search(['accounting_entry_id', 'in', $entries_ids])
    ->read(['account_id', 'debit', 'debit'])
    ->get();

$map_accounts_ids = [];
$totals = [];

foreach($entry_lines as $line) {
    $account_id = $line['account_id'];

    // retrouver un éventuel compte collecteur et l'utiliser à la place si l'option est cochée
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


$context->httpResponse()
        ->body($result)
        ->send();

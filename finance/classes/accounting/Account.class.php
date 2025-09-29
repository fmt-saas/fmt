<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace finance\accounting;
use equal\orm\Model;
use fmt\setting\Setting;
use realestate\finance\accounting\AccountingEntry;

class Account extends Model {

    public static function getName() {
        return "Accounting Account";
    }

    public static function getDescription() {
        return "An account holds information related to a specific financial account, including its code, type, nature, and hierarchical position within the chart of accounts.
        The accounts of a condominium are defined in the associated chart of accounts, created from the template configured for the Managing Agent.
        Once created, the accounts in a condominium's chart of accounts can no longer be deleted. If necessary, however, they can be hidden.";
    }

    public static function getColumns() {

        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the account refers to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'readonly'          => true
            ],

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcName',
                'multilang'         => true,
                'description'       => "Name of the account.",
                'store'             => true,
                'instant'           => true,
                'readonly'          => true
            ],

            'code' => [
                'type'              => 'string',
                'description'       => "A variable length string representing the number of the account.",
                'dependents'        => ['name', 'level', 'account_class'],
                'required'          => true
            ],

            'description' => [
                'type'              => 'string',
                'description'       => "Short description of the account.",
                'multilang'         => true
            ],

            'level' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => "Depth of the account in the chart.",
                'function'          => 'calcLevel',
                'store'             => true
            ],

            'account_class' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => "The accounting class of the account.",
                'function'          => 'calcAccountClass',
                'store'             => true
            ],

            'display_account_class' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "The accounting class of the account.",
                'function'          => 'calcAccountClass',
                // #memo - we need string keys because PHP doesn't make a distinction between string and numbers for array keys (therefore map is ignored)
                'selection'         => [
                    '00' => 'Linking and closing accounts',
                    '01' => 'Equity, Provisions, and Long-Term Liabilities',
                    '02' => 'Fixed Assets',
                    '03' => 'Inventories and work-in-progress',
                    '04' => 'Short-term receivables and payables',
                    '05' => 'Financial Accounts',
                    '06' => 'Expenses',
                    '07' => 'Revenues'
                ],
                'store'             => true
            ],

            'account_type' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'selection' => [
                    'B' => 'Balance Sheet',         // bilan
                    'I' => 'Income Statement'       // compte de résultat
                ],
                'function'          => 'calcAccountType',
                'store'             => true
            ],

            'account_nature' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'selection' => [
                    'asset',
                    'liability'
                ],
                'function'          => 'calcAccountNature',
                'store'             => true
            ],

            'account_category' => [
                'type'      => 'string',
                'selection' => [
                    'debt'              => 'Balance Sheet>Fixed assets>Debtor',
                    'bank'              => 'Balance Sheet>Fixed assets>Bank and liquidity',
                    'current_asset'     => 'Balance Sheet>Fixed assets>Current assets',
                    'fixed_asset'       => 'Balance Sheet>Fixed assets>Fixed asset',
                    'prepayment'        => 'Balance Sheet>Fixed assets>Prepayments',
                    'fixed_assets'      => 'Balance Sheet>Fixed assets>Fixed assets',
                    'payable'           => 'Balance Sheet>Liabilities>Payable',
                    'credit_card'       => 'Balance Sheet>Liabilities>Credit card',
                    'short_term_debt'   => 'Balance Sheet>Liabilities>Short term debts',
                    'fixed_liability'   => 'Balance Sheet>Liabilities>Fixed liabilities',
                    'equity'            => 'Balance Sheet>Equity>Equity',
                    'profits_yearly'    => 'Balance Sheet>Equity>Profits for the current year',
                    'income'            => 'Income Statement>Income>Income',
                    'other_income'      => 'Income Statement>Income>Other income',
                    'expenses'          => 'Income Statement>Spent>Expenses',
                    'amortization'      => 'Income Statement>Spent>Amortization',
                    'cost_of_sale'      => 'Income Statement>Spent>Cost of sales',
                    'off_balance'       => 'Other>Off balance sheet'
                ]
            ],

            'parent_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Account',
                'description'       => "The parent account (line) the account is part of.",
                'dependents'        => ['level']
            ],

            'children_accounts_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\accounting\Account',
                'foreign_field'     => 'parent_account_id',
                'description'       => "The children accounts linked to the account (all sub-levels)."
            ],

            'account_chart_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\AccountChart',
                'description'       => 'Parent chart of accounts.',
                'help'              => 'The chart of accounts the line belongs to.',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]],
                'required'          => true
            ],

            'is_visible' => [
                'type'              => 'boolean',
                'description'       => "Flag to switch visibility of the account.",
                'default'           => true
            ],

            'is_control_account' => [
                'type'              => 'boolean',
                'description'       => "Flag telling if the account is a group/collector account.",
                'help'              => "Accounting entries can only be made on non-group accounts.",
                'default'           => false
            ],

            'is_tier_balance' => [
                'type'              => 'boolean',
                'description'       => "Flag to mark the account as part of the tier balance.",
                'default'           => false
            ],

            'operation_assignment' => [
                'type'              => 'string',
                'description'       => "Operation the account is dedicated to.",
                'help'              => "Specific identifier to associate the account with a configuration parameter or a specific operation.",
                'default'           => '',
                'selection'         => [
                    '',
                    'adjustment_account',
                    'bank_current',
                    'bank_savings',
                    'bank_tier',
                    'bank_transfer',
                    'co_owners',
                    'co_owners_reserve_fund',           // used for FundRequestExecution
                    'co_owners_working_fund',           // used for FundRequestExecution
                    'deferred_expenses',                // used for purchase invoice over a date range
                    'deferred_income',
                    'expense_provisions',               // used for FundRequest
                    'installment_intermediate_account',
                    'manager_fees',
                    'pending_creditor_import',
                    'pending_debtor_import',
                    'pending_work_balance',
                    'private_expenses',                 // used for purchase invoice
                    'reinvoiced_private_expenses',      // used for purchase invoice
                    'reserve_fund',                     // used for FundRequest
                    'rounding_adjustment',
                    'suppliers',
                    'special_reserve_fund',             // used for FundRequest
                    'work_expenses',
                    'work_provisions',                  // used for FundRequest
                    'working_fund'                      // used for FundRequest
                ]
            ],

            'apportionment_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\property\Apportionment',
                'description'       => "Default apportionment to use when creating accounting entries on this account.",
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'tenant_share' => [
                'type'              => 'integer',
                'description'       => "Default value, in percent, of the amount to be imputed to the tenant when using the account.",
            ],

            'owner_share' => [
                'type'              => 'integer',
                'description'       => "Default value, in percent, of the amount to be imputed to the owner when using the account."
            ],


            /*

            */

            'accounting_account_id' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\accounting\Matching',
                'foreign_field'     => 'accounting_account_id',
                'description'       => "Matchings referencing the account.",
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]]
            ],

            /*
                entities the Account can possibly be linked to
            */

            'ownership_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\ownership\Ownership',
                'ondelete'          => 'null',
                'description'       => "The ownership that the account refers to, if any.",
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]]
            ],

            'suppliership_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'purchase\supplier\Suppliership',
                'ondelete'          => 'null',
                'description'       => 'The supplier the account relates to, if any.',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]]
            ],

            'condo_bank_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\bank\CondominiumBankAccount',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]],
                'ondelete'          => 'null',
                'description'       => 'Condominium bank account the account relates to, if any.'
            ]

        ];
    }

    public function getUnique() {
        return [
            ['condo_id', 'code']
        ];
    }

    public static function calcAccountNature($self) {
        $result = [];
        $self->read(['code']);
        foreach($self as $id => $account) {
            if($account['code'] && strlen($account['code']) >= 1) {
                $one_digit  = (int) substr($account['code'], 0, 1);
                $two_digits = (int) substr($account['code'], 0, 2);
                if (in_array($one_digit, [2, 3, 5]) || ($two_digits >= 40 && $two_digits <= 41)) {
                    $result[$id] = 'asset'; // Actif
                }
                elseif($one_digit == 1 || ($two_digits >= 44 && $two_digits <= 47)) {
                    $result[$id] = 'liability'; // Passif
                }
            }
        }
        return $result;
    }

    public static function calcAccountType($self) {
        $result = [];
        $self->read(['code']);
        foreach($self as $id => $account) {
            if($account['code']) {
                $result[$id] = (intval(substr($account['code'], 0, 1)) < 6) ? 'B' : 'I' ;
            }
        }
        return $result;
    }

    public static function calcAccountClass($self) {
        $result = [];
        $self->read(['code']);
        foreach($self as $id => $account) {
            if($account['code']) {
                $result[$id] = intval(substr($account['code'], 0, 1));
            }
        }
        return $result;
    }


    /**
     * Level is used in conjunction with code to display
     * #memo - level cannot be directly based parent-children links, because some levels might be missing.
     */
    public static function calcLevel($self) {
        $result = [];
        $self->read(['code']);
        foreach($self as $id => $line) {
            if(isset($line['code'])) {
                $result[$id] = strlen($line['code']);
            }
        }
        return $result;
    }

    public static function calcName($self) {
        $result = [];
        $self->read(['code', 'description']);
        foreach($self as $id => $line) {
            if(!isset($line['code']) || strlen($line['code']) <= 0) {
                continue;
            }
            $name = $line['code'];
            if($line['description'] && strlen($line['description']) > 0) {
                $name .= ' - ' . $line['description'];
            }
            $result[$id] = $name;
        }
        return $result;
    }

    public static function candelete($self) {
        $self->read(['condo_id']);
        foreach($self as $id => $account) {
            if(!$account['condo_id']) {
                continue;
            }
            $accounting_entries_ids = AccountingEntry::search([ ['condo_id', '=', $account['condo_id']], ['account_id', '=', $id] ])->ids();
            if(count($accounting_entries_ids) > 0) {
                return ['id' => ['non_removable' => 'Account with accounting entries cannot be removed.']];
            }
        }
        return parent::candelete($self);
    }

}

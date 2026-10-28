<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace finance\accounting;
use core\Lang;
use equal\orm\Model;
use realestate\finance\accounting\AccountingEntry;
use realestate\property\Apportionment;

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
                'usage'             => 'text/plain:10',
                'description'       => "A variable length string representing the number of the account.",
                'dependents'        => ['name', 'level', 'account_class'],
                'required'          => true
            ],

            'description' => [
                'type'              => 'string',
                'description'       => "Short description of the account.",
                'dependents'        => ['name'],
                'multilang'         => true
            ],

            'suffix' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcSuffix',
                'description'       => "Suffix inherited from the account apportionment.",
                'dependents'        => ['name'],
                'multilang'         => true,
                'store'             => false,
                'readonly'          => true,
                'visible'           => ['is_control_account', '=', true]
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
                    '',
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
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'finance\accounting\Account',
                'domain'            => ['condo_id', '=', 'object.condo_id'],
                'description'       => 'The parent control account the account is part of.',
                'help'              => 'The first account that has a common prefix and marked as control control account.',
                'function'          => 'calcParentAccountId',
                'store'             => true,
                'readonly'          => true
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
                'default'           => false,
                'onupdate'          => 'onupdateIsControlAccount'
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
                    'accrued_expenses',                 // used for PurchaseInvoice before a date range
                    'accrued_income',
                    'adjustment_account',
                    'bank_current',
                    'bank_fees',
                    'bank_savings',
                    'bank_tier',
                    'bank_transfer',
                    'co_owners',
                    'co_owners_reserve_fund',
                    'co_owners_working_fund',
                    'co_owners_owner',                  // single Owner, parent account of specific co_owners_owner_reserve_fund & co_owners_owner_working_fund
                    'co_owners_owner_reserve_fund',     // used for FundRequestExecution
                    'co_owners_owner_working_fund',     // used for FundRequestExecution
                    'co_owners_former',
                    'co_owners_repayment',
                    'consumption_statement',
                    'deferred_expenses',                // used for PurchaseInvoice over a date range
                    'deferred_income',
                    'expense_provisions',               // used for FundRequest
                    'pending_creditor_import',
                    'pending_debtor_import',
                    // not in imported Chart of Accounts
                    'manager_fees',
                    // not in imported Chart of Accounts
                    'pending_work_balance',
                    'private_expenses',                 // used for PurchaseInvoice
                    'reinvoiced_private_expenses',      // used for PurchaseInvoice
                    'reserve_fund',                     // used for CondoFund
                    'reserve_fund_call',                // used for FundRequest
                    'reserve_fund_variation',
                    'rounding_adjustment',
                    'special_reserve_fund',             // used for CondoFund
                    'special_reserve_fund_call',        // used for FundRequest
                    'special_reserve_fund_variation',
                    'suppliers',
                    'suppliers_supplier',               // single Supplier
                    // not in imported Chart of Accounts
                    'work_expenses',
                    'work_provisions',                  // used for FundRequest
                    'working_fund',                     // used for CondoFund
                    'working_fund_call',                // used for FundRequest
                    'working_fund_variation'
                ]
            ],

            'is_apportionable' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => "Is an apportionment possible?",
                'function'          => 'calcIsApportionable',
                'store'             => true
            ],

            'apportionment_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\property\Apportionment',
                'description'       => "Default apportionment to use when creating accounting entries on this account.",
                'domain'            => ['condo_id', '=', 'object.condo_id'],
                'dependents'        => ['suffix', 'name'],
                'visible'           => ['is_apportionable', '=', true],
                'onupdate'          => 'onupdateApportionmentId'
            ],

            'property_entrances_ids' => [
                'type'              => 'many2many',
                'description'       => "Property entrances for which this account must be duplicated.",
                'foreign_object'    => 'realestate\property\PropertyEntrance',
                'foreign_field'     => 'duplication_accounts_ids',
                'rel_table'         => 'realestate_propertyentrance_rel_account',
                'rel_local_key'     => 'account_id',
                'rel_foreign_key'   => 'property_entrance_id'
            ],

            'vat_rate' => [
                'type'              => 'float',
                'usage'             => 'amount/rate',
                'description'       => 'VAT rate to be applied.',
                'default'           => 0.0
            ],

            'tenant_share' => [
                'type'              => 'integer',
                'description'       => "Default value, in percent, of the amount to be imputed to the tenant when using the account.",
                'visible'           => ['is_apportionable', '=', true]
            ],

            'owner_share' => [
                'type'              => 'integer',
                'description'       => "Default value, in percent, of the amount to be imputed to the owner when using the account.",
                'visible'           => ['is_apportionable', '=', true]
            ],

            // #todo - this field is not handled yet
            'owner_share_usufruct' => [
                'type'              => 'integer',
                'usage'             => 'amount/natural',
                'description'       => "Default value, in percent, of the amount to be imputed to the owner when using the account.",
                'default'           => 0
            ],

            // #todo - this field is not handled yet
            'owner_share_bare' => [
                'type'              => 'integer',
                'usage'             => 'amount/natural',
                'description'       => "Default value, in percent, of the amount to be imputed to the owner when using the account.",
                'default'           => 100
            ],

            'is_reconcilable' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => "Indicates whether entries posted on this account are expected to be reconciled with bank statement lines through funding records.",
                'function'          => 'calcIsReconcilable',
                'store'             => true
            ],

            'matchings_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\accounting\Matching',
                'foreign_field'     => 'accounting_account_id',
                'description'       => 'Matchings targeting the account.',
                'visible'           => ['is_matchable', '=', true],
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
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null], ['is_active', '=', true]],
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

    public static function getPolicies(): array {
        return array_merge(parent::getPolicies(), [
            'can_duplicate_tree' => [
                'description' => 'Verifies that an account and its sub-accounts can be duplicated.',
                'function'    => 'policyCanDuplicateTree'
            ]
        ]);
    }

    public static function getActions() {
        return array_merge(parent::getActions(), [
            'duplicate_tree' => [
                'description' => 'Duplicates an account and all its sub-accounts with the selected apportionment.',
                'help'        => 'Only six-digit expense control accounts ending in 0 and without operation assignments in their hierarchy can be duplicated.',
                'policies'    => ['can_duplicate_tree'],
                'function'    => 'doDuplicateTree'
            ],
            'refresh_descendant_parents' => [
                'description' => 'Recomputes the parent account of all descendant accounts.',
                'policies'    => [],
                'function'    => 'doRefreshDescendantParents'
            ],
            'sync_descendant_apportionment' => [
                'description' => 'Synchronizes the apportionment of all descendant accounts.',
                'policies'    => [],
                'function'    => 'doSyncDescendantApportionment'
            ]
        ]);
    }

    protected static function onupdateIsControlAccount($self) {
        $self->do('refresh_descendant_parents');
    }

    protected static function onupdateApportionmentId($self) {
        $self->do('sync_descendant_apportionment');
    }

    protected static function doRefreshDescendantParents($self) {
        $self->read(['condo_id', 'account_chart_id', 'code']);

        $descendant_ids = [];

        foreach($self as $id => $account) {
            if(!$account['condo_id'] || !$account['account_chart_id'] || !$account['code']) {
                continue;
            }

            $prefix = (string) $account['code'];
            $candidates = self::search([
                    ['condo_id', '=', $account['condo_id']],
                    ['account_chart_id', '=', $account['account_chart_id']],
                    ['code', 'like', $prefix . '%']
                ])
                ->read(['code']);

            foreach($candidates as $candidate_id => $candidate) {
                $candidateCode = (string) $candidate['code'];

                if(
                    (int) $candidate_id === (int) $id
                    || strlen($candidateCode) <= strlen($prefix)
                    || strncmp($candidateCode, $prefix, strlen($prefix)) !== 0
                ) {
                    continue;
                }

                $descendant_ids[$candidate_id] = true;
            }
        }

        if(empty($descendant_ids)) {
            return;
        }

        $descendants = self::ids(array_keys($descendant_ids));

        $descendants
            ->update(['parent_account_id' => null])
            ->read(['parent_account_id']);
    }

    protected static function doSyncDescendantApportionment($self) {
        $self->read(['condo_id', 'account_chart_id', 'code', 'level', 'is_control_account', 'apportionment_id']);

        foreach($self as $account) {
            if(
                !$account['is_control_account']
                || !$account['condo_id']
                || !$account['account_chart_id']
                || !$account['code']
                || $account['level'] < 6
            ) {
                continue;
            }

            $prefix = (string) $account['code'];
            $targetApportionmentId = $account['apportionment_id'] ? (int) $account['apportionment_id'] : null;
            $descendantIds = [];

            $candidates = self::search([
                    ['condo_id', '=', $account['condo_id']],
                    ['account_chart_id', '=', $account['account_chart_id']],
                    ['code', 'like', $prefix . '%']
                ])
                ->read(['code', 'apportionment_id']);

            foreach($candidates as $candidateId => $candidate) {
                $candidateCode = (string) $candidate['code'];
                $candidateApportionmentId = $candidate['apportionment_id'] ? (int) $candidate['apportionment_id'] : null;

                if(
                    strlen($candidateCode) <= strlen($prefix)
                    || strncmp($candidateCode, $prefix, strlen($prefix)) !== 0
                    || $candidateApportionmentId === $targetApportionmentId
                ) {
                    continue;
                }

                $descendantIds[] = $candidateId;
            }

            if(count($descendantIds)) {
                self::ids($descendantIds)->write(['apportionment_id' => $targetApportionmentId]);
            }
        }
    }

    protected static function policyCanDuplicateTree($self): array {
        $result = [];

        $self->read([
            'condo_id',
            'account_chart_id',
            'code',
            'account_type',
            'account_class',
            'is_control_account'
        ]);

        foreach($self as $id => $account) {
            if(!$account['condo_id']) {
                $result[$id] = [
                    'missing_condo_id' => 'The account must belong to a condominium.'
                ];
                continue;
            }

            if(!$account['account_chart_id']) {
                $result[$id] = [
                    'missing_account_chart_id' => 'The account must belong to a chart of accounts.'
                ];
                continue;
            }

            $accountChart = AccountChart::id($account['account_chart_id'])
                ->read(['condo_id'])
                ->first();

            if(!$accountChart || !$accountChart['condo_id'] || $accountChart['condo_id'] !== $account['condo_id']) {
                $result[$id] = [
                    'invalid_account_chart' => 'The chart of accounts must belong to the same condominium as the account.'
                ];
                continue;
            }

            if(!$account['is_control_account']) {
                $result[$id] = [
                    'not_control_account' => 'Only a control account can be duplicated with its sub-accounts.'
                ];
                continue;
            }

            if($account['account_type'] !== 'I') {
                $result[$id] = [
                    'invalid_account_type' => 'Only an income statement account can be duplicated.'
                ];
                continue;
            }

            if($account['account_class'] !== 6) {
                $result[$id] = [
                    'invalid_account_class' => 'Only a class 6 expense account can be duplicated.'
                ];
                continue;
            }

            $sourceCode = (string) $account['code'];
            if(strlen($sourceCode) !== 6 || !ctype_digit($sourceCode) || substr($sourceCode, -1) !== '0') {
                $result[$id] = [
                    'invalid_source_code' => 'The source account code must contain exactly six digits and end in 0.'
                ];
                continue;
            }

            $targetRootCode = self::computeNextDuplicateRootCode($account['account_chart_id'], $sourceCode);
            if($targetRootCode === null) {
                $result[$id] = [
                    'duplicate_index_exhausted' => 'No duplicate index is available for this account code.'
                ];
                continue;
            }

            $chartAccounts = self::search([
                    ['account_chart_id', '=', $account['account_chart_id']]
                ])
                ->read(['code', 'is_control_account', 'operation_assignment']);

            $existingCodes = [];
            $condoAccounts = self::search([
                    ['condo_id', '=', $account['condo_id']]
                ])
                ->read(['code']);

            foreach($condoAccounts as $condoAccount) {
                $existingCodes[(string) $condoAccount['code']] = true;
            }

            $hasOperationAssignment = false;

            foreach($chartAccounts as $chartAccount) {
                $chartAccountCode = (string) $chartAccount['code'];

                $isSourceAccount = $chartAccountCode === $sourceCode;
                $isDescendant = strlen($chartAccountCode) > strlen($sourceCode)
                    && strncmp($chartAccountCode, $sourceCode, strlen($sourceCode)) === 0;
                $isAncestor = $chartAccount['is_control_account']
                    && strlen($chartAccountCode) < strlen($sourceCode)
                    && strncmp($sourceCode, $chartAccountCode, strlen($chartAccountCode)) === 0;

                if(($isSourceAccount || $isDescendant || $isAncestor) && $chartAccount['operation_assignment']) {
                    $hasOperationAssignment = true;
                }

            }

            if($hasOperationAssignment) {
                $result[$id] = [
                    'assigned_related_account' => 'The account hierarchy must not contain an operation assignment.'
                ];
                continue;
            }

            foreach(array_keys($existingCodes) as $existingCode) {
                if(strncmp($existingCode, $targetRootCode, strlen($targetRootCode)) === 0) {
                    $result[$id] = [
                        'target_code_exists' => 'The target account hierarchy already contains at least one account.'
                    ];
                    break;
                }
            }
        }

        return $result;
    }

    protected static function doDuplicateTree($self, $values, $lang) {
        if(!isset($values['apportionment_id']) || $values['apportionment_id'] <= 0) {
            throw new \Exception('missing_apportionment_id', EQ_ERROR_INVALID_PARAM);
        }

        $apportionment = Apportionment::id($values['apportionment_id'])
            ->read(['condo_id', 'is_statutory'])
            ->first();

        if(!$apportionment || $apportionment['is_statutory']) {
            throw new \Exception('invalid_apportionment', EQ_ERROR_INVALID_PARAM);
        }

        $copyFields = [
            'code',
            'condo_id',
            'account_chart_id',
            'description',
            'account_category',
            'is_visible',
            'is_control_account',
            'is_tier_balance',
            'operation_assignment',
            'vat_rate',
            'tenant_share',
            'owner_share',
            'owner_share_usufruct',
            'owner_share_bare',
            'ownership_id',
            'suppliership_id',
            'condo_bank_account_id'
        ];

        $self->read(['account_chart_id', 'code', 'condo_id']);

        foreach($self as $account) {
            if((int) $account['condo_id'] !== (int) $apportionment['condo_id']) {
                throw new \Exception('invalid_apportionment', EQ_ERROR_INVALID_PARAM);
            }

            $sourceCode = (string) $account['code'];
            $targetRootCode = self::computeNextDuplicateRootCode($account['account_chart_id'], $sourceCode);
            if($targetRootCode === null) {
                throw new \Exception('duplicate_index_exhausted', EQ_ERROR_INVALID_PARAM);
            }

            $sourceAccounts = self::search(
                    [
                        ['account_chart_id', '=', $account['account_chart_id']],
                        ['code', 'like', $sourceCode . '%']
                    ],
                    ['sort' => ['code' => 'asc']]
                )
                ->read($copyFields, $lang);

            $map_target_accounts_ids = [];

            foreach($sourceAccounts as $source_account_id => $sourceAccount) {
                $targetCode = $targetRootCode . substr($sourceAccount['code'], strlen($sourceCode));
                $newAccount = self::create([
                        'condo_id'              => $sourceAccount['condo_id'],
                        'account_chart_id'      => $sourceAccount['account_chart_id'],
                        'code'                  => $targetCode,
                        'description'           => $sourceAccount['description'],
                        'account_category'      => $sourceAccount['account_category'],
                        'is_visible'            => $sourceAccount['is_visible'],
                        'is_control_account'    => $sourceAccount['is_control_account'],
                        'is_tier_balance'       => $sourceAccount['is_tier_balance'],
                        'operation_assignment'  => $sourceAccount['operation_assignment'],
                        'apportionment_id'       => (int) $values['apportionment_id'],
                        'vat_rate'               => $sourceAccount['vat_rate'],
                        'tenant_share'           => $sourceAccount['tenant_share'],
                        'owner_share'            => $sourceAccount['owner_share'],
                        'owner_share_usufruct'   => $sourceAccount['owner_share_usufruct'],
                        'owner_share_bare'       => $sourceAccount['owner_share_bare'],
                        'ownership_id'           => $sourceAccount['ownership_id'],
                        'suppliership_id'        => $sourceAccount['suppliership_id'],
                        'condo_bank_account_id'  => $sourceAccount['condo_bank_account_id']
                    ],
                    $lang
                )
                ->first();

                $map_target_accounts_ids[$source_account_id][] = $newAccount['id'];
            }

            $languages = Lang::search()->read(['code']);

            foreach($languages as $language) {
                if($language['code'] === $lang) {
                    continue;
                }

                $translatedAccounts = self::ids(array_keys($map_target_accounts_ids))
                    ->read(['description'], $language['code']);

                foreach($translatedAccounts as $source_account_id => $translatedAccount) {
                    self::ids($map_target_accounts_ids[$source_account_id])
                        ->update([
                                'description'   => $translatedAccount['description'],
                                'suffix'        => null
                            ],
                            $language['code']
                        );
                }
            }
        }
    }

    private static function computeNextDuplicateRootCode(int $account_chart_id, string $source_code): ?string {
        $index_length = substr($source_code, -2) === '00' ? 2 : 1;
        $code_prefix = substr($source_code, 0, -$index_length);
        $max_index = (10 ** $index_length) - 1;
        $existing_indexes = [];
        $accounts = self::search([
                ['account_chart_id', '=', $account_chart_id],
                ['code', 'like', $code_prefix . '%']
            ])
            ->read(['code']);

        foreach($accounts as $account) {
            $account_code = (string) $account['code'];
            if(strlen($account_code) !== 6 || strncmp($account_code, $code_prefix, strlen($code_prefix)) !== 0) {
                continue;
            }

            $index = substr($account_code, -$index_length);
            if(!ctype_digit($index)) {
                continue;
            }

            $existing_indexes[(int) $index] = true;
        }

        $duplicate_count = count($existing_indexes) - (isset($existing_indexes[0]) ? 1 : 0);
        if($duplicate_count >= $max_index) {
            return null;
        }

        for($index = 1; $index <= $max_index; ++$index) {
            if($index_length === 2 && $index % 10 === 0) {
                continue;
            }

            if(!isset($existing_indexes[$index])) {
                return $code_prefix . str_pad((string) $index, $index_length, '0', STR_PAD_LEFT);
            }
        }

        return null;
    }

    private static function computeAccountNature(string $code) {
        $result = null;
       if($code && strlen($code) >= 1) {
            $one_digit  = (int) substr($code, 0, 1);
            $two_digits = (int) substr($code, 0, 2);
            if (in_array($one_digit, [2, 3, 5]) || ($two_digits >= 40 && $two_digits <= 41)) {
                $result = 'asset'; // Actif
            }
            elseif($one_digit == 1 || ($two_digits >= 44 && $two_digits <= 47)) {
                $result = 'liability'; // Passif
            }
        }
        return $result;
    }

    private static function computeAccountType(string $code) {
        $result = null;
        if($code && strlen($code) >= 1) {
            $result = (intval(substr($code, 0, 1)) < 6) ? 'B' : 'I' ;
        }
        return $result;
    }

    private static function computeAccountClass(string $code) {
        $result = null;
        if($code && strlen($code) >= 1) {
            $result = intval(substr($code, 0, 1));
        }
        return $result;
    }

    private static function computeParentAccountId(string $code, int $condo_id) {
        $result = null;
        if($condo_id && $code && strlen($code) >= 1) {

            $account_class = intval(substr($code, 0, 1));

            $map_accounts_codes = [];
            $accounts = self::search([
                    ['condo_id', '=', $condo_id],
                    ['is_control_account', '=', true],
                    ['account_class', '=', intval($account_class)]
                ])
                ->read(['id', 'code']);

            foreach($accounts as $control_account_id => $controlAccount) {
                $map_accounts_codes[$controlAccount['code']] = $control_account_id;
            }

            $parent_code = $code;

            while(strlen($parent_code) > 1) {
                $parent_code = substr($parent_code, 0, -1);
                if(isset($map_accounts_codes[$parent_code])) {
                    $result = $map_accounts_codes[$parent_code];
                    break;
                }
            }
        }

        return $result;
    }

    protected static function calcIsReconcilable($self) {
        $result = [];
        $self->read(['operation_assignment']);
        foreach($self as $id => $account) {
            $result[$id] = false;
            if($account['operation_assignment']) {
                $result[$id] = in_array($account['operation_assignment'], [
                        'bank_transfer',
                        'suppliers_supplier',
                        'co_owners_owner',
                        'co_owners_owner_reserve_fund',
                        'co_owners_owner_working_fund'
                    ], true);
            }
        }
        return $result;
    }

    protected static function calcIsApportionable($self) {
        $result = [];
        $self->read(['account_class']);
        foreach($self as $id => $account) {
            if(!$account['account_class']) {
                continue;
            }
            $result[$id] = ($account['account_class'] === 6 || $account['account_class'] === 7);
        }
        return $result;
    }

    protected static function calcAccountNature($self) {
        $result = [];
        $self->read(['code']);
        foreach($self as $id => $account) {
            if(!$account['code']) {
                continue;
            }
            $result[$id] = self::computeAccountNature($account['code']);
        }
        return $result;
    }

    protected static function calcAccountType($self) {
        $result = [];
        $self->read(['code']);
        foreach($self as $id => $account) {
            if(!$account['code']) {
                continue;
            }
            $result[$id] = self::computeAccountType($account['code']);
        }
        return $result;
    }

    protected static function calcAccountClass($self) {
        $result = [];
        $self->read(['code']);
        foreach($self as $id => $account) {
            if(!$account['code']) {
                continue;
            }
            $result[$id] = self::computeAccountClass($account['code']);
        }
        return $result;
    }

    protected static function calcParentAccountId($self) {
        $result = [];
        $self->read(['condo_id', 'code']);

        foreach($self as $id => $account) {
            if(!$account['code']) {
                continue;
            }
            $result[$id] = self::computeParentAccountId($account['code'], $account['condo_id']);
        }

        return $result;
    }

    /**
     * Level is used in conjunction with code to display
     * #memo - level cannot be directly based parent-children links, because some levels might be missing.
     */
    protected static function calcLevel($self) {
        $result = [];
        $self->read(['code']);
        foreach($self as $id => $account) {
            if(!$account['code']) {
                continue;
            }
            $result[$id] = strlen($account['code']);
        }
        return $result;
    }

    protected static function calcName($self, $lang) {
        $result = [];
        $self->read(['code', 'description', 'suffix'], $lang);
        foreach($self as $id => $line) {
            if(!isset($line['code']) || strlen($line['code']) <= 0) {
                continue;
            }
            $name = $line['code'];
            if($line['description'] && strlen($line['description']) > 0) {
                $name .= ' - ' . $line['description'];
            }
            if($line['suffix'] && strlen($line['suffix']) > 0) {
                $name .= ' - ' . $line['suffix'];
            }
            $result[$id] = $name;
        }
        return $result;
    }

    protected static function calcSuffix($self, $lang) {
        $result = [];
        $self->read([
            'apportionment_id' => ['suffix']
        ], $lang);

        foreach($self as $id => $account) {
            if(!$account['apportionment_id']) {
                continue;
            }

            $result[$id] = $account['apportionment_id']['suffix'];
        }

        return $result;
    }


    public static function onchange($event, $values) {
        $result = [];

        if(isset($event['code']) && strlen($event['code']) >= 1) {
            $result['account_nature'] = self::computeAccountNature($event['code']);
            $result['account_type'] = self::computeAccountType($event['code']);
            $result['account_class'] = self::computeAccountClass($event['code']);
            $parent_account_id = self::computeParentAccountId($event['code'], $event['condo_id'] ?? $values['condo_id'] ?? null);
            if($parent_account_id) {
                $parentAccount = self::id($parent_account_id)->read(['id', 'name'])->first();
                $result['parent_account_id'] = ['id' => $parentAccount['id'], 'name' => $parentAccount['name']];
            }
        }

        return $result;
    }

    protected static function candelete($self) {
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

    protected static function canupdate($self, $values) {
        // cannot create account of class 1 (except for long-term debts)
        if(isset($values['code'])) {
            if(substr($values['code'], 0, 1) === '1') {
                if(substr($values['code'], 0, 2) !== '17') {
                    return ['code' => ['not_allowed' => 'Account of class 1 cannot be created manually.']];
                }
            }
        }
        return parent::canupdate($self);
    }

}

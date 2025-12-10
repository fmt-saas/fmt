<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace realestate\finance\accounting;

use finance\accounting\Account;
use realestate\property\Apportionment;

class CondoFund extends \equal\orm\Model {

    public static function getName() {
        return 'Condominium Fund';
    }

    public static function getDescription() {
        return "A Condominium Fund is used a as a link between a fund account, an utilization account and an apportionment key.";
    }

    public static function getColumns() {
        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true
            ],

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcName',
                'description'       => "Short description of the request, based on fiscal year and period.",
                'instant'           => true,
                'store'             => true
            ],

            'fund_type' => [
                'type'              => 'string',
                'selection'         => [
                    'working_fund',             // working capital
                    'reserve_fund',             // reserve fund for general expense as agreed in GA
                    'special_reserve_fund'      // special reserve fund for specific planned work
                ],
                'description'       => "Type of fund the entry relates to.",
                'help'              => "There might be as many funds as necessary, for each type of fund."
            ],

            'description' => [
                'type'              => 'string',
                'description'       => "Short description of the request, based on fiscal year and period.",
            ],

            'fund_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Account',
                'description'       => "Accounting account the fund relates to.",
                'ondelete'          => 'null',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['operation_assignment', 'in', ['working_fund', 'reserve_fund', 'special_reserve_fund']]],
                'dependents'        => ['name']
            ],

            'call_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Account',
                'description'       => "Accounting account for fund call (if through MiscOp).",
                'ondelete'          => 'null',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]],
                'dependents'        => ['expense_account_code']
            ],

            'call_account_code' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'relation'          => ['call_account_id' => 'code'],
                'description'       => "Code of the call account associated to the Reserve Fund (if through MiscOp).",
                'store'             => true,
                'instant'           => true
            ],

            'expense_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Account',
                'description'       => "Accounting account for fund utilization.",
                'ondelete'          => 'null',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]],
                'dependents'        => ['expense_account_code']
            ],

            'collector_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Account',
                'description'       => "Collector accounting account for Fund.",
                'help'              => "Accounting account set as collector for expense account and call account.",
                'ondelete'          => 'null',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null], ['is_control_account', '=', true]]
            ],

            'expense_account_code' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'relation'          => ['expense_account_id' => 'code'],
                'description'       => "Code of the expense account associated to the Reserve Fund.",
                'store'             => true,
                'instant'           => true
            ],

            'apportionment_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\property\Apportionment',
                'description'       => "Default apportionment to use when creating accounting entries on this account.",
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null], ['status', '=', 'validated']],
                'onupdate'          => 'onupdateApportionmentId'
            ],

            'total_shares' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'relation'          => ['apportionment_id' => 'total_shares'],
                'description'       => "Total shares of the apportionment.",
            ],

            'status' => [
                'type'              => 'string',
                'description'       => 'Current status of the Condominium.',
                'selection'         => [
                    'pending',
                    'validated'
                ],
                'default'           => 'pending'
            ]

        ];
    }

    public static function getWorkflow() {
        return [
            'pending' => [
                'description' => 'Fund being completed, waiting to be validated.',
                'icon'        => 'done',
                'transitions' => [
                    'validate' => [
                        'description' => 'Update the fund to `validated`.',
                        'policies'    => ['is_valid'],
                        'onbefore'    => 'onbeforeValidate',
                        'status'      => 'validated'
                    ]
                ]
            ],
            'validated' => [
                'description' => 'Validated fund, ready to be used.',
                'icon'        => 'edit',
                'transitions' => [
                    'revert' => [
                        'description' => 'Revert to `pending` to allow changes.',
                        'policies'    => [/* #todo */],
                        'status'      => 'pending'
                    ]
                ]
            ]
        ];
    }

    public static function getPolicies(): array {
        return [
            'is_valid' => [
                'description' => 'Verifies that the fund details are complete and consistent.',
                'function'    => 'policyIsValid'
            ]
        ];
    }

    protected static function policyIsValid($self) {
        $result = [];
        $self->read(['condo_id', 'fund_account_id', 'expense_account_id', 'apportionment_id', 'fund_type']);
        foreach($self as $id => $condoFund) {
            if(!$condoFund['fund_type']) {
                $result[$id] = [
                    'incomplete_fund_type' => 'Missing mandatory Fund type.'
                ];
                continue;
            }
        }

        return $result;
    }

    /**
     * Apply change on related accounting accounts.
     *
     */
    protected static function onupdateApportionmentId($self) {
        $self->read(['fund_account_id', 'call_account_id', 'expense_account_id', 'collector_account_id', 'apportionment_id']);
        foreach($self as $id => $condoFund) {
            if(!$condoFund['apportionment_id']) {
                continue;
            }
            // #todo - cascade update apportionment key on children accounts
            if($condoFund['collector_account_id']) {
                Account::id($condoFund['collector_account_id'])
                    ->update(['apportionment_id' => $condoFund['apportionment_id']]);
            }
            if($condoFund['fund_account_id']) {
                Account::id($condoFund['fund_account_id'])
                    ->update(['apportionment_id' => $condoFund['apportionment_id']]);
            }
            if($condoFund['call_account_id']) {
                Account::id($condoFund['call_account_id'])
                    ->update(['apportionment_id' => $condoFund['apportionment_id']]);
            }
            if($condoFund['expense_account_id']) {
                Account::id($condoFund['expense_account_id'])
                    ->update(['apportionment_id' => $condoFund['apportionment_id']]);
            }
        }
    }

    /**
     * When creating a fund, automatically generate corresponding accounting accounts:
     *   - 160 (reserve_fund) and 161 (special_reserve_fund)
     *   - 68160 (reserve_fund_variation) and 68161 (special_reserve_fund_variation)
     * Each fund has subaccounts for "call" (xx...0) and "use" (xx...1).
     *
     * Example: 16001 (fund), 68160010 (call), 68160011 (use).
     * Mirrors reserve fund movements between 16x and 6816x accounts and links each CondoFund to its collector.
     */
    protected static function onbeforeValidate($self) {
        // create related accounting accounts

        $self->read(['condo_id' => ['account_chart_id'], 'description', 'fund_type', 'apportionment_id']);
        foreach($self as $id => $condoFund) {

            $templateAccount = Account::search([
                    ['condo_id', '=', $condoFund['condo_id']['id']],
                    ['account_chart_id', '=', $condoFund['condo_id']['account_chart_id']],
                    ['is_control_account', '=', true],
                    ['operation_assignment', '=', $condoFund['fund_type']]
                ])
                ->read(['code', 'description'])
                ->first();

            $accounts_ids = Account::search([
                    ['condo_id', '=', $condoFund['condo_id']['id']],
                    ['account_chart_id', '=', $condoFund['condo_id']['account_chart_id']],
                    ['is_control_account', '=', false],
                    ['operation_assignment', '=', $condoFund['fund_type']]
                ])
                ->ids();

            $index = count($accounts_ids);

            $account_code = $templateAccount['code'] . str_pad($index, 2, '0', STR_PAD_LEFT);

            // create the fund account
            $fundAccount = Account::create([
                    'condo_id'              => $condoFund['condo_id']['id'],
                    'code'                  => $account_code,
                    'is_control_account'    => false,
                    'description'           => $condoFund['description'] ?? $templateAccount['description'],
                    'account_chart_id'      => $condoFund['condo_id']['account_chart_id'],
                    'operation_assignment'  => $condoFund['fund_type'],
                    'apportionment_id'      => $condoFund['apportionment_id'],
                    'tenant_share'          => 0,
                    'owner_share'           => 100
                ])
                ->first();

            // create the variation accounts

            $collectorAccount = Account::create([
                    'condo_id'              => $condoFund['condo_id']['id'],
                    'code'                  => '68' . $account_code,
                    'is_control_account'    => true,
                    'description'           => $condoFund['description'] ?? $templateAccount['description'],
                    'account_chart_id'      => $condoFund['condo_id']['account_chart_id'],
                    'apportionment_id'      => $condoFund['apportionment_id']
                ])
                ->first();

            $callAccount = Account::create([
                    'condo_id'              => $condoFund['condo_id']['id'],
                    'code'                  => '68' . $account_code . '0',
                    'is_control_account'    => false,
                    'description'           => $condoFund['description'] ?? $templateAccount['description'] . ' (appel)',
                    'account_chart_id'      => $condoFund['condo_id']['account_chart_id'],
                    'operation_assignment'  => $condoFund['fund_type'] . '_variation',
                    'apportionment_id'      => $condoFund['apportionment_id'],
                    'tenant_share'          => 0,
                    'owner_share'           => 100,
                    'parent_account_id'     => $collectorAccount['id']
                ])
                ->first();

            $expenseAccount = Account::create([
                    'condo_id'              => $condoFund['condo_id']['id'],
                    'code'                  => '68' . $account_code . '1',
                    'is_control_account'    => false,
                    'description'           => $condoFund['description'] ?? $templateAccount['description'] . ' (prélèvement)',
                    'account_chart_id'      => $condoFund['condo_id']['account_chart_id'],
                    'operation_assignment'  => $condoFund['fund_type'] . '_variation',
                    'apportionment_id'      => $condoFund['apportionment_id'],
                    'tenant_share'          => 0,
                    'owner_share'           => 100,
                    'parent_account_id'     => $collectorAccount['id']
                ])
                ->first();


            self::id($id)->update([
                    'call_account_id'           => $callAccount['id'],
                    'expense_account_id'        => $expenseAccount['id'],
                    'fund_account_id'           => $fundAccount['id'],
                    'collector_account_id'      => $collectorAccount['id']
                ]);
        }
    }

    protected static function calcName($self) {
        $result = [];
        $self->read(['fund_account_id' => ['code'], 'description']);

        foreach($self as $id => $condoFund) {
            $result[$id] = $condoFund['fund_account_id']['code'] . ' - ' . $condoFund['description'];
        }

        return $result;
    }

    public static function canupdate($self, $values) {
        $self->read(['status']);
        $allowed_fields = ['apportionment_id'];
        foreach($self as $funding) {
            if($funding['status'] === 'validated') {
                // in other cases, only allow editable fields
                if(count(array_diff(array_keys($values), $allowed_fields)) > 0) {
                    return ['status' => ['non_editable' => "No change is allowed once the fund has been validated."]];
                }
            }
        }

        return parent::canupdate($self, $values);
    }

    protected static function onchange($event, $values) {
        $result = [];
        return $result;
    }

}

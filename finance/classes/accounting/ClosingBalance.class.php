<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace finance\accounting;

class ClosingBalance extends Balance {

    public function getTable() {
        return "finance_accounting_closingbalance";
    }

    public static function getName() {
        return "Closing Balance";
    }

    public static function getDescription() {
        return "A closing balance is a snapshot at the end of a given period or fiscal year. The ClosingBalance reflects the final debits and credits for all accounts at the close of the period or year, providing a definitive financial picture.";
    }

    public static function getColumns() {
        return [

            'balance_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\accounting\ClosingBalanceLine',
                'foreign_field'     => 'balance_id',
                'description'       => "Lines of the balance."
            ]
        ];
    }

    public static function getWorkflow() {
        return [
            'pending' => [
                'description' => 'Balance being completed, waiting to be validated.',
                'icon'        => 'edit',
                'transitions' => [
                    'validate' => [
                        'description' => 'Update the Balance to `validated`.',
                        'onbefore'    => 'onbeforeValidate',
                        'status'      => 'validated'
                    ]
                ]
            ]
        ];
    }

    protected static function onbeforeValidate($self) {
        $self->do('generate_balance_lines');
    }

    public static function getActions() {
        return [
            'generate_balance_lines' => [
                'description'   => 'Generate the balance lines according to the accounting entries related to the balance fiscal year.',
                'policies'      => [],
                'function'      => 'doGenerateBalanceLines'
            ]
        ];
    }

    /**
     * Generate all balance lines according to accounting entries related to the fiscal period of the closing balance.
     * A closing balance is generated once and is directly set to 'closed'
     *
     */
    protected static function doGenerateBalanceLines($self) {
        $self->read(['condo_id', 'status', 'fiscal_year_id', 'is_period_balance', 'fiscal_period_id']);
        foreach($self as $id => $balance) {
            // ignore non-draft
            if($balance['status'] != 'pending') {
                continue;
            }
            // if some lines already exist, remove them
            ClosingBalanceLine::search(['balance_id', '=', $id])->delete(true);

            $domain = [
                ['is_closing', '=', false],
                ['is_carry_forward', '=', false],
                ['status', 'in', ['validated','reversed']]
            ];

            // fetch all accounting entries for considered period
            if($balance['is_period_balance']) {
                $domain[] = ['fiscal_period_id', '=', $balance['fiscal_period_id']];
            }
            else {
                $domain[] = ['fiscal_year_id', '=', $balance['fiscal_year_id']];
            }

            $accounting_entries_ids = AccountingEntry::search($domain)->ids();

            $map_accounts_values = [];

            $values = [
                    'condo_id'          => $balance['condo_id'],
                    'balance_id'        => $id,
                    'fiscal_year_id'    => $balance['fiscal_year_id']
                ];

            // pass-1 - read all accounting entry lines
            foreach($accounting_entries_ids ?? [] as $entry_id) {
                $accountingEntry = AccountingEntry::id($entry_id)
                    ->read(['entry_lines_ids' => ['account_id', 'debit', 'credit']])
                    ->first();

                foreach($accountingEntry['entry_lines_ids'] as $entry_line_id => $entryLine) {
                    if(!isset($map_accounts_values[$entryLine['account_id']])) {
                        $map_accounts_values[$entryLine['account_id']] = [
                                'debit'     => 0.0,
                                'credit'    => 0.0
                            ];
                    }
                    $map_accounts_values[$entryLine['account_id']]['debit']  += round($entryLine['debit'], 4);
                    $map_accounts_values[$entryLine['account_id']]['credit'] += round($entryLine['credit'], 4);
                }
            }

            // pass-2 - create resulting balance lines
            foreach($map_accounts_values as $account_id => $debit_credit) {
                if(!$account_id) {
                    continue;
                }
                $debit = $debit_credit['debit'];
                $credit = $debit_credit['credit'];

                $delta = round($debit - $credit, 4);

                $debit_balance = ($delta > 0.0) ? $delta : 0.0;
                $credit_balance = ($delta < 0.0) ? abs($delta) : 0.0;

                ClosingBalanceLine::create(array_merge([
                        'account_id'     => $account_id,
                        'debit'          => $debit,
                        'credit'         => $credit,
                        'debit_balance'  => $debit_balance,
                        'credit_balance' => $credit_balance
                    ], $values));
            }

        }
    }

    protected static function candelete($self) {
        $self->read(['fiscal_year_id' => ['status']]);
        foreach($self as $id => $closingBalance) {
            if($closingBalance['fiscal_year_id']['status'] === 'closed') {
                return ['status' => ['non_removable' => 'Closing balance from a closed fiscal year cannot be deleted.']];
            }
        }
        return [];
    }
}
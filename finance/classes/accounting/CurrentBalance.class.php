<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace finance\accounting;

class CurrentBalance extends Balance {

    public function getTable() {
        return "finance_accounting_currentbalance";
    }

    public static function getName() {
        return "Current Balance";
    }

    public static function getDescription() {
        return "An up-to-date balance for a given fiscal year. The CurrentBalance reflects the ongoing status of debits and credits on the accounts for the current period.";
    }

    public static function getColumns() {
        return [

            'balance_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\accounting\CurrentBalanceLine',
                'foreign_field'     => 'balance_id',
                'description'       => "Lines of the balance."
            ]

        ];
    }

    public static function getActions() {
        return [
            'update_account' => [
                'description'   => 'Update line matching a given account, or create it if line does not exist yet.',
                'help'          => 'This action is not thread-safe and should only be invoked by BalanceUpdateRequest to ensure consistency in concurrent operations.',
                'policies'      => [],
                'function'      => 'doUpdateAccount'
            ],
            'generate_balance_lines' => [
                'description'   => 'Generate the balance lines according to the accounting entries related to the balance fiscal year.',
                'policies'      => [],
                'function'      => 'doGenerateBalanceLines'
            ]
        ];
    }

    /**
     * Update the line of the balance that matches a given account, with debit and credit to be added.
     * This method is intended to be called upon validation of an accounting entry line.
     *
     * #memo - This method is not thread-safe and should only be invoked by BalanceUpdateRequest to ensure consistency in concurrent operations.
     *
     * @param   array   $values     Is expected to hold values for following attributes: account_id, debit, credit.
     *
     */
    protected static function doUpdateAccount($self, $values) {
        if(!isset($values['account_id'], $values['debit'], $values['credit'])) {
            return;
        }
        $self->read(['condo_id', 'fiscal_year_id']);
        foreach($self as $id => $balance) {
            /** @var \equal\orm\Model **/
            $balanceLine = CurrentBalanceLine::search([['balance_id', '=', $id], ['account_id', '=', $values['account_id']]])
                ->read(['debit', 'credit', 'debit_balance', 'credit_balance'])
                ->first();

            if($balanceLine) {
                [
                    'debit'             => $debit,
                    'credit'            => $credit,
                    'debit_balance'     => $debit_balance,
                    'credit_balance'    => $credit_balance
                ] = $balanceLine->toArray();
            }
            else {
                $balanceLine = CurrentBalanceLine::create([
                        'condo_id'          => $balance['condo_id'],
                        'balance_id'        => $id,
                        'account_id'        => $values['account_id'],
                        'fiscal_year_id'    => $balance['fiscal_year_id']
                    ])
                    ->first();

                $debit = 0.0;
                $credit = 0.0;
                $debit_balance = 0.0;
                $credit_balance = 0.0;
            }

            $debit  += round($values['debit'], 2);
            $credit += round($values['credit'], 2);

            $delta = round($debit - $credit, 2);

            $debit_balance  = ($delta > 0.0) ? abs($delta) : 0.0;
            $credit_balance = ($delta < 0.0) ? abs($delta) : 0.0;

            CurrentBalanceLine::id($balanceLine['id'])
                ->update([
                        'debit'             => $debit,
                        'credit'            => $credit,
                        'debit_balance'     => $debit_balance,
                        'credit_balance'    => $credit_balance
                    ]);
        }
    }

    /**
     * Generate all balance lines according to accounting entries related to the fiscal period of the closing balance.
     * A closing balance is generated once and is directly set to 'closed'
     *
     */
    protected static function doGenerateBalanceLines($self) {
        $self->read(['condo_id', 'status', 'fiscal_year_id', 'fiscal_period_id', 'accounting_entry_id' => ['entry_lines_ids' => ['account_id', 'debit', 'credit']]]);
        foreach($self as $id => $balance) {

            // if some lines already exist, remove them
            CurrentBalanceLine::search(['balance_id', '=', $id])->delete(true);

            // fetch all accounting entries for considered period
            $accounting_entries_ids = AccountingEntry::search([['fiscal_year_id', '=', $balance['fiscal_year_id']]])->ids();

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
                    $map_accounts_values[$entryLine['account_id']]['debit']  += round($entryLine['debit'], 2);
                    $map_accounts_values[$entryLine['account_id']]['credit'] += round($entryLine['credit'], 2);
                }
            }

            // pass-2 - create resulting balance lines
            foreach($map_accounts_values as $account_id => $debit_credit) {
                if(!$account_id) {
                    continue;
                }

                $debit  = round($debit_credit['debit'], 2);
                $credit = round($debit_credit['credit'], 2);

                $delta = round($debit - $credit, 2);

                $debit_balance  = ($delta > 0.0) ? $delta : 0.0;
                $credit_balance = ($delta < 0.0) ? abs($delta) : 0.0;

                CurrentBalanceLine::create(array_merge([
                        'account_id'     => $account_id,
                        'debit'          => $debit,
                        'credit'         => $credit,
                        'debit_balance'  => $debit_balance,
                        'credit_balance' => $credit_balance
                    ], $values));
            }

        }
    }

    public function getUnique() {
        return [
            ['condo_id', 'fiscal_year_id']
        ];
    }

}
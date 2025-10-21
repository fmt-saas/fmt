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

            $debit  += round($values['debit'], 4);
            $credit += round($values['credit'], 4);

            $delta = round($debit - $credit, 4);

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


}
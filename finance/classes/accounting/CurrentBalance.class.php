<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
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

    /**
     * Update the line of the balance that matches a given account, with debit and credit to be added.
     * This method is intended to be called upon validation of an accounting entry line).
     *
     * @param   array   $values     Is expected to hold values for following attributes: account_id, debit, credit.
     *
     */
    public static function doUpdateAccount($self, $values) {
        if(!isset($values['account_id'], $values['debit'], $values['credit'])) {
            return;
        }
        $self->read(['condo_id', 'fiscal_year_id']);
        foreach($self as $id => $balance) {
            $balanceLine = CurrentBalanceLine::search([['balance_id', '=', $id], ['account_id', '=', $values['account_id']]])
                ->read(['debit', 'credit'])
                ->first();

            if($balanceLine) {
                ['debit' => $debit, 'credit' => $credit] = $balanceLine->toArray();
            }
            else {
                $balanceLine = CurrentBalanceLine::create([
                        'balance_id'        => $id,
                        'account_id'        => $values['account_id'],
                        'condo_id'          => $balance['condo_id'],
                        'fiscal_year_id'    => $balance['fiscal_year_id']
                    ])
                    ->first();

                $debit = 0.0;
                $credit = 0.0;
            }

            $debit += $values['debit'];
            $credit += $values['credit'];

            CurrentBalanceLine::id($balanceLine['id'])
                ->update([
                        'debit'  => round($debit, 4),
                        'credit' => round($credit, 4)
                    ])
                ->read(['debit_balance', 'credit_balance']);
        }
    }


}
<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
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

            'date' => [
                'type'              => 'string',
                'usage'             => 'date/plain',
                'description'       => 'Date at which the balance was generated.',
                'help'              => 'If closing balance is validated, the date should match the `date_to` of the related fiscal period.'
            ],

            'balance_type' => [
                'type'              => 'string',
                'selection'         => [
                    'fiscal_year',
                    'fiscal_period'
                ],
                'description'       => 'Type of balance.',
            ],

            'balance_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\accounting\ClosingBalanceLine',
                'foreign_field'     => 'balance_id',
                'description'       => "Lines of the balance."
            ]
        ];
    }

    public static function getActions() {
        return [
            'init' => [
                'description'   => 'Generate the balance lines according to the accounting entries related to the balance fiscal year.',
                'policies'      => [],
                'function'      => 'doInit'
            ]
        ];
    }

    /**
     * Generate all balance lines according to accounting entries related to the fiscal period of the closing balance.
     * A closing balance is generated once and is directly set to 'closed'
     */
    public function doInit($self) {
        $self->read(['condo_id', 'status', 'fiscal_year_id', 'is_period_balance', 'fiscal_period_id', 'accounting_entry_id' => ['entry_lines_ids' => ['account_id', 'debit', 'credit']]]);
        foreach($self as $id => $balance) {
            // ignore non-draft
            if($balance['status'] != 'pending') {
                continue;
            }
            // if some lines already exist, remove them
            ClosingBalanceLine::search(['balance_id', '=', $id])->delete(true);

            // fetch all accounting entries for considered period
            if($balance['is_period_balance']) {
                $accounting_entries_ids = AccountingEntry::search([['fiscal_period_id', '=', $balance['fiscal_period_id']]])->ids();
            }
            else {
                $accounting_entries_ids = AccountingEntry::search([['fiscal_year_id', '=', $balance['fiscal_year_id']]])->ids();
            }

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
                ClosingBalanceLine::create(array_merge([
                        'account_id'    => $account_id,
                        'debit'         => $debit_credit['debit'],
                        'credit'        => $debit_credit['credit']
                    ], $values));
            }

            self::id($id)->update(['status' => 'closed']);
        }
    }

}
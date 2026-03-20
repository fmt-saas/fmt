<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace finance\accounting;

class OpeningBalance extends Balance {

    public function getTable() {
        return "finance_accounting_openingbalance";
    }

    public static function getName() {
        return "Opening Balance";
    }

    public static function getDescription() {
        return "An opening balance is a snapshot of account balances at the beginning of a fiscal year. The OpeningBalance reflects the debit and credit balances carried forward from the previous closing balance, establishing the initial financial position of all accounts for the new fiscal year.";
    }

    public static function getColumns() {
        return [

            'balance_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\accounting\OpeningBalanceLine',
                'foreign_field'     => 'balance_id',
                'description'       => "Lines of the balance.",
                'order'             => 'name',
                'sort'              => 'asc'
            ],

            'misc_operation_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\MiscOperation',
                'description'       => 'Miscellaneous operation the opening balance originates from, if any.',
                'help'              => 'This is an optional link to an opening journal Misc Operation holding details about accounting accounts movements.',
                'readonly'          => true
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
     * Generate all opening balance lines by copying the closing balance of the previous fiscal year, if it exists.
     *
     */
    protected static function doGenerateBalanceLines($self) {

        $self->read([
            'condo_id',
            'status',
            'fiscal_year_id' => ['id','date_from']
        ]);

        foreach($self as $id => $balance) {

            // ignore non-pending balances
            if($balance['status'] !== 'pending') {
                continue;
            }

            // find previous fiscal year for the same condo
            $prevFiscalYear = FiscalYear::search([
                        ['condo_id', '=', $balance['condo_id']],
                        ['date_to', '<', $balance['fiscal_year_id']['date_from']]
                    ],
                    ['sort' => ['date_to' => 'desc'], 'limit' => 1]
                )
                ->first();

            if(!$prevFiscalYear) {
                // no previous fiscal year → empty opening balance
                continue;
            }

            // find the closing balance for that fiscal year
            $closingBalance = ClosingBalance::search([
                        ['condo_id', '=', $balance['condo_id']],
                        ['fiscal_year_id', '=', $prevFiscalYear['id']],
                    ],
                    ['sort' => ['date_to' => 'desc'], 'limit' => 1]
                )
                ->first();

            if(!$closingBalance) {
                // no closing balance found → nothing to copy
                continue;
            }

            // remove existing lines if any
            OpeningBalanceLine::search(['balance_id', '=', $id])->delete(true);

            // read closing balance lines
            $closingBalanceLines = ClosingBalanceLine::search(['balance_id', '=', $closingBalance['id']])
                ->read([
                    'account_id',
                    'debit',
                    'credit',
                    'debit_balance',
                    'credit_balance'
                ]);

            $values = [
                'condo_id'       => $balance['condo_id'],
                'balance_id'     => $id,
                'fiscal_year_id' => $balance['fiscal_year_id']['id']
            ];

            foreach($closingBalanceLines as $line) {

                OpeningBalanceLine::create(array_merge([
                    'account_id'     => $line['account_id'],
                    'debit'          => $line['debit'],
                    'credit'         => $line['credit'],
                    'debit_balance'  => $line['debit_balance'],
                    'credit_balance' => $line['credit_balance']
                ], $values));

            }
        }
    }

    protected static function candelete($self) {
        $self->read(['fiscal_year_id' => ['status']]);
        foreach($self as $id => $openingBalance) {
            if($openingBalance['fiscal_year_id']['status'] === 'closed') {
                return ['status' => ['non_removable' => 'Opening balance from a closed fiscal year cannot be deleted.']];
            }
        }
        return [];
    }
}
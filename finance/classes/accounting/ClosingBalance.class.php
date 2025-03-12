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

            'fiscal_period_id' => [
                'type'              => 'many2one',
                'description'       => "The fiscal period the balance refers to.",
                'foreign_object'    => 'finance\accounting\FiscalPeriod',
                'readonly'          => true
            ],

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

}
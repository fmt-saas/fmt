<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace finance\accounting;
use equal\orm\Model;

class Balance extends Model {

    public static function getName() {
        return "Accounting Balance";
    }

    public static function getDescription() {
        return "Accounting Balances provide an overview of the accounts at a specific date.
        This class in an abstraction for the CurrentBalance and ClosingBalance entities.";
    }

    public static function getColumns() {
        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the accounting entry refers to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'readonly'          => true
            ],

            'fiscal_year_id' => [
                'type'              => 'many2one',
                'description'       => "The fiscal year the balance refers to.",
                'foreign_object'    => 'finance\accounting\FiscalYear',
                'readonly'          => true
            ],

            'is_period_balance' => [
                'type'              => 'boolean',
                'description'       => "Does the balance relate to a specific fiscal period.",
                'default'           => false
            ],

            'fiscal_period_id' => [
                'type'              => 'many2one',
                'description'       => "The fiscal period the balance refers to.",
                'foreign_object'    => 'finance\accounting\FiscalPeriod',
                'readonly'          => true,
                'visible'           => ['is_period_balance', '=', true]
            ],

            'balance_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\accounting\BalanceLine',
                'foreign_field'     => 'balance_id',
                'description'       => "Lines of the balance."
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'pending',
                    'validated',
                    'closed'
                ],
                'default'           => 'pending',
                'description'       => 'Status of the balance.',
            ]

        ];
    }

}
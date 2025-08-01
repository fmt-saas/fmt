<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
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

    public static function getWorkflow() {
        return [
            'pending' => [
                'description' => 'Balance being completed, waiting to be validated.',
                'icon'        => 'edit',
                'transitions' => [
                    'validate' => [
                        'description' => 'Update the Balance to `validated`.',
                        'status'      => 'validated'
                    ]
                ]
            ],
            'validated' => [
                'description' => 'Validated Ownership, ready to be used.',
                'icon'        => 'done',
                'transitions' => [
                    'revert' => [
                        'description' => 'Revert to `pending` to allow changes.',
                        'status'      => 'pending'
                    ]
                ]
            ]
        ];
    }
}
<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\utility\energy;

use finance\accounting\Account;
use finance\accounting\FiscalYear;
use finance\accounting\Journal;
use finance\accounting\MiscOperation;
use finance\accounting\MiscOperationLine;
use realestate\property\PropertyLotOwnership;

class ConsumptionStatement extends \equal\orm\Model {

    public static function getName() {
        return 'Consumption Statement';
    }

    public static function getColumns() {
        return [

            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the payment relates to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'readonly'          => true
            ],

            'fiscal_year_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\FiscalYear',
                'description'       => "Fiscal year the consumption statement relates to.",
                'required'          => true,
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]]
            ],

            'fiscal_period_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\FiscalPeriod',
                'description'       => "Period of the fiscal year the consumption statement relates to.",
                'help'              => "Posting date is automatically assigned on the last day of the period.",
                'domain'            => [['condo_id', '<>', null], ['condo_id', '=', 'object.condo_id'], ['fiscal_year_id', '=', 'object.fiscal_year_id']]
            ],

            'date_from' => [
                'type'              => 'date',
                'description'       => "Date from which the owners owned at least one property lot.",
                'required'          => true
            ],

            'date_to' => [
                'type'              => 'date',
                'description'       => "Date at which the last owned lot was sold by the owners.",
            ],


            'misc_operation_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\MiscOperation',
                'description'       => "Accounting entry the line relates to."
            ],

            'posting_date' => [
                'type'              => 'date',
                'description'       => 'Date the operation should be accounted ins the accounting system.',
                'default'           => function () { return time(); }
            ],

            'document_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\Document',
                'description'       => 'Original document of the consumption statement.',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]],
            ],

            'consumption_meter_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\utility\energy\ConsumptionMeter',
                'description'       => 'The meter ID relates to the consumption meter reading in the booking.',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null], ['meter_scope', '=', 'master']],
                'required'          => true
            ],

            // (décompte eau et chaufffage)
            'accounting_account_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'store'             => false,
                'relation'          => ['consumption_meter_id' => 'accounting_account_id'],
                'foreign_object'    => 'finance\accounting\Account',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null], ['is_control_account', '=', false]],
                'readonly'          => true
            ],

            'apportionment_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'store'             => false,
                'relation'          => ['consumption_meter_id' => 'apportionment_id'],
                'description'       => "The key that the apportionment refers to.",
                'foreign_object'    => 'realestate\property\Apportionment',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null], ['is_statutory', '=', false], ['is_active', '=', true], ['status', '=', 'validated']],
                'help'              => "This value is used for splitting the amount amongst owners. One set, it can no longer be changed.",
                'readonly'          => true
            ],

            /*
                supplier (techem, ista, aquatel)

                option pour dire si les provisions ont été comptabilisées ou non
            */

            'consumption_statement_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\utility\energy\ConsumptionStatementLine',
                'foreign_field'     => 'consumption_statement_id',
                'description'       => "Period of the fiscal year the consumption statement relates to."
            ],


            'statement_total' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:2',
                'function'          => 'calcStatementTotal',
                'store'             => false,
                'description'       => 'Total amount assigned from common expenses.',
                'help'              => 'This is used in order to make sure all the lines have been correctly entered.'
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'proforma',
                    'posted',
                ],
                'description'       => 'Status of the reading.',
                'default'           => 'proforma',
                // cannot be set manually
                'readonly'          => true
            ],

        ];
    }

    public static function getWorkflow() {
        return [
            'proforma' => [
                'description' => 'Draft consumption statement, pending and still waiting to be completed.',
                'icon' => 'edit',
                'transitions' => [
                    'post' => [
                        'description' => 'Update the consumption statement. Generate a Misc Operation, generate accounting entries and validate accounting entries.',
                        'policies'    => [
                            'can_post'
                        ],
                        'onbefore'  => 'onbeforePost',
                        'status'    => 'posted',
                    ]
                ],
            ]
        ];
    }

    public static function getActions() {
        return array_merge(parent::getActions(), [
            'generate_statement_lines' => [
                'description'   => 'Generate the request lines according to the property lots of the condominium and their respective shares.',
                'policies'      => ['can_generate_statement_lines'],
                'function'      => 'doGenerateStatementLines'
            ],
            'generate_misc_operation' => [
                'description'   => 'Generate misc operation for the statement, and post it to the accounting system.',
                'policies'      => [],
                'function'      => 'doGenerateMiscOperation'
            ]
        ]);
    }

    public static function getPolicies(): array {
        return array_merge(parent::getPolicies(), [
            'can_post' => [
                'description' => 'Verifies that the allocation of a fund request can still be updated.',
                'function'    => 'policyCanPost'
            ],
            'can_generate_statement_lines' => [
                'description' => 'Verifies that the allocation of a fund request can still be updated.',
                'function'    => 'policyCanGenerateStatementLine'
            ]

        ]);
    }

    protected static function policyCanGenerateStatementLine($self) {
        $result = [];
        $self->read(['status', 'accounting_account_id', 'statement_total']);
        foreach($self as $id => $expenseStatement) {
            if($expenseStatement['status'] !== 'proforma') {
                $result[$id] = [
                    'invalid_status' => 'Lines can only be generated while statement is in proforma.'
                ];
                continue;
            }
        }
        return $result;
    }

    protected static function policyCanPost($self): array {
        $result = [];
        $self->read(['status', 'accounting_account_id', 'statement_total']);
        foreach($self as $id => $expenseStatement) {
            if($expenseStatement['status'] !== 'proforma') {
                $result[$id] = [
                    'invalid_status' => 'Already cancelled.'
                ];
                continue;
            }
            if(!$expenseStatement['accounting_account_id']) {
                $result[$id] = [
                    'invalid_account' => 'An accounting account must be specified.'
                ];
                continue;
            }

            if($expenseStatement['statement_total'] <= 0.01) {
                $result[$id] = [
                    'invalid_total' => 'Statement total must be greater than 0.'
                ];
                continue;
            }
        }
        return $result;
    }

    protected static function calcStatementTotal($self) {
        $result = [];
        $self->read(['consumption_statement_lines_ids' => ['price']]);
        foreach($self as $id => $consumptionStatement) {
            $result[$id] = 0.0;
            foreach($consumptionStatement['consumption_statement_lines_ids'] as $consumptionStatementLine) {
                $result[$id] += $consumptionStatementLine['price'];
            }
        }
        return $result;
    }

    protected static function onbeforePost($self) {
        $self->do('generate_misc_operation');
    }

    protected static function doGenerateStatementLines($self) {
        $self->read(['condo_id', 'consumption_meter_id', 'date_from', 'date_to']);
        foreach($self as $id => $consumptionStatement) {
            // remove any previously created line
            ConsumptionStatementLine::search([['consumption_statement_id', '=', $id]])->delete(true);

            $consumptionMeter = ConsumptionMeter::id($consumptionStatement['consumption_meter_id'])
                ->read(['children_meters_ids' => ['property_lot_id']])
                ->first();

            foreach($consumptionMeter['children_meters_ids'] as $child_consumption_meter_id => $childConsumptionMeter) {
                $values = [
                    'condo_id'                  => $consumptionStatement['condo_id'],
                    'consumption_statement_id'  => $id,
                    'consumption_meter_id'      => $child_consumption_meter_id,
                    'property_lot_id'           => $childConsumptionMeter['property_lot_id']
                ];

                // for each lot, identify the ownerships for the start and end dates
                $propertyLotOwnerships = PropertyLotOwnership::search([
                        ['condo_id', '=', $consumptionStatement['condo_id']],
                        ['property_lot_id', '=', $childConsumptionMeter['property_lot_id']],
                        ['date_from', '<=', $consumptionStatement['date_to']]
                    ])
                    ->read(['date_from', 'date_to', 'ownership_id']);

                foreach($propertyLotOwnerships as $propertyLotOwnership) {
                    $ownership_id = $propertyLotOwnership['ownership_id'];
                    // intersection between the statement period and the propertyLotOwnership
                    $date_from = max($propertyLotOwnership['date_from'], $consumptionStatement['date_from']);
                    $date_to   = ($propertyLotOwnership['date_to']) ? min($propertyLotOwnership['date_to'], $consumptionStatement['date_to']) : $consumptionStatement['date_to'];
                    ConsumptionStatementLine::create(array_merge($values, [
                            'ownership_id' => $ownership_id,
                            'date_from' => $date_from,
                            'date_to' => $date_to
                        ]));
                }
            }
        }
    }

    /**
     * Generates the Miscellaneous Operation, and post it.
     *
     *
     *
     */
    protected static function doGenerateMiscOperation($self) {
        $self->read([
                'condo_id',
                'fiscal_year_id',
                'posting_date',
                'accounting_account_id',
                'apportionment_id',
                'statement_total',
                'consumption_statement_lines_ids' => ['price', 'property_lot_id', 'ownership_id']
            ]);

        foreach($self as $id => $consumptionStatement) {
            // on doit générer une MiscOperation avec les infos encodées
            // -> poster la MiscOperation : sera prise en compte dans le ExpenseStatement

            // une ligne avec le montant sur le compte renseigne
            // une ligne pour chaque Ownership (le montant peut être négatif)

            $saleJournal = Journal::search([
                    ['condo_id', '=', $consumptionStatement['condo_id']],
                    ['journal_type', '=', 'MISC']
                ])
                ->first();

            $description = 'Décompte de consommations';

            $miscOperation = MiscOperation::create([
                    'condo_id'          => $consumptionStatement['condo_id'],
                    'description'       => $description,
                    'posting_date'      => $consumptionStatement['posting_date'],
                    'journal_id'        => $saleJournal['id']
                ])
                ->first();

            // create the credit line on the consumption expense
            MiscOperationLine::create([
                    'condo_id'                  => $consumptionStatement['condo_id'],
                    'misc_operation_id'         => $miscOperation['id'],
                    'apportionment_id'          => $consumptionStatement['apportionment_id'],
                    'account_id'                => $consumptionStatement['accounting_account_id'],
                    'description'               => $description,
                    'debit'                     => 0.0,
                    'credit'                    => $consumptionStatement['statement_total']
                ]);

            foreach($consumptionStatement['consumption_statement_lines_ids'] as $consumptionStatementLine) {
                // create the debit line on the private expense (+ownership)
                $ownership_id = $consumptionStatementLine['ownership_id'];

                // set expense_account_id to 643xxx
                $privateExpenseAccount = Account::search([
                        ['condo_id', '=', $consumptionStatement['condo_id']],
                        ['operation_assignment', '=', 'private_expenses']
                    ])
                    ->first();

                if(!$privateExpenseAccount) {
                    throw new \Exception('missing_ownership_accounting_account', EQ_ERROR_INVALID_PARAM);
                }

                MiscOperationLine::create([
                        'condo_id'                  => $consumptionStatement['condo_id'],
                        'misc_operation_id'         => $miscOperation['id'],
                        'description'               => $description,
                        'account_id'                => $privateExpenseAccount['id'],
                        'is_private_expense'        => true,
                        'ownership_id'              => $ownership_id,
                        'property_lot_id'           => $consumptionStatementLine['property_lot_id'],
                        'debit'                     => $consumptionStatementLine['price'],
                        'credit'                    => 0.0,
                        'owner_share'               => 0,
                        'tenant_share'              => 100
                    ]);

            }

            // instant posting of the Misc Operation - will create Accounting Entry Lines & Funding
            MiscOperation::id($miscOperation['id'])
                ->transition('publish')
                ->transition('post');

            self::id($id)->update(['misc_operation_id' => $miscOperation['id']]);
        }
    }

    public static function onchange($event, $values): array {
        $result = [];
        if(isset($event['condo_id'])) {
            $fiscalYear = FiscalYear::search([
                    ['condo_id', '=', $event['condo_id']],
                    ['status', '=', 'open']
                ])
                ->read(['id', 'name'])
                ->first();
            if($fiscalYear) {
                $result['fiscal_year_id'] = [
                        'id'    => $fiscalYear['id'],
                        'name'  => $fiscalYear['name'],
                    ];
                $event['fiscal_year_id'] = $fiscalYear['id'];
            }
            $account = Account::search([
                    ['condo_id', '=', $event['condo_id']],
                    ['operation_assignment', '=', 'consumption_statement'],
                ])
                ->read(['id', 'name'])
                ->first();
            if($account) {
                $result['accounting_account_id'] = [
                        'id'        => $account['id'],
                        'name'      => $account['name']
                    ];
            }
        }
        if(isset($event['fiscal_year_id'])) {
            $fiscalYear = FiscalYear::id($event['fiscal_year_id'])->read(['date_from', 'date_to'])->first();
            if($fiscalYear) {
                $result['date_from'] = $fiscalYear['date_from'];
                $result['date_to'] = $fiscalYear['date_to'];
            }
        }
        return $result;
    }

}

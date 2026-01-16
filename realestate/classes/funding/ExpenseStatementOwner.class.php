<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace realestate\funding;

use finance\accounting\Account;
use realestate\property\Apportionment;
use realestate\property\PropertyLot;

class ExpenseStatementOwner extends \equal\orm\Model {

    public static function getName() {
        return 'Expense Statement Owner';
    }

    public static function getColumns() {
        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true
            ],

            'expense_statement_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\funding\ExpenseStatement',
                'description'       => "Expense Statement the entry relates to.",
                'ondelete'          => 'cascade',
                'required'          => true
            ],

            'fiscal_period_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\FiscalPeriod',
                'description'       => "Period of the fiscal year the invoice statement relates to.",
                'help'              => "Posting date is automatically assigned on the last day of the period.",
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'statement_owner_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\funding\ExpenseStatementOwnerLine',
                'foreign_field'     => 'statement_owner_id',
                'description'       => 'Detailed lines of the Owner Statement.',
                'ondetach'          => 'delete',
                'dependencies'      => ['expense_amount']
            ],

            'ownership_id' => [
                'type'              => 'many2one',
                'description'       => "The ownership that the owner refers to.",
                'foreign_object'    => 'realestate\ownership\Ownership',
                'required'          => true,
                'readonly'          => true
            ],

            'property_lots_count' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => "The total amount of property lots to the ownership.",
                'function'          => 'calcPropertyLotsCount',
                'store'             => true
            ],

            'expense_amount' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:2',
                'description'       => "The total of expense allocated to the ownership.",
                'function'          => 'calcExpenseAmount',
                'store'             => true
            ],

            'date_from' => [
                'type'              => 'date',
                'usage'             => 'date/plain',
                'description'       => "Date from which the owner is considered for the statement."
            ],

            'date_to' => [
                'type'              => 'date',
                'usage'             => 'date/plain',
                'description'       => "Date until when the owner is considered for the statement."
            ],

            'nb_days' => [
                'type'              => 'integer',
                'description'       => "The number of days covered by the ownership on the statement period."
            ],

            'schema' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'usage'             => 'application/json',
                'function'          => 'calcSchema',
                'store'             => false,
                'help'              => 'This field is not intended to be stored and can safely be computed at any time since its relies on immutable data.'
            ]
        ];
    }

    public static function calcPropertyLotsCount($self) {
        $result = [];
        $self->read(['statement_owner_lines_ids' => ['property_lot_id']]);
        foreach($self as $id => $statementOwner) {
            $map_property_lots = [];
            foreach($statementOwner['statement_owner_lines_ids'] as $line_id => $statementOwnerLine) {
                $map_property_lots[$statementOwnerLine['property_lot_id']] = true;
            }
            $result[$id] = count($map_property_lots);
        }
        return $result;
    }

    public static function calcExpenseAmount($self) {
        $result = [];
        $self->read(['statement_owner_lines_ids' => ['price']]);
        foreach($self as $id => $statementOwner) {
            $sum = 0.0;
            foreach($statementOwner['statement_owner_lines_ids'] as $line_id => $statementOwnerLine) {
                $sum += $statementOwnerLine['price'];
            }
            $result[$id] = $sum;
        }
        return $result;
    }


    /**
     * Compute a structured JSON schema of the expense statement for given ownership.
     * This is used for easier rendering.
     */
    protected static function calcSchema($self) {
        $result = [];

        $self->read([
                'id',
                'date_from',
                'date_to',
                'nb_days',
                'fiscal_period_id' => ['date_from', 'date_to'],
                'ownership_id' => ['id', 'name'],
                'statement_owner_lines_ids' => [
                    'apportionment_id',
                    'account_id',
                    'property_lot_id',
                    'total',
                    'total_amount',
                    'owner_amount',
                    'tenant_amount',
                    'vat_amount',
                    'date',
                    'expense_type',
                    'shares',
                    'description'
                ]
            ]);

        foreach($self as $id => $statementOwner) {

            // load all dependencies at once
            $invoice_lines = $statementOwner['statement_owner_lines_ids']->toArray();

            $apportionments_ids = array_map(fn($a) => $a['apportionment_id'], $invoice_lines);
            $accounts_ids       = array_map(fn($a) => $a['account_id'], $invoice_lines);
            $property_lots_ids  = array_map(fn($a) => $a['property_lot_id'], $invoice_lines);

            $accounts = Account::ids($accounts_ids)->read(['name', 'code'])->get();
            $property_lots = PropertyLot::ids($property_lots_ids)->read(['name', 'code', 'property_lot_ref', 'property_lot_nature'])->get();
            $apportionments = Apportionment::ids($apportionments_ids)->read(['name', 'total_shares'])->get();

            $account_code_map = [];
            foreach($accounts as $account_id => $account) {
                $account_code_map[$account_id] = $account['code'];
            }

            $owner = [
                    'id'                    => $statementOwner['ownership_id']['id'],
                    'name'                  => $statementOwner['ownership_id']['name'],
                    'nb_days'               => $statementOwner['nb_days'],
                    'date_from'             => $statementOwner['date_from'],
                    'date_to'               => $statementOwner['date_to'],
                    'has_reserve_fund'      => false,
                    'has_private_expense'   => false,
                    'has_common_expense'    => false,
                    'has_provisions'        => false,
                    'property_lots'         => []
                ];

            foreach($invoice_lines as $line_id => $line) {

                $property_lot_id   = $line['property_lot_id'];
                $expense_type      = $line['expense_type'];
                $account_id        = $line['account_id'];
                $apportionment_id  = $line['apportionment_id'] ?? 0;

                if(!isset($owner['property_lots'][$property_lot_id])) {
                    $owner['property_lots'][$property_lot_id] = [
                        'id'                    => $property_lot_id,
                        'name'                  => $property_lots[$property_lot_id]['name'],
                        'code'                  => $property_lots[$property_lot_id]['code'],
                        'ref'                   => $property_lots[$property_lot_id]['property_lot_ref'],
                        'nature'                => $property_lots[$property_lot_id]['property_lot_nature'],
                        'has_reserve_fund'      => false,
                        'has_private_expense'   => false,
                        'has_common_expense'    => false,
                        'has_provisions'        => false,
                        'expenses'              => []
                    ];
                }

                $owner['has_' . $expense_type] = true;
                $owner['property_lots'][$property_lot_id]['has_' . $expense_type] = true;

                if (!isset($owner['property_lots'][$property_lot_id]['expenses'][$expense_type])) {
                    $owner['property_lots'][$property_lot_id]['expenses'][$expense_type] = [
                        'name'              => $expense_type,
                        'apportionments'    => []
                    ];
                }

                $expense_ref = &$owner['property_lots'][$property_lot_id]['expenses'][$expense_type];

                if (!isset($expense_ref['apportionments'][$apportionment_id])) {
                    $expense_ref['apportionments'][$apportionment_id] = [
                        'id'            => $apportionment_id,
                        'name'          => $apportionments[$apportionment_id]['name'] ?? 'private',
                        'total_shares'  => $apportionments[$apportionment_id]['total_shares'],
                        'shares'        => $line['shares'],
                        'accounts'      => []
                    ];
                }

                $expense_ref['apportionments'][$apportionment_id]['accounts'][$account_id][] = [
                    'id'            => $account_id,
                    'name'          => $accounts[$account_id]['name'],
                    'code'          => $accounts[$account_id]['code'],
                    'total_amount'  => $line['total_amount'],
                    'owner'         => $line['owner_amount'],
                    'tenant'        => $line['tenant_amount'],
                    'vat'           => $line['vat_amount'],
                    'description'   => $line['description'],
                    'date'          => $line['date']
                ];
            }

            foreach($owner['property_lots'] as &$lot) {
                foreach($lot['expenses'] as &$expense) {
                    foreach($expense['apportionments'] as &$apportionment) {

                        uksort(
                            $apportionment['accounts'],
                            static function ($a, $b) use ($account_code_map) {
                                return strcmp(
                                    $account_code_map[$a] ?? '',
                                    $account_code_map[$b] ?? ''
                                );
                            }
                        );

                        $sorted_accounts = [];
                        foreach($apportionment['accounts'] as $lines) {
                            foreach($lines as $line) {
                                $sorted_accounts[] = $line;
                            }
                        }

                        $apportionment['accounts'] = $sorted_accounts;
                    }
                }
            }

            $owner['property_lots'] = array_values($owner['property_lots']);

            foreach($owner['property_lots'] as &$lot) {
                $lot['expenses'] = array_values($lot['expenses']);

                foreach($lot['expenses'] as &$expense) {
                    $expense['apportionments'] = array_values($expense['apportionments']);
                }
            }

            $result[$id] = $owner;
        }

        return $result;
    }


}

<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace realestate\funding;

use realestate\ownership\Ownership;
use finance\accounting\FiscalYear;
use realestate\property\Condominium;

class ExpenseStatementOwnerLine extends \sale\accounting\invoice\InvoiceLine {

    public static function getName() {
        return 'Expense Statement Owner Line';
    }

    public static function getDescription() {
        return "Expense Statement Owner Lines are used both as lines in the expense statement (considered as sales invoices) and as a source of information for generating the settlement documents for the owners.";
    }

    public static function getColumns() {
        return [
            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcName',
                'description'       => 'Override invoiceLine total (vat incl.)',
                'help'              => 'This is the amount to be used for the related accounting entry. There is not VAT handling for Condominiums.',
                'store'             => true
            ],

            /*
            inherited from InvoiceLine
            - 'condo_id'
            - 'description'

            */

            'product_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\catalog\Product',
                'description'       => 'No products are associated with statements.',
            ],


            'invoice_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\funding\ExpenseStatement',
                'description'       => "Expense Statement (sale invoice) the line relates to.",
                'ondelete'          => 'cascade',
                'required'          => true
            ],

            'statement_owner_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\funding\ExpenseStatementOwner',
                'description'       => "Specif Owner Statement the line relates to.",
                'ondelete'          => 'cascade',
                'required'          => true
            ],

            'ownership_id' => [
                'type'              => 'many2one',
                'description'       => "The ownership that the owner refers to.",
                'foreign_object'    => 'realestate\ownership\Ownership',
                'required'          => true,
                'readonly'          => true
            ],

            'apportionment_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\property\Apportionment',
                'description'       => "Default apportionment to use when creating accounting entries on this account.",
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Account',
                'description'       => "Accounting account the entry relates to.",
                'required'          => true,
                'ondelete'          => 'null',
                'dependents'        => ['journal_id'],
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['is_control_account', '=', false]],
                'dependents'        => ['account_code']
            ],

            'property_lot_id' => [
                'type'              => 'many2one',
                'description'       => "Property Lot to apply the charge to.",
                'foreign_object'    => 'realestate\property\PropertyLot',
                'visible'           => ['is_private_expense', '=', true],
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'shares' => [
                'type'              => 'integer',
                'description'       => 'Owner shares considered fot the line (according to apportionment).',
            ],

            'price' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:2',
                'function'          => 'calcPrice',
                'store'             => true,
                'description'       => 'Override invoiceLine total price (vat incl.)',
                'help'              => 'This is the amount to be used for the related accounting entry. There is not VAT handling for Condominiums.'
            ],

            'total_amount' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Total amount allocated between owners for the expense.',
                'help'              => 'This field is not applicable to expenses funded with a reserve fund.'
            ],

            'owner_amount' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'required'          => true,
                'dependents'        => ['price']
            ],

            'tenant_amount'=> [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'required'          => true,
                'dependents'        => ['price']
            ],

            'vat_amount'=> [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'required'          => true,
                'description'       => 'Amount of VAT included in the total (owner_amount + tenant_amount).',
                'help'              => 'This amount can be deduced either by Owner or Tenant for the related expense.'
            ],

            'date'=> [
                'type'              => 'date',
                'description'       => 'Date of the expense the line relates to.',
                'help'              => 'This field is necessary for identifying lines relating to a private expense.'
            ],

            'expense_type' => [
                'type'              => 'string',
                'selection'         => [
                    'private_expense',
                    'common_expense',
                    'reserve_fund',
                    'consumptions'
                ],
                'description'       => 'Kind of expense the line relates to.',
                'help'              => 'This is required for expense statement.'
            ]

        ];
    }

    public static function calcName($self): array {
        $result = [];
        $self->read(['date', 'description', 'account_id' => ['description']]);
        foreach($self as $id => $statementOwnerLine) {
            $parts = [];
            if($statementOwnerLine['date']) {
                $parts[] = date('d/m/Y', $statementOwnerLine['date']);
            }
            if($statementOwnerLine['description'] && strlen($statementOwnerLine['description'])) {
                $parts[] = $statementOwnerLine['description'];
            }
            else {
                $parts[] = $statementOwnerLine['account_id']['description'];
            }
            $result[$id] = implode(' - ', $parts);
        }
        return $result;
    }

    public static function calcPrice($self) {
        $result = [];
        $self->read(['owner_amount', 'tenant_amount']);
        foreach($self as $id => $statementOwnerLine) {
            $result[$id] = $statementOwnerLine['owner_amount'] + $statementOwnerLine['tenant_amount'];
        }
        return $result;
    }
}

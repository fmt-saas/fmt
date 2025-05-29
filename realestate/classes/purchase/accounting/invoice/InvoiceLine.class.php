<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace realestate\purchase\accounting\invoice;

use finance\accounting\Account;
use realestate\property\PropertyLot;
use realestate\property\PropertyLotOwnership;

class InvoiceLine extends \purchase\accounting\invoice\InvoiceLine {

    public function getTable() {
        return 'purchase_accounting_invoice_invoiceline';
    }

    public static function getColumns() {
        return [

            /*
                from finance\accounting\invoice\InvoiceLine
                'condo_id'
            */

            'invoice_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\purchase\accounting\invoice\Invoice',
                'description'       => 'Invoice the line is related to.',
                'required'          => true,
                'ondelete'          => 'cascade'
            ],

            'is_private_expense' => [
                'type'              => 'boolean',
                'description'       => 'Enable to apply charge to a single owner.',
                'default'           => false,
                'onupdate'          => 'onupdateIsPrivateExpense'
            ],

            'expense_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Account',
                'description'       => "Accounting account the entry relates to.",
                'required'          => true,
                'ondelete'          => 'null',
                // #todo - limit to supplier assigned accounts
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['is_control_account', '=', false], ['account_class', '=', '06']]
            ],

            'apportionment_id' => [
                'type'              => 'many2one',
                'description'       => "The key that the apportionment refers to.",
                'foreign_object'    => 'realestate\property\Apportionment',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['is_statutory', '=', false], ['is_active', '=', true], ['status', '=', 'published']],
                'help'              => "This value is used for splitting the amount amongst owners. One set, it can no longer be changed.",
                'visible'           => ['is_private_expense', '=', false]
            ],

            'owner_share'           => [
                'type'              => 'integer',
                'default'           => 100,
                'description'       => "Default value, in percent, of the amount to be imputed to the owner when using the account.",
                'help'              => "This value is used for splitting the amount amongst owners. One set, it can no longer be changed."
            ],

            'tenant_share'          => [
                'type'              => 'integer',
                'default'           => 0,
                'description'       => "Default value, in percent, of the amount to be imputed to the tenant when using the account.",
                'help'              => "This value is used for splitting the amount amongst owners. One set, it can no longer be changed."
            ],

            'ownership_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\ownership\Ownership',
                'description'       => "Ownership to apply the charge to.",
                'visible'           => ['is_private_expense', '=', true],
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'property_lot_id' => [
                'type'              => 'many2one',
                'description'       => "Property Lot to apply the charge to.",
                'foreign_object'    => 'realestate\property\PropertyLot',
                'visible'           => ['is_private_expense', '=', true],
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

        ];
    }

    public static function onupdateIsPrivateExpense($self) {
        $self->read(['is_private_expense', 'condo_id']);
        foreach($self as $id => $invoiceLine) {
            if(!$invoiceLine['is_private_expense']) {
                continue;
            }
            // set expense_account_id to 643xxx
            $account = Account::search([['condo_id', '=', $invoiceLine['condo_id']], ['operation_assignment', '=', 'private_expenses']])
                ->read(['id', 'name'])
                ->first();
            if($account) {
                self::id($id)->update(['expense_account_id' => $account['id']]);
            }
        }
    }

    public static function onchange($event, $values) {
        $result = [];
        // check VAT
        if(isset($event['vat_rate']) && $event['vat_rate'] >= 1) {
            $result['vat_rate'] = round($event['vat_rate'] / 100, 2);
            $event['vat_rate'] = $result['vat_rate'];
        }
        // update price
        if(array_key_exists('vat_rate', $event) && $values['total']) {
            $result['price'] = round($values['total'] * (1 + $event['vat_rate']), 2);
        }
        if(array_key_exists('total', $event)) {
            if($values['vat_rate']) {
                $result['price'] = round($event['total'] * (1 + $values['vat_rate']), 2);
            }
            else {
                $result['price'] = round($event['total'], 2);
            }
        }
        // update expense account
        if(isset($event['is_private_expense']) && $event['is_private_expense']) {
            $account = Account::search([['condo_id', '=', $values['condo_id']], ['operation_assignment', '=', 'private_expenses']])
                ->read(['id', 'name'])
                ->first();
            if($account) {
                $result['expense_account_id'] = [
                        'id'    => $account['id'],
                        'name'  => $account['name']
                    ];
            }
        }

        if(isset($event['tenant_share'])) {
            $result['owner_share'] = 100 - intval($event['tenant_share']);
        }
        elseif(isset($event['owner_share'])) {
            $result['tenant_share'] = 100 - intval($event['owner_share']);
        }

        // synchronize ownership & property lots
        // #memo - we must be able to assign any ownership (not only active ones)
        if(array_key_exists('ownership_id', $event)) {
            if($event['ownership_id']) {
                $propertyOwnerships = PropertyLotOwnership::search([['ownership_id', '=', $event['ownership_id']]])->read(['property_lot_id'])->get(true);
                $property_lots_ids = array_map(function ($a) {return $a['property_lot_id'];}, $propertyOwnerships);
                if(!$values['property_lot_id'] || !in_array($values['property_lot_id'], $property_lots_ids) ) {
                    $result['property_lot_id'] = [
                        'domain' => [['condo_id', '=', $values['condo_id']], ['id', 'in', $property_lots_ids]]
                    ];
                }
            }
            else {
                $result['ownership_id'] = [
                    'domain' => ['condo_id', '=', $values['condo_id']]
                ];
                $result['property_lot_id'] = [
                    'domain' => ['condo_id', '=', $values['condo_id']]
                ];
            }
        }
        if(array_key_exists('property_lot_id', $event)) {
            if($event['property_lot_id']) {
                $propertyOwnerships = PropertyLotOwnership::search([['property_lot_id', '=', $event['property_lot_id']]])->read(['ownership_id'])->get(true);
                $ownerships_ids = array_map(function ($a) {return $a['ownership_id'];}, $propertyOwnerships);
                if(!$values['ownership_id'] || !in_array($values['ownership_id'], $ownerships_ids) ) {
                    $result['ownership_id'] = [
                        'domain' => [['condo_id', '=', $values['condo_id']], ['id', 'in', $ownerships_ids]]
                    ];
                }
            }
            else {
                $result['ownership_id'] = [
                    'domain' => ['condo_id', '=', $values['condo_id']]
                ];
                $result['property_lot_id'] = [
                    'domain' => ['condo_id', '=', $values['condo_id']]
                ];
            }
        }
        if(isset($event['expense_account_id'])) {
            $expenseAccount = Account::id($event['expense_account_id'])->read(['id', 'apportionment_id', 'tenant_share', 'owner_share'])->first();
            if($expenseAccount) {
                $result['apportionment_id'] = $expenseAccount['apportionment_id'];
                $result['tenant_share'] = $expenseAccount['tenant_share'];
                $result['owner_share'] = $expenseAccount['owner_share'];
            }
        }
        return $result;
    }

    public static function canupdate($self, $values) {
        $self->read(['invoice_id' => ['status', 'document_process_id' => ['status']]]);
        foreach($self as $id => $invoiceLine) {
            if($invoiceLine['invoice_id']['status'] !== 'proforma') {
                return ['invoice_id' => ['non_editable' => 'Line cannot be updated after invoice creation.']];
            }
            if(!$invoiceLine['invoice_id']['document_process_id']) {
                continue;
            }
            if($invoiceLine['invoice_id']['document_process_id']['status'] !== 'created') {
                return ['invoice_id' => ['non_editable' => 'Line cannot be updated after Document processing.']];
            }
        }
    }

    public static function onafterupdate($self, $values) {
        $self->read(['invoice_id']);
        $map_invoices_ids = [];
        foreach($self as $id => $invoiceLine) {
            $map_invoices_ids[$invoiceLine['invoice_id']] = true;
        }
        Invoice::ids(array_keys($map_invoices_ids))->do('update_document_json', ['lines' => $values]);
    }
}


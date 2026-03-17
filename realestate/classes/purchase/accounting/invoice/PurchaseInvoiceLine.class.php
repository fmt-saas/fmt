<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace realestate\purchase\accounting\invoice;

use finance\accounting\Account;
use realestate\finance\accounting\AccountingEntryLine;
use realestate\property\PropertyLotOwnership;

class PurchaseInvoiceLine extends \purchase\accounting\invoice\PurchaseInvoiceLine {

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
                'foreign_object'    => 'realestate\purchase\accounting\invoice\PurchaseInvoice',
                'description'       => 'Invoice the line is related to.',
                'required'          => true,
                'ondelete'          => 'cascade'
            ],

            'is_private_expense' => [
                'type'              => 'boolean',
                'description'       => 'Enable to apply charge to a single owner.',
                'default'           => false,
                'onupdate'          => 'onupdateIsPrivateExpense',
                'visible'           => ['is_apportionable', '=', true]
            ],

            'has_instant_reinvoice' => [
                'type'              => 'boolean',
                'description'       => 'Immediate reinvoicing of private expenses.',
                'help'              => 'When enabled, private charges are automatically reinvoiced as soon as they are recorded, without waiting for the end of the period or manual grouping.',
                'default'           => 'defaultHasInstantReinvoice',
            ],

            'expense_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Account',
                'description'       => "Accounting account the entry relates to.",
                'required'          => true,
                'ondelete'          => 'null',
                // #todo - limit to supplier assigned accounts
                'domain'            => [
                    ['condo_id', '=', 'object.condo_id'],
                    ['condo_id', '<>', null],
                    ['is_control_account', '=', false],
                    ['account_class', 'in', [3, 4, 6, 7]],
                    ['ownership_id', 'is', null],
                    ['suppliership_id', 'is', null]
                ],
                'dependents'        => ['is_apportionable']
            ],

            'is_apportionable' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'Is an apportionment possible for this expense account?',
                'store'             => true,
                'relation'          => ['expense_account_id' => 'is_apportionable']
            ],

            'apportionment_id' => [
                'type'              => 'many2one',
                'description'       => "The key that the apportionment refers to.",
                'foreign_object'    => 'realestate\property\Apportionment',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['is_statutory', '=', false], ['is_active', '=', true], ['status', '=', 'validated']],
                'help'              => "This value is used for splitting the amount amongst owners. One set, it can no longer be changed.",
                'visible'           => [
                    ['is_private_expense', '=', false],
                    ['is_apportionable', '=', true]
                ]
            ],

            'owner_share'           => [
                'type'              => 'integer',
                'default'           => 100,
                'description'       => "Default value, in percent, of the amount to be imputed to the owner when using the account.",
                'help'              => "This value is used for splitting the amount amongst owners. One set, it can no longer be changed.",
                'onupdate'          => 'onupdateOwnerShare',
                'visible'           => ['is_apportionable', '=', true]
            ],

            'tenant_share'          => [
                'type'              => 'integer',
                'default'           => 0,
                'description'       => "Default value, in percent, of the amount to be imputed to the tenant when using the account.",
                'help'              => "This value is used for splitting the amount amongst owners. One set, it can no longer be changed.",
                'onupdate'          => 'onupdateTenantShare',
                'visible'           => ['is_apportionable', '=', true]
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

            'price' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Final tax-included price of the line.',
                'help'              => "For realestate purchase invoice (manually encoded), price is always provided at creation. It is the only amount used for generating the accounting entries.",
                'dependents'        => ['total']
            ],

            'total' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:4',
                'function'          => 'calcTotal',
                'store'             => true,
                'description'       => 'Total tax-excluded price of the line.',
                'help'              => "For realestate purchase invoice (manually encoded), total is computed based on vat rate an VAT incl price.
                    Only price is used for generating the accounting entries.
                    To maintain SUM lines = invoice payable_amount, this field needs a precision of 4 digits.",
            ],

        ];
    }

    public static function defaultHasInstantReinvoice($values) {
        $result = null;
        if(isset($values['invoice_id'])) {
            $invoice = PurchaseInvoice::id($values['invoice_id'])->read(['has_instant_reinvoice'])->first();
            if(isset($invoice['has_instant_reinvoice'])) {
                $result = $invoice['has_instant_reinvoice'];
            }
        }
        return $result;
    }

    protected static function onupdateTenantShare($self) {
        $self->read(['tenant_share', 'owner_share']);
        foreach($self as $id => $invoiceLine) {
            if(!isset($invoiceLine['tenant_share'])) {
                continue;
            }
            $owner_share = 100 - intval($invoiceLine['tenant_share']);
            if($owner_share != $invoiceLine['owner_share']) {
                self::id($id)->update(['owner_share' => $owner_share]);
            }
        }
    }

    protected static function onupdateOwnerShare($self) {
        $self->read(['tenant_share', 'owner_share']);
        foreach($self as $id => $invoiceLine) {
            if(!isset($invoiceLine['owner_share'])) {
                continue;
            }
            $tenant_share = 100 - intval($invoiceLine['owner_share']);
            if($tenant_share != $invoiceLine['tenant_share']) {
                self::id($id)->update(['tenant_share' => $tenant_share]);
            }
        }
    }

    protected static function onupdateIsPrivateExpense($self) {
        $self->read(['is_private_expense', 'condo_id']);
        foreach($self as $id => $invoiceLine) {
            if(!$invoiceLine['is_private_expense']) {
                continue;
            }
            $values = [
                'apportionment_id'    => null
            ];
            // set expense_account_id to 643xxx
            $account = Account::search([['condo_id', '=', $invoiceLine['condo_id']], ['operation_assignment', '=', 'private_expenses']])
                ->first();
            if($account) {
                $values['expense_account_id'] = $account['id'];
            }
            self::id($id)->update($values);
        }
    }

    public static function onchange($self, $event, $values, $view) {
        $result = [];

        /*
            switch($view) {
                case 'list.default':
                break;
            }
        */
        $purchaseInvoiceLine = self::id($values['id'])->read(['price', 'invoice_id' => ['status']])->first();

        // check VAT
        if(isset($event['vat_rate']) && $event['vat_rate'] >= 1) {
            $result['vat_rate'] = round($event['vat_rate'] / 100, 2);
            $event['vat_rate'] = $result['vat_rate'];
        }

        if($purchaseInvoiceLine['invoice_id']['status'] === 'posted') {
            // update total
            if(array_key_exists('vat_rate', $event)) {
                $result['total'] = round($purchaseInvoiceLine['price'] / (1 + $event['vat_rate']), 4);
            }
        }
        else {
            // update price
            if(array_key_exists('vat_rate', $event) && isset($values['total'])) {
                $result['price'] = round($values['total'] * (1 + $event['vat_rate']), 2);
            }

            if(isset($event['price'])) {
                if(isset($event['vat_rate']) || isset($values['vat_rate'])) {
                    $vat_rate = $event['vat_rate'] ?? $values['vat_rate'];
                    // #memo - qty is fixed to 1
                    $result['total'] = round($event['price'] / (1 + $vat_rate), 2);
                    $result['unit_price'] = round($event['price'] / (1 + $vat_rate), 2);
                }
                else {
                    $result['total'] = $event['price'];
                    $result['unit_price'] = $event['price'];
                }
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
            if(isset($event['is_private_expense'])) {
                if($event['is_private_expense']) {
                    $account = Account::search([['condo_id', '=', $values['condo_id']], ['operation_assignment', '=', 'private_expenses']])
                        ->read(['id', 'name'])
                        ->first();
                    if($account) {
                        $result['expense_account_id'] = [
                                'id'    => $account['id'],
                                'name'  => $account['name']
                            ];
                    }
                    $result['apportionment_id'] = null;
                }
                else {
                    // #memo - visibility might be impacted
                    $result['is_apportionable'] = false;
                    $result['apportionment_id'] = null;
                    $result['expense_account_id'] = null;
                }
            }

            if(array_key_exists('expense_account_id', $event)) {
                if(is_null($event['expense_account_id'])) {
                    $result['is_apportionable'] = false;
                }
                else {
                    $account = Account::id($event['expense_account_id'])
                        ->read(['operation_assignment', 'is_apportionable'])
                        ->first();

                    if($account) {
                        if($account['operation_assignment'] !== 'private_expenses') {
                            $result['is_private_expense'] = false;
                            // #memo - visibility might be impacted
                            $result['is_apportionable'] = $account['is_apportionable'];
                        }
                        else {
                            $result['is_apportionable'] = false;
                        }
                    }
                }
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
            $expenseAccount = Account::id($event['expense_account_id'])->read(['id', 'apportionment_id' => ['id', 'name'], 'tenant_share', 'owner_share'])->first();
            if($expenseAccount) {
                if($expenseAccount['apportionment_id']) {
                    $result['apportionment_id'] = [
                        'id'    => $expenseAccount['apportionment_id']['id'],
                        'name'  => $expenseAccount['apportionment_id']['name']
                    ];
                }
                if($expenseAccount['tenant_share']) {
                    $result['tenant_share'] = $expenseAccount['tenant_share'];
                    $result['owner_share'] = 100 - intval($expenseAccount['tenant_share']);
                }
                elseif($expenseAccount['owner_share']) {
                    $result['owner_share'] = $expenseAccount['owner_share'];
                    $result['tenant_share'] = 100 - intval($expenseAccount['owner_share']);
                }
            }
        }

        return $result;
    }

    public static function canupdate($self, $values) {
        $self->read(['invoice_id' => ['status', 'document_process_id' => ['status']]]);
        $allowed_fields = ['name', 'description'];
        foreach($self as $id => $invoiceLine) {
            $self_allowed_fields = $allowed_fields;
            if($invoiceLine['invoice_id']['status'] === 'posted') {
                // check if related accounting records has been cleared or not
                $accountingEntryLine = AccountingEntryLine::search(['purchase_invoice_line_id', '=', $id])
                    ->read(['is_cleared'])
                    ->first();

                if($accountingEntryLine) {
                    // special case: if the corresponding period has not yet been closed (i.e. no expense statement has been issued yet, i.e. related accounting entry not yet "cleared"), then modification of the account is allowed
                    if(!$accountingEntryLine['is_cleared']) {
                        $self_allowed_fields = array_merge($allowed_fields, ['apportionment_id', 'vat_rate', 'owner_share', 'tenant_share', 'ownership_id', 'property_lot_id']);
                    }

                    // no change allowed, on any field
                    if($accountingEntryLine['is_cleared']) {
                        return ['status' => ['non_editable' => "Invoice can only be updated while its status is proforma ({$id})."]];
                    }
                }

                // in other cases, only allow editable fields
                if(count(array_diff(array_keys($values), $self_allowed_fields)) > 0) {
                    return ['invoice_id' => ['non_editable' => "Line can only be updated while parent invoice hasn't been posted ({$id})."]];
                }
            }
            if(!$invoiceLine['invoice_id']['document_process_id']) {
                // #memo - statuses of invoice and related document processing are linked
                continue;
            }
        }
    }

    protected static function onbeforedelete($self) {
        $self->read(['invoice_id']);
        $map_invoices_ids = [];
        foreach($self as $id => $invoiceLine) {
            $map_invoices_ids[$invoiceLine['invoice_id']] = true;
        }
        PurchaseInvoice::ids(array_keys($map_invoices_ids))->do('update_document_json');
    }

    protected static function onafterupdate($self) {
        $self->read(['invoice_id']);
        $map_invoices_ids = [];
        foreach($self as $id => $invoiceLine) {
            $map_invoices_ids[$invoiceLine['invoice_id']] = true;
        }
        PurchaseInvoice::ids(array_keys($map_invoices_ids))->do('update_document_json');
    }

    protected static function oncreate($self, $values, $lang) {
        if(isset($values['invoice_id'])) {
            $invoice = PurchaseInvoice::id($values['invoice_id'])
                ->read(['description'])
                ->first();
            $self->update(['description' => $invoice['description']], $lang);
        }
    }

    protected static function calcTotal($self) {
        $result = [];
        $self->read(['price', 'vat_rate']);
        foreach($self as $id => $purchaseInvoiceLine) {
            $vat_rate = (float) $purchaseInvoiceLine['vat_rate'];
            if((1 + $vat_rate) == 0 || $vat_rate < 0) {
                $result[$id] = null;
                continue;
            }
            $result[$id] = round($purchaseInvoiceLine['price'] / (1 + $purchaseInvoiceLine['vat_rate']), 4);
        }
        return $result;
    }
}


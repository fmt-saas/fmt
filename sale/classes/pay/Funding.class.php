<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace sale\pay;

use core\setting\Setting;
use equal\data\DataFormatter;

class Funding extends \equal\orm\Model {

    public static function getDescription() {
        return "A funding represents the accounting link between a document (such as an invoice, a call for funds or a charge statement) and the entries that settle it.
            It can be covered by one or several payments, or by any other accounting entries needed to close the document.";
    }

    public static function getColumns() {

        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the payment relates to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'readonly'          => true
            ],

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Display name of funding.',
                'function'          => 'calcName',
                'store'             => true
            ],

            'description' => [
                'type'              => 'string',
                'description'       => 'Optional description to identify the funding.'
            ],

            'has_mandate' => [
                'type'              => 'boolean',
                'description'       => 'Mark Payment to be made through a mandate.',
                'help'              => 'The Condominium has an active SEPA mandate for the subsequent payments (and should be sent to bank).',
                'default'           => false
            ],

            'is_sent' => [
                'type'              => 'boolean',
                'description'       => 'Flag indicating if a SEPA order has been generated (once or more) from the Funding.',
                'default'           => false
            ],

            'payments_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\pay\Payment',
                'foreign_field'     => 'funding_id',
                'description'       => 'Payments of the funding.',
                'dependents'        => ['paid_amount', 'remaining_amount', 'is_paid']
            ],

            'accounting_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Account',
                'description'       => "Accounting account the funding relates to.",
                'required'          => true,
                'domain'            => [
                    ['condo_id', '=', 'object.condo_id'], ['is_control_account', '=', false]
                ]
            ],

            'accounting_entry_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\accounting\AccountingEntryLine',
                'foreign_field'     => 'funding_id',
                'description'       => "Accounting entry of the Matching.",
                'domain'            => [
                    ['condo_id', '=', 'object.condo_id']
                ]
            ],

            'funding_type' => [
                'type'              => 'string',
                'selection'         => [
                    'installment',
                    'refund',
                    'transfer',
                    'sale_invoice',
                    'purchase_invoice'
                ],
                'required'          => true,
                'description'       => "Type of funding. Either an installment, a specific invoice, a fund request, or an expense statement."
            ],

            'due_amount' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Amount expected for the funding.',
                'required'          => true,
                'dependents'        => ['name']
            ],

            'due_date' => [
                'type'              => 'date',
                'usage'             => 'date/plain',
                'description'       => "Deadline before which the funding is expected."
            ],

            'issue_date' => [
                'type'              => 'date',
                'description'       => "Date at which the request for payment has to be issued.",
                'default'           => function() { return time(); }
            ],

            'paid_amount' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:2',
                'description'       => "Total amount that has been received or paid (can exceed due_amount).",
                'function'          => 'calcPaidAmount',
                'store'             => true,
                'instant'           => true
            ],

            'remaining_amount' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:2',
                'description'       => "Total amount that is left to be received or paid.",
                'function'          => 'calcRemainingAmount',
                'store'             => true,
                'instant'           => true
            ],

            'is_paid' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'Has the full payment been performed?',
                'deprecated'        => 'Use status instead (balanced means fully paid).',
                'function'          => 'calcIsPaid',
                'store'             => true,
                'instant'           => true
            ],

            'accounting_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Account',
                'description'       => "Target accounting account that will be impacted by the movement (expected payments).",
                'required'          => true,
                'ondelete'          => 'null',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null], ['is_control_account', '=', false]],
                'dependents'        => ['account_code']
            ],

            'bank_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\bank\BankAccount',
                'description'       => 'The Bank account the funding relates to.',
                'help'              => 'This is the bank account to which payments are expected to be received (or from which payment is expected to be made).',
                'readonly'          => true,
                'dependents'        => ['bank_account_iban'],
                'domain'            => [['is_active', '=', true]]
            ],

            'counterpart_bank_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\bank\BankAccount',
                'description'       => 'Counterpart bank account, when applying.',
                'help'              => 'The bank account used as the counterpart in a transfer. Required when the funding represents an internal transfer between two bank accounts.',
                'readonly'          => true,
                'dependents'        => ['counterpart_bank_account_iban']
            ],

            'bank_account_iban' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'usage'             => 'uri/urn.iban',
                'description'       => 'The Bank account IBAN.',
                'relation'          => ['bank_account_id' => 'bank_account_iban'],
                'store'             => true,
                'instant'           => true
            ],

            'counterpart_bank_account_iban' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'usage'             => 'uri/urn.iban',
                'description'       => 'Counterpart bank account IBAN.',
                'relation'          => ['counterpart_bank_account_id' => 'bank_account_iban'],
                'store'             => true,
                'instant'           => true
            ],

            'sale_invoice_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\accounting\invoice\SaleInvoice',
                'description'       => 'The invoice targeted by the funding, if any.',
                'help'              => 'As a convention, this field is set when a funding relates to an invoice: either because the funding has been invoiced (downpayment or balance invoice), or because it is an installment (deduced from the due amount).',
                'readonly'          => true,
                'visible'           => ['funding_type', 'in', ['installment', 'sale_invoice']],
            ],

            'purchase_invoice_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'purchase\accounting\invoice\PurchaseInvoice',
                'description'       => 'The invoice targeted by the funding, if any.',
                'help'              => 'As a convention, this field is set when a funding relates to an invoice: either because the funding has been invoiced (downpayment or balance invoice), or because it is an installment (deduced from the due amount).',
                'readonly'          => true,
                'visible'           => ['funding_type', 'in', ['installment', 'purchase_invoice']],
            ],

            'payment_reference' => [
                'type'              => 'string',
                'description'       => 'Message for identifying the purpose of the transaction.'
            ],

            'is_cancelled' => [
                'type'              => 'boolean',
                'description'       => "Flag marking the funding as cancelled.",
                'help'              => 'When cancelled, in addition to having this flag to true, the subsequent payments, if any, are also detached from the funding.',
                'default'          => false
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'pending',
                    'debit_balance',
                    'credit_balance',
                    'balanced'
                ],
                'default'           => 'pending',
                'description'       => 'Status of the funding.'
            ]
        ];
    }

    public static function getActions() {
        return [
            'refresh_status' => [
                'description'   => 'Update status according to currently paid amount.',
                'policies'      => [],
                'function'      => 'doRefreshStatus'
            ]
        ];
    }

    public static function getPolicies(): array {
        return [
            'is_paid' => [
                'description' => 'Checks that delta between opening and closing amounts matches the sum of the lines amounts.',
                'function'    => 'policyIsPaid'
            ],
        ];
    }

    public static function policyIsPaid($self): array {
        $result = [];
        $self->read(['is_paid']);
        foreach($self as $id => $funding) {
            if(!$funding['is_paid']) {
                $result[$id] = [
                    'not_paid' => 'The funding is not fully paid.'
                ];
            }
        }
        return $result;
    }

    protected static function doRefreshStatus($self) {
        $self
            ->update(['is_paid' => null, 'paid_amount' => null, 'remaining_amount' => null])
            ->read(['due_amount', 'is_paid', 'paid_amount', 'remaining_amount']);

        foreach($self as $id => $funding) {
            $due = round((float) $funding['due_amount'], 2);
            $paid = round((float) $funding['paid_amount'], 2);

            if($paid === $due) {
                $status = 'balanced';
            }
            elseif(($due > 0 && $paid > $due) || ($due < 0 && $paid < $due)) {
                $status = 'credit_balance';
            }
            else {
                $status = 'debit_balance';
            }
            self::id($id)->update(['status' => $status]);
        }
    }

    protected static function calcName($self) {
        $result = [];
        $self->read(['due_amount', 'payment_reference', 'invoice_id' => ['name']]);
        foreach($self as $id => $funding) {
            $result[$id] = Setting::format_number_currency($funding['due_amount']);

            if($funding['invoice_id']) {
                $result[$id] .= '  ' . $funding['invoice_id']['name'];
            }

            if($funding['payment_reference']) {
                $result[$id] .= '  ' . DataFormatter::format($funding['payment_reference'], 'scor');
            }
        }

        return $result;
    }

    public static function calcPaidAmount($self) {
        $result = [];
        $self->read(['payments_ids' => ['status', 'amount']]);
        foreach($self as $id => $funding) {
            $result[$id] = array_reduce($funding['payments_ids']->get(true), function ($c, $a) {
                return ($a['status'] === 'posted') ? ($c + $a['amount']) : $c;
            }, 0.0);
        }
        return $result;
    }

    public static function calcRemainingAmount($self) {
        $result = [];
        $self->read(['due_amount', 'paid_amount']);
        foreach($self as $id => $funding) {
            $result[$id] = round($funding['due_amount'] - $funding['paid_amount'], 2);
        }
        return $result;
    }

    protected static function calcIsPaid($self) {
        $result = [];
        $self->read(['due_amount', 'paid_amount']);

        foreach ($self as $id => $funding) {
            $due  = round($funding['due_amount'] ?? 0.0, 2);
            $paid = round($funding['paid_amount'] ?? 0.0, 2);

            if($due > 0.0) {
                $result[$id] = ($paid >= $due);
            }
            else {
                $result[$id] = ($paid <= $due);
            }
        }

        return $result;
    }

    public static function canupdate($self, $values) {
        $self->read(['status']);
        foreach($self as $funding) {
            if($funding['status'] == 'balanced') {
                // Funding might change depending on actions performed on Payments
                // return ['status' => ['non_editable' => 'No change is allowed once the funding has been fully paid.']];
            }
        }

        return parent::canupdate($self, $values);
    }

    public static function candelete($self) {
        $self->read(['is_paid', 'paid_amount', 'invoice_id' => ['status', 'invoice_type'], 'payments_ids']);
        foreach($self as $funding) {
            if($funding['is_paid'] || $funding['paid_amount'] != 0 || count($funding['payments_ids']) > 0) {
                return ['payments_ids' => ['non_removable_funding' => 'Funding paid or partially paid cannot be deleted.']];
            }
            if(isset($funding['invoice_id']['status']) && $funding['invoice_id']['status'] == 'posted' && $funding['invoice_id']['invoice_type'] == 'invoice') {
                return ['invoice_id' => ['non_removable_funding' => 'Funding relating to an invoice cannot be deleted.']];
            }
        }

        return parent::candelete($self);
    }

    public static function onchange($event, $values) {
        $result = [];

        // if 'is_paid' is set manually, adapt 'paid_mount' consequently
        if(isset($event['is_paid'])) {
            $result['paid_amount'] = $values['due_amount'];
        }

        return $result;
    }

    /**
     * Compute a Structured Reference using belgian SCOR (StructuredCommunicationReference) reference format.
     *
     * Note:
     *   format is aaa-bbbbbbb-XX and is displayed +++aaa/bbbb/bbbXX+++
     *   where Xaaa is the prefix, bbbbbbb is the suffix, and XX is the control number, that must verify (aaa * 10000000 + bbbbbbb) % 97
     *      since 10000000 % 97 = 76
     *      we do (aaa * 76 + bbbbbbb) % 97
     */
    protected static function computePaymentReference($prefix, $suffix) {
        $a = intval($prefix);
        $b = intval($suffix);
        $control = ((76*$a) + $b ) % 97;
        $control = ($control == 0) ? 97 : $control;
        return sprintf("%03d%04d%03d%02d", $a, $b / 1000, $b % 1000, $control);
    }
}

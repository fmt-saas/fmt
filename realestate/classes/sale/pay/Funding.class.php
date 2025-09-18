<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace realestate\sale\pay;

use core\setting\Setting;
use equal\data\DataFormatter;
use finance\accounting\MiscOperation;
use finance\bank\BankStatementLine;
use realestate\finance\accounting\MoneyRefund;
use realestate\finance\accounting\MoneyTransfer;

class Funding extends \sale\pay\Funding {

    public static function getDescription() {
        return 'Funding for tracking fund requests and expense statements (funds to be received).';
    }

    public static function getColumns() {

        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Display name of funding.',
                'function'          => 'calcName',
                'store'             => true
            ],

            'is_sent' => [
                'type'              => 'boolean',
                'description'       => 'Flag indicating if a SEPA order has been generated (once or more) from the Funding.',
                'default'           => false
            ],

            'payments_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\sale\pay\Payment',
                'foreign_field'     => 'funding_id',
                'description'       => 'Payments of the funding.',
                'dependents'        => ['paid_amount', 'is_paid']
            ],

            'accounting_entries_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\finance\accounting\AccountingEntry',
                'description'       => "Accounting entry of the Matching.",
                'domain'            => [
                    ['condo_id', '=', 'object.condo_id'],
                    ['matching_id', '=', 'object.id']
                ]
            ],

            'bank_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\bank\BankAccount',
                'description'       => 'The Bank account the funding relates to.',
                'help'              => 'This is the bank account to which payment is expected to be received, or from which payment is expected to be made.',
                'readonly'          => true,
                'dependents'        => ['bank_account_iban'],
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'counterpart_bank_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\bank\BankAccount',
                'description'       => 'Counterpart bank account, when applying.',
                'help'              => 'The bank account used as the counterpart in a transfer. Required when the funding represents an internal transfer between two bank accounts.',
                'readonly'          => true
            ],

            'funding_type' => [
                'type'              => 'string',
                'selection'         => [
                    'installment',
                    // money_refund
                    'refund',
                    // money transfer
                    'transfer',
                    'purchase_invoice',
                    'sale_invoice',
                    'fund_request',
                    'expense_statement',
                    'misc',
                    'statement_line'
                ],
                'required'          => true,
                'dependents'        => ['payment_reference'],
                'description'       => "Type of funding. Either an installment, a specific invoice, a fund request, or an expense statement."
            ],

            'has_mandate' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'relation'          => ['purchase_invoice_id' => 'has_mandate'],
                'description'       => 'Mark Payment to be made through a mandate.',
                'help'              => 'The Condominium has an active SEPA mandate for paying invoices from this supplier and payment will be made through it.',
                'store'             => true,
                'default'           => false,
                'visible'           => ['purchase_invoice_id', '<>', null]
            ],

            'fund_request_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\funding\FundRequest',
                'description'       => 'The fund request targeted by the funding, if any.',
                'readonly'          => true,
                'visible'           => ['funding_type', '=', 'fund_request']
            ],

            'fund_request_execution_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\funding\FundRequestExecution',
                'description'       => 'The fund request execution targeted by the funding, if any.',
                'help'              => 'As a convention, this field is set when a funding relates to a fund request. Fund request executions are sale invoices (with invoice_type set to fund_request).',
                'visible'           => ['funding_type', '=', 'fund_request'],
                'readonly'          => true
            ],

            'expense_statement_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\funding\ExpenseStatement',
                'description'       => 'The fund request execution targeted by the funding, if any.',
                'help'              => 'As a convention, this field is set when a funding relates to a fund request. Fund request executions are sale invoices (with invoice_type set to fund_request).',
                'visible'           => ['funding_type', '=', 'expense_statement'],
                'readonly'          => true
            ],

            'purchase_invoice_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\purchase\accounting\invoice\PurchaseInvoice',
                'description'       => 'The purchase invoice targeted by the funding, if any.',
                'help'              => 'As a convention, this field is set when a funding relates to an invoice: either because the funding has been invoiced (downpayment or balance invoice), or because it is an installment (deduced from the due amount).',
                'readonly'          => true,
                'dependents'        => ['has_mandate'],
                'visible'           => ['funding_type', '=', 'purchase_invoice'],
            ],

            'money_transfer_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\finance\accounting\MoneyTransfer',
                'description'       => 'Miscellaneous operation targeted by the funding, if any.',
                'help'              => 'Money transfer is a particular case of misc operation.',
                'readonly'          => true,
                'visible'           => ['funding_type', '=', 'transfer'],
            ],

            'money_refund_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\finance\accounting\MoneyRefund',
                'description'       => 'Miscellaneous operation targeted by the funding, if any.',
                'help'              => 'Money refund is a particular case of misc operation.',
                'readonly'          => true,
                'visible'           => ['funding_type', '=', 'refund'],
            ],

            'misc_operation_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\MiscOperation',
                'description'       => 'Miscellaneous operation targeted by the funding, if any.',
                'help'              => 'This is for the unexpected movements, for which the Funding was created at bank statement line reconcile.',
                'readonly'          => true,
                'visible'           => ['funding_type', '=', 'misc'],
            ],

            'bank_statement_line_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\bank\BankStatementLine',
                'description'       => 'The bank statement line targeted by the funding, if any.',
                'visible'           => ['funding_type', '=', 'statement_line'],
            ],

            'ownership_id' => [
                'type'              => 'many2one',
                'description'       => "The ownership that the funding refers to.",
                'foreign_object'    => 'realestate\ownership\Ownership',
                'ondelete'          => 'cascade',
                'domain'            => ['condo_id', '=', 'object.condo_id'],
                'readonly'          => true
            ],

            'suppliership_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'purchase\supplier\Suppliership',
                'description'       => 'The supplier the funding relates to.',
                'domain'            => ['condo_id', '=', 'object.condo_id'],
                'readonly'          => true
            ],

            'payment_reference' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Message for identifying the purpose of the transaction.',
                'help'              => 'An arbitrary payment reference can be assigned at Funding creation to override the computation logic.',
                'store'             => true,
                'instant'           => true,
                'function'          => 'calcPaymentReference'
            ],

        ];
    }

    public static function getActions() {
        return array_merge(parent::getActions(), [
            'refresh_status' => [
                'description'   => 'Update status according to currently paid amount.',
                'policies'      => [],
                'function'      => 'doRefreshStatus'
            ],
            'attempt_post_accounting_document' => [
                'description'   => 'Retrieve the associated accounting document and post it, if not posted yet.',
                'policies'      => [],
                'function'      => 'doAttemptPostAccountingDocument'
            ]

        ]);
    }


    protected static function doAttemptPostAccountingDocument($self) {
        $self->read([
                'status',
                'funding_type',
                'money_transfer_id',
                'money_refund_id',
                'misc_operation_id',
                'bank_statement_line_id',
            ]);

        foreach($self as $id => $funding) {
            if($funding['status'] === 'balanced') {
                continue;
            }

            $operations = null;

            switch($funding['funding_type']) {
                // these accounting documents are posted independently from the Funding
                case 'fund_request':
                case 'expense_statement':
                case 'purchase_invoice':
                    break;
                case 'transfer':
                    $operations = MoneyTransfer::id($funding['bank_statemoney_transfer_idment_line_id'])->read(['status']);
                    break;
                case 'refund':
                    $operations = MoneyRefund::id($funding['money_refund_id'])->read(['status']);
                    break;
                case 'statement_line':
                    $operations = BankStatementLine::id($funding['bank_statement_line_id'])->read(['status']);
                    break;
                case 'misc':
                    $operations = MiscOperation::id($funding['misc_operation_id'])->read(['status']);
                    break;
            }

            if($operations) {
                $operation = $operations->first();
                if($operation['status'] !== 'posted') {
                    $operations->transition('post');
                }
            }

        }
    }

    protected static function calcName($self) {
        $result = [];
        $self->read(['state', 'due_amount', 'payment_reference', 'fund_request_execution_id' => ['name'],  'purchase_invoice_id' => ['name']]);
        foreach($self as $id => $funding) {
            if($funding['state'] === 'draft') {
                continue;
            }
            if(!$funding['due_amount']) {
                continue;
            }

            $result[$id] = Setting::format_number_currency($funding['due_amount']);

            if($funding['payment_reference']) {
                $result[$id] .= '  ' . DataFormatter::format($funding['payment_reference'], 'scor');
            }

            if($funding['purchase_invoice_id']) {
                $result[$id] .= '  ' . $funding['purchase_invoice_id']['name'];
            }

            if($funding['fund_request_execution_id']) {
                $result[$id] .= '  ' . $funding['fund_request_execution_id']['name'];
            }

        }

        return $result;
    }

    /**
     * Generate payment reference according to SCOR/VCS logic
     *
     */
    protected static function calcPaymentReference($self) {
        $result = [];
        $self->read([
                'funding_type',
                'purchase_invoice_id', 'money_transfer_id', 'money_refund_id', 'misc_operation_id', 'bank_statement_line_id',
                'condo_id' => ['code'],
                'ownership_id' => ['code']
            ]);
        foreach($self as $id => $funding) {
            if(!$funding['funding_type']) {
                continue;
            }

            $reference = str_pad('', 12, '0');

            switch($funding['funding_type']) {
                case 'statement_line':
                    // by convention, references for statement lines start with '3'
                    $reference = sprintf("3%09d", $funding['bank_statement_line_id']);
                    break;
                case 'fund_request':
                    // by convention, references for fund requests start with '4'
                    $reference =
                        '4' .
                        substr(str_pad((int) $funding['condo_id']['code'], 5, '0', STR_PAD_LEFT), 0, 6) .
                        substr(str_pad((int) $funding['ownership_id']['code'], 4, '0', STR_PAD_LEFT), 0, 4);
                    break;
                case 'expense_statement':
                    // by convention, references for expense statements start with '5'
                    $reference =
                        '5' .
                        substr(str_pad((int) $funding['condo_id']['code'], 5, '0', STR_PAD_LEFT), 0, 6) .
                        substr(str_pad((int) $funding['ownership_id']['code'], 4, '0', STR_PAD_LEFT), 0, 4);
                    break;
                case 'refund':
                    // by convention, references for refunds start with '6'
                    $reference = sprintf("6%09d", $funding['money_refund_id']);
                    break;
                case 'transfer':
                    // by convention, references for money transfers start with '7'
                    $reference = sprintf("7%09d", $funding['money_transfer_id']);
                    break;
                case 'invoice':
                    // by convention, references for purchase invoices start with '8'
                    $reference = sprintf("8%09d", $funding['purchase_invoice_id']);
                    break;
                case 'misc':
                    // by convention, references for misc operations start with '9'
                    $reference = sprintf("9%09d", $funding['misc_operation_id']);
                    break;
                case 'installment':
                    // #memo - non relevant here
                    break;
            }

            $prefix = substr($reference, 0, 3);
            $suffix = substr($reference, 3);

            $result[$id] = self::computePaymentReference($prefix, $suffix);
        }
        return $result;
    }

    /**
     * Check if the Funding relates to an Ownership for which a property transfer is in progress,
     * and, if it is the case, dispatch `sale.pay.funding.ownership_transfer`.
     *
     */
    protected static function oncreate($self) {
        \eQual::run('do', 'realestate_sale_pay_Funding_check-transfer', ['ids' => $self->ids()]);
    }

}

<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace realestate\sale\pay;

use core\setting\Setting;
use equal\data\DataFormatter;
use finance\accounting\Matching;
use finance\accounting\MiscOperation;
use finance\bank\BankStatementLine;
use realestate\finance\accounting\AccountingEntryLine;
use realestate\finance\accounting\MoneyRefund;
use realestate\finance\accounting\MoneyTransfer;
use realestate\funding\ExpenseStatement;
use realestate\funding\FundRequestExecution;
use realestate\purchase\accounting\invoice\PurchaseInvoice;

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

            'accounting_entry_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\finance\accounting\AccountingEntryLine',
                'foreign_field'     => 'funding_id',
                'description'       => "Accounting entry lines (records) of the Funding.",
                'domain'            => [
                    ['condo_id', '=', 'object.condo_id']
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
            'match_accounting_entries' => [
                'description'   => 'Update status according to currently paid amount.',
                'policies'      => [],
                'function'      => 'doMatchAccountingEntries'
            ]
        ]);
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

    protected static function doMatchAccountingEntries($self) {
        $self->read([
                'condo_id',
                // lines from Bank Statement
                'accounting_entry_lines_ids',
                // lines from accounting entries linked accounting documents
                'funding_type',
                'money_refund_id',
                'money_transfer_id',
                'purchase_invoice_id',
                'expense_statement_id',
                'fund_request_execution_id'
            ]);

        foreach($self as $id => $funding) {
            $accounting_entry_lines_ids = $funding['accounting_entry_lines_ids'];
            switch($funding['funding_type']) {
                case 'expense_statement':
                    $expenseStatement = ExpenseStatement::id($funding['expense_statement_id'])->read(['accounting_entry_id' => ['entry_lines_ids']])->first();
                    $accounting_entry_lines_ids = array_merge($accounting_entry_lines_ids, $expenseStatement['accounting_entry_id']['entry_lines_ids']);
                    break;
                case 'fund_request':
                    $fundRequest = FundRequestExecution::id($funding['fund_request_execution_id'])->read(['accounting_entry_id' => ['entry_lines_ids']])->first();
                    $accounting_entry_lines_ids = array_merge($accounting_entry_lines_ids, $fundRequest['accounting_entry_id']['entry_lines_ids']);
                    break;
                case 'purchase_invoice':
                    $purchaseInvoice = PurchaseInvoice::id($funding['purchase_invoice_id'])->read(['accounting_entry_id' => ['entry_lines_ids']])->first();
                    $accounting_entry_lines_ids = array_merge($accounting_entry_lines_ids, $purchaseInvoice['accounting_entry_id']['entry_lines_ids']);
                    break;
                case 'refund':
                    $refund = MoneyRefund::id($funding['money_refund_id'])->read(['fundings_ids' => ['accounting_entry_lines_ids']])->first();
                    foreach($refund['fundings_ids'] as $extFunding) {
                        $accounting_entry_lines_ids = array_merge($accounting_entry_lines_ids, $extFunding['accounting_entry_lines_ids']);
                    }
                    break;
                case 'transfer':
                    $transfer = MoneyTransfer::id($funding['money_transfer_id'])->read(['fundings_ids' => ['accounting_entry_lines_ids']])->first();
                    foreach($transfer['fundings_ids'] as $extFunding) {
                        $accounting_entry_lines_ids = array_merge($accounting_entry_lines_ids, $extFunding['accounting_entry_lines_ids']);
                    }
                    break;
            }
            $map_account_entry_lines = [];
            $accountingEntryLines = AccountingEntryLine::ids($accounting_entry_lines_ids)->read(['account_id']);
            foreach($accountingEntryLines as $accounting_entry_line_id => $accountingEntryLine) {
                $map_account_entry_lines[$accountingEntryLine['account_id']][] = $accounting_entry_line_id;
            }

            foreach($map_account_entry_lines as $accounting_account_id => $accounting_entry_lines_ids) {
                Matching::create([
                        'condo_id'              => $funding['condo_id'],
                        'accounting_account_id' => $accounting_account_id
                    ])
                    ->update(['accounting_entry_lines_ids' => $accounting_entry_lines_ids]);
            }
        }
    }

    protected static function doRefreshStatus($self) {
        parent::doRefreshStatus($self);

        $self->read([
                'status',
                'funding_type',
                'money_refund_id',
                'money_transfer_id',
                'purchase_invoice_id',
                'expense_statement_id',
                'fund_request_execution_id'
            ]);

        foreach($self as $id => $funding) {
            if($funding['status'] === 'pending') {
                continue;
            }
            switch($funding['funding_type']) {
                case 'expense_statement':
                    ExpenseStatement::id($funding['money_refund_id'])->update(['payment_status' => $funding['status']]);
                    break;
                case 'fund_request':
                    FundRequestExecution::id($funding['fund_request_execution_id'])->update(['payment_status' => $funding['status']]);
                    break;
                case 'purchase_invoice':
                    PurchaseInvoice::id($funding['purchase_invoice_id'])->update(['payment_status' => $funding['status']]);
                    break;
                case 'refund':
                    MoneyRefund::id($funding['money_refund_id'])->update(['payment_status' => $funding['status']]);
                    break;
                case 'transfer':
                    MoneyTransfer::id($funding['money_transfer_id'])->update(['payment_status' => $funding['status']]);
                    break;
            }
        }
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
                // incoming payments (no sale_invoice)
                case 'expense_statement':
                case 'fund_request':
                    $reference =
                        substr(str_pad((int) $funding['condo_id']['code'], 6, '0', STR_PAD_LEFT), 0, 6) .
                        substr(str_pad((int) $funding['ownership_id']['code'], 4, '0', STR_PAD_LEFT), 0, 4);
                    break;
                // outgoing payments
                case 'purchase_invoice':
                    // by convention, references for purchase invoices start with '9'
                    // this reference might be overwritten by the reference given by the supplier
                    $reference = sprintf("9%09d", $funding['purchase_invoice_id']);
                    break;
                case 'refund':
                    // by convention, references for refunds start with '8'
                    $reference = sprintf("8%09d", $funding['money_refund_id']);
                    break;
                case 'transfer':
                    // by convention, references for money transfers start with '7'
                    $reference = sprintf("7%09d", $funding['money_transfer_id']);
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

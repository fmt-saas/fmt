<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace realestate\sale\pay;

use finance\bank\BankStatement;
use finance\bank\BankStatementLine;
use finance\bank\CondominiumBankAccount;
use purchase\supplier\Suppliership;
use realestate\finance\accounting\MoneyTransfer;
use realestate\ownership\Ownership;
use realestate\purchase\accounting\AccountingEntry;
use realestate\purchase\accounting\AccountingEntryLine;

class Payment extends \sale\pay\Payment {

    public static function getColumns() {
        return [
            'funding_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\sale\pay\Funding',
                'description'       => 'The funding the payment relates to, if any.',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]],
                'order'             => 'issue_date',
                'sort'              => 'asc'
            ]
        ];
    }

    public static function getWorkflow() {
        return array_merge(parent::getWorkflow(), [
            'proforma' => [
                'description' => 'Payment being created.',
                'icon'        => 'draw',
                'transitions' => [
                    'post' => [
                        'description' => 'Update the payment status to `payment`.',
                        'onafter'     => 'onafterPost',
                        'status'      => 'posted'
                    ]
                ]
            ]
        ]);
    }

    /**
     * Create or request creation of the accounting entries related to the bank movement.
     * #memo - There is a Special case for MoneyTransfer : Notify the original documents of the funding that a payment has been made, and that the status may change.
     */
    protected static function onafterPost($self) {
        $self->read([
                'condo_id',
                'amount',
                'journal_id',
                'receipt_date',
                'fiscal_year_id',
                'fiscal_period_id',
                'accounting_account_id',
                'statement_line_id' => ['bank_statement_id'],
                'has_funding',
                'funding_id' => [
                    'funding_type',
                    'money_transfer_id',
                    'ownership_id',
                    'suppliership_id',
                    'bank_account_id',
                    'counterpart_bank_account_id'
                ]
            ]);

        foreach($self as $id => $payment) {

            try {
                if(!$payment['statement_line_id']) {
                    // invalid payment, no statement line id
                    throw new \Exception("invalid_payment", EQ_ERROR_MISSING_PARAM);
                }
                if($payment['has_funding'] && !$payment['funding_id']) {
                    throw new \Exception("invalid_payment", EQ_ERROR_MISSING_PARAM);
                }

                $bankStatement = BankStatement::id($payment['statement_line_id']['bank_statement_id'])
                    ->read(['bank_account_id'])
                    ->first();

                BankStatementLine::id($payment['statement_line_id']['id'])
                    ->update(['remaining_amount' => null]);

                $bankAccount = CondominiumBankAccount::id($bankStatement['bank_account_id'])->read(['accounting_account_id'])->first();

                // #memo - there may be Payments without Funding (e.g. bank fees)
                if($payment['has_funding']) {

                    Funding::id($payment['funding_id']['id'])
                        ->do('refresh_status');

                    // special case
                    if($payment['funding_id']['funding_type'] === 'transfer') {
                        // create a single accounting entry, only if transfer is complete
                        MoneyTransfer::id($payment['funding_id']['money_transfer_id'])->do('attempt_posting');
                        continue;
                    }

                    $accountingEntry = AccountingEntry::create([
                            'condo_id'              => $payment['condo_id'],
                            'entry_date'            => $payment['receipt_date'],
                            'origin_object_class'   => self::getType(),
                            'origin_object_id'      => $id,
                            'journal_id'            => $payment['journal_id'],
                            'fiscal_year_id'        => $payment['fiscal_year_id'],
                            'fiscal_period_id'      => $payment['fiscal_period_id']
                        ])
                        ->first();

                    switch($payment['funding_id']['funding_type']) {
                        case 'installment':
                            // #todo
                            throw new \Exception('missing_funding_type', EQ_ERROR_INVALID_PARAM);
                            break;
                        case 'refund':
                            // #todo
                            throw new \Exception('missing_funding_type', EQ_ERROR_INVALID_PARAM);
                            break;
                        case 'invoice':
                            // payment to the supplier
                            $suppliership = Suppliership::id($payment['funding_id']['suppliership_id'])->read(['suppliership_account_id'])->first();
                            $debit_account_id = $suppliership['suppliership_account_id'];
                            $credit_account_id = $payment['funding_id']['bank_account_id'];
                            break;
                        case 'fund_request':
                            // payment from the owner(ship)
                            $ownership = Ownership::id($payment['funding_id']['ownership_id'])->read(['ownership_account_id'])->first();
                            $debit_account_id = $bankAccount['accounting_account_id'];
                            $credit_account_id = $ownership['ownership_account_id'];
                            break;
                        case 'expense_statement':
                            // payment from the owner(ship)
                            $ownership = Ownership::id($payment['funding_id']['ownership_id'])->read(['ownership_account_id'])->first();
                            $debit_account_id = $bankAccount['accounting_account_id'];
                            $credit_account_id = $ownership['ownership_account_id'];
                            break;
                        default:
                            throw new \Exception('invalid_funding_type', EQ_ERROR_INVALID_PARAM);
                    }

                    // debit line
                    AccountingEntryLine::create([
                                'account_id'            => $debit_account_id,
                                'debit'                 => abs(round($payment['amount'], 2)),
                                'credit'                => 0.0,
                                'accounting_entry_id'   => $accountingEntry['id']
                            ]);

                    // credit line
                    AccountingEntryLine::create([
                                'account_id'            => $credit_account_id,
                                'debit'                 => 0.0,
                                'credit'                => abs(round($payment['amount'], 2)),
                                'accounting_entry_id'   => $accountingEntry['id']
                            ]);

                }
                // accounting entry without Funding
                else {
                    $accountingEntry = AccountingEntry::create([
                            'condo_id'              => $payment['condo_id'],
                            'entry_date'            => $payment['receipt_date'],
                            'origin_object_class'   => self::getType(),
                            'origin_object_id'      => $id,
                            'journal_id'            => $payment['journal_id'],
                            'fiscal_year_id'        => $payment['fiscal_year_id'],
                            'fiscal_period_id'      => $payment['fiscal_period_id']
                        ])
                        ->first();

                    if($payment['amount'] > 0) {
                        // debit the bank account
                        AccountingEntryLine::create([
                                'account_id'            => $bankAccount['id'],
                                'debit'                 => abs(round($payment['amount'], 2)),
                                'credit'                => 0.0,
                                'accounting_entry_id'   => $accountingEntry['id']
                            ]);

                        AccountingEntryLine::create([
                                'account_id'            => $payment['accounting_account_id'],
                                'debit'                 => 0.0,
                                'credit'                => abs(round($payment['amount'], 2)),
                                'accounting_entry_id'   => $accountingEntry['id']
                            ]);
                    }
                    else {
                        // credit the bank account
                        AccountingEntryLine::create([
                                'account_id'            => $bankAccount['id'],
                                'debit'                 => 0.0,
                                'credit'                => abs(round($payment['amount'], 2)),
                                'accounting_entry_id'   => $accountingEntry['id']
                            ]);

                        AccountingEntryLine::create([
                                'account_id'            => $payment['accounting_account_id'],
                                'debit'                 => abs(round($payment['amount'], 2)),
                                'credit'                => 0.0,
                                'accounting_entry_id'   => $accountingEntry['id']
                            ]);
                    }
                }
            }
            catch(\Exception $e) {
                trigger_error("APP::onafterPost: Error while creating accounting entries for payment #{$id} : " . $e->getMessage(), EQ_REPORT_ERROR);
            }
        }
    }
}

<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace finance\accounting;
use equal\orm\Model;

class AccountingEntryLine extends Model {

    public static function getName() {
        return "Accounting entry line";
    }

    public static function getDescription() {
        return "Accounting entries lines map invoices lines into records of financial transactions in the accounting books.";
    }

    public static function getColumns() {
        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the accounting entry line refers to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'readonly'          => true,
                'default'           => 'defaultCondoId'
            ],

            'name' => [
                'type'              => 'string',
                'description'       => 'Label for identifying the entry.',
            ],

            'accounting_entry_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\AccountingEntry',
                'description'       => "Accounting entry the line relates to.",
                'ondelete'          => 'cascade',
                'dependents'        => ['journal_id']
            ],

            'account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Account',
                'description'       => "Accounting account the entry relates to.",
                'required'          => true,
                'ondelete'          => 'null',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['is_control_account', '=', false]],
                'dependents'        => ['account_code']
            ],

            'account_code' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "Code of the related accounting account.",
                'relation'          => ['account_id' => 'code'],
                'store'             => true,
                'readonly'          => true
            ],

            'journal_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'finance\accounting\Journal',
                'description'       => "Accounting journal the entry relates to.",
                'relation'          => ['accounting_entry_id' => 'journal_id'],
                'store'             => true,
                'readonly'          => true
            ],

            'fiscal_year_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'finance\accounting\FiscalYear',
                'description'       => "Fiscal year the entry relates to.",
                'relation'          => ['accounting_entry_id' => 'fiscal_year_id'],
                'store'             => true,
                'readonly'          => true
            ],

            'fiscal_period_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'finance\accounting\FiscalPeriod',
                'description'       => "Period of the fiscal year the entry relates to (from entry_date).",
                'relation'          => ['accounting_entry_id' => 'fiscal_period_id'],
                'store'             => true,
                'readonly'          => true
            ],

            'debit' => [
                'type'              => 'float',
                'usage'             => 'amount/money:4',
                'description'       => 'Amount to be debited on the account.',
                'default'           => 0.0,
                'dependents'        => ['accounting_entry_id' => 'debit']
            ],

            'credit' => [
                'type'              => 'float',
                'usage'             => 'amount/money:4',
                'description'       => 'Amount to be credited on the account.',
                'default'           => 0.0,
                'dependents'        => ['accounting_entry_id' => 'credit']
            ],

            'has_invoice_line' => [
                'type'              => 'boolean',
                'description'       => "Is the accounting entry line linked to an invoice line ?",
                'default'           => false
            ],

        ];
    }

    public static function defaultCondoId($values) {
        $result = null;
        if(isset($values['accounting_entry_id'])) {
            $accountingEntry = AccountingEntry::id($values['accounting_entry_id'])->read(['condo_id'])->first();
            if($accountingEntry) {
                $result = $accountingEntry['condo_id'];
            }
        }
        return $result;
    }
}
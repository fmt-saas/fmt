<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
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
                'description'       => 'Label for identifying the entry line.',
            ],

            'accounting_entry_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\AccountingEntry',
                'description'       => "Accounting entry the line relates to.",
                'ondelete'          => 'cascade',
                'dependents'        => ['journal_id', 'entry_date', 'entry_number', 'fiscal_year_id', 'fiscal_period_id']
            ],

            'journal_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'finance\accounting\Journal',
                'description'       => "Accounting journal the entry relates to.",
                'relation'          => ['accounting_entry_id' => 'journal_id'],
                'store'             => true,
                'instant'           => true,
                'readonly'          => true
            ],

            'fiscal_year_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'finance\accounting\FiscalYear',
                'description'       => "Fiscal year the entry relates to.",
                'relation'          => ['accounting_entry_id' => 'fiscal_year_id'],
                'store'             => true,
                'instant'           => true,
                'readonly'          => true
            ],

            'fiscal_period_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'finance\accounting\FiscalPeriod',
                'description'       => "Period of the fiscal year the entry relates to (from entry_date).",
                'relation'          => ['accounting_entry_id' => 'fiscal_period_id'],
                'store'             => true,
                'instant'           => true,
                'readonly'          => true
            ],

            'entry_date' => [
                'type'              => 'computed',
                'result_type'       => 'date',
                'usage'             => 'date/plain',
                'relation'          => ['accounting_entry_id' => 'entry_date'],
                'description'       => 'The date on which the transaction is recorded in the accounting system and affects the fiscal period.',
                'store'             => true,
                'instant'           => true
            ],

            'entry_number' => [
                'type'              => 'computed',
                'result_type'       => 'date',
                'usage'             => 'date/plain',
                'relation'          => ['accounting_entry_id' => 'entry_date'],
                'description'       => 'The date on which the transaction is recorded in the accounting system and affects the fiscal period.',
                'store'             => true,
                'instant'           => true
            ],

            'matching_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Matching',
                'ondelete'          => null,
                'description'       => 'Matching (lettering) to which the accounting entry is linked, if any.',
                'onupdate'          => 'onupdateMatchingId',
                'dependents'        => ['matching_level']
            ],

            'matching_level' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'usage'             => 'icon',
                'selection'         => [
                    'none',
                    'part',
                    'full'
                ],
                'function'          => 'calcMatchingLevel',
                'store'             => true
            ],

            'account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Account',
                'description'       => "Accounting account the entry relates to.",
                'required'          => true,
                'ondelete'          => 'null',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null], ['is_control_account', '=', false]],
                'dependents'        => ['account_code']
            ],

            'account_code' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "Code of the related accounting account.",
                'relation'          => ['account_id' => 'code'],
                'store'             => true,
                'instant'           => true,
                'readonly'          => true
            ],

            'debit' => [
                'type'              => 'float',
                'usage'             => 'amount/money:4',
                'description'       => 'Amount to be debited on the account.',
                'default'           => 0.0,
                'dependents'        => ['accounting_entry_id' => 'debit'],
                'onupdate'          => 'onupdateDebit'
            ],

            'credit' => [
                'type'              => 'float',
                'usage'             => 'amount/money:4',
                'description'       => 'Amount to be credited on the account.',
                'default'           => 0.0,
                'dependents'        => ['accounting_entry_id' => 'credit'],
                'onupdate'          => 'onupdateCredit'
            ],

            'bank_statement_line_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\bank\BankStatementLine',
                'description'       => 'Bank Statement line the entry line relates to, if any.',
                'readonly'          => true,
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

        ];
    }

    public static function getActions() {
        return [
            'attempt_match' => [
                'description'   => 'Attempts to find a suitable Matching and, if so, links the entry to it.',
                'policies'      => [/* */],
                'function'      => 'doAttemptMatch'
            ],
            'refresh_matching_level' => [
                'description'   => 'Update status according to currently paid amount.',
                'policies'      => [],
                'function'      => 'doRefreshMatchingLevel'
            ]
        ];
    }


    protected static function doAttemptMatch($self) {
        $self->read(['condo_id', 'matching_id', 'account_id' => ['account_type'], 'debit', 'credit']);
        foreach($self as $id => $accountingEntryLine) {
            // skip records that are already matched (matching cancellation must be done manually)
            if($accountingEntryLine['matching_id']) {
                continue;
            }
            if($accountingEntryLine['account_id']['account_type'] === 'B') {
                // If a Matching exists on this account and the delta matches the amount of the line, then assign the entry to this Matching
                $amount = round($accountingEntryLine['debit'] - $accountingEntryLine['crebit'], 2);
                $matching = Matching::search([
                        ['condo_id', '=', $accountingEntryLine['condo_id']],
                        ['balance_amount', '=', $amount],
                        ['is_balanced', '=', false]
                    ],
                    [
                        'sort' => ['created' => 'desc']
                    ])
                    ->first();
                if($matching) {
                    self::id($id)->update(['matching_id' => $matching['id']]);
                }
            }
        }
    }


    protected static function doRefreshMatchingLevel($self) {
        $self->update(['matching_level' => null]);
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

    public static function canupdate($self, $values) {
        $self->read(['accounting_entry_id' => ['status']]);
        $allowed_fields = ['matching_id'];
        $updated_fields = array_keys($values);

        if(count(array_diff($updated_fields, $allowed_fields)) > 0) {
            foreach($self as $id => $accountingEntryLine) {
                if($accountingEntryLine['accounting_entry_id']['status'] == 'validated') {
                    return ['accounting_entry_id' => ['not_allowed' => 'Accounting entry cannot be modified once validated.']];
                }
            }
        }
        return parent::canupdate($self);
    }

    protected static function onupdateCredit($self) {
        $self->read(['matching_id']);
        foreach($self as $id => $accountingEntryLine) {
            if($accountingEntryLine['matching_id']) {
                Matching::id($accountingEntryLine['matching_id'])->do('refresh_matching_level');
            }
        }
    }

    protected static function onupdateDebit($self) {
        $self->read(['matching_id']);
        foreach($self as $id => $accountingEntryLine) {
            if($accountingEntryLine['matching_id']) {
                Matching::id($accountingEntryLine['matching_id'])->do('refresh_matching_level');
            }
        }
    }

    protected static function onupdateMatchingId($self) {
        $self->read(['matching_id']);
        foreach($self as $id => $accountingEntryLine) {
            if($accountingEntryLine['matching_id']) {
                Matching::id($accountingEntryLine['matching_id'])->do('refresh_matching_level');
            }
            else {
                // matching_id just reset to null
                self::id($id)->do('refresh_matching_level');
            }
        }
    }

    protected static function calcMatchingLevel($self) {
        $result = [];
        $self->read(['accounting_entry_id' => ['status'], 'matching_id' => ['is_balanced']]);
        foreach($self as $id => $accountingEntryLine) {
            if($accountingEntryLine['accounting_entry_id'] !== 'validated') {
                continue;
            }
            $result[$id] = 'none';
            if(!$accountingEntryLine['$matching_id']) {
                continue;
            }
            if($accountingEntryLine['matching_id']) {
                $result[$id] = 'part';
                if($accountingEntryLine['matching_id']['is_balanced']) {
                    $result[$id] = 'full';
                }
            }
        }
        return $result;
    }
}
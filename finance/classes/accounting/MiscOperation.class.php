<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace finance\accounting;
use equal\orm\Model;

class MiscOperation extends Model {

    public static function getName() {
        return "Miscellaneous Operation";
    }

    public static function getDescription() {
        return "This class represents miscellaneous accounting operation. It provides functionalities for creating misc journal entries that are not classified under standard categories.";
    }

    public static function getColumns() {
        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the accounting journal refers to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'readonly'          => true
            ],

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcName',
                'store'             => true,
                'description'       => 'Title or summary label of the miscellaneous operation.',
            ],

            'description' => [
                'type'              => 'string',
                'description'       => 'Explanation or internal notes about the operation.',
            ],

            'organisation_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Organisation',
                'description'       => "The organisation the chart belongs to.",
                'default'           => 1
            ],

            'fiscal_year_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\FiscalYear',
                'description'       => 'Fiscal year in which the operation is recorded.',
                'required'          => true,
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'posting_date' => [
                'type'              => 'date',
                'description'       => 'Date the operation is posted in the accounting system.',
                'default'           => function () { return time(); },
                'dependents'        => ['fiscal_period_id']
            ],

            'fiscal_period_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'finance\accounting\FiscalPeriod',
                'description'       => 'Accounting period derived from the posting date.',
                'help'              => 'Automatically computed when the operation is validated.',
                'function'          => 'calcFiscalPeriodId',
                'store'             => true,
                'instant'           => true
            ],

            'journal_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Journal',
                'description'       => 'Accounting journal used for this miscellaneous operation.',
                'required'          => true,
                'domain'            => [['journal_type', '=', 'MISC'], ['condo_id', '=', 'object.condo_id']]
            ],

            'accounting_entry_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\AccountingEntry',
                'description'       => "Accounting entry of the invoice.",
                'domain'            => [['origin_object_class', '=', 'finance\accounting\MiscOperation'], ['origin_object_id', '=', 'object.id']]
            ],

            'misc_operation_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\accounting\MiscOperationLine',
                'foreign_field'     => 'misc_operation_id',
                'domain'            => ['condo_id', '=', 'object.condo_id'],
                'description'       => 'Accounting entries relating to the lines of the invoice.',
                'ondetach'          => 'delete'
            ],

            'document_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\Document',
                'description'       => 'Supporting document attached to the operation, if any.',
            ],

            'funding_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\pay\Funding',
                'description'       => 'The funding related to the misc operation, if any.'
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'proforma',
                    'misc_operation'
                ],
                'default'           => 'proforma',
                'description'       => 'Current status of the operation.',
            ],

        ];
    }

    public static function getActions() {
        return [
            'generate_accounting_entry' => [
                'description'   => 'Creates accounting entries according to operation lines.',
                'policies'      => [/* 'can_generate_accounting_entry' */],
                'function'      => 'doGenerateAccountingEntry'
            ],
            'validate_accounting_entry' => [
                'description'   => 'Validate accounting entry (that should be pending) to be accounted in balance',
                'policies'      => [/* 'can_validate_accounting_entry' */],
                'function'      => 'doValidateAccountingEntry'
            ]
        ];
    }

    public static function calcName($self) {
        $result = [];
        $self->read(['description', 'journal_id' => ['code']]);
        foreach($self as $id => $operation) {
            if($operation['journal_id']) {
                $result[$id] = sprintf("%05d - %s - %s", $id, $operation['journal_id']['code'], $operation['description']);
            }
        }
        return $result;
    }

    public static function doValidateAccountingEntry($self) {
        $self->read(['accounting_entry_id' => ['status']]);
        foreach($self as $id => $miscOperation) {
            if($miscOperation['accounting_entry_id']['status'] == 'pending') {
                AccountingEntry::id($miscOperation['accounting_entry_id']['id'])->transition('validate');
            }
        }
    }

    public static function doGenerateAccountingEntry($self) {
        $self->read(['misc_operation_lines_ids' => ['account_id', 'debit', 'credit']]);
        foreach($self as $id => $miscOperation) {

        }
    }

}

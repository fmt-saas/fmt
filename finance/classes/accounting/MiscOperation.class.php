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
                'required'          => true
            ],

            'organisation_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Organisation',
                'description'       => "The organisation the chart belongs to.",
                'default'           => 1
            ],

            'operation_type' => [
                'type'              => 'string',
                'selection'         => [
                    'misc',
                    'transfer'
                ],
                'default'           => 'misc',
                'description'       => "Type of operation, necessary for entities inheriting from MiscOperation."
            ],

            'posting_date' => [
                'type'              => 'date',
                'description'       => 'Date the operation is posted in the accounting system.',
                'default'           => function () { return time(); }
            ],

            'fiscal_year_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\FiscalYear',
                'description'       => 'Fiscal year in which the operation is recorded.',
                'domain'            => [ ['condo_id', '=', 'object.condo_id'], ['status', 'in', ['preopen','open']] ]
            ],

            'fiscal_period_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\FiscalPeriod',
                'description'       => 'Accounting period derived from the posting date.',
                'help'              => 'Automatically computed when the operation is validated.',
                'domain'            => [ ['condo_id', '=', 'object.condo_id'], ['status', '<>', 'closed'] ]
            ],

            'journal_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Journal',
                'description'       => 'Accounting journal used for this miscellaneous operation.',
                'default'           => 'defaultJournalId',
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
                    'draft',
                    'proforma',
                    'posted'
                ],
                'default'           => 'draft',
                'description'       => 'Current status of the operation.',
            ],

        ];
    }

    public static function getWorkflow() {
        return [
            'draft' => [
                'description' => 'Miscellaneous operation being created.',
                'icon'        => 'draw',
                'transitions' => [
                    'publish' => [
                        'description' => 'Update the document to `proforma`.',
                        'help'        => 'Entities inheriting from MiscOperation may create Fundings at this stage.',
                        'status'      => 'proforma'
                    ]
                ]
            ],
            'proforma' => [
                'description' => 'Ready for review. Not posted yet to the accounting system.',
                'icon'        => 'hourglass_top',
                'transitions' => [
                    'post' => [
                        'description' => 'Create accounting entries and update the document to `posted`.',
                        'policies'    => ['is_valid'],
                        'onafter'     => 'onafterPost',
                        'status'      => 'posted'
                    ]
                ]
            ],
            'posted' => [
                'description' => 'The Miscellaneous Operation is posted to the accounting system.',
                'icon' => 'receipt_long',
                'transitions' => [
                    'cancel' => [
                        'description' => '',
                        'status' => 'cancelled',
                    ]
                ],
            ],
        ];
    }

    public static function getPolicies(): array {
        return [
            'is_valid' => [
                'description' => 'Verifies that the state of the Money Transfer allows validation.',
                'function'    => 'policyIsValid'
            ]
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

    public static function defaultJournalId($values) {
        $result = null;
        if(isset($values['condo_id'])) {
            $journal = Journal::search([['condo_id', '=', $values['condo_id']], ['journal_type', '=', 'MISC']])->first();
            if($journal) {
                $result = $journal['id'];
            }
        }
        return $result;

    }

    protected static function policyIsValid($self): array {
        $result = [];
        $self->read(['status', 'posting_date', 'condo_id', 'fiscal_year_id', 'fiscal_period_id', 'journal_id']);
        foreach($self as $id => $miscOperation) {
            if($miscOperation['posting_date'] >= strtotime('tomorrow midnight')) {
                $result[$id] = [
                    'invalid_posting_date' => 'Posting date cannot be in the future.'
                ];
            }
            if(!isset($miscOperation['fiscal_year_id'])) {
                $result[$id] = [
                    'missing_fiscal_year' => 'Fiscal year is missing.'
                ];
            }
            if(!isset($miscOperation['fiscal_period_id'])) {
                $result[$id] = [
                    'missing_fiscal_period' => 'Fiscal period is missing.'
                ];
            }
            if(!isset($miscOperation['journal_id'])) {
                $result[$id] = [
                    'missing_journal' => 'Accounting journal is missing.'
                ];
            }
            if(!isset($miscOperation['condo_id'])) {
                $result[$id] = [
                    'missing_condominium' => 'The target condominium must be specified.'
                ];
            }
        }
        return $result;
    }

    protected static function calcName($self) {
        $result = [];
        $self->read(['description', 'journal_id' => ['code']]);
        foreach($self as $id => $operation) {
            if($operation['journal_id']) {
                $result[$id] = sprintf("%05d - %s - %s", $id, $operation['journal_id']['code'], $operation['description']);
            }
        }
        return $result;
    }

    protected static function doValidateAccountingEntry($self) {
        $self->read(['accounting_entry_id' => ['status']]);
        foreach($self as $id => $miscOperation) {
            if($miscOperation['accounting_entry_id']['status'] == 'pending') {
                AccountingEntry::id($miscOperation['accounting_entry_id']['id'])->transition('validate');
            }
        }
    }

    protected static function doGenerateAccountingEntry($self) {
        $self->read([
                'condo_id', 'posting_date', 'journal_id', 'fiscal_year_id', 'fiscal_period_id',
                'misc_operation_lines_ids' => ['account_id', 'debit', 'credit']
            ]);
        foreach ($self as $id => $miscOperation) {

            // remove any previously created accounting entry (resulting from an incomplete operation)
            AccountingEntry::search([
                    ['condo_id', '=', $miscOperation['condo_id']],
                    ['origin_object_class', '=', self::getType()],
                    ['origin_object_id', '=', $id]
                ])
                ->delete(true);

            $accountingEntry = AccountingEntry::create([
                    'condo_id'              => $miscOperation['condo_id'],
                    'entry_date'            => $miscOperation['posting_date'],
                    'origin_object_class'   => self::getType(),
                    'origin_object_id'      => $id,
                    'journal_id'            => $miscOperation['journal_id'],
                    'fiscal_year_id'        => $miscOperation['fiscal_year_id'],
                    'fiscal_period_id'      => $miscOperation['fiscal_period_id']
                ])
                ->first();

            foreach($miscOperation['misc_operation_lines_ids'] as $line) {
                AccountingEntryLine::create([
                        'account_id'            => $line['account_id'],
                        'debit'                 => $line['debit'],
                        'credit'                => $line['credit'],
                        'accounting_entry_id'   => $accountingEntry['id']
                    ]);
            }

            // Store the created accounting entry ID back to the misc operation
            self::id($id)->update(['accounting_entry_id' => $accountingEntry['id']]);
        }
    }

    protected static function onafterPost($self) {
        $self
            ->do('generate_accounting_entry')
            ->do('validate_accounting_entry');
    }

    public static function onchange($event, $values) {
        $result = [];

        if(isset($event['condo_id'])) {
            $journal = Journal::search([['condo_id', '=', $event['condo_id']], ['journal_type', '=', 'MISC']])->read(['id', 'name'])->first();
            if($journal) {
                $result['journal_id'] = [
                        'id'    => $journal['id'],
                        'name'  => $journal['name']
                    ];
            }
            if(!isset($event['posting_date']) && isset($values['posting_date'])) {
                $event['posting_date'] = $values['posting_date'];
            }
            $values['condo_id'] = $event['condo_id'];
        }

        if(isset($event['posting_date'])) {
            $fiscalYear = FiscalYear::search([
                    ['condo_id', '=', $values['condo_id']],
                    ['date_from', '<=', $event['posting_date']],
                    ['date_to', '>=', $event['posting_date']]
                ])
                ->read(['id', 'name'])
                ->first();

            $fiscalPeriod = FiscalPeriod::search([
                    ['condo_id', '=', $values['condo_id']],
                    ['date_from', '<=', $event['posting_date']],
                    ['date_to', '>=', $event['posting_date']]
                ])
                ->read(['id', 'name'])
                ->first();

            if($fiscalYear) {
                $result['fiscal_year_id'] = [
                        'id'    => $fiscalYear['id'],
                        'name'  => $fiscalYear['name']
                    ];
            }

            if($fiscalPeriod) {
                $result['fiscal_period_id'] = [
                        'id'    => $fiscalPeriod['id'],
                        'name'  => $fiscalPeriod['name']
                    ];
            }
        }

        return $result;
    }

}

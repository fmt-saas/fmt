<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace finance\accounting;
use equal\orm\Model;
use finance\bank\CondominiumBankAccount;
use finance\bank\SuppliershipBankAccount;
use realestate\sale\pay\Funding;
use realestate\finance\accounting\AccountingEntry;
use realestate\finance\accounting\AccountingEntryLine;
use realestate\sale\pay\FundingAllocation;
use finance\bank\BankStatementLine;
use fmt\setting\Setting;
use sale\pay\Payment;

// #memo - This class models a generic Accounting Operation. It is a true MiscOperation only if journal is MISC
class MiscOperation extends Model {

    // #memo - for backward compatibility
    public function getTable() {
        return 'finance_accounting_miscoperation';
    }

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
                'readonly'          => true,
                'required'          => true,
                'dependents'        => ['fiscal_year_id', 'fiscal_period_id']
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
                'required'          => true,
                'onupdate'          => 'onupdateDescription'
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
                    'transfer',
                    'refund'
                ],
                'default'           => 'misc',
                'description'       => "Type of operation, necessary for entities inheriting from MiscOperation."
            ],

            'payment_status' => [
                'type'              => 'string',
                'selection'         => [
                    'debit_balance',    // movement is still incomplete
                    'credit_balance',   // reverse movement (reimbursement) is required
                    'balanced'          // movement fully performed
                ],
                'visible'           => ['status', '=', 'posted'],
                'default'           => 'pending'
            ],

            'posting_date' => [
                'type'              => 'date',
                'usage'             => 'date/plain',
                'description'       => 'Date the operation is posted in the accounting system.',
                'dependents'        => ['fiscal_year_id', 'fiscal_period_id'],
                'default'           => function () { return time(); }
            ],

            'has_date_range' => [
                'type'              => 'boolean',
                'description'       => 'Apply expense/income on a date range.',
                'help'              => '',
                'default'           => false
            ],

            'date_from' => [
                'type'              => 'date',
                'usage'             => 'date/plain',
                'description'       => 'First date of the date range.',
                'default'           => function () { return time(); },
                'visible'           => ['has_date_range', '=', true]
            ],

            'date_to' => [
                'type'              => 'date',
                'usage'             => 'date/plain',
                'description'       => 'Last date of the date range.',
                'default'           => function () { return time(); },
                'visible'           => ['has_date_range', '=', true]
            ],

            'fiscal_year_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'finance\accounting\FiscalYear',
                'description'       => 'Fiscal year in which the operation is recorded.',
                'function'          => 'calcFiscalYearId',
                'store'             => true,
                'instant'           => true,
                'readonly'          => true,
                'domain'            => [ ['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null], ['status', 'in', ['preopen','open']] ]
            ],

            'fiscal_period_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'finance\accounting\FiscalPeriod',
                'description'       => 'Accounting period derived from the posting date.',
                'help'              => 'Automatically computed when the operation is validated.',
                'function'          => 'calcFiscalPeriodId',
                'store'             => true,
                'instant'           => true,
                'readonly'          => true,
                'domain'            => [ ['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null], ['status', '<>', 'closed'] ]
            ],

            'operation_number' => [
                'type'              => 'string',
                'description'       => 'Number of the misc operation, according to organization logic.',
                'dependents'        => ['name']
            ],

            /**
             * // #memo - behavior has been changed
             * journal_id is no longer used for "opening journal" Misc, since these are handled with auto creation of an opening balance
            */
            'journal_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Journal',
                'description'       => 'Accounting journal used for this miscellaneous operation.',
                'domain'            => [
                        ['condo_id', '=', 'object.condo_id'],
                        ['condo_id', '<>', null],
                        ['journal_type', '=', 'MISC']
                    ]
            ],

            'accounting_entry_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\AccountingEntry',
                'description'       => "Accounting entry of the MiscOperation.",
                'domain'            => [['origin_object_class', '=', 'finance\accounting\MiscOperation'], ['origin_object_id', '=', 'object.id']]
            ],

            'accounting_entries_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\accounting\AccountingEntry',
                'foreign_field'     => 'origin_object_id',
                'domain'            => ['origin_object_class', '=', 'finance\accounting\MiscOperation'],
                'description'       => 'Accounting entries relating to the Misc Operation.',
                'help'              => "Misc Operations might be subject to several accounting entries (in case of reversal or correction)."
            ],

            'fundings_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\sale\pay\Funding',
                'foreign_field'     => 'misc_operation_id',
                'domain'            => ['funding_type', '=', 'misc_operation'],
                'description'       => 'Fundings created from the misc operation.'
            ],

            'opening_balance_id' => [
                'type'              => 'many2one',
                'description'       => "The opening balance of the fiscal year.",
                'foreign_object'    => 'finance\accounting\OpeningBalance',
                'ondelete'          => 'null'
            ],

            'has_opening_journal' => [
                'type'              => 'boolean',
                'default'           => false,
                'description'       => 'Accounting journal used for this miscellaneous operation.'
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

            'documents_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'documents\Document',
                'foreign_field'     => 'misc_operation_id',
                'description'       => 'All documents linked to the misc operation.',
            ],

            'purchase_invoice_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'purchase\accounting\invoice\PurchaseInvoice',
                'description'       => 'Invoice the accounting entry is related to.',
                'ondelete'          => 'null',
                'help'              => 'In case the Misc Operation relates to a purchaseInvoiceLine marked with instant re-invoicing.'
            ],

            'is_balanced' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'An entry is balanced if the total debited amount equals the total credited amount.',
                'function'          => 'calcIsBalanced'
            ],

            'ownership_transfer_settlements_ids' => [
                'type'            => 'many2many',
                'foreign_object'  => 'realestate\property\transfer\OwnershipTransferSettlementOperation',
                'foreign_field'   => 'misc_operations_ids',
                'rel_table'       => 'realestate_property_transfer_settlement_operation',
                'rel_foreign_key' => 'settlement_id',
                'rel_local_key'   => 'misc_operation_id',
                'description'     => 'Miscellaneous operations generated by the settlement.',
                'domain'          => ['condo_id', '=', 'object.condo_id'],
                'readonly'        => true
            ],

            'logs' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => 'Logs of accounting entry and fundings generation.'
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'pending',
                    'proforma',
                    'posted',
                    'cancelled'
                ],
                'default'           => 'pending',
                'description'       => 'Current status of the operation.',
            ],

        ];
    }

    public static function getWorkflow() {
        return [
            'pending' => [
                'description' => 'Miscellaneous operation being created.',
                'icon'        => 'draw',
                'transitions' => [
                    'publish' => [
                        'description' => 'Update the document to `proforma`.',
                        'help'        => 'Entities inheriting from MiscOperation may create Fundings at this stage.',
                        'status'      => 'proforma',
                        'policies'    => ['is_valid']
                    ]
                ]
            ],
            'proforma' => [
                'description' => 'Ready for review. Not posted yet to the accounting system.',
                'icon'        => 'hourglass_top',
                'transitions' => [
                    'post' => [
                        'description' => 'Create accounting entries and update the document to `posted`.',
                        'policies'    => ['is_valid', 'can_generate_accounting_entry', 'can_generate_opening_balance', 'can_create_fundings'],
                        'onbefore'    => 'onbeforePost',
                        'status'      => 'posted'
                    ],
                    'revert' => [
                        'description' => 'Revert the MiscOperation to `pending`.',
                        'status'      => 'pending'
                    ]
                ]
            ],
            'posted' => [
                'description' => 'The Miscellaneous Operation is posted to the accounting system.',
                'icon' => 'receipt_long',
                'transitions' => [
                    'cancel' => [
                        'description' => 'Set the miscellaneous operation as cancelled.',
                        'policies'    => [],
                        'status' => 'cancelled',
                    ]
                ],
            ],
            'cancelled' => [
                'description' => 'The Miscellaneous Operation is cancelled. There are no transitions available.',
                'icon'        => 'cancel',
                'transitions' => []
            ],
        ];
    }

    public static function getPolicies(): array {
        return [
            'is_valid' => [
                'description' => 'Verifies that the state of the Money Transfer allows validation.',
                'function'    => 'policyIsValid'
            ],
            'can_post' => [
                'description' => 'Verifies that a fiscal year can be opened according its configuration.',
                'function'    => 'policyCanPost'
            ],
            'can_unlock' => [
                'description' => 'Verifies that a fiscal year can be opened according its configuration.',
                'function'    => 'policyCanUnlock'
            ],
            'can_generate_opening_balance' => [
                'description' => 'Verifies that an opening balance can be generated from the misc operation.',
                'function'    => 'policyCanGenerateOpeningBalance'
            ],
            'can_generate_accounting_entry' => [
                'description' => 'Verifies that an accounting entry can be generated from the misc operation.',
                'function'    => 'policyCanGenerateAccountingEntry'
            ],
            'can_create_fundings' => [
                'description' => 'Verifies that fundings can be generated from the misc operation.',
                'function'    => 'policyCanCreateFundings'
            ]
        ];
    }

    public static function getActions() {
        return [
            'generate_opening_balance' => [
                'description'   => 'Creates an opening balance according to operation lines.',
                'policies'      => ['can_generate_opening_balance'],
                'function'      => 'doGenerateOpeningBalance'
            ],
            'generate_accounting_entry' => [
                'description'   => 'Creates accounting entries according to operation lines.',
                'policies'      => ['can_generate_accounting_entry'],
                'function'      => 'doGenerateAccountingEntry'
            ],
            'validate_accounting_entry' => [
                'description'   => 'Validate accounting entry (that should be pending) to be accounted in balance.',
                'policies'      => [/* 'can_validate_accounting_entry' */],
                'function'      => 'doValidateAccountingEntry'
            ],
            'create_fundings' => [
                'description'   => 'Generate fundings for lines related to Ownerships.',
                'policies'      => ['can_create_fundings'],
                'function'      => 'doCreateFundings'
            ],
            'assign_operation_number' => [
                'description'   => 'Assign MiscOp name according to config.',
                'policies'      => [],
                'function'      => 'doAssignOperationNumber'
            ],
            'cancel' => [
                'description'   => 'Cancel the miscellaneous operation. No further change will be possible.',
                'help'          => 'Void the accounting entry and set status to `cancelled`.',
                'policies'      => [],
                'function'      => 'doCancel'
            ],
            'unlock' => [
                'description'   => 'Unlock the miscellaneous operation, to allow re-posting after modifications.',
                'help'          => 'Self voiding accounting entries will be left as `reversed`, and operation will be set back to `proforma`.',
                'policies'      => [ 'can_unlock' ],
                'function'      => 'doUnlock'
            ]
        ];
    }

    private static function computeFundingBankAccount($condo_id) {
        $bankAccount = CondominiumBankAccount::search([
                ['condo_id', '=', $condo_id],
                ['is_primary', '=', true]
            ])
            ->first();

        if($bankAccount) {
            return $bankAccount;
        }

        $current_bank_account_ids = CondominiumBankAccount::search([
                ['condo_id', '=', $condo_id],
                ['bank_account_type', '=', 'bank_current']
            ])
            ->ids();

        if(count($current_bank_account_ids) === 1) {
            return ['id' => reset($current_bank_account_ids)];
        }

        if(count($current_bank_account_ids) === 0) {
            $third_party_bank_account_ids = CondominiumBankAccount::search([
                    ['condo_id', '=', $condo_id],
                    ['bank_account_type', '=', 'bank_tier']
                ])
                ->ids();

            if(count($third_party_bank_account_ids) === 1) {
                return ['id' => reset($third_party_bank_account_ids)];
            }
        }

        return null;
    }

    protected static function policyCanCreateFundings($self): array {
        $result = [];
        $self->read([
                'status',
                'condo_id',
                'accounting_entry_id',
                'fundings_ids' => ['id'],
                'misc_operation_lines_ids' => [
                    'account_id',
                    'is_owner',
                    'is_supplier',
                    'ownership_id',
                    'suppliership_id'
                ]
            ]);

        foreach($self as $id => $miscOperation) {
            if($miscOperation['status'] !== 'proforma') {
                $result[$id] = [
                    'invalid_status' => 'Misc Operation status must be proforma.'
                ];
                continue;
            }

            if(!$miscOperation['condo_id']) {
                $result[$id] = [
                    'missing_condominium' => 'The target condominium must be specified.'
                ];
                continue;
            }

            if(!$miscOperation['accounting_entry_id']) {
                $result[$id] = [
                    'missing_accounting_entry' => 'Accounting entry is missing.'
                ];
                continue;
            }

            /*
            if(count($miscOperation['fundings_ids']) > 0) {
                $result[$id] = [
                    'existing_fundings' => 'Fundings have already been generated for this misc operation.'
                ];
                continue;
            }
            */

            $condominiumBankAccount = self::computeFundingBankAccount($miscOperation['condo_id']);

            if(!$condominiumBankAccount) {
                $result[$id] = [
                    'missing_bank_account' => 'A primary condominium bank account is required; failing that, exactly one current account, or exactly one third-party account when no current account exists.'
                ];
                continue;
            }

            foreach($miscOperation['misc_operation_lines_ids'] as $misc_operation_line_id => $miscOperationLine) {
                if(!$miscOperationLine['is_owner'] && !$miscOperationLine['is_supplier']) {
                    continue;
                }

                $accountingEntryLine = AccountingEntryLine::search([
                        ['condo_id', '=', $miscOperation['condo_id']],
                        ['misc_operation_line_id', '=', $misc_operation_line_id]
                    ])
                    ->first();

                if(!$accountingEntryLine) {
                    $result[$id] = [
                        'missing_accounting_entry_line' => 'Accounting entry line is missing for one or more MiscOperation lines.'
                    ];
                    break;
                }

                if($miscOperationLine['is_owner']) {
                    if(!$miscOperationLine['ownership_id']) {
                        $result[$id] = [
                            'missing_ownership_id' => 'Ownership is missing for one or more owner funding lines.'
                        ];
                        break;
                    }

                    $fundingOwnershipAccount = Account::search([
                            ['condo_id', '=', $miscOperation['condo_id']],
                            ['ownership_id', '=', $miscOperationLine['ownership_id']],
                            ['is_control_account', '=', true]
                        ])
                        ->first();

                    if(!$fundingOwnershipAccount) {
                        $result[$id] = [
                            'missing_ownership_accounting_account' => 'Ownership accounting account is missing for one or more owner funding lines.'
                        ];
                        break;
                    }
                }
                elseif($miscOperationLine['is_supplier'] && !$miscOperationLine['suppliership_id']) {
                    $result[$id] = [
                        'missing_suppliership_id' => 'Suppliership is missing for one or more supplier funding lines.'
                    ];
                    break;
                }
            }
        }
        return $result;
    }

    protected static function policyCanPost($self): array {
        $result = [];
        $self->read([
                'status', 'posting_date',
                'fiscal_period_id' => ['status', 'fiscal_year_status']
            ]);

        foreach($self as $id => $requestExecution) {
            if($requestExecution['status'] != 'proforma') {
                $result[$id] = [
                    'invalid_status' => 'Misc Operation status must be proforma.'
                ];
                continue;
            }
            if(!$requestExecution['fiscal_period_id']) {
                $result[$id] = [
                    'missing_fiscal_period' => 'Fiscal period is mandatory (could not resolve).'
                ];
                continue;
            }

            if(!in_array($requestExecution['fiscal_period_id']['status'], ['open', 'preclosed'], true)) {
                $result[$id] = [
                    'invalid_fiscal_period' => 'Cannot perform fund request on a non-open fiscal period.'
                ];
                continue;
            }

            if(!in_array($requestExecution['fiscal_period_id']['fiscal_year_status'], ['preopen', 'open', 'preclosed'], true)) {
                $result[$id] = [
                    'invalid_fiscal_year' => 'Cannot perform Misc Operation on a non-open fiscal year.'
                ];
                continue;
            }

        }
        return $result;
    }

    protected static function policyCanUnlock($self): array {
        $result = [];
        $self->read([
                'status', 'posting_date',
                'fiscal_period_id' => ['status', 'fiscal_year_status']
            ]);

        foreach($self as $id => $miscOperation) {
            if($miscOperation['status'] != 'posted') {
                $result[$id] = [
                    'invalid_status' => 'Misc Operation status must be posted.'
                ];
                continue;
            }

            if(!in_array($miscOperation['fiscal_period_id']['status'], ['open', 'preclosed'], true)) {
                $result[$id] = [
                    'invalid_fiscal_period' => 'Cannot perform fund request on a non-open fiscal period.'
                ];
                continue;
            }

            if(!in_array($miscOperation['fiscal_period_id']['fiscal_year_status'], ['preopen', 'open', 'preclosed'], true)) {
                $result[$id] = [
                    'invalid_fiscal_year' => 'Cannot perform Misc Operation on a non-open fiscal year.'
                ];
                continue;
            }

        }
        return $result;
    }

    protected static function doCancel($self) {
        $self
            ->do('unlock')
            ->update([
                'status'                => 'cancelled',
                'accounting_entry_id'   => null
            ]);
    }

    protected static function doUnlock($self) {
        $self->read(['condo_id', 'accounting_entry_id', 'has_opening_journal', 'opening_balance_id']);

        foreach($self as $id => $miscOperation) {

            if($miscOperation['has_opening_journal'] && $miscOperation['opening_balance_id']) {
                OpeningBalance::id($miscOperation['opening_balance_id'])->transition('revert');
            }

            // retrieve accounting entry and cancel it
            AccountingEntry::id($miscOperation['accounting_entry_id'])->do('cancel');

            // remove related fundings (move payments to BankStatementLine Funding if any)
            $fundings = Funding::search([
                    ['condo_id', '=', $miscOperation['condo_id']],
                    ['funding_type', '=', 'misc_operation'],
                    ['misc_operation_id', '=', $id]
                ])
                ->read(['is_sent']);

            foreach($fundings as $funding_id => $funding) {
                if(!$funding['is_sent']) {
                    Funding::id($funding_id)->do('remove');
                }
            }
        }
        $self->update(['status' => 'proforma']);
    }

    private static function computeIsBalanced($misc_operation_lines_ids) {
        $entry_lines = MiscOperationLine::ids($misc_operation_lines_ids)->read(['credit', 'debit']);
        $credit = 0;
        $debit = 0;
        foreach($entry_lines as $line_id => $line) {
            $credit += $line['credit'];
            $debit += $line['debit'];
        }
        return (abs($credit - $debit) < 0.01 && round($credit, 2) != 0.00);
    }

    protected static function calcIsBalanced($self) {
        $result = [];
        $self->read(['misc_operation_lines_ids']);
        foreach($self as $id => $miscOperation) {
            $result[$id] = self::computeIsBalanced($miscOperation['misc_operation_lines_ids']);
        }
        return $result;
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

    protected static function doGenerateOpeningBalance($self) {
        $self->read([
                'condo_id',
                'posting_date',
                'fiscal_year_id',
                'has_opening_journal',
                'misc_operation_lines_ids'
            ]);

        foreach($self as $id => $miscOperation) {
            if(!$miscOperation['has_opening_journal']) {
                continue;
            }

            $condo_id = $miscOperation['condo_id'];
            $fiscal_year_id = $miscOperation['fiscal_year_id'];

            $existingOpeningBalance = OpeningBalance::search([
                    ['condo_id', '=', $condo_id],
                    ['fiscal_year_id', '=', $fiscal_year_id]
                ])
                ->first();

            if($existingOpeningBalance) {
                OpeningBalance::id($existingOpeningBalance['id'])->delete(true);
            }

            $openingBalance = OpeningBalance::create([
                    'condo_id'          => $condo_id,
                    'fiscal_year_id'    => $fiscal_year_id,
                    'misc_operation_id' => $id
                ])
                ->first();

            FiscalYear::id($fiscal_year_id)
                ->update(['opening_balance_id' => $openingBalance['id']]);

            OpeningBalance::id($openingBalance['id'])->update(['status' => 'validated']);

            self::id($id)->update(['opening_balance_id' => $openingBalance['id']]);
        }
    }

    protected static function doGenerateAccountingEntry($self) {
        $self->read([
                'condo_id', 'posting_date', 'journal_id', 'fiscal_year_id', 'fiscal_period_id',
                'description', 'has_opening_journal',
                'misc_operation_lines_ids' => [
                    'account_id', 'debit', 'credit', 'description',
                    'ownership_id',
                    'property_lot_id'
                ]
            ]);

        foreach ($self as $id => $miscOperation) {

            // #memo - `has_opening_journal` implies a MiscOp for an initial opening balance
            $fiscal_year_id = $miscOperation['fiscal_year_id'];
            $fiscal_period_id = $miscOperation['fiscal_period_id'];

            // #memo - a Misc Operation can be unlocked and therefore have several accounting entries (@see `accounting_entries_ids`)
            /*
            $accountingEntry = AccountingEntry::search([
                    ['condo_id', '=', $miscOperation['condo_id']],
                    ['origin_object_class', '=', self::getType()],
                    ['origin_object_id', '=', $id]
                ])
                ->first();
            */

            $accountingEntry = AccountingEntry::create([
                    'condo_id'              => $miscOperation['condo_id'],
                    'entry_date'            => $miscOperation['posting_date'],
                    'origin_object_class'   => self::getType(),
                    'origin_object_id'      => $id,
                    'misc_operation_id'     => $id,
                    'description'           => $miscOperation['description'],
                    'journal_id'            => $miscOperation['journal_id'],
                    'fiscal_year_id'        => $fiscal_year_id,
                    'fiscal_period_id'      => $fiscal_period_id
                ])
                ->first();

            foreach($miscOperation['misc_operation_lines_ids'] as $line_id => $line) {
                AccountingEntryLine::create([
                        'account_id'            => $line['account_id'],
                        'debit'                 => $line['debit'],
                        'credit'                => $line['credit'],
                        'accounting_entry_id'   => $accountingEntry['id'],
                        'misc_operation_line_id'=> $line_id,
                        'description'           => $line['description']
                    ]);
            }

            // Store the created accounting entry ID back to the misc operation
            self::id($id)->update(['accounting_entry_id' => $accountingEntry['id']]);
        }
    }

    private static function computeFiscalYearId($condo_id, $posting_date) {
        $result = null;

        $fiscalYear = FiscalYear::search([['condo_id', '=', $condo_id], ['date_from', '<=', $posting_date], ['date_to', '>=', $posting_date]])
            ->first();

        if($fiscalYear) {
            $result = $fiscalYear['id'];
        }

        return $result;
    }

    private static function computeFiscalPeriodId($condo_id, $posting_date) {
        $result = null;

        $fiscalYear = FiscalYear::search([['condo_id', '=', $condo_id], ['date_from', '<=', $posting_date], ['date_to', '>=', $posting_date]])
            ->read(['fiscal_periods_ids' => ['date_from', 'date_to']])
            ->first();

        if(!$fiscalYear) {
            return $result;
        }

        foreach($fiscalYear['fiscal_periods_ids'] ?? [] as $period_id => $period) {
            if($posting_date >= $period['date_from'] && $posting_date <= $period['date_to']) {
                $result = $period_id;
                break;
            }
        }

        return $result;
    }

    protected static function calcFiscalYearId($self) {
        $result = [];
        $self->read(['condo_id', 'posting_date']);
        foreach($self as $id => $miscOperation) {
            $result[$id] = self::computeFiscalYearId($miscOperation['condo_id'], $miscOperation['posting_date']);
        }
        return $result;
    }

    protected static function calcFiscalPeriodId($self) {
        $result = [];
        $self->read(['condo_id', 'posting_date']);
        foreach($self as $id => $miscOperation) {
            $result[$id] = self::computeFiscalPeriodId($miscOperation['condo_id'], $miscOperation['posting_date']);
        }
        return $result;
    }

    protected static function onupdateDescription($self, $lang) {
        $self->read(['description', 'misc_operation_lines_ids' => ['description']]);
        foreach($self as $id => $miscOperation) {
            if(!$miscOperation['description'] || strlen($miscOperation['description']) <= 0) {
                continue;
            }
            foreach($miscOperation['misc_operation_lines_ids'] as $misc_operation_line_id => $miscOperationLine) {
                if(!$miscOperationLine['description'] || strlen($miscOperationLine['description']) <= 0) {
                    MiscOperationLine::id($misc_operation_line_id)->update(['description' => $miscOperation['description']], $lang);
                }
            }
        }
    }

    protected static function policyIsValid($self): array {
        $result = [];
        $self->read(['status', 'posting_date', 'condo_id', 'fiscal_year_id', 'fiscal_period_id', 'journal_id', 'misc_operation_lines_ids' => ['debit', 'credit']]);
        foreach($self as $id => $miscOperation) {
            $fiscal_year_id = $miscOperation['fiscal_year_id'];
            $fiscal_period_id = $miscOperation['fiscal_period_id'];

            if($miscOperation['posting_date'] >= strtotime('tomorrow midnight')) {
                $result[$id] = [
                    'invalid_posting_date' => 'Posting date cannot be in the future.'
                ];
            }
            if(!$fiscal_year_id) {
                $result[$id] = [
                    'missing_fiscal_year' => 'Fiscal year is missing.'
                ];
            }
            if(!$fiscal_period_id) {
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

            $journal = Journal::id($miscOperation['journal_id'])->read(['condo_id'])->first();
            if(!isset($miscOperation['condo_id'])) {
                if($journal['condo_id'] !== $miscOperation['condo_id']) {
                    $result[$id] = [
                        'invalid_journal' => 'The target journal does not relate to MiscOperation condominium.'
                    ];
                }
            }

            $credit = 0.0;
            $debit = 0.0;
            foreach($miscOperation['misc_operation_lines_ids'] as $operation_line_id => $operationLine) {
                $credit += $operationLine['credit'];
                $debit  += $operationLine['debit'];
            }
            if(abs($debit - $credit) >= 0.01) {
                $result[$id] = [
                    'non_balanced' => 'The lines of the operation are not balanced.'
                ];
            }
        }
        return $result;
    }

    protected static function policyCanGenerateOpeningBalance($self): array {
        $result = [];
        $self->read(['has_opening_journal', 'condo_id', 'posting_date', 'fiscal_period_id', 'misc_operation_lines_ids' => ['debit', 'credit']]);
        foreach($self as $id => $miscOperation) {
            if(!$miscOperation['has_opening_journal']) {
                continue;
            }

            if(!$miscOperation['condo_id']) {
                $result[$id] = [
                    'missing_condominium' => 'The target condominium must be specified.'
                ];
                continue;
            }

            if(!self::computeIsBalanced($miscOperation['misc_operation_lines_ids'])) {
                $result[$id] = [
                    'non_balanced' => 'The lines of the operation are not balanced.'
                ];
                continue;
            }

            $fiscal_year_id = $miscOperation['fiscal_period_id'];
            if(!$fiscal_year_id) {
                $result[$id] = [
                    'missing_fiscal_year' => 'Fiscal year is missing.'
                ];
                continue;
            }

            $existingOpeningBalance = OpeningBalance::search([
                    ['condo_id', '=', $miscOperation['condo_id']],
                    ['fiscal_year_id', '=', $fiscal_year_id]
                ])
                ->read(['status'])
                ->first();

            if($existingOpeningBalance && $existingOpeningBalance['status'] === 'validated') {
                $result[$id] = [
                    'existing_validated_opening_balance' => 'An opening balance already exists for the fiscal year.'
                ];
            }
        }
        return $result;
    }

    protected static function policyCanGenerateAccountingEntry($self): array {
        $result = [];
        $self->read(['condo_id', 'posting_date', 'fiscal_year_id', 'fiscal_period_id', 'journal_id', 'misc_operation_lines_ids' => ['debit', 'credit']]);
        foreach($self as $id => $miscOperation) {
            if(!$miscOperation['condo_id']) {
                $result[$id] = [
                    'missing_condominium' => 'The target condominium must be specified.'
                ];
                continue;
            }

            if(!isset($miscOperation['journal_id'])) {
                $result[$id] = [
                    'missing_journal' => 'Accounting journal is missing.'
                ];
                continue;
            }

            if(!self::computeIsBalanced($miscOperation['misc_operation_lines_ids'])) {
                $result[$id] = [
                    'non_balanced' => 'The lines of the operation are not balanced.'
                ];
                continue;
            }

            $fiscal_year_id = $miscOperation['fiscal_year_id'];
            if(!$fiscal_year_id) {
                $result[$id] = [
                    'missing_fiscal_year' => 'Fiscal year is missing.'
                ];
                continue;
            }

            $fiscal_period_id = $miscOperation['fiscal_period_id'];
            if(!$fiscal_period_id) {
                $result[$id] = [
                    'missing_fiscal_period' => 'Fiscal period is missing.'
                ];
            }
        }
        return $result;
    }

    protected static function calcName($self) {
        $result = [];
        $self->read(['status', 'operation_number']);
        foreach($self as $id => $miscOperation) {
            if(in_array($miscOperation['status'], ['pending', 'proforma'], true)) {
                $result[$id] = '[proforma]';
            }
            elseif($miscOperation['operation_number']) {
                $result[$id] = $miscOperation['operation_number'];
            }
        }
        return $result;
    }

    protected static function doAssignOperationNumber($self) {
        $self->read([
                'condo_id',
                'operation_number',
                'journal_id'        => ['code'],
                'fiscal_year_id'    => ['code'],
                'fiscal_period_id'  => ['code']
            ]);

        foreach($self as $id => $miscOperation) {
            // #memo - unlocked misc operations are set to status `proforma`, but keep their operation number
            if($miscOperation['operation_number']) {
                continue;
            }

            $format = Setting::get_value(
                    'finance',
                    'accounting',
                    'misc_operation.sequence_format',
                    '%02d{year}/%02d{period}/%05d{sequence}',
                    [
                        'condo_id'          => $miscOperation['condo_id']
                    ]
                );

            $fiscal_year_code = $miscOperation['fiscal_year_id']['code'];
            $fiscal_period_code = $miscOperation['fiscal_period_id']['code'];
            $journal_code = $miscOperation['journal_id']['code'];

            $sequence = Setting::fetch_and_add(
                    'finance',
                    'accounting',
                    "operation.sequence.{$fiscal_year_code}.{$fiscal_period_code}.{$journal_code}",
                    1,
                    [
                        'condo_id'          => $miscOperation['condo_id']
                    ]
                );

            if($sequence) {
                $operation_number = Setting::parse_format($format, [
                        'year'      => substr($miscOperation['fiscal_year_id']['code'] ?? '', 0, 2),
                        'period'    => $miscOperation['fiscal_period_id']['code'] ?? 0,
                        'condo'     => $miscOperation['condo_id'],
                        'sequence'  => $sequence
                    ]);
                self::id($id)->update([
                        'operation_number' => $operation_number,
                        'name'             => null
                    ]);
            }
        }
    }

    protected static function doValidateAccountingEntry($self) {
        $self->read(['has_opening_journal', 'accounting_entry_id' => ['status']]);

        foreach($self as $id => $miscOperation) {

            /*
            // @see comment above : no distinction
            // ignore MiscOperation that relate to an opening balance
            if($miscOperation['has_opening_journal']) {
                continue;
            }
            */

            if($miscOperation['accounting_entry_id']['status'] == 'pending') {
                AccountingEntry::id($miscOperation['accounting_entry_id']['id'])->transition('validate');
            }
        }
    }

    protected static function doCreateFundings($self) {

        /* from finance\accounting\invoice\Invoice: */
        // 'condo_id'
        // 'fiscal_year_id'
        // 'fiscal_period_id'
        // 'accounting_entry_id'
        // 'emission_date'
        // 'due_date'


        $self->read([
                'condo_id',
                'name',
                'description',
                'posting_date',
                'has_opening_journal',
                'fiscal_year_id' => ['date_from'],
                'fiscal_period_id' => ['date_from'],
                'accounting_entry_id',
                'misc_operation_lines_ids' => [
                    'account_id',
                    'is_owner',
                    'is_supplier',
                    'ownership_id',
                    'suppliership_id',
                    'debit',
                    'credit'
                ]
            ]);

        foreach($self as $id => $miscOperation) {
            $operationFunding = null;

            $condominiumBankAccount = self::computeFundingBankAccount($miscOperation['condo_id']);

            if(!$condominiumBankAccount) {
                throw new \Exception('missing_bank_account', EQ_ERROR_INVALID_CONFIG);
            }

            foreach($miscOperation['misc_operation_lines_ids'] as $misc_operation_line_id => $miscOperationLine) {
                if(!$miscOperationLine['is_owner'] && !$miscOperationLine['is_supplier'])  {
                    continue;
                }

                $accountingEntryLine = AccountingEntryLine::search([
                        ['condo_id', '=', $miscOperation['condo_id']],
                        ['misc_operation_line_id', '=', $misc_operation_line_id]
                    ])
                    ->first();

                if(!$accountingEntryLine) {
                    throw new \Exception('missing_accounting_entry_line', EQ_ERROR_INVALID_PARAM);
                }

                // #memo - when importing historical data, we must be able to issue a funding in the past
                $issue_date = $miscOperation['posting_date'];
                $due_date = $miscOperation['posting_date'] + 86400 * 15;

                $funding_account_id = $miscOperationLine['account_id'];
                $funding_due_amount = $miscOperationLine['debit'] - $miscOperationLine['credit'];

                $fundings_domain = [
                    ['condo_id', '=', $miscOperation['condo_id']],
                    ['funding_type', '=', 'misc_operation'],
                    ['misc_operation_id', '=', $id]
                ];

                if($miscOperationLine['is_owner']) {
                    $fundings_domain[] = ['ownership_id', '=', $miscOperationLine['ownership_id']];
                }
                elseif($miscOperationLine['is_supplier']) {
                    $fundings_domain[] = ['suppliership_id', '=', $miscOperationLine['suppliership_id']];
                }

                $fundings = Funding::search($fundings_domain)
                    ->read(['due_amount']);

                foreach($fundings as $funding_id => $funding) {
                    $funding_due_amount -= $funding['due_amount'];
                }

                if(abs($funding_due_amount) < 0.01) {
                    continue;
                }

                if($miscOperationLine['is_owner']) {
                    if(!$miscOperationLine['ownership_id'])  {
                        throw new \Exception('missing_ownership_id', EQ_ERROR_INVALID_PARAM);
                    }
                    // pass-1 : retrieve AEL from execution line, create a funding for the ownership, and assign it to the AEL
                    $ownership_id = $miscOperationLine['ownership_id'];

                    // #memo - Fundings always use Ownership control_account
                    $fundingOwnershipAccount = Account::search([
                            ['condo_id', '=', $miscOperation['condo_id']],
                            ['ownership_id', '=', $ownership_id],
                            ['is_control_account', '=', true]
                        ])
                        ->first();

                    if(!$fundingOwnershipAccount) {
                        throw new \Exception('missing_ownership_accounting_account', EQ_ERROR_INVALID_PARAM);
                    }

                    $funding_account_id = $fundingOwnershipAccount['id'];

                    $operationFunding = Funding::create([
                            'condo_id'                  => $miscOperation['condo_id'],
                            'description'               => $miscOperation['description'],
                            'misc_operation_id'         => $id,
                            'ownership_id'              => $ownership_id,
                            'accounting_account_id'     => $funding_account_id,
                            'accounting_entry_line_id'  => $accountingEntryLine['id'],
                            'bank_account_id'           => $condominiumBankAccount['id'],
                            'issue_date'                => $issue_date,
                            'due_date'                  => $due_date,
                            'due_amount'                => $funding_due_amount,
                            'funding_type'              => 'misc_operation'
                        ])
                        ->first();

                }
                elseif($miscOperationLine['is_supplier']) {
                    if(!$miscOperationLine['suppliership_id'])  {
                        throw new \Exception('missing_suppliership_id', EQ_ERROR_INVALID_PARAM);
                    }

                    $suppliership_id = $miscOperationLine['suppliership_id'];

                    $suppliershipBankAccount = SuppliershipBankAccount::search([
                            ['condo_id', '=', $miscOperation['condo_id']],
                            ['suppliership_id', '=', $suppliership_id],
                            // ['is_primary', '=', true]
                        ])
                        ->read(['bank_account_id'])
                        ->first();

                    $operationFunding = Funding::create([
                            'condo_id'                          => $miscOperation['condo_id'],
                            'description'                       => $miscOperation['description'],
                            'misc_operation_id'                 => $id,
                            'suppliership_id'                   => $suppliership_id,
                            'bank_account_id'                   => $condominiumBankAccount['id'],
                            'counterpart_bank_account_id'       => $suppliershipBankAccount['bank_account_id'] ?? null,
                            'accounting_account_id'             => $funding_account_id,
                            'accounting_entry_line_id'          => $accountingEntryLine['id'],
                            'due_amount'                        => $funding_due_amount,
                            'is_paid'                           => false,
                            'issue_date'                        => $issue_date,
                            'due_date'                          => $due_date,
                            'funding_type'                      => 'misc_operation',
                            // 'payment_reference'                 => $purchaseInvoice['payment_reference'] ?? null,
                            // relay on_hold flag
                            // 'has_payment_on_hold'               => $purchaseInvoice['has_payment_on_hold']
                        ])
                        ->first();
                }

                // pass-2 : attempt to balance created ownership Funding with pending fundings of opposite sign

                $sign = ($funding_due_amount >= 0) ? 1.0 : -1.0;

                // retrieve non-empty fundings relating to the targeted ownership with opposite sign
                $fundings = Funding::search(
                        [
                            ['condo_id', '=', $miscOperation['condo_id']],
                            ['accounting_account_id', '=', $funding_account_id],
                            ['status', '<>', 'balanced'],
                            ['is_cancelled', '=', false],
                            ['remaining_amount', ($sign > 0) ? '<' : '>', 0]
                        ],
                        ['sort' => ['issue_date' => 'asc']]
                    )
                    ->read(['remaining_amount', 'accounting_entry_line_id']);

                foreach($fundings as $funding_id => $funding) {
                    if(!$operationFunding || $operationFunding['id'] === $funding_id) {
                        continue;
                    }

                    $delta = min(
                        abs($funding_due_amount),
                        abs($funding['remaining_amount'])
                    );

                    $signed_delta = $sign * $delta;

                    $fundingAllocationA = FundingAllocation::create([
                            'condo_id'                  => $miscOperation['condo_id'],
                            'amount'                    => -$signed_delta,
                            'receipt_date'              => $miscOperation['posting_date'],
                            'origin_object_class'       => 'finance\accounting\MiscOperation',
                            'origin_object_id'          => $id,
                            'misc_operation_id'         => $id,
                            'accounting_entry_line_id'  => $accountingEntryLine['id'],
                            'funding_id'                => $funding_id
                        ])
                        ->first();

                    $fundingAllocationB = FundingAllocation::create([
                            'condo_id'                  => $miscOperation['condo_id'],
                            'amount'                    => $signed_delta,
                            'receipt_date'              => $miscOperation['posting_date'],
                            'origin_object_class'       => 'finance\accounting\MiscOperation',
                            'origin_object_id'          => $id,
                            'misc_operation_id'         => $id,
                            'accounting_entry_line_id'  => $accountingEntryLine['id'],
                            'funding_id'                => $operationFunding['id'],
                            'linked_payment_id'         => $fundingAllocationA['id']
                        ])
                        ->first();

                    FundingAllocation::id($fundingAllocationA['id'])->update(['linked_payment_id' => $fundingAllocationB['id']]);

                    Funding::id($funding_id)->do('refresh_status');

                    // merge Matching if applicable
                    if($funding['accounting_entry_line_id']) {
                        AccountingEntryLine::id($accountingEntryLine['id'])
                            ->do('attempt_match_with_line', ['accounting_entry_line_id' => $funding['accounting_entry_line_id']]);
                    }

                    $funding_due_amount -= $signed_delta;
                    if(abs($funding_due_amount) < 0.01) {
                        break;
                    }
                }


                Funding::id($operationFunding['id'])->do('refresh_status');

            }
        }
    }

    protected static function onbeforePost($self) {
        $self
            ->do('generate_accounting_entry')
            // create empty opening balance (MiscOp with flag `has_opening_journal` set to true)
            ->do('generate_opening_balance')
            ->do('create_fundings')
            ->do('assign_operation_number')
            ->do('validate_accounting_entry')
            // force refresh name
            ->update(['name' => null]);
    }

    public static function onchange($event, $values) {
        $result = [];

        $condo_id = $event['condo_id'] ?? $values['condo_id'] ?? null;
        $has_opening_journal = $event['has_opening_journal'] ?? $values['has_opening_journal'] ?? null;

        if($condo_id) {
            $journal_type = $has_opening_journal ? 'OPEN' : 'MISC';
            $journal = Journal::search([['condo_id', '=', $condo_id], ['journal_type', '=', $journal_type]])->read(['id', 'name'])->first();
            if($journal) {
                $result['journal_id'] = [
                        'id'    => $journal['id'],
                        'name'  => $journal['name']
                    ];
            }
        }

        if(isset($event['has_opening_journal']) && $condo_id) {
            $journal_type = $event['has_opening_journal'] ? 'OPEN' : 'MISC';
            if($event['has_opening_journal']) {
                // #memo - Opening MiscOp can only be made for first fiscal year
                $firstFiscalYear = FiscalYear::search([
                        ['condo_id', '=', $condo_id],
                        ['is_first', '=', true]
                    ])
                    ->read(['id', 'date_from'])
                    ->first();
                if($firstFiscalYear) {
                    $result['posting_date'] = $firstFiscalYear['date_from'];
                }
            }

            $journal = Journal::search([['condo_id', '=', $condo_id], ['journal_type', '=', $journal_type]])->read(['id', 'name'])->first();

            if($journal) {
                $result['journal_id'] = [
                        'id'    => $journal['id'],
                        'name'  => $journal['name']
                    ];
            }
        }

        return $result;
    }

    public static function candelete($self) {
        $self->read(['status']);
        foreach($self as $miscOperation) {
            if(!in_array($miscOperation['status'], ['pending', 'proforma'])) {
                return ['status' => ['non_removable' => 'Non-draft Document cannot be deleted.']];
            }
        }
        return parent::candelete($self);
    }

    public static function canupdate($self, $values) {
        $self->read(['status']);
        $allowed_fields = ['status', 'name', 'operation_number', 'accounting_entry_id', 'opening_balance_id', 'payment_status', 'has_date_range', 'date_from', 'date_to'];
        foreach($self as $id => $miscOperation) {
            // only allow editable fields
            if(count(array_diff(array_keys($values), $allowed_fields)) > 0) {
                if($miscOperation['status'] !== 'pending') {
                    return ['status' => ['non_editable' => "MiscOperation can only be updated while its status is proforma ({$id})."]];
                }
            }
        }
        return parent::canupdate($self);
    }
}

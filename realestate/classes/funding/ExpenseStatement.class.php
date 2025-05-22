<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace realestate\funding;

use finance\accounting\Account;
use finance\accounting\AccountingEntryLine;
use finance\accounting\FiscalPeriod;
use finance\accounting\Journal;
use realestate\finance\accounting\ReserveFund;
use realestate\ownership\Ownership;
use realestate\property\Apportionment;
use realestate\purchase\accounting\AccountingEntry;
use realestate\purchase\accounting\invoice\InvoiceLine;
use realestate\sale\pay\Funding;

class ExpenseStatement extends \realestate\sale\accounting\invoice\Invoice {

    public static function getName() {
        return 'Expense Statement';
    }

    public static function getDescription() {
        return "An Expense Statement is issued at the end of the fiscal period and allows the co-owners to cover common expenses, as well as to reimburse any private charges they may have.";
    }

    public static function getColumns() {
        return [
            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcName',
                'description'       => "Short description of the request execution.",
                'store'             => true
            ],

            /* from finance\accounting\invoice\Invoice: */
            // 'condo_id'
            // 'fiscal_year_id'
            // 'fiscal_period_id'
            // 'accounting_entry_id'
            // 'emission_date'
            // 'due_date'

            'invoice_type' => [
                'type'              => 'string',
                'description'       => 'Document type (fund requests are handled as sale invoices).',
                'default'           => 'expense_statement',
                'readonly'          => true
            ],

            'fiscal_period_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\FiscalPeriod',
                'description'       => "Period of the fiscal year the invoice statement relates to.",
                'help'              => "Posting date is automatically assigned on the last day of the period.",
                'onupdate'          => 'onupdateFiscalPeriodId',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['fiscal_year_id', '=', 'object.fiscal_year_id']]
            ],

            /* from sale\accounting\invoice\Invoice: */
            // 'funding_id'

            'customer_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\customer\Customer',
                'description'       => 'There is no customer for fund requests.',
            ],

            'invoice_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\funding\ExpenseStatementOwnerLine',
                'foreign_field'     => 'invoice_id',
                'description'       => 'Detailed lines of the invoice.',
                'ondetach'          => 'delete'
            ],

            /* additional fields*/

            'statement_bank_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\bank\BankAccount',
                'description'       => 'Bank account to use for the request.',
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'statement_owners_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\funding\ExpenseStatementOwner',
                'foreign_field'     => 'expense_statement_id',
                'description'       => "List of Owners Statements.",
                'ondelete'          => 'cascade'
            ],

            'fundings_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\sale\pay\Funding',
                'foreign_field'     => 'expense_statement_id',
                'description'       => 'The fundings that relate to the execution (sale invoice).'
            ],

            'common_total' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Total amount assigned from common expenses.'
            ],

            'private_total' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Total amount assigned from common expenses.'
            ],

            'assigned_delta' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Rounding delta between allocation and expenses, if any.'
            ],

            'schema' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'usage'             => 'application/json',
                'function'          => 'calcSchema',
                'help'              => 'This field is not intended to be stored and can safely be computed at any time since its relies on immutable data.'
            ]

        ];
    }

    public static function getWorkflow() {
        return [
            'proforma' => [
                'description' => 'Draft invoice, pending and still waiting to be completed.',
                'icon' => 'edit',
                'transitions' => [
                    'validate' => [
                        'description' => 'Update the invoice status based on the `invoice` field. Assign invoice number, generate accounting entries and validate accounting entries.',
                        'policies'    => [
                            'can_be_invoiced'
                        ],
                        'onbefore'  => 'onbeforeInvoice',
                        'status'    => 'invoice',
                    ]
                ],
            ],
            'invoice' => [
                'description' => 'Invoice can no longer be modified and can be sent to the customer.',
                'icon' => 'receipt_long',
                'transitions' => [
                    'cancel' => [
                        'description'   => 'Set the invoice and receivables statuses as cancelled.',
                        'onafter'       => 'onafterCancel',
                        'status'        => 'cancelled',
                    ]
                ],
            ],
            'cancelled' => [
                'description' => 'The invoice is cancelled. There are no transitions available.',
                'icon' => 'cancel',
                'transitions' => []
            ],
        ];
    }

    public static function getActions() {
        return array_merge(parent::getActions(), [
            'generate_statement' => [
                'description'   => 'Generate the request lines according to the property lots of the condominium and their respective shares.',
                'policies'      => ['can_generate_statement'],
                'function'      => 'doGenerateStatement'
            ],
            'generate_fundings' => [
                'description'   => 'Generate fundings for each involved ownership.',
                'policies'      => [],
                'function'      => 'doGenerateFundings'
            ]
        ]);
    }

    public static function getPolicies(): array {
        return array_merge(parent::getPolicies(), [
            'has_mandatory_data' => [
                'description' => 'Checks & validate values required for activation.',
                'function'    => 'policyHasMandatoryData'
            ],
            'can_cancel' => [
                'description' => 'Verifies that there are no invoiced executions.',
                'function'    => 'policyCanCancel'
            ],
            'can_generate_statement' => [
                'description' => 'Verifies that the allocation of a fund request can still be updated.',
                'function'    => 'policyCanGenerateStatement'
            ],
            'is_balanced' => [
                'description' => 'Verifies that request amount matches allocated amount.',
                'function'    => 'policyIsBalanced'
            ]
        ]);
    }

    public static function policyCanCancel($self): array {
        $result = [];
        $self->read(['status']);
        foreach($self as $id => $expenseStatement) {
            if($expenseStatement['status'] == 'cancelled') {
                $result[$id] = [
                    'invalid_status' => 'Already cancelled.'
                ];
                continue;
            }
        }
        return $result;
    }

    public static function policyCanGenerateStatement($self): array {
        $result = [];
        $self->read(['status']);
        foreach($self as $id => $expenseStatement) {
            if($expenseStatement['status'] != 'proforma') {
                $result[$id] = [
                    'invalid_status' => 'Invoiced statement cannot be re-generated.'
                ];
                continue;
            }
            // #todo - check that ownerships are set and continuous for all involved property lots of the period
        }
        return $result;
    }

    public static function onupdateFiscalPeriodId($self) {
        $self->read(['fiscal_period_id' => ['date_from', 'date_to']]);
        foreach($self as $id => $expenseStatement) {
            if($expenseStatement['fiscal_period_id']) {
                self::id($id)->update(['posting_date' => $expenseStatement['fiscal_period_id']['date_to']]);
            }
        }
    }

    public static function policyHasMandatoryData($self): array {
        $result = [];
        $self->read(['condo_id', 'request_date', 'has_date_range', 'date_from', 'date_to', 'request_account_id', 'request_bank_account_id', 'payment_terms_id']);
        foreach($self as $id => $fundRequest) {
            if($fundRequest['has_date_range']) {
                if(!$fundRequest['date_from']) {
                    $result[$id] = [
                        'missing_date_from' => 'The start date of the time range is mandatory.'
                    ];
                }
                if(!$fundRequest['date_to']) {
                    $result[$id] = [
                        'missing_date_to' => 'The end date of the time range is mandatory.'
                    ];
                }
                if($fundRequest['date_from'] > $fundRequest['date_from']) {
                    $result[$id] = [
                        'invalid_date_interval' => 'The end date cannot be before start date.'
                    ];
                }
            }
            elseif(!$fundRequest['request_date']) {
                $result[$id] = [
                    'missing_date' => 'The date of the request is mandatory.'
                ];
            }

            if(!$fundRequest['condo_id']) {
                $result[$id] = [
                    'missing_condominium' => 'The condominium is mandatory.'
                ];
            }
            if(!$fundRequest['request_account_id']) {
                $result[$id] = [
                    'missing_accounting_account' => 'The accounting account is mandatory.'
                ];
            }
            if(!$fundRequest['request_bank_account_id']) {
                $result[$id] = [
                    'missing_bank_account' => 'The bank account is mandatory.'
                ];
            }
            if(!$fundRequest['payment_terms_id']) {
                $result[$id] = [
                    'missing_payment_terms' => 'The payment terms are mandatory.'
                ];
            }
        }
        return $result;
    }

    public static function onbeforeInvoice($self) {
        $self
            ->do('generate_statement')
            ->do('generate_accounting_entries')
            ->do('generate_fundings')
            ->do('assign_invoice_number')
            ->do('validate_accounting_entries');
    }

    /**
     * Generate the ExpenseStatementOwner and ExpenseStatementOwnerLine objects based on accounting entries of the period.
     *
     */
    public static function doGenerateStatement($self) {
        $self->read(['condo_id', 'fiscal_period_id']);
        foreach($self as $id => $expenseStatement) {
            // remove any previously created owner statement
            ExpenseStatementOwner::search(['expense_statement_id', '=', $id])->delete(true);
            $data = self::computeExpenseStatementData($expenseStatement['fiscal_period_id']);
            self::id($id)->update([
                    'private_total'  => $data['private_total'],
                    'common_total'   => $data['common_total'],
                    'assigned_delta' => $data['assigned_delta']
                ]);

            foreach($data['owners'] as $owner) {

                $statementOwner = ExpenseStatementOwner::create([
                        'condo_id'              => $expenseStatement['condo_id'],
                        'fiscal_period_id'      => $expenseStatement['fiscal_period_id'],
                        'expense_statement_id'  => $id,
                        'ownership_id'          => $owner['id'],
                        'nb_days'               => $owner['nb_days'],
                        'date_from'             => $owner['date_from'],
                        'date_to'               => $owner['date_to']
                    ])
                    ->first();

                foreach($owner['lines'] as $line) {
                    // these are invoice lines
                    ExpenseStatementOwnerLine::create([
                            'condo_id'              => $expenseStatement['condo_id'],
                            'statement_owner_id'    => $statementOwner['id'],
                            'invoice_id'            => $id,
                            'ownership_id'          => $owner['id'],
                            'description'           => $line['description'],
                            'apportionment_id'      => $line['apportionment_id'],
                            'account_id'            => $line['account_id'],
                            'property_lot_id'       => $line['property_lot_id'],
                            'total_amount'          => $line['total_amount'] ?? 0.0,
                            'owner_amount'          => $line['owner'] ?? 0.0,
                            'tenant_amount'         => $line['tenant'] ?? 0.0,
                            'vat_amount'            => $line['vat'] ?? 0.0,
                            'date'                  => $line['date'] ?? null,
                            'expense_type'          => $line['expense_type'],
                            'shares'                => $line['shares'] ?? null,
                        ]);
                }

            }
        }
    }


    /**
     * Generates the initial accounting entry.
     *
     * The accounting entry created is meant to be instantly validated (with invoice validation action).
     *
     *
     */
    public static function doGenerateAccountingEntries($self) {
        $self->read([
                'condo_id',
                'assigned_delta',
                'fiscal_year_id',
                'fiscal_period_id' => ['name', 'date_to'],
                'statement_owners_ids' => [
                    'ownership_id' => ['ownership_account_id'],
                    'statement_owner_lines_ids' => [
                        'name',
                        'expense_type',
                        'price',
                        'account_id',
                        'ownership_id'
                    ]
                ]
            ]);

        foreach($self as $id => $statement) {
            // retrieve journal dedicated to purchases
            $journal = Journal::search([['condo_id', '=', $statement['condo_id']], ['code', '=', 'SAL']])->first();
            if(!$journal) {
                trigger_error("APP::unable to find a match for journal PUR for condominium {$statement['condo_id']}", EQ_REPORT_ERROR);
                throw new \Exception("missing_mandatory_journal", EQ_ERROR_INVALID_CONFIG);
            }

            // create the accounting entry for the Expense Statement
            $accountingEntry = AccountingEntry::create([
                    'condo_id'              => $statement['condo_id'],
                    'journal_id'            => $journal['id'],
                    'fiscal_year_id'        => $statement['fiscal_year_id'],
                    'fiscal_period_id'      => $statement['fiscal_period_id']['id'],
                    // #memo - if necessary, entry_date will be reassigned based on selected fiscal year and matching period (so that dates remain in ascending order)
                    'entry_date'            => $statement['fiscal_period_id']['date_to'],
                    'origin_object_class'   => self::getType(),
                    'origin_object_id'      => $id
                ])
                ->first();

            // #todo - allow customization of label
            $description = 'Décompte de charge ' . $statement['fiscal_period_id']['name'];

            // handle assigned delta (diff between paid to provider and reinvoiced to owners - rounding account), if any
            /*
            // this unbalances the entry: it has to be taken under account at the end of the fiscal year
            $assigned_delta = round($statement['assigned_delta'], 2);
            if($assigned_delta != 0.0) {
                // find the account based on operation_assignment
                $roundingAccount = Account::search([
                        ['condo_id', '=', $statement['condo_id']],
                        ['operation_assignment', '=', 'rounding_adjustment']
                    ])
                    ->first();

                AccountingEntryLine::create([
                        'condo_id'              => $statement['condo_id'],
                        'accounting_entry_id'   => $accountingEntry['id'],
                        'name'                  => $description,
                        'account_id'            => $roundingAccount['id'],
                        'debit'                 => ($assigned_delta > 0.0) ? abs($assigned_delta) : 0.0,
                        'credit'                => ($assigned_delta < 0.0) ? abs($assigned_delta) : 0.0
                    ]);
            }
            */

            foreach($statement['statement_owners_ids'] as $statement_owner_id => $statementOwner) {

                // sum of field `price`, to be accounted on ownership debit
                $total_ownership = 0.0;

                foreach($statementOwner['statement_owner_lines_ids'] as $line_id => $statementLine) {

                    // ignore lines relating to use of reserve funds (already made when importing invoice)
                    if($statementLine['price'] <= 0.0) {
                        continue;
                    }

                    $total_ownership += $statementLine['price'];

                    // create the credit line on the expense account
                    AccountingEntryLine::create([
                            'condo_id'              => $statement['condo_id'],
                            'accounting_entry_id'   => $accountingEntry['id'],
                            'name'                  => $description,
                            'account_id'            => $statementLine['account_id'],
                            'invoice_line_id'       => $line_id,
                            'debit'                 => 0.0,
                            'credit'                => $statementLine['price']
                        ]);
                }

                // create a single debit line on the ownership account
                AccountingEntryLine::create([
                        'condo_id'              => $statement['condo_id'],
                        'accounting_entry_id'   => $accountingEntry['id'],
                        'name'                  => $description,
                        'account_id'            => $statementOwner['ownership_id']['ownership_account_id'],
                        'debit'                 => $total_ownership,
                        'credit'                => 0.0
                    ]);
            }

            self::id($id)->update(['accounting_entry_id' => $accountingEntry['id']]);
        }
    }


    public static function doGenerateFundings($self) {

        /* from finance\accounting\invoice\Invoice: */
        // 'condo_id'
        // 'fiscal_year_id'
        // 'fiscal_period_id'
        // 'accounting_entry_id'
        // 'emission_date'
        // 'due_date'


        $self->read([
                'name',
                'posting_date',
                'due_date',
                'statement_bank_account_id',
                'fiscal_year_id' => ['date_from'],
                'fiscal_period_id' => ['date_from'],
                'condo_id' => ['code'],
                'statement_owners_ids' => [
                    'ownership_id' => ['code'],
                    'statement_owner_lines_ids' => [
                        'price'
                    ]
                ]
            ]);

        foreach($self as $id => $expenseStatement) {
            foreach($expenseStatement['statement_owners_ids'] as $statement_owner_id => $statementOwner) {
                $ownership_id = $statementOwner['ownership_id']['id'];

                $due_amount = 0.0;
                foreach($statementOwner['statement_owner_lines_ids'] as $line_id => $ownerLine) {
                    // use both positive and negative amounts
                    $due_amount += $ownerLine['price'];
                }

                // a funding cannot be issued nor due in the past
                $issue_date = max(strtotime('today'), $expenseStatement['posting_date']);
                $due_date = $expenseStatement['due_date'];

                Funding::create([
                        'condo_id'                  => $expenseStatement['condo_id']['id'],
                        'description'               => $expenseStatement['name'],
                        'funding_type'              => 'expense_statement',
                        'expense_statement_id'      => $id,
                        'ownership_id'              => $ownership_id,
                        'bank_account_id'           => $expenseStatement['statement_bank_account_id'],
                        'issue_date'                => $issue_date,
                        'due_date'                  => $due_date,
                        'due_amount'                => $due_amount
                    ]);

            }
        }
    }


    public static function onchange($event, $values): array {
        $result = [];
        if(isset($event['fiscal_period_id'])) {
            $fiscalPeriod = FiscalPeriod::id($event['fiscal_period_id'])->read(['date_to'])->first();
            if($fiscalPeriod) {
                $result['posting_date'] = $fiscalPeriod['date_to'];
            }
        }
        return $result;
    }

    /**
     * Fetch all required data to generate a 2 levels linearized structures [owners, lines].
     *
     * This result is meant to be used to generate ExpenseStatementOwner and ExpenseStatementOwnerLines before the Statement is invoiced.
     * Afterward, the consistency might be broken (in case of reopening of a fiscal period and changes impacting the expenses),
     * so ExpenseStatementOwner and ExpenseStatementOwnerLines will be the only source for generating `schema` and dependent documents.
     *
     * Build a resulting map with the following hierarchy:
     *  ownership > property_lot > {expense type} > apportionment > account > {share}
     *
     *  - {expense type} : is based on on the code of the account associated to each accounting entry line, and can be amongst these: 'private_expense', 'common_expense', 'reserve_fund'
     *  - {share} : there are always two keys: 'owner' and 'tenant'. For reserve_fund, owner is always 100.
     *  - apportionment : for private expense, we usa a fake apportionment ('0'), so that the structure remains the same in all situations.
     *
     */
    private static function computeExpenseStatementData($fiscal_period_id) {
        $result = [];

        $fiscalPeriod = FiscalPeriod::id($fiscal_period_id)
            ->read(['condo_id', 'date_from', 'date_to'])
            ->first();

        if(!$fiscalPeriod) {
            throw new Exception('unknown_period', EQ_ERROR_INVALID_PARAM);
        }

        // compute number of calendar days within the period
        $nb_days = round(($fiscalPeriod['date_to'] - $fiscalPeriod['date_from']) / 86400, 0) + 1;

        // #todo - il y a la notion de lots groupés - à faire une map, par propriétaire, par lot :
        // on peut le faire par groupe de lots (si un lot est marqué avec primary_lot_id, il peut être ignoré pour les calculs)

        // fetch relevant accounting entries that apply to the chosen period
        $accountingEntries = AccountingEntry::search([
                ['fiscal_period_id', '=', $fiscalPeriod['id']],
                ['status', '=', 'validated'],
                ['invoice_id', '<>', null]
            ])
            ->read([
                'entry_date',
                'entry_lines_ids' => ['invoice_line_id', 'account_id', 'account_code', 'debit', 'credit']
            ]);


        /*
            Prefetch required objects (condominium configuration)
        */

        $ownerships = Ownership::search(['condo_id', '=', $fiscalPeriod['condo_id']])
            ->read(['name', 'date_from', 'date_to', 'property_lots_ids'])
            ->get();


        // compute nb_days of Ownership to apply prorata
        // #memo - we assume ownerships remain consistent and that a property lot is always owned by someone (for a same property lot, sum of ownerships nb_days matches the nb_days of the period)
        foreach($ownerships as $ownership_id => $ownership) {
            $start = max($fiscalPeriod['date_from'], $ownership['date_from'] ?? $fiscalPeriod['date_from']);
            $end   = min($fiscalPeriod['date_to'], $ownership['date_to'] ?? $fiscalPeriod['date_to']);
            $ownerships[$ownership_id]['nb_days'] = ($start <= $end) ? (($end-$start)/86400 + 1) : 0;
        }

        // retrieve applicable reserve funds
        $reserveFunds = ReserveFund::search(['condo_id', '=', $fiscalPeriod['condo_id']])->read(['expense_account_code', 'fund_account_id', 'expense_account_id', 'apportionment_id']);
        $map_reserve_funds = [];
        foreach($reserveFunds as $id => $reserveFund) {
            $map_reserve_funds[$reserveFund['expense_account_code']] = $reserveFund;
        }

        // map all condo apportionment by property lot
        $map_apportionments = [];
        $apportionments = Apportionment::search(['condo_id', '=', $fiscalPeriod['condo_id']])
            ->read(['name', 'total_shares', 'apportionment_shares_ids' => ['property_lot_id', 'property_lot_shares']])
            ->get();

        foreach($apportionments as $apportionment_id => $apportionment) {
            $map_apportionments[$apportionment_id] = [];
            foreach($apportionment['apportionment_shares_ids'] as $apportionment_share_id => $apportionmentShare) {
                $map_apportionments[$apportionment_id][$apportionmentShare['property_lot_id']] = $apportionmentShare['property_lot_shares'];
            }
        }

        $map_accounts_ids = [];
        $map_property_lots_ids = [];

        $map_result = [];

        // We need to keep track of the delta between the total entries and the actual distributed total (on which rounding operations are applied)

        /**
         * @var float $common_total
         * Amount that has been splitted in the current statement, without deferred expenses and deducing reserve funds usage, cumulating non-rounded values.
         */
        $common_total = 0.0;
        /**
         * @var float $delta_total
         * Total of diffs between line amounts and assigned amounts, considering deferred expenses, cumulating rounded values.
         * This value is used for computing assigned_delta.
         */
        $delta_total = 0.0;
        /**
         * @var float $private_total
         * Total amount of private expenses in the current statement (all owners included).
         */
        $private_total = 0.0;

        // pass-1 - identify private expenses that have been reinvoiced
        $map_private_expenses = [];
        foreach($accountingEntries as $accountingEntry) {
            foreach($accountingEntry['entry_lines_ids'] as $accountingEntryLine) {
                if(substr($accountingEntryLine['account_code'], 0, 3) === '643' && $accountingEntryLine['credit'] > 0) {
                    $map_private_expenses[$accountingEntryLine['invoice_line_id']] = true;
                }
            }
        }

        // pass-2 - handle all expenses
        foreach($accountingEntries as $accountingEntry) {
            if($accountingEntry['entry_date'] < $fiscalPeriod['date_from'] || $accountingEntry['entry_date'] > $fiscalPeriod['date_to']) {
                throw new \Exception('out_of_range_entry', EQ_ERROR_INVALID_CONFIG);
            }
            foreach($accountingEntry['entry_lines_ids'] as $accountingEntryLine) {

                // 1) private expense
                // #todo - handle energy/water consumption in a distinct manner (different in section in the statement : `consumptions`)
                /*
                encodage des factures sur le compte correspondant à l'énergie consommée 61200
                + utilisation d'un compte dédié au décomptes de consommation (compteur) 61240
                + création d'un total consommations privatives
                */
                // #memo - consider both debit and credit lines here (to void already reinvoiced private expenses)
                if(substr($accountingEntryLine['account_code'], 0, 3) === '643') {
                    //skip private expense that have been reinvoiced
                    // ceci ne fonctionne pas dans le cas d'aller-retours multiples (annuler, recréeer)
                    if(isset($map_private_expenses[$accountingEntryLine['invoice_line_id']])) {
                        continue;
                    }
                    // #todo - pour les consommations on n'a pas d'invoice line, mais il faut un lien avec des lignes de relevés (format compatible avec InvoiceLine ? ConsumptionLine)
                    $invoiceLine = InvoiceLine::id($accountingEntryLine['invoice_line_id'])->read([
                            'description', 'vat_rate', 'owner_share', 'tenant_share', 'ownership_id', 'property_lot_id',
                            'invoice_id' => ['posting_date']
                        ])
                        ->first();

                    if(!$invoiceLine) {
                        throw new \Exception('missing_mandatory_invoice_line', EQ_ERROR_INVALID_CONFIG);
                    }

                    $ownership_id = $invoiceLine['ownership_id'];
                    $property_lot_id = $invoiceLine['property_lot_id'];

                    $amount = ($accountingEntryLine['debit'] > 0) ? $accountingEntryLine['debit'] : -$accountingEntryLine['credit'];

                    $private_total += $amount;

                    if(!isset($map_result[$ownership_id][$property_lot_id]['private_expense'][0][$accountingEntryLine['account_id']])) {
                        $map_result[$ownership_id][$property_lot_id]['private_expense'][0][$accountingEntryLine['account_id']] = [];
                    }

                    $amount_vat = round($amount * $invoiceLine['vat_rate'], 2);
                    $amount_owner  = round($amount * ($invoiceLine['owner_share'] / 100), 2);
                    $amount_tenant = round($amount * (1 - $invoiceLine['owner_share'] / 100), 2);
                    $adjust = round($amount - $amount_owner - $amount_tenant, 2);
                    $amount_owner += $adjust;

                    $amount_tenant = round($amount - $amount_owner, 2);

                    $map_result[$ownership_id][$property_lot_id]['private_expense'][0][$accountingEntryLine['account_id']][] = [
                            'owner'         => $amount_owner,
                            'tenant'        => $amount_tenant,
                            'vat'           => $amount_vat,
                            'description'   => $invoiceLine['description'],
                            'date'          => $invoiceLine['invoice_id']['posting_date'] ?? null
                        ];

                    $map_accounts_ids[$accountingEntryLine['account_id']] = true;
                    $map_property_lots_ids[$property_lot_id] = true;
                }
                // 2) common expense
                // #memo - only debit lines for these
                elseif(substr($accountingEntryLine['account_code'], 0, 2) === '61') {
                    $invoiceLine = InvoiceLine::id($accountingEntryLine['invoice_line_id'])->read(['vat_rate', 'apportionment_id', 'owner_share', 'tenant_share'])->first();
                    if(!$invoiceLine) {
                        throw new \Exception('missing_mandatory_invoice_line', EQ_ERROR_INVALID_CONFIG);
                    }

                    $line_amount = ($accountingEntryLine['debit'] > 0) ? $accountingEntryLine['debit'] : -$accountingEntryLine['credit'];
                    $vat_amount = $line_amount * $invoiceLine['vat_rate'];

                    $common_total += $line_amount;

                    $apportionment = $map_apportionments[$invoiceLine['apportionment_id']];

                    foreach($ownerships as $ownership_id => $ownership) {
                        foreach($ownership['property_lots_ids'] as $property_lot_id) {
                            if(!isset($apportionment[$property_lot_id])) {
                                continue;
                            }
                            $prorata = $ownerships[$ownership_id]['nb_days'] / $nb_days;
                            $shares = $apportionment[$property_lot_id];
                            $total_shares = $apportionments[$invoiceLine['apportionment_id']]['total_shares'];
                            $amount = $prorata * ($line_amount * $shares / $total_shares);

                            if(!isset($map_result[$ownership_id][$property_lot_id]['common_expense'][$invoiceLine['apportionment_id']][$accountingEntryLine['account_id']])) {
                                $map_result[$ownership_id][$property_lot_id]['common_expense'][$invoiceLine['apportionment_id']][$accountingEntryLine['account_id']] = [
                                        'shares'        => $shares,
                                        'total_shares'  => $total_shares,
                                        'total_amount'  => $line_amount,
                                        'owner'         => 0.0,
                                        'tenant'        => 0.0,
                                        'vat'           => 0.0
                                    ];
                            }
                            else {
                                $map_result[$ownership_id][$property_lot_id]['common_expense'][$invoiceLine['apportionment_id']][$accountingEntryLine['account_id']]['total_amount'] += $line_amount;
                            }

                            $amount_vat = round($prorata * ($vat_amount * $shares / $total_shares), 2);
                            $amount_owner = round($amount * ($invoiceLine['owner_share'] / 100), 2);
                            $amount_tenant = round($amount * (1 - $invoiceLine['owner_share'] / 100), 2);
                            $adjust = round($amount - $amount_owner - $amount_tenant, 2);
                            $amount_owner += $adjust;

                            $delta_total += $amount - ($amount_owner + $amount_tenant);

                            $map_result[$ownership_id][$property_lot_id]['common_expense'][$invoiceLine['apportionment_id']][$accountingEntryLine['account_id']]['vat'] += $amount_vat;
                            $map_result[$ownership_id][$property_lot_id]['common_expense'][$invoiceLine['apportionment_id']][$accountingEntryLine['account_id']]['owner'] += $amount_owner;
                            $map_result[$ownership_id][$property_lot_id]['common_expense'][$invoiceLine['apportionment_id']][$accountingEntryLine['account_id']]['tenant'] += $amount_tenant;

                            $map_property_lots_ids[$property_lot_id] = true;
                        }
                    }
                    $map_accounts_ids[$accountingEntryLine['account_id']] = true;
                }
                // 3) reserve fund
                // #memo - limit to lines related to use of reserve fund (debit only)
                elseif(substr($accountingEntryLine['account_code'], 0, 4) === '6816' && substr($accountingEntryLine['account_code'], -1) === '1') {

                    // retrieve account according to account_id and ReserveFund
                    $reserveFund = $map_reserve_funds[$accountingEntryLine['account_code']] ?? null;
                    if(!$reserveFund) {
                        trigger_error("APP::unable to retrieve reserve fund with code {$accountingEntryLine['account_code']}", EQ_REPORT_ERROR);
                        throw new \Exception('missing_mandatory_reserve_fund', EQ_ERROR_INVALID_CONFIG);
                    }

                    $line_amount = ($accountingEntryLine['debit'] > 0) ? -$accountingEntryLine['debit'] : $accountingEntryLine['credit'];

                    $common_total += $line_amount;

                    $apportionment_id = $reserveFund['apportionment_id'];
                    $apportionment = $map_apportionments[$apportionment_id];

                    foreach($ownerships as $ownership_id => $ownership) {
                        foreach($ownership['property_lots_ids'] as $property_lot_id) {
                            if(!isset($apportionment[$property_lot_id])) {
                                continue;
                            }
                            $prorata = $ownerships[$ownership_id]['nb_days'] / $nb_days;
                            $shares = $apportionment[$property_lot_id];
                            $total_shares = $apportionments[$apportionment_id]['total_shares'];
                            $amount = $prorata * ($line_amount * $shares / $total_shares);
                            if(!isset($map_result[$ownership_id][$property_lot_id]['reserve_fund'][$apportionment_id][$accountingEntryLine['account_id']])) {
                                $map_result[$ownership_id][$property_lot_id]['reserve_fund'][$apportionment_id][$accountingEntryLine['account_id']] = [
                                        'shares'        => $shares,
                                        'total_shares'  => $total_shares,
                                        'total_amount'  => $line_amount,
                                        'owner'         => 0.0,
                                        'tenant'        => 0.0
                                    ];
                            }
                            // use of reserve fund only applies to the owners
                            $map_result[$ownership_id][$property_lot_id]['reserve_fund'][$apportionment_id][$accountingEntryLine['account_id']]['owner'] += round($amount, 2);

                            $map_property_lots_ids[$property_lot_id] = true;
                        }
                    }
                    $map_accounts_ids[$accountingEntryLine['account_id']] = true;
                }
            }
        }

        // generate output response
        $result = [
                'private_total'  => $private_total,
                'common_total'   => $common_total,
                // #memo - a positive amount means that a part of the purchase invoice was not allocated to owners
                'assigned_delta' => round($delta_total, 2),
                'owners'         => []
            ];

        foreach($map_result as $ownership_id => $list_property_lots) {
            if($ownerships[$ownership_id]['nb_days'] <= 0) {
                continue;
            }

            $owner = [
                    'id'                    => $ownership_id,
                    'nb_days'               => $ownerships[$ownership_id]['nb_days'],
                    'date_from'             => $ownerships[$ownership_id]['date_from'] ?? null,
                    'date_to'               => $ownerships[$ownership_id]['date_to'] ?? null,
                    'lines'                 => []
                ];

            // linearize resulting lines
            foreach($list_property_lots as $property_lot_id => $list_expenses) {
                foreach($list_expenses as $expense_type => $list_apportionments) {
                    foreach($list_apportionments as $apportionment_id => $list_accounts) {
                        foreach($list_accounts as $account_id => $account) {
                            // special case for private expense (same account with several entries)
                            if(!isset($account['owner']) && count($account)) {
                                foreach($account as $account_entry) {
                                    $owner['lines'][] = [
                                            'account_id'        => $account_id,
                                            'property_lot_id'   => $property_lot_id,
                                            'apportionment_id'  => $apportionment_id,
                                            'expense_type'      => $expense_type,
                                            'owner'             => $account_entry['owner'],
                                            'tenant'            => $account_entry['tenant'],
                                            'vat'               => $account_entry['vat'],
                                            'description'       => $account_entry['description'] ?? null,
                                            'date'              => $account_entry['date'] ?? null,
                                        ];
                                }
                            }
                            else {
                                $owner['lines'][] = [
                                        'account_id'        => $account_id,
                                        'property_lot_id'   => $property_lot_id,
                                        'apportionment_id'  => $apportionment_id,
                                        'expense_type'      => $expense_type,
                                        'owner'             => $account['owner'],
                                        'tenant'            => $account['tenant'],
                                        'vat'               => $account['vat'] ?? 0.0,
                                        'description'       => $account['description'] ?? null,
                                        'date'              => $account['date'] ?? null,
                                        'shares'            => $account['shares'] ?? null,
                                        'total_amount'      => $account['total_amount'],
                                    ];
                            }
                        }
                    }
                }
            }

            $result['owners'][] = $owner;
        }

        return $result;
    }

    /**
     * Generates a structure that holds all information for generating closing accounting entries and expense statement report (addressed to owners).
     *
     */
    public static function calcSchema($self) {
        $result = [];

        $self->read([
                'id',
                'common_total',
                'private_total',
                'fiscal_period_id' => ['date_from', 'date_to'],
                'statement_owners_ids' => ['schema']
            ]);

        foreach($self as $id => $statement) {

            $response = [
                    'date_from'         => $statement['fiscal_period_id']['date_from'],
                    'date_to'           => $statement['fiscal_period_id']['date_to'],
                    'common_total'      => $statement['common_total'],
                    'private_total'     => $statement['private_total'],
                    'nb_days'           => round(($statement['fiscal_period_id']['date_to'] - $statement['fiscal_period_id']['date_from']) / 86400, 0) + 1,
                    'owners'            => []
                ];

            foreach($statement['statement_owners_ids'] as $statement_owner_id => $statementOwner) {
                $response['owners'][] = $statementOwner['schema'];
            }

            $result[$id] = $response;
        }

        return $result;
    }
}

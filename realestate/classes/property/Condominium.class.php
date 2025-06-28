<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace realestate\property;

use documents\navigation\Node;
use finance\accounting\AccountChart;
use finance\accounting\FiscalYear;
use finance\accounting\Journal;
use finance\bank\Bank;
use fmt\setting\Setting;
use identity\Identity;
use purchase\supplier\Suppliership;

class Condominium extends Identity {

    public function getTable() {
        return 'realestate_property_condominium';
    }

    public static function getColumns() {

        return [
            'object_class' => [
                'type'              => 'string',
                'description'       => 'Class of the current Identity.',
                'help'              => 'This is required in order to display the relational fields accordingly.',
                'default'           => 'realestate\property\Condominium'
            ],

            'condo_id' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'relation'          => ['id'],
                'description'       => "Alias of the `id` field.",
                'help'              => "This is used to comply with the Role assignments at Access Control level.",
                'instant'           => true,
                'store'             => true
            ],

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcName',
                'store'             => true,
                'instant'           => true,
                'description'       => 'The display name of the Condominium.',
            ],

            'code' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcCode',
                'store'             => true,
                'readonly'          => true,
                'description'       => 'The unique code of the Condominium, for global identification.',
            ],

            'managing_agent_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\management\ManagingAgent',
                'description'       => "The managing agent currently managing the condominium.",
                'help'              => "The managing agent or 'Syndic', is in charge of the condominium, and can be a single person or an agency."
            ],

            'management_contracts_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\management\ManagementContract',
                'foreign_field'     => 'condo_id',
                'description'       => 'List of management contracts of the condominium.'
            ],

            'role_assignments_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'hr\role\RoleAssignment',
                'foreign_field'     => 'condo_id',
                'description'       => 'List of employees assigned to the management of the condominium.'
            ],

            // #todo - this does not seem useful
            'total_shares' => [
                'type'              => 'integer',
                'description'       => "The total number of shares of the ownership.",
                'default'           => 1000
            ],

            'construction_permit_date' => [
                'type'              => 'date',
                'description'       => 'Date at which the permit was issued.'
            ],

            'construction_start_date' => [
                'type'              => 'date',
                'description'       => 'Date at which the construction started.'
            ],

            'construction_compliance_date' => [
                'type'              => 'date',
                'description'       => 'Date at which the compliancy documentation was issued.'
            ],

            'construction_completion_date' => [
                'type'              => 'date',
                'description'       => 'Date at which the construction finished.'
            ],

            'condo_creation_date' => [
                'type'              => 'date',
                'description'       => 'Date at which the condominium was constituted.'
            ],

            'condo_regulations_date' => [
                'type'              => 'date',
                'description'       => 'Date of the latest update of the condominium regulations.'
            ],

            'cadastral_number' => [
                'type'              => 'string',
                'description'       => 'Number of the cadastral register of the property.',
            ],

            'fiscal_year_start' => [
                'type'              => 'date',
                'description'       => 'Date at which the first regular fiscal year started or is planned.',
                'help'              => 'In some cases, the first year might start earlier or after that date.'
            ],

            'fiscal_year_end' => [
                'type'              => 'date',
                'description'       => 'Date at which the first fiscal year ends.',
                'help'              => 'This date reflects the initial notary deed but can be changed in general assembly (only day and month are considered).'
            ],

            'fiscal_period_frequency' => [
                'type'              => 'string',
                'selection'         => [
                    'Q' => 'Quarterly',
                    'T' => 'Tertially' ,
                    'S' => 'Semi-Annually',
                    'A' => 'Annually'
                ],
                'description'       => 'List of employees assigned to the management of the condominium.',
                'default'           => 'Q'
                /*
                Quarterly (3 months)	4	Q (Q1, Q2, Q3, Q4)
                Tertially (4 months)	3	T (T1, T2, T3)
                Semi-Annually (6 months)2	S (S1, S2)
                Annually (12 months)	1	A (A1)
                */
            ],

            'expense_management_mode' => [
                'type'               => 'string',
                'selection'          => [
                        'real_expenses',
                        'provisions'
                    ],
                'description'       => 'Management mode foc Condominium expenses.',
                'help'              => "Defines how common charges are handled within the condominium.
                    - In 'real_expenses' mode, no provisions are called in advance: charges are recorded as actual expenses and paid using the working capital, and settled during each period.
                    - In 'provisions' mode, regular fund calls are made in advance and settled at the end of the fiscal year through a global expense statement. The working capital is usually minimal.",
                'default'           => 'real_expenses'
            ],

            'account_chart_id' => [
                'type'              => 'many2one',
                'description'       => "The Chart of accounts assigned to the Condominium.",
                'foreign_object'    => 'finance\accounting\AccountChart',
                'domain'            => ['condo_id', '=', 'object.id']
                // 'readonly'          => true
            ],

            'fiscal_years_ids' => [
                'type'              => 'one2many',
                'description'       => "List of fiscal years related to the condominium.",
                'foreign_object'    => 'finance\accounting\FiscalYear',
                'foreign_field'     => 'condo_id'
            ],

            'current_fiscal_year_id' => [
                'type'              => 'many2one',
                'description'       => "Current `open` fiscal year assigned to the condominium.",
                'help'              => "This value is assigned at fiscal year opening.",
                'foreign_object'    => 'finance\accounting\FiscalYear',
                'onupdate'          => 'onupdateCurrentFiscalYearId',
            ],

            'common_areas_ids' => [
                'type'              => 'one2many',
                'description'       => "List of common areas in the condominium.",
                'foreign_object'    => 'realestate\property\CommonArea',
                'foreign_field'     => 'condo_id'
            ],

            'apportionments_ids' => [
                'type'              => 'one2many',
                'description'       => "The apportionment keys relating to the condominium.",
                'foreign_object'    => 'realestate\property\Apportionment',
                'foreign_field'     => 'condo_id'
            ],

            'setting_values_ids' => [
                'type'              => 'one2many',
                'description'       => "The apportionment keys relating to the condominium.",
                'foreign_object'    => 'fmt\setting\SettingValue',
                'foreign_field'     => 'condo_id'
            ],

            'setting_sequences_ids' => [
                'type'              => 'one2many',
                'description'       => "The apportionment keys relating to the condominium.",
                'foreign_object'    => 'fmt\setting\SettingSequence',
                'foreign_field'     => 'condo_id'
            ],

            'ownerships_ids' => [
                'type'              => 'one2many',
                'description'       => "The ownerships of the condominium.",
                'foreign_object'    => 'realestate\ownership\Ownership',
                'foreign_field'     => 'condo_id'
            ],

            'property_lots_ids' => [
                'type'              => 'one2many',
                'description'       => "The property lots of the condominium.",
                'foreign_object'    => 'realestate\property\PropertyLot',
                'foreign_field'     => 'condo_id'
            ],

            'bank_accounts_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\bank\CondominiumBankAccount',
                'foreign_field'     => 'condo_id',
                'description'       => 'List of the bank account of the Condominium.',
                'domain'            => ['owner_identity_id', '=', 'object.identity_id'],
                'ondetach'          => 'delete',
                'order'             => 'id',
                'sort'              => 'asc'
            ],

            'suppliers_ids' => [
                'type'              => 'many2many',
                'description'       => "Suppliers with which Condominium has (or had) one or more contracted services.",
                'foreign_object'    => 'purchase\supplier\Supplier',
                'foreign_field'     => 'condominiums_ids',
                'rel_table'         => 'purchase_supplier_suppliership',
                'rel_foreign_key'   => 'supplier_id',
                'rel_local_key'     => 'condo_id'
            ],

            'condo_funds_ids' => [
                'type'              => 'one2many',
                'description'       => "Funds allocated by the condominium.",
                'foreign_object'    => 'realestate\finance\accounting\CondoFund',
                'foreign_field'     => 'condo_id'
            ],

            'status' => [
                'type'              => 'string',
                'description'       => 'Current status of the Condominium.',
                'selection'         => [
                    'pending',
                    'validated'
                ],
                'default'           => 'pending'
            ]
        ];
    }

    public static function getWorkflow() {
        return [
            'pending' => [
                'description' => 'Completed document, waiting to be validated.',
                'icon'        => 'edit',
                'transitions' => [
                    'validate' => [
                        'description' => 'Update the document to `validated`.',
                        'policies'    => ['is_valid'],
                        'onafter'     => 'onafterValidate',
                        'status'      => 'validated'
                    ]
                ]
            ],
            'validated' => [
                'description' => 'Validated document, waiting to be processed.',
                'icon'        => 'done',
                'transitions' => [
                ]
            ]
        ];
    }

    public static function getPolicies(): array {
        return [
            'is_valid' => [
                'description' => 'Verifies that the mandatory values are present for Condominium validation.',
                'function'    => 'policyIsValid'
            ],

            'can_open_fiscal_year' => [
                'description' => 'Verifies that a fiscal year can be opened according to user roles.',
                'function'    => 'policyCanOpenFiscalYear'
            ],
            'can_create_draft_fiscal_year' => [
                'description' => 'Verifies that a fiscal year can be opened according to user roles.',
                'function'    => 'policyCanCreateDraftFiscalYear'
            ]
        ];
    }

    public static function getActions() {
        return array_merge(parent::getActions(), [
            'open_fiscal_year' => [
                'description'   => 'Open the fiscal year.',
                'policies'      => ['can_open_fiscal_year'],
                'function'      => 'doOpenFiscalYear'
            ],
            'create_draft_fiscal_year' => [
                'description'   => 'Create a new fiscal year draft, following the `preopen` one.',
                'policies'      => ['can_create_draft_fiscal_year'],
                'function'      => 'doCreateDraftFiscalYear'
            ],
            'generate_sequences' => [
                'description'   => 'Generate mandatory sequences for Condominium (for related entities codes).',
                'policies'      => [],
                'function'      => 'doGenerateSequences'
            ],
            'generate_account_chart' => [
                'description'   => 'Generate mandatory default Chart of accounts for Condominium.',
                'policies'      => [],
                'function'      => 'doGenerateAccountChart'
            ],
            'generate_journals' => [
                'description'   => 'Generate mandatory default Chart of accounts for Condominium.',
                'policies'      => [],
                'function'      => 'doGenerateJournals'
            ],
            'generate_folders' => [
                'description'   => 'Generate default folders for Documents repository.',
                'policies'      => [],
                'function'      => 'doGenerateFolders'
            ],
            'sync_from_identity' => [
                'description'   => 'Force sync values from related identity.',
                'function'      => 'doSyncFromIdentity'
            ]
        ]);
    }

    public static function onupdateIdentityId($self) {
        $self->read(['identity_id']);
        foreach($self as $id => $condominium) {
            if($condominium['identity_id']) {
                Identity::id($condominium['identity_id'])->update(['condominium_id' => $id]);
            }
        }
    }

    public static function doSyncFromIdentity($self, $orm) {
        // sync bank accounts
        $self->read(['identity_id' => ['bank_accounts_ids' => ['bank_account_bic']]]);
        foreach($self as $id => $condominium) {
            foreach($condominium['identity_id']['bank_accounts_ids'] as $bank_account_id => $bankAccount) {
                $bank = Bank::search(['bic', '=', $bankAccount['bank_account_bic']])->first();
                if($bank) {
                    // #memo - class Bank inherits from Supplier (considered as "financial services supplier")
                    $suppliership = Suppliership::search([['condo_id', '=', $id], ['supplier_id', '=', $bank['id']]])->first();
                    if(!$suppliership) {
                        Suppliership::create(['condo_id' => $id, 'supplier_id' => $bank['id']]);
                    }
                }
            }
        }
        parent::doSyncFromIdentity($self, $orm);
    }


    protected static function policyIsValid($self) {
        $result = [];
        $self->read(['condo_id', 'managing_agent_id']);
        foreach($self as $id => $condominium) {

            if(!$condominium['condo_id']) {
                $result[$id] = [
                    'missing_cond_id' => 'The condominium must be provided.'
                ];
            }

            if(!$condominium['managing_agent_id']) {
                $result[$id] = [
                    'missing_managing_agent_id' => 'The managing agent must be provided.'
                ];
            }

            // #todo - check which of these must be mandatory
            /*

            'construction_permit_date' => [
            'construction_start_date' => [
            'construction_compliance_date' => [
            'construction_completion_date' => [
            'condo_creation_date' => [
            'condo_regulations_date' => [
            'cadastral_number' => [
            'fiscal_year_start' => [
            'fiscal_year_end' => [

            */


        }
        return $result;
    }

    protected static function policyCanOpenFiscalYear($self, $user_id) {
        $result = [];
        /** @var \fmt\access\AccessController */

        #todo - find a generic way to check user against Roles
        ['access' => $access] = \eQual::inject(['access']);

        foreach($self as $id => $condominium) {
            if(!$access->userHasCondoRole($user_id, ['manager', 'accountant'], $id)) {
                $result[$id] = [
                    'not_allowed' => 'User missing mandatory role.'
                ];
            }
        }

        return $result;
    }

    protected static function policyCanCreateDraftFiscalYear($self, $user_id) {
        $result = [];
        /** @var \fmt\access\AccessController */
        /*
        // #todo - idem (see above)
        ['access' => $access] = \eQual::inject(['access']);

        foreach($self as $id => $condominium) {
            if(!$access->userHasCondoRole($user_id, ['manager', 'accountant'], $id)) {
                trigger_error("APP::user {$user_id} has no role not amongst requested condo role for condo {$id}.", EQ_REPORT_WARNING);
                $result[$id] = [
                    'not_allowed' => "User missing mandatory role."
                ];
            }
        }
        */
        return $result;
    }

    protected static function calcCode($self) {
        $result = [];
        $self->read(['id']);
        foreach($self as $id => $condominium) {
            $result[$id] = str_pad((string) $id, 6, '0', STR_PAD_LEFT);
        }
        return $result;
    }

    /**
     * #memo - Condominium is a PRIVATE entity : only MASTER instance can provide an ID. By convention, PRIVATE ID start at 1.000.001.
     */
    public static function calcName($self) {
        $result = [];
        $self->read(['legal_name', 'code']);
        foreach($self as $id => $condominium) {
            if($condominium['legal_name'] && strlen($condominium['legal_name'])) {
                $result[$id] = $condominium['code'] . ' - ' . $condominium['legal_name'];
            }
        }
        return $result;
    }

    /**
     * Attempt to open the preopen fiscal year of the Condominium.
     */
    protected static function doOpenFiscalYear($self) {
        $self->read(['fiscal_year_start']);

        foreach($self as $id => $condominium) {
            $fiscalYear = FiscalYear::search([
                    ['status', '=', 'preopen'],
                    ['condo_id', '=', $id]
                ])
                ->read(['date_from', 'date_to']);

            if($fiscalYear->count() <= 0) {
                throw new \Exception('missing_preopen_fiscal_year', EQ_ERROR_INVALID_CONFIG);
            }
            elseif($fiscalYear->count() > 1) {
                throw new \Exception('ambiguous_fiscal_year', EQ_ERROR_INVALID_CONFIG);
            }

            $fiscalYear->transition('open');
        }
    }

    /**
     * Create a new draft fiscal year that directly follows the farthest `preopen` one (there should be only one).
     * If a fiscal year already exist, it is overwritten (re-created).
     *
     */
    protected static function doCreateDraftFiscalYear($self) {
        $self->read([
                'fiscal_year_start', 'fiscal_year_end', 'fiscal_period_frequency',
                'construction_compliance_date',
                'condo_creation_date'
            ]);

        // find fiscal year based on current date
        foreach($self as $id => $condominium) {
            if($condominium['fiscal_year_end']) {
                $fiscal_year_end = $condominium['fiscal_year_end'];
            }
            else {
                if(!$condominium['fiscal_year_start']) {
                    throw new \Exception('undefined_fiscal_year_end', EQ_ERROR_INVALID_CONFIG);
                }
                $fiscal_year_end = strtotime('-1 day', strtotime('+1 year', $condominium['fiscal_year_start']));
            }

            $values = [
                'condo_id'                  => $id,
                'fiscal_period_frequency'   => $condominium['fiscal_period_frequency']
            ];

            // remove existing draft, if any
            FiscalYear::search([['status', '=', 'draft'], ['condo_id', '=', $id]])->delete(true);

            // find farthest preopen fiscal year
            $fiscalYear = FiscalYear::search([
                    ['status', '=', 'preopen'],
                    ['condo_id', '=', $id]
                ],
                [
                    'sort'  => ['created' => 'desc'],
                    'limit' => 1
                ])
                ->read(['date_from', 'date_to'])
                ->first();

            // next fiscal year exists: compute date_from and date_to
            if($fiscalYear) {
                $fiscal_year_start = $fiscalYear['date_to'];
                $fiscal_year_start = strtotime('+1 day', $fiscal_year_start);

                $day_end   = intval(date('d', $fiscal_year_end));
                $month_end = intval(date('m', $fiscal_year_end));
                $year_end  = intval(date('Y', $fiscal_year_start));

                $fiscal_year_end = strtotime(sprintf("%d-%02d-%02d", $year_end, $month_end, $day_end));

                if($fiscal_year_end <= $fiscal_year_start) {
                    ++$year_end;
                    $fiscal_year_end = strtotime(sprintf("%d-%02d-%02d", $year_end, $month_end, $day_end));
                }

                $values['date_from'] = $fiscal_year_start;
                $values['date_to'] = $fiscal_year_end;
            }
            // first fiscal year
            else {
                /*
                    // Priority 1: If a start date has been chosen in the General Assembly, we take it
                    IF start_date_chosen IS DEFINED
                        RETURN start_date_chosen

                    // Priority 2: If the General Assembly has already taken place, we take that date
                    ELSE IF first_AG_date IS DEFINED
                        RETURN first_AG_date

                    // Priority 3: If a compliance certificate has been issued, we take that date
                    ELSE IF compliance_certificate_date IS DEFINED
                        RETURN compliance_certificate_date

                */
                if($condominium['fiscal_year_start']) {
                    $values['date_from'] = $condominium['fiscal_year_start'];
                }
                elseif($condominium['condo_creation_date']) {
                    $values['date_from'] = $condominium['condo_creation_date'];
                }
                elseif($condominium['construction_compliance_date']) {
                    $values['date_from'] = $condominium['construction_compliance_date'];
                }
                else {
                    $values['date_from'] = time();
                }
                $values['date_to'] = $fiscal_year_end;
            }

            FiscalYear::create($values);
        }
    }

    public static function onchange($self, $event, $values, $lang) {
        $result = [];

        if(isset($event['fiscal_year_end'])) {
            $date_from = strtotime('-1 year', $event['fiscal_year_end']);
            $result['fiscal_year_start'] = strtotime('+1 day', $date_from);
        }

        return $result;
    }

    /**
     * Upon creation of a condominium, it is necessary to create sequences for:
     * - owners:            realestate.organization.ownership.sequence      [condo_id]
     * - lots:              realestate.organization.property_lot.sequence   [condo_id]
     * - apportionments:    realestate.organization.apportionment.sequence  [condo_id]
     * - suppliers:         realestate.organization.suppliership.sequence   [condo_id]
     * - purchase invoice:  purchase.accounting.invoice.sequence    [condo_id]
     */
    protected static function doGenerateSequences($self) {
        foreach($self as $id => $condominium) {
            Setting::assert_sequence('realestate', 'organization', 'ownership.sequence', 1, ['condo_id' => $id]);
            Setting::assert_sequence('realestate', 'organization', 'property_lot.sequence', 1, ['condo_id' => $id]);
            Setting::assert_sequence('realestate', 'organization', 'apportionment.sequence', 1, ['condo_id' => $id]);
            Setting::assert_sequence('realestate', 'organization', 'suppliership.sequence', 1, ['condo_id' => $id]);
            Setting::assert_value('finance', 'accounting', 'fiscal_year', date('Y'), ['condo_id' => $id]);
            Setting::assert_value('sale', 'accounting', 'invoice.sequence_format', '%2d{year}/%02d{period}/%05d{sequence}', ['condo_id' => $id]);
            Setting::assert_value('purchase', 'accounting', 'invoice.sequence_format', '%2d{year}/%02d{period}/%05d{sequence}', ['condo_id' => $id]);
            // #memo - sequences for sale and purchase invoices & accounting entries are set in FiscalYear (since they rely on year and period)
        }
    }

    protected static function doGenerateAccountChart($self) {
        foreach($self as $id => $condominium) {
            $accountChart = AccountChart::create([
                    'condo_id'  => $id,
                    'name'      => 'Plan comptable'
                ])
                ->first();
            self::id($id)->update(['account_chart_id' => $accountChart['id']]);
        }
    }

    protected static function doGenerateJournals($self) {
        foreach($self as $id => $condominium) {
            // read 'default' journals (not assigned to any condominium)
            $journals = Journal::search(['condo_id', '=', null])
                ->read([
                    'name',
                    'description',
                    'mnemo',
                    'code',
                    'journal_type',
                    'is_visible'
                ]);

            // duplicate each journal
            foreach($journals as $journal_id => $journal) {
                Journal::create([
                        'condo_id'      => $id,
                        'name'          => $journal['name'],
                        'description'   => $journal['description'],
                        'mnemo'         => $journal['mnemo'],
                        'code'          => $journal['code'],
                        'journal_type'  => $journal['journal_type'],
                        'is_visible'    => $journal['is_visible']
                    ]);
            }
        }
    }

    protected static function doGenerateFolders($self) {
        foreach($self as $id => $condominium) {
            // read 'default' journals (not assigned to any condominium)
            $folders = Node::search(['condo_id', '=', null])
                ->read([
                    'name',
                    'code',
                    'description'
                ]);

            // duplicate each folder/node
            foreach($folders as $folder_id => $folder) {
                Node::create([
                        'condo_id'      => $id,
                        "node_type"     => 'folder',
                        'name'          => $folder['name'],
                        'code'          => $folder['code'],
                        'description'   => $folder['description']
                    ]);
            }
        }
    }

    /**
     * Create mandatory dependencies for new Condominium
     * This is meant to be done only once, at the Condominium validation.
     */
    protected static function onafterValidate($self) {
        $self
            // 1 - create specific sequences for accounting entries, invoices, lots, owners, ...
            ->do('generate_sequences')
            // 2 - create (empty) account chart
            ->do('generate_account_chart')
            // 3 - create journals
            ->do('generate_journals')
            // 4 - create folders
            ->do('generate_folders');
    }

    protected static function onupdateCurrentFiscalYearId($self) {
        $self->read(['current_fiscal_year_id']);
        foreach($self as $id => $condominium) {
            if(!$condominium['current_fiscal_year_id']) {
                continue;
            }

            $fiscalYear = FiscalYear::id($condominium['current_fiscal_year_id'])
                ->read(['date_from', 'date_to'])
                ->first();

            if($fiscalYear) {
                self::id($id)->update(['fiscal_year_start' => $fiscalYear['date_from'], 'fiscal_year_end' => $fiscalYear['date_to']]);
            }
        }
    }

}
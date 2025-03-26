<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace realestate\property;

use finance\accounting\AccountChart;
use finance\accounting\AccountChartTemplate;
use finance\accounting\FiscalYear;
use finance\accounting\FiscalPeriod;
use finance\accounting\Journal;
use fmt\setting\Setting;

class Condominium extends \identity\Organisation {

    public function getTable() {
        return 'realestate_property_condominium';
    }

    public static function getColumns() {

        return [
            'condo_id' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'relation'          => ['id'],
                'description'       => "Alias of the `id` field.",
                'help'              => "This is used to comply with the Role assignments at Access Control level.",
                'instant'           => true,
                'store'             => true
            ],

            'managing_agent_id' => [
                'type'              => 'many2one',
                'description'       => "The managing agent currently managing the condominium.",
                'help'              => "The managing agent or 'Syndic', is in charge of the condominium, and can be a single person or an agency.",
                'foreign_object'    => 'realestate\management\ManagingAgent',
                // 'required'          => true
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
                'description'       => 'Date at which the fiscal year starts.'
            ],

            'fiscal_year_end' => [
                'type'              => 'date',
                'description'       => 'Date at which the fiscal year ends.',
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

            'account_chart_id' => [
                'type'              => 'many2one',
                'description'       => "The Chart of accounts assigned to the Condominium.",
                'foreign_object'    => 'finance\accounting\AccountChart',
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
                'foreign_object'    => 'finance\accounting\FiscalYear'
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

            'bank_account_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\bank\BankAccount',
                'foreign_field'     => 'condo_id',
                'description'       => 'List of the bank account of the organisation',
                'ondetach'          => 'delete',
                'order'             => 'id',
                'sort'              => 'asc'
            ]

        ];
    }

    public static function getPolicies(): array {
        return [
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
        return [
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
            ]
        ];
    }

    public static function policyCanOpenFiscalYear($self, $user_id) {
        $result = [];
        /** @var \fmt\access\AccessController */
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

    public static function policyCanCreateDraftFiscalYear($self, $user_id) {
        $result = [];
        /** @var \fmt\access\AccessController */
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

    /**
     * Attempt to open the preopen fiscal year of the Condominium.
     */
    public static function doOpenFiscalYear($self) {
        $self->read(['fiscal_year_start']);

        foreach($self as $id => $condominium) {
            $fiscalYear = FiscalYear::search([
                    ['status', '=', 'preopen'],
                    ['condo_id', '=', $id]
                ]);

            if($fiscalYear->count() != 1) {
                throw new \Exception('missing_preopen_fiscal_year', EQ_ERROR_INVALID_CONFIG);
            }

            $fiscalYear->transition('open');
        }
    }

    /**
     * Create a new draft fiscal year that directly follows the farthest `preopen` one (there should be only one).
     * If a fiscal year already exist, it is overwritten (re-created).
     *
     */
    public static function doCreateDraftFiscalYear($self) {
        $self->read(['fiscal_year_start', 'fiscal_year_end', 'fiscal_period_frequency', 'current_fiscal_year_id']);

        // find fiscal year based on current date
        foreach($self as $id => $condominium) {
            if(!$condominium['fiscal_year_end']) {
                throw new \Exception('undefined_fiscal_year_end', EQ_ERROR_INVALID_CONFIG);
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

            if($fiscalYear) {
                // computed next fiscal year date_from and date_to
                $fiscal_year_start = $fiscalYear['date_to'];
                $fiscal_year_start = strtotime('+1 day', $fiscal_year_start);

                $day_end   = intval(date('d', $condominium['fiscal_year_end']));
                $month_end = intval(date('m', $condominium['fiscal_year_end']));
                $year_end  = intval(date('Y', $fiscal_year_start));

                $fiscal_year_end = strtotime(sprintf("%d-%02d-%02d", $year_end, $month_end, $day_end));

                if($fiscal_year_end <= $fiscal_year_start) {
                    ++$year_end;
                    $fiscal_year_end = strtotime(sprintf("%d-%02d-%02d", $year_end, $month_end, $day_end));
                }

                $value['previous_fiscal_year_id'] = $fiscalYear['id'];
                $value['date_from'] = $fiscal_year_start;
                $value['date_to'] = $fiscal_year_end;
            }
            // first fiscal year
            else {
                $value['date_from'] = $condominium['fiscal_year_start'];
                $value['date_to'] = $condominium['fiscal_year_end'];
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
     * - the owners:        realestate.main.ownership.sequence [condo_id]
     * - the lots:          realestate.main.property_lot.sequence [condo_id]
     */
    public static function doGenerateSequences($self) {
        foreach($self as $id => $condominium) {
            Setting::assert_sequence('realestate', 'main', "ownership.sequence");
            Setting::assert_sequence('realestate', 'main', "property_lot.sequence");
            Setting::init_sequence('realestate', 'main', "ownership.sequence", ['condo_id' => $id]);
            Setting::init_sequence('realestate', 'main', "property_lot.sequence", ['condo_id' => $id]);
        }
    }

    public static function doGenerateAccountChart($self) {
        foreach($self as $id => $condominium) {
            $accountChart = AccountChart::create([
                    'condo_id'  => $id,
                    'name'      => 'Plan comptable'
                ])
                ->first();
            self::id($id)->update(['account_chart_id' => $accountChart['id']]);
        }
    }

    public static function doGenerateJournals($self) {
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

    /**
     * Create mandatory dependencies for new Condominium
     */
    public static function oncreate($self) {
        $self
            // 1 - create specific sequences for accounting entries, invoices, lots, owners, ...
            ->do('generate_sequences')
            // 2 - create (empty) account chart
            ->do('generate_account_chart')
            // 3 - create journals
            ->do('generate_journals');
    }


}
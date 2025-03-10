<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace realestate\property;

use finance\accounting\FiscalYear;
use finance\accounting\FiscalPeriod;

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
                'required'          => true
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
            ]

        ];
    }

    public static function getActions() {
        return [
            'open_fiscal_year' => [
                'description'   => 'Open the fiscal year.',
                'policies'      => [],
                'function'      => 'doOpenFiscalYear'
            ],
            'create_fiscal_year' => [
                'description'   => 'Open the fiscal year.',
                'policies'      => [],
                'function'      => 'doCreateFiscalYear'
            ]
        ];
    }

    public static function doOpenFiscalYear($self) {
        $self->read(['fiscal_year_start']);
        $today = time();
        // find fiscal year based on current date
        foreach($self as $id => $condominium) {
            if(!$condominium['fiscal_year_start']) {
                throw new \Exception('undefined_fiscal_year', EQ_ERROR_INVALID_CONFIG);
            }
            $fiscalYear = FiscalYear::search([
                    ['date_from', '<=', $today],
                    ['date_to', '>', $today],
                    ['condo_id', '=', $id]
                ]);

            if($fiscalYear->count() != 1) {
                throw new \Exception('missing_current_fiscal_year', EQ_ERROR_UNKNOWN);
            }

            $fiscalYear->transition('open');
        }
    }

    public static function doCreateFiscalYear($self) {
        $self->read(['fiscal_year_start', 'fiscal_period_frequency']);
        $today = time();
        // find fiscal year based on current date
        foreach($self as $id => $condominium) {
            if(!$condominium['fiscal_year_start']) {
                throw new \Exception('undefined_fiscal_year', EQ_ERROR_INVALID_CONFIG);
            }
            $fiscalYear = FiscalYear::search([
                    ['date_from', '>=', $today],
                    ['date_to', '<', $today],
                    ['condo_id', '=', $id]
                ]);

            if($fiscalYear->count() > 0) {
                throw new \Exception('duplicate_fiscal_year', EQ_ERROR_UNKNOWN);
            }

            $fiscal_year_start = mktime(0, 0, 0, date('m', $condominium['fiscal_year_start']), date('d', $condominium['fiscal_year_start']), intval(date('Y', $today)));
            $fiscal_year_end = strtotime('+1 year', $fiscal_year_start);
            $fiscal_year_end = strtotime('-1 day', $fiscal_year_end);

            FiscalYear::create([
                    'condo_id'                  => $id,
                    'fiscal_period_frequency'   => $condominium['fiscal_period_frequency'],
                    'date_from'                 => $fiscal_year_start,
                    'date_to'                   => $fiscal_year_end
                ]);
        }
    }

}